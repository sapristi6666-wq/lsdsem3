const WPSD_Adhesion = (function() {
    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'adhesion') load(); });
        WPSD_Utils.$('wpsd_open_portal')?.addEventListener('click', openPortal);
        WPSD_Utils.$('wpsd_change_plan')?.addEventListener('click', changePlan);
    }

    async function load() {
        loadPaymentHistory();
    }

    async function loadPaymentHistory() {
        const box = WPSD_Utils.$('wpsd_payment_history');
        if (!box) return;
        box.innerHTML = '<p class="wpsd-hint">Chargement...</p>';
        try {
            const r = await WPSD_API.get('/stripe/payment-history');
            if (r.ok && r.items?.length) {
                box.innerHTML = r.items.map(p => `<div class="wpsd-sub-row"><span>${WPSD_Utils.escapeHtml(p.date)}</span><strong>${WPSD_Utils.escapeHtml(p.amount)}</strong>${p.url ? ` <a href="${WPSD_Utils.escapeHtml(p.url)}" target="_blank" class="wpsd-btn wpsd-btn-sm">Voir</a>` : ''}</div>`).join('');
            } else {
                box.innerHTML = '<p class="wpsd-hint">Aucun paiement.</p>';
            }
        } catch {
            box.innerHTML = '<p class="wpsd-hint">Indisponible.</p>';
        }
    }

    async function openPortal() {
        WPSD_Toast.show('Ouverture du portail...', 'info');
        const r = await WPSD_API.post('/create-portal-session');
        if (r.url) window.location.href = r.url;
        else WPSD_Toast.show(r.error || 'Erreur', 'error');
    }

    async function changePlan() {
        const btn = WPSD_Utils.$('wpsd_change_plan');
        const plan = btn?.dataset?.plan || 'family';
        if (!confirm(`Passer au plan ${plan === 'family' ? 'Couple / Famille (70€/an)' : plan} ?`)) return;
        btn.disabled = true;
        WPSD_Toast.show('Création de la session...', 'info');
        const r = await WPSD_API.post('/create-checkout-session', { plan });
        if (r.url) window.location.href = r.url;
        else { WPSD_Toast.show(r.error || 'Erreur', 'error'); btn.disabled = false; }
    }

    return { init };
})();