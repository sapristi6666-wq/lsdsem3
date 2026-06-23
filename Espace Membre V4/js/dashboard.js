(function() {
    if (!window.WPSD) return;

    // Inject styles
    var style = document.createElement('style');
    style.textContent = '.wpsd-pin-marker{transition:transform 0.15s ease;}.wpsd-pin-marker:hover{transform:scale(1.3);}.wpsd-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;}.wpsd-badge-pending{background:#fef3c7;color:#92400e;}.wpsd-badge-approved{background:#dcfce7;color:#166534;}.wpsd-badge-rejected{background:#fee2e2;color:#991b1b;}.wpsd-badge-canceled{background:#f3f4f6;color:#374151;}.wpsd-badge-completed{background:#dbeafe;color:#1e40af;}.wpsd-pin-both{width:18px;height:18px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);background:linear-gradient(135deg,#005247 50%,#e0b912 50%);}.wpsd-pin-act{width:18px;height:18px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);background:#005247;}.wpsd-pin-acc{width:18px;height:18px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);background:#e0b912;}';
    document.head.appendChild(style);

    // Init core modules
    if (typeof WPSD_Tabs !== 'undefined') WPSD_Tabs.init();
    if (typeof WPSD_Modals !== 'undefined') WPSD_Modals.init();

    // Preload active tab on page load
    var activePanel = document.querySelector('.wpsd-panel.is-active');
    if (activePanel) {
        var tabId = activePanel.id.replace('wpsd-panel-', '');
        if (typeof WPSD_Tabs !== 'undefined') WPSD_Tabs.loadTabIfNeeded(tabId);
    }

    // Invalidate maps on tab switch
    document.querySelectorAll('.wpsd-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            setTimeout(function() {
                var m = document.querySelector('#wpsd_carte_map');
                var pm = document.querySelector('#wpsd_parcours_map');
                if (m && m._leaflet_id) {
                    var lm = Object.values(m).find(function(v) { return v._leaflet_id; });
                    if (lm) lm.invalidateSize();
                }
                if (pm && pm._leaflet_id) {
                    var lpm = Object.values(pm).find(function(v) { return v._leaflet_id; });
                    if (lpm) lpm.invalidateSize();
                }
            }, 200);
        });
    });
})();