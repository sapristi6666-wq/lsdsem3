<?php
if (!defined('ABSPATH')) exit;

// Fonction utilitaire pour générer un champ
function wpsd_company_field($key, $f, $val) {
    $req = !empty($f['required']) ? ' <span style="color:#c2a50f;">*</span>' : '';
    $base_style = 'width:100%;padding:11px 14px;border:1px solid #005247;border-radius:8px;background:#FBF1CA;font-family:\'Exo\',sans-serif;font-size:14px;color:#005247;box-sizing:border-box;outline:none;';
    $focus = 'onfocus="this.style.borderColor=\'#e0b912\';this.style.boxShadow=\'0 0 0 3px rgba(224,185,18,0.15)\'" onblur="this.style.borderColor=\'#005247\';this.style.boxShadow=\'none\'"';

    ob_start();
    ?>
    <label style="display:block;font-family:'Exo',sans-serif;font-weight:600;font-size:13px;color:#005247;margin-bottom:6px;">
        <?php echo esc_html($f['label']) . $req; ?>
    </label>
    <?php if (($f['type'] ?? 'text') === 'select'): ?>
        <select name="<?php echo esc_attr($key); ?>"
            style="<?php echo $base_style; ?>cursor:pointer;appearance:none;background-image:url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%23005247%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e');background-repeat:no-repeat;background-position:right 12px center;background-size:16px;padding-right:40px;"
            <?php echo $focus; ?>>
            <?php foreach ($f['options'] as $optVal => $optLabel): ?>
                <option value="<?php echo esc_attr($optVal); ?>" <?php selected($val, $optVal); ?>>
                    <?php echo esc_html($optLabel); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif (($f['type'] ?? 'text') === 'textarea'): ?>
        <textarea name="<?php echo esc_attr($key); ?>" rows="<?php echo $f['rows'] ?? 4; ?>"
            placeholder="<?php echo esc_attr($f['placeholder'] ?? ''); ?>"
            style="<?php echo $base_style; ?>resize:vertical;"
            <?php echo $focus; ?>><?php echo esc_textarea($val); ?></textarea>
    <?php else: ?>
        <input type="<?php echo $f['type']; ?>" name="<?php echo esc_attr($key); ?>"
            value="<?php echo esc_attr($val); ?>"
            placeholder="<?php echo esc_attr($f['placeholder'] ?? ''); ?>"
            <?php echo !empty($f['maxlength']) ? 'maxlength="' . $f['maxlength'] . '"' : ''; ?>
            style="<?php echo $base_style; ?>"
            <?php echo $focus; ?>>
    <?php endif;
    return ob_get_clean();
}

// Définition des lignes (pleine largeur) et des paires (2 colonnes)
$full_fields = [
    'inst_name'           => ['label' => 'Raison sociale', 'type' => 'text', 'placeholder' => 'Nom de l\'entreprise', 'required' => true],
    'inst_address_line1'  => ['label' => 'Adresse du siège', 'type' => 'text', 'placeholder' => 'Adresse complète', 'required' => true],
    'inst_address_line2'  => ['label' => 'Complément d\'adresse', 'type' => 'text', 'placeholder' => 'Bâtiment, étage...'],
];

$paired_fields = [
    [ // Ligne 1
        ['key' => 'inst_legal_form', 'field' => ['label' => 'Forme juridique', 'type' => 'select', 'options' => ['', 'SARL', 'SAS', 'SA', 'EURL', 'SCI', 'Association', 'Fondation', 'Autre']]],
        ['key' => 'inst_siret',      'field' => ['label' => 'SIRET', 'type' => 'text', 'placeholder' => '14 chiffres', 'maxlength' => 14]]
    ],
    [ // Ligne 2
        ['key' => 'inst_postal_code', 'field' => ['label' => 'Code postal', 'type' => 'text', 'placeholder' => 'Code postal']],
        ['key' => 'inst_city',        'field' => ['label' => 'Ville', 'type' => 'text', 'placeholder' => 'Ville', 'required' => true]]
    ],
    [ // Ligne 3
        ['key' => 'inst_country',       'field' => ['label' => 'Pays', 'type' => 'select', 'options' => ['FR' => 'France', 'BE' => 'Belgique', 'CH' => 'Suisse', 'LU' => 'Luxembourg', 'CA' => 'Canada', 'DE' => 'Allemagne', 'ES' => 'Espagne', 'IT' => 'Italie', 'UK' => 'Royaume-Uni', 'US' => 'États-Unis', 'other' => 'Autre']]],
        ['key' => 'inst_representative', 'field' => ['label' => 'Représenté par', 'type' => 'text', 'placeholder' => 'Nom du représentant']]
    ],
    [ // Ligne 4
        ['key' => 'inst_function', 'field' => ['label' => 'Fonction', 'type' => 'text', 'placeholder' => 'Fonction dans l\'entreprise']],
        ['key' => 'inst_email',    'field' => ['label' => 'E-mail (contact)', 'type' => 'email', 'placeholder' => 'Email de contact', 'required' => true]]
    ],
    [ // Ligne 5 : téléphone seul (prend toute la largeur)
        ['key' => 'inst_phone', 'field' => ['label' => 'Téléphone', 'type' => 'tel', 'placeholder' => 'Numéro de téléphone']]
    ]
];
?>

<div style="max-width: 720px; margin: 0 auto; font-family: 'Exo', sans-serif;">
    <div style="background: #FBF1CA; border: 1px solid rgba(224, 185, 18, 0.4); border-radius: 18px; padding: 35px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);">

        <h3 style="font-family: 'Exo', sans-serif; font-weight: 700; font-size: 18px; color: #005247; margin: 0 0 24px; padding-bottom: 16px; border-bottom: 1px solid rgba(0, 82, 71, 0.2);">
            <?php _e('Informations de l\'entreprise / organisme', 'wp-stripe-dashboard'); ?>
        </h3>

        <?php foreach ($full_fields as $key => $f): ?>
            <div style="margin-bottom: 18px;">
                <?php echo wpsd_company_field($key, $f, get_user_meta($user_id, $key, true)); ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($paired_fields as $row): ?>
            <div style="display:flex;gap:16px;margin-bottom:18px;">
                <?php foreach ($row as $item): ?>
                    <div style="flex:1;">
                        <?php echo wpsd_company_field($item['key'], $item['field'], get_user_meta($user_id, $item['key'], true)); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

    </div>
</div>