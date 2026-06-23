<?php
if (!defined('ABSPATH')) exit;

require_once WPSD_PATH . 'includes/class-wpsd-stripe-api.php';

class WPSD_Stripe {

  private $api;

  public function __construct() {
    $this->api = new WPSD_Stripe_API();
  }

  public function request($method, $path, $body = []) {
    return $this->api->request($method, $path, $body);
  }

  public function get($path, $params = []) {
    return $this->api->get($path, $params);
  }

  public function post($path, $body = []) {
    return $this->api->post($path, $body);
  }

  public function delete($path, $body = []) {
    return $this->api->delete($path, $body);
  }

  public function create_checkout_session(WP_REST_Request $req) {
    $plan = sanitize_text_field($req->get_param('plan'));

    $price_map = [
      'member'      => WPSD_Data::get_cached_option('price_member'),
      'family'      => WPSD_Data::get_cached_option('price_family'),
      'institution' => WPSD_Data::get_cached_option('price_institution'),
    ];

    if (!isset($price_map[$plan]) || !$price_map[$plan]) {
      return new WP_REST_Response(['error' => 'Plan invalide ou Price ID manquant'], 400);
    }

    $user = wp_get_current_user();
    $user_id = $user->ID;

    $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
    if (!$customer_id) {
      $cust = $this->api->post('/customers', [
        'email' => $user->user_email,
        'name'  => $user->display_name ?: $user->user_login,
        'metadata[user_id]' => (string)$user_id,
      ]);
      if (is_wp_error($cust)) return new WP_REST_Response(['error' => $cust->get_error_message()], 400);
      $customer_id = $cust['id'];
      update_user_meta($user_id, 'stripe_customer_id', $customer_id);
    }

    $success = WPSD_Data::get_cached_option('success_url', home_url('/mon-compte/?success=1'));
    $cancel  = WPSD_Data::get_cached_option('cancel_url', home_url('/mon-compte/?canceled=1'));

    $session = $this->api->post('/checkout/sessions', [
      'mode'                => 'subscription',
      'customer'            => $customer_id,
      'line_items[0][price]'    => $price_map[$plan],
      'line_items[0][quantity]' => 1,
      'success_url'         => $success,
      'cancel_url'          => $cancel,
      'client_reference_id' => (string)$user_id,
      'metadata[user_id]'   => (string)$user_id,
      'metadata[plan]'      => $plan,
      'allow_promotion_codes' => 'true',
    ]);

    if (is_wp_error($session)) return new WP_REST_Response(['error' => $session->get_error_message()], 400);
    return new WP_REST_Response(['url' => $session['url']], 200);
  }

  public function create_portal_session(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
    if (!$customer_id) return new WP_REST_Response(['error' => 'Aucun customer Stripe lié à ce compte'], 400);

    $portal = $this->api->post('/billing_portal/sessions', [
      'customer'   => $customer_id,
      'return_url' => home_url('/mon-compte/'),
    ]);
    if (is_wp_error($portal)) return new WP_REST_Response(['error' => $portal->get_error_message()], 400);
    return new WP_REST_Response(['url' => $portal['url']], 200);
  }

  public function create_raw_session($data) {
    return $this->api->post('/checkout/sessions', $data);
  }

  public function get_plan_key_for_user($user_id) {
    $price_id = get_user_meta($user_id, 'price_id', true);
    $label    = get_user_meta($user_id, 'plan_label', true);

    $pm = WPSD_Data::get_cached_option('price_member');
    $pf = WPSD_Data::get_cached_option('price_family');
    $pi = WPSD_Data::get_cached_option('price_institution');

    if ($price_id && $pm && $price_id === $pm) return 'member';
    if ($price_id && $pf && $price_id === $pf) return 'family';
    if ($price_id && $pi && $price_id === $pi) return 'institution';
    if (in_array($label, ['member','family','institution'], true)) return $label;
    return 'none';
  }

  public function human_plan($user_id) {
    return WPSD_Data::plan_label($this->get_plan_key_for_user($user_id), '—');
  }

  public function is_active($user_id) {
    $status = get_user_meta($user_id, 'subscription_status', true);
    return in_array($status, ['active','trialing'], true);
  }
}