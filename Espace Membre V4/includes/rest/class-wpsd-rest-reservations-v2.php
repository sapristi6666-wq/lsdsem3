<?php
if (!defined('ABSPATH')) exit;

/**
 * Routes REST pour les réservations v2
 * 
 * POST /wpsd/v2/reservations/{id}/accept
 * POST /wpsd/v2/reservations/{id}/reject
 * POST /wpsd/v2/reservations/{id}/confirm-itinerant
 * POST /wpsd/v2/reservations/{id}/confirm-prestataire
 */
class WPSD_REST_ReservationsV2 {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('wpsd/v2', '/reservations/(?P<id>\d+)/accept', [
            'methods' => 'POST',
            'callback' => [$this, 'accept_reservation'],
            'permission_callback' => [$this, 'check_provider'],
        ]);
        register_rest_route('wpsd/v2', '/reservations/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_reservation'],
            'permission_callback' => [$this, 'check_provider'],
        ]);
        register_rest_route('wpsd/v2', '/reservations/(?P<id>\d+)/confirm-itinerant', [
            'methods' => 'POST',
            'callback' => [$this, 'confirm_itinerant'],
            'permission_callback' => [$this, 'check_itinerant'],
        ]);
        register_rest_route('wpsd/v2', '/reservations/(?P<id>\d+)/confirm-prestataire', [
            'methods' => 'POST',
            'callback' => [$this, 'confirm_prestataire'],
            'permission_callback' => [$this, 'check_provider'],
        ]);
    }

    // ==================== PERMISSIONS ====================

    private function get_reservation($request) {
        global $wpdb;
        $reservation_id = (int) $request->get_param('id');
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE id = %d",
            $reservation_id
        ), ARRAY_A);
        return $reservation;
    }

    public function check_provider($request) {
        if (!is_user_logged_in()) return false;
        $reservation = $this->get_reservation($request);
        if (!$reservation) return false;
        return (int) $reservation['provider_user_id'] === get_current_user_id()
            || current_user_can('manage_options');
    }

    public function check_itinerant($request) {
        if (!is_user_logged_in()) return false;
        $reservation = $this->get_reservation($request);
        if (!$reservation) return false;
        return (int) $reservation['itinerant_user_id'] === get_current_user_id()
            || current_user_can('manage_options');
    }

    // ==================== ACTIONS ====================

    /**
     * POST /reservations/{id}/accept
     * Le prestataire accepte la réservation
     */
    public function accept_reservation($request) {
        return $this->update_status($request, 'accepted', 'Réservation acceptée');
    }

    /**
     * POST /reservations/{id}/reject
     * Le prestataire refuse la réservation
     * Vérifie si le parcours est déjà payé (rejected_after_paid)
     */
    public function reject_reservation($request) {
        $reservation = $this->get_reservation($request);
        if (!$reservation) {
            return new WP_Error('not_found', 'Réservation introuvable', ['status' => 404]);
        }

        // Vérifier le statut du parcours
        if ($reservation['parcours_id']) {
            global $wpdb;
            $parcours = $wpdb->get_row($wpdb->prepare(
                "SELECT statut FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
                $reservation['parcours_id']
            ), ARRAY_A);

            if ($parcours && ($parcours['statut'] === 'paid' || $parcours['statut'] === 'completed')) {
                return $this->update_status($request, 'rejected_after_paid', 'Réservation refusée après paiement (remboursement à effectuer)');
            }
        }

        return $this->update_status($request, 'rejected', 'Réservation refusée');
    }

    /**
     * POST /reservations/{id}/confirm-itinerant
     * L'itinérant confirme sa présence
     */
    public function confirm_itinerant($request) {
        return $this->set_confirmation($request, 'confirmation_itinérant', 'Présence confirmée');
    }

    /**
     * POST /reservations/{id}/confirm-prestataire
     * Le prestataire confirme l'accueil
     */
    public function confirm_prestataire($request) {
        return $this->set_confirmation($request, 'confirmation_prestataire', 'Accueil confirmé');
    }

    // ==================== HELPERS ====================

    /**
     * Met à jour le statut d'une réservation
     */
    private function update_status($request, $new_status, $success_message) {
        global $wpdb;
        $reservation_id = (int) $request->get_param('id');

        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE id = %d",
            $reservation_id
        ), ARRAY_A);

        if (!$reservation) {
            return new WP_Error('not_found', 'Réservation introuvable', ['status' => 404]);
        }

        // Vérifier la transition
        $allowed_from = [
            'accepted' => ['pending'],
            'rejected' => ['pending'],
            'rejected_after_paid' => ['accepted', 'awaiting_confirmation'],
        ];

        $allowed = $allowed_from[$new_status] ?? [];
        if (!empty($allowed) && !in_array($reservation['status'], $allowed, true)) {
            return new WP_Error(
                'invalid_transition',
                sprintf('Impossible de passer de %s à %s', $reservation['status'], $new_status),
                ['status' => 400]
            );
        }

        $wpdb->update(
            WPSD_DB::table_reservations(),
            ['status' => $new_status],
            ['id' => $reservation_id],
            ['%s'],
            ['%d']
        );

        // Si toutes les réservations sont acceptées, mettre à jour le parcours
        if ($new_status === 'accepted' && $reservation['parcours_id']) {
            $this->check_parcours_accepted($reservation['parcours_id']);
        }

        // Envoyer notification
        $this->send_status_notification($reservation, $new_status);

        return rest_ensure_response([
            'message' => $success_message,
            'status' => $new_status,
        ]);
    }

    /**
     * Met à jour une confirmation (itinérant ou prestataire)
     */
    private function set_confirmation($request, $column, $success_message) {
        global $wpdb;
        $reservation_id = (int) $request->get_param('id');

        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE id = %d",
            $reservation_id
        ), ARRAY_A);

        if (!$reservation) {
            return new WP_Error('not_found', 'Réservation introuvable', ['status' => 404]);
        }

        // Vérifier que la réservation est en awaiting_confirmation
        if ($reservation['status'] !== 'awaiting_confirmation' && $reservation['status'] !== 'accepted') {
            return new WP_Error(
                'invalid_status',
                'La réservation doit être en attente de confirmation',
                ['status' => 400]
            );
        }

        // Si le statut est 'accepted', le passer en 'awaiting_confirmation'
        if ($reservation['status'] === 'accepted') {
            $wpdb->update(
                WPSD_DB::table_reservations(),
                ['status' => 'awaiting_confirmation'],
                ['id' => $reservation_id],
                ['%s'],
                ['%d']
            );
        }

        $wpdb->update(
            WPSD_DB::table_reservations(),
            [$column => 1],
            ['id' => $reservation_id],
            ['%d'],
            ['%d']
        );

        // Vérifier si les deux confirmations sont faites
        $updated = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPSD_DB::table_reservations() . " WHERE id = %d",
            $reservation_id
        ), ARRAY_A);

        if ((int) $updated['confirmation_itinérant'] === 1 && (int) $updated['confirmation_prestataire'] === 1) {
            $wpdb->update(
                WPSD_DB::table_reservations(),
                [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                ],
                ['id' => $reservation_id],
                ['%s', '%s'],
                ['%d']
            );

            // Vérifier si le parcours est complété
            if ($reservation['parcours_id']) {
                $this->check_parcours_completed($reservation['parcours_id']);
            }

            return rest_ensure_response([
                'message' => 'Les deux confirmations sont faites, réservation terminée !',
                'status' => 'completed',
            ]);
        }

        return rest_ensure_response([
            'message' => $success_message,
            'status' => 'awaiting_confirmation',
        ]);
    }

    /**
     * Vérifie si toutes les réservations d'un parcours sont acceptées
     * Si oui, passe le parcours en accepted
     */
    private function check_parcours_accepted($parcours_id) {
        global $wpdb;

        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . WPSD_DB::table_reservations() . "
             WHERE parcours_id = %d AND status = 'pending'",
            $parcours_id
        ));

        if ((int) $pending === 0) {
            $wpdb->update(
                WPSD_DB::table_parcours(),
                ['statut' => 'accepted'],
                ['id' => $parcours_id],
                ['%s'],
                ['%d']
            );
        }
    }

    /**
     * Vérifie si toutes les réservations d'un parcours sont complétées
     * Si oui, passe le parcours en completed
     */
    private function check_parcours_completed($parcours_id) {
        global $wpdb;

        $not_completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . WPSD_DB::table_reservations() . "
             WHERE parcours_id = %d AND status != 'completed'",
            $parcours_id
        ));

        if ((int) $not_completed === 0) {
            $wpdb->update(
                WPSD_DB::table_parcours(),
                ['statut' => 'completed'],
                ['id' => $parcours_id],
                ['%s'],
                ['%d']
            );
        }
    }

    /**
     * Envoie une notification email pour le changement de statut
     */
    private function send_status_notification($reservation, $new_status) {
        $itinerant = get_userdata($reservation['itinerant_user_id']);
        $provider = get_userdata($reservation['provider_user_id']);

        if (!$itinerant || !$provider) return;

        // Map v2 statuses to template events
        $template_map = [
            'accepted'            => 'reservation_accepted',
            'rejected'            => 'reservation_rejected',
            'rejected_after_paid' => 'reservation_rejected',
        ];

        $tpl_event = $template_map[$new_status] ?? '';

        // Handle rejected_after_paid — send to admin only
        if ($new_status === 'rejected_after_paid') {
            $subject = WPSD_Data::get_email_template($tpl_event, 'subject');
            $body    = WPSD_Data::get_email_template($tpl_event, 'body');

            $object_title = !empty($reservation['object_id']) ? (get_the_title((int)$reservation['object_id']) ?: '') : '';
            $date_start   = !empty($reservation['date_start']) ? date_i18n('d/m/Y', strtotime($reservation['date_start'])) : '';
            $date_end     = !empty($reservation['date_end'])   ? date_i18n('d/m/Y', strtotime($reservation['date_end']))   : '';
            $days = 0;
            if (!empty($reservation['date_start']) && !empty($reservation['date_end'])) {
                $days = (int) max(1, (strtotime($reservation['date_end']) - strtotime($reservation['date_start'])) / DAY_IN_SECONDS);
            }

            $data = [
                'display_name'  => esc_html($itinerant->display_name ?: $itinerant->user_login),
                'provider_name' => esc_html($provider->display_name ?: $provider->user_login),
                'object_title'  => esc_html($object_title),
                'date_start'    => $date_start,
                'date_end'      => $date_end,
                'days'          => (string) $days,
            ];

            if (empty($subject) || empty($body)) {
                $subject = 'Réservation annulée après paiement';
                $body = sprintf(
                    '<p>Bonjour,</p><p>%s a annulé une réservation déjà payée. L\'administrateur va procéder au remboursement.</p>',
                    esc_html($provider->display_name)
                );
            } else {
                $subject = WPSD_Data::render_email_template($subject, $data);
                $body    = WPSD_Data::render_email_template($body, $data);
            }

            $body .= WPSD_Data::get_email_legal_suffix();
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                wp_mail($admin_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
            }
            return;
        }

        if (!$tpl_event) return;

        $object_title = !empty($reservation['object_id']) ? (get_the_title((int)$reservation['object_id']) ?: '') : '';
        $date_start   = !empty($reservation['date_start']) ? date_i18n('d/m/Y', strtotime($reservation['date_start'])) : '';
        $date_end     = !empty($reservation['date_end'])   ? date_i18n('d/m/Y', strtotime($reservation['date_end']))   : '';
        $days = 0;
        if (!empty($reservation['date_start']) && !empty($reservation['date_end'])) {
            $days = (int) max(1, (strtotime($reservation['date_end']) - strtotime($reservation['date_start'])) / DAY_IN_SECONDS);
        }

        $data = [
            'display_name'  => esc_html($itinerant->display_name ?: $itinerant->user_login),
            'provider_name' => esc_html($provider->display_name ?: $provider->user_login),
            'object_title'  => esc_html($object_title),
            'date_start'    => $date_start,
            'date_end'      => $date_end,
            'days'          => (string) $days,
        ];

        $subject = WPSD_Data::get_email_template($tpl_event, 'subject');
        $body    = WPSD_Data::get_email_template($tpl_event, 'body');

        // Fallback to hard-coded defaults if template is empty
        if (empty($subject) || empty($body)) {
            switch ($new_status) {
                case 'accepted':
                    $subject = 'Votre demande a été acceptée';
                    $body = sprintf(
                        '<p>Bonjour %s,</p><p>%s a accepté votre demande !</p>',
                        esc_html($itinerant->display_name),
                        esc_html($provider->display_name)
                    );
                    break;
                case 'rejected':
                    $subject = 'Votre demande a été refusée';
                    $body = sprintf(
                        '<p>Bonjour %s,</p><p>%s a refusé votre demande.</p>',
                        esc_html($itinerant->display_name),
                        esc_html($provider->display_name)
                    );
                    break;
                default:
                    return;
            }
        } else {
            $subject = WPSD_Data::render_email_template($subject, $data);
            $body    = WPSD_Data::render_email_template($body, $data);
        }

        $body .= WPSD_Data::get_email_legal_suffix();

        if ($subject && $body) {
            wp_mail($itinerant->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        }
    }
}
