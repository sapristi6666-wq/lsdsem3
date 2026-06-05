<?php
if (!defined('ABSPATH')) exit;

function wpsd_render_modal($prefix, $label, $saveFn, $fields) {
    ob_start();
    ?>
    <div class="wpsd-modal" id="wpsd_<?= $prefix ?>_modal" aria-hidden="true">
        <div class="wpsd-modal-inner">
            <h4 id="wpsd_<?= $prefix ?>_modal_title">Nouveau <?= esc_html($label) ?></h4>
            <input type="hidden" id="wpsd_<?= $prefix ?>_id">
            <?php foreach ($fields as $f): ?>
                <div class="wpsd-form-group">
                    <?php if ($f['type'] === 'checkbox'): ?>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                            <input type="checkbox" id="wpsd_<?= $prefix ?>_<?= $f['id'] ?>">
                            <?= esc_html($f['label']) ?>
                        </label>
                    <?php else: ?>
                        <label><?= esc_html($f['label']) ?></label>
                        <?php if ($f['type'] === 'textarea'): ?>
                            <textarea id="wpsd_<?= $prefix ?>_<?= $f['id'] ?>" rows="4"></textarea>
                        <?php else: ?>
                            <input type="<?= $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : 'text') ?>" id="wpsd_<?= $prefix ?>_<?= $f['id'] ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="wpsd-form-group">
                <label>Photo</label>
                <input id="wpsd_<?= $prefix ?>_photo_file" type="file" accept="image/*">
                <img id="wpsd_<?= $prefix ?>_photo_preview" style="max-width:100%;display:none;margin-top:8px;">
                <input type="hidden" id="wpsd_<?= $prefix ?>_photo_id">
            </div>
            <div class="wpsd-row">
                <button class="wpsd-btn wpsd-primary" type="button" onclick="<?= $saveFn ?>">Enregistrer</button>
                <button class="wpsd-btn" type="button" onclick="WPSD_Modals.close('<?= $prefix ?>')">Annuler</button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<?= wpsd_render_modal('act', 'activité', 'WPSD_Savoirs.handleSave()', [
    ['id' => 'title', 'label' => 'Titre', 'type' => 'text'],
    ['id' => 'desc', 'label' => 'Description', 'type' => 'textarea'],
    ['id' => 'address_line1', 'label' => 'Adresse', 'type' => 'text'],
    ['id' => 'postal_code', 'label' => 'Code postal', 'type' => 'text'],
    ['id' => 'city', 'label' => 'Ville', 'type' => 'text'],
    ['id' => 'has_accommodation', 'label' => 'Je propose aussi un hébergement sur place', 'type' => 'checkbox'],
    ['id' => 'acc_capacity', 'label' => 'Capacité d\'hébergement (personnes)', 'type' => 'number'],
]) ?>

<?= wpsd_render_modal('acc', 'hébergement', 'WPSD_Hebergements.handleSave()', [
    ['id' => 'title', 'label' => 'Titre', 'type' => 'text'],
    ['id' => 'desc', 'label' => 'Description', 'type' => 'textarea'],
    ['id' => 'adults', 'label' => 'Adultes max', 'type' => 'number'],
    ['id' => 'children', 'label' => 'Enfants max', 'type' => 'number'],
    ['id' => 'address_line1', 'label' => 'Adresse', 'type' => 'text'],
    ['id' => 'postal_code', 'label' => 'Code postal', 'type' => 'text'],
    ['id' => 'city', 'label' => 'Ville', 'type' => 'text'],
]) ?>

<?= wpsd_render_modal('art', 'récit', 'WPSD_Articles.handleSave()', [
    ['id' => 'title', 'label' => 'Titre', 'type' => 'text'],
    ['id' => 'content', 'label' => 'Contenu', 'type' => 'textarea'],
]) ?>

<?= wpsd_render_modal('fam', 'membre de la famille', 'WPSD_Famille.handleSave()', [
    ['id' => 'first_name', 'label' => 'Prénom', 'type' => 'text'],
    ['id' => 'last_name', 'label' => 'Nom', 'type' => 'text'],
    ['id' => 'email', 'label' => 'Email', 'type' => 'text'],
    ['id' => 'phone', 'label' => 'Téléphone', 'type' => 'text'],
    ['id' => 'birth_date', 'label' => 'Date de naissance', 'type' => 'date'],
    ['id' => 'address_line1', 'label' => 'Adresse', 'type' => 'text'],
    ['id' => 'postal_code', 'label' => 'Code postal', 'type' => 'text'],
    ['id' => 'city', 'label' => 'Ville', 'type' => 'text'],
    ['id' => 'bio_text', 'label' => 'Notes (allergies, régime...)', 'type' => 'textarea'],
]) ?>