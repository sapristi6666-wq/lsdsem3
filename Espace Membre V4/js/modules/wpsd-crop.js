const WPSD_Famille = (function() {
    function init() {
        // Lier l'upload photo dès que le DOM est prêt
        var input = document.getElementById('wpsd_fam_photo_file');
        if (input) {
            input.addEventListener('change', handlePhotoUpload);
        }
    }

    function openNew() {
        WPSD_Modals.open('fam', null);
    }

    async function handleSave() {
        if (!WPSD_Utils.validateModalFields('fam', [
            { id: 'first_name', label: 'Prénom', required: true },
            { id: 'last_name', label: 'Nom', required: true }
        ])) return;

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
            photo_id: WPSD_Modals.getInt('wpsd_fam_photo_id')
        };

        WPSD_Toast.show('Enregistrement...', 'info');
        var r = id
            ? await WPSD_API.put('/family/' + id, payload)
            : await WPSD_API.post('/family', payload);

        if (r.ok) {
            WPSD_Modals.close('fam');
            WPSD_Toast.show('Membre enregistré !', 'success');
            if (WPSD_Profile && WPSD_Profile.loadFamily) WPSD_Profile.loadFamily();
        } else {
            WPSD_Toast.show(r.error || 'Erreur', 'error');
        }
    }

    function handlePhotoUpload() {
        var file = this.files[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            WPSD_Toast.show('L\'image ne doit pas dépasser 5 Mo.', 'error');
            this.value = '';
            return;
        }

        var reader = new FileReader();
        reader.onload = function(e) {
            // Ouvre la modale de crop réutilisable
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
                        var preview = document.getElementById('wpsd_fam_photo_preview');
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
        this.value = '';
    }

    // Appeler init() au chargement du DOM pour attacher l'écouteur
    document.addEventListener('DOMContentLoaded', init);

    return { init, openNew, handleSave };
})();