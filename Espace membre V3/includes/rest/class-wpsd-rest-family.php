<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Family {
    use WPSD_REST_Helpers;
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/family-members', [
            'methods' => 'GET', 'callback' => [$this, 'list'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/family-members', [
            'methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/family-members/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/family-members/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [$this, 'delete'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function list() {
        global $wpdb;
        $user_id = get_current_user_id();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . WPSD_DB::table_family() . " WHERE user_id = %d ORDER BY id DESC", $user_id), ARRAY_A);
        return new WP_REST_Response(['items' => $rows], 200);
    }

    public function create(WP_REST_Request $req) {
        global $wpdb;
        $user_id = get_current_user_id(); $p = $req->get_json_params() ?: [];
        $data = [
            'user_id' => $user_id, 'first_name' => sanitize_text_field($p['first_name'] ?? ''),
            'last_name' => sanitize_text_field($p['last_name'] ?? ''), 'email' => sanitize_email($p['email'] ?? ''),
            'phone' => sanitize_text_field($p['phone'] ?? ''), 'address_line1' => sanitize_text_field($p['address_line1'] ?? ''),
            'address_line2' => sanitize_text_field($p['address_line2'] ?? ''), 'postal_code' => sanitize_text_field($p['postal_code'] ?? ''),
            'city' => sanitize_text_field($p['city'] ?? ''), 'country' => sanitize_text_field($p['country'] ?? 'FR'),
            'bio_text' => sanitize_textarea_field($p['bio_text'] ?? ''), 'birth_date' => !empty($p['birth_date']) ? sanitize_text_field($p['birth_date']) : null,
        ];
        $wpdb->insert(WPSD_DB::table_family(), $data);
        return new WP_REST_Response(['ok' => true, 'id' => $wpdb->insert_id], 200);
    }

    public function update(WP_REST_Request $req) {
        global $wpdb;
        $user_id = get_current_user_id(); $id = intval($req['id']); $p = $req->get_json_params() ?: [];
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM " . WPSD_DB::table_family() . " WHERE id=%d", $id));
        if ((int)$owner !== $user_id) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        $data = []; $fmt = [];
        $s = function($k) use (&$data, &$fmt, $p) { if (array_key_exists($k, $p)) { $data[$k] = sanitize_text_field($p[$k]); $fmt[] = '%s'; } };
        $se = function($k) use (&$data, &$fmt, $p) { if (array_key_exists($k, $p)) { $data[$k] = sanitize_email($p[$k]); $fmt[] = '%s'; } };
        $st = function($k) use (&$data, &$fmt, $p) { if (array_key_exists($k, $p)) { $data[$k] = sanitize_textarea_field($p[$k]); $fmt[] = '%s'; } };
        $s('first_name'); $s('last_name'); $se('email'); $s('phone');
        $s('address_line1'); $s('address_line2'); $s('postal_code'); $s('city'); $s('country');
        $st('bio_text');
        if (array_key_exists('birth_date', $p)) { $data['birth_date'] = !empty($p['birth_date']) ? sanitize_text_field($p['birth_date']) : null; $fmt[] = '%s'; }
        if (empty($data)) return new WP_REST_Response(['ok' => true, 'skipped' => true], 200);
        $wpdb->update(WPSD_DB::table_family(), $data, ['id' => $id], $fmt, ['%d']);
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function delete(WP_REST_Request $req) {
        global $wpdb;
        $user_id = get_current_user_id(); $id = intval($req['id']);
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM " . WPSD_DB::table_family() . " WHERE id=%d", $id));
        if ((int)$owner !== $user_id) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        $wpdb->delete(WPSD_DB::table_family(), ['id' => $id]);
        return new WP_REST_Response(['ok' => true], 200);
    }
}