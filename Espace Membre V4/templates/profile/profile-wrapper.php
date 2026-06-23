<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$user    = wp_get_current_user();

// Récupérer les données utilisateur
$first_name = get_user_meta($user_id, 'first_name', true);
$last_name  = get_user_meta($user_id, 'last_name', true);

// Plan et rôle
$plan_key   = get_user_meta($user_id, 'plan_key', true);      // 'member', 'family', 'institution'
$is_passeur = (int) get_user_meta($user_id, 'is_passeur', true); // 0 ou 1

// Fallback nom depuis la table pending
if (empty($first_name) || empty($last_name)) {
    global $wpdb;
    $pending = $wpdb->get_row($wpdb->prepare(
        "SELECT nom, prenom FROM " . WPSD_DB::table_pending_registrations() . " WHERE email = %s AND status = 'approved' ORDER BY id DESC LIMIT 1",
        $user->user_email
    ));
    if ($pending) {
        if (empty($first_name)) $first_name = $pending->prenom;
        if (empty($last_name))  $last_name  = $pending->nom;
    }
}

$display_name = trim($first_name . ' ' . $last_name) ?: $user->display_name;
$photo_url    = get_user_meta($user_id, 'wpsd_profile_photo_url', true);
?>

<div class="wpsd-profile-container" id="wpsd-panel-profile">

    <!-- Barre de sous-onglets -->
    <div class="wpsd-profile-subtabs">
        <!-- ✅ Mon Profil : toujours visible -->
        <button class="wpsd-subtab-btn active" data-subtab="profile">
             <?php _e('Mon Profil', 'wp-stripe-dashboard'); ?>
        </button>
        
        <!-- ✅ Ma Famille : uniquement plan "family" -->
        <?php if ($plan_key === 'family'): ?>
        <button class="wpsd-subtab-btn" data-subtab="family">
             <?php _e('Ma Famille', 'wp-stripe-dashboard'); ?>
        </button>
        <?php endif; ?>
        
        <!-- ✅ Mon Entreprise : uniquement rôle "passeur de savoirs" -->
        <?php if ($is_passeur): ?>
        <button class="wpsd-subtab-btn" data-subtab="company">
             <?php _e('Mon Entreprise', 'wp-stripe-dashboard'); ?>
        </button>
        <?php endif; ?>
        
        <!-- ✅ Mon Adhésion : toujours visible -->
        <button class="wpsd-subtab-btn" data-subtab="subscription">
             <?php _e('Mon Adhésion', 'wp-stripe-dashboard'); ?>
        </button>
    </div>

    <!-- Contenu des sous-onglets -->
    <div class="wpsd-profile-content">

        <!-- ✅ Mon Profil : toujours affiché -->
        <div class="wpsd-subtab-panel active" id="wpsd-subtab-profile">
            <?php include __DIR__ . '/profile-subtab-profile.php'; ?>
        </div>

        <!-- ✅ Ma Famille : uniquement si plan "family" -->
        <?php if ($plan_key === 'family'): ?>
        <div class="wpsd-subtab-panel" id="wpsd-subtab-family">
            <?php include __DIR__ . '/profile-subtab-family.php'; ?>
        </div>
        <?php endif; ?>

        <!-- ✅ Mon Entreprise : uniquement si rôle "passeur" -->
        <?php if ($is_passeur): ?>
        <div class="wpsd-subtab-panel" id="wpsd-subtab-company">
            <?php include __DIR__ . '/profile-subtab-company.php'; ?>
        </div>
        <?php endif; ?>

        <!-- ✅ Mon Adhésion : toujours affiché -->
        <div class="wpsd-subtab-panel" id="wpsd-subtab-subscription">
            <?php include __DIR__ . '/profile-subtab-subscription.php'; ?>
        </div>

    </div>
</div>