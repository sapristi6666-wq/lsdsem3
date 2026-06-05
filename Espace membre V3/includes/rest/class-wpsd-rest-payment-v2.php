<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_PaymentV2 {
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('wpsd/v2', '/payment/create-payment-intent', [
            'methods' => 'POST', 'callback' => [$this, 'create_payment_intent'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v2', '/payment/confirm-parcours', [
            'methods' => 'POST', 'callback' => [$this, 'confirm_parcours_paid'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v2', '/payment/refund-parcours', [
            'methods' => 'POST', 'callback' => [$this, 'refund_parcours'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function create_payment_intent($req) {
        $parcours_id = (int) $req->get_param('parcours_id');
        if (!$parcours_id) return new WP_Error('missing', 'parcours_id requis', ['status' => 400]);
        global $wpdb;
        $parcours = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d AND user_id = %d", $parcours_id, get_current_user_id()), ARRAY_A);
        if (!$parcours) return new WP_Error('not_found', 'Parcours introuvable', ['status' => 404]);
        if ($parcours['statut'] !== 'accepted') return new WP_Error('bad_status', 'Le parcours doit être accepté', ['status' => 400]);

        // Calculer le montant : nb_jours * prix
        $etapes = $wpdb->get_results($wpdb->prepare("SELECT SUM(duree) as total FROM " . WPSD_DB::table_etapes() . " WHERE parcours_id = %d", $parcours_id), ARRAY_A);
        $nb_jours = max(1, (int) ($etapes[0]['total'] ?? 1));

        $settings = get_option('wpsd_settings', []);
        $prix_jour = (float) ($settings['prix_jour_itinérant'] ?? 5);
        $montant_centimes = (int) round($nb_jours * $prix_jour * 100);
        if ($montant_centimes < 50) $montant_centimes = 50; // minimum 0.50€

        // Créer ou récupérer customer
        $user_id = get_current_user_id();
        $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
        if (!$customer_id) {
            $user = wp_get_current_user();
            $customer = $this->stripe->post('/customers', ['email' => $user->user_email, 'metadata' => ['user_id' => $user_id]]);
            if (is_wp_error($customer)) return $customer;
            $customer_id = $customer['id'];
            update_user_meta($user_id, 'stripe_customer_id', $customer_id);
        }

        $intent = $this->stripe->post('/payment_intents', [
            'amount' => $montant_centimes,
            'currency' => 'eur',
            'customer' => $customer_id,
            'metadata' => ['parcours_id' => $parcours_id, 'user_id' => $user_id, 'nb_jours' => $nb_jours],
            'automatic_payment_methods' => ['enabled' => true],
        ]);
        if (is_wp_error($intent)) return $intent;

        return ['client_secret' => $intent['client_secret'], 'amount' => $montant_centimes, 'nb_jours' => $nb_jours];
    }

    public function confirm_parcours_paid($req) {
        $parcours_id = (int) $req->get_param('parcours_id');
        $payment_intent_id = sanitize_text_field($req->get_param('payment_intent_id'));
        if (!$parcours_id || !$payment_intent_id) return new WP_Error('missing', 'Paramètres manquants', ['status' => 400]);

        global $wpdb;
        $wpdb->update(
            WPSD_DB::table_parcours(),
            ['statut' => 'paid', 'payment_intent_id' => $payment_intent_id],
            ['id' => $parcours_id, 'user_id' => get_current_user_id()],
            ['%s', '%s'], ['%d', '%d']
        );

        // Marquer les réservations du parcours comme awaiting_confirmation
        $wpdb->query($wpdb->prepare(
            "UPDATE " . WPSD_DB::table_reservations() . " SET status = 'awaiting_confirmation' WHERE parcours_id = %d AND status = 'accepted'",
            $parcours_id
        ));

        return ['message' => 'Paiement confirmé, réservations en attente de confirmation'];
    }

    public function refund_parcours($req) {
        $parcours_id = (int) $req->get_param('parcours_id');
        global $wpdb;
        $parcours = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . WPSD_DB::table_parcours() . " WHERE id = %d", $parcours_id), ARRAY_A);
        if (!$parcours) return new WP_Error('not_found', 'Parcours introuvable', ['status' => 404]);
        if (!in_array($parcours['statut'], ['paid', 'completed'])) return new WP_Error('bad_status', 'Pas de paiement à rembourser', ['status' => 400]);

        $results = [];
        if ($parcours['payment_intent_id']) {
            $refund = $this->stripe->post('/refunds', ['payment_intent' => $parcours['payment_intent_id']]);
            if (is_wp_error($refund)) return $refund;
            $results['refund'] = $refund['id'];
        }

        $wpdb->update(WPSD_DB::table_parcours(), ['statut' => 'cancelled'], ['id' => $parcours_id], ['%s'], ['%d']);
        $wpdb->query($wpdb->prepare("UPDATE " . WPSD_DB::table_reservations() . " SET status = 'rejected_after_paid' WHERE parcours_id = %d", $parcours_id));

        return ['message' => 'Parcours annulé et remboursé', 'results' => $results];
    }
}
