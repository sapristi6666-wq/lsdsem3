const WPSD_Profil = (function() {
    let currentData = null;

    function init() {
        WPSD_State.on('activeTab', function(tabId) {
            if (tabId === 'profil') loadForm();
        });
    }

    async function loadForm() {
        const userId = window.WPSD?.userId;
        if (!userId) return;
        try {
            const card = await WPSD_API.get('/wpsd/v2/member-card/' + userId);
            if (!card || card.code) return;
            currentData = card;
            setField('wpsd_profil_bio', card.bio || '');
            setField('wpsd_profil_photo', card.photo_url || '');
            setField('wpsd_profil_interets', card.centre_interet || '');
            setField('wpsd_profil_langues', Array.isArray(card.langues) ? card.langues.join(', ') : (card.langues || ''));
            setChecked('wpsd_profil_visible', card.visible_carte === true);
            updatePhotoPreview(card.photo_url);
            updateBioCount();
        } catch (e) {
            // ignore
        }
    }

    function setField(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val || '';
    }

    function getField(id) {
        const el = document.getElementById(id);
        return el ? el.value : '';
    }

    function setChecked(id, val) {
        const el = document.getElementById(id);
        if (el) el.checked = !!val;
    }

    function getChecked(id) {
        const el = document.getElementById(id);
        return el ? el.checked : false;
    }

    function updatePhotoPreview(url) {
        const preview = document.getElementById('wpsd_profil_photo_preview');
        if (!preview) return;
        if (url) {
            preview.src = url;
            preview.style.display = 'block';
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    }

    function updateBioCount() {
        const bio = document.getElementById('wpsd_profil_bio');
        const count = document.getElementById('wpsd_profil_bio_count');
        if (bio && count) {
            count.textContent = bio.value.length + ' / 300';
        }
    }

    async function handleSave(e) {
        e.preventDefault();
        const statusEl = document.getElementById('wpsd_profil_status');
        if (statusEl) statusEl.textContent = 'Enregistrement...';

        const bio = getField('wpsd_profil_bio').trim();
        const payload = {
            bio: bio,
            photo_url: getField('wpsd_profil_photo').trim(),
            centre_interet: getField('wpsd_profil_interets').trim(),
            langues: getField('wpsd_profil_langues').split(',').map(function(s) { return s.trim(); }).filter(Boolean),
            visible_carte: getChecked('wpsd_profil_visible') ? 1 : 0,
        };

        const r = await WPSD_API.put('/wpsd/v2/member-card/me', payload);
        if (r && r.message) {
            if (statusEl) statusEl.textContent = 'Profil enregistre !';
            // Si la bio vient d'etre remplie, emettre un evenement pour reveler les onglets
            if (bio && !window.WPSD?.hasBio) {
                document.dispatchEvent(new CustomEvent('wpsd-bio-saved'));
            }
            if (window.WPSD) window.WPSD.hasBio = !!bio;
            // Recharger le formulaire avec les donnees a jour
            setTimeout(function() { loadForm(); if (statusEl) statusEl.textContent = ''; }, 2000);
        } else {
            if (statusEl) statusEl.textContent = 'Erreur lors de l\'enregistrement.';
        }
    }

    function attachEvents() {
        const form = document.getElementById('wpsd-profil-form');
        if (form) form.addEventListener('submit', handleSave);

        const photoInput = document.getElementById('wpsd_profil_photo');
        if (photoInput) {
            photoInput.addEventListener('input', function() {
                updatePhotoPreview(this.value);
            });
        }

        const bioInput = document.getElementById('wpsd_profil_bio');
        if (bioInput) {
            bioInput.addEventListener('input', updateBioCount);
        }
    }

    // Attacher les evenements au chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachEvents);
    } else {
        attachEvents();
    }

    return { init: init, loadForm: loadForm };
})();
