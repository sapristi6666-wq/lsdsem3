<?php
if (!defined('ABSPATH')) exit;

class WPSD_Shortcode_Register {
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function render() {
        wp_enqueue_style('wpsd-frontend-forms');

        $out = '
        <div class="wpsd-register-wrapper">
            <div class="wpsd-register-card">
                <h3>Adhérer aux Sentiers des Savoirs</h3>
                
                <form method="post" class="wpsd-form wpsd-register-form" id="wpsd-register-form">
                    <div class="wpsd-form-row wpsd-two-columns">
                        <div class="wpsd-form-group">
                            <label>Nom <span class="wpsd-required">*</span></label>
                            <input type="text" name="nom" placeholder="Dupont" required>
                        </div>
                        <div class="wpsd-form-group">
                            <label>Prénom <span class="wpsd-required">*</span></label>
                            <input type="text" name="prenom" placeholder="Jean" required>
                        </div>
                    </div>
                    
                    <div class="wpsd-form-group">
                        <label>Email <span class="wpsd-required">*</span></label>
                        <input type="email" name="email" placeholder="jean.dupont@exemple.fr" required>
                    </div>
                    
                    <div class="wpsd-form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="phone" placeholder="06 12 34 56 78">
                    </div>
                    
                    <div class="wpsd-form-group">
                        <label>Vous souhaitez devenir : <span class="wpsd-required">*</span></label>
                        <div class="wpsd-categories">
                            <label class="wpsd-category wpsd-category-itinerant">
                                <input type="radio" name="categorie" value="itinerant" checked>
                                <span class="wpsd-category-title">' . WPSD_Data::role_label('itinerant') . '</span>
                            </label>
                            <label class="wpsd-category wpsd-category-passeur">
                                <input type="radio" name="categorie" value="passeur">
                                <span class="wpsd-category-title">' . WPSD_Data::role_label('passeur') . '</span>
                            </label>
                            <label class="wpsd-category wpsd-category-sympathisant">
                                <input type="radio" name="categorie" value="sympathisant">
                                <span class="wpsd-category-title">' . WPSD_Data::role_label('sympathisant') . '</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="wpsd-form-group">
                        <label>Type d\'abonnement <span class="wpsd-required">*</span></label>
                        <div class="wpsd-plans">
                            <label class="wpsd-plan">
                                <input type="radio" name="plan" value="member" checked>
                                <span class="wpsd-plan-title">Individuel</span>
                                <span class="wpsd-plan-price">50€/an</span>
                            </label>
                            <label class="wpsd-plan">
                                <input type="radio" name="plan" value="family">
                                <span class="wpsd-plan-title">Couple / Famille</span>
                                <span class="wpsd-plan-price">70€/an</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="wpsd-form-group">
                        <label class="wpsd-toggle">
                            <input type="checkbox" name="newsletter" value="1" checked>
                            <span class="wpsd-toggle-ui" aria-hidden="true"></span>
                            <span class="wpsd-toggle-label">Je souhaite m\'inscrire à la newsletter</span>
                        </label>
                    </div>
                    
                    <div id="wpsd-register-error" class="wpsd-alert wpsd-alert-error" style="display:none;"></div>
                    <div id="wpsd-register-loading" style="display:none; text-align:center; padding:10px;">Chargement...</div>
                    
                    <button type="submit" name="wpsd_register" value="1" class="wpsd-btn wpsd-btn-block wpsd-primary" id="wpsd-register-submit">Adhérer et payer</button>
                </form>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var form = document.getElementById("wpsd-register-form");
            if (!form) return;
            
            var submitBtn = document.getElementById("wpsd-register-submit");
            var errorDiv = document.getElementById("wpsd-register-error");
            var loadingDiv = document.getElementById("wpsd-register-loading");
            
            form.onsubmit = function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (errorDiv) errorDiv.style.display = "none";
                
                var nom = document.querySelector(\'input[name="nom"]\').value.trim();
                var prenom = document.querySelector(\'input[name="prenom"]\').value.trim();
                var email = document.querySelector(\'input[name="email"]\').value.trim();
                var phone = document.querySelector(\'input[name="phone"]\').value.trim();
                var categorie = document.querySelector(\'input[name="categorie"]:checked\').value;
                var plan = document.querySelector(\'input[name="plan"]:checked\').value;
                var newsletter = document.querySelector(\'input[name="newsletter"]\')?.checked ? 1 : 0;
                
                if (!nom || !prenom || !email) {
                    if (errorDiv) {
                        errorDiv.textContent = "Veuillez remplir tous les champs obligatoires (Nom, Prénom, Email)";
                        errorDiv.style.display = "block";
                    }
                    return false;
                }
                
                submitBtn.disabled = true;
                if (loadingDiv) loadingDiv.style.display = "block";
                
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "/wp-json/wpsd/v1/create-checkout-from-register", true);
                xhr.setRequestHeader("Content-Type", "application/json");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            var data = JSON.parse(xhr.responseText);
                            if (data && data.url) {
                                window.location.href = data.url;
                            } else {
                                if (errorDiv) {
                                    errorDiv.textContent = data.error || "Erreur lors de la création de la session";
                                    errorDiv.style.display = "block";
                                }
                                submitBtn.disabled = false;
                                if (loadingDiv) loadingDiv.style.display = "none";
                            }
                        } else if (xhr.status === 409) {
                            var data = JSON.parse(xhr.responseText);
                            if (errorDiv) {
                                errorDiv.textContent = data.error || "Cet email est déjà utilisé.";
                                errorDiv.style.display = "block";
                            }
                            submitBtn.disabled = false;
                            if (loadingDiv) loadingDiv.style.display = "none";
                        } else {
                            if (errorDiv) {
                                errorDiv.textContent = "Erreur de connexion. Veuillez réessayer.";
                                errorDiv.style.display = "block";
                            }
                            submitBtn.disabled = false;
                            if (loadingDiv) loadingDiv.style.display = "none";
                        }
                    }
                };
                xhr.send(JSON.stringify({
                    email: email,
                    plan: plan,
                    role: categorie,
                    nom: nom,
                    prenom: prenom,
                    phone: phone,
                    newsletter: newsletter
                }));
                
                return false;
            };
        });
        </script>
        ';
        
        return $out;
    }
}