<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Accommodations {
    use WPSD_REST_Helpers;
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/accommodations', [
            'methods' => 'GET', 'callback' => [$this, 'list'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/accommodations', [
            'methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/accommodations/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/accommodations/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [$this, 'delete'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function list() {
        if ($r = $this->require_active_or_403()) return $r;
        $uid = get_current_user_id();
        $q = new WP_Query(['post_type' => 'wpsd_accommodation', 'post_status' => ['publish','publish'], 'posts_per_page' => 50, 'meta_key' => 'owner_user_id', 'meta_value' => $uid]);
        $items = array_map(function($p){ return ['id' => $p->ID, 'title' => $p->post_title, 'description' => $p->post_content, 'capacity_adults' => (int)get_post_meta($p->ID, 'capacity_adults', true), 'capacity_children' => (int)get_post_meta($p->ID, 'capacity_children', true), 'address_line1' => get_post_meta($p->ID, 'address_line1', true), 'address_line2' => get_post_meta($p->ID, 'address_line2', true), 'postal_code' => get_post_meta($p->ID, 'postal_code', true), 'city' => get_post_meta($p->ID, 'city', true), 'country' => get_post_meta($p->ID, 'country', true), 'lat' => get_post_meta($p->ID, 'lat', true), 'lng' => get_post_meta($p->ID, 'lng', true), 'photo_id' => (int)get_post_thumbnail_id($p->ID), 'photo_url' => get_the_post_thumbnail_url($p->ID, 'medium') ?: '', 'status' => $p->post_status, 'status_label' => ($p->post_status === 'pending') ? 'En cours de validation' : 'Validé']; }, $q->posts);
        return new WP_REST_Response(['items' => $items], 200);
    }

    public function create(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        $uid = get_current_user_id(); $p = $req->get_json_params() ?: [];
        $post_id = wp_insert_post(['post_type' => 'wpsd_accommodation', 'post_status' => 'publish', 'post_title' => sanitize_text_field($p['title'] ?? ''), 'post_content' => wp_kses_post($p['description'] ?? '')], true);
        if (is_wp_error($post_id)) return new WP_REST_Response(['error' => $post_id->get_error_message()], 400);
        update_post_meta($post_id, 'owner_user_id', $uid);
        update_post_meta($post_id, 'capacity_adults', (int)($p['capacity_adults'] ?? 0));
        update_post_meta($post_id, 'capacity_children', (int)($p['capacity_children'] ?? 0));
        $this->save_location_meta($post_id, $p);
        $this->save_photo($post_id, $p);
        return new WP_REST_Response(['ok' => true, 'id' => $post_id], 200);
    }

    public function update(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        $uid = get_current_user_id(); $id = (int)$req['id']; $p = $req->get_json_params() ?: [];
        $this->save_location_meta($id, $p); $this->save_photo($id, $p);
        if ((int)get_post_meta($id, 'owner_user_id', true) !== $uid) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field($p['title'] ?? ''), 'post_content' => wp_kses_post($p['description'] ?? '')]);
        if (isset($p['capacity_adults'])) update_post_meta($id, 'capacity_adults', (int)$p['capacity_adults']);
        if (isset($p['capacity_children'])) update_post_meta($id, 'capacity_children', (int)$p['capacity_children']);
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function delete(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        $uid = get_current_user_id(); $id = (int)$req['id'];
        if ((int)get_post_meta($id, 'owner_user_id', true) !== $uid) return new WP_REST_Response(['error' => 'Forbidden'], 403);
        wp_delete_post($id, true);
        return new WP_REST_Response(['ok' => true], 200);
    }
}