const WPSD_Famille = (function() {
    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'famille') load(); });
    }

    function openNew() {
        WPSD_Modals.open('fam', null);
    }

    async function load() {
        var box = WPSD_Utils.$('wpsd_famille_list');
        if (!box) return;
        WPSD_State.setLoading('famille', true);
        var data = await WPSD_API.get('/family-members');
        var items = data.items || [];
        if (!items.length) { box.innerHTML = '<p class="wpsd-hint">Aucun membre.</p>'; WPSD_State.setLoading('famille', false); return; }
        box.innerHTML = items.map(function(m) { return '<div class="wpsd-card" style="padding:14px;"><strong>' + WPSD_Utils.escapeHtml(m.first_name) + ' ' + WPSD_Utils.escapeHtml(m.last_name) + '</strong><div>' + WPSD_Utils.escapeHtml(m.email||'') + ' · ' + WPSD_Utils.escapeHtml(m.phone||'') + ' · ' + WPSD_Utils.escapeHtml(m.birth_date||'') + '</div><button class="wpsd-btn wpsd-btn-sm" data-fam-edit="' + m.id + '">Modifier</button><button class="wpsd-btn wpsd-btn-sm" data-fam-del="' + m.id + '">Supprimer</button></div>'; }).join('');
        box.querySelectorAll('[data-fam-edit]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var found = items.find(function(m) { return m.id == btn.dataset.famEdit; });
                openModal(found);
            });
        });
        box.querySelectorAll('[data-fam-del]').forEach(function(btn) {
            btn.addEventListener('click', function() { if (confirm('Supprimer ?')) { WPSD_API.delete('/family-members/' + btn.dataset.famDel); WPSD_Toast.show('Supprimé', 'success'); load(); } });
        });
        WPSD_State.setLoading('famille', false);
    }

    function openModal(member) {
        var modal = WPSD_Utils.$('wpsd_fam_modal');
        if (!modal) return;
        if (member) {
            WPSD_Utils.$('wpsd_fam_modal_title').textContent = 'Modifier';
            WPSD_Modals.setVal('wpsd_fam_id', member.id);
            WPSD_Modals.setVal('wpsd_fam_first_name', member.first_name);
            WPSD_Modals.setVal('wpsd_fam_last_name', member.last_name);
            WPSD_Modals.setVal('wpsd_fam_email', member.email);
            WPSD_Modals.setVal('wpsd_fam_phone', member.phone);
            WPSD_Modals.setVal('wpsd_fam_birth_date', member.birth_date);
            WPSD_Modals.setVal('wpsd_fam_address_line1', member.address_line1);
            WPSD_Modals.setVal('wpsd_fam_postal_code', member.postal_code);
            WPSD_Modals.setVal('wpsd_fam_city', member.city);
            WPSD_Modals.setVal('wpsd_fam_bio_text', member.bio_text);
        } else {
            WPSD_Utils.$('wpsd_fam_modal_title').textContent = 'Ajouter';
            ['id','first_name','last_name','email','phone','birth_date','address_line1','postal_code','city','bio_text'].forEach(function(f) { WPSD_Modals.setVal('wpsd_fam_'+f, ''); });
        }
        modal.setAttribute('aria-hidden', 'false');
    }

    async function handleSave() {
        var id = WPSD_Modals.getVal('wpsd_fam_id');
        var payload = {
            first_name: WPSD_Modals.getVal('wpsd_fam_first_name'),
            last_name: WPSD_Modals.getVal('wpsd_fam_last_name'),
            email: WPSD_Modals.getVal('wpsd_fam_email'),
            phone: WPSD_Modals.getVal('wpsd_fam_phone'),
            birth_date: WPSD_Modals.getVal('wpsd_fam_birth_date'),
            address_line1: WPSD_Modals.getVal('wpsd_fam_address_line1'),
            postal_code: WPSD_Modals.getVal('wpsd_fam_postal_code'),
            city: WPSD_Modals.getVal('wpsd_fam_city'),
            bio_text: WPSD_Modals.getVal('wpsd_fam_bio_text')
        };
        WPSD_Toast.show('Enregistrement...', 'info');
        var r = id ? await WPSD_API.put('/family-members/' + id, payload) : await WPSD_API.post('/family-members', payload);
        if (r.ok) { WPSD_Modals.close('fam'); WPSD_Toast.show('Enregistré !', 'success'); load(); }
        else WPSD_Toast.show(r.error || 'Erreur', 'error');
    }

    return { init: init, load: load, handleSave: handleSave, openNew: openNew };
})();