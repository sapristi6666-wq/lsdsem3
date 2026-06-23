const WPSD_Profile = (function() {
    let panel = null, ready = false;
    let pendingPhotoBase64 = null;
    let saveTimeout = null;
    let currentSubtab = 'profile';

    // Crop state
    let cropImage = null, cropCanvas = null, cropOverlayCanvas = null;
    let cropX = 0, cropY = 0, cropSize = 200;
    let isDragging = false, isResizing = false;
    let dragStartX = 0, dragStartY = 0;
    let cropStartX = 0, cropStartY = 0, cropStartSize = 0;
    let imgNaturalW = 0, imgNaturalH = 0;
    let displayW = 0, displayH = 0;

    function init() {
        if (ready) return;
        panel = document.getElementById('wpsd-panel-profile');
        if (!panel) return;
        ready = true;

        bindSubtabEvents();
        bindPhotoEvents();
        bindAutoSave();
        loadData();
        
        // ✅ Charger les données selon le premier sous-onglet visible
        loadCurrentSubtabData();
    }

    // ==================== SOUS-ONGLETS ====================

    function bindSubtabEvents() {
        var btns = panel.querySelectorAll('.wpsd-subtab-btn');
        var panels = panel.querySelectorAll('.wpsd-subtab-panel');

        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = this.dataset.subtab;
                currentSubtab = target;

                btns.forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');

                panels.forEach(function(p) { p.classList.remove('active'); });
                var activePanel = document.getElementById('wpsd-subtab-' + target);
                if (activePanel) {
                    activePanel.classList.add('active');
                }

                // ✅ Recharger les données spécifiques au sous-onglet
                loadCurrentSubtabData();
            });
        });
    }

    /**
     * ✅ Charge les données selon le sous-onglet actif
     */
    function loadCurrentSubtabData() {
        switch (currentSubtab) {
            case 'family':
                loadFamily();
                break;
            case 'subscription':
                loadSubscription();
                break;
            default:
                // profile et company n'ont pas de chargement spécifique
                break;
        }
    }

    function switchToSubtab(name) {
        var btn = panel.querySelector('[data-subtab="' + name + '"]');
        if (btn) btn.click();
    }

    // ==================== CHARGEMENT DONNÉES ====================

    function loadData() {
        fetch(WPSD.restBase + '/profile', {
            headers: { 'X-WP-Nonce': WPSD.nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!panel) return;
            Object.keys(data).forEach(function(key) {
                if (key === 'wpsd_profile_photo_url') return;
                var field = panel.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = data[key] || '';
                }
            });
        })
        .catch(function(err) {
            console.error('WPSD Profile: erreur chargement', err);
        });
    }

    function loadFamily() {
        var box = document.getElementById('wpsd-family-list');
        if (!box) return;

        fetch(WPSD.restBase + '/family', {
            headers: { 'X-WP-Nonce': WPSD.nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var members = data.items || data || [];
            if (!members.length) {
                box.innerHTML = '<p class="wpsd-hint">Aucun membre de la famille.</p>';
                return;
            }
            box.innerHTML = members.map(function(m) {
                return '<div class="wpsd-card wpsd-family-card" style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">' +
                    '<div>' +
                        '<strong>' + WPSD_Utils.escapeHtml(m.first_name || '') + ' ' + WPSD_Utils.escapeHtml(m.last_name || '') + '</strong>' +
                        (m.email ? '<br><span style="font-size:13px;color:#666;">' + WPSD_Utils.escapeHtml(m.email) + '</span>' : '') +
                    '</div>' +
                    '<div style="display:flex;gap:6px;">' +
                        '<button class="wpsd-btn wpsd-btn-sm" data-fam-edit="' + m.id + '">Modifier</button>' +
                        '<button class="wpsd-btn wpsd-btn-sm" data-fam-del="' + m.id + '">Supprimer</button>' +
                    '</div>' +
                '</div>';
            }).join('');

            box.querySelectorAll('[data-fam-edit]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var found = members.find(function(m) { return m.id == btn.dataset.famEdit; });
                    if (found && typeof WPSD_Modals !== 'undefined') {
                        WPSD_Modals.open('fam', found);
                    }
                });
            });
            box.querySelectorAll('[data-fam-del]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    deleteFamilyMember(btn.dataset.famDel);
                });
            });
        })
        .catch(function(err) {
            console.error('WPSD Profile: erreur chargement famille', err);
            box.innerHTML = '<p class="wpsd-hint">Erreur de chargement.</p>';
        });
    }

    function deleteFamilyMember(id) {
        if (!confirm('Supprimer ce membre de la famille ?')) return;
        WPSD_Toast.show('Suppression...', 'info');
        fetch(WPSD.restBase + '/family/' + id, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': WPSD.nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                WPSD_Toast.show('Membre supprimé', 'success');
                loadFamily();
            } else {
                WPSD_Toast.show(data.error || 'Erreur', 'error');
            }
        })
        .catch(function() {
            WPSD_Toast.show('Erreur de connexion', 'error');
        });
    }

    function loadSubscription() {
        var box = document.getElementById('wpsd-subscription-info');
        if (!box) return;

        fetch(WPSD.restBase + '/subscription', {
            headers: { 'X-WP-Nonce': WPSD.nonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.status) {
                box.innerHTML = '<p class="wpsd-hint">Aucune information d\'adhésion disponible.</p>';
                return;
            }

            var statusLabel = '';
            switch (data.status) {
                case 'active':   statusLabel = 'Actif'; break;
                case 'trialing': statusLabel = 'Période d\'essai'; break;
                case 'past_due': statusLabel = 'En retard'; break;
                case 'canceled': statusLabel = 'Annulé'; break;
                default:         statusLabel = data.status;
            }

            box.innerHTML = '<div class="wpsd-card">' +
                '<p><strong>Statut :</strong> <span style="color:' + (data.status === 'active' ? '#005247' : '#c0392b') + ';">' + statusLabel + '</span></p>' +
                (data.current_period_end ? '<p><strong>Prochaine échéance :</strong> ' + new Date(data.current_period_end * 1000).toLocaleDateString('fr-FR') + '</p>' : '') +
                (data.plan_name ? '<p><strong>Formule :</strong> ' + WPSD_Utils.escapeHtml(data.plan_name) + '</p>' : '') +
                (data.portal_url ? '<p><a href="' + data.portal_url + '" target="_blank" class="wpsd-btn wpsd-btn-sm">Gérer mon abonnement</a></p>' : '') +
            '</div>';
        })
        .catch(function(err) {
            console.error('WPSD Profile: erreur chargement adhésion', err);
            box.innerHTML = '<p class="wpsd-hint">Erreur de chargement.</p>';
        });
    }

    // ==================== AUTO-SAVE ====================

    function bindAutoSave() {
        var fields = panel.querySelectorAll('[name]');
        fields.forEach(function(field) {
            field.addEventListener('input', function() {
                scheduleSave();
            });
            field.addEventListener('change', function() {
                scheduleSave();
            });
        });
    }

    function scheduleSave() {
        if (saveTimeout) clearTimeout(saveTimeout);
        saveTimeout = setTimeout(save, 1500);
    }

    // ==================== PHOTO ====================

    function bindPhotoEvents() {
        var fileInput = document.getElementById('wpsd-photo-input');
        var uploadBtn = document.getElementById('wpsd-upload-photo');
        var preview = document.getElementById('wpsd-photo-preview');

        if (!fileInput || !uploadBtn) return;

        uploadBtn.addEventListener('click', function() {
            fileInput.click();
        });

        fileInput.addEventListener('change', function() {
            var file = fileInput.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                WPSD_Toast.show('La photo ne doit pas dépasser 5 Mo.', 'error');
                fileInput.value = '';
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                openCropModal(e.target.result);
            };
            reader.readAsDataURL(file);
            fileInput.value = '';
        });

        var removeBtn = document.getElementById('wpsd-remove-photo');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                pendingPhotoBase64 = null;
                preview.innerHTML = '<span class="wpsd-photo-placeholder">Photo</span>';
                removeBtn.remove();
                save();
            });
        }
    }

    // ==================== CROP MODAL (inchangé) ====================

    function openCropModal(imageSrc) {
        var old = document.getElementById('wpsd-crop-modal');
        if (old) old.remove();

        var modal = document.createElement('div');
        modal.id = 'wpsd-crop-modal';
        modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;';

        var img = new Image();
        img.onload = function() {
            imgNaturalW = img.naturalWidth;
            imgNaturalH = img.naturalHeight;

            var maxW = window.innerWidth - 80;
            var maxH = window.innerHeight - 200;
            var scale = Math.min(maxW / imgNaturalW, maxH / imgNaturalH, 1);
            displayW = Math.round(imgNaturalW * scale);
            displayH = Math.round(imgNaturalH * scale);

            cropCanvas = document.createElement('canvas');
            cropCanvas.width = displayW;
            cropCanvas.height = displayH;
            cropCanvas.style.cssText = 'display:block;border-radius:4px;';
            cropImage = img;

            cropOverlayCanvas = document.createElement('canvas');
            cropOverlayCanvas.width = displayW;
            cropOverlayCanvas.height = displayH;
            cropOverlayCanvas.style.cssText = 'position:absolute;top:0;left:0;cursor:move;';

            cropSize = Math.min(displayW, displayH, 280);
            cropX = Math.round((displayW - cropSize) / 2);
            cropY = Math.round((displayH - cropSize) / 2);

            var wrapper = document.createElement('div');
            wrapper.style.cssText = 'position:relative;display:inline-block;line-height:0;';
            wrapper.appendChild(cropCanvas);
            wrapper.appendChild(cropOverlayCanvas);

            redrawCropCanvas();
            redrawCropOverlay();

            var bar = document.createElement('div');
            bar.style.cssText = 'display:flex;gap:12px;margin-top:16px;align-items:center;';
            var hint = document.createElement('span');
            hint.style.cssText = 'color:#ccc;font-size:13px;margin-right:auto;';
            hint.textContent = 'Déplacez le cadre ou redimensionnez-le par le coin';
            var validateBtn = document.createElement('button');
            validateBtn.className = 'wpsd-btn wpsd-primary';
            validateBtn.textContent = 'Valider';
            var cancelBtn = document.createElement('button');
            cancelBtn.className = 'wpsd-btn';
            cancelBtn.textContent = 'Annuler';
            bar.appendChild(hint);
            bar.appendChild(cancelBtn);
            bar.appendChild(validateBtn);

            modal.appendChild(wrapper);
            modal.appendChild(bar);
            document.body.appendChild(modal);

            cancelBtn.addEventListener('click', function() { modal.remove(); });
            validateBtn.addEventListener('click', function() {
                applyCrop();
                modal.remove();
            });

            bindCropEvents(cropOverlayCanvas);
        };
        img.src = imageSrc;
    }

    function redrawCropCanvas() {
        if (!cropCanvas || !cropImage) return;
        var ctx = cropCanvas.getContext('2d');
        ctx.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
        ctx.drawImage(cropImage, 0, 0, cropCanvas.width, cropCanvas.height);
    }

    function redrawCropOverlay() {
        if (!cropOverlayCanvas) return;
        var ctx = cropOverlayCanvas.getContext('2d');
        var w = cropOverlayCanvas.width;
        var h = cropOverlayCanvas.height;
        ctx.clearRect(0, 0, w, h);

        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(0, 0, w, h);

        ctx.clearRect(cropX, cropY, cropSize, cropSize);

        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.setLineDash([8, 4]);
        ctx.strokeRect(cropX + 1, cropY + 1, cropSize - 2, cropSize - 2);
        ctx.setLineDash([]);

        var handleR = 8;
        var hx = cropX + cropSize;
        var hy = cropY + cropSize;
        ctx.fillStyle = '#e0b912';
        ctx.beginPath();
        ctx.arc(hx, hy, handleR, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.stroke();

        ctx.strokeStyle = 'rgba(255,255,255,0.25)';
        ctx.lineWidth = 1;
        var third = cropSize / 3;
        ctx.beginPath();
        ctx.moveTo(cropX + third, cropY);
        ctx.lineTo(cropX + third, cropY + cropSize);
        ctx.moveTo(cropX + third * 2, cropY);
        ctx.lineTo(cropX + third * 2, cropY + cropSize);
        ctx.moveTo(cropX, cropY + third);
        ctx.lineTo(cropX + cropSize, cropY + third);
        ctx.moveTo(cropX, cropY + third * 2);
        ctx.lineTo(cropX + cropSize, cropY + third * 2);
        ctx.stroke();
    }

    function bindCropEvents(canvas) {
        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            return { x: e.clientX - rect.left, y: e.clientY - rect.top };
        }

        function isOnHandle(mx, my) {
            var handleSize = 18;
            return mx >= cropX + cropSize - handleSize && mx <= cropX + cropSize + handleSize &&
                   my >= cropY + cropSize - handleSize && my <= cropY + cropSize + handleSize;
        }

        function isInsideCrop(mx, my) {
            return mx >= cropX && mx <= cropX + cropSize && my >= cropY && my <= cropY + cropSize;
        }

        function updateCursor(mx, my) {
            if (isOnHandle(mx, my)) {
                canvas.style.cursor = 'nwse-resize';
            } else if (isInsideCrop(mx, my)) {
                canvas.style.cursor = 'move';
            } else {
                canvas.style.cursor = 'default';
            }
        }

        canvas.addEventListener('mousedown', function(e) {
            var pos = getPos(e);
            var mx = pos.x, my = pos.y;

            if (isOnHandle(mx, my)) {
                isResizing = true;
                cropStartSize = cropSize;
                cropStartX = cropX;
                cropStartY = cropY;
                dragStartX = mx;
                dragStartY = my;
            } else if (isInsideCrop(mx, my)) {
                isDragging = true;
                cropStartX = cropX;
                cropStartY = cropY;
                dragStartX = mx;
                dragStartY = my;
            }
            e.preventDefault();
        });

        canvas.addEventListener('mousemove', function(e) {
            var pos = getPos(e);
            var mx = pos.x, my = pos.y;

            if (isDragging) {
                cropX = Math.max(0, Math.min(displayW - cropSize, cropStartX + mx - dragStartX));
                cropY = Math.max(0, Math.min(displayH - cropSize, cropStartY + my - dragStartY));
                redrawCropOverlay();
            } else if (isResizing) {
                var dx = mx - dragStartX;
                var dy = my - dragStartY;
                var newSize = Math.max(50, Math.min(
                    displayW - cropStartX,
                    displayH - cropStartY,
                    cropStartSize + Math.max(dx, dy)
                ));
                cropSize = newSize;
                redrawCropOverlay();
            } else {
                updateCursor(mx, my);
            }
        });

        window.addEventListener('mouseup', function() {
            isDragging = false;
            isResizing = false;
        });

        canvas.addEventListener('touchstart', function(e) {
            if (e.touches.length !== 1) return;
            var pos = getPos(e.touches[0]);
            var mx = pos.x, my = pos.y;
            var handleSize = 22;

            if (mx >= cropX + cropSize - handleSize && mx <= cropX + cropSize + handleSize &&
                my >= cropY + cropSize - handleSize && my <= cropY + cropSize + handleSize) {
                isResizing = true;
                cropStartSize = cropSize;
                cropStartX = cropX;
                cropStartY = cropY;
                dragStartX = mx;
                dragStartY = my;
            } else if (mx >= cropX && mx <= cropX + cropSize && my >= cropY && my <= cropY + cropSize) {
                isDragging = true;
                cropStartX = cropX;
                cropStartY = cropY;
                dragStartX = mx;
                dragStartY = my;
            }
            e.preventDefault();
        });

        window.addEventListener('touchmove', function(e) {
            if (!isDragging && !isResizing) return;
            var pos = getPos(e.touches[0]);
            var mx = pos.x, my = pos.y;

            if (isDragging) {
                cropX = Math.max(0, Math.min(displayW - cropSize, cropStartX + mx - dragStartX));
                cropY = Math.max(0, Math.min(displayH - cropSize, cropStartY + my - dragStartY));
            } else if (isResizing) {
                var dx = mx - dragStartX;
                var dy = my - dragStartY;
                var newSize = Math.max(50, Math.min(
                    displayW - cropStartX,
                    displayH - cropStartY,
                    cropStartSize + Math.max(dx, dy)
                ));
                cropSize = newSize;
            }
            redrawCropOverlay();
        });

        window.addEventListener('touchend', function() {
            isDragging = false;
            isResizing = false;
        });
    }

    function applyCrop() {
        if (!cropImage) return;

        var scaleX = imgNaturalW / displayW;
        var scaleY = imgNaturalH / displayH;
        var sx = Math.round(cropX * scaleX);
        var sy = Math.round(cropY * scaleY);
        var sSize = Math.round(cropSize * scaleX);

        var output = document.createElement('canvas');
        output.width = 300;
        output.height = 300;
        var ctx = output.getContext('2d');
        ctx.drawImage(cropImage, sx, sy, sSize, sSize, 0, 0, 300, 300);

        pendingPhotoBase64 = output.toDataURL('image/jpeg', 0.85);

        var preview = document.getElementById('wpsd-photo-preview');
        if (preview) {
            preview.innerHTML = '<img src="' + pendingPhotoBase64 + '" alt="Photo de profil">';
        }
        ensureRemoveBtn();
        save();
    }

    function ensureRemoveBtn() {
        var uploadBtn = document.getElementById('wpsd-upload-photo');
        if (!uploadBtn) return;
        if (document.getElementById('wpsd-remove-photo')) return;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'wpsd-btn wpsd-btn-sm wpsd-btn-remove';
        btn.id = 'wpsd-remove-photo';
        btn.textContent = 'Supprimer';
        btn.addEventListener('click', function() {
            var previewEl = document.getElementById('wpsd-photo-preview');
            var fileInputEl = document.getElementById('wpsd-photo-input');
            pendingPhotoBase64 = null;
            if (fileInputEl) fileInputEl.value = '';
            if (previewEl) previewEl.innerHTML = '<span class="wpsd-photo-placeholder">Photo</span>';
            btn.remove();
            save();
        });
        uploadBtn.parentNode.insertBefore(btn, uploadBtn.nextSibling);
    }

    // ==================== SAVE ====================

    function save() {
        var payload = {};
        var fields = panel.querySelectorAll('[name]');
        fields.forEach(function(field) {
            if (field.name) {
                payload[field.name] = field.value;
            }
        });

        if (pendingPhotoBase64) {
            payload.wpsd_profile_photo_base64 = pendingPhotoBase64;
        }

        var removeBtn = document.getElementById('wpsd-remove-photo');
        var preview = document.getElementById('wpsd-photo-preview');
        if (!removeBtn && preview && preview.querySelector('.wpsd-photo-placeholder')) {
            payload.wpsd_remove_photo = true;
        }

        fetch(WPSD.restBase + '/profile', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': WPSD.nonce
            },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                WPSD_Toast.show('Profil enregistré', 'success', 2000);
                pendingPhotoBase64 = null;
                if (data.photo_url && preview) {
                    preview.innerHTML = '<img src="' + data.photo_url + '" alt="Photo de profil">';
                    ensureRemoveBtn();
                }
            } else {
                WPSD_Toast.show(data.error || 'Erreur lors de l\'enregistrement', 'error');
            }
        })
        .catch(function(err) {
            console.error('WPSD Profile: erreur sauvegarde', err);
            WPSD_Toast.show('Erreur de connexion', 'error');
        });
    }

    // ==================== INIT ====================

    WPSD_State.on('activeTab', function(tab) {
        if (tab === 'profile') {
            setTimeout(init, 200);
        }
    });

    return {
        init: init,
        loadData: loadData,
        save: save,
        switchToSubtab: switchToSubtab,
        loadFamily: loadFamily,
        loadSubscription: loadSubscription
    };
})();