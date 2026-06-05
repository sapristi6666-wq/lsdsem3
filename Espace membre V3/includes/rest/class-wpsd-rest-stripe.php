<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Stripe {
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/create-checkout-session', [
            'methods' => 'POST', 'callback' => [$this, 'create_checkout'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/create-portal-session', [
            'methods' => 'POST', 'callback' => [$this, 'create_portal'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/stripe/payment-history', [
            'methods' => 'GET', 'callback' => [$this, 'payment_history'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
        register_rest_route('wpsd/v1', '/create-checkout-from-register', [
            'methods' => 'POST', 'callback' => [$this, 'checkout_from_register'], 'permission_callback' => '__return_true',
        ]);
    }

    public function create_checkout($req) { return $this->stripe->create_checkout_session($req); }
    public function create_portal($req) { return $this->stripe->create_portal_session($req); }

    public function payment_history() {
        $uid = get_current_user_id();
        $customer_id = get_user_meta($uid, 'stripe_customer_id', true);
        if (!$customer_id) return new WP_REST_Response(['ok' => true, 'items' => []], 200);
        $invoices = $this->stripe->get('/invoices', ['customer' => $customer_id, 'status' => 'paid', 'limit' => 20]);
        if (is_wp_error($invoices)) return new WP_REST_Response(['ok' => false, 'error' => $invoices->get_error_message()], 500);
        $items = [];
        foreach ($invoices['data'] ?? [] as $inv) $items[] = ['date' => date_i18n('d/m/Y', $inv['created'] ?? time()), 'amount' => number_format(($inv['amount_paid'] ?? 0) / 100, 2, ',', ' ') . ' €', 'status' => $inv['status'] ?? '', 'url' => $inv['hosted_invoice_url'] ?? ''];
        return new WP_REST_Response(['ok' => true, 'items' => $items], 200);
    }

    public function checkout_from_register(WP_REST_Request $req) {
        $email = sanitize_email($req->get_param('email') ?? ''); $plan = sanitize_text_field($req->get_param('plan') ?? 'member');
        $role = sanitize_text_field($req->get_param('role') ?? 'itinerant'); $nom = sanitize_text_field($req->get_param('nom') ?? '');
        $prenom = sanitize_text_field($req->get_param('prenom') ?? ''); $phone = sanitize_text_field($req->get_param('phone') ?? '');
        if (!is_email($email)) return new WP_REST_Response(['error' => 'Email invalide'], 400);
        if (email_exists($email)) return new WP_REST_Response(['error' => 'Un compte existe déjà avec cet email.'], 409);
        global $wpdb;
        $pending_table = WPSD_DB::table_pending_registrations();
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $pending_table WHERE email = %s AND status = 'pending'", $email))) return new WP_REST_Response(['error' => 'Une inscription est déjà en attente avec cet email.'], 409);
        if (!in_array($plan, ['member','family'])) $plan = 'member';
        if (!in_array($role, ['itinerant','passeur','sympathisant'])) $role = 'itinerant';
        $price_map = ['member' => WPSD_Data::get_cached_option('price_member'), 'family' => WPSD_Data::get_cached_option('price_family')];
        if (empty($price_map[$plan])) return new WP_REST_Response(['error' => 'Plan invalide'], 400);
        $session = $this->stripe->create_raw_session(['mode' => 'subscription', 'line_items' => [['price' => $price_map[$plan], 'quantity' => 1]], 'success_url' => add_query_arg(['session_id' => '{CHECKOUT_SESSION_ID}', 'register_role' => $role], home_url('/validation-paiement/')), 'cancel_url' => home_url('/inscription/?canceled=1'), 'metadata' => ['register_email' => $email, 'register_role' => $role, 'register_plan' => $plan, 'register_nom' => $nom, 'register_prenom' => $prenom, 'register_phone' => $phone]]);
        if (is_wp_error($session)) return new WP_REST_Response(['error' => $session->get_error_message()], 400);
        return new WP_REST_Response(['url' => $session['url']], 200);
    }
}