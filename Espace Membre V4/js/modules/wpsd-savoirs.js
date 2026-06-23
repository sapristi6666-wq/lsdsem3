const WPSD_Savoirs = (function() {
    let currentActivityId = null;
    let currentActivityTitle = '';
    let currentKind = 'activity';
    let slotsCalendar = null;
    let photoInputListenerAttached = false;
    let accommodationToggleInitialized = false;

    function init() {
        WPSD_State.on('activeTab', function(tab) {
            if (tab === 'savoirs') load();
        });

        document.addEventListener('wpsd-modal-opened', function(e) {
            if (e.detail && e.detail.modalId === 'act') {
                setTimeout(initAccommodationToggle, 50);
            }
        });
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
        if (!items.length) {
            box.innerHTML = '<p class="wpsd-hint">Aucune proposition.</p>';
            WPSD_State.setLoading('savoirs', false);
            return;
        }
        box.innerHTML = items.map(function(item) {
            return renderCard(item, 'act');
        }).join('');

        box.querySelectorAll('[data-act-edit]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var editId = parseInt(btn.dataset.actEdit, 10);
                var found = items.find(function(a) { return parseInt(a.id, 10) === editId; });
                if (found) {
                    WPSD_Modals.open('act', found);
                } else {
                    WPSD_Toast.show('Activité introuvable', 'error');
                }
            });
        });

        box.querySelectorAll('[data-act-del]').forEach(function(btn) {
            btn.addEventListener('click', function() { deleteItem(btn.dataset.actDel); });
        });

        box.querySelectorAll('[data-act-slots]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var found = items.find(function(a) { return a.id == btn.dataset.actSlots; });
                if (found) openSlotManager(found.id, found.title, 'activity');
            });
        });

        WPSD_State.setLoading('savoirs', false);

        var photoInput = WPSD_Utils.$('wpsd_act_photo_file');
        if (photoInput && !photoInputListenerAttached) {
            photoInput.addEventListener('change', function() { handlePhotoUpload(this, 'act'); });
            photoInputListenerAttached = true;
        }
    }

    function renderCard(item, prefix) {
        return '<div class="wpsd-card wpsd-item-card" style="margin-bottom:12px;">' +
            '<div style="display:flex;align-items:flex-start;gap:12px;">' +
            (item.photo_url ? '<img src="' + WPSD_Utils.escapeHtml(item.photo_url) + '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0;">' : '') +
            '<div style="flex:1;">' +
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;">' +
            '<strong>' + WPSD_Utils.escapeHtml(item.title) + '</strong>' +
            WPSD_Utils.statusBadge(item.status, item.status_label) +
            '</div>' +
            (item.description ? '<p style="font-size:13px;color:#5a6e68;margin:6px 0;">' + WPSD_Utils.escapeHtml(item.description).substring(0, 120) + '...</p>' : '') +
            '<div style="display:flex;gap:6px;margin-top:10px;">' +
            '<button class="wpsd-btn wpsd-btn-sm" data-' + prefix + '-edit="' + item.id + '">Modifier</button>' +
            '<button class="wpsd-btn wpsd-btn-sm" data-' + prefix + '-slots="' + item.id + '">Créneaux</button>' +
            '<button class="wpsd-btn wpsd-btn-sm" data-' + prefix + '-del="' + item.id + '">Supprimer</button>' +
            '</div></div></div></div>';
    }

    async function deleteItem(id) {
        if (!confirm('Supprimer cette proposition ?')) return;
        WPSD_Toast.show('Suppression...', 'info');
        await WPSD_API.delete('/activities/' + id);
        WPSD_Toast.show('Proposition supprimée', 'success');
        load();
    }

    /**
     * Initialise l'affichage conditionnel du bloc hébergement (sans champ capacité)
     */
    function initAccommodationToggle() {
        var checkbox = document.getElementById('wpsd_act_has_accommodation');
        var accFields = document.getElementById('wpsd_act_accommodation_fields');

        if (!checkbox || !accFields) return;

        // Éviter les doublons d'écouteurs
        if (accommodationToggleInitialized) {
            updateDisplay();
            return;
        }
        accommodationToggleInitialized = true;

        function updateDisplay() {
            if (checkbox.checked) {
                accFields.style.display = 'block';
                accFields.style.maxHeight = accFields.scrollHeight + 'px';
                accFields.style.opacity = '1';
            } else {
                accFields.style.display = 'none';
                accFields.style.maxHeight = '0';
                accFields.style.opacity = '0';
            }
        }

        // Appliquer l'état initial
        updateDisplay();

        // Gérer le changement
        checkbox.addEventListener('change', function() {
            updateDisplay();
        });
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

        var id = WPSD_Modals.getInt('wpsd_act_id');
        var hasAcc = document.getElementById('wpsd_act_has_accommodation');
        var isAccommodation = hasAcc && hasAcc.checked;

        // Plus de validation de capacité, on envoie juste 0
        var payload = {
            title: WPSD_Modals.getVal('wpsd_act_title'),
            description: WPSD_Modals.getVal('wpsd_act_desc'),
            address_line1: WPSD_Modals.getVal('wpsd_act_address_line1'),
            postal_code: WPSD_Modals.getVal('wpsd_act_postal_code'),
            city: WPSD_Modals.getVal('wpsd_act_city'),
            country: 'FR',
            photo_id: WPSD_Modals.getInt('wpsd_act_photo_id'),
            has_accommodation: isAccommodation ? 1 : 0,
            acc_capacity: 0  // Valeur fixe, plus de champ à remplir
        };

        WPSD_Toast.show('Enregistrement...', 'info');
        var r = id ? await WPSD_API.put('/activities/' + id, payload) : await WPSD_API.post('/activities', payload);

        if (r.ok) {
            var activityId = r.id || id;
            if (isAccommodation) {
                await createAccommodationIfNotExists(activityId, payload);
            }
            WPSD_Modals.close('act');
            WPSD_Toast.show('Enregistré !', 'success');
            load();
        } else {
            WPSD_Toast.show(r.error || 'Erreur', 'error');
        }
    }

    async function createAccommodationIfNotExists(activityId, activityPayload) {
        var existing = await WPSD_API.get('/accommodations?activity_id=' + activityId);
        if (existing.items && existing.items.length > 0) return;

        var accPayload = {
            title: 'Hébergement - ' + activityPayload.title,
            description: 'Hébergement sur place pour : ' + activityPayload.title,
            capacity_adults: 0,  // Plus de capacité, on met 0
            capacity_children: 0,
            address_line1: activityPayload.address_line1,
            postal_code: activityPayload.postal_code,
            city: activityPayload.city,
            country: activityPayload.country || 'FR',
            photo_id: activityPayload.photo_id || 0,
            activity_id: activityId
        };
        await WPSD_API.post('/accommodations', accPayload);
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
                })
                .catch(function() {
                    WPSD_Toast.show('Erreur réseau lors de l\'upload', 'error');
                });
            });
        };
        reader.readAsDataURL(file);
        input.value = '';
    }

    // ==================== SLOTS ====================

    function openSlotManager(activityId, activityTitle, kind) {
        currentActivityId = activityId;
        currentActivityTitle = activityTitle;
        currentKind = kind;

        var card = document.getElementById('wpsd-slots-card');
        var titleEl = document.getElementById('wpsd-slots-activity-title');
        if (card) card.style.display = 'block';
        if (titleEl) titleEl.textContent = activityTitle;

        document.getElementById('wpsd-slot-date-start').value = '';
        document.getElementById('wpsd-slot-date-end').value = '';

        loadSlotsCalendar(activityId, kind);

        var addBtn = document.getElementById('wpsd-slot-add-btn');
        if (addBtn) {
            addBtn.onclick = function() { addSlot(); };
        }
    }

    async function loadSlotsCalendar(objectId, kind) {
        var calEl = document.getElementById('wpsd-slots-calendar');
        if (!calEl) return;

        var res = await WPSD_API.get('/slots?kind=' + kind + '&object_id=' + objectId);
        var items = res.items || [];

        var events = items.map(function(slot) {
            return {
                id: slot.id,
                title: 'Disponible',
                start: slot.date_start,
                end: new Date(new Date(slot.date_end).getTime() + 86400000).toISOString().slice(0, 10),
                color: '#005247',
                textColor: '#FBF1CA'
            };
        });

        if (slotsCalendar) {
            slotsCalendar.destroy();
            slotsCalendar = null;
        }

        slotsCalendar = new FullCalendar.Calendar(calEl, {
            initialView: window.innerWidth < 600 ? 'listWeek' : 'dayGridMonth',
            height: 'auto',
            locale: 'fr',
            firstDay: 1,
            events: events,
            eventClick: async function(info) {
                if (confirm('Supprimer cette disponibilité ?')) {
                    await WPSD_API.delete('/slots/' + info.event.id);
                    WPSD_Toast.show('Créneau supprimé', 'success');
                    loadSlotsCalendar(objectId, kind);
                }
            }
        });
        slotsCalendar.render();
    }

    async function addSlot() {
        var dateStart = document.getElementById('wpsd-slot-date-start').value;
        var dateEnd = document.getElementById('wpsd-slot-date-end').value;

        if (!dateStart || !dateEnd) {
            WPSD_Toast.show('Veuillez choisir une date de début et de fin.', 'error');
            return;
        }
        if (dateEnd < dateStart) {
            WPSD_Toast.show('La date de fin doit être après la date de début.', 'error');
            return;
        }

        WPSD_Toast.show('Création du créneau...', 'info');

        var start = new Date(dateStart);
        var end = new Date(dateEnd);
        var current = new Date(start);
        var created = 0;

        while (current <= end) {
            var dateStr = current.toISOString().slice(0, 10);
            await WPSD_API.post('/slots', {
                kind: currentKind,
                object_id: parseInt(currentActivityId),
                date_start: dateStr,
                date_end: dateStr,
                capacity: 99,
                units: null
            });
            created++;
            current.setDate(current.getDate() + 1);
        }

        WPSD_Toast.show(created + ' jour(s) ajouté(s)', 'success');
        document.getElementById('wpsd-slot-date-start').value = '';
        document.getElementById('wpsd-slot-date-end').value = '';
        loadSlotsCalendar(currentActivityId, currentKind);
    }

    return {
        init: init,
        load: load,
        renderCard: renderCard,
        handleSave: handleSave,
        openNew: openNew,
        openSlotManager: openSlotManager
    };
})();