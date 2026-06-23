const WPSD_Carte = (function() {
    let map = null, markers = null, loaded = false;

    function init() {
        ensureMap();
        WPSD_State.on('activeTab', function(tab) {
            if (tab === 'carte') {
                setTimeout(function() {
                    ensureMap();
                    load();
                }, 300);
            }
        });
    }

    function ensureMap() {
        var el = WPSD_Utils.$('wpsd_carte_map');
        if (!el) return;
        if (!map) {
            map = L.map('wpsd_carte_map').setView([46.6, 2.5], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            markers = L.layerGroup().addTo(map);
        }
        setTimeout(function() { map.invalidateSize(); }, 150);
    }

    function load() {
        if (!markers) return;
        markers.clearLayers();
        loaded = true;

        fetch(WPSD.restBase.replace('/wpsd/v1', '/wpsd/v2') + '/disponibilites', {
            headers: { 'X-WP-Nonce': WPSD.nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.length) return;
            for (var i = 0; i < data.length; i++) {
                addMarker(data[i]);
            }
        });
    }

    function getKind(point) {
        return point.kind || point.type_display || point.type || '';
    }

    function getPinClass(point) {
        var kind = getKind(point);
        if (kind === 'both' || kind === 'mixed') return 'wpsd-pin-both';
        if (kind === 'activity') return 'wpsd-pin-act';
        return 'wpsd-pin-acc';
    }

    function buildPopupCard(p, typeLabel) {
        var card = p.member_card || {};
        var photo = p.photo_url || '';
        var name = WPSD_Utils.escapeHtml(p.owner_name || '');
        var city = WPSD_Utils.escapeHtml(p.city || '');
        var title = WPSD_Utils.escapeHtml(p.title || '');
        var bio = card.bio ? WPSD_Utils.escapeHtml(card.bio).substring(0, 80) : '';
        var userId = p.owner_id;

        var typeClass = typeLabel === 'Activité' ? 'popup-type-activity' : 'popup-type-accommodation';

        return `<div class="wpsd-popup-card">
            <div class="wpsd-popup-card-img" style="background-image:url('${photo}')">
                ${!photo ? '<div class="wpsd-popup-card-img-placeholder"><span>📷</span></div>' : ''}
            </div>
            <div class="wpsd-popup-card-body">
                <span class="wpsd-popup-card-type ${typeClass}">${typeLabel}</span>
                <h4 class="wpsd-popup-card-title">${title}</h4>
                <a href="#" class="wpsd-carte-member-link" data-user-id="${userId}" style="text-decoration:none;color:#005247;font-weight:600;">${name}</a>
                ${city ? `<p class="wpsd-popup-card-city">${city}</p>` : ''}
                ${bio ? `<p class="wpsd-popup-card-bio">${bio}…</p>` : ''}
            </div>
        </div>`;
    }

    function addMarker(point) {
        var kind = getKind(point);
        var pinClass = getPinClass(point);
        var icon = L.divIcon({ html: '<div class="' + pinClass + '"></div>', iconSize: [22,22], iconAnchor: [11,11], popupAnchor: [0,-10] });

        var isMixed = kind === 'both' || kind === 'mixed';
        var accTitle = point.acc_title || 'Hébergement';
        var accPhoto = point.acc_photo_url || '';

        var popupHtml = '<div class="wpsd-popup-container">';
        if (isMixed) {
            popupHtml += '<div class="wpsd-popup-dual">';
            popupHtml += buildPopupCard(point, 'Activité');
            // Construire un objet pour l'hébergement avec ses propres infos
            var accPoint = Object.assign({}, point, {
                title: accTitle,
                photo_url: accPhoto,
                type: 'accommodation'
            });
            popupHtml += buildPopupCard(accPoint, 'Hébergement');
            popupHtml += '</div>';
        } else {
            popupHtml += buildPopupCard(point, kind === 'activity' ? 'Activité' : 'Hébergement');
        }
        popupHtml += '</div>';

        var marker = L.marker([point.lat, point.lng], { icon: icon }).addTo(markers).bindPopup(popupHtml, {
            maxWidth: 600,
            className: 'wpsd-custom-popup'
        });

        // Réattacher les événements sur les liens après ouverture du popup
        marker.on('popupopen', function() {
            setTimeout(function() {
                var links = document.querySelectorAll('.wpsd-carte-member-link');
                for (var i = 0; i < links.length; i++) {
                    links[i].addEventListener('click', function(e) {
                        e.preventDefault();
                        var userId = this.getAttribute('data-user-id');
                        if (userId) openMemberCard(userId);
                    });
                }
            }, 50);
        });
    }

    function openMemberCard(userId) {
        var old = document.getElementById('wpsd-member-card-modal');
        if (old) old.remove();

        var modal = document.createElement('div');
        modal.id = 'wpsd-member-card-modal';
        modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,82,71,0.6);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';

        var inner = document.createElement('div');
        inner.style.cssText = 'background:#fff;border-radius:14px;padding:24px;max-width:480px;width:100%;max-height:85vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.2);';
        inner.innerHTML = '<p style="text-align:center;padding:40px;">Chargement...</p>';

        modal.appendChild(inner);
        document.body.appendChild(modal);

        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.remove();
        });

        fetch(WPSD.restBase.replace('/wpsd/v1', '/wpsd/v2') + '/member-card/' + userId, {
            headers: { 'X-WP-Nonce': WPSD.nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            renderMemberCard(inner, data);
        })
        .catch(function() {
            inner.innerHTML = '<p style="text-align:center;padding:40px;color:var(--wpsd-danger);">Erreur de chargement</p>';
        });
    }

    function renderMemberCard(container, data) {
        var photoUrl = data.photo_url || '';
        var displayName = WPSD_Utils.escapeHtml(data.display_name || '');
        var bio = WPSD_Utils.escapeHtml(data.bio || '');
        var centreInteret = WPSD_Utils.escapeHtml(data.centre_interet || '');
        var skills = WPSD_Utils.escapeHtml(data.skills || '');
        var city = WPSD_Utils.escapeHtml(data.city || '');
        var region = WPSD_Utils.escapeHtml(data.region || '');
        var langues = data.langues || [];
        var roles = data.roles || [];
        var website = data.website || '';
        var instagram = data.instagram || '';
        var otherLink = data.other_link || '';
        var hasLinks = website || instagram || otherLink;

        var html = '';
        html += '<button class="wpsd-modal-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--wpsd-muted);">&times;</button>';
        html += '<div style="text-align:center;margin-bottom:20px;">';
        if (photoUrl) {
            html += '<img src="' + WPSD_Utils.escapeHtml(photoUrl) + '" alt="" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--wpsd-primary);margin-bottom:12px;">';
        } else {
            html += '<div style="width:100px;height:100px;border-radius:50%;background:var(--wpsd-primary-light);display:inline-flex;align-items:center;justify-content:center;font-size:36px;color:var(--wpsd-secondary);margin-bottom:12px;">' + (displayName.charAt(0).toUpperCase()) + '</div>';
        }
        html += '<h3 style="margin:0;color:var(--wpsd-secondary);font-size:20px;">' + displayName + '</h3>';
        if (roles.length > 0) {
            html += '<div style="margin-top:8px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">';
            for (var i = 0; i < roles.length; i++) {
                html += '<span style="background:var(--wpsd-primary-light);color:var(--wpsd-secondary);padding:4px 10px;border-radius:12px;font-size:12px;font-weight:500;">' + WPSD_Utils.escapeHtml(roles[i]) + '</span>';
            }
            html += '</div>';
        }
        if (city || region) {
            html += '<div style="margin-top:6px;font-size:13px;color:var(--wpsd-muted);">📍 ';
            if (city) html += WPSD_Utils.escapeHtml(city);
            if (city && region) html += ', ';
            if (region) html += WPSD_Utils.escapeHtml(region);
            html += '</div>';
        }
        html += '</div>';
        if (bio) {
            html += '<div style="margin-bottom:16px;"><h4 style="margin:0 0 6px;color:var(--wpsd-secondary);font-size:14px;">Bio</h4><p style="margin:0;font-size:14px;color:var(--wpsd-text);line-height:1.6;">' + bio + '</p></div>';
        }
        if (centreInteret) {
            html += '<div style="margin-bottom:16px;"><h4 style="margin:0 0 6px;color:var(--wpsd-secondary);font-size:14px;">Centres d\'intérêt</h4><p style="margin:0;font-size:14px;color:var(--wpsd-text);">' + centreInteret + '</p></div>';
        }
        if (skills) {
            html += '<div style="margin-bottom:16px;"><h4 style="margin:0 0 6px;color:var(--wpsd-secondary);font-size:14px;">Compétences / Savoir-faire</h4><p style="margin:0;font-size:14px;color:var(--wpsd-text);">' + skills + '</p></div>';
        }
        if (langues.length > 0) {
            html += '<div style="margin-bottom:16px;"><h4 style="margin:0 0 6px;color:var(--wpsd-secondary);font-size:14px;">Langues parlées</h4><div style="display:flex;gap:6px;flex-wrap:wrap;">';
            for (var j = 0; j < langues.length; j++) {
                html += '<span style="background:#f0f0f0;padding:4px 10px;border-radius:12px;font-size:12px;color:var(--wpsd-text);">' + WPSD_Utils.escapeHtml(langues[j]) + '</span>';
            }
            html += '</div></div>';
        }
        if (hasLinks) {
            html += '<div style="margin-bottom:16px;"><h4 style="margin:0 0 6px;color:var(--wpsd-secondary);font-size:14px;">Liens</h4><div style="display:flex;gap:8px;flex-wrap:wrap;">';
            if (website) html += '<a href="' + WPSD_Utils.escapeHtml(website) + '" target="_blank" rel="noopener" style="font-size:13px;color:var(--wpsd-primary-dark);text-decoration:underline;">🌐 Site web</a>';
            if (instagram) html += '<a href="https://instagram.com/' + WPSD_Utils.escapeHtml(instagram.replace('@', '')) + '" target="_blank" rel="noopener" style="font-size:13px;color:var(--wpsd-primary-dark);text-decoration:underline;">📷 Instagram</a>';
            if (otherLink) html += '<a href="' + WPSD_Utils.escapeHtml(otherLink) + '" target="_blank" rel="noopener" style="font-size:13px;color:var(--wpsd-primary-dark);text-decoration:underline;">🔗 Autre</a>';
            html += '</div></div>';
        }
        container.innerHTML = html;

        var closeBtn = container.querySelector('.wpsd-modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                var modalEl = document.getElementById('wpsd-member-card-modal');
                if (modalEl) modalEl.remove();
            });
        }
    }

    return { init: init, load: load, openMemberCard: openMemberCard };
})();