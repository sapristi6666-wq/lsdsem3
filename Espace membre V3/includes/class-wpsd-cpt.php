<?php
if (!defined('ABSPATH')) exit;

class WPSD_CPT {

  public function __construct() {
    add_action('init', [$this, 'register']);

    if (is_admin()) {
      add_filter('manage_wpsd_activity_posts_columns', [$this, 'activity_columns']);
      add_action('manage_wpsd_activity_posts_custom_column', [$this, 'activity_column_content'], 10, 2);

      add_filter('manage_wpsd_accommodation_posts_columns', [$this, 'acc_columns']);
      add_action('manage_wpsd_accommodation_posts_custom_column', [$this, 'acc_column_content'], 10, 2);

      add_action('restrict_manage_posts', [$this, 'admin_owner_filter']);
      add_action('pre_get_posts', [$this, 'admin_owner_filter_query']);

      add_action('add_meta_boxes', [$this, 'add_wpsd_metaboxes']);
      add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    }
  }

  public function register() {
    register_post_type('wpsd_activity', [
      'labels' => [
        'name' => 'Activités',
        'singular_name' => 'Activité',
      ],
      'public' => false,
      'show_ui' => true,
      'supports' => ['title', 'editor', 'thumbnail'],
      'menu_icon' => 'dashicons-calendar-alt',
    ]);

    register_post_type('wpsd_accommodation', [
      'labels' => [
        'name' => 'Hébergements',
        'singular_name' => 'Hébergement',
      ],
      'public' => false,
      'show_ui' => true,
      'supports' => ['title', 'editor', 'thumbnail'],
      'menu_icon' => 'dashicons-admin-multisite',
    ]);
    register_post_type('wpsd_article', [
  'labels' => [
    'name' => 'Articles (commantaires)',
    'singular_name' => 'Article',
  ],
  'public' => true,
  'show_ui' => true,
  'show_in_menu' => true,
  'has_archive' => true,
  'rewrite' => ['slug' => 'articles'],
  'supports' => ['title','editor','thumbnail','excerpt'],
  'show_in_rest' => true,
  'capability_type' => 'post',
  'map_meta_cap' => true,
]);

  }
  
  

  /* ---------------- COLONNES ADMIN ---------------- */

  public function activity_columns($cols) {
    $new = [];
    $new['cb'] = $cols['cb'] ?? 'cb';
    $new['title'] = 'Titre';
    $new['wpsd_owner'] = 'Utilisateur';
    $new['wpsd_city'] = 'Ville';
    $new['wpsd_postal'] = 'CP';
    $new['wpsd_status'] = 'Statut';
    $new['date'] = $cols['date'] ?? 'Date';
    return $new;
  }

  public function activity_column_content($col, $post_id) {
    if ($col === 'wpsd_owner') {
      $uid = (int) get_post_meta($post_id, 'owner_user_id', true);
      if ($uid) {
        $u = get_user_by('id', $uid);
        echo $u ? esc_html($u->user_email) : ('#' . (int)$uid);
      } else {
        echo '—';
      }
    }

    if ($col === 'wpsd_city') {
      echo esc_html(get_post_meta($post_id, 'city', true) ?: '—');
    }

    if ($col === 'wpsd_postal') {
      echo esc_html(get_post_meta($post_id, 'postal_code', true) ?: '—');
    }

    if ($col === 'wpsd_status') {
      $st = get_post_status($post_id);
      echo ($st === 'pending') ? 'En cours de validation' : (($st === 'publish') ? 'Validé' : esc_html($st));
    }
  }

  public function acc_columns($cols) {
    $new = [];
    $new['cb'] = $cols['cb'] ?? 'cb';
    $new['title'] = 'Titre';
    $new['wpsd_owner'] = 'Utilisateur';
    $new['wpsd_caps'] = 'Capacité';
    $new['wpsd_city'] = 'Ville';
    $new['wpsd_postal'] = 'CP';
    $new['wpsd_status'] = 'Statut';
    $new['date'] = $cols['date'] ?? 'Date';
    return $new;
  }

  public function acc_column_content($col, $post_id) {
    if ($col === 'wpsd_owner') {
      $uid = (int) get_post_meta($post_id, 'owner_user_id', true);
      if ($uid) {
        $u = get_user_by('id', $uid);
        echo $u ? esc_html($u->user_email) : ('#' . (int)$uid);
      } else {
        echo '—';
      }
    }

    if ($col === 'wpsd_caps') {
      $a = (int) get_post_meta($post_id, 'capacity_adults', true);
      $c = (int) get_post_meta($post_id, 'capacity_children', true);
      echo 'Adultes: ' . $a . ' / Enfants: ' . $c;
    }

    if ($col === 'wpsd_city') {
      echo esc_html(get_post_meta($post_id, 'city', true) ?: '—');
    }

    if ($col === 'wpsd_postal') {
      echo esc_html(get_post_meta($post_id, 'postal_code', true) ?: '—');
    }

    if ($col === 'wpsd_status') {
      $st = get_post_status($post_id);
      echo ($st === 'pending') ? 'En cours de validation' : (($st === 'publish') ? 'Validé' : esc_html($st));
    }
  }

  public function admin_owner_filter() {
    global $typenow;
    if (!in_array($typenow, ['wpsd_activity', 'wpsd_accommodation'], true)) return;

    $selected = isset($_GET['wpsd_owner']) ? (int)$_GET['wpsd_owner'] : 0;

    wp_dropdown_users([
      'show_option_all' => 'Tous les utilisateurs',
      'name' => 'wpsd_owner',
      'selected' => $selected,
      'orderby' => 'display_name',
      'order' => 'ASC',
      'show' => 'display_name',
      'include_selected' => true,
      'class' => 'postform',
    ]);
  }

  public function admin_owner_filter_query($q) {
    if (!is_admin() || !$q->is_main_query()) return;

    $pt = $q->get('post_type');
    if (!in_array($pt, ['wpsd_activity', 'wpsd_accommodation'], true)) return;

    if (!empty($_GET['wpsd_owner'])) {
      $uid = (int) $_GET['wpsd_owner'];
      $q->set('meta_query', [
        [
          'key' => 'owner_user_id',
          'value' => $uid,
          'compare' => '=',
          'type' => 'NUMERIC',
        ]
      ]);
    }
  }

  /* ---------------- METABOXES ---------------- */

  public function add_wpsd_metaboxes() {
    add_meta_box(
      'wpsd_details_box',
      'Détails WPSD',
      [$this, 'render_details_box'],
      ['wpsd_activity', 'wpsd_accommodation'],
      'normal',
      'high'
    );

    add_meta_box(
      'wpsd_calendar_box',
      'Calendrier des disponibilités',
      [$this, 'render_calendar_box'],
      ['wpsd_activity', 'wpsd_accommodation'],
      'normal',
      'default'
    );
  }

  public function admin_assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;

    // plus robuste : on veut uniquement l'édition d'un post de ces CPT
    $is_edit_screen =
      ($screen->base === 'post' && in_array($screen->post_type, ['wpsd_activity','wpsd_accommodation'], true));

    if (!$is_edit_screen) return;

    wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css', [], null);
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', [], null, true);

    wp_enqueue_script(
      'fullcalendar-locales',
      'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js',
      ['fullcalendar'],
      null,
      true
    );

    wp_add_inline_script('fullcalendar-locales', $this->admin_calendar_js(), 'after');
  }

private function admin_calendar_js() {
  return <<<'JS'
(function(){
  function addOneDay(dateStr){
    const d = new Date(dateStr + "T00:00:00");
    d.setDate(d.getDate() + 1);
    return d.toISOString().slice(0,10);
  }

  document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll("[data-wpsd-calendar]").forEach(function(el){
      const payload = el.getAttribute("data-wpsd-calendar");
      if (!payload) return;

      let data;
      try { data = JSON.parse(payload); } catch(e){ return; }

      const events = (data.items || []).map(function(s){
        const isActivity = data.kind === "activity";
        const unitLabel = isActivity ? "places" : "logements";

        const total = parseInt(s.total ?? (isActivity ? s.capacity : s.units) ?? 0, 10) || 0;
        const reserved = parseInt(s.reserved ?? 0, 10) || 0;
        const remaining = parseInt(s.remaining ?? (total - reserved), 10) || 0;

        return {
          id: String(s.id),
          title: `${remaining}/${total} ${unitLabel} (réservé: ${reserved})`,
          start: s.date_start,
          end: addOneDay(s.date_end),
          allDay: true
        };
      });

      const cal = new FullCalendar.Calendar(el, {
        initialView: "dayGridMonth",
        height: "auto",
        locale: "fr",
        firstDay: 1,
        events: events
      });

      cal.render();
    });
  });
})();
JS;
}


  /* ---------------- DETAILS BOX ---------------- */

  public function render_details_box($post) {
    $type = get_post_type($post);
    $owner_id = (int) get_post_meta($post->ID, 'owner_user_id', true);
    $owner = $owner_id ? get_user_by('id', $owner_id) : null;

    $addr1 = get_post_meta($post->ID, 'address_line1', true);
    $addr2 = get_post_meta($post->ID, 'address_line2', true);
    $cp    = get_post_meta($post->ID, 'postal_code', true);
    $city  = get_post_meta($post->ID, 'city', true);
    $country = get_post_meta($post->ID, 'country', true);

    $thumb = get_the_post_thumbnail_url($post->ID, 'medium');

    echo '<div style="display:flex;gap:16px;align-items:flex-start;">';

    echo '<div style="min-width:180px;">';
    if ($thumb) {
      echo '<img src="'.esc_url($thumb).'" style="max-width:180px;height:auto;border-radius:8px;" />';
    } else {
      echo '<div style="width:180px;height:120px;background:#f1f1f1;border-radius:8px;display:flex;align-items:center;justify-content:center;">Aucune image</div>';
    }
    echo '</div>';

    echo '<div style="flex:1;">';
    echo '<p><strong>Statut :</strong> '.esc_html(get_post_status($post)).'</p>';
    echo '<p><strong>Utilisateur :</strong> '.($owner ? esc_html($owner->user_email) : '—').'</p>';

    echo '<p><strong>Adresse :</strong><br>';
    echo esc_html($addr1);
    if ($addr2) echo '<br>'.esc_html($addr2);
    echo '<br>'.esc_html(trim($cp.' '.$city));
    if ($country) echo '<br>'.esc_html($country);
    echo '</p>';

    if ($type === 'wpsd_accommodation') {
      $a = (int) get_post_meta($post->ID, 'capacity_adults', true);
      $c = (int) get_post_meta($post->ID, 'capacity_children', true);
      echo '<p><strong>Capacité :</strong> Adultes: '.(int)$a.' / Enfants: '.(int)$c.'</p>';
      echo '<p style="color:#666;margin:0;">(Nombre de logements géré dans le calendrier des disponibilités)</p>';
    } else {
      echo '<p style="color:#666;margin:0;">(Capacité des activités gérée dans le calendrier des disponibilités)</p>';
    }

    echo '</div></div>';

    echo '<hr style="margin:12px 0;">';
    echo '<p><strong>Description :</strong></p>';
    echo '<div style="padding:10px;background:#fafafa;border:1px solid #eee;border-radius:8px;">'
      . wp_kses_post(wpautop($post->post_content))
      . '</div>';
  }

  /* ---------------- CALENDAR BOX ---------------- */

  public function render_calendar_box($post) {
    global $wpdb;

    $type = get_post_type($post);
    $kind = ($type === 'wpsd_activity') ? 'activity' : 'accommodation';

    $owner_id = (int) get_post_meta($post->ID, 'owner_user_id', true);
    if (!$owner_id) {
      echo '<p>Aucun propriétaire (owner_user_id) défini.</p>';
      return;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT 
          s.id, s.date_start, s.date_end, s.capacity, s.units,
          COALESCE(SUM(CASE WHEN r.status IN ('pending','approved') THEN r.quantity ELSE 0 END),0) AS reserved
       FROM " . WPSD_DB::table_slots() . " s
       LEFT JOIN " . WPSD_DB::table_reservations() . " r
         ON r.slot_id = s.id
       WHERE s.user_id=%d AND s.kind=%s AND s.object_id=%d
       GROUP BY s.id
       ORDER BY s.date_start ASC",
      $owner_id, $kind, (int)$post->ID
    ), ARRAY_A);

    // ✅ calcule total + remaining pour le JS + tableau
    foreach ($rows as &$r) {
      $total = ($kind === 'activity') ? (int)($r['capacity'] ?? 0) : (int)($r['units'] ?? 0);
      $reserved = (int)($r['reserved'] ?? 0);
      $remaining = max(0, $total - $reserved);

      $r['total'] = $total;
      $r['remaining'] = $remaining;
      $r['reserved'] = $reserved;
    }
    unset($r);

    $payload = wp_json_encode([
      'kind' => $kind,
      'items' => $rows
    ]);
    
    $resRows = $wpdb->get_results($wpdb->prepare(
  "SELECT r.*
   FROM " . WPSD_DB::table_reservations() . " r
   WHERE r.kind=%s AND r.object_id=%d
   ORDER BY r.created_at DESC
   LIMIT 200",
  $kind, (int)$post->ID
), ARRAY_A);

$labels = [
  'pending'   => 'En attente',
  'approved'  => 'Accepté',
  'rejected'  => 'Refusé',
  'canceled'  => 'Annulé',
  'completed' => 'Terminé',
];

    echo '<div data-wpsd-calendar="'.esc_attr($payload).'" style="background:#fff;"></div>';

    echo '<hr style="margin:12px 0;">';
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Début</th><th>Fin</th><th>Total</th><th>Réservé</th><th>Reste</th></tr></thead><tbody>';

    if (!$rows) {
      echo '<tr><td colspan="5">Aucune disponibilité.</td></tr>';
    } else {
      foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>'.esc_html($r['date_start']).'</td>';
        echo '<td>'.esc_html($r['date_end']).'</td>';
        echo '<td>'.esc_html((int)$r['total']).'</td>';
        echo '<td>'.esc_html((int)$r['reserved']).'</td>';
        echo '<td>'.esc_html((int)$r['remaining']).'</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';
    
    
    echo '<hr style="margin:16px 0;">';
echo '<h3 style="margin:0 0 8px;">Réservations liées</h3>';

if (!$resRows) {
  echo '<p>Aucune réservation pour cet élément.</p>';
  return;
}

echo '<table class="widefat striped">';
echo '<thead><tr>';
echo '<th>ID</th>';
echo '<th>Statut</th>';
echo '<th>Dates</th>';
echo '<th>Qté</th>';
echo '<th>Itinérant</th>';
echo '<th>Prestataire</th>';
echo '<th>Confirmations</th>';
echo '<th>Notes</th>';
echo '<th>Créée</th>';
echo '</tr></thead><tbody>';

foreach ($resRows as $r) {
  $itId = (int)($r['itinerant_user_id'] ?? 0);
  $prId = (int)($r['provider_user_id'] ?? 0);

  $it = $itId ? get_userdata($itId) : null;
  $pr = $prId ? get_userdata($prId) : null;

  $itEmail = $it ? $it->user_email : ('#' . $itId);
  $prEmail = $pr ? $pr->user_email : ('#' . $prId);

  $itLink = $itId ? admin_url('user-edit.php?user_id=' . $itId) : '';
  $prLink = $prId ? admin_url('user-edit.php?user_id=' . $prId) : '';

  $status = $r['status'] ?? '';
  $statusLabel = $labels[$status] ?? $status;

  $provOk = !empty($r['provider_done']) ? '✅' : '❌';
  $itiOk  = !empty($r['itinerant_done']) ? '✅' : '❌';

  echo '<tr>';
  echo '<td>' . (int)$r['id'] . '</td>';
  echo '<td><strong>' . esc_html($statusLabel) . '</strong><br><small>' . esc_html($status) . '</small></td>';
  echo '<td>' . esc_html($r['date_start'] . ' → ' . $r['date_end']) . '</td>';
  echo '<td>' . (int)($r['quantity'] ?? 0) . '</td>';

  echo '<td>';
  if ($itLink) echo '<a href="' . esc_url($itLink) . '">' . esc_html($itEmail) . '</a>';
  else echo esc_html($itEmail);
  echo '</td>';

  echo '<td>';
  if ($prLink) echo '<a href="' . esc_url($prLink) . '">' . esc_html($prEmail) . '</a>';
  else echo esc_html($prEmail);
  echo '</td>';

  echo '<td>Prestataire: ' . $provOk . '<br>Itinérant: ' . $itiOk . '</td>';

  $pNote = $r['provider_note'] ?? '';
  $iNote = $r['itinerant_note'] ?? '';

  echo '<td>';
  echo '<div><strong>P:</strong> ' . esc_html($pNote ?: '—') . '</div>';
  echo '<div><strong>I:</strong> ' . esc_html($iNote ?: '—') . '</div>';
  echo '</td>';

  echo '<td>' . esc_html($r['created_at'] ?? '—') . '</td>';
  echo '</tr>';
}

echo '</tbody></table>';

  }
}
