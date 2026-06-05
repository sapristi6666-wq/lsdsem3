const WPSD_Compte = (function() {
    let currentCard = null;

    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'compte') loadCompte(); });
        document.getElementById('wpsd-compte-profil-form')?.addEventListener('submit', saveProfil);
        document.getElementById('wpsd-compte-infos-form')?.addEventListener('submit', saveInfos);
        document.getElementById('wpsd_compte_open_portal')?.addEventListener('click', openPortal);
        document.getElementById('wpsd_compte_change_plan')?.addEventListener('click', changePlan);
        document.getElementById('wpsd_compte_famille_add')?.addEventListener('click', () => WPSD_Modals.open('fam', null));
        document.getElementById('wpsd_compte_photo')?.addEventListener('change', handlePhotoUpload);
        document.getElementById('wpsd_compte_bio')?.addEventListener('input', updateBioCount);
    }

    async function loadCompte() {
        await loadProfil();
        await loadInfos();
        await loadFamille();
        await loadPaymentHistory();
    }

    // --- Section 1: Profil public ---
    async function loadProfil() {
        const r = await WPSD_API.get('/wpsd/v2/member-card/me');
        if (r && !r.code) {
            currentCard = r;
            setVal('wpsd_compte_bio', r.bio || '');
            setVal('wpsd_compte_photo_url', r.photo_url || '');
            setVal('wpsd_compte_interets', r.centre_interet || '');
            setVal('wpsd_compte_langues', Array.isArray(r.langues) ? r.langues.join(', ') : (r.langues || ''));
            setChecked('wpsd_compte_visible', r.visible_carte === true);
            updatePhotoPreview(r.photo_url);
            updateBioCount();
        }
    }

    async function saveProfil(e) {
        e.preventDefault();
        const statusEl = document.getElementById('wpsd_compte_profil_status');
        if (statusEl) statusEl.textContent = 'Enregistrement...';
        const bio = getVal('wpsd_compte_bio').trim();
        const payload = {
            bio,
            photo_url: getVal('wpsd_compte_photo_url').trim(),
            centre_interet: getVal('wpsd_compte_interets').trim(),
            langues: getVal('wpsd_compte_langues').split(',').map(s => s.trim()).filter(Boolean),
            visible_carte: getChecked('wpsd_compte_visible') ? 1 : 0,
        };
        const r = await WPSD_API.put('/wpsd/v2/member-card/me', payload);
        if (r && r.message) {
            if (statusEl) statusEl.textContent = 'Profil enregistré !';
            if (bio && !window.WPSD?.hasBio) document.dispatchEvent(new CustomEvent('wpsd-bio-saved'));
            if (window.WPSD) window.WPSD.hasBio = !!bio;
            setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 2000);
        } else {
            if (statusEl) statusEl.textContent = 'Erreur lors de l\'enregistrement.';
        }
    }

    function handlePhotoUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            const dataUrl = ev.target.result;
            updatePhotoPreview(dataUrl);
            setVal('wpsd_compte_photo_url', dataUrl);
        };
        reader.readAsDataURL(file);
    }

    function updatePhotoPreview(url) {
        const preview = document.getElementById('wpsd_compte_photo_preview');
        if (!preview) return;
        if (url) { preview.src = url; preview.style.display = 'block'; }
        else { preview.src = ''; preview.style.display = 'none'; }
    }

    function updateBioCount() {
        const bio = document.getElementById('wpsd_compte_bio');
        const count = document.getElementById('wpsd_compte_bio_count');
        if (bio && count) count.textContent = bio.value.length + ' / 300';
    }

    // --- Section 2: Infos personnelles ---
    async function loadInfos() {
        const user = window.WPSD || {};
        setVal('wpsd_compte_email', user.userEmail || '');
        const r = await fetch('/wp-json/wp/v2/users/me?_fields=id,first_name,last_name,meta.phone', {
            headers: { 'X-WP-Nonce': window.WPSD?.nonce || '' }
        });
        if (r.ok) {
            const data = await r.json();
            setVal('wpsd_compte_nom', data.first_name || '');
            setVal('wpsd_compte_prenom', data.last_name || '');
            setVal('wpsd_compte_telephone', data.meta?.phone || '');
        }
    }

    async function saveInfos(e) {
        e.preventDefault();
        const statusEl = document.getElementById('wpsd_compte_infos_status');
        if (statusEl) statusEl.textContent = 'Mise à jour...';
        const r = await fetch('/wp-json/wp/v2/users/me', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.WPSD?.nonce || '' },
            body: JSON.stringify({
                first_name: getVal('wpsd_compte_nom'),
                last_name: getVal('wpsd_compte_prenom'),
                meta: { phone: getVal('wpsd_compte_telephone') }
            })
        });
        if (r.ok) { if (statusEl) statusEl.textContent = 'Informations mises à jour !'; setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 2000); }
        else { if (statusEl) statusEl.textContent = 'Erreur.'; }
    }

    // --- Section 3: Famille ---
    async function loadFamille() {
        const box = document.getElementById('wpsd_compte_famille_list');
        if (!box) return;
        const r = await WPSD_API.get('/family-members');
        const items = r.items || [];
        if (!items.length) { box.innerHTML = '<p class="wpsd-hint">Aucun membre.</p>'; return; }
        box.innerHTML = items.map(m => `<div class="wpsd-card" style="padding:14px;"><strong>${WPSD_Utils.escapeHtml(m.first_name)} ${WPSD_Utils.escapeHtml(m.last_name)}</strong><div>${WPSD_Utils.escapeHtml(m.email||'')} · ${WPSD_Utils.escapeHtml(m.phone||'')} · ${WPSD_Utils.escapeHtml(m.birth_date||'')}</div><button class="wpsd-btn wpsd-btn-sm" data-fam-edit="${m.id}">Modifier</button><button class="wpsd-btn wpsd-btn-sm" data-fam-del="${m.id}">Supprimer</button></div>`).join('');
        box.querySelectorAll('[data-fam-edit]').forEach(btn => { btn.addEventListener('click', () => { const m = items.find(x => x.id == btn.dataset.famEdit); WPSD_Modals.open('fam', m); }); });
        box.querySelectorAll('[data-fam-del]').forEach(btn => { btn.addEventListener('click', () => { if (confirm('Supprimer ?')) { WPSD_API.delete('/family-members/' + btn.dataset.famDel); WPSD_Toast.show('Supprimé', 'success'); loadFamille(); } }); });
    }

    // --- Section 4: Adhésion ---
    async function loadPaymentHistory() {
        const box = document.getElementById('wpsd_compte_payment_history');
        if (!box) return;
        box.innerHTML = '<p class="wpsd-hint">Chargement...</p>';
        try {
            const r = await WPSD_API.get('/stripe/payment-history');
            if (r.ok && r.items?.length) {
                box.innerHTML = r.items.map(p => `<div class="wpsd-sub-row"><span>${WPSD_Utils.escapeHtml(p.date)}</span><strong>${WPSD_Utils.escapeHtml(p.amount)}</strong>${p.url ? ` <a href="${WPSD_Utils.escapeHtml(p.url)}" target="_blank" class="wpsd-btn wpsd-btn-sm">Voir</a>` : ''}</div>`).join('');
            } else {
                box.innerHTML = '<p class="wpsd-hint">Aucun paiement.</p>';
            }
        } catch { box.innerHTML = '<p class="wpsd-hint">Indisponible.</p>'; }
    }

    async function openPortal() {
        WPSD_Toast.show('Ouverture du portail...', 'info');
        const r = await WPSD_API.post('/create-portal-session');
        if (r.url) window.location.href = r.url;
        else WPSD_Toast.show(r.error || 'Erreur', 'error');
    }

    async function changePlan() {
        const btn = document.getElementById('wpsd_compte_change_plan');
        const plan = btn?.dataset?.plan || 'family';
        if (!confirm(`Passer au plan ${plan === 'family' ? 'Couple / Famille (70€/an)' : plan} ?`)) return;
        if (btn) btn.disabled = true;
        WPSD_Toast.show('Création de la session...', 'info');
        const r = await WPSD_API.post('/create-checkout-session', { plan });
        if (r.url) window.location.href = r.url;
        else { WPSD_Toast.show(r.error || 'Erreur', 'error'); if (btn) btn.disabled = false; }
    }

    function setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val || ''; }
    function getVal(id) { const el = document.getElementById(id); return el ? el.value : ''; }
    function setChecked(id, val) { const el = document.getElementById(id); if (el) el.checked = !!val; }
    function getChecked(id) { const el = document.getElementById(id); return el ? el.checked : false; }

    return { init, loadCompte };
})();
