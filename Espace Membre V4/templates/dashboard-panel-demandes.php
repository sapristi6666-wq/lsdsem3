<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel" id="wpsd-panel-demandes">
    <div class="wpsd-subtabs">
        <?php if ($is_itinerant): ?>
            <button class="wpsd-subtab is-active" data-subtab="envoyees">Envoyées</button>
        <?php endif; ?>
        <?php if ($is_passeur || $show_hebergements): ?>
            <button class="wpsd-subtab<?= !$is_itinerant ? ' is-active' : '' ?>" data-subtab="recues">Reçues</button>
        <?php endif; ?>
    </div>
    <?php if ($is_itinerant): ?>
        <div class="wpsd-subpanel is-active" id="wpsd-subpanel-envoyees">
            <div class="wpsd-subtabs-secondary" style="margin-bottom:12px;">
                <button class="wpsd-subtab-secondary is-active" data-period="a_venir">À venir</button>
                <button class="wpsd-subtab-secondary" data-period="en_cours">En cours</button>
                <button class="wpsd-subtab-secondary" data-period="passees">Passées</button>
            </div>
            <div class="wpsd-period-panel is-active" id="wpsd_it_a_venir"></div>
            <div class="wpsd-period-panel" id="wpsd_it_en_cours"></div>
            <div class="wpsd-period-panel" id="wpsd_it_passees"></div>
        </div>
    <?php endif; ?>
    <?php if ($is_passeur || $show_hebergements): ?>
        <div class="wpsd-subpanel<?= !$is_itinerant ? ' is-active' : '' ?>" id="wpsd-subpanel-recues">
            <div class="wpsd-subtabs-secondary" style="margin-bottom:12px;">
                <button class="wpsd-subtab-secondary is-active" data-period="a_venir">À venir</button>
                <button class="wpsd-subtab-secondary" data-period="en_cours">En cours</button>
                <button class="wpsd-subtab-secondary" data-period="passees">Passées</button>
            </div>
            <div class="wpsd-period-panel is-active" id="wpsd_pr_a_venir"></div>
            <div class="wpsd-period-panel" id="wpsd_pr_en_cours"></div>
            <div class="wpsd-period-panel" id="wpsd_pr_passees"></div>
        </div>
    <?php endif; ?>
</div>