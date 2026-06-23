<?php if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$user = wp_get_current_user();

// Prénom et nom
$first_name = get_user_meta($user_id, 'first_name', true);
$last_name  = get_user_meta($user_id, 'last_name', true);
$display_name = trim($first_name . ' ' . $last_name) ?: $user->display_name;

// Photo de profil
$photo_url = get_user_meta($user_id, 'wpsd_profile_photo_url', true);
?>
<div class="wpsd-dashboard-header">
    <div class="wpsd-header-photo">
        <?php if ($photo_url): ?>
            <img src="<?php echo esc_url($photo_url); ?>" alt="Photo de profil" class="wpsd-header-avatar">
        <?php else: ?>
            <div class="wpsd-header-avatar wpsd-header-avatar-placeholder">
                <?php echo esc_html(strtoupper(mb_substr($first_name ?: $user->display_name, 0, 1))); ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="wpsd-profile-info">
        <div class="wpsd-profile-name"><?php echo esc_html($display_name); ?></div>
        <div class="wpsd-profile-email"><?php echo esc_html($user->user_email); ?></div>
        <div class="wpsd-profile-plan"><strong>Offre :</strong> <?php echo $plan_h; ?></div>
        <div class="wpsd-profile-logout"><a href="<?php echo esc_url(wp_logout_url(home_url('/connexion/'))); ?>">Se déconnecter</a></div>
    </div>
</div>