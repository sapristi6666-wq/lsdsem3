<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Slots {
    use WPSD_REST_Helpers;
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/slots', [
            'methods' => 'GET', 'callback' => [$this, 'list'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/slots', [
            'methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/slots/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [$this, 'delete'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function list(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        global $wpdb;
        $uid = get_current_user_id();
        $kind = sanitize_text_field($req->get_param('kind') ?? '');
        $object_id = (int)($req->get_param('object_id') ?? 0);
        $target_uid = $uid;
        if ($object_id) {
            $owner_id = (int)get_post_meta($object_id, 'owner_user_id', true);
            if ($owner_id > 0) $target_uid = $owner_id;
            if ($target_uid !== $uid && !current_user_can('manage_options')) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        }
        $where = "s.user_id=%d"; $params = [$target_uid];
        if ($kind) { $where .= " AND s.kind=%s"; $params[] = $kind; }
        if ($object_id) { $where .= " AND s.object_id=%d"; $params[] = $object_id; }
        $sql = "SELECT s.*, COALESCE(SUM(r.quantity),0) AS reserved FROM {$this->slots_table()} s LEFT JOIN {$this->reservations_table()} r ON r.slot_id = s.id AND r.status IN ('pending','approved') WHERE $where GROUP BY s.id ORDER BY s.date_start ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        foreach ($rows as &$row) {
            $total = ($row['kind'] === 'activity') ? (int)($row['capacity'] ?? 0) : (int)($row['units'] ?? 0);
            $row['slot_total'] = $total;
            $row['reserved'] = (int)($row['reserved'] ?? 0);
            $row['remaining'] = max(0, $total - $row['reserved']);
        }
        unset($row);
        return new WP_REST_Response(['items' => $rows], 200);
    }

    public function create(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        global $wpdb;
        $uid = get_current_user_id(); $p = $req->get_json_params() ?: [];
        $kind = sanitize_text_field($p['kind'] ?? ''); $object_id = (int)($p['object_id'] ?? 0);
        if (!in_array($kind, ['activity','accommodation'], true) || $object_id <= 0) return new WP_REST_Response(['ok' => false, 'error' => 'kind/object_id invalid'], 400);
        $post_type = ($kind === 'activity') ? 'wpsd_activity' : 'wpsd_accommodation';
        $owner = (int)get_post_meta($object_id, 'owner_user_id', true);
        if ($owner !== $uid || get_post_type($object_id) !== $post_type) return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
        $date_start = sanitize_text_field($p['date_start'] ?? ''); $date_end = sanitize_text_field($p['date_end'] ?? '');
        if ($date_start && !$date_end) $date_end = $date_start;
        if (!$date_start) return new WP_REST_Response(['ok' => false, 'error' => 'date_start required'], 400);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_end)) return new WP_REST_Response(['ok' => false, 'error' => 'Invalid date format'], 400);
        if (!$date_end) $date_end = $date_start;
        $ts_start = strtotime($date_start); $ts_end = strtotime($date_end);
        if (!$ts_start || !$ts_end) return new WP_REST_Response(['ok' => false, 'error' => 'Invalid date'], 400);
        if ($ts_end < $ts_start) { $tmp = $date_start; $date_start = $date_end; $date_end = $tmp; }
        $capacity = isset($p['capacity']) ? (int)$p['capacity'] : null;
        $units = isset($p['units']) ? (int)$p['units'] : null;
        if ($kind === 'activity') { if ($capacity === null || $capacity < 0) $capacity = 0; $units = null; }
        else { if ($units === null || $units < 0) $units = 0; $capacity = null; }
        $table = WPSD_DB::table_slots();
        $existing_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d AND kind=%s AND object_id=%d AND date_start=%s AND date_end=%s LIMIT 1", $uid, $kind, $object_id, $date_start, $date_end));
        if ($existing_id > 0) {
            $wpdb->update($table, ['capacity' => $capacity, 'units' => $units], ['id' => $existing_id], ['%d','%d'], ['%d']);
            return new WP_REST_Response(['ok' => true, 'id' => $existing_id, 'updated' => 1], 200);
        }
        $wpdb->insert($table, ['user_id' => $uid, 'kind' => $kind, 'object_id' => $object_id, 'date_start' => $date_start, 'date_end' => $date_end, 'capacity' => $capacity, 'units' => $units], ['%d','%s','%d','%s','%s','%d','%d']);
        return new WP_REST_Response(['ok' => true, 'id' => (int)$wpdb->insert_id, 'created' => 1], 200);
    }

    public function delete(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        global $wpdb;
        $uid = get_current_user_id(); $id = (int)$req['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . WPSD_DB::table_slots() . " WHERE id=%d", $id), ARRAY_A);
        if (!$row || (int)$row['user_id'] !== $uid) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        $wpdb->delete(WPSD_DB::table_slots(), ['id' => $id]);
        return new WP_REST_Response(['ok' => true], 200);
    }
}