<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel" id="wpsd-panel-adhesion">
    <div class="wpsd-dashboard-grid">
        <div class="wpsd-card">
            <h3>Mon abonnement</h3>
            <div class="wpsd-subscription-info">
                <div class="wpsd-sub-row"><span>Plan</span><strong><?= $plan_h ?></strong></div>
                <div class="wpsd-sub-row"><span>Statut</span><strong class="<?= $is_active ? 'wpsd-text-success' : 'wpsd-text-danger' ?>"><?= esc_html($sub_status ?: 'Inactif') ?></strong></div>
                <div class="wpsd-sub-row"><span>Prochain renouvellement</span><strong><?= $period_end_date ?></strong></div>
                <div class="wpsd-sub-row"><span>Stripe Customer</span><code><?= esc_html($customer_id ?: '—') ?></code></div>
            </div>
            <div class="wpsd-sub-actions">
                <button class="wpsd-btn wpsd-primary" id="wpsd_open_portal">Gérer mon abonnement (Stripe)</button>
                <?php if ($plan_key !== 'family' && $plan_key !== 'none'): ?>
                    <button class="wpsd-btn" id="wpsd_change_plan" data-plan="family">Passer au plan Famille</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="wpsd-card">
            <h3>Historique des paiements</h3>
            <div id="wpsd_payment_history"><p class="wpsd-hint">Chargement...</p></div>
        </div>
    </div>
</div>