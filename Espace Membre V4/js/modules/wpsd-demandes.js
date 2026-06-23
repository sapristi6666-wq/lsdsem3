const WPSD_Demandes = (function() {
    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'demandes') load(); });

        const box = document.getElementById('wpsd-panel-demandes');
        if (!box) return;

        // Sous-onglets secondaires (À venir / En cours / Passées)
        box.addEventListener('click', (e) => {
            const btn = e.target.closest('.wpsd-subtab-secondary');
            if (!btn) return;
            const panel = btn.closest('.wpsd-subpanel');
            panel.querySelectorAll('.wpsd-subtab-secondary').forEach(b => b.classList.remove('is-active'));
            panel.querySelectorAll('.wpsd-period-panel').forEach(p => p.classList.remove('is-active'));
            btn.classList.add('is-active');
            const prefix = panel.id.includes('envoyees') ? 'it' : 'pr';
            const target = panel.querySelector('#wpsd_' + prefix + '_' + btn.dataset.period);
            if (target) target.classList.add('is-active');
        });

        // Actions (accepter, refuser, confirmer, annuler)
        box.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const id = btn.dataset.actionId;
            const action = btn.dataset.action;
            if (!id || !action) return;

            if (action === 'approve') {
                const note = getNoteValue(id);
                WPSD_Toast.show('Acceptation...', 'info');
                const rr = await WPSD_API.post(`/reservations/${id}/approve`, { provider_note: note });
                WPSD_Toast.show(rr.ok ? 'Acceptée' : (rr.error || 'Erreur'), rr.ok ? 'success' : 'error');
                load();
            } else if (action === 'reject') {
                if (!confirm('Refuser ?')) return;
                const note = getNoteValue(id);
                const rr = await WPSD_API.post(`/reservations/${id}/reject`, { provider_note: note });
                WPSD_Toast.show(rr.ok ? 'Refusée' : (rr.error || 'Erreur'), rr.ok ? 'success' : 'error');
                load();
            } else if (action === 'provider-done') {
                if (!confirm('Confirmer la prestation ?')) return;
                const rr = await WPSD_API.post(`/reservations/${id}/provider-done`);
                WPSD_Toast.show(rr.ok ? 'Confirmé' : (rr.error || 'Erreur'), rr.ok ? 'success' : 'error');
                load();
            } else if (action === 'cancel') {
                if (!confirm('Annuler ?')) return;
                const rr = await WPSD_API.post(`/reservations/${id}/cancel`);
                WPSD_Toast.show(rr.ok ? 'Annulée' : (rr.error || 'Erreur'), rr.ok ? 'success' : 'error');
                load();
            } else if (action === 'itinerant-done') {
                if (!confirm('Confirmer le séjour ?')) return;
                const rr = await WPSD_API.post(`/reservations/${id}/itinerant-done`);
                WPSD_Toast.show(rr.ok ? 'Confirmé' : (rr.error || 'Erreur'), rr.ok ? 'success' : 'error');
                load();
            }
        });
    }

    function getNoteValue(id) {
        const ta = document.querySelector(`textarea[data-pr-note="${id}"]`);
        return ta ? ta.value.trim() : '';
    }

    async function load() {
        const today = new Date().toISOString().split('T')[0];
        const itinerant = document.getElementById('wpsd-subpanel-envoyees');
        const prestataire = document.getElementById('wpsd-subpanel-recues');

        if (itinerant) {
            const r = await WPSD_API.get('/reservations?role=itinerant');
            if (r.ok && r.items) {
                fillPeriod('it', r.items, today);
            }
        }

        if (prestataire) {
            const r = await WPSD_API.get('/reservations?role=provider');
            if (r.ok && r.items) {
                fillPeriod('pr', r.items, today);
            }
        }
    }

    function fillPeriod(prefix, items, today) {
        const aVenir = items.filter(x => ['pending','approved'].includes(x.status) && x.date_start > today);
        const enCours = items.filter(x => x.status === 'approved' && x.date_start <= today && x.date_end >= today);
        const passees = items.filter(x => !aVenir.includes(x) && !enCours.includes(x));

        const aVenirEl = document.getElementById('wpsd_' + prefix + '_a_venir');
        const enCoursEl = document.getElementById('wpsd_' + prefix + '_en_cours');
        const passeesEl = document.getElementById('wpsd_' + prefix + '_passees');

        const role = prefix === 'pr' ? 'provider' : 'itinerant';

        if (aVenirEl) aVenirEl.innerHTML = aVenir.length ? renderCards(aVenir, role) : '<p class="wpsd-hint">Rien à venir.</p>';
        if (enCoursEl) enCoursEl.innerHTML = enCours.length ? renderCards(enCours, role) : '<p class="wpsd-hint">Rien en cours.</p>';
        if (passeesEl) passeesEl.innerHTML = passees.length ? renderCards(passees, role) : '<p class="wpsd-hint">Rien.</p>';
    }

    function renderCards(items, role) {
        return items.map(r => {
            const otherName = role === 'provider' ? r.itinerant_name : r.provider_name;
            const otherPhoto = role === 'provider' ? r.itinerant_photo : r.provider_photo;
            const otherPhone = role === 'provider' ? r.itinerant_phone : r.provider_phone;
            const daysLeft = Math.ceil((new Date(r.date_start) - new Date()) / (1000 * 60 * 60 * 24));
            const showPhone = ['approved','awaiting_confirmation','completed'].includes(r.status);
            const canApproveReject = role === 'provider' && r.status === 'pending';
            const canProviderDone = role === 'provider' && r.status === 'approved' && !r.provider_done;
            const canCancel = role === 'itinerant' && ['pending','approved'].includes(r.status);
            const canItDone = role === 'itinerant' && r.status === 'approved' && r.provider_done && !r.itinerant_done;

            return `
            <div class="wpsd-demande-card">
                <div class="wpsd-demande-photo">
                    ${otherPhoto ? `<img src="${WPSD_Utils.escapeHtml(otherPhoto)}" alt="">` : `<div class="wpsd-demande-avatar">${(otherName||'?')[0].toUpperCase()}</div>`}
                </div>
                <div class="wpsd-demande-body">
                    <div class="wpsd-demande-header">
                        <strong>${WPSD_Utils.escapeHtml(r.object_title)}</strong>
                        <span class="wpsd-demande-badge wpsd-demande-${r.status}">${WPSD_Utils.escapeHtml(r.status_label)}</span>
                    </div>
                    <div class="wpsd-demande-dates">${WPSD_Utils.escapeHtml(r.date_start)} → ${WPSD_Utils.escapeHtml(r.date_end)} · ${r.quantity} pers.</div>
                    <div class="wpsd-demande-name">${WPSD_Utils.escapeHtml(otherName||'')}</div>
                    ${showPhone && otherPhone ? `<div class="wpsd-demande-phone">${WPSD_Utils.escapeHtml(otherPhone)}</div>` : ''}
                    ${daysLeft > 0 ? `<div class="wpsd-demande-countdown">Dans ${daysLeft} jour${daysLeft>1?'s':''}</div>` : daysLeft === 0 ? `<div class="wpsd-demande-countdown">Aujourd'hui</div>` : ''}
                    ${r.itinerant_note ? `<div class="wpsd-hint" style="margin-top:6px;">Note : ${WPSD_Utils.escapeHtml(r.itinerant_note)}</div>` : ''}
                    ${canApproveReject ? `<div class="wpsd-demande-actions"><textarea class="wpsd-input" data-pr-note="${r.id}" placeholder="Note (optionnel)" rows="2" style="width:100%;margin-bottom:6px;"></textarea><button class="wpsd-btn wpsd-primary wpsd-btn-sm" data-action="approve" data-action-id="${r.id}">Accepter</button><button class="wpsd-btn wpsd-btn-sm wpsd-btn-danger" data-action="reject" data-action-id="${r.id}">Refuser</button></div>` : ''}
                    <div class="wpsd-demande-actions">
                        ${canProviderDone ? `<button class="wpsd-btn wpsd-primary wpsd-btn-sm" data-action="provider-done" data-action-id="${r.id}">Confirmer la prestation</button>` : ''}
                        ${canCancel ? `<button class="wpsd-btn wpsd-btn-sm" data-action="cancel" data-action-id="${r.id}">Annuler</button>` : ''}
                        ${canItDone ? `<button class="wpsd-btn wpsd-primary wpsd-btn-sm" data-action="itinerant-done" data-action-id="${r.id}">Confirmer mon séjour</button>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    return { init, load };
})();