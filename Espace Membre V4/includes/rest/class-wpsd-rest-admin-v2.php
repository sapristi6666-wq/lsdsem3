<?php
if (!defined('ABSPATH')) exit;

/**
 * Routes REST pour l'admin (v2)
 * 
 * GET  /wpsd/v2/admin/inscriptions
 * POST /wpsd/v2/admin/inscriptions/{user_id}/validate
 * POST /wpsd/v2/admin/inscriptions/{user_id}/reject
 * GET  /wpsd/v2/admin/facturation
 * PUT  /wpsd/v2/admin/facturation/mark-paid
 */
class WPSD_REST_AdminV2 {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('wpsd/v2', '/admin/inscriptions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_inscriptions'],
            'permission_callback' => [$this, 'check_admin_or_moderator'],
        ]);
        register_rest_route('wpsd/v2', '/admin/inscriptions/(?P<user_id>\d+)/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'validate_inscription'],
            'permission_callback' => [$this, 'check_admin_or_moderator'],
        ]);
        register_rest_route('wpsd/v2', '/admin/inscriptions/(?P<user_id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_inscription'],
            'permission_callback' => [$this, 'check_admin_or_moderator'],
        ]);
        register_rest_route('wpsd/v2', '/admin/facturation', [
            'methods' => 'GET',
            'callback' => [$this, 'get_facturation'],
            'permission_callback' => [$this, 'check_admin'],
        ]);
        register_rest_route('wpsd/v2', '/admin/facturation/mark-paid', [
            'methods' => 'PUT',
            'callback' => [$this, 'mark_paid'],
            'permission_callback' => [$this, 'check_admin'],
        ]);
    }

    // ==================== PERMISSIONS ====================

    public function check_admin_or_moderator() {
        return current_user_can('manage_options') || current_user_can('wpsd_moderate_registrations');
    }

    public function check_admin() {
        return current_user_can('manage_options');
    }

    // ==================== INSCRIPTIONS ====================

    /**
     * GET /admin/inscriptions
     * Liste des inscriptions en attente
     */
    public function get_inscriptions($request) {
        global $wpdb;
        $table = WPSD_DB::table_pending_registrations();

        $statut = sanitize_text_field($request->get_param('statut') ?: 'pending');
        $role = sanitize_text_field($request->get_param('role') ?: '');

        $where = "WHERE status = %s";
        $args = [$statut];

        if ($role) {
            $where .= " AND role = %s";
            $args[] = $role;
        }

        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 100", ...$args),
            ARRAY_A
        );

        return rest_ensure_response([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    /**
     * POST /admin/inscriptions/{user_id}/validate
     * Valide une inscription et crée le compte utilisateur
     */
    public function validate_inscription($request) {
        global $wpdb;
        $user_id = (int) $request->get_param('user_id');

        // Récupérer depuis la table pending
        $pending_table = WPSD_DB::table_pending_registrations();
        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $pending_table WHERE id = %d AND status = 'pending'",
            $user_id
        ), ARRAY_A);

        if (!$pending) {
            return new WP_Error('not_found', 'Inscription non trouvée ou déjà traitée', ['status' => 404]);
        }

        $user = get_user_by('email', $pending['email']);
        if (!$user) {
            return new WP_Error('user_not_found', 'Utilisateur introuvable', ['status' => 404]);
        }

        $uid = $user->ID;

        // Mettre à jour les métas
        update_user_meta($uid, 'wpsd_admin_approved', 1);
        update_user_meta($uid, 'wpsd_admin_approved_at', current_time('mysql'));

        // Rôle
        $role = $pending['role'];
        $roles = ['is_itinerant' => 0, 'is_passeur' => 0, 'is_hebergeur' => 0, 'is_sympathisant' => 0];
        if ($role === 'passeur') $roles['is_passeur'] = 1;
        elseif ($role === 'itinerant') $roles['is_itinerant'] = 1;
        elseif ($role === 'hebergeur') $roles['is_hebergeur'] = 1;
        elseif ($role === 'sympathisant') $roles['is_sympathisant'] = 1;
        foreach ($roles as $k => $v) update_user_meta($uid, $k, $v);

        // Plan
        $plan = $pending['plan'];
        update_user_meta($uid, 'plan_key', $plan);
        update_user_meta($uid, 'plan_label', $plan === 'family' ? 'Famille (70€)' : 'Membre (50€)');
        update_user_meta($uid, 'subscription_status', 'active');
        update_user_meta($uid, 'subscription_start', date('Y-m-d'));
        update_user_meta($uid, 'subscription_end', date('Y-m-d', strtotime('+1 year')));

        // Marquer comme traité
        $wpdb->update(
            $pending_table,
            ['status' => 'approved'],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        // Envoyer email de bienvenue
        $reset_key = get_password_reset_key($user);
        if (!is_wp_error($reset_key)) {
            $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($pending['email']));
            $subject = 'Bienvenue aux Sentiers des Savoirs - Créez votre mot de passe';
            $body = sprintf(
                '<p>Bonjour %s,</p><p>Votre adhésion a été validée. Créez votre mot de passe :</p><p><a href="%s" style="padding:12px 24px;background:#005247;color:#FBF1CA;text-decoration:none;border-radius:8px;">Créer mon mot de passe</a></p>',
                esc_html($pending['prenom']),
                esc_url($reset_url)
            );
            wp_mail($pending['email'], $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        }

        return rest_ensure_response([
            'message' => 'Inscription validée avec succès',
            'user_id' => $uid,
        ]);
    }

    /**
     * POST /admin/inscriptions/{user_id}/reject
     * Refuse une inscription et rembourse si Stripe
     */
    public function reject_inscription($request) {
        global $wpdb;
        $pending_id = (int) $request->get_param('user_id');

        $pending_table = WPSD_DB::table_pending_registrations();
        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $pending_table WHERE id = %d AND status = 'pending'",
            $pending_id
        ), ARRAY_A);

        if (!$pending) {
            return new WP_Error('not_found', 'Inscription non trouvée', ['status' => 404]);
        }

        // Tenter un remboursement Stripe
        if (!empty($pending['stripe_subscription_id'])) {
            try {
                $stripe = new WPSD_Stripe();
                // Annuler l'abonnement
                $stripe->post('/subscriptions/' . $pending['stripe_subscription_id'], ['cancel_at_period_end' => false]);
                // Rembourser le dernier paiement
                $invoices = $stripe->get('/invoices', [
                    'subscription' => $pending['stripe_subscription_id'],
                    'limit' => 1,
                ]);
                if (!is_wp_error($invoices) && !empty($invoices['data'])) {
                    $payment_intent = $invoices['data'][0]['payment_intent'] ?? null;
                    if ($payment_intent) {
                        $stripe->post('/refunds', ['payment_intent' => $payment_intent]);
                    }
                }
            } catch (\Exception $e) {
                error_log('WPSD: Erreur remboursement Stripe: ' . $e->getMessage());
            }
        }

        // Marquer comme refusé
        $wpdb->update(
            $pending_table,
            ['status' => 'rejected'],
            ['id' => $pending_id],
            ['%s'],
            ['%d']
        );

        // Supprimer l'utilisateur s'il existe
        $user = get_user_by('email', $pending['email']);
        if ($user && !user_can($user->ID, 'manage_options')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user->ID);
        }

        // Notifier l'utilisateur
        $subject = 'Votre adhésion a été refusée';
        $body = sprintf(
            '<p>Bonjour %s,</p><p>Nous ne pouvons pas donner suite à votre demande d\'adhésion. Votre paiement a été remboursé.</p>',
            esc_html($pending['prenom'])
        );
        wp_mail($pending['email'], $subject, $body, ['Content-Type: text/html; charset=UTF-8']);

        return rest_ensure_response([
            'message' => 'Inscription refusée et remboursée',
        ]);
    }

    // ==================== FACTURATION ====================

    /**
     * GET /admin/facturation?mois=MM&annee=AAAA
     * Calcule les sommes dues par prestataire pour un mois donné
     */
    public function get_facturation($request) {
        global $wpdb;

        $mois = (int) $request->get_param('mois') ?: (int) date('m');
        $annee = (int) $request->get_param('annee') ?: (int) date('Y');

        // Valider mois
        if ($mois < 1 || $mois > 12) {
            return new WP_Error('invalid_month', 'Mois invalide', ['status' => 400]);
        }

        $date_debut = sprintf('%04d-%02d-01', $annee, $mois);
        $date_fin = date('Y-m-t', strtotime($date_debut));

        // Récupérer les réservations completed dans la période
        $res_table = WPSD_DB::table_reservations();
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                r.provider_user_id,
                COUNT(DISTINCT r.id) as nb_reservations,
                SUM(
                    DATEDIFF(LEAST(r.date_end, %s), GREATEST(r.date_start, %s))
                ) as nb_jours,
                r.id as reservation_id,
                r.date_start,
                r.date_end,
                r.kind
            FROM $res_table r
            WHERE r.status = 'completed'
                AND r.date_start <= %s
                AND r.date_end >= %s
            GROUP BY r.provider_user_id
            ORDER BY nb_jours DESC",
            $date_fin, $date_debut,
            $date_fin, $date_debut
        ), ARRAY_A);

        // Prix configurable
        $settings = get_option('wpsd_settings', []);
        $prix_jour_itinerant = (float) ($settings['prix_jour_itinérant'] ?? 5);
        $prix_jour_association = (float) ($settings['prix_jour_association'] ?? 20);

        $providers = [];
        foreach ($results as $r) {
            $provider_id = (int) $r['provider_user_id'];
            $user = get_userdata($provider_id);
            if (!$user) continue;

            $nb_jours = (int) $r['nb_jours'];
            if ($nb_jours <= 0) $nb_jours = 1; // Minimum 1 jour

            if (!isset($providers[$provider_id])) {
                $providers[$provider_id] = [
                    'provider_id' => $provider_id,
                    'provider_name' => $user->display_name,
                    'provider_email' => $user->user_email,
                    'nb_jours' => 0,
                    'nb_reservations' => 0,
                    'montant_itinerant' => 0,
                    'montant_association' => 0,
                    'montant_total' => 0,
                    'details' => [],
                ];
            }

            $providers[$provider_id]['nb_jours'] += $nb_jours;
            $providers[$provider_id]['nb_reservations'] += (int) $r['nb_reservations'];
            $providers[$provider_id]['montant_itinerant'] += $nb_jours * $prix_jour_itinerant;
            $providers[$provider_id]['montant_association'] += $nb_jours * $prix_jour_association;
            $providers[$provider_id]['montant_total'] += $nb_jours * ($prix_jour_itinerant + $prix_jour_association);
            $providers[$provider_id]['details'][] = [
                'date_start' => $r['date_start'],
                'date_end' => $r['date_end'],
                'kind' => $r['kind'],
                'nb_jours' => $nb_jours,
            ];
        }

        return rest_ensure_response([
            'mois' => $mois,
            'annee' => $annee,
            'periode' => [
                'debut' => $date_debut,
                'fin' => $date_fin,
            ],
            'providers' => array_values($providers),
            'prix_jour_itinerant' => $prix_jour_itinerant,
            'prix_jour_association' => $prix_jour_association,
        ]);
    }

    /**
     * PUT /admin/facturation/mark-paid
     * Marque des factures comme payées
     */
    public function mark_paid($request) {
        $provider_ids = $request->get_param('provider_ids');
        $mois = (int) $request->get_param('mois') ?: (int) date('m');
        $annee = (int) $request->get_param('annee') ?: (int) date('Y');

        if (!is_array($provider_ids) || empty($provider_ids)) {
            return new WP_Error('invalid_input', 'Liste de prestataires requise', ['status' => 400]);
        }

        $marked = [];
        foreach ($provider_ids as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;

            $meta_key = sprintf('wpsd_facture_paid_%04d_%02d', $annee, $mois);
            update_user_meta($pid, $meta_key, current_time('mysql'));
            $marked[] = $pid;
        }

        return rest_ensure_response([
            'message' => sprintf('%d facture(s) marquée(s) comme payée(s)', count($marked)),
            'marked' => $marked,
        ]);
    }
}
