<?php if (!defined('ABSPATH')) exit;
$is_famille = ($plan_key === 'family');
?>
<div class="wpsd-panel is-active" id="wpsd-panel-compte">
  <!-- SECTION 1 : Profil public -->
  <div class="wpsd-card">
    <div class="wpsd-card-header"><h3>Profil public</h3></div>
    <form id="wpsd-compte-profil-form" class="wpsd-form-grid">
      <div class="wpsd-form-group" style="grid-column:1/-1;">
        <label>Photo</label>
        <input type="file" id="wpsd_compte_photo" accept="image/*" style="width:100%;">
        <img id="wpsd_compte_photo_preview" style="margin-top:8px;max-height:120px;border-radius:8px;display:none;">
        <input type="hidden" id="wpsd_compte_photo_url">
      </div>
      <div class="wpsd-form-group" style="grid-column:1/-1;">
        <label for="wpsd_compte_bio">Bio</label>
        <textarea id="wpsd_compte_bio" rows="4" maxlength="300" placeholder="Parlez de vous..." style="width:100%;"></textarea>
        <span style="font-size:12px;color:var(--wpsd-muted);" id="wpsd_compte_bio_count">0 / 300</span>
      </div>
      <div class="wpsd-form-group"><label for="wpsd_compte_interets">Centres d'intérêt</label><input type="text" id="wpsd_compte_interets" placeholder="Nature, culture..." style="width:100%;"></div>
      <div class="wpsd-form-group"><label for="wpsd_compte_langues">Langues parlées</label><input type="text" id="wpsd_compte_langues" placeholder="Français, Anglais..." style="width:100%;"></div>
      <div class="wpsd-form-group" style="grid-column:1/-1;">
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="wpsd_compte_visible" style="width:auto;"> Afficher ma fiche sur la carte</label>
      </div>
      <div style="grid-column:1/-1;"><button type="submit" class="wpsd-btn wpsd-primary">Enregistrer le profil public</button><span id="wpsd_compte_profil_status" style="margin-left:12px;font-size:13px;"></span></div>
    </form>
  </div>

  <!-- SECTION 2 : Infos personnelles -->
  <div class="wpsd-card">
    <div class="wpsd-card-header"><h3>Informations personnelles</h3></div>
    <form id="wpsd-compte-infos-form" class="wpsd-form-grid">
      <div class="wpsd-form-group"><label for="wpsd_compte_nom">Nom</label><input type="text" id="wpsd_compte_nom" style="width:100%;"></div>
      <div class="wpsd-form-group"><label for="wpsd_compte_prenom">Prénom</label><input type="text" id="wpsd_compte_prenom" style="width:100%;"></div>
      <div class="wpsd-form-group"><label for="wpsd_compte_email">Email</label><input type="email" id="wpsd_compte_email" style="width:100%;"></div>
      <div class="wpsd-form-group"><label for="wpsd_compte_telephone">Téléphone</label><input type="text" id="wpsd_compte_telephone" style="width:100%;"></div>
      <div style="grid-column:1/-1;"><button type="submit" class="wpsd-btn wpsd-primary">Mettre à jour</button><span id="wpsd_compte_infos_status" style="margin-left:12px;font-size:13px;"></span></div>
    </form>
  </div>

  <!-- SECTION 3 : Famille -->
  <?php if ($is_famille): ?>
  <div class="wpsd-card" id="wpsd-compte-famille">
    <div class="wpsd-card-header"><h3>Ma famille</h3><button class="wpsd-btn wpsd-primary wpsd-btn-sm" id="wpsd_compte_famille_add">+ Ajouter</button></div>
    <div id="wpsd_compte_famille_list"></div>
  </div>
  <?php endif; ?>

  <!-- SECTION 4 : Adhésion -->
  <div class="wpsd-card">
    <div class="wpsd-card-header"><h3>Adhésion</h3></div>
    <div class="wpsd-dashboard-grid">
      <div>
        <div class="wpsd-subscription-info">
          <div class="wpsd-sub-row"><span>Plan</span><strong><?= $plan_h ?></strong></div>
          <div class="wpsd-sub-row"><span>Statut</span><strong class="<?= $is_active ? 'wpsd-text-success' : 'wpsd-text-danger' ?>"><?= esc_html($sub_status ?: 'Inactif') ?></strong></div>
          <div class="wpsd-sub-row"><span>Prochain renouvellement</span><strong><?= $period_end_date ?></strong></div>
        </div>
        <div class="wpsd-sub-actions">
          <button class="wpsd-btn wpsd-primary" id="wpsd_compte_open_portal">Gérer (Stripe)</button>
          <?php if ($plan_key !== 'family' && $plan_key !== 'none'): ?>
            <button class="wpsd-btn" id="wpsd_compte_change_plan" data-plan="family">Passer au plan Famille</button>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <h4 style="margin:0 0 8px;font-size:15px;">Historique des paiements</h4>
        <div id="wpsd_compte_payment_history"><p class="wpsd-hint">Chargement...</p></div>
      </div>
    </div>
  </div>
</div>
