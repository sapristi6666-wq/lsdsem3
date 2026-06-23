<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$user = wp_get_current_user();

$first_name = get_user_meta($user_id, 'first_name', true);
$last_name  = get_user_meta($user_id, 'last_name', true);

if (empty($first_name) || empty($last_name)) {
    global $wpdb;
    $pending = $wpdb->get_row($wpdb->prepare(
        "SELECT nom, prenom FROM " . WPSD_DB::table_pending_registrations() . " WHERE email = %s AND status = 'approved' ORDER BY id DESC LIMIT 1",
        $user->user_email
    ));
    if ($pending) {
        if (empty($first_name)) $first_name = $pending->prenom;
        if (empty($last_name))  $last_name  = $pending->nom;
    }
}

$display_name = trim($first_name . ' ' . $last_name) ?: $user->display_name;
$photo_url = get_user_meta($user_id, 'wpsd_profile_photo_url', true);
?>

<div class="wpsd-profile-container" id="wpsd-panel-profile">

    <!-- Barre de sous-onglets -->
    <div class="wpsd-profile-subtabs">
        <button class="wpsd-subtab-btn active" data-subtab="profile"><?php _e('Mon Profil', 'wp-stripe-dashboard'); ?></button>
        <button class="wpsd-subtab-btn" data-subtab="family"><?php _e('Ma Famille', 'wp-stripe-dashboard'); ?></button>
        <button class="wpsd-subtab-btn" data-subtab="company"><?php _e('Mon Entreprise', 'wp-stripe-dashboard'); ?></button>
        <button class="wpsd-subtab-btn" data-subtab="subscription"><?php _e('Mon Adhésion', 'wp-stripe-dashboard'); ?></button>
    </div>

    <!-- Contenu des sous-onglets -->
    <div class="wpsd-profile-content">

        <!-- ========== MON PROFIL ========== -->
        <div class="wpsd-subtab-panel active" id="wpsd-subtab-profile">
            <!-- Photo + Identité -->
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

            <?php
            $fields = [
                'wpsd_bio'        => ['label' => 'Bio', 'type' => 'textarea', 'rows' => 5, 'placeholder' => 'Décrivez-vous, votre parcours, ce qui vous passionne...'],
                'wpsd_interests'  => ['label' => 'Centres d\'intérêt', 'type' => 'text', 'placeholder' => 'Ex: permaculture, menuiserie, chant...'],
                'wpsd_skills'     => ['label' => 'Compétences / Savoir-faire / Savoir-être', 'type' => 'textarea', 'rows' => 3, 'placeholder' => 'Ex: tissage, forge, apiculture...'],
                'wpsd_city'       => ['label' => 'Ville', 'type' => 'text', 'placeholder' => 'Votre ville'],
                'wpsd_region'     => ['label' => 'Région', 'type' => 'text', 'placeholder' => 'Votre région'],
                'wpsd_languages'  => ['label' => 'Langues parlées', 'type' => 'text', 'placeholder' => 'Ex: français, anglais, espagnol...'],
                'wpsd_website'    => ['label' => 'Site web', 'type' => 'url', 'placeholder' => 'https://...'],
                'wpsd_instagram'  => ['label' => 'Instagram', 'type' => 'text', 'placeholder' => '@...'],
                'wpsd_other_link' => ['label' => 'Autre réseau', 'type' => 'text', 'placeholder' => 'https://...'],
            ];

            foreach ($fields as $key => $f):
                $val = get_user_meta($user_id, $key, true);
            ?>
            <div class="wpsd-profile-section">
                <h3><?php echo esc_html($f['label']); ?></h3>
                <div class="wpsd-field">
                    <?php if ($f['type'] === 'textarea'): ?>
                        <textarea name="<?php echo esc_attr($key); ?>" rows="<?php echo $f['rows']; ?>" placeholder="<?php echo esc_attr($f['placeholder']); ?>"><?php echo esc_textarea($val); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo $f['type']; ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>" placeholder="<?php echo esc_attr($f['placeholder']); ?>">
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ========== MA FAMILLE ========== -->
        <div class="wpsd-subtab-panel" id="wpsd-subtab-family">
            <div id="wpsd-family-list">
                <p class="wpsd-hint">Chargement...</p>
            </div>
            <button class="wpsd-btn wpsd-primary" onclick="WPSD_Modals.open('fam', null)">
                <?php _e('Ajouter un membre', 'wp-stripe-dashboard'); ?>
            </button>
        </div>

        <!-- ========== MON ENTREPRISE ========== -->
        <div class="wpsd-subtab-panel" id="wpsd-subtab-company">
            <?php
            $inst_fields = [
                'inst_name'           => ['label' => 'Nom de l\'établissement', 'type' => 'text'],
                'inst_email'          => ['label' => 'Email professionnel', 'type' => 'email'],
                'inst_phone'          => ['label' => 'Téléphone', 'type' => 'text'],
                'inst_address_line1'  => ['label' => 'Adresse', 'type' => 'text'],
                'inst_address_line2'  => ['label' => 'Complément d\'adresse', 'type' => 'text'],
                'inst_postal_code'    => ['label' => 'Code postal', 'type' => 'text'],
                'inst_city'           => ['label' => 'Ville', 'type' => 'text'],
                'inst_country'        => ['label' => 'Pays', 'type' => 'text'],
                'inst_description'    => ['label' => 'Description', 'type' => 'textarea', 'rows' => 4],
            ];
            foreach ($inst_fields as $key => $f):
                $val = get_user_meta($user_id, $key, true);
            ?>
            <div class="wpsd-profile-section">
                <h3><?php echo esc_html($f['label']); ?></h3>
                <div class="wpsd-field">
                    <?php if (($f['type'] ?? 'text') === 'textarea'): ?>
                        <textarea name="<?php echo esc_attr($key); ?>" rows="<?php echo $f['rows'] ?? 3; ?>"><?php echo esc_textarea($val); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo $f['type']; ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>">
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ========== MON ADHÉSION ========== -->
        <div class="wpsd-subtab-panel" id="wpsd-subtab-subscription">
            <div id="wpsd-subscription-info">
                <p class="wpsd-hint">Chargement...</p>
            </div>
        </div>

    </div>
</div>