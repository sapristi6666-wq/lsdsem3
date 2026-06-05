const WPSD_Articles = (function() {
    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'articles') load(); });
    }

    function openNew() {
        WPSD_Modals.open('art', null);
    }

    async function load() {
        var box = WPSD_Utils.$('wpsd_articles_list');
        if (!box) return;
        WPSD_State.setLoading('articles', true);
        var data = await WPSD_API.get('/articles');
        var items = data.items || [];
        if (!items.length) { box.innerHTML = '<p class="wpsd-hint">Aucun récit.</p>'; WPSD_State.setLoading('articles', false); return; }
        box.innerHTML = items.map(function(a) { return '<div class="wpsd-card" style="margin-bottom:12px;"><strong>' + WPSD_Utils.escapeHtml(a.title) + '</strong> ' + WPSD_Utils.statusBadge(a.status, a.status_label) + '<p>' + WPSD_Utils.escapeHtml(a.content||'').substring(0,150) + '...</p><button class="wpsd-btn wpsd-btn-sm" data-art-edit="' + a.id + '">Modifier</button><button class="wpsd-btn wpsd-btn-sm" data-art-del="' + a.id + '">Supprimer</button></div>'; }).join('');
        box.querySelectorAll('[data-art-edit]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var found = items.find(function(a) { return a.id == btn.dataset.artEdit; });
                WPSD_Modals.open('art', found);
            });
        });
        box.querySelectorAll('[data-art-del]').forEach(function(btn) {
            btn.addEventListener('click', function() { if (confirm('Supprimer ?')) { WPSD_API.delete('/articles/' + btn.dataset.artDel); WPSD_Toast.show('Supprimé', 'success'); load(); } });
        });
        WPSD_State.setLoading('articles', false);
    }

    async function handleSave() {
        var id = WPSD_Modals.getVal('wpsd_art_id');
        var payload = {
            title: WPSD_Modals.getVal('wpsd_art_title'),
            content: WPSD_Modals.getVal('wpsd_art_content'),
            photo_id: WPSD_Modals.getInt('wpsd_art_photo_id')
        };
        WPSD_Toast.show('Enregistrement...', 'info');
        var r = id ? await WPSD_API.put('/articles/' + id, payload) : await WPSD_API.post('/articles', payload);
        if (r.ok) { WPSD_Modals.close('art'); WPSD_Toast.show('Enregistré !', 'success'); load(); }
        else WPSD_Toast.show(r.error || 'Erreur', 'error');
    }

    return { init: init, load: load, handleSave: handleSave, openNew: openNew };
})();