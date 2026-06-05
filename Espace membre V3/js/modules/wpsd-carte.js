const WPSD_Carte = (function() {
    let map = null, markers = null, currentPage = 1, totalPages = 1, loaded = false;

    function init() {
        ensureMap();
        WPSD_State.on('activeTab', tab => {
            if (tab === 'carte') {
                setTimeout(() => {
                    ensureMap();
                    load();
                }, 300);
            }
        });
    }

    function ensureMap() {
    const el = WPSD_Utils.$('wpsd_carte_map');
    if (!el) return;
    if (!map) {
        map = L.map('wpsd_carte_map').setView([46.6, 2.5], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        markers = L.layerGroup().addTo(map);
    }
    setTimeout(() => map.invalidateSize(), 150);
    }

    function load(page) {
        if (page === undefined) page = 1;
        if (!markers) return;
        if (page === 1) markers.clearLayers();
        currentPage = page;
        loaded = true;

        WPSD_API.get('/map-points?per_page=50&page=' + page).then(function(r) {
            if (!r.ok || !r.items) return;
            totalPages = r.total_pages || 1;
            for (var i = 0; i < r.items.length; i++) {
                addMarker(r.items[i]);
            }
            addLoadMoreButton();
        });
    }

    function getPinClass(point) {
        if (point.kind === 'both') return 'wpsd-pin-both';
        if (point.kind === 'activity') return 'wpsd-pin-act';
        return 'wpsd-pin-acc';
    }

    function addMarker(point) {
        var pinClass = getPinClass(point);
        var typeLabel = WPSD_Utils.kindLabel(point.kind);
        var icon = L.divIcon({ html: '<div class="' + pinClass + '"></div>', iconSize: [22,22], iconAnchor: [11,11], popupAnchor: [0,-10] });

        var popupHtml = '<div style="min-width:220px;">';
        popupHtml += '<strong>' + WPSD_Utils.escapeHtml(point.title) + '</strong>';
        popupHtml += '<div style="font-size:12px;">' + typeLabel + '</div>';

        if (point.kind === 'both') {
            popupHtml += '<div style="font-size:12px;color:#8B5CF6;font-weight:bold;margin-top:4px;">🏠 Hébergement inclus (' + (point.acc_capacity || 0) + ' places)</div>';
        }

        popupHtml += '<div style="font-size:11px;">' + WPSD_Utils.escapeHtml(point.provider_first_name || '') + ' ' + WPSD_Utils.escapeHtml(point.provider_last_name || '') + ' · ' + WPSD_Utils.escapeHtml(point.city || '') + '</div>';
        popupHtml += '</div>';

        L.marker([point.lat, point.lng], { icon: icon }).addTo(markers).bindPopup(popupHtml);
    }

    function addLoadMoreButton() {
        var oldBtn = document.querySelector('.wpsd-load-more-btn');
        if (oldBtn) oldBtn.remove();
        if (currentPage >= totalPages) return;

        var btn = L.control({ position: 'topright' });
        btn.onAdd = function() {
            var div = L.DomUtil.create('button', 'wpsd-load-more-btn');
            div.innerHTML = '+ Plus de points (' + currentPage + '/' + totalPages + ')';
            div.style.cssText = 'background:#005247;color:#FBF1CA;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-family:Exo,sans-serif;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.2);';
            L.DomEvent.disableClickPropagation(div);
            div.addEventListener('click', function() {
                load(currentPage + 1);
                btn.remove();
            });
            return div;
        };
        btn.addTo(map);
    }

    return { init: init, load: load };
})();