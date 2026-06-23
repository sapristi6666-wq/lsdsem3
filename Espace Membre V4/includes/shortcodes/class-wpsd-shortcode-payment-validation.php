<?php
if (!defined('ABSPATH')) exit;

class WPSD_Shortcode_PaymentValidation {
    public function render() {
        $session_id = $_GET['session_id'] ?? '';
        $role = $_GET['register_role'] ?? '';
        if (!$session_id) return '<div class="wpsd-card"><p>Session de paiement invalide.</p></div>';
        if ($role === 'passeur') return '<div class="wpsd-card"><h3>✅ Paiement confirmé !</h3><p>Votre compte a été créé avec le statut <strong>Passeur de savoir</strong>.</p><p>Votre compte est en attente de validation par un administrateur. Vous recevrez un email dès que votre accès sera activé.</p><p>En attendant, vous allez recevoir un email pour créer votre mot de passe.</p><p><a href="'.esc_url(home_url('/connexion/')).'" class="wpsd-btn wpsd-primary">Aller à la connexion</a></p></div>';
        return '<div class="wpsd-card"><h3>✅ Paiement confirmé !</h3><p>Votre compte a été créé avec succès.</p><p>Vous allez recevoir un email pour créer votre mot de passe.</p><p>Une fois votre mot de passe créé, vous pourrez vous connecter et accéder à votre dashboard.</p><p><a href="'.esc_url(home_url('/connexion/')).'" class="wpsd-btn wpsd-primary">Aller à la connexion</a></p></div>';
    }
}