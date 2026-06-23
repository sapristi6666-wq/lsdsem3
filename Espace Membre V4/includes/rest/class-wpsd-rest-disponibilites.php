<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Disponibilites {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('wpsd/v2', '/disponibilites', [
            'methods' => 'GET',
            'callback' => [$this, 'get_disponibilites'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_disponibilites($request) {
        global $wpdb;

        $date_debut = sanitize_text_field($request->get_param('date_debut'));
        $date_fin = sanitize_text_field($request->get_param('date_fin'));
        $types = $request->get_param('type');
        if (!is_array($types)) {
            $types = ['activity', 'hebergement'];
        }

        $results = [];

        if (in_array('activity', $types, true)) {
            $activities = $this->get_cpt_of_type('wpsd_activity', $date_debut, $date_fin);
            foreach ($activities as $a) {
                $owner_id = (int) get_post_meta($a->ID, 'owner_user_id', true) ?: (int) $a->post_author;
                $card = $this->get_member_card($owner_id);
                $photo_id = (int) get_post_meta($a->ID, 'photo_id', true);
                $photo_url = $photo_id ? wp_get_attachment_url($photo_id) : '';
                $results[] = [
                    'type' => 'activity',
                    'id' => $a->ID,
                    'title' => $a->post_title,
                    'owner_id' => $owner_id,
                    'owner_name' => $card['display_name'],
                    'lat' => (float) get_post_meta($a->ID, 'lat', true),
                    'lng' => (float) get_post_meta($a->ID, 'lng', true),
                    'photo_url' => $photo_url,
                    'member_card' => $card,
                ];
            }
        }

        if (in_array('hebergement', $types, true) || in_array('accommodation', $types, true)) {
            $hebergements = $this->get_cpt_of_type('wpsd_accommodation', $date_debut, $date_fin);
            foreach ($hebergements as $h) {
                $owner_id = (int) get_post_meta($h->ID, 'owner_user_id', true) ?: (int) $h->post_author;
                $card = $this->get_member_card($owner_id);
                $photo_id = (int) get_post_meta($h->ID, 'photo_id', true);
                $photo_url = $photo_id ? wp_get_attachment_url($photo_id) : '';
                $results[] = [
                    'type' => 'accommodation',
                    'id' => $h->ID,
                    'title' => $h->post_title,
                    'owner_id' => $owner_id,
                    'owner_name' => $card['display_name'],
                    'lat' => (float) get_post_meta($h->ID, 'lat', true),
                    'lng' => (float) get_post_meta($h->ID, 'lng', true),
                    'photo_url' => $photo_url,
                    'member_card' => $card,
                ];
            }
        }

        $results = $this->merge_mixed_points($results);

        return rest_ensure_response($results);
    }

    private function get_cpt_of_type($post_type, $date_debut = '', $date_fin = '') {
        $args = [
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $posts = get_posts($args);
        return array_filter($posts, function($p) {
            $lat = get_post_meta($p->ID, 'lat', true);
            $lng = get_post_meta($p->ID, 'lng', true);
            return !empty($lat) && !empty($lng);
        });
    }

    private function get_member_card($user_id) {
        global $wpdb;
        $table = WPSD_DB::table_member_cards();
        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $user = get_userdata($user_id);
        $first_name = $user ? get_user_meta($user_id, 'first_name', true) : '';
        $last_name = $user ? get_user_meta($user_id, 'last_name', true) : '';
        $full_name = trim($first_name . ' ' . $last_name);

        $result = [
            'display_name' => $full_name ?: ($user ? $user->display_name : ''),
        ];

        if ($card && (int) $card['visible_carte'] === 1) {
            $result['photo_url'] = $card['photo_url'];
            $result['bio'] = $card['bio'];
            $result['centre_interet'] = $card['centre_interet'];
            $result['langues'] = $card['langues'];
        } elseif (!$card) {
            $visible_default = (int) get_user_meta($user_id, 'visible_carte', true);
            if ($visible_default !== 0) {
                $result['photo_url'] = '';
                $result['bio'] = '';
                $result['centre_interet'] = '';
                $result['langues'] = '';
            }
        }

        return $result;
    }

    /**
     * Fusionne les points qui ont les mêmes coordonnées et le même propriétaire
     * en un seul point mixte (activité + hébergement).
     */
    private function merge_mixed_points($points) {
        $merged = [];
        $groups = [];

        // Regrouper par propriétaire, puis par coordonnées
        foreach ($points as $p) {
            $owner = $p['owner_id'];
            $coord = $p['lat'] . ',' . $p['lng'];
            $groups[$owner][$coord][] = $p;
        }

        foreach ($groups as $owner_id => $coord_groups) {
            foreach ($coord_groups as $coord => $items) {
                if (count($items) === 1) {
                    // Un seul type, pas de fusion
                    $item = $items[0];
                    $item['is_mixed'] = false;
                    $item['type_display'] = $item['type'] === 'activity' ? 'activity' : 'accommodation';
                    $merged[] = $item;
                } else {
                    // Plusieurs types aux mêmes coordonnées
                    $activity = null;
                    $accommodation = null;
                    foreach ($items as $it) {
                        if ($it['type'] === 'activity') $activity = $it;
                        elseif ($it['type'] === 'accommodation' || $it['type'] === 'hebergement') $accommodation = $it;
                    }
                    if ($activity && $accommodation) {
                        // Créer un point mixte unique basé sur l'activité
                        $mixed_point = $activity;
                        $mixed_point['is_mixed'] = true;
                        $mixed_point['type_display'] = 'mixed';
                        $mixed_point['acc_title'] = $accommodation['title'];
                        $mixed_point['acc_photo_url'] = $accommodation['photo_url'] ?? '';
                        $merged[] = $mixed_point;
                    } else {
                        // Si pas de paire, on ajoute chaque élément normalement
                        foreach ($items as $it) {
                            $it['is_mixed'] = false;
                            $it['type_display'] = $it['type'] === 'activity' ? 'activity' : 'accommodation';
                            $merged[] = $it;
                        }
                    }
                }
            }
        }

        return $merged;
    }
}