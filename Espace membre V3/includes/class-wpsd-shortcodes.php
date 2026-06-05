<?php
if (!defined('ABSPATH')) exit;

require_once WPSD_PATH . 'includes/shortcodes/class-wpsd-shortcode-register.php';
require_once WPSD_PATH . 'includes/shortcodes/class-wpsd-shortcode-login.php';
require_once WPSD_PATH . 'includes/shortcodes/class-wpsd-shortcode-dashboard.php';
require_once WPSD_PATH . 'includes/shortcodes/class-wpsd-shortcode-contact.php';
require_once WPSD_PATH . 'includes/shortcodes/class-wpsd-shortcode-newsletter.php';
require_once WPSD_PATH . 'includes/shortcodes/class-wpsd-shortcode-payment-validation.php';

class WPSD_Shortcodes {
    private $stripe;
    private $register;
    private $login;
    private $dashboard;
    private $contact;
    private $newsletter;
    private $payment_validation;

    public function __construct($stripe) {
        $this->stripe = $stripe;
        $this->register = new WPSD_Shortcode_Register($stripe);
        $this->login = new WPSD_Shortcode_Login();
        $this->dashboard = new WPSD_Shortcode_Dashboard($stripe);
        $this->contact = new WPSD_Shortcode_Contact();
        $this->newsletter = new WPSD_Shortcode_Newsletter();
        $this->payment_validation = new WPSD_Shortcode_PaymentValidation();

        add_shortcode('wpsd_register', [$this, 'register_form']);
        add_shortcode('wpsd_login', [$this, 'login_form']);
        add_shortcode('wpsd_dashboard', [$this, 'dashboard']);
        add_shortcode('wpsd_contact', [$this, 'contact_form']);
        add_shortcode('wpsd_newsletter', [$this, 'newsletter_form']);
        add_shortcode('wpsd_payment_validation', [$this, 'payment_validation_page']);

        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_login_failed', [$this, 'custom_login_failed']);
        add_filter('authenticate', [$this, 'custom_authenticate'], 30, 3);
    }

    public function register_form() { return $this->register->render(); }
    public function login_form() { return $this->login->render(); }
    public function dashboard() { return $this->dashboard->render(); }
    public function contact_form() { return $this->contact->render(); }
    public function newsletter_form($atts = []) { return $this->newsletter->render($atts); }
    public function payment_validation_page() { return $this->payment_validation->render(); }

    public function custom_authenticate($user, $username, $password) {
        if (is_wp_error($user)) { $ref = wp_get_referer(); if ($ref && !strstr($ref,'wp-login') && !strstr($ref,'wp-admin')) { wp_redirect(add_query_arg('login','failed',$ref)); exit; } }
        return $user;
    }

    public function custom_login_failed($username) {
        $ref = wp_get_referer();
        if ($ref && !strstr($ref,'wp-login') && !strstr($ref,'wp-admin')) { wp_redirect(add_query_arg('login','failed',$ref)); exit; }
    }

    public function register_assets() {
        // FullCalendar
        wp_register_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css', [], null);
        wp_register_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', [], null, true);

                // CSS
        wp_register_style('wpsd-dashboard', WPSD_URL . 'assets/dashboard.css', [], time());
        wp_register_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], null);
        wp_register_style('wpsd-frontend-forms', WPSD_URL . 'assets/frontend-forms.css', [], time());
        wp_register_style('wpsd-mon-parcours', WPSD_URL . 'assets/css/mon-parcours.css', [], time());

        // Leaflet
        wp_register_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);

        // Modules JS
        wp_register_script('wpsd-api', WPSD_URL . 'js/modules/wpsd-api.js', [], time(), true);
        wp_register_script('wpsd-utils', WPSD_URL . 'js/modules/wpsd-utils.js', [], time(), true);
        wp_register_script('wpsd-state', WPSD_URL . 'js/modules/wpsd-state.js', [], time(), true);
        wp_register_script('wpsd-toast', WPSD_URL . 'js/modules/wpsd-toast.js', ['wpsd-utils'], time(), true);
        wp_register_script('wpsd-tabs', WPSD_URL . 'js/modules/wpsd-tabs.js', ['wpsd-state'], time(), true);
        wp_register_script('wpsd-modals', WPSD_URL . 'js/modules/wpsd-modals.js', ['wpsd-utils'], time(), true);
        wp_register_script('wpsd-carte', WPSD_URL . 'js/modules/wpsd-carte.js', ['leaflet','wpsd-api','wpsd-utils','wpsd-state'], time(), true);
        wp_register_script('wpsd-parcours', WPSD_URL . 'js/modules/wpsd-parcours.js', ['leaflet','wpsd-api','wpsd-utils','wpsd-state','wpsd-demandes'], time(), true);
        wp_register_script('wpsd-savoirs', WPSD_URL . 'js/modules/wpsd-savoirs.js', ['fullcalendar','wpsd-api','wpsd-utils','wpsd-state','wpsd-toast','wpsd-modals'], time(), true);
        wp_register_script('wpsd-hebergements', WPSD_URL . 'js/modules/wpsd-hebergements.js', ['fullcalendar','wpsd-api','wpsd-utils','wpsd-state','wpsd-toast','wpsd-modals','wpsd-savoirs'], time(), true);
        wp_register_script('wpsd-demandes', WPSD_URL . 'js/modules/wpsd-demandes.js', ['wpsd-api','wpsd-utils','wpsd-state','wpsd-toast'], time(), true);
        wp_register_script('wpsd-articles', WPSD_URL . 'js/modules/wpsd-articles.js', ['wpsd-api','wpsd-utils','wpsd-state','wpsd-toast','wpsd-modals'], time(), true);
        wp_register_script('wpsd-famille', WPSD_URL . 'js/modules/wpsd-famille.js', ['wpsd-api','wpsd-utils','wpsd-state','wpsd-toast','wpsd-modals'], time(), true);
        wp_register_script('wpsd-adhesion', WPSD_URL . 'js/modules/wpsd-adhesion.js', ['wpsd-api','wpsd-utils','wpsd-state','wpsd-toast'], time(), true);
        wp_register_script('wpsd-moderation', WPSD_URL . 'js/modules/wpsd-moderation.js', ['wpsd-api','wpsd-utils','wpsd-state','wpsd-toast'], time(), true);
        wp_register_script('wpsd-compte', WPSD_URL . 'js/modules/wpsd-compte.js', ['wpsd-api','wpsd-utils','wpsd-state','wpsd-toast','wpsd-modals'], time(), true);

        // Point d'entrée
        wp_register_script('wpsd-dashboard', WPSD_URL . 'js/dashboard.js', [
            'jquery', 'wp-util', 'fullcalendar', 'leaflet',
            'wpsd-api', 'wpsd-utils', 'wpsd-state', 'wpsd-toast', 'wpsd-tabs', 'wpsd-modals',
            'wpsd-carte', 'wpsd-parcours', 'wpsd-savoirs', 'wpsd-hebergements',
            'wpsd-demandes', 'wpsd-articles', 'wpsd-famille', 'wpsd-adhesion', 'wpsd-moderation', 'wpsd-compte'
        ], time(), true);
    }
}