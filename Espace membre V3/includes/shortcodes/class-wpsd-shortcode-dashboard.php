<?php
if (!defined('ABSPATH')) exit;

class WPSD_Shortcode_Dashboard {
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function render() {
        if (!is_user_logged_in()) { wp_redirect(home_url('/connexion/')); exit; }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) return '<div class="wpsd-card"><p>Erreur : profil utilisateur introuvable.</p></div>';

        wp_enqueue_style('fullcalendar');
        wp_enqueue_script('fullcalendar');
        wp_enqueue_style('wpsd-dashboard');
        wp_enqueue_script('wpsd-dashboard');
        wp_enqueue_style('leaflet');
        wp_enqueue_script('leaflet');
        wp_enqueue_style('wpsd-mon-parcours');
        wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', [], null, true);

        $stripe_publishable_key = get_option('wpsd_stripe_publishable_key', '');

        $admin_approved = ((int)get_user_meta($user_id, 'wpsd_admin_approved', true) === 1);

        try { $plan_h = esc_html($this->stripe->human_plan($user_id)); $plan_key = esc_html($this->stripe->get_plan_key_for_user($user_id)); $is_active = $this->stripe->is_active($user_id); }
        catch (Exception $e) { $plan_h = '—'; $plan_key = 'none'; $is_active = false; }

        $is_itinerant = (int)get_user_meta($user_id, 'is_itinerant', true) === 1;
        $is_passeur = (int)get_user_meta($user_id, 'is_passeur', true) === 1;
        $is_hebergeur = (int)get_user_meta($user_id, 'is_hebergeur', true) === 1;
        $is_sympathisant = (int)get_user_meta($user_id, 'is_sympathisant', true) === 1;
        $is_moderator = user_can($user_id, 'wpsd_moderate_registrations');
        $is_full_admin = current_user_can('manage_options');

        $show_hebergements = ($is_passeur || $is_hebergeur);
        $show_moderation = ($is_moderator || $is_full_admin);
        $show_famille = ($is_full_admin || $plan_key === 'family');

        if ($is_full_admin) {
            $is_itinerant = true;
            $is_passeur = true;
            $show_hebergements = true;
            $show_famille = true;
        }

        $sub_status = get_user_meta($user_id, 'subscription_status', true);
        $sub_id = get_user_meta($user_id, 'stripe_subscription_id', true);
        $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
        $period_end_ts = wpsd_get_user_period_end_ts($user_id);
        $period_end_date = $period_end_ts ? date_i18n('d/m/Y', $period_end_ts) : '—';
        $total_reservations = $this->get_user_reservation_count($user_id);

        $wp_localize_script_args = [
            'restBase' => esc_url_raw(rest_url('wpsd/v1')), 'nonce' => wp_create_nonce('wp_rest'),
            'userEmail' => $user->user_email, 'planKey' => $plan_key, 'isActive' => $is_active, 'userId' => $user_id,
            'roles' => compact('is_itinerant','is_passeur','is_hebergeur','is_sympathisant','is_full_admin','is_moderator','show_famille','show_hebergements'),
            'subscription' => ['plan' => $plan_h, 'planKey' => $plan_key, 'status' => $sub_status ?: 'inactif', 'periodEnd' => $period_end_date, 'customerId' => $customer_id ?: '', 'subId' => $sub_id ?: ''],
            'stats' => ['totalReservations' => $total_reservations],
        ];

        global $wpdb;
        $card_row = $wpdb->get_var($wpdb->prepare(
            "SELECT bio FROM " . WPSD_DB::table_member_cards() . " WHERE user_id = %d",
            $user_id
        ));
        $has_bio = !empty($card_row);
        $tabs_unlocked = $has_bio;

        $parcours_table = WPSD_DB::table_parcours();
        $res_table = WPSD_DB::table_reservations();
        $parcours_en_cours = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $parcours_table WHERE user_id = %d AND statut IN ('draft','pending_acceptance','accepted','paid')",
            $user_id
        ));
        $demandes_en_attente = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $res_table WHERE (provider_user_id = %d OR itinerant_user_id = %d) AND status = 'pending'",
            $user_id, $user_id
        ));

        wp_localize_script('wpsd-dashboard', 'WPSD', array_merge(
            $wp_localize_script_args,
            ['hasBio' => $has_bio, 'activitySummary' => ['parcoursEnCours' => $parcours_en_cours, 'demandesEnAttente' => $demandes_en_attente]]
        ));

        $tabs = [];
        $tabs['dashboard'] = ['label' => 'Accueil', 'visible' => true];
        $tabs['carte'] = ['label' => 'Carte', 'visible' => true];
        $tabs['compte'] = ['label' => 'Mon compte', 'visible' => true];
        if ($tabs_unlocked) {
            if ($is_itinerant) $tabs['parcours'] = ['label' => 'Mon parcours', 'visible' => true];
            if ($is_passeur) $tabs['savoirs'] = ['label' => 'Mes savoirs', 'visible' => true];
            if ($show_hebergements && !$is_passeur) $tabs['hebergements'] = ['label' => 'Hebergements', 'visible' => true];
            if ($is_itinerant || $is_passeur || $show_hebergements) $tabs['demandes'] = ['label' => 'Demandes', 'visible' => true];
            $tabs['articles'] = ['label' => 'Recits', 'visible' => true];
        }
        if ($show_moderation) $tabs['moderation'] = ['label' => 'Moderation', 'visible' => true];

        ob_start();
        if (isset($_GET['success'])) echo '<div class="wpsd-alert wpsd-alert-success">Paiement confirme.</div>';
        if (isset($_GET['canceled'])) echo '<div class="wpsd-alert wpsd-alert-info">Paiement annule.</div>';
        if (!$admin_approved) { echo '<div class="wpsd-card"><div class="wpsd-alert-info">Votre compte est en attente de validation.</div></div>'; return ob_get_clean(); }

        include WPSD_PATH . 'templates/dashboard-header.php';
        echo '<meta name="wpsd_stripe_key" content="' . esc_attr($stripe_publishable_key) . '">';
        include WPSD_PATH . 'templates/dashboard-tabs.php';
        echo '<div class="wpsd-panels">';
        include WPSD_PATH . 'templates/dashboard-panel-accueil.php';
        include WPSD_PATH . 'templates/dashboard-panel-carte.php';
        include WPSD_PATH . 'templates/dashboard-panel-compte.php';
        if ($is_itinerant) { echo '<div class="wpsd-panel' . ($tabs_unlocked ? '' : ' wpsd-panel-conditional') . '" id="wpsd-panel-parcours">'; include WPSD_PATH . 'templates/dashboard-panel-parcours.php'; echo '</div>'; }
        if ($is_passeur) { echo '<div class="wpsd-panel' . ($tabs_unlocked ? '' : ' wpsd-panel-conditional') . '" id="wpsd-panel-savoirs">'; include WPSD_PATH . 'templates/dashboard-panel-savoirs.php'; echo '</div>'; }
        if ($show_hebergements && !$is_passeur) { echo '<div class="wpsd-panel' . ($tabs_unlocked ? '' : ' wpsd-panel-conditional') . '" id="wpsd-panel-hebergements">'; include WPSD_PATH . 'templates/dashboard-panel-hebergements.php'; echo '</div>'; }
        if ($is_itinerant || $is_passeur || $show_hebergements) { echo '<div class="wpsd-panel' . ($tabs_unlocked ? '' : ' wpsd-panel-conditional') . '" id="wpsd-panel-demandes">'; include WPSD_PATH . 'templates/dashboard-panel-demandes.php'; echo '</div>'; }
        echo '<div class="wpsd-panel' . ($tabs_unlocked ? '' : ' wpsd-panel-conditional') . '" id="wpsd-panel-articles">';
        include WPSD_PATH . 'templates/dashboard-panel-articles.php';
        echo '</div>';
        if ($show_moderation) include WPSD_PATH . 'templates/dashboard-panel-moderation.php';
        echo '</div>';
        include WPSD_PATH . 'templates/dashboard-modals.php';
        return ob_get_clean();
    }

    private function get_user_reservation_count($user_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".WPSD_DB::table_reservations()." WHERE itinerant_user_id=%d OR provider_user_id=%d", $user_id, $user_id));
    }
}