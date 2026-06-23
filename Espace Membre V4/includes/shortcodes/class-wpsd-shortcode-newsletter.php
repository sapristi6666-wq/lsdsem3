<?php
if (!defined('ABSPATH')) exit;

class WPSD_Shortcode_Newsletter {

    public function __construct() {
        add_action('wp_ajax_wpsd_newsletter_subscribe', [$this, 'ajax_subscribe']);
        add_action('wp_ajax_nopriv_wpsd_newsletter_subscribe', [$this, 'ajax_subscribe']);
    }

    public function render($atts = []) {
        wp_enqueue_style('wpsd-frontend-forms');
        $atts = shortcode_atts(['list_id' => '', 'placeholder' => 'Votre email', 'button' => "S'inscrire"], $atts, 'wpsd_newsletter');
        $list_id = $atts['list_id'] !== '' ? (int)$atts['list_id'] : 0;
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('wpsd_newsletter');
        $form_id = 'wpsd_newsletter_' . wp_generate_password(8, false, false);
        ob_start();
        ?>
        <form id="<?= esc_attr($form_id) ?>" class="wpsd-newsletter" method="post" action="#">
            <input type="email" name="email" required placeholder="<?= esc_attr($atts['placeholder']) ?>">
            <button type="submit"><?= esc_html($atts['button']) ?></button>
            <input type="hidden" name="nonce" value="<?= esc_attr($nonce) ?>">
            <input type="hidden" name="list_id" value="<?= (int)$list_id ?>">
            <span class="wpsd-newsletter-msg"></span>
        </form>
        <script>
        (function(){var f=document.getElementById(<?= json_encode($form_id) ?>);if(!f)return;var m=f.querySelector('.wpsd-newsletter-msg');f.addEventListener('submit',async function(e){e.preventDefault();if(m)m.textContent='...';var d=new FormData(f);d.append('action','wpsd_newsletter_subscribe');try{var r=await fetch(<?= json_encode($ajax_url) ?>,{method:'POST',body:d,credentials:'same-origin'});var j=await r.json();if(j&&j.success){if(m)m.textContent='Merci !';f.reset()}else{var t=(j&&j.data&&j.data.message)?j.data.message:'Erreur.';if(m)m.textContent=t}}catch(err){if(m)m.textContent='Erreur réseau.'}});})();
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_subscribe() {
        if (!wp_verify_nonce(sanitize_text_field($_POST['nonce'] ?? ''), 'wpsd_newsletter')) wp_send_json_error(['message' => 'Sécurité: nonce invalide.'], 403);
        $email = sanitize_email($_POST['email'] ?? '');
        if (!is_email($email)) wp_send_json_error(['message' => 'Merci de renseigner un email valide.'], 400);
        $list_id = isset($_POST['list_id']) && (int)$_POST['list_id'] > 0 ? (int)$_POST['list_id'] : null;
        if (!class_exists('WPSD_Brevo')) wp_send_json_error(['message' => 'Brevo: classe manquante.'], 500);
        $res = WPSD_Brevo::subscribe($email, [], $list_id);
        if (is_wp_error($res)) wp_send_json_error(['message' => $res->get_error_message()], 500);
        wp_send_json_success(['message' => 'Vous êtes inscrit(e) à la newsletter.']);
    }
}