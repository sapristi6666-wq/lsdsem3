<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel is-active" id="wpsd-panel-dashboard">
    <div class="wpsd-dashboard-grid">
        <!-- Carte de bienvenue -->
        <div class="wpsd-card wpsd-welcome-card" style="grid-column:1/-1;">
            <h3>Bienvenue <?= esc_html($user->display_name) ?> !</h3>
            <p style="font-size:15px;margin:8px 0 16px;"><?= $accueil_summary ?></p>
        </div>

        <?php if ($is_itinerant): ?>
        <!-- Guide Itinérant -->
        <div class="wpsd-card">
            <h3>1. Préparez votre parcours</h3>
            <p>Dans l'onglet <strong>Mon parcours</strong>, choisissez une date de début et ajoutez des étapes. Cliquez sur la carte pour trouver des activités et des hébergements.</p>
        </div>
        <div class="wpsd-card">
            <h3>2. Envoyez vos demandes</h3>
            <p>Une fois vos étapes remplies, cliquez sur <strong>"Envoyer toutes les demandes"</strong>. Les passeurs recevront vos demandes et pourront les accepter.</p>
        </div>
        <div class="wpsd-card">
            <h3>3. Suivez vos réservations</h3>
            <p>Dans l'onglet <strong>Demandes</strong>, vous pouvez suivre l'état de vos demandes et confirmer votre venue une fois acceptée.</p>
        </div>
        <?php endif; ?>

        <?php if ($is_passeur): ?>
        <!-- Guide Passeur -->
        <div class="wpsd-card">
            <h3>1. Proposez vos savoirs</h3>
            <p>Dans l'onglet <strong>Mes savoirs</strong>, cliquez sur "Nouvelle proposition" pour ajoutez les savoirs que vous souhaitez transmettre. Vous pouvez aussi ajouter au même moment votre hébergement.</p>
        </div>
        <div class="wpsd-card">
            <h3>2. Définissez vos disponibilités</h3>
            <p>Pour chaque savoir, ajoutez des créneaux de disponibilité via le calendrier. Glissez votre curseur sur l'ensemble des dates où vous êtes disponible puis indiquez le nombre de personnes pouvant être accueilli sur la période sélectionnée.</p>
        </div>
        <div class="wpsd-card">
            <h3>3. Gérez les demandes</h3>
            <p>Lorsqu'un itinérant vous enverra une demande, vous recevrez un mail. vous pourrez aller dans l'onglet <strong>Mes demandes</strong> pour accepter ou refuser.</p></p>
        </div>
        <?php endif; ?>

        <?php if ($show_hebergements && !$is_passeur): ?>
        <!-- Guide Hébergeur -->
        <div class="wpsd-card">
            <h3>1. Proposez vos hébergements</h3>
            <p>Dans l'onglet <strong>Hébergements</strong>, ajoutez les lieux que vous pouvez mettre à disposition.</p>
        </div>
        <div class="wpsd-card">
            <h3>2. Définissez vos disponibilités</h3>
            <p>Ajoutez des créneaux via le calendrier pour indiquer quand votre hébergement est disponible.</p>
        </div>
        <?php endif; ?>

        <!-- Carte des membres -->
        <div class="wpsd-card">
            <h3>Explorez la carte</h3>
            <p>Dans l'onglet <strong>Carte</strong>, découvrez tous les membres et leurs propositions.</p>
        </div>
    </div>
</div>