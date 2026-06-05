<?php
if (!defined('ABSPATH')) exit;

class WPSD_Admin_Stats {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_stats_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function add_stats_page() {
        add_submenu_page('wpsd-dashboard', 'Statistiques', 'Statistiques', 'manage_options', 'wpsd-stats', [$this, 'render']);
    }

    public function render() {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé'); }
        wp_enqueue_style('wpsd-admin-stats');
        wp_enqueue_script('chartjs');
        wp_enqueue_script('wpsd-admin-stats');
        include WPSD_PATH . 'templates/admin/stats-page.php';
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'sentiers_page_wpsd-stats') return;
        wp_enqueue_style('wpsd-admin-stats', WPSD_URL . 'assets/css/admin/wpsd-stats.css', [], time());
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('wpsd-admin-stats', WPSD_URL . 'assets/js/admin/wpsd-stats.js', ['chartjs'], time(), true);
        wp_localize_script('wpsd-admin-stats', 'WPSD_Stats', [
            'rest_url' => rest_url('wpsd/v2/admin/stats'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function register_routes() {
        register_rest_route('wpsd/v2', '/admin/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats_data'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function get_stats_data($request) {
        global $wpdb;
        $mois = (int) ($request->get_param('mois') ?: date('m'));
        $annee = (int) ($request->get_param('annee') ?: date('Y'));

        $total_membres = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key='wpsd_admin_approved' AND meta_value='1'"
        );

        $parcours_table = WPSD_DB::table_parcours();
        $parcours_mois = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $parcours_table WHERE statut='completed' AND MONTH(updated_at)=%d AND YEAR(updated_at)=%d",
            $mois, $annee
        ));

        $payments_table = WPSD_DB::table_payments();
        $montant_adhesions = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM $payments_table WHERE MONTH(created_at)=%d AND YEAR(created_at)=%d",
            $mois, $annee
        ));

        $res_table = WPSD_DB::table_reservations();
        $montant_passeurs = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(DATEDIFF(date_end, date_start) * 20),0) FROM $res_table WHERE status='completed' AND MONTH(date_end)=%d AND YEAR(date_end)=%d",
            $mois, $annee
        ));

        $evol_adhesions = [];
        $evol_parcours = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = (int) date('m', strtotime("-{$i} months"));
            $y = (int) date('Y', strtotime("-{$i} months"));
            $label = date('M Y', strtotime("-{$i} months"));
            $evol_adhesions[] = [
                'mois' => $label,
                'montant' => (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(amount),0) FROM $payments_table WHERE MONTH(created_at)=%d AND YEAR(created_at)=%d",
                    $m, $y
                )),
            ];
            $evol_parcours[] = [
                'mois' => $label,
                'nb' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $parcours_table WHERE statut='completed' AND MONTH(updated_at)=%d AND YEAR(updated_at)=%d",
                    $m, $y
                )),
            ];
        }

        $top_passeurs = $wpdb->get_results(
            "SELECT r.provider_user_id as prestataire_id, COUNT(*) as nb FROM $res_table r WHERE r.status='completed' GROUP BY r.provider_user_id ORDER BY nb DESC LIMIT 5",
            ARRAY_A
        );
        foreach ($top_passeurs as &$p) {
            $user = get_userdata((int) $p['prestataire_id']);
            $p['display_name'] = $user ? $user->display_name : 'Inconnu';
        }

        return rest_ensure_response([
            'ok' => true,
            'total_membres' => $total_membres,
            'parcours_mois' => $parcours_mois,
            'montant_adhesions' => $montant_adhesions,
            'montant_passeurs' => $montant_passeurs,
            'evol_adhesions' => $evol_adhesions,
            'evol_parcours' => $evol_parcours,
            'top_passeurs' => $top_passeurs,
        ]);
    }
}
