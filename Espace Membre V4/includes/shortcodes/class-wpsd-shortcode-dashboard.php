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
        wp_enqueue_media();
        wp_enqueue_script('wpsd-crop', WPSD_URL . 'js/modules/wpsd-crop.js', [], filemtime(WPSD_PATH . 'js/modules/wpsd-crop.js'), true);

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

        $show_hebergements = ($is_hebergeur || $is_passeur);
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
        $accueil_summary = $this->get_accueil_summary_v2($is_itinerant, $is_passeur, $show_hebergements, $is_sympathisant);

        wp_localize_script('wpsd-dashboard', 'WPSD', [
            'restBase' => esc_url_raw(rest_url('wpsd/v1')), 'nonce' => wp_create_nonce('wp_rest'),
            'userEmail' => $user->user_email, 'planKey' => $plan_key, 'isActive' => $is_active, 'userId' => $user_id,
            'roles' => compact('is_itinerant','is_passeur','is_hebergeur','is_sympathisant','is_full_admin','is_moderator','show_famille','show_hebergements'),
            'subscription' => ['plan' => $plan_h, 'planKey' => $plan_key, 'status' => $sub_status ?: 'inactif', 'periodEnd' => $period_end_date, 'customerId' => $customer_id ?: '', 'subId' => $sub_id ?: ''],
            'stats' => ['totalReservations' => $total_reservations],
        ]);

        $tabs = [];
        $tabs['dashboard'] = ['label' => 'Tableau de bord', 'visible' => true];
        $tabs['carte'] = ['label' => 'Carte', 'visible' => true];
        $tabs['profile'] = ['label' => 'Mon profil', 'visible' => true];
        if ($is_itinerant) $tabs['parcours'] = ['label' => 'Mon parcours', 'visible' => true];
        if ($is_passeur) $tabs['savoirs'] = ['label' => 'Mes savoirs', 'visible' => true];
        if ($show_hebergements) $tabs['hebergements'] = ['label' => 'Mes hébergements', 'visible' => true];
        if ($is_itinerant || $is_passeur || $show_hebergements) $tabs['demandes'] = ['label' => 'Demandes', 'visible' => true];
        if ($show_moderation) $tabs['moderation'] = ['label' => 'Modération', 'visible' => true];

        ob_start();
        if (isset($_GET['success'])) echo '<div class="wpsd-alert wpsd-alert-success">Paiement confirmé.</div>';
        if (isset($_GET['canceled'])) echo '<div class="wpsd-alert wpsd-alert-info">Paiement annulé.</div>';
        if (!$admin_approved) { echo '<div class="wpsd-card"><div class="wpsd-alert-info">Votre compte est en attente de validation.</div></div>'; return ob_get_clean(); }

        include WPSD_PATH . 'templates/dashboard-header.php';
        echo '<meta name="wpsd_stripe_key" content="' . esc_attr($stripe_publishable_key) . '">';
        include WPSD_PATH . 'templates/dashboard-tabs.php';
        echo '<div class="wpsd-panels">';
        include WPSD_PATH . 'templates/dashboard-panel-accueil.php';
        echo '<div class="wpsd-panel" id="wpsd-panel-profile">';
        include WPSD_PATH . 'templates/profile/profile-wrapper.php';
        echo '</div>';
        include WPSD_PATH . 'templates/dashboard-panel-carte.php';
        if ($is_itinerant) include WPSD_PATH . 'templates/dashboard-panel-parcours.php';
        if ($is_passeur) include WPSD_PATH . 'templates/dashboard-panel-savoirs.php';
        if ($show_hebergements) include WPSD_PATH . 'templates/dashboard-panel-hebergements.php';
        if ($is_itinerant || $is_passeur || $show_hebergements) include WPSD_PATH . 'templates/dashboard-panel-demandes.php';
        include WPSD_PATH . 'templates/dashboard-panel-articles.php';
        if ($show_famille) include WPSD_PATH . 'templates/dashboard-panel-famille.php';
        include WPSD_PATH . 'templates/dashboard-panel-adhesion.php';
        if ($show_moderation) include WPSD_PATH . 'templates/dashboard-panel-moderation.php';
        echo '</div>';
        include WPSD_PATH . 'templates/dashboard-modals.php';
        return ob_get_clean();
    }

    private function get_user_reservation_count($user_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".WPSD_DB::table_reservations()." WHERE itinerant_user_id=%d OR provider_user_id=%d", $user_id, $user_id));
    }

    private function get_accueil_summary_v2($is_itinerant, $is_passeur, $show_hebergements, $is_sympathisant) {
        $roles = [];
        if ($is_itinerant) $roles[] = WPSD_Data::role_label('itinerant');
        if ($is_passeur) $roles[] = WPSD_Data::role_label('passeur');
        if ($show_hebergements && !$is_passeur) $roles[] = 'Sympathisant-Hébergeur';
        elseif ($is_sympathisant && !$show_hebergements) $roles[] = WPSD_Data::role_label('sympathisant');

        $out = '<p>Vous êtes connecté en tant que <strong>' . esc_html(implode(' + ', $roles)) . '</strong>.</p>';

        if ($is_itinerant || $is_passeur || $show_hebergements || $is_sympathisant) {
            $out .= '<p style="margin-top:10px;">Voici ce que vous pouvez faire :</p><ul style="margin-top:6px;">';
            if ($is_itinerant) $out .= '<li>Planifiez votre voyage dans <strong>Mon parcours</strong></li>';
            if ($is_passeur) $out .= '<li>Proposez vos savoirs dans <strong>Mes savoirs</strong></li>';
            if ($show_hebergements) $out .= '<li>Gérez vos hébergements dans <strong>Hébergements</strong></li>';
            $out .= '<li>Découvrez les membres sur la <strong>Carte</strong></li>';
            $out .= '</ul>';
        }
        return $out;
    }
}