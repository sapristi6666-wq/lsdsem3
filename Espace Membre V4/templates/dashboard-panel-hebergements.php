<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel" id="wpsd-panel-hebergements">
    <div class="wpsd-card">
        <div class="wpsd-card-header">
            <h3>Mes hébergements</h3>
            <button class="wpsd-btn wpsd-primary" onclick="WPSD_Hebergements.openNew()">+ Nouvel hébergement</button>
        </div>
        <div id="wpsd_accommodations_list"></div>
    </div>

    <div class="wpsd-card" id="wpsd-acc-slots-card" style="display:none;">
        <div class="wpsd-card-header">
            <h3>Disponibilités : <span id="wpsd-acc-slots-title"></span></h3>
            <button class="wpsd-btn wpsd-btn-sm" id="wpsd-acc-slots-close">✕ Fermer</button>
        </div>

        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
            <div class="wpsd-form-group" style="margin-bottom:0;">
                <label>Date de début</label>
                <input type="date" id="wpsd-acc-slot-start">
            </div>
            <div class="wpsd-form-group" style="margin-bottom:0;">
                <label>Date de fin</label>
                <input type="date" id="wpsd-acc-slot-end">
            </div>
            <div class="wpsd-form-group" style="margin-bottom:0;">
                <label>Unités dispo.</label>
                <input type="number" id="wpsd-acc-slot-units" min="1" value="1" style="width:70px;">
            </div>
            <button class="wpsd-btn wpsd-primary" id="wpsd-acc-slot-add">Ajouter</button>
            <button class="wpsd-btn wpsd-btn-sm" id="wpsd-acc-slot-block" style="background:#fef3c7;color:#92400e;border-color:#f59e0b;">Bloquer des dates</button>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <button class="wpsd-btn wpsd-btn-sm" id="wpsd-acc-cal-prev">← Mois précédent</button>
            <strong id="wpsd-acc-cal-month" style="font-size:15px;color:var(--wpsd-secondary);"></strong>
            <button class="wpsd-btn wpsd-btn-sm" id="wpsd-acc-cal-next">Mois suivant →</button>
        </div>

        <div style="display:flex;gap:6px;margin-bottom:12px;font-size:12px;">
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:14px;height:14px;border-radius:3px;background:#dcfce7;border:1px solid #86efac;"></span> Disponible</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:14px;height:14px;border-radius:3px;background:#fee2e2;border:1px solid #fca5a5;"></span> Bloqué</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:14px;height:14px;border-radius:3px;background:#fef3c7;border:1px solid #fcd34d;"></span> Réservé</span>
        </div>

        <div id="wpsd-acc-calendar" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;"></div>
    </div>
</div>