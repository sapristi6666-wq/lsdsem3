<?php
if (!defined('ABSPATH')) exit;

class WPSD_Admin {

  const OPT_KEY = 'wpsd_settings';
  private $template_keys = [
    'admin_new_registration' => ['Admin - Nouvelle inscription', '{{nom}} {{prenom}} {{email}} {{role}} {{plan}} {{admin_url}}'],
    'user_pending'           => ['Utilisateur - Inscription reçue', '{{nom}} {{prenom}} {{email}} {{role}} {{plan}}'],
    'user_approved'          => ['Utilisateur - Compte approuvé', '{{display_name}} {{email}} {{role}} {{reset_url}}'],
    'user_rejected'          => ['Utilisateur - Compte refusé', '{{nom}} {{prenom}} {{email}}'],
    'subscription_active'    => ['Abonnement activé', '{{plan_label}} {{status}} {{account_url}}'],
    'renewal_reminder'       => ['Rappel renouvellement', '{{display_name}} {{date_fr}} {{account_url}}'],
    'admin_created_account'  => ['Admin - Création de compte', '{{first_name}} {{email}} {{reset_url}}'],
  ];

  public function __construct() {
    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('admin_init', [$this, 'admin_settings']);
    add_action('admin_post_wpsd_delete_reservation', [$this, 'handle_delete_reservation']);
  }

  // ==================== MENUS ====================
  public function admin_menu() {
    add_options_page('WP Stripe Dashboard', 'WP Stripe Dashboard', 'manage_options', 'wpsd', [$this, 'admin_page']);
    add_menu_page('Réservations', 'Réservations', 'manage_options', 'wpsd-reservations', [$this, 'reservations_page'], 'dashicons-calendar-alt', 56);
    add_menu_page("Templates d'emails", 'Emails', 'manage_options', 'wpsd-email-templates', [$this, 'email_templates_page'], 'dashicons-email-alt', 57);
    add_menu_page('Validations', 'Validations', 'manage_options', 'wpsd-pending-registrations', [$this, 'pending_registrations_page'], 'dashicons-groups', 55);
  }

    // ==================== SETTINGS ====================
  public function admin_settings() {
    register_setting('wpsd', self::OPT_KEY);
    add_settings_section('wpsd_main', 'Configuration Stripe & Facturation', fn() => print('<p>Configuration Stripe + montants fixes de facturation des séjours.</p>'), 'wpsd');
    $fields = ['stripe_secret_key'=>'Stripe Secret Key','stripe_webhook_secret'=>'Stripe Webhook Secret','success_url'=>'Success URL','cancel_url'=>'Cancel URL','price_member'=>'Stripe Price ID – Membre (50€/Année)','price_family'=>'Stripe Price ID – Couple/Famille (70€/Année)','price_institution'=>'Stripe Price ID – Institution (100€/Année)','invoice_amount_provider'=>'Facture – Montant prestataire (centimes)','invoice_amount_asso'=>'Facture – Montant association (centimes)','association_name'=>'Association – Nom','association_address'=>'Association – Adresse','association_email'=>'Association – Email','association_siret'=>'Association – SIRET','association_logo_id'=>'Association – Logo (ID média)','invoice_prefix'=>'Facture – Préfixe (ex: INV)','brevo_api_key'=>'Brevo – API Key (v3)','brevo_list_id'=>'Brevo – List ID (numérique)','brevo_stripe_list_id'=>'Brevo – List ID (Abonnés Stripe)','brevo_webhook_token'=>'Brevo – Webhook token (secret)'];
    foreach ($fields as $key => $label) {
      add_settings_field($key, $label, function() use ($key) {
        $val = esc_attr(get_option(self::OPT_KEY, [])[$key] ?? '');
        echo "<input type='text' class='regular-text' name='".self::OPT_KEY."[$key]' value='$val' />";
        if (str_contains($key, 'invoice_amount')) echo "<p class='description'>Ex : 3000 = 30€</p>";
      }, 'wpsd', 'wpsd_main');
    }

    // Nouveaux champs pour les prix du parcours
    add_settings_section('wpsd_parcours', 'Prix du parcours', fn() => print('<p>Montants utilisés pour le calcul du prix des parcours itinérants.</p>'), 'wpsd');
    add_settings_field('prix_jour_itinérant', 'Prix par jour (part itinérant)', function() {
      $val = esc_attr(get_option(self::OPT_KEY, [])['prix_jour_itinérant'] ?? '5');
      echo "<input type='number' step='0.01' min='0' class='small-text' name='".self::OPT_KEY."[prix_jour_itinérant]' value='$val' /> €";
    }, 'wpsd', 'wpsd_parcours');
    add_settings_field('prix_jour_association', 'Prix par jour (part association)', function() {
      $val = esc_attr(get_option(self::OPT_KEY, [])['prix_jour_association'] ?? '20');
      echo "<input type='number' step='0.01' min='0' class='small-text' name='".self::OPT_KEY."[prix_jour_association]' value='$val' /> €";
    }, 'wpsd', 'wpsd_parcours');
  }

  public function admin_page() {
    echo '<div class="wrap"><h1>WP Stripe Dashboard</h1><form method="post" action="options.php">';
    settings_fields('wpsd'); do_settings_sections('wpsd'); submit_button();
    echo '</form></div>';
  }

  // ==================== VALIDATIONS ====================
  public function pending_registrations_page() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé.');
    if (isset($_POST['wpsd_make_moderator']) && check_admin_referer('wpsd_make_moderator')) {
      $u = get_userdata((int)($_POST['moderator_user_id'] ?? 0));
      if ($u) { $u->set_role('wpsd_moderator'); echo '<div class="notice notice-success"><p>Utilisateur promu modérateur.</p></div>'; }
    }
    if (isset($_GET['revoke']) && isset($_GET['_wpnonce'])) {
      $u = get_userdata((int)$_GET['revoke']); check_admin_referer('revoke_moderator_' . (int)$_GET['revoke']);
      if ($u && in_array('wpsd_moderator', (array)$u->roles)) { $u->remove_role('wpsd_moderator'); echo '<div class="notice notice-success"><p>Rôle modérateur révoqué.</p></div>'; }
    }
    $active_tab = sanitize_text_field($_GET['tab'] ?? 'itinerant');
    $moderators = get_users(['role' => 'wpsd_moderator']);
    ?>
    <div class="wrap"><h1>Validations des inscriptions</h1>
      <div class="card" style="margin-bottom:20px;padding:15px;background:#f9f9f9;">
        <h3>➕ Ajouter un modérateur</h3>
        <form method="post"><?php wp_nonce_field('wpsd_make_moderator'); ?>
          <select name="moderator_user_id"><?php foreach(get_users(['role__not_in'=>['wpsd_moderator','administrator']]) as $u) echo '<option value="'.$u->ID.'">'.esc_html($u->user_email).'</option>'; ?></select>
          <button type="submit" name="wpsd_make_moderator" class="button button-primary">Donner le rôle modérateur</button>
        </form>
      </div>
      <h2 class="nav-tab-wrapper">
        <?php foreach(['itinerant'=>'Itinérants','passeur'=>'Passeurs','sympathisant'=>'Sympathisants','moderators'=>'Modérateurs ('.count($moderators).')'] as $k=>$l): ?>
          <a href="?page=wpsd-pending-registrations&tab=<?=$k?>" class="nav-tab <?=$active_tab===$k?'nav-tab-active':''?>"><?=$l?></a>
        <?php endforeach; ?>
      </h2>
      <?php if ($active_tab === 'moderators'): ?>
        <div style="background:white;border:1px solid #ddd;border-radius:10px;padding:15px;"><h3>Liste des modérateurs</h3>
          <?php if (empty($moderators)): ?><p>Aucun modérateur.</p><?php else: ?>
            <table class="widefat striped"><thead><tr><th>ID</th><th>Email</th><th>Nom</th><th>Date</th><th>Actions</th></tr></thead><tbody>
              <?php foreach($moderators as $m): ?>
                <tr><td><?=(int)$m->ID?></td><td><?=esc_html($m->user_email)?></td><td><?=esc_html($m->display_name)?></td><td><?=esc_html(date_i18n('d/m/Y',strtotime(get_userdata($m->ID)->user_registered)))?></td>
                <td><a href="<?=wp_nonce_url(add_query_arg(['revoke'=>$m->ID,'tab'=>'moderators']),'revoke_moderator_'.$m->ID)?>" class="button button-small" style="color:#b32d2e;border-color:#b32d2e;" onclick="return confirm('Révoquer ?')">❌ Révoquer</a></td></tr>
              <?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div id="wpsd-pending-container" data-role="<?=esc_attr($active_tab)?>"></div>
        <style>.wpsd-pending-card{background:white;border:1px solid #ddd;border-radius:10px;padding:15px;margin-bottom:15px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;}.wpsd-pending-info{flex:2;}.wpsd-pending-actions{display:flex;gap:10px;}.wpsd-btn-approve{background:#005247;color:white;border:none;padding:8px 16px;border-radius:5px;cursor:pointer;}.wpsd-btn-reject{background:#dc2626;color:white;border:none;padding:8px 16px;border-radius:5px;cursor:pointer;}.wpsd-loading{text-align:center;padding:20px;}.wpsd-empty{text-align:center;padding:40px;background:white;border:1px solid #ddd;border-radius:10px;color:#666;}</style>
        <script>
        (function(){var c=document.getElementById('wpsd-pending-container'),n='<?=wp_create_nonce('wp_rest')?>';function r(){return new URLSearchParams(window.location.search).get('tab')||'itinerant';}function l(t){var o={itinerant:'itinérants',passeur:'passeurs de savoir',sympathisant:'sympathisants'};return o[t]||t;}function e(s){return s?s.replace(/[&<>]/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;'}[m]||m;}):'';}async function p(){var t=r();c.innerHTML='<div class="wpsd-loading">Chargement...</div>';try{var o=await fetch('/wp-json/wpsd/v1/admin/pending-registrations',{headers:{'X-WP-Nonce':n}}),a=await o.json();if(!a.items?.length){c.innerHTML='<div class="wpsd-empty">Aucune inscription en attente.</div>';return;}var i=a.items.filter(function(x){return x.role===t;});if(!i.length){c.innerHTML='<div class="wpsd-empty">Aucune inscription '+l(t)+' en attente.</div>';return;}c.innerHTML='';i.forEach(function(x){var d=document.createElement('div');d.className='wpsd-pending-card';d.innerHTML='<div class="wpsd-pending-info"><strong>'+e(x.prenom)+' '+e(x.nom)+'</strong><br>Email: '+e(x.email)+'<br>Téléphone: '+e(x.phone||'—')+'<br>Offre: '+(x.plan==='member'?'Individuel (50€)':'Famille (70€)')+'<br>Inscrit le: '+new Date(x.created_at).toLocaleDateString()+'</div><div class="wpsd-pending-actions"><button class="wpsd-btn-approve" data-id="'+x.id+'">Valider</button><button class="wpsd-btn-reject" data-id="'+x.id+'">Refuser et rembourser</button></div>';c.appendChild(d);});document.querySelectorAll('.wpsd-btn-approve').forEach(function(b){b.addEventListener('click',async function(){if(!confirm('Valider ?'))return;b.disabled=true;b.textContent='...';try{var r=await fetch('/wp-json/wpsd/v1/admin/approve-registration',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':n},body:JSON.stringify({id:parseInt(b.dataset.id)})}),d=await r.json();d.ok?(alert('Validé !'),p()):(alert(d.error||'Erreur'),b.disabled=false,b.textContent='Valider');}catch(e){alert('Erreur réseau');b.disabled=false;b.textContent='Valider';}});});document.querySelectorAll('.wpsd-btn-reject').forEach(function(b){b.addEventListener('click',async function(){if(!confirm('Refuser ?'))return;b.disabled=true;b.textContent='...';try{var r=await fetch('/wp-json/wpsd/v1/admin/reject-registration',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':n},body:JSON.stringify({id:parseInt(b.dataset.id)})}),d=await r.json();d.ok?(alert('Refusé et remboursé.'),p()):(alert(d.error||'Erreur'),b.disabled=false,b.textContent='Refuser et rembourser');}catch(e){alert('Erreur réseau');b.disabled=false;b.textContent='Refuser et rembourser';}});});}catch(e){c.innerHTML='<div class="wpsd-empty">Erreur de chargement</div>';}}window.addEventListener('popstate',function(){p();});p();})();
        </script>
      <?php endif; ?>
    </div>
    <?php
  }

  // ==================== RÉSERVATIONS ====================
  public function reservations_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $t = WPSD_DB::table_reservations();
    $s = sanitize_text_field($_GET['status'] ?? ''); $where = '1=1'; $args = [];
    if ($s) { $where .= " AND status=%s"; $args[] = $s; }
    $sql = "SELECT * FROM $t WHERE $where ORDER BY created_at DESC LIMIT 500";
    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql,...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
    echo '<div class="wrap"><h1>Réservations</h1><p>Filtrer : ';
    foreach([''=>'Toutes','pending'=>'En attente','approved'=>'Acceptées','rejected'=>'Refusées','canceled'=>'Annulées','completed'=>'Terminées'] as $k=>$l)
      echo '<a style="margin-right:10px" href="'.esc_url($k?add_query_arg('status',$k,admin_url('admin.php?page=wpsd-reservations')):admin_url('admin.php?page=wpsd-reservations')).'">'.esc_html($l).'</a>';
    echo '</p><table class="widefat striped"><thead><tr><th>ID</th><th>Statut</th><th>Type</th><th>Objet</th><th>Dates</th><th>Qté</th><th>Itinérant</th><th>Prestataire</th><th>Confirmations</th><th>Dates</th><th>Notes</th><th>Facture</th><th>Actions</th><th>Créé</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="14">Aucune réservation.</td></tr>'; }
    else foreach($rows as $r) {
      $pu = (int)($r['provider_user_id']??0); $iu = get_userdata((int)($r['itinerant_user_id']??0)); $pu2 = get_userdata($pu);
      $facture = ($r['status']==='completed'&&$pu&&(int)get_user_meta($pu,'is_passeur',true)===1) ? '<a class="button button-small" target="_blank" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsd_invoice&rid='.(int)$r['id'].'&type=html'),WPSD_Invoices::ACTION)).'">Voir</a> <a class="button button-small button-primary" target="_blank" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsd_invoice&rid='.(int)$r['id'].'&type=pdf'),WPSD_Invoices::ACTION)).'">PDF</a>' : '—';
      $del = wp_nonce_url(admin_url('admin-post.php?action=wpsd_delete_reservation&rid='.(int)$r['id'].($s?'&status='.urlencode($s):'')),'wpsd_delete_reservation_'.(int)$r['id']);
      echo '<tr><td>'.(int)$r['id'].'</td><td>'.esc_html($r['status']).'</td><td>'.esc_html($r['kind']).'</td><td>'.esc_html(get_the_title($r['object_id'])?:'#'.$r['object_id']).'</td><td>'.esc_html($r['date_start']).' → '.esc_html($r['date_end']).'</td><td>'.(int)$r['quantity'].'</td><td>'.esc_html($iu?$iu->user_email:'').'</td><td>'.esc_html($pu2?$pu2->user_email:'').'</td><td>P:'.(!empty($r['provider_done'])?'✅':'❌').'<br>I:'.(!empty($r['itinerant_done'])?'✅':'❌').'</td><td>A:'.esc_html($r['approved_at']?:'—').'<br>P:'.esc_html($r['provider_done_at']?:'—').'<br>I:'.esc_html($r['itinerant_done_at']?:'—').'<br>T:'.esc_html($r['completed_at']?:'—').'</td><td>P:'.esc_html($r['provider_note']?:'—').'<br>I:'.esc_html($r['itinerant_note']?:'—').'</td><td>'.$facture.'</td><td><a class="button button-small" style="color:#b32d2e;border-color:#b32d2e;" href="'.esc_url($del).'" onclick="return confirm(\'Supprimer ?\')">Supprimer</a></td><td>'.esc_html($r['created_at']).'</td></tr>';
    }
    echo '</tbody></table></div>';
  }

  public function handle_delete_reservation() {
    if (!current_user_can('manage_options')) wp_die('Forbidden',403);
    $rid = (int)($_GET['rid']??0); check_admin_referer('wpsd_delete_reservation_'.$rid);
    if ($rid<=0) wp_die('RID invalide',400);
    global $wpdb; $wpdb->delete(WPSD_DB::table_reservations(),['id'=>$rid],['%d']);
    wp_redirect(add_query_arg('status',sanitize_text_field($_GET['status']??''),admin_url('admin.php?page=wpsd-reservations'))); exit;
  }

    // ==================== TEMPLATES EMAILS ====================
  public function email_templates_page() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé.');

    if (isset($_POST['wpsd_save_templates']) && check_admin_referer('wpsd_email_templates')) {
      foreach ($this->template_keys as $k => $info) {
        update_option('wpsd_email_subject_'.$k, sanitize_text_field($_POST['subject_'.$k] ?? ''));
        update_option('wpsd_email_body_'.$k, wp_kses_post($_POST['body_'.$k] ?? ''));
      }
      // Purger le transient
      delete_transient('wpsd_email_templates');
      echo '<div class="notice notice-success"><p>Templates des inscriptions mis à jour !</p></div>';
    }

    // Reservation email templates
    if (isset($_POST['wpsd_save_reservation_templates']) && check_admin_referer('wpsd_email_reservation_templates')) {
      $reservation_templates = get_option(WPSD_Data::EMAIL_TEMPLATES_KEY, []);
      foreach (WPSD_Data::EMAIL_TEMPLATES as $event => $info) {
        $reservation_templates[$event . '_subject'] = sanitize_text_field($_POST['reservation_subject_' . $event] ?? '');
        $reservation_templates[$event . '_body']    = wp_kses_post($_POST['reservation_body_' . $event] ?? '');
      }
      update_option(WPSD_Data::EMAIL_TEMPLATES_KEY, $reservation_templates);
      echo '<div class="notice notice-success"><p>Templates des notifications de réservation mis à jour !</p></div>';
    }

    $active_tab = sanitize_text_field($_GET['tab'] ?? 'admin_new_registration');
    $active_group = sanitize_text_field($_GET['group'] ?? 'inscriptions');
    ?>
    <div class="wrap"><h1>Templates d'emails</h1>
      <p class="description">Personnalisez le contenu des emails envoyés automatiquement.</p>

      <h2 class="nav-tab-wrapper">
        <a href="?page=wpsd-email-templates&group=inscriptions&tab=admin_new_registration" class="nav-tab <?=$active_group==='inscriptions'?'nav-tab-active':''?>">Inscriptions</a>
        <a href="?page=wpsd-email-templates&group=reservations&tab=reservation_created" class="nav-tab <?=$active_group==='reservations'?'nav-tab-active':''?>">Réservations</a>
      </h2>

      <?php if ($active_group === 'reservations'): ?>
        <?php $this->render_reservation_email_templates($active_tab); ?>
      <?php else: ?>
        <?php $this->render_inscription_email_templates($active_tab); ?>
      <?php endif; ?>
    </div>
    <?php
  }

  /**
   * Render the tab group for registration/inscription email templates.
   */
  private function render_inscription_email_templates($active_tab) {
    ?>
    <h2 class="nav-tab-wrapper" style="margin-top:8px;">
      <?php foreach ($this->template_keys as $k => $info): ?>
        <a href="?page=wpsd-email-templates&group=inscriptions&tab=<?=$k?>" class="nav-tab <?=$active_tab===$k?'nav-tab-active':''?>"><?=$info[0]?></a>
      <?php endforeach; ?>
    </h2>
    <form method="post" style="margin-top:20px;"><?php wp_nonce_field('wpsd_email_templates'); ?>
      <?php foreach ($this->template_keys as $k => $info): ?>
        <div id="tab-<?=$k?>" style="<?=$active_tab===$k?'':'display:none;'?>">
          <h3>Email : <?=$info[0]?></h3>
          <table class="form-table">
            <tr><th><label>Sujet</label></th><td><input type="text" name="subject_<?=$k?>" value="<?=esc_attr($this->get_template($k,'subject'))?>" class="regular-text"></td></tr>
            <tr><th><label>Contenu (HTML)</label></th><td><textarea name="body_<?=$k?>" rows="12" class="large-text"><?=esc_textarea($this->get_template($k,'body'))?></textarea><p class="description">Variables : <?=$info[1]?></p></td></tr>
          </table>
        </div>
      <?php endforeach; ?>
      <p class="submit"><button type="submit" name="wpsd_save_templates" class="button button-primary">Enregistrer les modifications</button></p>
    </form>
    <script>
    document.querySelectorAll('.nav-tab').forEach(tab => {
      tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
        this.classList.add('nav-tab-active');
        var id = 'tab-' + this.href.split('tab=')[1];
        document.querySelectorAll('[id^="tab-"]').forEach(d => d.style.display = 'none');
        document.getElementById(id).style.display = '';
        history.pushState(null, '', this.href);
      });
    });
    </script>
    <?php
  }

  /**
   * Render the tab group for reservation notification email templates.
   */
  private function render_reservation_email_templates($active_tab) {
    if (!in_array($active_tab, array_keys(WPSD_Data::EMAIL_TEMPLATES), true)) {
      $active_tab = 'reservation_created';
    }
    ?>
    <h2 class="nav-tab-wrapper" style="margin-top:8px;">
      <?php foreach (WPSD_Data::EMAIL_TEMPLATES as $event => $info): ?>
        <a href="?page=wpsd-email-templates&group=reservations&tab=<?=$event?>" class="nav-tab <?=$active_tab===$event?'nav-tab-active':''?>"><?=$info[0]?></a>
      <?php endforeach; ?>
    </h2>
    <form method="post" style="margin-top:20px;"><?php wp_nonce_field('wpsd_email_reservation_templates'); ?>
      <?php foreach (WPSD_Data::EMAIL_TEMPLATES as $event => $info): ?>
        <?php
          $subject_val = esc_attr(WPSD_Data::get_email_template($event, 'subject'));
          $body_val    = esc_textarea(WPSD_Data::get_email_template($event, 'body'));
        ?>
        <div id="tab-<?=$event?>" style="<?=$active_tab===$event?'':'display:none;'?>">
          <h3>Email : <?=$info[0]?></h3>
          <table class="form-table">
            <tr>
              <th><label for="reservation_subject_<?=$event?>">Sujet</label></th>
              <td>
                <input type="text" name="reservation_subject_<?=$event?>" id="reservation_subject_<?=$event?>" value="<?=$subject_val?>" class="regular-text">
              </td>
            </tr>
            <tr>
              <th><label for="reservation_body_<?=$event?>">Corps (HTML)</label></th>
              <td>
                <textarea name="reservation_body_<?=$event?>" id="reservation_body_<?=$event?>" rows="12" class="large-text"><?=$body_val?></textarea>
                <p class="description">
                  Shortcodes disponibles : <code>{{display_name}}</code> <code>{{provider_name}}</code> <code>{{object_title}}</code> <code>{{date_start}}</code> <code>{{date_end}}</code> <code>{{days}}</code>
                </p>
              </td>
            </tr>
          </table>
        </div>
      <?php endforeach; ?>
      <p class="submit"><button type="submit" name="wpsd_save_reservation_templates" class="button button-primary">Enregistrer les modifications</button></p>
    </form>
    <script>
    document.querySelectorAll('.nav-tab').forEach(tab => {
      tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
        this.classList.add('nav-tab-active');
        var id = 'tab-' + this.href.split('tab=')[1];
        document.querySelectorAll('[id^="tab-"]').forEach(d => d.style.display = 'none');
        document.getElementById(id).style.display = '';
        history.pushState(null, '', this.href);
      });
    });
    </script>
    <?php
  }

  private function get_template($key, $type) {
    $defaults = $this->get_default_email_templates();
    $default = $defaults[$key.'_'.$type] ?? '';
    $stored = get_option('wpsd_email_'.$type.'_'.$key, '');
    return $stored !== '' ? $stored : $default;
  }

  private function get_default_email_templates() {
    $admin_url = admin_url('admin.php?page=wpsd-pending-registrations');
    $account_url = home_url('/mon-compte/');
    $logo_url = 'https://sentiers-des-savoirs.fr/wp-content/uploads/logo-email.png';
    $btn_url = 'https://sentiers-des-savoirs.fr/wp-content/uploads/btn-creer-motdepasse.png';
    
    $header = "<div style=\"background:#005247;padding:20px;text-align:center;\"><img src=\"$logo_url\" alt=\"Sentiers des Savoirs\" style=\"max-width:200px;height:auto;\"></div>";
    $footer = "<div style=\"background:#FBF1CA;padding:16px;text-align:center;font-size:11px;color:#5a6e68;\"><p style=\"margin:0;\">Message automatique — ne pas répondre.</p><p style=\"margin:4px 0 0;\">Sentiers des Savoirs</p></div>";
    
    $wrapper_start = "<div style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial,sans-serif;\">$header<div style=\"padding:24px;color:#1a2e2a;font-size:15px;line-height:1.6;\">";
    $wrapper_end = "</div>$footer</div>";
    
    return [
        'admin_new_registration_subject' => 'Nouvelle adhésion en attente de validation',
        'admin_new_registration_body'    => $wrapper_start . "<p>Bonjour,</p>\n<p>Une nouvelle adhésion est en attente de validation :</p>\n<ul>\n<li>Nom : <strong>{{nom}} {{prenom}}</strong></li>\n<li>Email : <strong>{{email}}</strong></li>\n<li>Rôle : <strong>{{role}}</strong></li>\n<li>Offre : <strong>{{plan}}</strong></li>\n</ul>\n<p style=\"text-align:center;margin-top:20px;\"><a href=\"{{admin_url}}\"><img src=\"$btn_url\" alt=\"Valider\" style=\"max-width:260px;height:auto;\"></a></p>" . $wrapper_end,
        
        'user_pending_subject'           => 'Votre adhésion a bien été reçue',
        'user_pending_body'              => $wrapper_start . "<p>Bonjour {{nom}} {{prenom}},</p>\n<p>Nous avons bien reçu votre adhésion en tant que <strong>{{role}}</strong> avec l'offre <strong>{{plan}}</strong>.</p>\n<p>Votre compte est actuellement <strong>en attente de validation</strong> par notre équipe.</p>\n<p>Vous recevrez un email dès que votre compte sera validé, avec un lien pour créer votre mot de passe.</p>\n<p>Merci de votre patience !</p>" . $wrapper_end,
        
        'user_approved_subject'          => 'Bienvenue - Créez votre mot de passe',
        'user_approved_body'             => $wrapper_start . "<p>Bonjour {{display_name}},</p>\n<p>Votre adhésion a été validée par notre équipe. Bienvenue !</p>\n<p>Pour créer votre mot de passe et accéder à votre espace membre :</p>\n<p style=\"text-align:center;margin-top:20px;\"><a href=\"{{reset_url}}\"><img src=\"$btn_url\" alt=\"Créer mon mot de passe\" style=\"max-width:260px;height:auto;\"></a></p>\n<p style=\"font-size:13px;color:#888;\">Ce lien expire dans 24 heures.</p>" . $wrapper_end,
        
        'user_rejected_subject'          => 'Votre adhésion a été refusée',
        'user_rejected_body'             => $wrapper_start . "<p>Bonjour {{nom}} {{prenom}},</p>\n<p>Nous vous remercions pour votre demande d'adhésion, mais après examen, nous ne pouvons pas donner suite à celle-ci.</p>\n<p>Votre paiement a été automatiquement remboursé. Le délai de remboursement dépend de votre banque (généralement 3 à 10 jours).</p>" . $wrapper_end,
        
        'subscription_active_subject'    => 'Votre abonnement est activé',
        'subscription_active_body'       => $wrapper_start . "<p>Bonjour,</p>\n<p>Votre paiement a bien été reçu et votre abonnement est désormais <strong>actif</strong>.</p>\n<ul>\n<li>Offre : <strong>{{plan_label}}</strong></li>\n</ul>\n<p><a href=\"{{account_url}}\">Accéder à mon compte</a></p>" . $wrapper_end,
        
        'renewal_reminder_subject'       => 'Rappel : renouvellement automatique le {{date_fr}}',
        'renewal_reminder_body'          => $wrapper_start . "<p>Bonjour {{display_name}},</p>\n<p>Petit rappel : votre abonnement sera <strong>renouvelé automatiquement</strong> le <strong>{{date_fr}}</strong>.</p>\n<p>Le débit se fera automatiquement, vous n'avez rien à faire si vous souhaitez continuer.</p>\n<p><a href=\"{{account_url}}\">Gérer mon abonnement</a></p>" . $wrapper_end,

        'admin_created_account_subject' => 'Bienvenue aux Sentiers des Savoirs',
        'admin_created_account_body'    => $wrapper_start . "<p>Bonjour {{first_name}},</p>\n<p>Votre compte a été créé par l'équipe des Sentiers des Savoirs.</p>\n<p>Pour créer votre mot de passe et accéder à votre espace membre :</p>\n<p style=\"text-align:center;margin-top:20px;\"><a href=\"{{reset_url}}\"><img src=\"$btn_url\" alt=\"Créer mon mot de passe\" style=\"max-width:260px;height:auto;\"></a></p>\n<p style=\"font-size:13px;color:#888;\">Ce lien expire dans 24 heures.</p>" . $wrapper_end,
    ];
  }
}