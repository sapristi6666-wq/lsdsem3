<?php
if (!defined('ABSPATH')) exit;

/**
 * Routes REST pour les fiches membres
 * 
 * GET /wpsd/v2/member-card/{user_id}
 * PUT /wpsd/v2/member-card/me
 */
class WPSD_REST_MemberCard {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('wpsd/v2', '/member-card/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_member_card'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('wpsd/v2', '/member-card/me', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_my_card'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
    }

    public function check_logged_in() {
        return is_user_logged_in();
    }

    /**
     * GET /member-card/{user_id}
     * Retourne la fiche membre d'un utilisateur
     */
    public function get_member_card($request) {
        global $wpdb;
        $user_id = (int) $request->get_param('user_id');

        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('not_found', 'Utilisateur introuvable', ['status' => 404]);
        }

        $table = WPSD_DB::table_member_cards();
        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $result = [
            'user_id' => $user_id,
            'display_name' => $user->display_name,
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'photo_url' => '',
            'bio' => '',
            'bio_excerpt' => '',
            'centre_interet' => '',
            'langues' => [],
            'visible_carte' => false,
            'roles' => [],
        ];

        // Rôles
        $role_map = [
            'is_itinerant' => 'Itinérant',
            'is_passeur' => 'Passeur',
            'is_hebergeur' => 'Hébergeur',
            'is_sympathisant' => 'Sympathisant',
        ];
        foreach ($role_map as $meta_key => $label) {
            if ((int) get_user_meta($user_id, $meta_key, true) === 1) {
                $result['roles'][] = $label;
            }
        }

        if ($card) {
            $result['photo_url'] = $card['photo_url'] ?: '';
            $result['bio'] = $card['bio'] ?: '';
            $result['bio_excerpt'] = $card['bio'] ? wp_trim_words($card['bio'], 30) : '';
            $result['centre_interet'] = $card['centre_interet'] ?: '';
            $result['langues'] = $card['langues'] ? array_map('trim', explode(',', $card['langues'])) : [];
            $result['visible_carte'] = (int) $card['visible_carte'] === 1;
        }

        return rest_ensure_response($result);
    }

    /**
     * PUT /member-card/me
     * Met à jour la fiche membre de l'utilisateur connecté
     */
    public function update_my_card($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = WPSD_DB::table_member_cards();

        $photo_url = sanitize_url($request->get_param('photo_url'));
        $bio = sanitize_textarea_field($request->get_param('bio'));
        $centre_interet = sanitize_text_field($request->get_param('centre_interet'));
        $langues = $request->get_param('langues');
        $visible_carte = $request->get_param('visible_carte');

        // Validation
        if ($bio && mb_strlen($bio) > 300) {
            return new WP_Error('validation_error', 'La bio ne doit pas dépasser 300 caractères', ['status' => 400]);
        }

        // Convertir les langues en string
        $langues_str = '';
        if (is_array($langues)) {
            $safe_langues = [];
            foreach ($langues as $l) {
                $safe_langues[] = sanitize_text_field($l);
            }
            $langues_str = implode(', ', $safe_langues);
        } elseif (is_string($langues)) {
            $langues_str = sanitize_text_field($langues);
        }

        $data = [
            'user_id' => $user_id,
            'photo_url' => $photo_url ?: '',
            'bio' => $bio ?: '',
            'centre_interet' => $centre_interet ?: '',
            'langues' => $langues_str,
            'visible_carte' => $visible_carte !== null ? (int) $visible_carte : 1,
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            $wpdb->update(
                $table,
                $data,
                ['user_id' => $user_id],
                ['%d', '%s', '%s', '%s', '%s', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%d']
            );
        }

        return rest_ensure_response([
            'message' => 'Fiche membre mise à jour',
            'card' => $this->get_member_card($request)->data,
        ]);
    }
}
