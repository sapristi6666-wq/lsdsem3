const WPSD_Hebergements = (function() {
    function init() {
        WPSD_State.on('activeTab', function(tab) {
            if (tab === 'hebergements') load();
        });
    }

    async function load() {
        var box = WPSD_Utils.$('wpsd_accommodations_list');
        if (!box) return;
        WPSD_State.setLoading('hebergements', true);
        var data = await WPSD_API.get('/accommodations');
        var items = data.items || [];
        if (!items.length) {
            box.innerHTML = '<p class="wpsd-hint">Aucun hébergement.</p>';
            WPSD_State.setLoading('hebergements', false);
            return;
        }
        box.innerHTML = items.map(function(item) {
            return renderCard(item);
        }).join('');

        box.querySelectorAll('[data-acc-edit]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var editId = parseInt(btn.dataset.accEdit, 10);
                var found = items.find(function(a) { return parseInt(a.id, 10) === editId; });
                if (found) {
                    WPSD_Modals.open('acc', found);
                } else {
                    WPSD_Toast.show('Hébergement introuvable', 'error');
                }
            });
        });
        box.querySelectorAll('[data-acc-del]').forEach(function(btn) {
            btn.addEventListener('click', function() { deleteItem(btn.dataset.accDel); });
        });
        box.querySelectorAll('[data-acc-slots]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var found = items.find(function(a) { return parseInt(a.id, 10) === parseInt(btn.dataset.accSlots, 10); });
                if (found) WPSD_Savoirs.openSlotManager(found.id, found.title, 'accommodation');
            });
        });

        var photoInput = WPSD_Utils.$('wpsd_acc_photo_file');
        if (photoInput) photoInput.addEventListener('change', function() { handlePhotoUpload(this, 'acc'); });

        WPSD_State.setLoading('hebergements', false);
    }

    function renderCard(item) {
        return `<div class="wpsd-card wpsd-item-card" style="margin-bottom:12px;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                ${item.photo_url ? '<img src="' + WPSD_Utils.escapeHtml(item.photo_url) + '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0;">' : ''}
                <div style="flex:1;">
                    <strong>${WPSD_Utils.escapeHtml(item.title)}</strong>
                    ${item.description ? '<p style="font-size:13px;color:#5a6e68;margin:6px 0;">' + WPSD_Utils.escapeHtml(item.description).substring(0, 120) + '...</p>' : ''}
                    <div style="display:flex;gap:6px;margin-top:10px;">
                        <button class="wpsd-btn wpsd-btn-sm" data-acc-edit="${item.id}">Modifier</button>
                        <button class="wpsd-btn wpsd-btn-sm" data-acc-slots="${item.id}">Créneaux</button>
                        <button class="wpsd-btn wpsd-btn-sm" data-acc-del="${item.id}">Supprimer</button>
                    </div>
                </div>
            </div>
        </div>`;
    }

    async function deleteItem(id) {
        if (!confirm('Supprimer cet hébergement ?')) return;
        WPSD_Toast.show('Suppression...', 'info');
        await WPSD_API.delete('/accommodations/' + id);
        WPSD_Toast.show('Hébergement supprimé', 'success');
        load();
    }

    async function handlePhotoUpload(input, prefix) {
        var file = input.files[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            WPSD_Toast.show('L\'image ne doit pas dépasser 5 Mo.', 'error');
            input.value = '';
            return;
        }

        var reader = new FileReader();
        reader.onload = function(e) {
            WPSD_Crop.open(e.target.result, function(croppedBase64) {
                var blob = WPSD_Crop.dataURLtoBlob(croppedBase64);
                var fd = new FormData();
                fd.append('file', blob, 'cropped.jpg');
                WPSD_Toast.show('Upload...', 'info');
                fetch(WPSD.restBase + '/upload-image', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': WPSD.nonce },
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        WPSD_Modals.setVal('wpsd_' + prefix + '_photo_id', data.id);
                        var preview = WPSD_Utils.$('wpsd_' + prefix + '_photo_preview');
                        if (preview) {
                            preview.src = data.url;
                            preview.style.display = 'block';
                        }
                        WPSD_Toast.show('Image uploadée', 'success');
                    } else {
                        WPSD_Toast.show('Erreur upload', 'error');
                    }
                });
            });
        };
        reader.readAsDataURL(file);
        input.value = '';
    }

    async function handleSave() {
        var fields = [
            { id: 'title', label: 'Titre', required: true },
            { id: 'desc', label: 'Description' },
            { id: 'address_line1', label: 'Adresse' },
            { id: 'postal_code', label: 'Code postal' },
            { id: 'city', label: 'Ville' }
        ];
        if (!WPSD_Utils.validateModalFields('acc', fields)) return;

        var id = WPSD_Modals.getVal('wpsd_acc_id');
        var payload = {
            title: WPSD_Modals.getVal('wpsd_acc_title'),
            description: WPSD_Modals.getVal('wpsd_acc_desc'),
            capacity_adults: parseInt(WPSD_Modals.getVal('wpsd_acc_adults') || 0),
            capacity_children: parseInt(WPSD_Modals.getVal('wpsd_acc_children') || 0),
            address_line1: WPSD_Modals.getVal('wpsd_acc_address_line1'),
            postal_code: WPSD_Modals.getVal('wpsd_acc_postal_code'),
            city: WPSD_Modals.getVal('wpsd_acc_city'),
            country: 'FR',
            photo_id: WPSD_Modals.getInt('wpsd_acc_photo_id')
        };
        WPSD_Toast.show('Enregistrement...', 'info');
        var r = id ? await WPSD_API.put('/accommodations/' + id, payload) : await WPSD_API.post('/accommodations', payload);
        if (r.ok) {
            WPSD_Modals.close('acc');
            WPSD_Toast.show('Enregistré !', 'success');
            load();
        } else {
            WPSD_Toast.show(r.error || 'Erreur', 'error');
        }
    }
    
    function openNew() {
    WPSD_Modals.open('acc', null);
    }

    return { init: init, load: load, handleSave: handleSave, renderCard: renderCard, openNew: openNew };
})();