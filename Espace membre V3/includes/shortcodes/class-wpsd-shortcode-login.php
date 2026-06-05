<?php
if (!defined('ABSPATH')) exit;

class WPSD_Shortcode_Login {
    public function render() {
        wp_enqueue_style('wpsd-frontend-forms');

        if (is_user_logged_in()) {
            wp_redirect(home_url('/mon-compte/'));
            exit;
        }

        $redirect = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/mon-compte/');

        ob_start();
        ?>
        <div class="wpsd-register-wrapper wpsd-login-form">
            <div class="wpsd-register-card">
                <h3>Se connecter à l'espace membre</h3>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'failed') echo '<div class="wpsd-alert wpsd-alert-error">Email ou mot de passe incorrect.</div>'; ?>
                <?php if (isset($_GET['loggedout']) && $_GET['loggedout'] === 'true') echo '<div class="wpsd-alert wpsd-alert-info">Vous êtes déconnecté.</div>'; ?>
                <form method="post" action="<?= esc_url(site_url('wp-login.php')) ?>" class="wpsd-form">
                    <div class="wpsd-form-group">
                        <label>Email <span class="wpsd-required">*</span></label>
                        <input type="email" name="log" placeholder="Votre adresse email" required>
                    </div>
                    <div class="wpsd-form-group">
                        <label>Mot de passe <span class="wpsd-required">*</span></label>
                        <input type="password" name="pwd" placeholder="Votre mot de passe" required>
                    </div>
                    <div class="wpsd-form-group">
                        <label class="wpsd-checkbox-label">
                            <input type="checkbox" name="rememberme" value="forever">
                            <span class="wpsd-checkbox-text">Se souvenir de moi</span>
                        </label>
                    </div>
                    <input type="hidden" name="redirect_to" value="<?= esc_attr($redirect) ?>">
                    <button type="submit" name="wp-submit" value="1" class="wpsd-btn wpsd-btn-block wpsd-btn-primary">Se connecter</button>
                    <p class="wpsd-hint" style="margin-top:15px;text-align:center;">
                        <a class="wpsd-lost-password" href="<?= esc_url(wp_lostpassword_url()) ?>">Mot de passe oublié ?</a>
                    </p>
                    <div class="wpsd-register-link">
                        Si vous n'avez pas de compte, vous pouvez <a href="<?= esc_url(home_url('/adhesions/')) ?>">adhérer ici</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}