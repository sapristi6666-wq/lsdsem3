<?php
if (!defined('ABSPATH')) exit;

require_once WPSD_PATH . 'includes/rest/trait-wpsd-rest-helpers.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-activities.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-accommodations.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-slots.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-reservations.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-family.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-articles.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-profile.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-admin.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-stripe.php';
require_once WPSD_PATH . 'includes/rest/class-wpsd-rest-map.php';

class WPSD_REST {
  private $stripe;
  private $webhook;

  public function __construct($stripe, $webhook) {
    $this->stripe = $stripe;
    $this->webhook = $webhook;
    add_action('rest_api_init', [$this, 'routes']);
  }

  public function routes() {
    (new WPSD_REST_Activities($this->stripe))->register_routes();
    (new WPSD_REST_Accommodations($this->stripe))->register_routes();
    (new WPSD_REST_Slots($this->stripe))->register_routes();
    (new WPSD_REST_Reservations($this->stripe))->register_routes();
    (new WPSD_REST_Family($this->stripe))->register_routes();
    (new WPSD_REST_Articles($this->stripe))->register_routes();
    (new WPSD_REST_Profile($this->stripe))->register_routes();
    (new WPSD_REST_Admin($this->stripe))->register_routes();
    (new WPSD_REST_Stripe($this->stripe))->register_routes();
    (new WPSD_REST_Map($this->stripe))->register_routes();

    register_rest_route('wpsd/v1', '/stripe-webhook', [
      'methods' => 'POST', 'callback' => [$this->webhook, 'handle'], 'permission_callback' => '__return_true',
    ]);
  }
}