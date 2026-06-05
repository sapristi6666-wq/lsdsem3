<?php
if (!defined('ABSPATH')) exit;

/**
 * Routes REST pour les parcours et étapes
 * 
 * GET  /wpsd/v2/parcours/{id}
 * POST /wpsd/v2/parcours
 * PUT  /wpsd/v2/parcours/{id}
 * POST /wpsd/v2/parcours/{id}/send-demands
 * 
 * POST   /wpsd/v2/parcours/{id}/etapes
 * PUT    /wpsd/v2/etapes/{id}
 * DELETE /wpsd/v2/etapes/{id}
 */
class WPSD_REST_Parcours {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Parcours
        register_rest_route('wpsd/v2', '/parcours', [
            'methods' => 'GET',
            'callback' => [$this, 'list_parcours'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        register_rest_route('wpsd/v2', '/parcours/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_parcours'],
            'permission_callback' => [$this, 'check_access_parcours'],
        ]);
        register_rest_route('wpsd/v2', '/parcours', [
            'methods' => 'POST',
            'callback' => [$this, 'create_parcours'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        register_rest_route('wpsd/v2', '/parcours/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_parcours'],
            'permission_callback' => [$this, 'check_access_parcours'],
        ]);
        register_rest_route('wpsd/v2', '/parcours/(?P<id>\d+)/send-demands', [
            'methods' => 'POST',
            'callback' => [$this, 'send_demands'],
            'permission_callback' => [$this, 'check_owner_parcours'],
        ]);

        // Étapes
        register_rest_route('wpsd/v2', '/parcours/(?P<id>\d+)/etapes', [
            'methods' => 'POST',
            'callback' => [$this, 'create_etape'],
            'permission_callback' => [$this, 'check_owner_parcours'],
        ]);
        register_rest_route('wpsd/v2', '/etapes/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_etape'],
            'permission_callback' => [$this, 'check_owner_etape'],
        ]);
        register_rest_route('wpsd/v2', '/etapes/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_etape'],
            'permission_callback' => [$this, 'check_owner_etape'],
        ]);
    }

    // ==================== PERMISSIONS ====================

    public function check_logged_in() {
        return is_user_logged_in();
    }

    public function check_access_parcours($request) {
        if (!is_user_logged_in()) return false;
        $parcours_id = (int) $request->get_param('id');
        return WPSD_Capabilities::user_can_access_parcours($parcours_id);
    }

    public function check_owner_parcours($request) {
        if (!is_user_logged_in()) return false;
        $parcours_id = (int) $request->get_param('id');
        return WPSD_Capabilities::is_parcours_owner($parcours_id);
    }

    public function check_owner_etape($request) {
        if (!is_user_logged_in()) return false;
        $etape_id = (int) $request->get_param('id');
        global $wpdb;
        $parcours_id = $wpdb->get_var($wpdb->prepare(
            "SELECT parcours_id FROM " . WPSD_DB::table_etapes() . " WHERE id = %d",
            $etape_id
        ));
        if (!$parcours_id) return false;
        return WPSD_Capabilities::is_parcours_owner($parcours_id);
    }

    // ==================== PARCOURS ====================

    /**
     * GET /parcours?user_id=X
     * Liste les parcours d'un utilisateur
     */
    public function list_parcours($request) {
        global $wpdb;
        $user_id = (int) $request->get_param('user_id');
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        // L'utilisateur connecté ne peut voir que ses propres parcours
        // (sauf admin)
        if (!current_user_can('manage_options') && $user_id !== get_current_user_id()) {
            return new WP_Error('forbidden', 'Accès refusé', ['status' => 403]);
        }

        $parcours = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);

        if (empty($parcours)) {
            return rest_ensure_response([]);
        }

        // Pour chaque parcours, compter les étapes et réservations
        foreach ($parcours as &$p) {
            $nb_etapes = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d",
                $p['id']
            ));
            $p['nb_etapes'] = (int) $nb_etapes;

            $nb_reservations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . WPSD_DB::table_reservations() . " WHERE parcours_id = %d",
                $p['id']
            ));
            $p['nb_reservations'] = (int) $nb_reservations;
        }
        unset($p);

        return rest_ensure_response($parcours);
    }

    /**
     * GET /parcours/{id}
     * Retourne le parcours avec ses étapes et réservations
     */
    public function get_parcours($request) {
        global $wpdb;
        $parcours_id = (int) $request->get_param('id');

        $parcours = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $parcours_id
        ), ARRAY_A);

        if (!$parcours) {
            return new WP_Error('not_found', 'Parcours introuvable', ['status' => 404]);
        }

        // Récupérer les étapes
        $etapes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d ORDER BY numero_ordre ASC",
            $parcours_id
        ), ARRAY_A);

        // Ajouter les infos des activités/hébergements à chaque étape
        foreach ($etapes as &$etape) {
            $etape['activity'] = null;
            $etape['hebergement'] = null;
            if ($etape['activity_id']) {
                $post = get_post($etape['activity_id']);
                if ($post) {
                    $owner_id = (int) get_post_meta($post->ID, 'owner_user_id', true);
                    $owner = get_userdata($owner_id);
                    $etape['activity'] = [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'owner_id' => $owner_id,
                        'owner_name' => $owner ? $owner->display_name : '',
                    ];
                }
            }
            if ($etape['hebergement_id']) {
                $post = get_post($etape['hebergement_id']);
                if ($post) {
                    $owner_id = (int) get_post_meta($post->ID, 'owner_user_id', true);
                    $owner = get_userdata($owner_id);
                    $etape['hebergement'] = [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'owner_id' => $owner_id,
                        'owner_name' => $owner ? $owner->display_name : '',
                    ];
                }
            }
        }
        unset($etape);

        // Récupérer les réservations liées
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE parcours_id = %d ORDER BY date_start ASC",
            $parcours_id
        ), ARRAY_A);

        $parcours['etapes'] = $etapes;
        $parcours['reservations'] = $reservations;

        return rest_ensure_response($parcours);
    }

    /**
     * POST /parcours
     * Crée un nouveau parcours pour l'utilisateur connecté
     */
    public function create_parcours($request) {
        global $wpdb;
        $user_id = get_current_user_id();

        $date_debut = sanitize_text_field($request->get_param('date_debut_globale'));
        if (!$date_debut || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
            return new WP_Error('invalid_date', 'Date de début invalide (format YYYY-MM-DD)', ['status' => 400]);
        }

        $wpdb->insert(
            WPSD_DB::table_parcours(),
            [
                'user_id' => $user_id,
                'date_debut_globale' => $date_debut,
                'statut' => 'draft',
            ],
            ['%d', '%s', '%s']
        );

        $parcours_id = $wpdb->insert_id;

        // Créer automatiquement la première étape
        $this->create_first_etape($parcours_id, $date_debut);

        return rest_ensure_response([
            'id' => $parcours_id,
            'message' => 'Parcours créé avec succès',
        ]);
    }

    /**
     * Crée la première étape d'un parcours
     */
    private function create_first_etape($parcours_id, $date_debut) {
        global $wpdb;
        $date_fin = date('Y-m-d', strtotime($date_debut . ' +1 day'));
        $wpdb->insert(
            WPSD_DB::table_etapes(),
            [
                'parcours_id' => $parcours_id,
                'numero_ordre' => 1,
                'duree' => 1,
                'date_debut' => $date_debut,
                'date_fin' => $date_fin,
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * PUT /parcours/{id}
     * Modifie le statut ou annule un parcours
     */
    public function update_parcours($request) {
        global $wpdb;
        $parcours_id = (int) $request->get_param('id');
        $statut = sanitize_text_field($request->get_param('statut'));
        $valid_statuses = ['draft', 'pending_acceptance', 'accepted', 'paid', 'completed', 'cancelled'];

        if ($statut && !in_array($statut, $valid_statuses, true)) {
            return new WP_Error('invalid_status', 'Statut invalide', ['status' => 400]);
        }

        $parcours = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $parcours_id
        ), ARRAY_A);

        if (!$parcours) {
            return new WP_Error('not_found', 'Parcours introuvable', ['status' => 404]);
        }

        // Vérifier les transitions autorisées
        $allowed_transitions = [
            'draft' => ['pending_acceptance', 'cancelled'],
            'pending_acceptance' => ['accepted', 'cancelled'],
            'accepted' => ['paid', 'cancelled'],
            'paid' => ['completed', 'cancelled'],
        ];

        if ($statut && $statut !== $parcours['statut']) {
            $allowed = $allowed_transitions[$parcours['statut']] ?? [];
            if (!in_array($statut, $allowed, true)) {
                return new WP_Error(
                    'invalid_transition',
                    sprintf(
                        'Transition impossible : %s → %s',
                        $parcours['statut'],
                        $statut
                    ),
                    ['status' => 400]
                );
            }
        }

        $data = [];
        $formats = [];
        if ($statut) {
            $data['statut'] = $statut;
            $formats[] = '%s';
        }

        if (!empty($data)) {
            $wpdb->update(
                WPSD_DB::table_parcours(),
                $data,
                ['id' => $parcours_id],
                $formats,
                ['%d']
            );
        }

        return rest_ensure_response([
            'id' => $parcours_id,
            'statut' => $statut ?: $parcours['statut'],
            'message' => 'Parcours mis à jour',
        ]);
    }

    /**
     * POST /parcours/{id}/send-demands
     * Passe en pending_acceptance et envoie les emails aux prestataires
     */
    public function send_demands($request) {
        global $wpdb;
        $parcours_id = (int) $request->get_param('id');

        $parcours = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $parcours_id
        ), ARRAY_A);

        if (!$parcours || $parcours['statut'] !== 'draft') {
            return new WP_Error('invalid_status', 'Le parcours doit être en statut draft', ['status' => 400]);
        }

        // Vérifier que toutes les étapes ont au moins une activité ou un hébergement
        $etapes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d ORDER BY numero_ordre ASC",
            $parcours_id
        ), ARRAY_A);

        $incomplete = [];
        foreach ($etapes as $e) {
            if (empty($e['activity_id']) && empty($e['hebergement_id'])) {
                $incomplete[] = $e['numero_ordre'];
            }
        }

        if (!empty($incomplete)) {
            return new WP_Error(
                'incomplete_steps',
                'Certaines étapes n\'ont pas d\'activité ou d\'hébergement : étapes ' . implode(', ', $incomplete),
                ['status' => 400]
            );
        }

        // Créer les réservations pour chaque étape
        foreach ($etapes as $etape) {
            $this->create_reservations_for_etape($parcours, $etape);
        }

        // Mettre à jour le statut
        $wpdb->update(
            WPSD_DB::table_parcours(),
            ['statut' => 'pending_acceptance'],
            ['id' => $parcours_id],
            ['%s'],
            ['%d']
        );

        // Envoyer les emails aux prestataires
        $this->notify_providers($parcours_id, $parcours['user_id']);

        return rest_ensure_response([
            'message' => 'Demandes envoyées aux prestataires',
            'statut' => 'pending_acceptance',
        ]);
    }

    /**
     * Crée les réservations pour une étape
     */
    private function create_reservations_for_etape($parcours, $etape) {
        global $wpdb;
        $user_id = $parcours['user_id'];

        // Activité
        if ($etape['activity_id']) {
            $owner_id = (int) get_post_meta($etape['activity_id'], 'owner_user_id', true);
            if ($owner_id) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM " . WPSD_DB::table_reservations() . "
                     WHERE parcours_id = %d AND etape_id = %d AND activity_id = %d",
                    $parcours['id'], $etape['id'], $etape['activity_id']
                ));
                if (!$existing) {
                    $wpdb->insert(
                        WPSD_DB::table_reservations(),
                        [
                            'parcours_id' => $parcours['id'],
                            'etape_id' => $etape['id'],
                            'itinerant_user_id' => $user_id,
                            'provider_user_id' => $owner_id,
                            'activity_id' => $etape['activity_id'],
                            'date_start' => $etape['date_debut'],
                            'date_end' => $etape['date_fin'],
                            'kind' => 'activity',
                            'object_id' => $etape['activity_id'],
                            'slot_id' => 0,
                            'quantity' => 1,
                            'status' => 'pending',
                        ],
                        ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
                    );
                }
            }
        }

        // Hébergement
        if ($etape['hebergement_id']) {
            $owner_id = (int) get_post_meta($etape['hebergement_id'], 'owner_user_id', true);
            if ($owner_id) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM " . WPSD_DB::table_reservations() . "
                     WHERE parcours_id = %d AND etape_id = %d AND hebergement_id = %d",
                    $parcours['id'], $etape['id'], $etape['hebergement_id']
                ));
                if (!$existing) {
                    $wpdb->insert(
                        WPSD_DB::table_reservations(),
                        [
                            'parcours_id' => $parcours['id'],
                            'etape_id' => $etape['id'],
                            'itinerant_user_id' => $user_id,
                            'provider_user_id' => $owner_id,
                            'hebergement_id' => $etape['hebergement_id'],
                            'date_start' => $etape['date_debut'],
                            'date_end' => $etape['date_fin'],
                            'kind' => 'accommodation',
                            'object_id' => $etape['hebergement_id'],
                            'slot_id' => 0,
                            'quantity' => 1,
                            'status' => 'pending',
                        ],
                        ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
                    );
                }
            }
        }
    }

    /**
     * Notifie les prestataires par email
     */
    private function notify_providers($parcours_id, $itinerant_id) {
        global $wpdb;
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT provider_user_id FROM " . WPSD_DB::table_reservations() . " WHERE parcours_id = %d",
            $parcours_id
        ), ARRAY_A);

        $itinerant = get_userdata($itinerant_id);
        if (!$itinerant) return;

        $parcours_url = home_url('/mon-compte/');

        foreach ($reservations as $r) {
            $provider = get_userdata($r['provider_user_id']);
            if (!$provider) continue;

            $subject = 'Nouvelle demande de réservation';
            $body = sprintf(
                '<p>Bonjour %s,</p><p>%s vous a envoyé une demande de réservation dans le cadre de son parcours.</p><p><a href="%s">Voir les demandes</a></p>',
                esc_html($provider->display_name),
                esc_html($itinerant->display_name),
                esc_url($parcours_url)
            );

            wp_mail($provider->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        }
    }

    // ==================== ÉTAPES ====================

    /**
     * POST /parcours/{id}/etapes
     * Ajoute une étape à un parcours
     */
    public function create_etape($request) {
        global $wpdb;
        $parcours_id = (int) $request->get_param('id');

        $parcours = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $parcours_id
        ), ARRAY_A);

        if (!$parcours || $parcours['statut'] !== 'draft') {
            return new WP_Error('invalid_status', 'Le parcours doit être en statut draft', ['status' => 400]);
        }

        // Récupérer la dernière étape pour calculer la nouvelle date
        $last_etape = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d ORDER BY numero_ordre DESC LIMIT 1",
            $parcours_id
        ), ARRAY_A);

        $new_order = $last_etape ? ($last_etape['numero_ordre'] + 1) : 1;
        $new_date_debut = $last_etape ? date('Y-m-d', strtotime($last_etape['date_fin'] . ' +1 day')) : $parcours['date_debut_globale'];
        $new_date_fin = date('Y-m-d', strtotime($new_date_debut . ' +1 day'));

        $wpdb->insert(
            WPSD_DB::table_etapes(),
            [
                'parcours_id' => $parcours_id,
                'numero_ordre' => $new_order,
                'duree' => 1,
                'date_debut' => $new_date_debut,
                'date_fin' => $new_date_fin,
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );

        $etape_id = $wpdb->insert_id;

        return rest_ensure_response([
            'id' => $etape_id,
            'parcours_id' => $parcours_id,
            'numero_ordre' => $new_order,
            'date_debut' => $new_date_debut,
            'date_fin' => $new_date_fin,
            'message' => 'Étape ajoutée',
        ]);
    }

    /**
     * PUT /etapes/{id}
     * Modifie une étape (durée, trajet, activité, hébergement)
     * Recalcule les dates des étapes suivantes
     */
    public function update_etape($request) {
        global $wpdb;
        $etape_id = (int) $request->get_param('id');

        $etape = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE id = %d",
            $etape_id
        ), ARRAY_A);

        if (!$etape) {
            return new WP_Error('not_found', 'Étape introuvable', ['status' => 404]);
        }

        $parcours = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $etape['parcours_id']
        ), ARRAY_A);

        if (!$parcours || $parcours['statut'] !== 'draft') {
            return new WP_Error('invalid_status', 'Le parcours n\'est pas modifiable', ['status' => 400]);
        }

        $data = [];
        $formats = [];

        // Durée
        $duree = $request->get_param('duree');
        if ($duree !== null) {
            $duree = (int) $duree;
            if ($duree < 1) $duree = 1;
            $data['duree'] = $duree;
            $formats[] = '%d';

            // Recalcul date_fin
            $data['date_fin'] = date('Y-m-d', strtotime($etape['date_debut'] . ' +' . $duree . ' days'));
            $formats[] = '%s';
        }

        // Temps de trajet
        $travel_mode = $request->get_param('travel_mode');
        if ($travel_mode !== null) {
            $data['travel_mode'] = sanitize_text_field($travel_mode);
            $formats[] = '%s';
        }
        $travel_days = $request->get_param('travel_days');
        if ($travel_days !== null) {
            $data['travel_days'] = (int) $travel_days;
            $formats[] = '%d';
        }
        $travel_hours = $request->get_param('travel_hours');
        if ($travel_hours !== null) {
            $data['travel_hours'] = (int) $travel_hours;
            $formats[] = '%d';
        }

        // Activité/hébergement
        $activity_id = $request->get_param('activity_id');
        if ($activity_id !== null) {
            $data['activity_id'] = $activity_id ? (int) $activity_id : null;
            $formats[] = '%d';
        }
        $hebergement_id = $request->get_param('hebergement_id');
        if ($hebergement_id !== null) {
            $data['hebergement_id'] = $hebergement_id ? (int) $hebergement_id : null;
            $formats[] = '%d';
        }

        if (!empty($data)) {
            $wpdb->update(
                WPSD_DB::table_etapes(),
                $data,
                ['id' => $etape_id],
                $formats,
                ['%d']
            );

            // Recalculer les dates des étapes suivantes
            if (isset($data['duree']) || isset($data['travel_days']) || isset($data['travel_hours'])) {
                $this->recalculate_following_dates($etape['parcours_id'], $etape['numero_ordre']);
            }
        }

        // Retourner l'étape mise à jour
        $updated = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE id = %d",
            $etape_id
        ), ARRAY_A);

        return rest_ensure_response($updated);
    }

    /**
     * DELETE /etapes/{id}
     * Supprime une étape et décale les suivantes
     */
    public function delete_etape($request) {
        global $wpdb;
        $etape_id = (int) $request->get_param('id');

        $etape = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE id = %d",
            $etape_id
        ), ARRAY_A);

        if (!$etape) {
            return new WP_Error('not_found', 'Étape introuvable', ['status' => 404]);
        }

        $parcours = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $etape['parcours_id']
        ), ARRAY_A);

        if (!$parcours || $parcours['statut'] !== 'draft') {
            return new WP_Error('invalid_status', 'Le parcours n\'est pas modifiable', ['status' => 400]);
        }

        // Supprimer les réservations liées
        $wpdb->delete(
            WPSD_DB::table_reservations(),
            ['etape_id' => $etape_id],
            ['%d']
        );

        // Supprimer l'étape
        $wpdb->delete(
            WPSD_DB::table_etapes(),
            ['id' => $etape_id],
            ['%d']
        );

        // Renuméroter les étapes restantes
        $remaining = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d ORDER BY numero_ordre ASC",
            $etape['parcours_id']
        ), ARRAY_A);

        $order = 1;
        foreach ($remaining as $r) {
            $wpdb->update(
                WPSD_DB::table_etapes(),
                ['numero_ordre' => $order],
                ['id' => $r['id']],
                ['%d'],
                ['%d']
            );
            $order++;
        }

        // Recalculer les dates
        $this->recalculate_all_dates($etape['parcours_id']);

        return rest_ensure_response([
            'message' => 'Étape supprimée',
        ]);
    }

    /**
     * Recalcule les dates des étapes à partir d'un numéro d'ordre donné
     */
    private function recalculate_following_dates($parcours_id, $from_order) {
        global $wpdb;

        $etapes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d ORDER BY numero_ordre ASC",
            $parcours_id
        ), ARRAY_A);

        $current_date = null;
        foreach ($etapes as $etape) {
            if ($etape['numero_ordre'] < $from_order) {
                // Garder la date existante comme référence
                $current_date = $etape['date_fin'];
                continue;
            }

            if ($etape['numero_ordre'] == $from_order) {
                // Cette étape a déjà été mise à jour, on prend sa date_fin
                $current_date = $etape['date_fin'];
                continue;
            }

            // Calculer le décalage pour les étapes suivantes
            if ($current_date) {
                $travel_offset = $etape['travel_days'] + ($etape['travel_hours'] > 0 ? 1 : 0);
                $new_date_debut = date('Y-m-d', strtotime($current_date . ' +' . $travel_offset . ' days'));
                $new_date_fin = date('Y-m-d', strtotime($new_date_debut . ' +' . $etape['duree'] . ' days'));

                $wpdb->update(
                    WPSD_DB::table_etapes(),
                    [
                        'date_debut' => $new_date_debut,
                        'date_fin' => $new_date_fin,
                    ],
                    ['id' => $etape['id']],
                    ['%s', '%s'],
                    ['%d']
                );

                $current_date = $new_date_fin;
            }
        }
    }

    /**
     * Recalcule toutes les dates d'un parcours depuis le début
     */
    private function recalculate_all_dates($parcours_id) {
        global $wpdb;

        $parcours = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $parcours_id
        ), ARRAY_A);

        if (!$parcours) return;

        $etapes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d ORDER BY numero_ordre ASC",
            $parcours_id
        ), ARRAY_A);

        $current_date = $parcours['date_debut_globale'];

        foreach ($etapes as $etape) {
            $date_debut = $current_date;
            $date_fin = date('Y-m-d', strtotime($date_debut . ' +' . $etape['duree'] . ' days'));

            $wpdb->update(
                WPSD_DB::table_etapes(),
                [
                    'date_debut' => $date_debut,
                    'date_fin' => $date_fin,
                ],
                ['id' => $etape['id']],
                ['%s', '%s'],
                ['%d']
            );

            $current_date = $date_fin;
        }
    }
}
