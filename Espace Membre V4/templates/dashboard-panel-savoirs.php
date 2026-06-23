<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel" id="wpsd-panel-savoirs">
    <div class="wpsd-card">
        <div class="wpsd-card-header">
            <h3>Mes propositions de transmission</h3>
            <button class="wpsd-btn wpsd-primary" onclick="WPSD_Savoirs.openNew()">+ Nouvelle proposition</button>
        </div>
        <div id="wpsd_activities_list"></div>
    </div>

    <div class="wpsd-card" id="wpsd-slots-card" style="display:none;">
        <div class="wpsd-card-header">
            <h3>Disponibilités : <span id="wpsd-slots-activity-title"></span></h3>
        </div>
        <div class="wpsd-form-row">
            <div class="wpsd-form-group">
                <label>Date de début</label>
                <input type="date" id="wpsd-slot-date-start">
            </div>
            <div class="wpsd-form-group">
                <label>Date de fin</label>
                <input type="date" id="wpsd-slot-date-end">
            </div>
            <div class="wpsd-form-group">
                <label>Places disponibles par jour</label>
                <input type="number" id="wpsd-slot-capacity" min="1" value="1" style="width:80px;">
            </div>
            <div class="wpsd-form-group" style="align-self:flex-end;">
                <button class="wpsd-btn wpsd-primary" id="wpsd-slot-add-btn">Ajouter la période</button>
            </div>
        </div>
        <div id="wpsd-slots-calendar" style="min-height:250px;margin-top:16px;"></div>
    </div>
</div>