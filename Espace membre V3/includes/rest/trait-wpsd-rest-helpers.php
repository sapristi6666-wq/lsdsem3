<?php
if (!defined('ABSPATH')) exit;

trait WPSD_REST_Helpers {

    private function is_active_user() {
        $uid = get_current_user_id();
        if (current_user_can('manage_options') || current_user_can('wpsd_moderate_registrations')) {
            return true;
        }
        return $uid && $this->stripe && $this->stripe->is_active($uid);
    }

    private function require_itinerant() {
        $uid = get_current_user_id();
        return $uid && (int)get_user_meta($uid, 'is_itinerant', true) === 1 && $this->is_active_user();
    }

    private function require_provider() {
        $uid = get_current_user_id();
        if (!$uid || !$this->is_active_user()) return false;
        $is_passeur   = (int)get_user_meta($uid, 'is_passeur', true) === 1;
        $is_hebergeur = (int)get_user_meta($uid, 'is_hebergeur', true) === 1;
        return ($is_passeur || $is_hebergeur);
    }

    private function require_active_or_403() {
        $uid = get_current_user_id();
        if (current_user_can('manage_options') || current_user_can('wpsd_moderate_registrations')) {
            return null;
        }
        $status = get_user_meta($uid, 'subscription_status', true);
        if (!in_array($status, ['active','trialing'], true)) {
            return new WP_REST_Response(['error' => 'Abonnement non actif'], 403);
        }
        return null;
    }

    private function reservations_table() {
        return WPSD_DB::table_reservations();
    }

    private function slots_table() {
        return WPSD_DB::table_slots();
    }

    private function save_location_meta($post_id, $p) {
        $line1   = sanitize_text_field($p['address_line1'] ?? '');
        $line2   = sanitize_text_field($p['address_line2'] ?? '');
        $pc      = sanitize_text_field($p['postal_code'] ?? '');
        $city    = sanitize_text_field($p['city'] ?? '');
        $country = sanitize_text_field($p['country'] ?? 'FR');

        update_post_meta($post_id, 'address_line1', $line1);
        update_post_meta($post_id, 'address_line2', $line2);
        update_post_meta($post_id, 'postal_code',   $pc);
        update_post_meta($post_id, 'city',          $city);
        update_post_meta($post_id, 'country',       $country);

        $lat = (isset($p['lat']) && $p['lat'] !== '') ? (float)$p['lat'] : null;
        $lng = (isset($p['lng']) && $p['lng'] !== '') ? (float)$p['lng'] : null;

        if (($lat === null || $lng === null) && ($line1 || $pc || $city)) {
            $addr = trim($line1 . ' ' . $line2 . ', ' . $pc . ' ' . $city . ', ' . $country);
            $geo = $this->geocode_address($addr);
            if ($geo) {
                $lat = $geo['lat'];
                $lng = $geo['lng'];
            }
        }

        update_post_meta($post_id, 'lat', $lat !== null ? $lat : '');
        update_post_meta($post_id, 'lng', $lng !== null ? $lng : '');
    }

    private function save_photo($post_id, $p) {
        $photo_id = isset($p['photo_id']) ? (int)$p['photo_id'] : 0;
        if ($photo_id <= 0) return;
        $att = get_post($photo_id);
        if (!$att || $att->post_type !== 'attachment') return;
        if ((int)$att->post_author !== get_current_user_id()) return;
        set_post_thumbnail($post_id, $photo_id);
        update_post_meta($post_id, 'photo_id', $photo_id);
    }

    private function geocode_address($addr) {
        $q = trim((string)$addr);
        if ($q === '') return null;
        $url = add_query_arg(['q' => $q, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'fr'], 'https://nominatim.openstreetmap.org/search');
        $res = wp_remote_get($url, ['timeout' => 10, 'headers' => ['Accept' => 'application/json', 'User-Agent' => 'SentiersDesSavoirs/1.0']]);
        if (is_wp_error($res)) return null;
        if ((int)wp_remote_retrieve_response_code($res) !== 200) return null;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($json) || empty($json[0]['lat'])) return null;
        return ['lat' => (float)$json[0]['lat'], 'lng' => (float)$json[0]['lon']];
    }
}