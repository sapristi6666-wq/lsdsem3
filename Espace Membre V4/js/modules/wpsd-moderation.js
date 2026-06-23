const WPSD_Moderation = (function() {
    let rolesState = { search: '', roleFilter: '', noRole: 0, page: 1, perPage: 20 };

    function init() {
        WPSD_State.on('activeTab', tab => { if (tab === 'moderation') loadPending(); });

        document.getElementById('wpsd-moderation-pending')?.addEventListener('click', async (e) => {
            const t = e.target;
            const approveId = t.getAttribute?.('data-mod-approve');
            if (approveId) {
                WPSD_Toast.show('Validation...', 'info');
                const r = await WPSD_API.post('/admin/approve-registration', { id: parseInt(approveId) });
                if (r.ok) WPSD_Toast.show('Inscription validée !', 'success');
                else WPSD_Toast.show(r.error || 'Erreur', 'error');
                loadPending();
                return;
            }
            const rejectId = t.getAttribute?.('data-mod-reject');
            if (rejectId) {
                if (!confirm('Refuser cette inscription ? Le paiement sera remboursé.')) return;
                WPSD_Toast.show('Refus et remboursement...', 'info');
                const r = await WPSD_API.post('/admin/reject-registration', { id: parseInt(rejectId) });
                if (r.ok) WPSD_Toast.show('Inscription refusée et remboursée.', 'success');
                else WPSD_Toast.show(r.error || 'Erreur', 'error');
                loadPending();
                return;
            }
        });
    }

    async function loadPending() {
        const box = document.getElementById('wpsd-moderation-pending');
        if (!box) return;
        WPSD_State.setLoading('moderation', true);
        const r = await WPSD_API.get('/admin/pending-registrations');
        if (!r.items?.length) { box.innerHTML = '<p class="wpsd-hint">Aucune inscription en attente.</p>'; WPSD_State.setLoading('moderation', false); return; }
        box.innerHTML = r.items.map(function(item) {
            return '<div class="wpsd-card" style="margin-bottom:10px;padding:14px;"><strong>' + WPSD_Utils.escapeHtml(item.prenom) + ' ' + WPSD_Utils.escapeHtml(item.nom) + '</strong><div>' + WPSD_Utils.escapeHtml(item.email) + ' · ' + WPSD_Utils.escapeHtml(item.role) + ' · ' + (item.plan === 'member' ? 'Individuel' : 'Famille') + '</div><div style="font-size:11px;color:#888;">Inscrit le ' + new Date(item.created_at).toLocaleDateString() + '</div><div style="margin-top:8px;display:flex;gap:8px;"><button class="wpsd-btn wpsd-btn-sm wpsd-primary" data-mod-approve="' + item.id + '">Approuver</button><button class="wpsd-btn wpsd-btn-sm" data-mod-reject="' + item.id + '">Refuser</button></div></div>';
        }).join('');
        WPSD_State.setLoading('moderation', false);
    }

    async function loadRoles() {
        const box = document.getElementById('wpsd-moderation-roles');
        if (!box) return;
        WPSD_State.setLoading('moderation-roles', true);
        box.innerHTML = '<div class="wpsd-roles-toolbar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">' +
            '<input type="text" id="wpsd-user-search" placeholder="Rechercher..." value="' + WPSD_Utils.escapeHtml(rolesState.search) + '">' +
            '<select id="wpsd-role-filter">' +
                '<option value="">Tous</option>' +
                '<option value="is_itinerant" ' + (rolesState.roleFilter === 'is_itinerant' ? 'selected' : '') + '>Itinérants</option>' +
                '<option value="is_passeur" ' + (rolesState.roleFilter === 'is_passeur' ? 'selected' : '') + '>Passeurs</option>' +
                '<option value="is_hebergeur" ' + (rolesState.roleFilter === 'is_hebergeur' ? 'selected' : '') + '>Hébergeurs</option>' +
                '<option value="is_sympathisant" ' + (rolesState.roleFilter === 'is_sympathisant' ? 'selected' : '') + '>Sympathisants</option>' +
            '</select>' +
            '<button class="wpsd-btn wpsd-primary" id="wpsd-search-roles-btn">Rechercher</button>' +
        '</div>' +
        '<div id="wpsd-roles-list"></div>' +
        '<div id="wpsd-roles-pagination"></div>';
        document.getElementById('wpsd-search-roles-btn')?.addEventListener('click', function() {
            rolesState.search = document.getElementById('wpsd-user-search')?.value || '';
            rolesState.roleFilter = document.getElementById('wpsd-role-filter')?.value || '';
            rolesState.page = 1;
            searchRoles();
        });
        await searchRoles();
        WPSD_State.setLoading('moderation-roles', false);
    }

    async function searchRoles() {
        var listBox = document.getElementById('wpsd-roles-list');
        if (!listBox) return;
        listBox.innerHTML = '<p class="wpsd-hint">Chargement...</p>';
        var params = new URLSearchParams({ search: rolesState.search, role: rolesState.roleFilter, no_role: rolesState.noRole, page: rolesState.page, per_page: rolesState.perPage, orderby: 'display_name', order: 'ASC' });
        var r = await WPSD_API.get('/admin/users-by-role?' + params.toString());
        if (!r.items?.length) { listBox.innerHTML = '<p class="wpsd-hint">Aucun utilisateur trouvé.</p>'; return; }

        listBox.innerHTML = r.items.map(function(u) {
            var roles = [
                { key: 'is_itinerant', label: 'Itinérant', checked: u.is_itinerant },
                { key: 'is_passeur', label: 'Passeur', checked: u.is_passeur },
                { key: 'is_hebergeur', label: 'Hébergeur', checked: u.is_hebergeur },
                { key: 'is_sympathisant', label: 'Sympathisant', checked: u.is_sympathisant }
            ];
            var rolesHtml = roles.map(function(rl) {
                return '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px;"><input type="checkbox" data-role="' + rl.key + '" data-user="' + u.id + '" ' + (rl.checked ? 'checked' : '') + '>' + rl.label + '</label>';
            }).join('');
            return '<div class="wpsd-card" style="padding:14px;margin-bottom:10px;" id="user-row-' + u.id + '">' +
                '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">' +
                    '<div><strong>' + WPSD_Utils.escapeHtml(u.first_name) + ' ' + WPSD_Utils.escapeHtml(u.last_name) + '</strong>' + (u.is_admin ? ' <span class="wpsd-badge wpsd-badge-completed">Admin</span>' : '') + (u.is_moderator && !u.is_admin ? ' <span class="wpsd-badge wpsd-badge-approved">Modérateur</span>' : '') + '<div style="font-size:12px;">' + WPSD_Utils.escapeHtml(u.email) + '</div></div>' +
                    '<a href="' + WPSD_Utils.escapeHtml(u.edit_url) + '" target="_blank" class="wpsd-btn wpsd-btn-sm">Voir fiche</a>' +
                '</div>' +
                '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #e0e4e0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">' +
                    rolesHtml +
                    '<button class="wpsd-btn wpsd-btn-sm wpsd-primary" data-save-roles="' + u.id + '">Enregistrer</button>' +
                    '<span id="roles-msg-' + u.id + '" style="font-size:12px;"></span>' +
                '</div>' +
            '</div>';
        }).join('');

        // Comportement radio : une seule checkbox cochée à la fois
        listBox.querySelectorAll('input[data-role]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                if (this.checked) {
                    var row = this.closest('[id^="user-row-"]');
                    row.querySelectorAll('input[data-role]').forEach(function(other) {
                        if (other !== cb) other.checked = false;
                    });
                }
            });
        });

        // Bouton Enregistrer : envoyer UNE SEULE requête avec le rôle coché
        listBox.querySelectorAll('[data-save-roles]').forEach(function(btn) {
            btn.addEventListener('click', async function() {
                var uid = parseInt(btn.getAttribute('data-save-roles'));
                var row = document.getElementById('user-row-' + uid);
                if (!row) return;
                var checks = row.querySelectorAll('input[data-role]');

                var activeRole = 'none';
                checks.forEach(function(cb) {
                    if (cb.checked) activeRole = cb.getAttribute('data-role').replace('is_', '');
                });

                WPSD_Toast.show('Enregistrement...', 'info');
                var r = await WPSD_API.post('/admin/set-user-role', { user_id: uid, role: activeRole });
                var msg = document.getElementById('roles-msg-' + uid);
                if (msg) { msg.textContent = r.ok ? 'Enregistré !' : 'Erreur'; msg.style.color = r.ok ? '#166534' : '#c73d2a'; }
                if (r.ok) WPSD_Toast.show('Rôle mis à jour', 'success');
                else WPSD_Toast.show(r.error || 'Erreur', 'error');
                setTimeout(function() { if (msg) msg.textContent = ''; }, 2000);
            });
        });

        // Pagination
        var pagBox = document.getElementById('wpsd-roles-pagination');
        if (pagBox && r.total_pages > 1) {
            var html = '<div style="display:flex;gap:6px;justify-content:center;margin-top:12px;">';
            for (var i = 1; i <= r.total_pages; i++) {
                html += '<button class="wpsd-btn wpsd-btn-sm ' + (i === r.page ? 'wpsd-primary' : '') + '" data-roles-page="' + i + '">' + i + '</button>';
            }
            html += '</div>';
            pagBox.innerHTML = html;
            pagBox.querySelectorAll('[data-roles-page]').forEach(function(btn) {
                btn.addEventListener('click', function() { rolesState.page = parseInt(btn.dataset.rolesPage); searchRoles(); });
            });
        }
    }

    document.querySelector('.wpsd-moderation-subtab[data-subtab="roles"]')?.addEventListener('click', function() { loadRoles(); });

    return { init: init, loadPending: loadPending };
})();