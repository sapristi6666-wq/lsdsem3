const WPSD_Demandes = (function() {
    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'demandes') load(); });

        const box = document.getElementById('wpsd-panel-demandes');
        if (!box) return;

        box.addEventListener('click', async (e) => {
            const t = e.target;

            const approveId = t.getAttribute?.('data-provider-approve');
            if (approveId) {
                const note = getNoteValue(approveId);
                WPSD_Toast.show('Acceptation...', 'info');
                const rr = await WPSD_API.post(`/reservations/${approveId}/approve`, { provider_note: note });
                if (rr.ok) WPSD_Toast.show('Demande acceptée', 'success');
                else WPSD_Toast.show(rr.error || 'Erreur', 'error');
                load();
                return;
            }

            const rejectId = t.getAttribute?.('data-provider-reject');
            if (rejectId) {
                if (!confirm('Refuser cette demande ?')) return;
                const note = getNoteValue(rejectId);
                WPSD_Toast.show('Refus...', 'info');
                const rr = await WPSD_API.post(`/reservations/${rejectId}/reject`, { provider_note: note });
                if (rr.ok) WPSD_Toast.show('Demande refusée', 'success');
                else WPSD_Toast.show(rr.error || 'Erreur', 'error');
                load();
                return;
            }

            const doneId = t.getAttribute?.('data-provider-done');
            if (doneId) {
                if (!confirm('Confirmer que la prestation est réalisée ?')) return;
                const rr = await WPSD_API.post(`/reservations/${doneId}/provider-done`);
                if (rr.ok) WPSD_Toast.show('Confirmé !', 'success');
                else WPSD_Toast.show(rr.error || 'Erreur', 'error');
                load();
                return;
            }

            const cancelId = t.getAttribute?.('data-itinerant-cancel');
            if (cancelId) {
                if (!confirm('Annuler cette demande ?')) return;
                const rr = await WPSD_API.post(`/reservations/${cancelId}/cancel`);
                if (rr.ok) WPSD_Toast.show('Demande annulée', 'success');
                else WPSD_Toast.show(rr.error || 'Erreur', 'error');
                load();
                return;
            }

            const itDoneId = t.getAttribute?.('data-itinerant-done');
            if (itDoneId) {
                if (!confirm('Confirmer que le séjour est terminé ?')) return;
                const rr = await WPSD_API.post(`/reservations/${itDoneId}/itinerant-done`);
                if (rr.ok) WPSD_Toast.show('Confirmé !', 'success');
                else WPSD_Toast.show(rr.error || 'Erreur', 'error');
                load();
                return;
            }
        });

        // Filtres
        box.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                box.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                load();
            });
        });
    }

    function getNoteValue(id) {
        const ta = document.querySelector(`textarea[data-pr-note="${CSS.escape(String(id))}"]`);
        return ta ? String(ta.value || '').trim() : '';
    }

        function sortByDate(items, field = 'date_start') {
        return (items || []).slice().sort((a, b) => {
            const da = new Date(a[field] || '2099-12-31');
            const db = new Date(b[field] || '2099-12-31');
            return da - db;
        });
    }

    async function load() {
        const itBox = WPSD_Utils.$('wpsd_it_reservations');
        const pendingBox = WPSD_Utils.$('wpsd_pr_pending');
        const ongoingBox = WPSD_Utils.$('wpsd_pr_ongoing');
        const historyBox = WPSD_Utils.$('wpsd_pr_history');
        const arriveesBox = WPSD_Utils.$('wpsd_pr_arrivees');

        WPSD_State.setLoading('demandes', true);

        if (itBox) {
            const r = await WPSD_API.get('/reservations?role=itinerant');
            if (r.ok && r.items?.length) itBox.innerHTML = renderList(sortByDate(r.items), 'itinerant');
            else itBox.innerHTML = '<p class="wpsd-hint">Aucune demande envoyée.</p>';
        }

        if (pendingBox || ongoingBox || historyBox || arriveesBox) {
            const r = await WPSD_API.get('/reservations?role=provider');
            if (r.ok && r.items) {
                // Arrivées à venir : accepted ou awaiting_confirmation, triées par date
                const upcoming = sortByDate(r.items.filter(x => ['accepted', 'awaiting_confirmation'].includes(x.status)));
                if (arriveesBox) {
                    if (upcoming.length) {
                        arriveesBox.innerHTML = upcoming.map(r2 => renderArrivee(r2)).join('');
                    } else {
                        arriveesBox.innerHTML = '<p class="wpsd-hint">Aucune arrivée à venir.</p>';
                    }
                }

                const activeFilter = document.querySelector('[data-filter].is-active')?.dataset?.filter || 'all';
                let filtered = r.items;
                if (activeFilter === 'pending') filtered = r.items.filter(x => x.status === 'pending');
                else if (activeFilter === 'approved') filtered = r.items.filter(x => x.status === 'approved');
                else if (activeFilter === 'completed') filtered = r.items.filter(x => x.status === 'completed');

                const sorted = sortByDate(filtered);
                if (pendingBox) pendingBox.innerHTML = renderList(sorted.filter(x => x.status === 'pending'), 'provider') || '<p class="wpsd-hint">Aucune en attente.</p>';
                if (ongoingBox) ongoingBox.innerHTML = renderList(sorted.filter(x => x.status === 'approved'), 'provider') || '<p class="wpsd-hint">Aucun séjour en cours.</p>';
                if (historyBox) historyBox.innerHTML = renderList(sortByDate(r.items.filter(x => !['pending','approved','awaiting_confirmation'].includes(x.status))), 'provider') || '<p class="wpsd-hint">Aucun historique.</p>';
            }
        }

        WPSD_State.setLoading('demandes', false);
    }

    function renderArrivee(r) {
        const phone = (r.status === 'awaiting_confirmation' || r.status === 'completed') && r.itinerant_phone
            ? ` · <a href="tel:${WPSD_Utils.escapeHtml(r.itinerant_phone)}">${WPSD_Utils.escapeHtml(r.itinerant_phone)}</a>`
            : '';
        const photo = r.itinerant_photo
            ? `<img src="${WPSD_Utils.escapeHtml(r.itinerant_photo)}" alt="" class="wpsd-arrivee-photo">`
            : `<span class="wpsd-arrivee-photo wpsd-arrivee-photo-placeholder"></span>`;
        return `<div class="wpsd-arrivee-card">
            ${photo}
            <div class="wpsd-arrivee-info">
                <strong>${WPSD_Utils.escapeHtml(r.itinerant_name || 'Itinérant')}</strong>
                <div class="wpsd-hint">${WPSD_Utils.escapeHtml(r.date_start)} → ${WPSD_Utils.escapeHtml(r.date_end)}</div>
                <div class="wpsd-hint">${WPSD_Utils.escapeHtml(r.object_title || '')}</div>
                ${phone}
            </div>
            <span class="wpsd-badge wpsd-badge-${WPSD_Utils.escapeHtml(r.status)}">${WPSD_Utils.escapeHtml(r.status_label || r.status)}</span>
        </div>`;
    }

    function renderList(items, role) {
        return items.map(r => {
            const canApproveReject = role === 'provider' && r.status === 'pending';
            const canProviderDone = role === 'provider' && r.status === 'approved' && !r.provider_done;
            const canCancel = role === 'itinerant' && ['pending','approved'].includes(r.status);
            const canItDone = role === 'itinerant' && r.status === 'approved' && r.provider_done && !r.itinerant_done;

            const noteBox = canApproveReject ? `<div style="margin-top:8px;"><label class="wpsd-hint">Note (optionnel)</label><textarea class="wpsd-input" style="width:100%;min-height:70px;" data-pr-note="${WPSD_Utils.escapeHtml(r.id)}"></textarea></div>` : '';

            return `<div class="wpsd-card" style="padding:14px;margin-bottom:10px;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <strong>${WPSD_Utils.escapeHtml(r.object_title)}</strong> ${WPSD_Utils.statusBadge(r.status, r.status_label)}
                </div>
                <div class="wpsd-hint">${WPSD_Utils.escapeHtml(r.date_start)} → ${WPSD_Utils.escapeHtml(r.date_end)} · ${r.quantity} pers. · ${WPSD_Utils.escapeHtml(r.provider_name||'')}${r.itinerant_name?' — '+WPSD_Utils.escapeHtml(r.itinerant_name):''}</div>
                ${r.itinerant_note ? `<div class="wpsd-hint"><strong>Note :</strong> ${WPSD_Utils.escapeHtml(r.itinerant_note)}</div>` : ''}
                ${noteBox}
                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                    ${canApproveReject ? `<button class="wpsd-btn wpsd-primary wpsd-btn-sm" data-provider-approve="${r.id}">✅ Accepter</button><button class="wpsd-btn wpsd-btn-sm" data-provider-reject="${r.id}">❌ Refuser</button>` : ''}
                    ${canProviderDone ? `<button class="wpsd-btn wpsd-btn-sm wpsd-primary" data-provider-done="${r.id}">✅ Confirmer</button>` : ''}
                    ${canCancel ? `<button class="wpsd-btn wpsd-btn-sm" data-itinerant-cancel="${r.id}">Annuler</button>` : ''}
                    ${canItDone ? `<button class="wpsd-btn wpsd-btn-sm wpsd-primary" data-itinerant-done="${r.id}">✅ Je confirme</button>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    return { init, load };
})();