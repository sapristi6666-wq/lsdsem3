(function() {
    let chartAdh = null, chartPar = null;

    function loadStats(mois, annee) {
        fetch(WPSD_Stats.rest_url + '?mois=' + mois + '&annee=' + annee, {
            headers: { 'X-WP-Nonce': WPSD_Stats.nonce }
        })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) return;
            document.getElementById('wpsd_stat_membres').textContent = d.total_membres;
            document.getElementById('wpsd_stat_parcours').textContent = d.parcours_mois;
            document.getElementById('wpsd_stat_adhesions').textContent = d.montant_adhesions.toFixed(2);
            document.getElementById('wpsd_stat_passeurs').textContent = d.montant_passeurs.toFixed(2);

            if (chartAdh) chartAdh.destroy();
            chartAdh = new Chart(document.getElementById('wpsd_chart_adhesions'), {
                type: 'bar',
                data: {
                    labels: d.evol_adhesions.map(e => e.mois),
                    datasets: [{
                        label: 'Adhésions (€)',
                        data: d.evol_adhesions.map(e => e.montant),
                        backgroundColor: '#005247',
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            if (chartPar) chartPar.destroy();
            chartPar = new Chart(document.getElementById('wpsd_chart_parcours'), {
                type: 'line',
                data: {
                    labels: d.evol_parcours.map(e => e.mois),
                    datasets: [{
                        label: 'Parcours',
                        data: d.evol_parcours.map(e => e.nb),
                        borderColor: '#e0b912',
                        backgroundColor: 'rgba(224,185,18,0.1)',
                        fill: true,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            const tbody = document.getElementById('wpsd_top_passeurs');
            tbody.innerHTML = d.top_passeurs.map(p =>
                '<tr><td>' + p.display_name + '</td><td>' + p.nb + '</td></tr>'
            ).join('');
        })
        .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadStats(document.getElementById('wpsd_stats_mois').value, document.getElementById('wpsd_stats_annee').value);
        document.getElementById('wpsd_stats_apply').addEventListener('click', function() {
            loadStats(document.getElementById('wpsd_stats_mois').value, document.getElementById('wpsd_stats_annee').value);
        });
    });
})();
