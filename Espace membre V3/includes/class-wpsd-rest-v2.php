<?php
if (!defined('ABSPATH')) exit;

/**
 * Point d'entrée pour les routes API REST v2 (parcours, étapes, réservations, etc.)
 * Préfixe : wpsd/v2
 */
class WPSD_REST_V2 {

    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Parcours
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-parcours.php';
        new WPSD_REST_Parcours();

        // Réservations v2
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-reservations-v2.php';
        new WPSD_REST_ReservationsV2();

        // Disponibilités pour la carte
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-disponibilites.php';
        new WPSD_REST_Disponibilites();

        // Fiche membre
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-member-card.php';
        new WPSD_REST_MemberCard();

        // Admin v2 (facturation, etc.)
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-admin-v2.php';
        new WPSD_REST_AdminV2();

        // Paiement v2 (Stripe PaymentIntents)
        require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-payment-v2.php';
        new WPSD_REST_PaymentV2($this->stripe);
    }
}
