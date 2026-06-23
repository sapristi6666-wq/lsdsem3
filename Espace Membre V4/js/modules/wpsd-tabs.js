const WPSD_Tabs = (function() {
    let loadedTabs = {};
    const PRELOAD = ['demandes', 'adhesion'];

    function init() {
        document.querySelectorAll('.wpsd-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                document.querySelectorAll('.wpsd-tab').forEach(b => b.classList.remove('is-active'));
                document.querySelectorAll('.wpsd-panel').forEach(p => p.classList.remove('is-active'));
                btn.classList.add('is-active');
                const panel = document.getElementById('wpsd-panel-' + tabId);
                if (panel) panel.classList.add('is-active');
                WPSD_State.set('activeTab', tabId);
                loadTabIfNeeded(tabId);
            });
        });

        document.querySelectorAll('.wpsd-subtab').forEach(btn => {
            btn.addEventListener('click', () => {
                const parent = btn.closest('.wpsd-panel');
                parent.querySelectorAll('.wpsd-subtab').forEach(b => b.classList.remove('is-active'));
                parent.querySelectorAll('.wpsd-subpanel').forEach(p => p.classList.remove('is-active'));
                btn.classList.add('is-active');
                const panel = document.getElementById('wpsd-subpanel-' + btn.dataset.subtab);
                if (panel) panel.classList.add('is-active');
            });
        });

        document.querySelectorAll('.wpsd-moderation-subtab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.wpsd-moderation-subtab').forEach(b => b.classList.remove('is-active'));
                document.querySelectorAll('.wpsd-moderation-content').forEach(c => c.style.display = 'none');
                btn.classList.add('is-active');
                const target = document.getElementById('wpsd-moderation-' + btn.dataset.subtab);
                if (target) target.style.display = 'block';
            });
        });

        setTimeout(() => {
            PRELOAD.forEach(tab => loadTabIfNeeded(tab));
        }, 100);
    }

    function loadTabIfNeeded(tabId) {
        if (loadedTabs[tabId]) return;
        loadedTabs[tabId] = true;

        switch (tabId) {
            case 'carte':
                WPSD_Carte.init();
                setTimeout(function() { WPSD_Carte.load(1); }, 300);
                break;
            case 'parcours':
                WPSD_Parcours.init();
                break;
            case 'savoirs':
                WPSD_Savoirs.init();
                setTimeout(function() { WPSD_Savoirs.load(); }, 300);
                break;
            case 'hebergements':
                WPSD_Hebergements.init();
                setTimeout(function() { WPSD_Hebergements.load(); }, 300);
                break;
            case 'demandes':
                WPSD_Demandes.init();
                setTimeout(function() { WPSD_Demandes.load(); }, 300);
                break;
            case 'articles':
                WPSD_Articles.init();
                setTimeout(function() { if (typeof WPSD_Articles.load === 'function') WPSD_Articles.load(); }, 300);
                break;
            case 'famille':
                WPSD_Famille.init();
                setTimeout(function() { if (typeof WPSD_Famille.load === 'function') WPSD_Famille.load(); }, 300);
                break;
            case 'adhesion':
                WPSD_Adhesion.init();
                setTimeout(function() { if (typeof WPSD_Adhesion.load === 'function') WPSD_Adhesion.load(); }, 300);
                break;
            case 'moderation':
                WPSD_Moderation.init();
                setTimeout(function() { WPSD_Moderation.loadPending(); }, 300);
                break;
        }
    }

    return { init: init, loadTabIfNeeded: loadTabIfNeeded };
})();