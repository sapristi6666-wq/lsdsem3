<?php
if (!defined('ABSPATH')) exit;

require_once WPSD_PATH . 'includes/class-wpsd-db.php';
require_once WPSD_PATH . 'includes/class-wpsd-admin.php';
require_once WPSD_PATH . 'includes/class-wpsd-stripe.php';
require_once WPSD_PATH . 'includes/class-wpsd-rest.php';
require_once WPSD_PATH . 'includes/class-wpsd-webhook.php';
require_once WPSD_PATH . 'includes/class-wpsd-shortcodes.php';
require_once WPSD_PATH . 'includes/class-wpsd-cpt.php';
require_once WPSD_PATH . 'includes/class-wpsd-brevo.php';
require_once WPSD_PATH . 'includes/class-wpsd-data.php';
require_once WPSD_PATH . 'includes/class-wpsd-capabilities.php';
require_once WPSD_PATH . 'includes/admin/class-wpsd-admin-stats.php';


class WPSD_Plugin {
  private static $instance;

  public static function instance() {
    if (!self::$instance) self::$instance = new self();
    return self::$instance;
  }

  public function __construct() {
    new WPSD_Admin();

    $stripe   = new WPSD_Stripe();
    $webhook  = new WPSD_Webhook($stripe);

    new WPSD_REST($stripe, $webhook);
    new WPSD_Shortcodes($stripe);
    new WPSD_CPT();
        new WPSD_Brevo();

                // Ajout des capacités
        add_action('init', ['WPSD_Capabilities', 'add_capabilities']);

        // Statistiques admin
        new WPSD_Admin_Stats();

        // API REST v2 (parcours, étapes, réservations, etc.)
        require_once WPSD_PATH . 'includes/class-wpsd-rest-v2.php';
        new WPSD_REST_V2($stripe);

        if (is_admin()) {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_wpsd_set_admin_approved', [$this, 'handle_set_admin_approved']);
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_style('wpsd-admin', WPSD_URL . 'assets/admin.css', [], filemtime(WPSD_PATH . 'assets/admin.css'));
        });
    }

    // One-time migration: set payment_method for existing Stripe users
    add_action('admin_init', function() {
        if (get_option('wpsd_migrated_payment_method')) return;
        
        $users = get_users([
            'meta_key' => 'stripe_customer_id',
            'meta_value' => '',
            'compare' => '!=',
            'number' => 500,
        ]);
        foreach ($users as $u) {
            if (!get_user_meta($u->ID, 'payment_method', true)) {
                update_user_meta($u->ID, 'payment_method', 'stripe');
            }
        }
                update_option('wpsd_migrated_payment_method', 1);
    });

    // Migration de la table reservations (v2)
    add_action('admin_init', function() {
        if (get_option('wpsd_migrated_reservations_v2')) return;
        WPSD_DB::migrate_reservations_table();
    });
}

  public static function opt($k, $default = '') {
    $opt = get_option('wpsd_settings', []);
    return isset($opt[$k]) && $opt[$k] !== '' ? $opt[$k] : $default;
  }

  public function admin_menu() {
    add_menu_page(
      'Adhérants',
      'Adhérants',
      'manage_options',
      'wpsd-subscriptions',
      [$this, 'render_admin_subscriptions'],
      'dashicons-money-alt',
      58
    );

    add_submenu_page(
      'wpsd-subscriptions',
      'Détails utilisateur',
      'Détails utilisateur',
      'manage_options',
      'wpsd-user',
      [$this, 'render_admin_user_details']
    );
  }

  /**
   * Toggle approval admin (indépendant de Stripe)
   */
  public function handle_set_admin_approved() {
    if (!current_user_can('manage_options')) wp_die('Forbidden', 403);

    $user_id  = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    $val      = isset($_GET['val']) ? (int) $_GET['val'] : 0;
    $payment_method = isset($_GET['payment_method']) ? sanitize_text_field($_GET['payment_method']) : 'stripe';
    $redirect = isset($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : admin_url('admin.php?page=wpsd-subscriptions');

    if ($user_id <= 0) wp_die('User invalide', 400);
    if (!in_array($val, [0, 1], true)) wp_die('Valeur invalide', 400);

    $valid_methods = ['stripe', 'cash', 'cheque', 'transfer', 'free'];
    if (!in_array($payment_method, $valid_methods, true)) {
      $payment_method = 'stripe';
    }

    check_admin_referer('wpsd_admin_approved_' . $user_id . '_' . $val);

    update_user_meta($user_id, 'wpsd_admin_approved', $val);
    update_user_meta($user_id, 'wpsd_admin_approved_at', current_time('mysql'));
    
    if ($val === 1) {
      update_user_meta($user_id, 'payment_method', $payment_method);
    }
    
    $user = get_user_by('id', $user_id);
    if ($user && empty($user->roles)) {
        $user->set_role('subscriber');
        error_log('WPSD: Rôle subscriber attribué à user ' . $user_id);
    }

    wp_safe_redirect($redirect);
    exit;
  }

    public function render_admin_subscriptions() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé.');

    // Handle add member
    if (isset($_POST['wpsd_add_member']) && check_admin_referer('wpsd_add_member')) {
        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $role = sanitize_text_field($_POST['role']);
        $plan = sanitize_text_field($_POST['plan']);
        $method = sanitize_text_field($_POST['payment_method']);
        $period_start = sanitize_text_field($_POST['period_start']);
        $password = wp_generate_password(12, true);
        
        if (!email_exists($email)) {
            $user_id = wp_create_user($email, $password, $email);
            
            if (!is_wp_error($user_id)) {
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
                update_user_meta($user_id, 'phone', $phone);
                update_user_meta($user_id, 'wpsd_admin_approved', 1);
                update_user_meta($user_id, 'payment_method', $method);
                update_user_meta($user_id, 'subscription_status', 'active');
                update_user_meta($user_id, 'plan_label', $plan);
                
                $roles = ['is_itinerant' => 0, 'is_passeur' => 0, 'is_hebergeur' => 0, 'is_sympathisant' => 0];
                if ($role === 'passeur') $roles['is_passeur'] = 1;
                if ($role === 'itinerant') $roles['is_itinerant'] = 1;
                if ($role === 'hebergeur') $roles['is_hebergeur'] = 1;
                if ($role === 'sympathisant') $roles['is_sympathisant'] = 1;
                foreach ($roles as $k => $v) update_user_meta($user_id, $k, $v);
                
                $period_end = date('Y-m-d', strtotime($period_start . ' +1 year'));
                update_user_meta($user_id, 'subscription_start', $period_start);
                update_user_meta($user_id, 'subscription_end', $period_end);
                
                if ($method !== 'free') {
                    $amount = ($plan === 'family') ? 70 : 50;
                    self::record_payment($user_id, $amount, $method, $plan, $period_start, $period_end, get_current_user_id());
                }
                
                // Envoyer un lien pour créer le mot de passe
                $user = get_userdata($user_id);
                $reset_key = get_password_reset_key($user);
                if (!is_wp_error($reset_key)) {
                    $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($email));
                    
                    $templates = get_transient('wpsd_email_templates');
                    if ($templates === false) {
                        $templates = get_option('wpsd_email_templates', []);
                        set_transient('wpsd_email_templates', $templates, 12 * HOUR_IN_SECONDS);
                    }
                    $defaults = [
                        'admin_created_account_subject' => 'Bienvenue aux Sentiers des Savoirs',
                        'admin_created_account_body'    => "<p>Bonjour {{first_name}},</p>\n<p>Votre compte a été créé par l'équipe des Sentiers des Savoirs.</p>\n<p>Pour créer votre mot de passe et accéder à votre espace :</p>\n<p><a href=\"{{reset_url}}\" style=\"padding:12px 24px;background:#005247;color:#FBF1CA;text-decoration:none;border-radius:8px;\">Créer mon mot de passe</a></p>",
                    ];
                    $templates = wp_parse_args($templates, $defaults);

                    $subject = $templates['admin_created_account_subject'];
                    $message = str_replace(
                        ['{{first_name}}', '{{email}}', '{{reset_url}}'],
                        [$first_name, $email, $reset_url],
                        $templates['admin_created_account_body']
                    );
                    $message .= '<p style="margin-top:16px;color:#666;font-size:12px">Ce lien expire dans 24 heures. Message automatique — ne pas répondre.</p>';
                    wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
                }
                
                echo '<div class="wpsd-notice wpsd-notice-success">Adhérent créé. Email envoyé avec le lien de création de mot de passe.</div>';
            }
        } else {
            echo '<div class="wpsd-notice wpsd-notice-error">Cet email existe déjà.</div>';
        }
    }

    $q_email       = isset($_GET['email']) ? sanitize_text_field($_GET['email']) : '';
    $q_status      = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $q_user_status = isset($_GET['user_status']) ? sanitize_text_field($_GET['user_status']) : '';
    $q_payment     = isset($_GET['payment_method']) ? sanitize_text_field($_GET['payment_method']) : '';
    $q_has_inst    = isset($_GET['has_inst']) ? sanitize_text_field($_GET['has_inst']) : '';

    $meta_query = ['relation' => 'AND'];

    if ($q_status !== '') {
        $meta_query[] = ['key' => 'subscription_status', 'value' => $q_status, 'compare' => '='];
    }
    if ($q_user_status === 'passeur')   $meta_query[] = ['key'=>'is_passeur','value'=>'1'];
    if ($q_user_status === 'hebergeur') $meta_query[] = ['key'=>'is_hebergeur','value'=>'1'];
    if ($q_user_status === 'itinerant') $meta_query[] = ['key'=>'is_itinerant','value'=>'1'];
    if ($q_payment !== '') $meta_query[] = ['key'=>'payment_method','value'=>$q_payment,'compare'=>'='];
    if ($q_has_inst === '1') $meta_query[] = ['key'=>'inst_name','value'=>'','compare'=>'!='];
    if ($q_has_inst === '0') $meta_query[] = ['key'=>'inst_name','value'=>'','compare'=>'='];

    $args = [
        'number' => 50,
        'orderby' => 'ID',
        'order' => 'DESC',
        'search' => $q_email ? '*' . $q_email . '*' : '',
        'search_columns' => ['user_email'],
    ];
    if (count($meta_query) > 1) $args['meta_query'] = $meta_query;

    $users = get_users($args);

    ?>
    <div class="wpsd-admin-wrap">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h1 style="margin:0;">Adhérents</h1>
            <button class="wpsd-btn wpsd-btn-primary" id="wpsd-add-member-btn">+ Ajouter un adhérent</button>
        </div>

        <!-- Add Member Form -->
        <div id="wpsd-add-member-form" style="display:none;" class="wpsd-card">
            <div class="wpsd-card-header">
                <h2>Ajouter un adhérent</h2>
                <button class="wpsd-btn wpsd-btn-ghost wpsd-btn-sm" id="wpsd-cancel-add">Annuler</button>
            </div>
            <form method="post" class="wpsd-form-grid">
                <?php wp_nonce_field('wpsd_add_member'); ?>
                <div class="wpsd-form-group"><label class="wpsd-label">Prénom</label><input type="text" name="first_name" class="wpsd-input" required></div>
                <div class="wpsd-form-group"><label class="wpsd-label">Nom</label><input type="text" name="last_name" class="wpsd-input" required></div>
                <div class="wpsd-form-group"><label class="wpsd-label">Email</label><input type="email" name="email" class="wpsd-input" required></div>
                <div class="wpsd-form-group"><label class="wpsd-label">Téléphone</label><input type="text" name="phone" class="wpsd-input"></div>
                <div class="wpsd-form-group"><label class="wpsd-label">Rôle</label>
                    <select name="role" class="wpsd-select">
                        <option value="itinerant">Itinérant</option>
                        <option value="passeur">Passeur</option>
                        <option value="hebergeur">Hébergeur</option>
                        <option value="sympathisant">Sympathisant</option>
                    </select>
                </div>
                <div class="wpsd-form-group"><label class="wpsd-label">Plan</label>
                    <select name="plan" class="wpsd-select">
                        <option value="member">Membre (50€/an)</option>
                        <option value="family">Famille (70€/an)</option>
                    </select>
                </div>
                <div class="wpsd-form-group"><label class="wpsd-label">Paiement</label>
                    <select name="payment_method" class="wpsd-select">
                        <option value="cash">Espèces</option>
                        <option value="cheque">Chèque</option>
                        <option value="transfer">Virement</option>
                        <option value="free">Gratuit</option>
                    </select>
                </div>
                <div class="wpsd-form-group"><label class="wpsd-label">Début abonnement</label><input type="date" name="period_start" class="wpsd-input" value="<?php echo date('Y-m-d'); ?>"></div>
                <div style="grid-column:span 2;"><button type="submit" name="wpsd_add_member" class="wpsd-btn wpsd-btn-primary">Créer l'adhérent</button></div>
            </form>
        </div>

        <script>
        document.getElementById('wpsd-add-member-btn')?.addEventListener('click', function() {
            document.getElementById('wpsd-add-member-form').style.display = 'block';
            this.style.display = 'none';
        });
        document.getElementById('wpsd-cancel-add')?.addEventListener('click', function() {
            document.getElementById('wpsd-add-member-form').style.display = 'none';
            document.getElementById('wpsd-add-member-btn').style.display = 'inline-flex';
        });
        </script>

        <form method="get" class="wpsd-filter-bar">
            <input type="hidden" name="page" value="wpsd-subscriptions" />
            <div class="wpsd-form-group">
                <label class="wpsd-label">Recherche</label>
                <input type="text" name="email" class="wpsd-input" placeholder="Email..." value="<?php echo esc_attr($q_email); ?>">
            </div>
            <div class="wpsd-form-group">
                <label class="wpsd-label">Statut</label>
                <select name="status" class="wpsd-select">
                    <option value="">Tous</option>
                    <option value="active" <?php selected($q_status,'active'); ?>>Actif</option>
                    <option value="trialing" <?php selected($q_status,'trialing'); ?>>Essai</option>
                    <option value="canceled" <?php selected($q_status,'canceled'); ?>>Annulé</option>
                    <option value="past_due" <?php selected($q_status,'past_due'); ?>>Retard</option>
                </select>
            </div>
            <div class="wpsd-form-group">
                <label class="wpsd-label">Rôle</label>
                <select name="user_status" class="wpsd-select">
                    <option value="">Tous</option>
                    <option value="passeur" <?php selected($q_user_status,'passeur'); ?>>Passeur</option>
                    <option value="hebergeur" <?php selected($q_user_status,'hebergeur'); ?>>Hébergeur</option>
                    <option value="itinerant" <?php selected($q_user_status,'itinerant'); ?>>Itinérant</option>
                </select>
            </div>
            <div class="wpsd-form-group">
                <label class="wpsd-label">Paiement</label>
                <select name="payment_method" class="wpsd-select">
                    <option value="">Tous</option>
                    <option value="stripe" <?php selected($q_payment,'stripe'); ?>>Stripe</option>
                    <option value="cash" <?php selected($q_payment,'cash'); ?>>Espèces</option>
                    <option value="cheque" <?php selected($q_payment,'cheque'); ?>>Chèque</option>
                    <option value="transfer" <?php selected($q_payment,'transfer'); ?>>Virement</option>
                    <option value="free" <?php selected($q_payment,'free'); ?>>Gratuit</option>
                </select>
            </div>
            <div class="wpsd-form-group">
                <label class="wpsd-label">Institution</label>
                <select name="has_inst" class="wpsd-select">
                    <option value="">Tous</option>
                    <option value="1" <?php selected($q_has_inst,'1'); ?>>Oui</option>
                    <option value="0" <?php selected($q_has_inst,'0'); ?>>Non</option>
                </select>
            </div>
            <div class="wpsd-form-group" style="align-self:flex-end;">
                <button type="submit" class="wpsd-btn wpsd-btn-primary">Filtrer</button>
            </div>
        </form>

        <div class="wpsd-card" style="padding:0;overflow:hidden;">
            <table class="wpsd-table">
                <thead>
                    <tr>
                        <th>Adhérent</th>
                        <th>Rôles</th>
                        <th>Plan</th>
                        <th>Statut</th>
                        <th>Paiement</th>
                        <th>Échéance</th>
                        <th>Approuvé</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$users): ?>
                        <tr><td colspan="8" style="text-align:center;padding:24px;">Aucun adhérent trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): 
                            $uid = (int)$u->ID;
                            $sub_status = get_user_meta($uid, 'subscription_status', true);
                            $plan_key = get_user_meta($uid, 'plan_key', true);
                            $plan_label = get_user_meta($uid, 'plan_label', true);
                            $payment_method = get_user_meta($uid, 'payment_method', true);
                            $approved = ((int)get_user_meta($uid, 'wpsd_admin_approved', true) === 1);
                            $period_end = get_user_meta($uid, 'subscription_end', true);
                            
                            $roles = [];
                            if ((int)get_user_meta($uid, 'is_itinerant', true) === 1) $roles[] = 'Itinérant';
                            if ((int)get_user_meta($uid, 'is_passeur', true) === 1) $roles[] = 'Passeur';
                            if ((int)get_user_meta($uid, 'is_hebergeur', true) === 1) $roles[] = 'Hébergeur';
                            if ((int)get_user_meta($uid, 'is_sympathisant', true) === 1) $roles[] = 'Sympathisant';
                            
                            $status_badge = 'neutral';
                            if ($sub_status === 'active' || $sub_status === 'trialing') $status_badge = 'success';
                            if ($sub_status === 'past_due') $status_badge = 'warning';
                            if ($sub_status === 'canceled') $status_badge = 'danger';
                            
                            $payment_labels = ['stripe'=>'Stripe','cash'=>'Espèces','cheque'=>'Chèque','transfer'=>'Virement','free'=>'Gratuit'];
                            $plan_labels = ['member'=>'Membre (50€)','family'=>'Famille (70€)','institution'=>'Institution (100€)'];
                            
                            $detail_url = admin_url('admin.php?page=wpsd-user&user_id='.$uid);
                            $redirect = add_query_arg(['email'=>$q_email,'status'=>$q_status,'user_status'=>$q_user_status,'payment_method'=>$q_payment,'has_inst'=>$q_has_inst], admin_url('admin.php?page=wpsd-subscriptions'));
                            $approve_url = wp_nonce_url(admin_url('admin-post.php?action=wpsd_set_admin_approved&user_id='.$uid.'&val=1&redirect='.urlencode($redirect)), 'wpsd_admin_approved_'.$uid.'_1');
                            $disapprove_url = wp_nonce_url(admin_url('admin-post.php?action=wpsd_set_admin_approved&user_id='.$uid.'&val=0&redirect='.urlencode($redirect)), 'wpsd_admin_approved_'.$uid.'_0');
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($detail_url); ?>" style="font-weight:600;color:var(--wpsd-green);text-decoration:none;">
                                        <?php echo esc_html($u->display_name); ?>
                                    </a>
                                    <div style="font-size:11px;color:var(--wpsd-gray-500);"><?php echo esc_html($u->user_email); ?></div>
                                </td>
                                <td>
                                    <?php foreach ($roles as $role): ?>
                                        <span class="wpsd-badge wpsd-badge-outline"><?php echo esc_html($role); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (empty($roles)): ?>
                                        <span class="wpsd-badge wpsd-badge-neutral">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="wpsd-badge wpsd-badge-info"><?php echo esc_html($plan_labels[$plan_key] ?? $plan_label ?: '—'); ?></span></td>
                                <td><span class="wpsd-badge wpsd-badge-<?php echo $status_badge; ?>"><?php echo esc_html($sub_status ?: 'inactif'); ?></span></td>
                                <td><span class="wpsd-badge wpsd-badge-neutral"><?php echo esc_html($payment_labels[$payment_method] ?? $payment_method ?: '—'); ?></span></td>
                                <td style="font-size:12px;font-weight:500;white-space:nowrap;"><?php echo $period_end ? esc_html(date_i18n('d/m/Y', strtotime($period_end))) : '<span style="color:var(--wpsd-gray-500);">—</span>'; ?></td>
                                <td>
                                    <?php if ($approved): ?>
                                        <span class="wpsd-badge wpsd-badge-success">Approuvé</span>
                                    <?php else: ?>
                                        <span class="wpsd-badge wpsd-badge-warning">En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;">
                                        <a href="<?php echo esc_url($detail_url); ?>" class="wpsd-btn wpsd-btn-ghost wpsd-btn-sm">Détails</a>
                                        <?php if ($approved): ?>
                                            <a href="<?php echo esc_url($disapprove_url); ?>" class="wpsd-btn wpsd-btn-ghost wpsd-btn-sm" style="color:var(--wpsd-red);" onclick="return confirm('Désapprouver ?')">Bloquer</a>
                                        <?php else: ?>
                                            <a href="<?php echo esc_url($approve_url); ?>" class="wpsd-btn wpsd-btn-primary wpsd-btn-sm">Approuver</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

      public function render_admin_user_details() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé.');
    global $wpdb;

    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if (!$user_id) {
      echo '<div class="wrap"><h1>Détails utilisateur</h1><p>User ID manquant.</p></div>';
      return;
    }

    $u = get_user_by('id', $user_id);
    if (!$u) {
      echo '<div class="wrap"><h1>Détails utilisateur</h1><p>Utilisateur introuvable.</p></div>';
      return;
    }

    // Handle GDPR export
    if (isset($_POST['wpsd_export_user']) && check_admin_referer('wpsd_export_user_' . $user_id)) {
        $data = self::export_user_data($user_id);
        if ($data) {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="export-user-' . $user_id . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Handle GDPR delete
    if (isset($_POST['wpsd_delete_user']) && check_admin_referer('wpsd_delete_user_' . $user_id)) {
        self::anonymize_user($user_id);
        echo '<div class="wpsd-notice wpsd-notice-success"><p>Compte anonymisé avec succès.</p></div>';
    }

    // Handle role change
    if (isset($_POST['wpsd_change_role']) && check_admin_referer('wpsd_change_role_' . $user_id)) {
        $new_role = sanitize_text_field($_POST['new_role'] ?? 'none');
        update_user_meta($user_id, 'is_itinerant', 0);
        update_user_meta($user_id, 'is_passeur', 0);
        update_user_meta($user_id, 'is_hebergeur', 0);
        update_user_meta($user_id, 'is_sympathisant', 0);
        if ($new_role !== 'none') {
            update_user_meta($user_id, 'is_' . $new_role, 1);
        }
        echo '<div class="wpsd-notice wpsd-notice-success"><p>Rôle mis à jour.</p></div>';
    }

    // Handle record payment
    if (isset($_POST['wpsd_record_payment']) && check_admin_referer('wpsd_record_payment_' . $user_id)) {
        $amount = (float)($_POST['amount'] ?? 0);
        $method = sanitize_text_field($_POST['method'] ?? 'cash');
        $plan = sanitize_text_field($_POST['plan'] ?? 'member');
        $period_start = sanitize_text_field($_POST['period_start'] ?? '');
        $period_end = sanitize_text_field($_POST['period_end'] ?? '');
        $reference = sanitize_text_field($_POST['reference'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if ($amount > 0 && $period_start && $period_end) {
            self::record_payment($user_id, $amount, $method, $plan, $period_start, $period_end, get_current_user_id(), $reference, $notes);
            echo '<div class="wpsd-notice wpsd-notice-success"><p>Paiement enregistré.</p></div>';
            echo '<script>setTimeout(function(){ location.reload(); }, 1000);</script>';
        } else {
            echo '<div class="wpsd-notice wpsd-notice-error"><p>Veuillez remplir le montant et les dates.</p></div>';
        }
    }

    // Profil
    $profile = [
      'first_name' => get_user_meta($user_id, 'first_name', true),
      'last_name'  => get_user_meta($user_id, 'last_name', true),
      'phone'      => get_user_meta($user_id, 'phone', true),
      'address_line1' => get_user_meta($user_id, 'address_line1', true),
      'address_line2' => get_user_meta($user_id, 'address_line2', true),
      'postal_code'   => get_user_meta($user_id, 'postal_code', true),
      'city'          => get_user_meta($user_id, 'city', true),
      'country'       => get_user_meta($user_id, 'country', true),
      'bio_text'      => get_user_meta($user_id, 'bio_text', true),
      'rgpd'          => (int)get_user_meta($user_id, 'rgpd_consent', true),
      'rgpd_at'       => get_user_meta($user_id, 'rgpd_consent_at', true),
      'is_itinerant'  => (int)get_user_meta($user_id, 'is_itinerant', true) === 1,
      'is_passeur'    => (int)get_user_meta($user_id, 'is_passeur', true) === 1,
      'is_hebergeur'  => (int)get_user_meta($user_id, 'is_hebergeur', true) === 1,
      'is_sympathisant' => (int)get_user_meta($user_id, 'is_sympathisant', true) === 1,
    ];

    // Institution
    $inst = [
      'inst_name' => get_user_meta($user_id, 'inst_name', true),
      'inst_email'=> get_user_meta($user_id, 'inst_email', true),
      'inst_phone'=> get_user_meta($user_id, 'inst_phone', true),
      'inst_address_line1' => get_user_meta($user_id, 'inst_address_line1', true),
      'inst_address_line2' => get_user_meta($user_id, 'inst_address_line2', true),
      'inst_postal_code'   => get_user_meta($user_id, 'inst_postal_code', true),
      'inst_city'          => get_user_meta($user_id, 'inst_city', true),
      'inst_country'       => get_user_meta($user_id, 'inst_country', true),
      'inst_description'   => get_user_meta($user_id, 'inst_description', true),
    ];

    // Famille
    $family = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . WPSD_DB::table_family() . " WHERE user_id=%d ORDER BY id DESC", $user_id), ARRAY_A);

    // Abonnement
    $sub_status = (string)get_user_meta($user_id, 'subscription_status', true);
    $plan_key = (string)get_user_meta($user_id, 'plan_key', true);
    $customer_id = (string)get_user_meta($user_id, 'stripe_customer_id', true);
    $sub_id = (string)get_user_meta($user_id, 'stripe_subscription_id', true);
    $payment_method = (string)get_user_meta($user_id, 'payment_method', true);
    $period_end = get_user_meta($user_id, 'subscription_end', true);

    // Paiements
    $payments = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . WPSD_DB::table_payments() . " WHERE user_id = %d ORDER BY created_at DESC LIMIT 50", $user_id), ARRAY_A);

    $act_url = admin_url('edit.php?post_type=wpsd_activity&wpsd_owner=' . $user_id);
    $acc_url = admin_url('edit.php?post_type=wpsd_accommodation&wpsd_owner=' . $user_id);
    $payment_labels = ['stripe'=>'Stripe','cash'=>'Espèces','cheque'=>'Chèque','transfer'=>'Virement','free'=>'Gratuit'];
    $plan_labels = ['member'=>'Membre (50€/an)','family'=>'Famille (70€/an)','institution'=>'Institution (100€/an)'];

    ?>
    <div class="wpsd-admin-wrap">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?= esc_html($u->display_name) ?> <span style="font-weight:400;color:#888;">#<?= (int)$user_id ?></span></h1>
            <div style="display:flex;gap:8px;">
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('wpsd_export_user_' . $user_id); ?>
                    <button type="submit" name="wpsd_export_user" class="wpsd-btn wpsd-btn-secondary wpsd-btn-sm" value="<?= $user_id ?>">Exporter</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer définitivement ce compte ?');">
                    <?php wp_nonce_field('wpsd_delete_user_' . $user_id); ?>
                    <button type="submit" name="wpsd_delete_user" class="wpsd-btn wpsd-btn-danger wpsd-btn-sm" value="<?= $user_id ?>">Supprimer</button>
                </form>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div class="wpsd-card">
                    <div class="wpsd-card-header"><h2>Abonnement</h2> <span class="wpsd-badge wpsd-badge-<?= $sub_status === 'active' ? 'success' : ($sub_status === 'past_due' ? 'warning' : 'neutral') ?>"><?= esc_html($sub_status ?: 'inactif') ?></span></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                        <div><strong>Plan :</strong> <?= esc_html($plan_labels[$plan_key] ?? $plan_key ?: '—') ?></div>
                        <div><strong>Paiement :</strong> <?= esc_html($payment_labels[$payment_method] ?? $payment_method ?: '—') ?></div>
                        <div><strong>Échéance :</strong> <?= $period_end ? esc_html(date_i18n('d/m/Y', strtotime($period_end))) : '—' ?></div>
                        <div><strong>Approuvé :</strong> <?= ((int)get_user_meta($user_id, 'wpsd_admin_approved', true) === 1) ? '<span style="color:#166534;">Oui</span>' : '<span style="color:#92400e;">Non</span>' ?></div>
                    </div>
                    <?php if ($customer_id): ?>
                    <div style="margin-top:8px;font-size:12px;color:#888;">
                        <div>Stripe Customer : <code><?= esc_html($customer_id) ?></code></div>
                        <?php if ($sub_id): ?><div>Stripe Sub : <code><?= esc_html($sub_id) ?></code></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <a href="<?= esc_url($act_url) ?>" class="wpsd-btn wpsd-btn-ghost wpsd-btn-sm">Activités</a>
                        <a href="<?= esc_url($acc_url) ?>" class="wpsd-btn wpsd-btn-ghost wpsd-btn-sm">Hébergements</a>
                    </div>
                </div>

                <div class="wpsd-card">
                    <div class="wpsd-card-header"><h2>Profil</h2></div>
                    <div style="font-size:13px;">
                        <p><strong>Nom :</strong> <?= esc_html(trim($profile['first_name'] . ' ' . $profile['last_name'])) ?></p>
                        <p><strong>Téléphone :</strong> <?= esc_html($profile['phone'] ?: '—') ?></p>
                        <p><strong>Email :</strong> <?= esc_html($u->user_email) ?></p>
                        <p><strong>Adresse :</strong><br>
                            <?= esc_html($profile['address_line1'] ?: '') ?>
                            <?= $profile['address_line2'] ? '<br>' . esc_html($profile['address_line2']) : '' ?>
                            <br><?= esc_html(trim(($profile['postal_code'] ?: '') . ' ' . ($profile['city'] ?: ''))) ?>
                            <?= $profile['country'] ? '<br>' . esc_html($profile['country']) : '' ?>
                        </p>
                        <?php if ($profile['bio_text']): ?><p><strong>Bio :</strong><br><?= wp_kses_post(wpautop($profile['bio_text'])) ?></p><?php endif; ?>
                        <p><strong>RGPD :</strong> <?= $profile['rgpd'] ? 'Consenti' : 'Non' ?><?= $profile['rgpd_at'] ? ' (' . esc_html($profile['rgpd_at']) . ')' : '' ?></p>
                        <p><strong>Rôles :</strong>
                            <?php $roles = []; if ($profile['is_itinerant']) $roles[] = 'Itinérant'; if ($profile['is_passeur']) $roles[] = 'Passeur'; if ($profile['is_hebergeur']) $roles[] = 'Hébergeur'; if ($profile['is_sympathisant']) $roles[] = 'Sympathisant'; ?>
                            <?= !empty($roles) ? esc_html(implode(' + ', $roles)) : '<span style="color:#888;">Aucun</span>' ?>
                        </p>

                        <div style="margin-top:12px;padding:12px;background:#f9fafb;border-radius:8px;">
                            <h3 style="margin:0 0 8px;font-size:13px;">Modifier le rôle</h3>
                            <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                                <?php wp_nonce_field('wpsd_change_role_' . $user_id); ?>
                                <div>
                                    <label class="wpsd-label">Nouveau rôle</label>
                                    <select name="new_role" class="wpsd-select" style="width:auto;">
                                        <option value="itinerant" <?= $profile['is_itinerant'] ? 'selected' : '' ?>>Itinérant</option>
                                        <option value="passeur" <?= $profile['is_passeur'] ? 'selected' : '' ?>>Passeur</option>
                                        <option value="hebergeur" <?= $profile['is_hebergeur'] ? 'selected' : '' ?>>Hébergeur</option>
                                        <option value="sympathisant" <?= $profile['is_sympathisant'] ? 'selected' : '' ?>>Sympathisant</option>
                                        <option value="none" <?= empty($roles) ? 'selected' : '' ?>>Aucun</option>
                                    </select>
                                </div>
                                <button type="submit" name="wpsd_change_role" class="wpsd-btn wpsd-btn-primary">Appliquer</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="wpsd-card">
                    <div class="wpsd-card-header"><h2>Institution</h2></div>
                    <?php if (trim((string)$inst['inst_name']) === '' && trim((string)$inst['inst_email']) === ''): ?>
                        <p style="color:#888;">Aucune information institution.</p>
                    <?php else: ?>
                        <div style="font-size:13px;">
                            <p><strong>Nom :</strong> <?= esc_html($inst['inst_name'] ?: '—') ?></p>
                            <p><strong>Email :</strong> <?= esc_html($inst['inst_email'] ?: '—') ?></p>
                            <p><strong>Téléphone :</strong> <?= esc_html($inst['inst_phone'] ?: '—') ?></p>
                            <p><strong>Adresse :</strong><br>
                                <?= esc_html($inst['inst_address_line1'] ?: '') ?>
                                <?= $inst['inst_address_line2'] ? '<br>' . esc_html($inst['inst_address_line2']) : '' ?>
                                <br><?= esc_html(trim(($inst['inst_postal_code'] ?: '') . ' ' . ($inst['inst_city'] ?: ''))) ?>
                                <?= $inst['inst_country'] ? '<br>' . esc_html($inst['inst_country']) : '' ?>
                            </p>
                            <?php if ($inst['inst_description']): ?><p><strong>Description :</strong><br><?= wp_kses_post(wpautop($inst['inst_description'])) ?></p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:16px;">
                <div class="wpsd-card">
                    <div class="wpsd-card-header"><h2>Historique des paiements</h2></div>
                    <?php if (!$payments): ?>
                        <p style="color:#888;">Aucun paiement enregistré.</p>
                    <?php else: ?>
                        <div style="max-height:300px;overflow-y:auto;">
                            <table class="wpsd-table">
                                <thead><tr><th>Date</th><th>Montant</th><th>Méthode</th><th>Plan</th><th>Période</th><th>N° facture</th></tr></thead>
                                <tbody>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><?= esc_html(date_i18n('d/m/Y', strtotime($p['created_at']))) ?></td>
                                            <td><strong><?= esc_html(number_format($p['amount'], 2, ',', ' ') . ' €') ?></strong></td>
                                            <td><?= esc_html($payment_labels[$p['method']] ?? $p['method']) ?></td>
                                            <td><?= esc_html($plan_labels[$p['plan']] ?? $p['plan']) ?></td>
                                            <td style="font-size:12px;"><?= $p['period_start'] ? esc_html(date_i18n('d/m/Y', strtotime($p['period_start']))) : '—' ?> → <?= $p['period_end'] ? esc_html(date_i18n('d/m/Y', strtotime($p['period_end']))) : '—' ?></td>
                                            <td><code><?= esc_html($p['invoice_number'] ?: '—') ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:16px;padding:16px;background:#f9fafb;border-radius:8px;">
                        <h3 style="margin:0 0 12px;font-size:14px;">Enregistrer un paiement</h3>
                        <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <?php wp_nonce_field('wpsd_record_payment_' . $user_id); ?>
                            <div><label class="wpsd-label">Montant (€)</label><input type="number" name="amount" step="0.01" min="0" class="wpsd-input" required></div>
                            <div><label class="wpsd-label">Méthode</label><select name="method" class="wpsd-select"><option value="cash">Espèces</option><option value="cheque">Chèque</option><option value="transfer">Virement</option><option value="free">Gratuit</option></select></div>
                            <div><label class="wpsd-label">Plan</label><select name="plan" class="wpsd-select"><option value="member">Membre (50€/an)</option><option value="family">Famille (70€/an)</option><option value="institution">Institution (100€/an)</option></select></div>
                            <div><label class="wpsd-label">Référence</label><input type="text" name="reference" class="wpsd-input" placeholder="N° chèque..."></div>
                            <div><label class="wpsd-label">Début</label><input type="date" name="period_start" class="wpsd-input" value="<?= date('Y-m-d') ?>" required></div>
                            <div><label class="wpsd-label">Fin</label><input type="date" name="period_end" class="wpsd-input" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required></div>
                            <div style="grid-column:span 2;"><label class="wpsd-label">Notes</label><input type="text" name="notes" class="wpsd-input" placeholder="Notes éventuelles..."></div>
                            <div style="grid-column:span 2;"><button type="submit" name="wpsd_record_payment" class="wpsd-btn wpsd-btn-primary">Enregistrer le paiement</button></div>
                        </form>
                    </div>
                </div>

                <div class="wpsd-card">
                    <div class="wpsd-card-header"><h2>Famille</h2></div>
                    <?php if (!$family): ?>
                        <p style="color:#888;">Aucun membre de famille.</p>
                    <?php else: ?>
                        <div style="max-height:300px;overflow-y:auto;">
                            <table class="wpsd-table">
                                <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Naissance</th></tr></thead>
                                <tbody>
                                    <?php foreach ($family as $m): ?>
                                        <tr>
                                            <td><?= esc_html(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></td>
                                            <td><?= esc_html($m['email'] ?? '—') ?></td>
                                            <td><?= esc_html($m['phone'] ?? '—') ?></td>
                                            <td><?= esc_html($m['birth_date'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <p style="margin-top:16px;"><a href="<?= esc_url(admin_url('admin.php?page=wpsd-subscriptions')) ?>" class="wpsd-btn wpsd-btn-ghost">← Retour aux adhérents</a></p>
    </div>
    <?php
  }

  public static function record_payment($user_id, $amount, $method, $plan, $period_start, $period_end, $received_by = null, $reference = '', $notes = '') {
    global $wpdb;
    
    $invoice = self::generate_invoice_number();
    
    $wpdb->insert(
        WPSD_DB::table_payments(),
        [
            'user_id'       => $user_id,
            'amount'        => $amount,
            'method'        => $method,
            'status'        => 'paid',
            'plan'          => $plan,
            'period_start'  => $period_start,
            'period_end'    => $period_end,
            'received_by'   => $received_by,
            'reference'     => $reference,
            'notes'         => $notes,
            'invoice_number'=> $invoice,
        ],
        ['%d','%f','%s','%s','%s','%s','%s','%d','%s','%s','%s']
    );
    
    // Update user meta
    update_user_meta($user_id, 'subscription_status', 'active');
    update_user_meta($user_id, 'payment_method', $method);
    update_user_meta($user_id, 'plan_label', $plan);
    update_user_meta($user_id, 'subscription_start', $period_start);
    update_user_meta($user_id, 'subscription_end', $period_end);
    
    return $wpdb->insert_id;
  }

  /**
   * Generate a sequential invoice number
   */
  private static function generate_invoice_number() {
    $year = date('Y');
    $prefix = self::opt('invoice_prefix', 'FACT');
    $count = get_option('wpsd_invoice_counter', 0) + 1;
    update_option('wpsd_invoice_counter', $count);
    return sprintf('%s-%s-%04d', $prefix, $year, $count);
  }

  public static function activate() {
    add_role('wpsd_moderator', 'Modérateur WPSD', [
      'read' => true,
      'wpsd_moderate_registrations' => true,
    ]);
  }

  /**
 * GDPR - Export all user data as JSON
 */
public static function export_user_data($user_id) {
    global $wpdb;
    $uid = (int)$user_id;
    $u = get_userdata($uid);
    if (!$u) return null;

    $data = [
        'export_date' => current_time('mysql'),
        'user' => [
            'id' => $uid,
            'email' => $u->user_email,
            'display_name' => $u->display_name,
            'registered' => $u->user_registered,
            'roles' => $u->roles,
        ],
        'profile' => [
            'first_name' => get_user_meta($uid, 'first_name', true),
            'last_name' => get_user_meta($uid, 'last_name', true),
            'phone' => get_user_meta($uid, 'phone', true),
            'address_line1' => get_user_meta($uid, 'address_line1', true),
            'address_line2' => get_user_meta($uid, 'address_line2', true),
            'postal_code' => get_user_meta($uid, 'postal_code', true),
            'city' => get_user_meta($uid, 'city', true),
            'country' => get_user_meta($uid, 'country', true),
            'bio_text' => get_user_meta($uid, 'bio_text', true),
            'photo_id' => get_user_meta($uid, 'profile_photo_id', true),
            'rgpd_consent' => get_user_meta($uid, 'rgpd_consent', true),
            'rgpd_consent_at' => get_user_meta($uid, 'rgpd_consent_at', true),
        ],
        'roles' => [
            'is_itinerant' => get_user_meta($uid, 'is_itinerant', true),
            'is_passeur' => get_user_meta($uid, 'is_passeur', true),
            'is_hebergeur' => get_user_meta($uid, 'is_hebergeur', true),
            'is_sympathisant' => get_user_meta($uid, 'is_sympathisant', true),
            'is_admin' => user_can($uid, 'manage_options'),
            'is_moderator' => user_can($uid, 'wpsd_moderate_registrations'),
        ],
        'subscription' => [
            'status' => get_user_meta($uid, 'subscription_status', true),
            'plan_key' => get_user_meta($uid, 'plan_key', true),
            'plan_label' => get_user_meta($uid, 'plan_label', true),
            'payment_method' => get_user_meta($uid, 'payment_method', true),
            'stripe_customer_id' => get_user_meta($uid, 'stripe_customer_id', true),
            'stripe_subscription_id' => get_user_meta($uid, 'stripe_subscription_id', true),
            'subscription_start' => get_user_meta($uid, 'subscription_start', true),
            'subscription_end' => get_user_meta($uid, 'subscription_end', true),
            'wpsd_admin_approved' => get_user_meta($uid, 'wpsd_admin_approved', true),
        ],
        'institution' => [
            'name' => get_user_meta($uid, 'inst_name', true),
            'email' => get_user_meta($uid, 'inst_email', true),
            'phone' => get_user_meta($uid, 'inst_phone', true),
            'address_line1' => get_user_meta($uid, 'inst_address_line1', true),
            'address_line2' => get_user_meta($uid, 'inst_address_line2', true),
            'postal_code' => get_user_meta($uid, 'inst_postal_code', true),
            'city' => get_user_meta($uid, 'inst_city', true),
            'country' => get_user_meta($uid, 'inst_country', true),
            'description' => get_user_meta($uid, 'inst_description', true),
        ],
        'itinerant' => [
            'trip_info' => get_user_meta($uid, 'itinerant_trip_info', true),
            'motivations' => get_user_meta($uid, 'itinerant_motivations', true),
        ],
        'family_members' => $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_family() . " WHERE user_id=%d", $uid
        ), ARRAY_A),
        'payments' => $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_payments() . " WHERE user_id=%d ORDER BY created_at DESC", $uid
        ), ARRAY_A),
        'reservations_made' => $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE itinerant_user_id=%d ORDER BY created_at DESC", $uid
        ), ARRAY_A),
        'reservations_received' => $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE provider_user_id=%d ORDER BY created_at DESC", $uid
        ), ARRAY_A),
        'activities' => self::get_user_cpt_data($uid, 'wpsd_activity'),
        'accommodations' => self::get_user_cpt_data($uid, 'wpsd_accommodation'),
        'articles' => self::get_user_cpt_data($uid, 'wpsd_article'),
    ];

    return $data;
}

private static function get_user_cpt_data($user_id, $post_type) {
    $posts = get_posts([
        'post_type' => $post_type,
        'meta_key' => 'owner_user_id',
        'meta_value' => $user_id,
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);
    return array_map(function($p) {
        return [
            'id' => $p->ID,
            'title' => $p->post_title,
            'status' => $p->post_status,
            'date' => $p->post_date,
        ];
    }, $posts);
}

public static function anonymize_user($user_id) {
    global $wpdb;
    $uid = (int)$user_id;

    // Anonymize WordPress user
    $anonymized_email = 'anonymized_' . $uid . '@deleted.sentiers-des-savoirs.fr';
    $anonymized_name = 'Utilisateur supprimé #' . $uid;

    wp_update_user([
        'ID' => $uid,
        'user_email' => $anonymized_email,
        'display_name' => $anonymized_name,
        'user_nicename' => sanitize_title($anonymized_name),
    ]);

    // Clear personal meta
    $meta_to_clear = [
        'first_name', 'last_name', 'phone',
        'address_line1', 'address_line2', 'postal_code', 'city', 'country',
        'bio_text', 'profile_photo_id',
        'inst_name', 'inst_email', 'inst_phone',
        'inst_address_line1', 'inst_address_line2', 'inst_postal_code', 'inst_city', 'inst_country', 'inst_description',
        'itinerant_trip_info', 'itinerant_motivations',
        'stripe_customer_id', 'stripe_subscription_id', 'price_id',
        'rgpd_consent', 'rgpd_consent_at',
    ];
    foreach ($meta_to_clear as $key) {
        delete_user_meta($uid, $key);
    }

    // Set status flags
    update_user_meta($uid, 'subscription_status', 'deleted');
    update_user_meta($uid, 'wpsd_admin_approved', 0);
    update_user_meta($uid, 'gdpr_deleted_at', current_time('mysql'));

    // Delete family members
    $wpdb->delete(WPSD_DB::table_family(), ['user_id' => $uid]);

    // Unpublish CPTs
    foreach (['wpsd_activity', 'wpsd_accommodation', 'wpsd_article'] as $cpt) {
        $posts = get_posts(['post_type' => $cpt, 'meta_key' => 'owner_user_id', 'meta_value' => $uid, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids']);
        foreach ($posts as $pid) {
            wp_update_post(['ID' => $pid, 'post_status' => 'draft']);
        }
    }

    // Log the action
    error_log('GDPR: User #' . $uid . ' anonymized at ' . current_time('mysql'));
}
}

add_action('wpsd_pre_arrival_notice', 'wpsd_send_pre_arrival_emails');

function wpsd_send_pre_arrival_emails() {
    global $wpdb;
    $days_before = (int) apply_filters('wpsd_pre_arrival_days', 3);
    $target_date = date('Y-m-d', strtotime('+' . $days_before . ' days'));
    $reservations = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE status='approved' AND date_start=%s AND pre_arrival_sent=0", $target_date), ARRAY_A);
    if (empty($reservations)) return;

    foreach ($reservations as $r) {
        $itinerant = get_userdata($r['itinerant_user_id']); if (!$itinerant) continue;
        $provider = get_userdata($r['provider_user_id']);
        $object_title = get_the_title($r['object_id']) ?: '';

        $date_start = !empty($r['date_start']) ? date_i18n('d/m/Y', strtotime($r['date_start'])) : '';
        $date_end   = !empty($r['date_end'])   ? date_i18n('d/m/Y', strtotime($r['date_end']))   : '';

        $data = [
            'display_name'  => esc_html($itinerant->display_name ?: $itinerant->user_login),
            'provider_name' => $provider ? esc_html($provider->display_name ?: $provider->user_login) : '',
            'object_title'  => esc_html($object_title),
            'date_start'    => $date_start,
            'date_end'      => $date_end,
            'days'          => (string) $days_before,
        ];

        $subject = WPSD_Data::get_email_template('pre_arrival', 'subject');
        $body    = WPSD_Data::get_email_template('pre_arrival', 'body');

        // Fallback to hard-coded defaults if template is empty
        if (empty($subject) || empty($body)) {
            $subject = 'Votre séjour commence bientôt';
            $body = "<p>Bonjour {{display_name}},</p>\n<p>Votre séjour chez <strong>{{provider_name}}</strong> commence dans <strong>{{days}} jours</strong> (le {{date_start}}).</p>\n<p><strong>Activité :</strong> {{object_title}}</p>\n<p>Bon séjour !</p>";
        }

        $subject_rendered = WPSD_Data::render_email_template($subject, $data);
        $body_rendered    = WPSD_Data::render_email_template($body, $data);
        $body_rendered   .= WPSD_Data::get_email_legal_suffix();

        // Send to itinerant
        wp_mail($itinerant->user_email, $subject_rendered, $body_rendered, ['Content-Type: text/html; charset=UTF-8']);

        // Send to provider with swapped names
        if ($provider) {
            $provider_data = $data;
            $provider_data['display_name']  = esc_html($provider->display_name ?: $provider->user_login);
            $provider_data['provider_name'] = esc_html($itinerant->display_name ?: $itinerant->user_login);

            $provider_subject = WPSD_Data::render_email_template($subject, $provider_data);
            $provider_body    = WPSD_Data::render_email_template($body, $provider_data);
            $provider_body   .= WPSD_Data::get_email_legal_suffix();

            wp_mail($provider->user_email, $provider_subject, $provider_body, ['Content-Type: text/html; charset=UTF-8']);
        }

        $wpdb->update(WPSD_DB::table_reservations(), ['pre_arrival_sent' => 1], ['id' => $r['id']], ['%d'], ['%d']);
    }
}