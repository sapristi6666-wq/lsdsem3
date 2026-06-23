<?php
if (!defined('ABSPATH')) exit;

// Fonction utilitaire pour générer un champ (identique à celle de l'entreprise)
function wpsd_profile_field($key, $f, $val) {
    $req = !empty($f['required']) ? ' <span style="color:#c2a50f;">*</span>' : '';
    $base_style = 'width:100%;padding:11px 14px;border:1px solid #005247;border-radius:8px;background:#FBF1CA;font-family:\'Exo\',sans-serif;font-size:14px;color:#005247;box-sizing:border-box;outline:none;';
    $focus = 'onfocus="this.style.borderColor=\'#e0b912\';this.style.boxShadow=\'0 0 0 3px rgba(224,185,18,0.15)\'" onblur="this.style.borderColor=\'#005247\';this.style.boxShadow=\'none\'"';

    ob_start();
    ?>
    <label style="display:block;font-family:'Exo',sans-serif;font-weight:600;font-size:13px;color:#005247;margin-bottom:6px;">
        <?php echo esc_html($f['label']) . $req; ?>
    </label>
    <?php if (($f['type'] ?? 'text') === 'textarea'): ?>
        <textarea name="<?php echo esc_attr($key); ?>" rows="<?php echo $f['rows'] ?? 4; ?>"
            placeholder="<?php echo esc_attr($f['placeholder'] ?? ''); ?>"
            style="<?php echo $base_style; ?>resize:vertical;"
            <?php echo $focus; ?>><?php echo esc_textarea($val); ?></textarea>
    <?php else: ?>
        <input type="<?php echo $f['type']; ?>" name="<?php echo esc_attr($key); ?>"
            value="<?php echo esc_attr($val); ?>"
            placeholder="<?php echo esc_attr($f['placeholder'] ?? ''); ?>"
            style="<?php echo $base_style; ?>"
            <?php echo $focus; ?>>
    <?php endif;
    return ob_get_clean();
}
?>

<!-- Photo + Identité (reste indépendant) -->
<div class="wpsd-profile-section">
    <div class="wpsd-profile-header">
        <div class="wpsd-profile-photo">
            <div class="wpsd-photo-preview" id="wpsd-photo-preview">
                <?php if ($photo_url): ?>
                    <img src="<?php echo esc_url($photo_url); ?>" alt="Photo de profil">
                <?php else: ?>
                    <span class="wpsd-photo-placeholder"><?php _e('Photo', 'wp-stripe-dashboard'); ?></span>
                <?php endif; ?>
            </div>
            <input type="file" id="wpsd-photo-input" accept="image/*" style="display:none;">
            <div class="wpsd-photo-actions">
                <button type="button" class="wpsd-btn wpsd-btn-sm" id="wpsd-upload-photo">
                    <?php _e('Changer la photo', 'wp-stripe-dashboard'); ?>
                </button>
                <?php if ($photo_url): ?>
                    <button type="button" class="wpsd-btn wpsd-btn-sm wpsd-btn-remove" id="wpsd-remove-photo">
                        <?php _e('Supprimer', 'wp-stripe-dashboard'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="wpsd-profile-infos">
            <div class="wpsd-field">
                <label><?php _e('Nom complet', 'wp-stripe-dashboard'); ?></label>
                <input type="text" value="<?php echo esc_attr($display_name); ?>" disabled>
            </div>
            <div class="wpsd-field">
                <label><?php _e('Email', 'wp-stripe-dashboard'); ?></label>
                <input type="email" value="<?php echo esc_attr($user->user_email); ?>" disabled>
            </div>
        </div>
    </div>
</div>

<!-- Tous les autres champs regroupés dans une seule carte -->
<div class="wpsd-profile-section">
    <h3><?php _e('Informations personnelles', 'wp-stripe-dashboard'); ?></h3>

    <?php
    // Champs pleine largeur
    $full_fields = [
        'wpsd_bio'    => ['label' => 'Bio', 'type' => 'textarea', 'rows' => 5, 'placeholder' => 'Décrivez-vous, votre parcours, ce qui vous passionne...'],
        'wpsd_skills' => ['label' => 'Compétences / Savoir-faire / Savoir-être', 'type' => 'textarea', 'rows' => 3, 'placeholder' => 'Ex: tissage, forge, apiculture...'],
    ];
    foreach ($full_fields as $key => $f):
        $val = get_user_meta($user_id, $key, true);
    ?>
        <div style="margin-bottom: 18px;">
            <?php echo wpsd_profile_field($key, $f, $val); ?>
        </div>
    <?php endforeach; ?>

    <?php
    // Lignes en deux colonnes
    $paired_rows = [
        [
            ['key' => 'wpsd_city',   'field' => ['label' => 'Ville', 'type' => 'text', 'placeholder' => 'Votre ville']],
            ['key' => 'wpsd_region', 'field' => ['label' => 'Région', 'type' => 'text', 'placeholder' => 'Votre région']]
        ],
        [
            ['key' => 'wpsd_interests', 'field' => ['label' => 'Centres d\'intérêt', 'type' => 'text', 'placeholder' => 'Ex: permaculture, menuiserie, chant...']],
            ['key' => 'wpsd_languages', 'field' => ['label' => 'Langues parlées', 'type' => 'text', 'placeholder' => 'Ex: français, anglais, espagnol...']]
        ],
        [
            ['key' => 'wpsd_website',   'field' => ['label' => 'Site web', 'type' => 'url', 'placeholder' => 'https://...']],
            ['key' => 'wpsd_instagram', 'field' => ['label' => 'Instagram', 'type' => 'text', 'placeholder' => '@...']]
        ],
        [
            ['key' => 'wpsd_other_link', 'field' => ['label' => 'Autre réseau', 'type' => 'text', 'placeholder' => 'https://...']]
            // seul sur sa ligne
        ]
    ];

    foreach ($paired_rows as $row):
    ?>
        <div style="display:flex;gap:16px;margin-bottom:18px;">
            <?php foreach ($row as $item):
                $val = get_user_meta($user_id, $item['key'], true);
            ?>
                <div style="flex:1;">
                    <?php echo wpsd_profile_field($item['key'], $item['field'], $val); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>