<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestion des capacités et rôles personnalisés pour le système de parcours
 */
class WPSD_Capabilities {

    /**
     * Ajoute les capacités aux rôles existants (à l'activation du plugin)
     */
    public static function add_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('edit_parcours', true);
            $admin->add_cap('edit_own_parcours', true);
            $admin->add_cap('edit_reservation', true);
            $admin->add_cap('confirm_reservation', true);
            $admin->add_cap('manage_sentiers', true);
            $admin->add_cap('view_facturation', true);
        }

        // Capacités pour le rôle subscriber (utilisateurs connectés par défaut)
        $subscriber = get_role('subscriber');
        if ($subscriber) {
            $subscriber->add_cap('edit_own_parcours', true);
            $subscriber->add_cap('confirm_reservation', true);
        }

        // Modérateur WPSD
        $moderator = get_role('wpsd_moderator');
        if ($moderator) {
            $moderator->add_cap('edit_reservation', true);
            $moderator->add_cap('manage_sentiers', true);
        }
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'un parcours
     */
    public static function is_parcours_owner($parcours_id, $user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        global $wpdb;
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM " . WPSD_DB::table_parcours() . " WHERE id = %d",
            $parcours_id
        ));
        return (int)$owner === (int)$user_id;
    }

    /**
     * Vérifie si l'utilisateur peut éditer/voir un parcours
     */
    public static function user_can_access_parcours($parcours_id, $user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_sentiers')) return true;
        return self::is_parcours_owner($parcours_id, $user_id);
    }
}
