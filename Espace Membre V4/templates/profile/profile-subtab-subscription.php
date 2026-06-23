<?php
if (!defined('ABSPATH')) exit;

// Récupération des données d'adhésion (mêmes variables que dans le shortcode)
$sub_status      = get_user_meta($user_id, 'subscription_status', true);
$sub_id          = get_user_meta($user_id, 'stripe_subscription_id', true);
$customer_id     = get_user_meta($user_id, 'stripe_customer_id', true);
$plan_key        = get_user_meta($user_id, 'plan_key', true) ?: 'none';
$period_end_ts   = wpsd_get_user_period_end_ts($user_id);
$period_end_date = $period_end_ts ? date_i18n('d/m/Y', $period_end_ts) : '—';

// Libellé du statut
switch ($sub_status) {
    case 'active':   $status_label = 'Actif'; $status_color = '#005247'; break;
    case 'trialing': $status_label = 'Période d\'essai'; $status_color = '#e0b912'; break;
    case 'past_due': $status_label = 'En retard'; $status_color = '#c73d2a'; break;
    case 'canceled': $status_label = 'Annulé'; $status_color = '#999'; break;
    default:         $status_label = ucfirst($sub_status ?: 'Aucun'); $status_color = '#999';
}

// Récupération du libellé de l'offre
$plan_names = [
    'none'     => 'Aucune',
    'sympathisant' => 'Sympathisant',
    'family'       => 'Famille',
    'itinerant'    => 'Itinérant',
    'passeur'      => 'Passeur',
];
$plan_label = isset($plan_names[$plan_key]) ? $plan_names[$plan_key] : $plan_key;

// URL du portail client Stripe (si disponible)
$portal_url = get_user_meta($user_id, 'stripe_portal_url', true);
?>

<div class="wpsd-profile-section">
    <h3><?php _e('Mon Adhésion', 'wp-stripe-dashboard'); ?></h3>

    <div id="wpsd-subscription-info">
        <div style="display:flex; flex-direction: column; gap: 14px;">

            <!-- Statut -->
            <div style="display:flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid rgba(0,82,71,0.15);">
                <span style="font-weight:600; color:#005247;">Statut</span>
                <span style="font-weight:600; color:<?php echo $status_color; ?>;"><?php echo esc_html($status_label); ?></span>
            </div>

            <!-- Formule -->
            <div style="display:flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid rgba(0,82,71,0.15);">
                <span style="font-weight:600; color:#005247;">Formule</span>
                <span><?php echo esc_html($plan_label); ?></span>
            </div>

            <!-- Prochaine échéance -->
            <?php if ($sub_status === 'active' || $sub_status === 'trialing'): ?>
            <div style="display:flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid rgba(0,82,71,0.15);">
                <span style="font-weight:600; color:#005247;">Prochaine échéance</span>
                <span><?php echo esc_html($period_end_date); ?></span>
            </div>
            <?php endif; ?>

            <!-- Identifiant client Stripe (admin seulement) -->
            <?php if (current_user_can('manage_options') && $customer_id): ?>
            <div style="display:flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid rgba(0,82,71,0.15);">
                <span style="font-weight:600; color:#005247;">Client Stripe</span>
                <code style="font-size:12px; background:#f0f0f0; padding:2px 6px; border-radius:4px;"><?php echo esc_html($customer_id); ?></code>
            </div>
            <?php endif; ?>

            <?php if ($sub_id): ?>
            <div style="display:flex; justify-content: space-between; align-items: center;">
                <span style="font-weight:600; color:#005247;">Abonnement Stripe</span>
                <code style="font-size:12px; background:#f0f0f0; padding:2px 6px; border-radius:4px;"><?php echo esc_html($sub_id); ?></code>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($portal_url): ?>
        <div style="margin-top: 20px; text-align: center;">
            <a href="<?php echo esc_url($portal_url); ?>" target="_blank" class="wpsd-btn wpsd-primary">
                <?php _e('Gérer mon abonnement', 'wp-stripe-dashboard'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>