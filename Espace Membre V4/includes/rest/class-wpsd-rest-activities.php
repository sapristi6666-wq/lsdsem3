<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Activities {
    use WPSD_REST_Helpers;
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/activities', [
            'methods' => 'GET',
            'callback' => [$this, 'list'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/activities', [
            'methods' => 'POST',
            'callback' => [$this, 'create'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/activities/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/activities/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function list() {
        if ($r = $this->require_active_or_403()) return $r;
        $uid = get_current_user_id();
        
        $q = new WP_Query([
            'post_type' => 'wpsd_activity',
            'post_status' => ['publish'],
            'posts_per_page' => 50,
            'meta_key' => 'owner_user_id',
            'meta_value' => $uid
        ]);
        
        $items = array_map(function($p) {
            return [
                'id'                => $p->ID,
                'title'             => $p->post_title,
                'description'       => $p->post_content,
                'address_line1'     => get_post_meta($p->ID, 'address_line1', true),
                'address_line2'     => get_post_meta($p->ID, 'address_line2', true),
                'postal_code'       => get_post_meta($p->ID, 'postal_code', true),
                'city'              => get_post_meta($p->ID, 'city', true),
                'country'           => get_post_meta($p->ID, 'country', true),
                'lat'               => get_post_meta($p->ID, 'lat', true),
                'lng'               => get_post_meta($p->ID, 'lng', true),
                'photo_id'          => (int) get_post_thumbnail_id($p->ID),
                'photo_url'         => get_the_post_thumbnail_url($p->ID, 'medium') ?: '',
                'status'            => $p->post_status,
                'status_label'      => 'Validé',
                'has_accommodation' => (int) get_post_meta($p->ID, 'has_accommodation', true),
                'acc_capacity'      => (int) get_post_meta($p->ID, 'acc_capacity', true),
            ];
        }, $q->posts);
        
        return new WP_REST_Response(['items' => $items], 200);
    }

    public function create(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        
        $uid = get_current_user_id();
        $p = $req->get_json_params() ?: [];
        
        $post_id = wp_insert_post([
            'post_type'    => 'wpsd_activity',
            'post_status'  => 'publish',
            'post_title'   => sanitize_text_field($p['title'] ?? ''),
            'post_content' => wp_kses_post($p['description'] ?? ''),
        ], true);
        
        if (is_wp_error($post_id)) {
            return new WP_REST_Response(['error' => $post_id->get_error_message()], 400);
        }
        
        update_post_meta($post_id, 'owner_user_id', $uid);
        update_post_meta($post_id, 'has_accommodation', !empty($p['has_accommodation']) ? 1 : 0);
        update_post_meta($post_id, 'acc_capacity', (int)($p['acc_capacity'] ?? 0));
        
        $this->save_location_meta($post_id, $p);
        $this->save_photo($post_id, $p);
        
        return new WP_REST_Response(['ok' => true, 'id' => $post_id], 200);
    }

    public function update(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        
        $uid = get_current_user_id();
        $id = (int) $req['id'];
        $p = $req->get_json_params() ?: [];
        
        // ✅ Vérifier la propriété AVANT de modifier quoi que ce soit
        if ((int) get_post_meta($id, 'owner_user_id', true) !== $uid) {
            return new WP_REST_Response(['error' => 'Forbidden'], 403);
        }
        
        // Mise à jour du contenu
        wp_update_post([
            'ID'           => $id,
            'post_title'   => sanitize_text_field($p['title'] ?? ''),
            'post_content' => wp_kses_post($p['description'] ?? ''),
        ]);
        
        // Mise à jour des metas
        if (isset($p['has_accommodation'])) {
            update_post_meta($id, 'has_accommodation', !empty($p['has_accommodation']) ? 1 : 0);
        }
        if (isset($p['acc_capacity'])) {
            update_post_meta($id, 'acc_capacity', (int) $p['acc_capacity']);
        }
        
        // Localisation et photo (après vérification de propriété)
        $this->save_location_meta($id, $p);
        $this->save_photo($id, $p);
        
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function delete(WP_REST_Request $req) {
        if ($r = $this->require_active_or_403()) return $r;
        
        $uid = get_current_user_id();
        $id = (int) $req['id'];
        
        if ((int) get_post_meta($id, 'owner_user_id', true) !== $uid) {
            return new WP_REST_Response(['error' => 'Forbidden'], 403);
        }
        
        wp_delete_post($id, true);
        
        return new WP_REST_Response(['ok' => true], 200);
    }
}