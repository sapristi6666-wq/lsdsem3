const WPSD_Modals = (function() {
    function init() {
        document.querySelectorAll('.wpsd-modal').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.setAttribute('aria-hidden', 'true');
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.wpsd-modal[aria-hidden="false"]').forEach(function(m) { m.setAttribute('aria-hidden', 'true'); });
            }
        });
    }

    function open(prefix, item) {
        var modal = document.getElementById('wpsd_' + prefix + '_modal');
        var title = document.getElementById('wpsd_' + prefix + '_modal_title');
        if (!modal || !title) return;

        if (item) {
            title.textContent = 'Modifier';
            setVal('wpsd_' + prefix + '_id', item.id);
            setVal('wpsd_' + prefix + '_title', item.title);
            setVal('wpsd_' + prefix + '_desc', item.description || '');
            setVal('wpsd_' + prefix + '_address_line1', item.address_line1 || '');
            setVal('wpsd_' + prefix + '_postal_code', item.postal_code || '');
            setVal('wpsd_' + prefix + '_city', item.city || '');
            if (prefix === 'acc') { setVal('wpsd_acc_adults', item.capacity_adults || 0); setVal('wpsd_acc_children', item.capacity_children || 0); }
            setVal('wpsd_' + prefix + '_photo_id', item.photo_id || '');
            var preview = document.getElementById('wpsd_' + prefix + '_photo_preview');
            if (preview && item.photo_url) { preview.src = item.photo_url; preview.style.display = 'block'; }
        } else {
            title.textContent = 'Nouveau';
            ['id','title','desc','address_line1','postal_code','city','photo_id'].forEach(function(f) { setVal('wpsd_' + prefix + '_' + f, ''); });
            if (prefix === 'acc') { setVal('wpsd_acc_adults', ''); setVal('wpsd_acc_children', ''); }
            var preview = document.getElementById('wpsd_' + prefix + '_photo_preview');
            if (preview) { preview.src = ''; preview.style.display = 'none'; }
        }
        modal.setAttribute('aria-hidden', 'false');
    }

    function close(prefix) {
        var modal = document.getElementById('wpsd_' + prefix + '_modal');
        if (modal) modal.setAttribute('aria-hidden', 'true');
    }

    function setVal(id, value) { var el = document.getElementById(id); if (el) el.value = value; }
    function getVal(id, fallback) { fallback = fallback || ''; var el = document.getElementById(id); return el ? (el.value || fallback) : fallback; }
    function getInt(id, fallback) { fallback = fallback || 0; var v = getVal(id); var n = parseInt(v, 10); return isNaN(n) ? fallback : n; }

    return { init: init, open: open, close: close, setVal: setVal, getVal: getVal, getInt: getInt };
})();