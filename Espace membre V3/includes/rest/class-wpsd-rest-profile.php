<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Profile {
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/profile', [
            'methods' => 'GET', 'callback' => [$this, 'get'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/profile', [
            'methods' => 'POST', 'callback' => [$this, 'save'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function get() {
        $u = wp_get_current_user(); $id = $u->ID;
        $data = [
            'is_itinerant' => (int)get_user_meta($id, 'is_itinerant', true), 'is_passeur' => (int)get_user_meta($id, 'is_passeur', true),
            'is_hebergeur' => (int)get_user_meta($id, 'is_hebergeur', true), 'is_sympathisant' => (int)get_user_meta($id, 'is_sympathisant', true),
            'itinerant_trip_info' => get_user_meta($id, 'itinerant_trip_info', true), 'itinerant_motivations' => get_user_meta($id, 'itinerant_motivations', true),
            'first_name' => get_user_meta($id, 'first_name', true), 'last_name' => get_user_meta($id, 'last_name', true),
            'phone' => get_user_meta($id, 'phone', true), 'address_line1' => get_user_meta($id, 'address_line1', true),
            'address_line2' => get_user_meta($id, 'address_line2', true), 'postal_code' => get_user_meta($id, 'postal_code', true),
            'city' => get_user_meta($id, 'city', true), 'country' => get_user_meta($id, 'country', true),
            'bio_text' => get_user_meta($id, 'bio_text', true), 'photo_id' => get_user_meta($id, 'profile_photo_id', true),
            'rgpd' => (int)get_user_meta($id, 'rgpd_consent', true), 'inst_name' => get_user_meta($id, 'inst_name', true),
            'inst_email' => get_user_meta($id, 'inst_email', true), 'inst_phone' => get_user_meta($id, 'inst_phone', true),
            'inst_address_line1' => get_user_meta($id, 'inst_address_line1', true), 'inst_address_line2' => get_user_meta($id, 'inst_address_line2', true),
            'inst_postal_code' => get_user_meta($id, 'inst_postal_code', true), 'inst_city' => get_user_meta($id, 'inst_city', true),
            'inst_country' => get_user_meta($id, 'inst_country', true), 'inst_description' => get_user_meta($id, 'inst_description', true),
        ];
        return new WP_REST_Response($data, 200);
    }

    public function save(WP_REST_Request $req) {
        $id = get_current_user_id(); $p = $req->get_json_params() ?: [];
        $set = function($k, $default = null) use ($p, $id) { if (array_key_exists($k, $p)) { $v = $p[$k]; if ($default !== null && ($v === null || $v === '')) $v = $default; update_user_meta($id, $k, sanitize_text_field($v)); } };
        $set_area = function($k) use ($p, $id) { if (array_key_exists($k, $p)) update_user_meta($id, $k, sanitize_textarea_field($p[$k])); };
        $set_email = function($k) use ($p, $id) { if (array_key_exists($k, $p)) update_user_meta($id, $k, sanitize_email($p[$k])); };
        $set_bool = function($k) use ($p, $id) { if (array_key_exists($k, $p)) update_user_meta($id, $k, !empty($p[$k]) ? 1 : 0); };
        $set('first_name'); $set('last_name'); $set('phone');
        $set('address_line1'); $set('address_line2'); $set('postal_code'); $set('city'); $set('country', 'FR');
        $set_area('bio_text');
        $set_bool('is_itinerant'); $set_bool('is_passeur'); $set_bool('is_hebergeur'); $set_bool('is_sympathisant');
        $set_area('itinerant_trip_info'); $set_area('itinerant_motivations');
        if (array_key_exists('rgpd', $p)) { $rgpd = !empty($p['rgpd']) ? 1 : 0; update_user_meta($id, 'rgpd_consent', $rgpd); if ($rgpd) update_user_meta($id, 'rgpd_consent_at', current_time('mysql')); }
        $set('inst_name'); $set_email('inst_email'); $set('inst_phone');
        $set('inst_address_line1'); $set('inst_address_line2'); $set('inst_postal_code'); $set('inst_city'); $set('inst_country', 'FR');
        $set_area('inst_description');
        return new WP_REST_Response(['ok' => true], 200);
    }
}