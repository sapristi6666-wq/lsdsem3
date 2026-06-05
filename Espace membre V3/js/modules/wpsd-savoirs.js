const WPSD_Savoirs = (function() {
    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'savoirs') load(); });
    }

    function openNew() {
        WPSD_Modals.open('act', null);
    }

    async function load() {
        var box = WPSD_Utils.$('wpsd_activities_list');
        if (!box) return;
        WPSD_State.setLoading('savoirs', true);
        var data = await WPSD_API.get('/activities');
        var items = data.items || [];
        if (!items.length) { box.innerHTML = '<p class="wpsd-hint">Aucune proposition.</p>'; WPSD_State.setLoading('savoirs', false); return; }
        box.innerHTML = items.map(function(item) { return renderCard(item, 'act'); }).join('');
        box.querySelectorAll('[data-act-edit]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var found = items.find(function(a) { return a.id == btn.dataset.actEdit; });
                WPSD_Modals.open('act', found);
            });
        });
        box.querySelectorAll('[data-act-del]').forEach(function(btn) {
            btn.addEventListener('click', function() { deleteItem(btn.dataset.actDel); });
        });
        items.forEach(function(item) { setTimeout(function() { openSlotsInCard('activity', item.id, 'act'); }, 100); });
        WPSD_State.setLoading('savoirs', false);

        var photoInput = WPSD_Utils.$('wpsd_act_photo_file');
        if (photoInput) photoInput.addEventListener('change', function() { handlePhotoUpload(this, 'act'); });
    }

    function renderCard(item, prefix) {
        return '<div class="wpsd-card wpsd-item-card" style="margin-bottom:12px;"><div style="display:flex;align-items:flex-start;gap:12px;">' +
            (item.photo_url ? '<img src="' + WPSD_Utils.escapeHtml(item.photo_url) + '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0;">' : '') +
            '<div style="flex:1;"><div style="display:flex;justify-content:space-between;align-items:flex-start;"><strong>' + WPSD_Utils.escapeHtml(item.title) + '</strong>' + WPSD_Utils.statusBadge(item.status, item.status_label) + '</div>' +
            (item.description ? '<p style="font-size:13px;color:#5a6e68;margin:6px 0;">' + WPSD_Utils.escapeHtml(item.description).substring(0,120) + '...</p>' : '') +
            '<div style="display:flex;gap:6px;margin-top:10px;"><button class="wpsd-btn wpsd-btn-sm" data-' + prefix + '-edit="' + item.id + '">Modifier</button><button class="wpsd-btn wpsd-btn-sm" data-' + prefix + '-del="' + item.id + '">Supprimer</button></div><div id="slotbox-' + prefix + '-' + item.id + '" style="margin-top:8px;"></div></div></div></div>';
    }

    async function deleteItem(id) {
        if (!confirm('Supprimer cette proposition ?')) return;
        WPSD_Toast.show('Suppression...', 'info');
        await WPSD_API.delete('/activities/' + id);
        WPSD_Toast.show('Proposition supprimée', 'success');
        load();
    }

    async function handleSave() {
        var fields = [
            { id: 'title', label: 'Titre', required: true, maxLength: 200 },
            { id: 'desc', label: 'Description', maxLength: 5000 },
            { id: 'address_line1', label: 'Adresse', required: true, maxLength: 255 },
            { id: 'postal_code', label: 'Code postal', maxLength: 20 },
            { id: 'city', label: 'Ville', required: true, maxLength: 190 },
        ];
        if (!WPSD_Utils.validateModalFields('act', fields)) return;

        var id = WPSD_Modals.getVal('wpsd_act_id');
        var hasAcc = document.getElementById('wpsd_act_has_accommodation');
        var accCap = document.getElementById('wpsd_act_acc_capacity');
        var payload = {
            title: WPSD_Modals.getVal('wpsd_act_title'),
            description: WPSD_Modals.getVal('wpsd_act_desc'),
            address_line1: WPSD_Modals.getVal('wpsd_act_address_line1'),
            postal_code: WPSD_Modals.getVal('wpsd_act_postal_code'),
            city: WPSD_Modals.getVal('wpsd_act_city'),
            country: 'FR',
            photo_id: WPSD_Modals.getInt('wpsd_act_photo_id'),
            has_accommodation: (hasAcc && hasAcc.checked) ? 1 : 0,
            acc_capacity: accCap ? parseInt(accCap.value || 0) : 0,
        };
        WPSD_Toast.show('Enregistrement...', 'info');
        var r = id ? await WPSD_API.put('/activities/' + id, payload) : await WPSD_API.post('/activities', payload);
        if (r.ok) { WPSD_Modals.close('act'); WPSD_Toast.show('Enregistré !', 'success'); load(); }
        else WPSD_Toast.show(r.error || 'Erreur', 'error');
    }

    async function handlePhotoUpload(input, prefix) {
        var file = input.files[0]; if (!file) return;
        var fd = new FormData(); fd.append('file', file);
        WPSD_Toast.show('Upload...', 'info');
        var res = await fetch(WPSD.restBase + '/upload-image', { method: 'POST', headers: { 'X-WP-Nonce': WPSD.nonce }, body: fd, credentials: 'same-origin' });
        var data = await res.json();
        if (data.ok) { WPSD_Modals.setVal('wpsd_' + prefix + '_photo_id', data.id); var preview = WPSD_Utils.$('wpsd_' + prefix + '_photo_preview'); if (preview) { preview.src = data.url; preview.style.display = 'block'; } WPSD_Toast.show('Image uploadée', 'success'); }
        else WPSD_Toast.show('Erreur upload', 'error');
        input.value = '';
    }

    async function openSlotsInCard(kind, objectId, prefix) {
        var box = WPSD_Utils.$('slotbox-' + prefix + '-' + objectId);
        if (!box) return;
        box.innerHTML = '<div id="calendar-' + kind + '-' + objectId + '" style="min-height:250px;"></div>';
        var res = await WPSD_API.get('/slots?kind=' + kind + '&object_id=' + objectId);
        var items = res.items || [];
        var events = items.map(function(s) { return { id: String(s.id), title: (s.remaining||0) + '/' + (s.slot_total||0), start: s.date_start, end: s.date_end, allDay: true }; });
        var calEl = document.getElementById('calendar-' + kind + '-' + objectId);
        if (!calEl) return;
        var cal = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth', selectable: true, height: 'auto', locale: 'fr', firstDay: 1, events: events,
            select: async function(info) {
                var cap = prompt(kind === 'activity' ? 'Capacité par jour ?' : 'Logements par jour ?', '1');
                if (!cap) return;
                var capVal = parseInt(cap, 10);
                if (isNaN(capVal) || capVal < 0) return;
                var endDate = new Date(info.endStr); endDate.setDate(endDate.getDate() - 1);
                WPSD_Toast.show('Création du slot...', 'info');
                await WPSD_API.post('/slots', { kind: kind, object_id: parseInt(objectId), date_start: info.startStr, date_end: endDate.toISOString().slice(0,10), capacity: kind==='activity'?capVal:null, units: kind==='accommodation'?capVal:null });
                WPSD_Toast.show('Slot créé', 'success');
                openSlotsInCard(kind, objectId, prefix);
            },
            eventClick: async function(info) { if (confirm('Supprimer cette disponibilité ?')) { await WPSD_API.delete('/slots/' + info.event.id); WPSD_Toast.show('Slot supprimé', 'success'); openSlotsInCard(kind, objectId, prefix); } }
        });
        cal.render();
    }

    return { init: init, load: load, renderCard: renderCard, handleSave: handleSave, openNew: openNew };
})();