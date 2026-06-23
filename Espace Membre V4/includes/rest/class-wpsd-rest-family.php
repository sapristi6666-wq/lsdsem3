<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Family {
    use WPSD_REST_Helpers;

    public function register_routes() {
        register_rest_route('wpsd/v1', '/family', [
            'methods' => 'GET', 'callback' => [$this, 'list'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/family', [
            'methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/family/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/family/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [$this, 'delete'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function list() {
        $uid = get_current_user_id();
        global $wpdb;
        $table = WPSD_DB::table_family(); // suppose que vous avez une table wpsd_family
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY id ASC", $uid), ARRAY_A);
        return new WP_REST_Response(['items' => $items ?: []], 200);
    }

    public function create(WP_REST_Request $req) {
        $uid = get_current_user_id();
        $p = $req->get_json_params();
        $data = [
            'user_id' => $uid,
            'first_name' => sanitize_text_field($p['first_name'] ?? ''),
            'last_name' => sanitize_text_field($p['last_name'] ?? ''),
            'email' => sanitize_email($p['email'] ?? ''),
            'phone' => sanitize_text_field($p['phone'] ?? ''),
            'birth_date' => sanitize_text_field($p['birth_date'] ?? ''),
            'address_line1' => sanitize_text_field($p['address_line1'] ?? ''),
            'postal_code' => sanitize_text_field($p['postal_code'] ?? ''),
            'city' => sanitize_text_field($p['city'] ?? ''),
            'bio_text' => sanitize_textarea_field($p['bio_text'] ?? ''),
            'photo_id' => (int)($p['photo_id'] ?? 0),
        ];
        global $wpdb;
        $table = WPSD_DB::table_family();
        $wpdb->insert($table, $data);
        $id = $wpdb->insert_id;
        return new WP_REST_Response(['ok' => true, 'id' => $id], 200);
    }

    public function update(WP_REST_Request $req) {
        $uid = get_current_user_id();
        $id = (int)$req['id'];
        // Vérification propriétaire
        global $wpdb;
        $table = WPSD_DB::table_family();
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $id));
        if ($owner != $uid) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        $p = $req->get_json_params();
        $data = [];
        foreach (['first_name','last_name','email','phone','birth_date','address_line1','postal_code','city','bio_text','photo_id'] as $field) {
            if (isset($p[$field])) $data[$field] = ($field === 'photo_id') ? (int)$p[$field] : sanitize_text_field($p[$field]);
        }
        $wpdb->update($table, $data, ['id' => $id]);
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function delete(WP_REST_Request $req) {
        $uid = get_current_user_id();
        $id = (int)$req['id'];
        global $wpdb;
        $table = WPSD_DB::table_family();
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $id));
        if ($owner != $uid) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        $wpdb->delete($table, ['id' => $id]);
        return new WP_REST_Response(['ok' => true], 200);
    }
}