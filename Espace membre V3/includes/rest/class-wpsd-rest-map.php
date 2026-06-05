<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Map {
    use WPSD_REST_Helpers;
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/map-points', [
            'methods' => 'GET', 'callback' => [$this, 'map_points'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/availability', [
            'methods' => 'GET', 'callback' => [$this, 'availability_check'], 'permission_callback' => fn() => is_user_logged_in() && $this->require_itinerant(),
        ]);
        register_rest_route('wpsd/v1', '/upload-image', [
            'methods' => 'POST', 'callback' => [$this, 'upload_image'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/itinerary/draft', [
            'methods' => 'GET', 'callback' => [$this, 'load_draft'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/itinerary/draft', [
            'methods' => 'POST', 'callback' => [$this, 'save_draft'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/itinerary/draft', [
            'methods' => 'DELETE', 'callback' => [$this, 'delete_draft'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function map_points(WP_REST_Request $req) {
        global $wpdb;
        $date_start = sanitize_text_field($req->get_param('date_start') ?? '');
        $date_end   = sanitize_text_field($req->get_param('date_end') ?? '');
        $kindFilter = sanitize_text_field($req->get_param('kind') ?? '');
        $page       = max(1, (int)($req->get_param('page') ?? 1));
        $per_page   = min(100, max(1, (int)($req->get_param('per_page') ?? 50)));

        $noDateFilter = (!$date_start && !$date_end);
        if (!$noDateFilter) {
            if (!$date_start || !$date_end) return new WP_REST_Response(['ok' => false, 'error' => 'date_start et date_end requis'], 400);
            if ($date_end < $date_start) { $tmp = $date_start; $date_start = $date_end; $date_end = $tmp; }
        }

        $kinds = ['activity' => 'wpsd_activity', 'accommodation' => 'wpsd_accommodation'];
        $out = [];
        $total_points = 0;

        foreach ($kinds as $kind => $cpt) {
            if ($kindFilter && $kindFilter !== $kind && !($kindFilter === 'both' && $kind === 'activity')) continue;

            $q = new WP_Query([
                'post_type'      => $cpt,
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'no_found_rows'  => false,
                'fields'         => 'ids',
                'meta_query'     => [
                    ['key' => 'lat', 'compare' => '!=', 'value' => ''],
                    ['key' => 'lng', 'compare' => '!=', 'value' => ''],
                ],
            ]);

            $total_points += (int)$q->found_posts;
            $object_ids = array_map('intval', $q->posts);
            if (empty($object_ids)) continue;

            // Tous les slots en une requête
            $placeholders = implode(',', array_fill(0, count($object_ids), '%d'));
            $all_slots = [];
            if ($noDateFilter) {
                $all_slots = $wpdb->get_results($wpdb->prepare(
                    "SELECT s.* FROM {$this->slots_table()} s
                     INNER JOIN (SELECT kind, object_id, MIN(date_start) as min_start FROM {$this->slots_table()} WHERE kind=%s AND object_id IN ($placeholders) GROUP BY kind, object_id) grp
                     ON s.kind = grp.kind AND s.object_id = grp.object_id AND s.date_start = grp.min_start",
                    $kind, ...$object_ids
                ), ARRAY_A);
            } else {
                $params = array_merge([$kind], $object_ids, [$date_start, $date_end]);
                $all_slots = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$this->slots_table()} WHERE kind=%s AND object_id IN ($placeholders) AND date_start <= %s AND date_end >= %s ORDER BY date_start ASC",
                    ...$params
                ), ARRAY_A);
            }

            $slots_by_object = [];
            foreach ($all_slots as $s) {
                $oid = (int)$s['object_id'];
                if (!isset($slots_by_object[$oid])) $slots_by_object[$oid] = [];
                $slots_by_object[$oid][] = $s;
            }

            // Toutes les réservations en une requête
            $all_reserved = [];
            $all_slot_ids = array_map(function($s) { return (int)$s['id']; }, $all_slots);
            if (!empty($all_slot_ids)) {
                $slot_placeholders = implode(',', array_fill(0, count($all_slot_ids), '%d'));
                $reserved_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT slot_id, COALESCE(SUM(quantity),0) as total FROM {$this->reservations_table()} WHERE slot_id IN ($slot_placeholders) AND status IN ('pending','approved') GROUP BY slot_id",
                    ...$all_slot_ids
                ), ARRAY_A);
                foreach ($reserved_rows as $rr) { $all_reserved[(int)$rr['slot_id']] = (int)$rr['total']; }
            }

            foreach ($q->posts as $pid) {
                $lat = get_post_meta($pid, 'lat', true);
                $lng = get_post_meta($pid, 'lng', true);
                $provider_id = (int)get_post_meta($pid, 'owner_user_id', true) ?: (int)get_post_field('post_author', $pid);
                $provider = $provider_id ? get_userdata($provider_id) : null;
                $is_passeur = $provider_id && (int)get_user_meta($provider_id, 'is_passeur', true) === 1;
                $provider_type = $is_passeur ? 'passeur' : 'sympathisant';
                $thumb = get_the_post_thumbnail_url($pid, 'medium') ?: '';

                $best = null; $remaining = 0; $slot_total = 0; $reserved = 0; $slot_id = 0;
                $slots = $slots_by_object[$pid] ?? [];
                if ($slots) {
                    $best = $slots[0]; $slot_id = (int)$best['id'];
                    $slot_total = (int)($best['capacity'] ?? $best['units'] ?? 0);
                    $reserved = $all_reserved[$slot_id] ?? 0;
                    $remaining = max(0, $slot_total - $reserved);
                }

                $has_acc = $kind === 'activity' && (int)get_post_meta($pid, 'has_accommodation', true) === 1;
                $acc_capacity = $has_acc ? (int)get_post_meta($pid, 'acc_capacity', true) : 0;
                $display_kind = $has_acc ? 'both' : $kind;

                $out[] = [
                    'id' => (int)$pid, 'kind' => $display_kind, 'title' => get_the_title($pid),
                    'photo_url' => $thumb,
                    'address_line1' => (string)get_post_meta($pid, 'address_line1', true),
                    'address_line2' => (string)get_post_meta($pid, 'address_line2', true),
                    'postal_code' => (string)get_post_meta($pid, 'postal_code', true),
                    'city' => (string)get_post_meta($pid, 'city', true),
                    'country' => (string)get_post_meta($pid, 'country', true),
                    'lat' => (float)$lat, 'lng' => (float)$lng,
                    'provider_user_id' => (int)$provider_id,
                    'provider_name' => $provider ? ($provider->display_name ?: $provider->user_login) : '',
                    'provider_email' => $provider ? $provider->user_email : '',
                    'provider_type' => $provider_type,
                    'provider_first_name' => (string)($provider_id ? get_user_meta($provider_id, 'first_name', true) : ''),
                    'provider_last_name' => (string)($provider_id ? get_user_meta($provider_id, 'last_name', true) : ''),
                    'has_slot' => $best ? 1 : 0,
                    'slot_id' => (int)$slot_id, 'slot_total' => (int)$slot_total,
                    'reserved' => (int)$reserved, 'remaining' => (int)$remaining,
                    'has_accommodation' => $has_acc ? 1 : 0,
                    'acc_capacity' => $acc_capacity,
                ];
            }
        }

        return new WP_REST_Response([
            'ok' => true, 'items' => $out,
            'page' => $page, 'per_page' => $per_page,
            'total' => $total_points, 'total_pages' => ceil($total_points / $per_page),
        ], 200);
    }

    public function availability_check(WP_REST_Request $req) {
        global $wpdb;
        $kind = sanitize_text_field($req->get_param('kind') ?? ''); $object_id = (int)($req->get_param('object_id') ?? 0);
        $date_start = sanitize_text_field($req->get_param('date_start') ?? ''); $date_end = sanitize_text_field($req->get_param('date_end') ?? '');
        $quantity = max(1, (int)($req->get_param('quantity') ?? 1));
        if (!in_array($kind, ['activity','accommodation'], true) || $object_id <= 0 || !$date_start || !$date_end) return new WP_REST_Response(['ok' => false, 'error' => 'Paramètres invalides'], 400);
        if ($date_end < $date_start) { $tmp = $date_start; $date_start = $date_end; $date_end = $tmp; }
        $slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->slots_table()} WHERE kind=%s AND object_id=%d AND date_start <= %s AND date_end >= %s ORDER BY date_start ASC LIMIT 1", $kind, $object_id, $date_start, $date_end), ARRAY_A);
        if (!$slot) return new WP_REST_Response(['ok' => true, 'has_slot' => 0, 'slot_id' => 0, 'slot_total' => 0, 'reserved' => 0, 'remaining' => 0, 'can' => 0, 'quantity' => $quantity], 200);
        $slot_id = (int)$slot['id']; $slot_total = ($kind === 'activity') ? (int)($slot['capacity'] ?? 0) : (int)($slot['units'] ?? 0);
        $reserved = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity),0) FROM {$this->reservations_table()} WHERE slot_id=%d AND status IN ('pending','approved')", $slot_id));
        $remaining = max(0, $slot_total - $reserved);
        return new WP_REST_Response(['ok' => true, 'has_slot' => 1, 'slot_id' => $slot_id, 'slot_total' => $slot_total, 'reserved' => $reserved, 'remaining' => $remaining, 'can' => ($quantity <= $remaining) ? 1 : 0, 'quantity' => $quantity], 200);
    }

    public function upload_image(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        $uid = get_current_user_id();
        $files = $req->get_file_params();
        if (empty($files['file']) || empty($files['file']['tmp_name'])) return new WP_REST_Response(['ok' => false, 'error' => 'Fichier manquant'], 400);
        $file = $files['file'];
        if (!empty($file['size']) && (int)$file['size'] > 5 * 1024 * 1024) return new WP_REST_Response(['ok' => false, 'error' => 'Image trop lourde (max 5 Mo)'], 400);
        $allowed = ['jpg','jpeg','png','webp','gif']; $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $ext = strtolower($check['ext'] ?? ''); $type = strtolower($check['type'] ?? '');
        if (!$ext || !in_array($ext, $allowed, true) || !str_starts_with($type, 'image/')) return new WP_REST_Response(['ok' => false, 'error' => 'Type non autorisé'], 400);
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $att_id = media_handle_upload('file', 0);
        if (is_wp_error($att_id)) return new WP_REST_Response(['ok' => false, 'error' => $att_id->get_error_message()], 400);
        wp_update_post(['ID' => $att_id, 'post_author' => $uid]);
        $url = wp_get_attachment_image_url($att_id, 'medium') ?: wp_get_attachment_url($att_id);
        return new WP_REST_Response(['ok' => true, 'id' => (int)$att_id, 'url' => $url ?: ''], 200);
    }

    public function load_draft() {
        global $wpdb;
        $uid = get_current_user_id();
        $table = WPSD_DB::table_itinerary_drafts();
        $row = $wpdb->get_row($wpdb->prepare("SELECT data FROM $table WHERE user_id = %d", $uid), ARRAY_A);
        if (!$row) return new WP_REST_Response(['ok' => true, 'data' => null], 200);
        $data = json_decode($row['data'], true);
        return new WP_REST_Response(['ok' => true, 'data' => $data], 200);
    }

    public function save_draft(WP_REST_Request $req) {
        global $wpdb;
        $uid = get_current_user_id();
        $data = $req->get_json_params();
        $table = WPSD_DB::table_itinerary_drafts();
        $json = wp_json_encode($data);
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $uid));
        if ($exists) {
            $wpdb->update($table, ['data' => $json], ['user_id' => $uid], ['%s'], ['%d']);
        } else {
            $wpdb->insert($table, ['user_id' => $uid, 'data' => $json], ['%d', '%s']);
        }
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function delete_draft() {
        global $wpdb;
        $uid = get_current_user_id();
        $table = WPSD_DB::table_itinerary_drafts();
        $wpdb->delete($table, ['user_id' => $uid], ['%d']);
        return new WP_REST_Response(['ok' => true], 200);
    }
}