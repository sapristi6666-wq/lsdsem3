const WPSD_Modals = (function() {
    function init() {
        // Fermeture au clic sur l'overlay
        document.querySelectorAll('.wpsd-modal').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.setAttribute('aria-hidden', 'true');
            });
        });

        // Fermeture avec la touche Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.wpsd-modal[aria-hidden="false"]').forEach(function(m) {
                    m.setAttribute('aria-hidden', 'true');
                });
            }
        });
    }

    function open(prefix, item) {
        var modal = document.getElementById('wpsd_' + prefix + '_modal');
        var title = document.getElementById('wpsd_' + prefix + '_modal_title');
        if (!modal || !title) return;

        if (item) {
            // Mode édition
            title.textContent = 'Modifier';
            setVal('wpsd_' + prefix + '_id', item.id);
            setVal('wpsd_' + prefix + '_title', item.title);
            setVal('wpsd_' + prefix + '_desc', item.description || '');

            // Champs spécifiques selon le type de modale
            switch (prefix) {
                case 'act':
                    // Activité : adresse et hébergement sur place
                    setVal('wpsd_act_address_line1', item.address_line1 || '');
                    setVal('wpsd_act_postal_code', item.postal_code || '');
                    setVal('wpsd_act_city', item.city || '');

                    // Case à cocher hébergement
                    var hasAccCheckbox = document.getElementById('wpsd_act_has_accommodation');
                    if (hasAccCheckbox) {
                        hasAccCheckbox.checked = !!(item.has_accommodation);
                    }

                    // Capacité hébergement
                    var accCap = document.getElementById('wpsd_act_acc_capacity');
                    if (accCap) {
                        accCap.value = (item.acc_capacity !== undefined && item.acc_capacity !== null) 
                            ? item.acc_capacity 
                            : '';
                    }
                    break;

                case 'acc':
                    // Hébergement : adresse, capacités
                    setVal('wpsd_acc_address_line1', item.address_line1 || '');
                    setVal('wpsd_acc_postal_code', item.postal_code || '');
                    setVal('wpsd_acc_city', item.city || '');
                    setVal('wpsd_acc_adults', item.capacity_adults || 0);
                    setVal('wpsd_acc_children', item.capacity_children || 0);
                    break;

                case 'article':
                    // Article : pas de champs supplémentaires spécifiques
                    break;

                case 'famille':
                    // Famille : pas de champs supplémentaires spécifiques
                    break;
            }

            // Gestion de la photo (commun à act et acc)
            setVal('wpsd_' + prefix + '_photo_id', item.photo_id || '');
            var preview = document.getElementById('wpsd_' + prefix + '_photo_preview');
            if (preview) {
                if (item.photo_url) {
                    preview.src = item.photo_url;
                    preview.style.display = 'block';
                } else {
                    preview.src = '';
                    preview.style.display = 'none';
                }
            }
        } else {
            // Mode création
            title.textContent = 'Nouveau';

            // Réinitialisation des champs communs
            var fields = ['id', 'title', 'desc', 'photo_id'];
            
            // Ajout des champs spécifiques selon le type
            switch (prefix) {
                case 'act':
                    fields = fields.concat(['address_line1', 'postal_code', 'city']);
                    break;
                case 'acc':
                    fields = fields.concat(['address_line1', 'postal_code', 'city']);
                    break;
                case 'article':
                    // champs spécifiques articles si nécessaire
                    break;
                case 'famille':
                    // champs spécifiques famille si nécessaire
                    break;
            }

            // Vider tous les champs
            fields.forEach(function(f) {
                setVal('wpsd_' + prefix + '_' + f, '');
            });

            // Réinitialisation spécifique par type
            switch (prefix) {
                case 'act':
                    var hasAccCheckbox = document.getElementById('wpsd_act_has_accommodation');
                    if (hasAccCheckbox) hasAccCheckbox.checked = false;
                    
                    var accCap = document.getElementById('wpsd_act_acc_capacity');
                    if (accCap) accCap.value = '';
                    break;

                case 'acc':
                    setVal('wpsd_acc_adults', '');
                    setVal('wpsd_acc_children', '');
                    break;

                case 'article':
                    // Réinitialisation champs articles
                    break;

                case 'famille':
                    // Réinitialisation champs famille
                    break;
            }

            // Cacher la prévisualisation photo
            var preview = document.getElementById('wpsd_' + prefix + '_photo_preview');
            if (preview) {
                preview.src = '';
                preview.style.display = 'none';
            }
        }

        // Afficher la modale
        modal.setAttribute('aria-hidden', 'false');
    }

    function close(prefix) {
        var modal = document.getElementById('wpsd_' + prefix + '_modal');
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function setVal(id, value) {
        var el = document.getElementById(id);
        if (el) {
            if (el.type === 'checkbox') {
                el.checked = !!value;
            } else {
                el.value = value !== undefined && value !== null ? value : '';
            }
        }
    }

    function getVal(id, fallback) {
        fallback = fallback || '';
        var el = document.getElementById(id);
        if (!el) return fallback;
        
        if (el.type === 'checkbox') {
            return el.checked ? '1' : '0';
        }
        return el.value || fallback;
    }

    function getInt(id, fallback) {
        fallback = fallback || 0;
        var v = getVal(id);
        var n = parseInt(v, 10);
        return isNaN(n) ? fallback : n;
    }

    return {
        init: init,
        open: open,
        close: close,
        setVal: setVal,
        getVal: getVal,
        getInt: getInt
    };
})();