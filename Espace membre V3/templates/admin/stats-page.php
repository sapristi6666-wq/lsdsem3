<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap wpsd-stats-wrap">
    <h1>Statistiques</h1>
    <div class="wpsd-stats-filters">
        <select id="wpsd_stats_mois">
            <?php foreach (range(1,12) as $m): ?>
                <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= date_i18n('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="wpsd_stats_annee">
            <?php foreach (range(date('Y'), date('Y')-5) as $a): ?>
                <option value="<?= $a ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-primary" id="wpsd_stats_apply">Appliquer</button>
    </div>
    <div class="wpsd-stats-cards">
        <div class="wpsd-stat-card"><div class="wpsd-stat-number" id="wpsd_stat_membres">—</div><div class="wpsd-stat-label">Membres actifs</div></div>
        <div class="wpsd-stat-card"><div class="wpsd-stat-number" id="wpsd_stat_parcours">—</div><div class="wpsd-stat-label">Parcours réalisés</div></div>
        <div class="wpsd-stat-card"><div class="wpsd-stat-number" id="wpsd_stat_adhesions">—</div><div class="wpsd-stat-label">Montant adhésions (€)</div></div>
        <div class="wpsd-stat-card"><div class="wpsd-stat-number" id="wpsd_stat_passeurs">—</div><div class="wpsd-stat-label">Reversé passeurs (€)</div></div>
    </div>
    <div class="wpsd-stats-charts">
        <div class="wpsd-chart-box"><h2>Adhésions mensuelles</h2><canvas id="wpsd_chart_adhesions" width="100%" height="300"></canvas></div>
        <div class="wpsd-chart-box"><h2>Parcours mensuels</h2><canvas id="wpsd_chart_parcours" width="100%" height="300"></canvas></div>
    </div>
    <div class="wpsd-stats-table">
        <h2>Top 5 passeurs les plus actifs</h2>
        <table class="wp-list-table widefat striped">
            <thead><tr><th>Passeur</th><th>Prestations</th></tr></thead>
            <tbody id="wpsd_top_passeurs"></tbody>
        </table>
    </div>
</div>
