<?php
if (!defined('ABSPATH')) exit;

class WPSD_Shortcode_Contact {

    public function __construct() {
        add_action('wp_ajax_wpsd_contact_submit', [$this, 'ajax_submit']);
        add_action('wp_ajax_nopriv_wpsd_contact_submit', [$this, 'ajax_submit']);
        add_action('admin_menu', [$this, 'settings_menu']);
        add_action('admin_init', [$this, 'settings_init']);
    }

    public function render() {
        wp_enqueue_style('wpsd-frontend-forms');
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('wpsd_contact');
        $form_id = 'wpsd_contact_' . wp_generate_password(8, false, false);
        $subjects_raw = get_option('wpsd_contact_subjects', "Demande d'information\nQuestion sur l'adhésion\nProblème technique\nAutre");
        $subjects = array_filter(array_map('trim', explode("\n", $subjects_raw)));
        ob_start();
        ?>
        <div class="wpsd-contact-wrapper">
            <div class="wpsd-contact-card">
                <h3>Contactez-nous</h3>
                <form id="<?= esc_attr($form_id) ?>" class="wpsd-contact-form">
                    <div class="wpsd-contact-message" style="display:none;"></div>
                    <div class="wpsd-contact-row">
                        <div class="wpsd-contact-group"><label>Nom <span class="wpsd-required">*</span></label><input type="text" name="lastname" placeholder="Dupont" required></div>
                        <div class="wpsd-contact-group"><label>Prénom <span class="wpsd-required">*</span></label><input type="text" name="firstname" placeholder="Jean" required></div>
                    </div>
                    <div class="wpsd-contact-group"><label>Email <span class="wpsd-required">*</span></label><input type="email" name="email" placeholder="jean.dupont@exemple.fr" required></div>
                    <div class="wpsd-contact-group"><label>Téléphone</label><input type="tel" name="phone" placeholder="06 12 34 56 78"></div>
                    <div class="wpsd-contact-group"><label>Sujet <span class="wpsd-required">*</span></label>
                        <select name="subject" required>
                            <option value="">-- Choisissez un sujet --</option>
                            <?php foreach ($subjects as $s): ?><option value="<?= esc_attr($s) ?>"><?= esc_html($s) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="wpsd-contact-group"><label>Message <span class="wpsd-required">*</span></label><textarea name="message" rows="5" placeholder="Votre message..." required></textarea></div>
                    <div class="wpsd-contact-group"><label style="display:flex;align-items:center;gap:10px;"><input type="checkbox" name="rgpd" required><span style="font-weight:normal;">J'accepte que mes données soient utilisées pour traiter ma demande.</span></label></div>
                    <input type="hidden" name="nonce" value="<?= esc_attr($nonce) ?>">
                    <button type="submit" class="wpsd-contact-btn">Envoyer le message</button>
                </form>
            </div>
        </div>
        <script>
        (function(){var f=document.getElementById(<?= json_encode($form_id) ?>);if(!f)return;var m=f.querySelector('.wpsd-contact-message'),b=f.querySelector('button[type="submit"]');f.addEventListener('submit',async function(e){e.preventDefault();var r=f.querySelector('input[name="rgpd"]');if(r&&!r.checked){m.style.display='block';m.className='wpsd-contact-error';m.textContent='Vous devez accepter la politique de confidentialité.';return}m.style.display='none';b.disabled=true;b.textContent='Envoi en cours...';var d=new FormData(f);d.append('action','wpsd_contact_submit');try{var res=await fetch(<?= json_encode($ajax_url) ?>,{method:'POST',body:d,credentials:'same-origin'});var j=await res.json();if(j.success){m.style.display='block';m.className='wpsd-contact-success';m.textContent=j.data.message;f.reset()}else{m.style.display='block';m.className='wpsd-contact-error';m.textContent=j.data.message||'Une erreur est survenue.'}}catch(err){m.style.display='block';m.className='wpsd-contact-error';m.textContent='Erreur réseau.'}finally{b.disabled=false;b.textContent='Envoyer le message'}});})();
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_submit() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpsd_contact')) wp_send_json_error(['message' => 'Erreur de sécurité.']);
        $lastname = sanitize_text_field($_POST['lastname'] ?? ''); $firstname = sanitize_text_field($_POST['firstname'] ?? '');
        $email = sanitize_email($_POST['email'] ?? ''); $phone = sanitize_text_field($_POST['phone'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? ''); $message = sanitize_textarea_field($_POST['message'] ?? '');
        if (empty($lastname) || empty($firstname) || empty($email) || empty($subject) || empty($message)) wp_send_json_error(['message' => 'Veuillez remplir tous les champs obligatoires.']);
        if (!is_email($email)) wp_send_json_error(['message' => 'Adresse email invalide.']);
        $recipient = get_option('wpsd_contact_recipient', get_option('admin_email'));
        $email_subject = get_option('wpsd_contact_email_subject', 'Contact site') . ' : ' . $subject;
        $template = get_option('wpsd_contact_email_template', $this->default_template());
        $html = str_replace(['{{lastname}}','{{firstname}}','{{email}}','{{phone}}','{{subject}}','{{message}}','{{date}}','{{site_name}}'], [esc_html($lastname),esc_html($firstname),esc_html($email),esc_html($phone?:'—'),esc_html($subject),nl2br(esc_html($message)),date_i18n('d/m/Y à H:i'),esc_html(get_bloginfo('name'))], $template);
        $html = '<div style="font-family:Exo,Arial,sans-serif;">' . wpautop($html) . '</div>';
        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: '.$firstname.' '.$lastname.' <'.$email.'>', 'Reply-To: '.$email];
        if (wp_mail($recipient, $email_subject, $html, $headers)) wp_send_json_success(['message' => get_option('wpsd_contact_success_message', 'Votre message a été envoyé avec succès !')]);
        else wp_send_json_error(['message' => 'Erreur lors de l\'envoi.']);
    }

    private function default_template() {
        return '<h2 style="color:#005247;">Nouveau message de contact</h2><p><strong>Date :</strong> {{date}}</p><table style="border-collapse:collapse;width:100%;"><tr><td style="padding:8px;"><strong>Nom :</strong></td><td style="padding:8px;">{{lastname}}</td></tr><tr><td style="padding:8px;"><strong>Prénom :</strong></td><td style="padding:8px;">{{firstname}}</td></tr><tr><td style="padding:8px;"><strong>Email :</strong></td><td style="padding:8px;">{{email}}</td></tr><tr><td style="padding:8px;"><strong>Téléphone :</strong></td><td style="padding:8px;">{{phone}}</td></tr><tr><td style="padding:8px;"><strong>Sujet :</strong></td><td style="padding:8px;">{{subject}}</td></tr></table><h3 style="color:#005247;margin-top:20px;">Message :</h3><p style="background:#f5f7f5;padding:16px;border-radius:8px;">{{message}}</p><p style="color:#5a6e68;font-size:12px;margin-top:30px;">Message envoyé depuis le formulaire de contact de {{site_name}}.</p>';
    }

    public function settings_menu() { add_options_page('Formulaire de contact','Formulaire de contact','manage_options','wpsd-contact-settings',[$this,'settings_page']); }
    public function settings_init() { foreach(['wpsd_contact_subjects','wpsd_contact_recipient','wpsd_contact_email_subject','wpsd_contact_email_template','wpsd_contact_success_message'] as $o) register_setting('wpsd_contact_settings',$o); }

    public function settings_page() {
        ?><div class="wrap"><h1>Réglages du formulaire de contact</h1>
        <form method="post" action="options.php"><?php settings_fields('wpsd_contact_settings'); ?>
        <table class="form-table">
            <tr><th>Email de destination</th><td><input type="email" name="wpsd_contact_recipient" value="<?= esc_attr(get_option('wpsd_contact_recipient',get_option('admin_email'))) ?>" class="regular-text"></td></tr>
            <tr><th>Préfixe du sujet</th><td><input type="text" name="wpsd_contact_email_subject" value="<?= esc_attr(get_option('wpsd_contact_email_subject','Contact site')) ?>" class="regular-text"></td></tr>
            <tr><th>Message de succès</th><td><input type="text" name="wpsd_contact_success_message" value="<?= esc_attr(get_option('wpsd_contact_success_message','Votre message a été envoyé avec succès !')) ?>" class="large-text"></td></tr>
            <tr><th>Liste des sujets</th><td><textarea name="wpsd_contact_subjects" rows="6" class="large-text"><?= esc_textarea(get_option('wpsd_contact_subjects',"Demande d'information\nQuestion sur l'adhésion\nProblème technique\nAutre")) ?></textarea></td></tr>
            <tr><th>Template de l'email</th><td><textarea name="wpsd_contact_email_template" rows="15" class="large-text"><?= esc_textarea(get_option('wpsd_contact_email_template',$this->default_template())) ?></textarea></td></tr>
        </table><?php submit_button(); ?></form></div><?php
    }
}