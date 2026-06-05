<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel is-active" id="wpsd-panel-dashboard">
    <div class="wpsd-dashboard-grid">

    <?php if (!$has_bio): ?>
    <!-- Bloc bio manquante : message + carte action prioritaire -->
    <div class="wpsd-card" style="grid-column:1/-1;border-left:4px solid var(--wpsd-primary);">
            <h3>Bienvenue <?= esc_html($user->display_name) ?> !</h3>
      <p style="margin:8px 0 16px;">Votre compte a ete valide. Pour acceder a toutes les fonctionnalites, commencez par ecrire votre bio.</p>
      <a href="#" class="wpsd-btn wpsd-btn-primary" data-tab="profil" data-focus-bio="1">Ecrire ma bio</a>
        </div>
    <!-- Carte secondaire : infos role -->
        <div class="wpsd-card">
      <h3>Votre profil</h3>
      <p>Vous etes inscrit comme <strong><?= esc_html(implode(' + ', array_filter([$is_itinerant ? WPSD_Data::role_label('itinerant') : '', $is_passeur ? WPSD_Data::role_label('passeur') : '', ($show_hebergements && !$is_passeur) ? 'Hebergeur' : '', $is_sympathisant ? WPSD_Data::role_label('sympathisant') : '']))) ?></strong>.</p>
      <p>Les onglets <strong>Mon parcours</strong>, <strong>Mes savoirs</strong>, <strong>Hebergements</strong>, <strong>Demandes</strong> et <strong>Recits</strong> seront disponibles apres avoir rempli votre bio.</p>
        </div>
        <div class="wpsd-card">
      <h3>Explorez des maintenant</h3>
      <p>La <strong>Carte</strong> est accessible pour decouvrir les membres et leurs propositions.</p>
      <p>L'onglet <strong>Adhesion</strong> vous permet de gerer votre abonnement.</p>
        </div>

    <?php else: ?>
    <!-- Bio remplie : message de bienvenue + resume -->
    <div class="wpsd-card" style="grid-column:1/-1;">
      <h3>Bienvenue <?= esc_html($user->display_name) ?> !</h3>
      <p style="margin:8px 0 4px;">Vous etes connecte en tant que <strong><?= esc_html(implode(' + ', array_filter([$is_itinerant ? WPSD_Data::role_label('itinerant') : '', $is_passeur ? WPSD_Data::role_label('passeur') : '', ($show_hebergements && !$is_passeur) ? 'Hebergeur' : '', $is_sympathisant ? WPSD_Data::role_label('sympathisant') : '']))) ?></strong>.</p>
        </div>
    <?php if ($is_active): ?>
    <!-- Resume activite pour membres actifs -->
    <?php if ($parcours_en_cours > 0 || $demandes_en_attente > 0): ?>
    <div class="wpsd-card" style="grid-column:1/-1;">
      <h3>Resume de votre activite</h3>
      <div class="wpsd-stats-row" style="margin-top:12px;">
        <?php if ($is_itinerant): ?>
        <div class="wpsd-stat-card">
          <div class="wpsd-stat-number"><?= (int)$parcours_en_cours ?></div>
          <div class="wpsd-stat-label">Parcours en cours</div>
        </div>
        <?php endif; ?>
        <div class="wpsd-stat-card">
          <div class="wpsd-stat-number"><?= (int)$demandes_en_attente ?></div>
          <div class="wpsd-stat-label">Demandes en attente</div>
        </div>
        <div class="wpsd-stat-card">
          <div class="wpsd-stat-number"><?= (int)$total_reservations ?></div>
          <div class="wpsd-stat-label">Reservations totales</div>
        </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Actions recommandees -->
    <div class="wpsd-card">
      <h3>Decouvrir</h3>
      <p>Explorez la <strong>Carte</strong> pour trouver des activites et des hebergements.</p>
</div>
    <?php if ($is_itinerant): ?>
    <div class="wpsd-card">
      <h3>Planifier</h3>
      <p>Utilisez <strong>Mon parcours</strong> pour organiser votre voyage etape par etape.</p>
    </div>
    <?php endif; ?>
    <?php if ($is_passeur): ?>
    <div class="wpsd-card">
      <h3>Partager</h3>
      <p>Proposez vos savoirs dans <strong>Mes savoirs</strong> et definissez vos disponibilites.</p>
    </div>
    <?php endif; ?>
    <?php if ($show_hebergements && !$is_passeur): ?>
    <div class="wpsd-card">
      <h3>Heberger</h3>
      <p>Ajoutez vos hebergements et indiquez vos disponibilites.</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
