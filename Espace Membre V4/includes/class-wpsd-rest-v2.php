<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_V2 {

    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Parcours
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-parcours.php';
        $parcours = new WPSD_REST_Parcours();
        $parcours->register_routes();

        // Réservations v2
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-reservations-v2.php';
        $reservations = new WPSD_REST_ReservationsV2();
        $reservations->register_routes();

        // Disponibilités pour la carte
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-disponibilites.php';
        $disponibilites = new WPSD_REST_Disponibilites();
        $disponibilites->register_routes();

        // Fiche membre
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-member-card.php';
        $memberCard = new WPSD_REST_MemberCard();
        $memberCard->register_routes();

        // Admin v2 (facturation, etc.)
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-admin-v2.php';
        $adminV2 = new WPSD_REST_AdminV2();
        $adminV2->register_routes();

        // Paiement v2 (Stripe PaymentIntents)
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-payment-v2.php';
        $paymentV2 = new WPSD_REST_PaymentV2($this->stripe);
        $paymentV2->register_routes();
    }
}