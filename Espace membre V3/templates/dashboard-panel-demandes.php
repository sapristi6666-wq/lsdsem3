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
            <div class="wpsd-card"><div id="wpsd_it_reservations"></div></div>
        </div>
    <?php endif; ?>
        <?php if ($is_passeur || $show_hebergements): ?>
        <div class="wpsd-subpanel<?= !$is_itinerant ? ' is-active' : '' ?>" id="wpsd-subpanel-recues">
            <div class="wpsd-card">
                <h4 style="margin:0 0 10px;font-size:15px;">Arrivées à venir</h4>
                <div id="wpsd_pr_arrivees" style="margin-bottom:16px;"><p class="wpsd-hint">Chargement...</p></div>
            </div>
            <div class="wpsd-card">
                <div class="wpsd-card-header"><h3>Toutes les demandes</h3></div>
                <div class="wpsd-demandes-filters">
                    <button class="wpsd-btn wpsd-btn-sm is-active" data-filter="all">Toutes</button>
                    <button class="wpsd-btn wpsd-btn-sm" data-filter="pending">En attente</button>
                    <button class="wpsd-btn wpsd-btn-sm" data-filter="approved">Acceptées</button>
                    <button class="wpsd-btn wpsd-btn-sm" data-filter="completed">Terminées</button>
                </div>
                <div id="wpsd_pr_pending"></div>
                <div id="wpsd_pr_ongoing"></div>
                <div id="wpsd_pr_history"></div>
            </div>
        </div>
    <?php endif; ?>
</div>