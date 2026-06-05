<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Articles {
    use WPSD_REST_Helpers;
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/articles', [
            'methods' => 'GET', 'callback' => [$this, 'list'], 'permission_callback' => fn() => is_user_logged_in() && $this->is_active_user(),
        ]);
        register_rest_route('wpsd/v1', '/articles', [
            'methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => fn() => is_user_logged_in() && $this->is_active_user(),
        ]);
        register_rest_route('wpsd/v1', '/articles/(?P<id>\\d+)', [
            'methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => fn() => is_user_logged_in() && $this->is_active_user(),
        ]);
        register_rest_route('wpsd/v1', '/articles/(?P<id>\\d+)', [
            'methods' => 'DELETE', 'callback' => [$this, 'delete'], 'permission_callback' => fn() => is_user_logged_in() && $this->is_active_user(),
        ]);
    }

    public function list() {
        $uid = get_current_user_id();
        $q = new WP_Query(['post_type' => 'wpsd_article', 'post_status' => ['publish','pending','draft'], 'posts_per_page' => 200, 'meta_key' => 'owner_user_id', 'meta_value' => $uid]);
        $items = array_map(function($p){ return ['id' => $p->ID, 'title' => $p->post_title, 'content' => $p->post_content, 'status' => $p->post_status, 'status_label' => ($p->post_status === 'pending') ? 'En attente de validation' : (($p->post_status === 'publish') ? 'Publié' : $p->post_status), 'photo_id' => (int)get_post_thumbnail_id($p->ID), 'photo_url' => get_the_post_thumbnail_url($p->ID, 'medium') ?: '', 'created_at' => $p->post_date]; }, $q->posts);
        return new WP_REST_Response(['items' => $items], 200);
    }

    public function create(WP_REST_Request $req) {
        $uid = get_current_user_id(); $p = $req->get_json_params() ?: [];
        $post_id = wp_insert_post(['post_type' => 'wpsd_article', 'post_status' => 'publish', 'post_title' => sanitize_text_field($p['title'] ?? ''), 'post_content' => wp_kses_post($p['content'] ?? '')], true);
        if (is_wp_error($post_id)) return new WP_REST_Response(['ok' => false, 'error' => $post_id->get_error_message()], 400);
        update_post_meta($post_id, 'owner_user_id', $uid);
        if (!empty($p['photo_id'])) { $photo_id = (int)$p['photo_id']; if ($photo_id > 0) set_post_thumbnail($post_id, $photo_id); }
        return new WP_REST_Response(['ok' => true, 'id' => $post_id], 200);
    }

    public function update(WP_REST_Request $req) {
        $uid = get_current_user_id(); $id = (int)$req['id']; $p = $req->get_json_params() ?: [];
        if ((int)get_post_meta($id, 'owner_user_id', true) !== $uid) return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wpsd_article') return new WP_REST_Response(['ok' => false, 'error' => 'Not found'], 404);
        $new_status = ($post->post_status === 'publish') ? 'pending' : $post->post_status;
        wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field($p['title'] ?? $post->post_title), 'post_content' => wp_kses_post($p['content'] ?? $post->post_content), 'post_status' => $new_status]);
        if (array_key_exists('photo_id', $p)) { $photo_id = (int)$p['photo_id']; if ($photo_id > 0) set_post_thumbnail($id, $photo_id); else delete_post_thumbnail($id); }
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function delete(WP_REST_Request $req) {
        $uid = get_current_user_id(); $id = (int)$req['id'];
        if ((int)get_post_meta($id, 'owner_user_id', true) !== $uid) return new WP_REST_Response(['ok' => false, 'error' => 'Forbidden'], 403);
        wp_delete_post($id, true);
        return new WP_REST_Response(['ok' => true], 200);
    }
}