<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-dashboard-header">
    <div class="wpsd-profile-info">
        <div class="wpsd-profile-name"><?= esc_html($user->display_name) ?></div>
        <div class="wpsd-profile-email"><?= esc_html($user->user_email) ?></div>
        <div class="wpsd-profile-plan"><strong>Offre :</strong> <?= $plan_h ?></div>
        <div class="wpsd-profile-logout"><a href="<?= esc_url(wp_logout_url(home_url('/connexion/'))) ?>">Se déconnecter</a></div>
    </div>
</div>