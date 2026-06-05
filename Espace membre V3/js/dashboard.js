(function() {
    if (!window.WPSD) return;

    // Inject styles
    const style = document.createElement('style');
    style.textContent = '.wpsd-pin-marker{transition:transform 0.15s ease;}.wpsd-pin-marker:hover{transform:scale(1.3);}.wpsd-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;}.wpsd-badge-pending{background:#fef3c7;color:#92400e;}.wpsd-badge-approved{background:#dcfce7;color:#166534;}.wpsd-badge-rejected{background:#fee2e2;color:#991b1b;}.wpsd-badge-canceled{background:#f3f4f6;color:#374151;}.wpsd-badge-completed{background:#dbeafe;color:#1e40af;}.wpsd-badge-awaiting_confirmation{background:#dbeafe;color:#1e40af;}.wpsd-pin-both{width:18px;height:18px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);background:linear-gradient(135deg,#005247 50%,#e0b912 50%);}.wpsd-pin-act{width:18px;height:18px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);background:#005247;}.wpsd-pin-acc{width:18px;height:18px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);background:#e0b912;}';
    document.head.appendChild(style);

    // Init core modules
    WPSD_Tabs.init();
    WPSD_Modals.init();

    // === Gestion deblocage onglets apres bio ===
    var lockedTabs = ['parcours', 'savoirs', 'hebergements', 'demandes', 'articles'];

    function revealConditionalTabs() {
        var nav = document.querySelector('.wpsd-tabs');
        if (!nav) return;
        lockedTabs.forEach(function(id) {
            var btn = nav.querySelector('.wpsd-tab[data-tab="' + id + '"]');
            if (btn) btn.style.display = '';
        });
        // Afficher les panels conditionnels (ils existent deja dans le HTML)
        document.querySelectorAll('.wpsd-panel.wpsd-panel-conditional').forEach(function(p) {
            p.style.display = '';
        });
    }

    function hideConditionalTabs() {
        var nav = document.querySelector('.wpsd-tabs');
        if (!nav) return;
        lockedTabs.forEach(function(id) {
            var btn = nav.querySelector('.wpsd-tab[data-tab="' + id + '"]');
            if (btn) btn.style.display = 'none';
        });
    }

    // Au chargement : masquer les onglets conditionnels si pas de bio
    if (!window.WPSD.hasBio) {
        hideConditionalTabs();
    }

    // Ecouter l'evenement bio-saved pour reveler les onglets
    document.addEventListener('wpsd-bio-saved', function() {
        revealConditionalTabs();
        // Recharger l'accueil pour afficher le nouveau contenu
        var accueilPanel = document.getElementById('wpsd-panel-dashboard');
        if (accueilPanel) {
            // Recharger la page simplement pour que PHP regenere le contenu avec has_bio=true
            location.reload();
        }
    });

    // Clic sur le bouton "Ecrire ma bio" dans l'accueil (data-tab="profil")
    document.addEventListener('click', function(e) {
        var target = e.target.closest('[data-tab]');
        if (target && target.dataset.tab === 'profil') {
            e.preventDefault();
            var tabBtn = document.querySelector('.wpsd-tab[data-tab="profil"]');
            if (tabBtn) tabBtn.click();
            // Focus sur le champ bio apres un court delai
            if (target.dataset.focusBio) {
                setTimeout(function() {
                    var bioField = document.getElementById('wpsd_profil_bio');
                    if (bioField) bioField.focus();
                }, 500);
            }
        }
    });

    // Preload active tab on page load
    const activePanel = document.querySelector('.wpsd-panel.is-active');
    if (activePanel) {
        const tabId = activePanel.id.replace('wpsd-panel-', '');
        WPSD_Tabs.loadTabIfNeeded(tabId);
    }

    // Invalidate maps on tab switch
    document.querySelectorAll('.wpsd-tab').forEach(tab => {
        tab.addEventListener('click', () => setTimeout(() => {
            const m = document.querySelector('#wpsd_carte_map');
            const pm = document.querySelector('#wpsd_parcours_map');
            if (m && m._leaflet_id) { const lm = Object.values(m).find(v => v._leaflet_id); if (lm) lm.invalidateSize(); }
            if (pm && pm._leaflet_id) { const lpm = Object.values(pm).find(v => v._leaflet_id); if (lpm) lpm.invalidateSize(); }
        }, 200));
    });
})();