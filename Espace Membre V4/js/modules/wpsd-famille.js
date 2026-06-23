const WPSD_Famille = (function() {
    function init() {}

    function openNew() {
        WPSD_Modals.open('fam', null);
    }

    async function handleSave() {
        // Validation basique
        var fields = [
            { id: 'first_name', label: 'Prénom', required: true },
            { id: 'last_name', label: 'Nom', required: true },
        ];
        if (!WPSD_Utils.validateModalFields('fam', fields)) return;

        var id = WPSD_Modals.getInt('wpsd_fam_id');
        var payload = {
            first_name: WPSD_Modals.getVal('wpsd_fam_first_name'),
            last_name: WPSD_Modals.getVal('wpsd_fam_last_name'),
            email: WPSD_Modals.getVal('wpsd_fam_email'),
            phone: WPSD_Modals.getVal('wpsd_fam_phone'),
            birth_date: WPSD_Modals.getVal('wpsd_fam_birth_date'),
            address_line1: WPSD_Modals.getVal('wpsd_fam_address_line1'),
            postal_code: WPSD_Modals.getVal('wpsd_fam_postal_code'),
            city: WPSD_Modals.getVal('wpsd_fam_city'),
            bio_text: WPSD_Modals.getVal('wpsd_fam_bio_text'),
            photo_id: WPSD_Modals.getInt('wpsd_fam_photo_id'),
        };

        WPSD_Toast.show('Enregistrement...', 'info');
        var r = id
            ? await WPSD_API.put('/family/' + id, payload)
            : await WPSD_API.post('/family', payload);

        if (r.ok) {
            WPSD_Modals.close('fam');
            WPSD_Toast.show('Membre enregistré !', 'success');
            if (typeof WPSD_Profile !== 'undefined' && WPSD_Profile.loadFamily) {
                WPSD_Profile.loadFamily();
            }
        } else {
            WPSD_Toast.show(r.error || 'Erreur', 'error');
        }
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
                fetch(WPSD.restBase + '/upload-image', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': WPSD.nonce },
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        WPSD_Modals.setVal('wpsd_fam_photo_id', data.id);
                        var preview = WPSD_Utils.$('wpsd_fam_photo_preview');
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

    return { init, openNew, handleSave, handlePhotoUpload };
})();