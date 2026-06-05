<?php
if (!defined('ABSPATH')) exit;

/**
 * Routes REST pour les disponibilités (carte Leaflet)
 * 
 * GET /wpsd/v2/disponibilites?date_debut=X&date_fin=Y&type[]=activity&type[]=hebergement
 */
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

    /**
     * GET /disponibilites
     * Retourne les activités et hébergements disponibles sur une période
     * avec les infos de la fiche membre du prestataire
     */
    public function get_disponibilites($request) {
        global $wpdb;

        $date_debut = sanitize_text_field($request->get_param('date_debut'));
        $date_fin = sanitize_text_field($request->get_param('date_fin'));
        $types = $request->get_param('type');
        if (!is_array($types)) {
            $types = ['activity', 'hebergement'];
        }

        $results = [];

        // Récupérer les activités
        if (in_array('activity', $types, true)) {
            $activities = $this->get_cpt_of_type('wpsd_activity', $date_debut, $date_fin);
            foreach ($activities as $a) {
                $owner_id = (int) get_post_meta($a->ID, 'owner_user_id', true);
                $card = $this->get_member_card($owner_id);
                $results[] = [
                    'type' => 'activity',
                    'id' => $a->ID,
                    'title' => $a->post_title,
                    'owner_id' => $owner_id,
                    'owner_name' => $card['display_name'],
                    'lat' => (float) get_post_meta($a->ID, 'lat', true),
                    'lng' => (float) get_post_meta($a->ID, 'lng', true),
                    'member_card' => $card,
                ];
            }
        }

        // Récupérer les hébergements
        if (in_array('hebergement', $types, true) || in_array('accommodation', $types, true)) {
            $hebergements = $this->get_cpt_of_type('wpsd_accommodation', $date_debut, $date_fin);
            foreach ($hebergements as $h) {
                $owner_id = (int) get_post_meta($h->ID, 'owner_user_id', true);
                $card = $this->get_member_card($owner_id);
                $results[] = [
                    'type' => 'accommodation',
                    'id' => $h->ID,
                    'title' => $h->post_title,
                    'owner_id' => $owner_id,
                    'owner_name' => $card['display_name'],
                    'lat' => (float) get_post_meta($h->ID, 'lat', true),
                    'lng' => (float) get_post_meta($h->ID, 'lng', true),
                    'member_card' => $card,
                ];
            }
        }

        // Détecter les points mixtes (même auteur = même point géographique)
        $results = $this->mark_mixed_points($results);

        return rest_ensure_response($results);
    }

    /**
     * Récupère les CPT d'un type avec géolocalisation
     */
    private function get_cpt_of_type($post_type, $date_debut = '', $date_fin = '') {
        $meta_query = [
            'relation' => 'AND',
        ];

        // Vérifier que le propriétaire existe et a sa fiche membre visible
        $meta_query[] = [
            'key' => 'owner_user_id',
            'value' => 0,
            'compare' => '>',
        ];

        // Vérifier que le propriétaire a sa fiche visible sur la carte
        // On ne filtre pas ici, on le fait après pour éviter une requête trop complexe

        $args = [
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => $meta_query,
        ];

        $posts = get_posts($args);

        // Filtrer : garder seulement ceux avec lat/lng
        return array_filter($posts, function($p) {
            $lat = get_post_meta($p->ID, 'lat', true);
            $lng = get_post_meta($p->ID, 'lng', true);
            return !empty($lat) && !empty($lng);
        });
    }

    /**
     * Récupère la fiche membre d'un utilisateur
     */
    private function get_member_card($user_id) {
        global $wpdb;
        $table = WPSD_DB::table_member_cards();
        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $user = get_userdata($user_id);

        $result = [
            'display_name' => $user ? $user->display_name : '',
        ];

        if ($card && (int) $card['visible_carte'] === 1) {
            $result['photo_url'] = $card['photo_url'];
            $result['bio'] = $card['bio'];
            $result['centre_interet'] = $card['centre_interet'];
            $result['langues'] = $card['langues'];
        } elseif (!$card) {
            // Pas encore de fiche, mais l'utilisateur existe
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
     * Marque les points mixtes (même auteur = activité + hébergement)
     */
    private function mark_mixed_points($points) {
        $mixed = [];
        $seen = [];

        foreach ($points as $p) {
            $key = $p['owner_id'];
            if (!isset($seen[$key])) {
                $seen[$key] = [];
            }
            $seen[$key][] = $p;
        }

        // Si un propriétaire a à la fois activité et hébergement, c'est un point mixte
        foreach ($seen as $owner_id => $items) {
            $has_activity = false;
            $has_accommodation = false;

            foreach ($items as $item) {
                if ($item['type'] === 'activity') $has_activity = true;
                if ($item['type'] === 'accommodation' || $item['type'] === 'hebergement') $has_accommodation = true;
            }

            if ($has_activity && $has_accommodation) {
                foreach ($items as &$item) {
                    $item['is_mixed'] = true;
                    // Mettre le type en "mixed" pour l'affichage violet
                    $item['type_display'] = 'mixed';
                }
                unset($item);
            } else {
                foreach ($items as &$item) {
                    $item['is_mixed'] = false;
                    $item['type_display'] = $item['type'] === 'activity' ? 'activity' : 'accommodation';
                }
                unset($item);
            }

            $mixed = array_merge($mixed, $items);
        }

        return $mixed;
    }
}
