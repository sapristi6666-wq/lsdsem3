<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wpsd-profile-section">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;"><?php _e('Membres de la famille', 'wp-stripe-dashboard'); ?></h3>
        <button class="wpsd-btn wpsd-btn-sm wpsd-primary" onclick="WPSD_Modals.open('fam', null)">
            + <?php _e('Ajouter', 'wp-stripe-dashboard'); ?>
        </button>
    </div>
    <div id="wpsd-family-list">
        <p class="wpsd-hint"><?php _e('Chargement...', 'wp-stripe-dashboard'); ?></p>
    </div>
</div>