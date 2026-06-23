<?php if (!defined('ABSPATH')) exit; ?>

<!-- Modale activité -->
<div class="wpsd-modal" id="wpsd_act_modal" aria-hidden="true">
    <div class="wpsd-modal-inner">
        <h4 id="wpsd_act_modal_title">Nouveau activité</h4>
        <input type="hidden" id="wpsd_act_id">
        <div class="wpsd-form-group">
            <label>Titre</label>
            <input type="text" id="wpsd_act_title">
        </div>
        <div class="wpsd-form-group">
            <label>Image de l'activité</label>
            <input type="file" id="wpsd_act_photo_file" accept="image/*">
            <img id="wpsd_act_photo_preview" style="max-width:100%;display:none;margin-top:8px;">
            <input type="hidden" id="wpsd_act_photo_id">
        </div>
        <div class="wpsd-form-group">
            <label>Description</label>
            <textarea id="wpsd_act_desc" rows="4"></textarea>
        </div>
        <div class="wpsd-form-group">
            <label>Adresse</label>
            <input type="text" id="wpsd_act_address_line1">
        </div>
        <div class="wpsd-form-group">
            <label>Code postal</label>
            <input type="text" id="wpsd_act_postal_code">
        </div>
        <div class="wpsd-form-group">
            <label>Ville</label>
            <input type="text" id="wpsd_act_city">
        </div>
        
        <!-- ✅ MODIFIÉ : Checkbox hébergement (gardée) -->
        <div class="wpsd-accommodation-toggle">
            <div class="wpsd-form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="wpsd_act_has_accommodation">
                    <span>Je propose aussi un hébergement sur place</span>
                </label>
            </div>
        </div>

        <!-- ✅ MODIFIÉ : Conteneur hébergement VIDÉ (sans le champ capacité) -->
        <div id="wpsd_act_accommodation_fields" class="wpsd-accommodation-fields" style="display:none;">
            <!-- Champ capacité SUPPRIMÉ -->
        </div>

        <div class="wpsd-row">
            <button class="wpsd-btn wpsd-primary" type="button" onclick="WPSD_Savoirs.handleSave()">Enregistrer</button>
            <button class="wpsd-btn" type="button" onclick="WPSD_Modals.close('act')">Annuler</button>
        </div>
    </div>
</div>

<!-- ✅ SUPPRIMÉ : Le script inline qui gérait la capacité -->
<!-- Plus besoin puisque le JS principal le fait déjà proprement -->

<!-- Modale hébergement -->
<div class="wpsd-modal" id="wpsd_acc_modal" aria-hidden="true">
    <div class="wpsd-modal-inner">
        <h4 id="wpsd_acc_modal_title">Nouvel hébergement</h4>
        <input type="hidden" id="wpsd_acc_id">
        <div class="wpsd-form-group">
            <label>Titre</label>
            <input type="text" id="wpsd_acc_title">
        </div>
        <div class="wpsd-form-group">
            <label>Image de l'hébergement</label>
            <input type="file" id="wpsd_acc_photo_file" accept="image/*">
            <img id="wpsd_acc_photo_preview" style="max-width:100%;display:none;margin-top:8px;">
            <input type="hidden" id="wpsd_acc_photo_id">
        </div>
        <div class="wpsd-form-group">
            <label>Description</label>
            <textarea id="wpsd_acc_desc" rows="4"></textarea>
        </div>
        <div class="wpsd-form-group">
            <label>Adultes max</label>
            <input type="number" id="wpsd_acc_adults" min="0">
        </div>
        <div class="wpsd-form-group">
            <label>Enfants max</label>
            <input type="number" id="wpsd_acc_children" min="0">
        </div>
        <div class="wpsd-form-group">
            <label>Adresse</label>
            <input type="text" id="wpsd_acc_address_line1">
        </div>
        <div class="wpsd-form-group">
            <label>Code postal</label>
            <input type="text" id="wpsd_acc_postal_code">
        </div>
        <div class="wpsd-form-group">
            <label>Ville</label>
            <input type="text" id="wpsd_acc_city">
        </div>
        <div class="wpsd-row">
            <button class="wpsd-btn wpsd-primary" type="button" onclick="WPSD_Hebergements.handleSave()">Enregistrer</button>
            <button class="wpsd-btn" type="button" onclick="WPSD_Modals.close('acc')">Annuler</button>
        </div>
    </div>
</div>

<!-- Modale article / récit -->
<div class="wpsd-modal" id="wpsd_art_modal" aria-hidden="true">
    <div class="wpsd-modal-inner">
        <h4 id="wpsd_art_modal_title">Nouveau récit</h4>
        <input type="hidden" id="wpsd_art_id">
        <div class="wpsd-form-group">
            <label>Titre</label>
            <input type="text" id="wpsd_art_title">
        </div>
        <div class="wpsd-form-group">
            <label>Contenu</label>
            <textarea id="wpsd_art_content" rows="4"></textarea>
        </div>
        <div class="wpsd-form-group">
            <label>Photo</label>
            <input id="wpsd_art_photo_file" type="file" accept="image/*">
            <img id="wpsd_art_photo_preview" style="max-width:100%;display:none;margin-top:8px;">
            <input type="hidden" id="wpsd_art_photo_id">
        </div>
        <div class="wpsd-row">
            <button class="wpsd-btn wpsd-primary" type="button" onclick="WPSD_Articles.handleSave()">Enregistrer</button>
            <button class="wpsd-btn" type="button" onclick="WPSD_Modals.close('art')">Annuler</button>
        </div>
    </div>
</div>

<!-- Modale famille -->
<div class="wpsd-modal" id="wpsd_fam_modal" aria-hidden="true">
    <div class="wpsd-modal-inner">
        <h4 id="wpsd_fam_modal_title">Nouveau membre de la famille</h4>
        <input type="hidden" id="wpsd_fam_id">
        <div class="wpsd-form-group">
            <label>Prénom</label>
            <input type="text" id="wpsd_fam_first_name">
        </div>
        <div class="wpsd-form-group">
            <label>Nom</label>
            <input type="text" id="wpsd_fam_last_name">
        </div>
        <div class="wpsd-form-group">
            <label>Email</label>
            <input type="text" id="wpsd_fam_email">
        </div>
        <div class="wpsd-form-group">
            <label>Téléphone</label>
            <input type="text" id="wpsd_fam_phone">
        </div>
        <div class="wpsd-form-group">
            <label>Date de naissance</label>
            <input type="date" id="wpsd_fam_birth_date">
        </div>
        <div class="wpsd-form-group">
            <label>Adresse</label>
            <input type="text" id="wpsd_fam_address_line1">
        </div>
        <div class="wpsd-form-group">
            <label>Code postal</label>
            <input type="text" id="wpsd_fam_postal_code">
        </div>
        <div class="wpsd-form-group">
            <label>Ville</label>
            <input type="text" id="wpsd_fam_city">
        </div>
        <div class="wpsd-form-group">
            <label>Notes (allergies, régime...)</label>
            <textarea id="wpsd_fam_bio_text" rows="4"></textarea>
        </div>
        <div class="wpsd-form-group">
            <label>Photo</label>
            <input id="wpsd_fam_photo_file" type="file" accept="image/*">
            <img id="wpsd_fam_photo_preview" style="max-width:100%;display:none;margin-top:8px;">
            <input type="hidden" id="wpsd_fam_photo_id">
        </div>
        <div class="wpsd-row">
            <button class="wpsd-btn wpsd-primary" type="button" onclick="WPSD_Famille.handleSave()">Enregistrer</button>
            <button class="wpsd-btn" type="button" onclick="WPSD_Modals.close('fam')">Annuler</button>
        </div>
    </div>
</div>