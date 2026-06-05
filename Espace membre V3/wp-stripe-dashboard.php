<?php
/**
 * Plugin Name: WP Stripe Dashboard
 * Version: 0.2.0
 * Description: Gestion des abonnements Stripe et réservations
 * Text Domain: wp-stripe-dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPSD_PATH', plugin_dir_path(__FILE__));
define('WPSD_URL', plugin_dir_url(__FILE__));

require_once WPSD_PATH . 'includes/class-wpsd-plugin.php';

register_activation_hook(__FILE__, function() {
    require_once WPSD_PATH . 'includes/class-wpsd-plugin.php';
    WPSD_DB::activate();
    WPSD_Plugin::activate();
});

add_action('plugins_loaded', function() {
    WPSD_Plugin::instance();
    if (class_exists('WPSD_Invoices')) {
        new WPSD_Invoices();
    } else {
        require_once WPSD_PATH . 'includes/class-wpsd-invoices.php';
        new WPSD_Invoices();
    }
});

function wpsd_get_user_period_end_ts($user_id) {
    $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
    if (!$customer_id) return null;

    $cache_key = 'wpsd_period_end_' . $user_id;
    $cached = wp_cache_get($cache_key, 'wpsd');
    if ($cached !== false) return $cached ?: null;

    $stripe = new WPSD_Stripe();

    $subs = $stripe->get('/subscriptions', [
        'customer' => $customer_id,
        'status'   => 'active',
        'limit'    => 1,
    ]);

    if (is_wp_error($subs) || empty($subs['data'])) {
        $subs = $stripe->get('/subscriptions', [
            'customer' => $customer_id,
            'status'   => 'trialing',
            'limit'    => 1,
        ]);
    }

    if (is_wp_error($subs)) {
        wp_cache_set($cache_key, 0, 'wpsd', HOUR_IN_SECONDS);
        return null;
    }

    $sub = isset($subs['data'][0]) ? $subs['data'][0] : null;
    if (!$sub) {
        wp_cache_set($cache_key, 0, 'wpsd', HOUR_IN_SECONDS);
        return null;
    }

    $ts = (int) (isset($sub['current_period_end']) ? $sub['current_period_end'] : 0);
    wp_cache_set($cache_key, $ts ?: 0, 'wpsd', HOUR_IN_SECONDS);
    return $ts > 0 ? $ts : null;
}

add_action('init', function() {
    if (!wp_next_scheduled('wpsd_daily_renewal_notice')) {
        wp_schedule_event(time() + 300, 'daily', 'wpsd_daily_renewal_notice');
    }
    if (!wp_next_scheduled('wpsd_pre_arrival_notice')) {
        wp_schedule_event(time() + 600, 'daily', 'wpsd_pre_arrival_notice');
    }
});

add_action('wpsd_daily_renewal_notice', function() {
    $users = get_users([
        'fields' => ['ID'],
        'meta_query' => [
            [
                'key' => 'subscription_status',
                'value' => ['active', 'trialing'],
                'compare' => 'IN'
            ]
        ],
        'number' => 5000,
    ]);

    $batches = array_chunk($users, 50);
    $delay = 0;

    foreach ($batches as $index => $batch) {
        wp_schedule_single_event(time() + $delay, 'wpsd_renewal_batch', [$batch]);
        $delay += 30;
    }
});

add_action('wpsd_renewal_batch', function($users) {
    $subject_template = get_option('wpsd_email_subject_renewal_reminder', '');
    if ($subject_template === '') $subject_template = 'Rappel : renouvellement automatique le {{date_fr}}';
    $body_template = get_option('wpsd_email_body_renewal_reminder', '');
    if ($body_template === '') $body_template = "<p>Bonjour {{display_name}},</p>\n<p>Petit rappel : votre abonnement sera renouvelé automatiquement le {{date_fr}}.</p>\n<p><a href=\"{{account_url}}\">Gérer mon abonnement</a></p>";

    foreach ($users as $u) {
        $uid = (int) $u->ID;
        $period_end = wpsd_get_user_period_end_ts($uid);
        if (!$period_end) continue;

        $days_left = (int) floor(($period_end - time()) / DAY_IN_SECONDS);
        if ($days_left < 29 || $days_left > 30) continue;

        $last = (int) get_user_meta($uid, 'wpsd_renewal_notice_period_end', true);
        if ($last === (int) $period_end) continue;

        $user_data = get_userdata($uid);
        $to = sanitize_email($user_data->user_email);
        if (!$to) continue;

        $name = $user_data->display_name ? $user_data->display_name : 'Bonjour';
        $date_fr = date_i18n('d/m/Y', $period_end);
        $account_url = home_url('/mon-compte/');

        $subject = str_replace('{{date_fr}}', $date_fr, $subject_template);
        $body = str_replace(
            ['{{display_name}}', '{{date_fr}}', '{{account_url}}'],
            [$name, $date_fr, $account_url],
            $body_template
        );
        $body .= '<p style="margin-top:16px;color:#666;font-size:12px">Message automatique — ne pas répondre.</p>';

        wp_mail([$to], $subject, $body, ['Content-Type: text/html; charset=UTF-8']);

        update_user_meta($uid, 'wpsd_renewal_notice_period_end', (int) $period_end);
        update_user_meta($uid, 'wpsd_renewal_notice_sent_at', current_time('mysql'));
    }
});

register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('wpsd_daily_renewal_notice');
    if ($timestamp) wp_unschedule_event($timestamp, 'wpsd_daily_renewal_notice');
    $timestamp2 = wp_next_scheduled('wpsd_pre_arrival_notice');
    if ($timestamp2) wp_unschedule_event($timestamp2, 'wpsd_pre_arrival_notice');
    
    $crons = _get_cron_array();
    if (!empty($crons)) {
        foreach ($crons as $ts => $hooks) {
            if (isset($hooks['wpsd_renewal_batch'])) {
                unset($crons[$ts]['wpsd_renewal_batch']);
                if (empty($crons[$ts])) unset($crons[$ts]);
            }
        }
        _set_cron_array($crons);
    }
    
    remove_role('wpsd_moderator');
});

add_action('wpsd_pre_arrival_notice', 'wpsd_send_pre_arrival_emails');

add_action('rest_api_init', function() {
    remove_filter('rest_authentication_errors', 'rest_cookie_check_errors', 100);
}, 15);

// ============================================================
// PERSONNALISATION DES PAGES DE CONNEXION
// ============================================================

// CSS pour les pages de connexion WordPress
add_action('login_enqueue_scripts', function() {
    wp_enqueue_style('wpsd-login-page', WPSD_URL . 'assets/login-page.css', [], filemtime(WPSD_PATH . 'assets/login-page.css'));
});

// Remplacer le logo WordPress par le nom du site
add_filter('login_headertext', function() { return get_bloginfo('name'); });
add_filter('login_headerurl', function() { return home_url(); });

// Rediriger le lien "S'inscrire" vers la page d'adhésion
add_filter('register_url', function($url) {
    return home_url('/adhesions/');
});

// Changer le texte du lien "S'inscrire" en "Pas encore de compte ? Adhérer"
add_action('login_footer', function() {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var regLink = document.querySelector("#nav a");
            if (regLink && regLink.href.includes("action=register") || regLink && regLink.href.includes("adhesions")) {
                // Remplacer le texte
                var parent = regLink.parentNode;
                if (parent) {
                    parent.innerHTML = \'Pas encore de compte ? <a href="\' + regLink.href + \'">Adhérer</a>\';
                }
            }
        });
    </script>';
});