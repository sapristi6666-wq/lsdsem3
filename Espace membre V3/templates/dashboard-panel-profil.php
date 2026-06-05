<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel" id="wpsd-panel-profil">
  <div class="wpsd-card">
    <div class="wpsd-card-header">
      <h3>Mon profil</h3>
    </div>
    <form id="wpsd-profil-form" class="wpsd-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div class="wpsd-form-section" style="grid-column:1/-1;">
        <h4>Bio</h4>
        <div class="wpsd-form-group">
          <label for="wpsd_profil_bio">Votre bio <span style="color:var(--wpsd-danger);">*</span></label>
          <textarea id="wpsd_profil_bio" rows="4" maxlength="300" placeholder="Parlez de vous, de vos motivations..." style="width:100%;"></textarea>
          <span style="font-size:12px;color:var(--wpsd-muted);" id="wpsd_profil_bio_count">0 / 300</span>
        </div>
        <div class="wpsd-form-group">
          <label for="wpsd_profil_photo">Photo (URL)</label>
          <input type="url" id="wpsd_profil_photo" placeholder="https://..." style="width:100%;">
          <img id="wpsd_profil_photo_preview" style="margin-top:8px;max-height:120px;border-radius:8px;display:none;">
        </div>
      </div>
      <div class="wpsd-form-section" style="grid-column:1/-1;">
        <h4>Centres d'interet</h4>
        <div class="wpsd-form-group">
          <label for="wpsd_profil_interets">Centres d'interet</label>
          <input type="text" id="wpsd_profil_interets" placeholder="Nature, culture, cuisine..." style="width:100%;">
        </div>
        <div class="wpsd-form-group">
          <label for="wpsd_profil_langues">Langues parlees</label>
          <input type="text" id="wpsd_profil_langues" placeholder="Francais, Anglais, Espagnol..." style="width:100%;">
        </div>
      </div>
      <div class="wpsd-form-section" style="grid-column:1/-1;">
        <h4>Visibilite sur la carte</h4>
        <div class="wpsd-form-group" style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" id="wpsd_profil_visible" style="width:auto;">
          <label for="wpsd_profil_visible" style="margin:0;">Afficher ma fiche sur la carte</label>
        </div>
      </div>
      <div style="grid-column:1/-1;">
        <button type="submit" class="wpsd-btn wpsd-btn-primary">Enregistrer le profil</button>
        <span id="wpsd_profil_status" style="margin-left:12px;font-size:13px;"></span>
      </div>
    </form>
  </div>
</div>
