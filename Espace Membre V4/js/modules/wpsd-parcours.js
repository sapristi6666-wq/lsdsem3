const WPSD_Parcours = (function() {
    let map = null, markers = null, leafletInitialized = false;
    let state = {
        parcoursId: null,
        startDate: '',
        steps: [],
        activeStepIndex: 0,
        etapes: [] // contient les vraies étapes depuis la BDD
    };

    // ==================== INITIALISATION ====================

    async function init() {
        WPSD_State.on('activeTab', tab => {
            if (tab === 'parcours') {
                activate();
            }
        });

        // Initialisation retardée de la carte (quand le panneau est visible)
        setTimeout(() => {
            const el = document.getElementById('wpsd_parcours_map');
            if (el && !map) {
                initMap(el);
            }
        }, 800);

        bindControls();
        await loadParcours();
        renderTimeline();
    }

    function initMap(el) {
        if (leafletInitialized) return;
        leafletInitialized = true;

        map = L.map('wpsd_parcours_map', {
            center: [46.6, 2.5],
            zoom: 6,
            zoomControl: true,
        });
        L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> France'
        }).addTo(map);
        markers = L.layerGroup().addTo(map);
    }

    // ==================== ACTIVATION ====================

    function activate() {
        // Initialiser la carte si pas fait
        const el = document.getElementById('wpsd_parcours_map');
        if (el && !map) initMap(el);

        if (map) {
            setTimeout(() => map.invalidateSize(), 250);
        }

        const elStart = document.getElementById('wpsd_parcours_start_date');
        if (elStart && !elStart.value && state.startDate) {
            elStart.value = state.startDate;
        }

        // Recharger les données depuis la BDD si on a un parcoursId
        if (state.parcoursId) {
            loadParcours();
        }

        loadMapDisponibilites();
    }

    // ==================== LIENS ====================

    function bindControls() {
        // Date de début
        const startInput = document.getElementById('wpsd_parcours_start_date');
        if (startInput) {
            startInput.addEventListener('change', async () => {
                state.startDate = startInput.value;
                if (state.parcoursId) {
                    await apiUpdateParcours({ date_debut_globale: state.startDate });
                    await recalcAllDates();
                }
                updateSummary();
            });
        }

        // Bouton ajouter une étape
        document.getElementById('wpsd_parcours_add_step')?.addEventListener('click', addStep);

                // Bouton envoyer les demandes
        document.getElementById('wpsd_parcours_submit')?.addEventListener('click', submitDemands);

        // Bouton payer
        document.getElementById('wpsd_parcours_pay')?.addEventListener('click', initPayment);

        // Bouton vider
        document.getElementById('wpsd_parcours_clear')?.addEventListener('click', async () => {
            if (!confirm('Vider tout le parcours ? Cela supprimera les étapes et les réservations en attente.')) return;
            if (state.parcoursId) {
                // Supprimer les étapes
                for (const etape of state.etapes) {
                    if (etape.id) {
                        await WPSD_API.deleteRequest(`/wpsd/v2/etapes/${etape.id}`);
                    }
                }
                // Supprimer les réservations en pending
                if (state.parcoursId) {
                    const r = await WPSD_API.get(`/wpsd/v2/parcours/${state.parcoursId}`);
                    if (r && r.reservations) {
                        for (const res of r.reservations) {
                            if (res.status === 'pending') {
                                await WPSD_API.deleteRequest(`/wpsd/v1/reservations/${res.id}`);
                            }
                        }
                    }
                }
            }
            state.etapes = [];
            state.steps = [];
            state.activeStepIndex = 0;
            if (markers) markers.clearLayers();
            saveDraft();
            renderTimeline();
            updateSummary();
            msg('');
        });
    }

    // ==================== CHARGEMENT DU PARCOURS ====================

    async function loadParcours() {
        // 1. Chercher un parcours existant pour l'utilisateur
        const myParcours = await WPSD_API.get('/wpsd/v2/parcours?user_id=' + WPSD.userId);
        let activeParcours = null;

        if (Array.isArray(myParcours)) {
            activeParcours = myParcours.find(p => p.statut === 'draft' || p.statut === 'pending_acceptance');
        } else if (myParcours && myParcours.id) {
            activeParcours = myParcours;
        }

        if (activeParcours) {
            // Charger le parcours complet avec ses étapes
            const full = await WPSD_API.get(`/wpsd/v2/parcours/${activeParcours.id}`);
            if (full && full.id) {
                state.parcoursId = full.id;
                state.startDate = full.date_debut_globale || '';
                state.etapes = full.etapes || [];
                state.steps = state.etapes.map(e => ({
                    id: e.id,
                    duree: e.duree || 1,
                    date_debut: e.date_debut,
                    date_fin: e.date_fin,
                    activity_id: e.activity_id || null,
                    hebergement_id: e.hebergement_id || null,
                    activity: e.activity || null,
                    hebergement: e.hebergement || null,
                    travel_mode: e.travel_mode || '',
                    travel_days: e.travel_days || 0,
                    travel_hours: e.travel_hours || 0,
                }));
                state.activeStepIndex = 0;

                // Afficher les réservations liées
                if (full.reservations && full.reservations.length > 0) {
                    showReservationsForParcours(full.reservations);
                }

                const elStart = document.getElementById('wpsd_parcours_start_date');
                if (elStart) elStart.value = state.startDate;
            }
        } else {
            // Pas de parcours existant, charger le draft local
            loadDraft();
        }

        renderTimeline();
        updateSummary();
    }

    // ==================== CRÉATION D'UNE ÉTAPE ====================

    async function addStep() {
        if (!state.startDate) {
            document.getElementById('wpsd_parcours_start_date')?.focus();
            msg('Choisissez d\'abord une date de début.', 'error');
            return;
        }

        // Créer ou récupérer le parcours
        if (!state.parcoursId) {
            const r = await WPSD_API.post('/wpsd/v2/parcours', {
                date_debut_globale: state.startDate
            });
            if (r && r.id) {
                state.parcoursId = r.id;
            } else {
                msg('Erreur lors de la création du parcours', 'error');
                return;
            }
        }

        // Créer l'étape via l'API
        const r = await WPSD_API.post(`/wpsd/v2/parcours/${state.parcoursId}/etapes`, {});
        if (r && r.id) {
            state.etapes.push({
                id: r.id,
                numero_ordre: r.numero_ordre,
                duree: 1,
                date_debut: r.date_debut,
                date_fin: r.date_fin,
                activity_id: null,
                hebergement_id: null,
                activity: null,
                hebergement: null,
                travel_mode: '',
                travel_days: 0,
                travel_hours: 0,
            });
            state.steps = state.etapes;
            state.activeStepIndex = state.etapes.length - 1;
            renderTimeline();
            updateSummary();
            loadMapDisponibilites();
            msg(`Étape ${state.etapes.length} ajoutée. Cliquez sur la carte pour choisir une activité/hébergement.`, 'info');
        } else {
            msg('Erreur lors de l\'ajout de l\'étape', 'error');
        }

        saveDraft();
    }

    // ==================== TIMELINE ====================

    function renderTimeline() {
        const box = document.getElementById('wpsd_parcours_steps');
        if (!box) return;

        if (!state.etapes.length) {
            box.innerHTML = '<p class="wpsd-hint">Ajoutez une première étape pour commencer votre parcours.<br>Chaque étape peut inclure une activité et/ou un hébergement.</p>';
            return;
        }

        box.innerHTML = state.etapes.map((etape, i) => {
            const isActive = i === state.activeStepIndex;
            const act = etape.activity;
            const heberg = etape.hebergement;
            const hasAct = !!etape.activity_id;
            const hasHeberg = !!etape.hebergement_id;
            const complete = hasAct && hasHeberg;

            let statusClass = '';
            if (complete) statusClass = 'completed';
            else if (isActive) statusClass = 'active';

            const dateRange = etape.date_debut && etape.date_fin
                ? formatDate(etape.date_debut) + ' → ' + formatDate(etape.date_fin)
                : 'Dates à calculer';

            return `
                <div class="wpsd-etape ${statusClass}" data-numero="${i + 1}" data-index="${i}">
                    <div class="wpsd-etape-header">
                        <div>
                            <strong>Étape ${i + 1}</strong>
                            <div class="wpsd-etape-dates">${dateRange}</div>
                        </div>
                        <button class="wpsd-etape-btn danger" data-remove="${etape.id || i}" title="Supprimer cette étape">✕</button>
                    </div>

                    <div class="wpsd-etape-infos">
                        <div class="info-row">
                            <span class="info-label">Durée</span>
                            <span>
                                <input type="number" value="${etape.duree || 1}" min="1" max="30"
                                    style="width:50px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;text-align:center;"
                                    data-duree="${i}" data-etape-id="${etape.id || ''}">
                                jour${(etape.duree || 1) > 1 ? 's' : ''}
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Activité</span>
                            <span>
                                ${hasAct
                                    ? `✅ ${escapeHtml(act?.title || act?.name || `#${etape.activity_id}`)}
                                       <a href="#" data-remove-activity="${i}" style="font-size:11px;color:#dc2626;">[Retirer]</a>`
                                    : `<span style="color:#9ca3af;">— Cliquez sur la carte</span>`
                                }
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Hébergement</span>
                            <span>
                                ${hasHeberg
                                    ? `✅ ${escapeHtml(heberg?.title || heberg?.name || `#${etape.hebergement_id}`)}
                                       <a href="#" data-remove-hebergement="${i}" style="font-size:11px;color:#dc2626;">[Retirer]</a>`
                                    : `<span style="color:#9ca3af;">— Cliquez sur la carte</span>`
                                }
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Trajet</span>
                            <span>
                                <select data-travel-mode="${i}" data-etape-id="${etape.id || ''}" style="font-size:12px;padding:2px 4px;">
                                    <option value="">—</option>
                                    <option value="car" ${etape.travel_mode === 'car' ? 'selected' : ''}>🚗 Voiture</option>
                                    <option value="train" ${etape.travel_mode === 'train' ? 'selected' : ''}>🚂 Train</option>
                                    <option value="bus" ${etape.travel_mode === 'bus' ? 'selected' : ''}>🚌 Bus</option>
                                    <option value="bicycle" ${etape.travel_mode === 'bicycle' ? 'selected' : ''}>🚲 Vélo</option>
                                    <option value="walk" ${etape.travel_mode === 'walk' ? 'selected' : ''}>🚶‍♂️ À pied</option>
                                    <option value="hitchhike" ${etape.travel_mode === 'hitchhike' ? 'selected' : ''}>👍 Auto-stop</option>
                                    <option value="other" ${etape.travel_mode === 'other' ? 'selected' : ''}>📍 Autre</option>
                                </select>
                                <input type="number" value="${etape.travel_days || 0}" min="0" max="30"
                                    style="width:40px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;text-align:center;"
                                    placeholder="j" data-travel-days="${i}" data-etape-id="${etape.id || ''}"
                                    title="Jours de trajet">
                                <input type="number" value="${etape.travel_hours || 0}" min="0" max="12"
                                    style="width:35px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;text-align:center;"
                                    placeholder="h" data-travel-hours="${i}" data-etape-id="${etape.id || ''}"
                                    title="Heures de trajet">
                            </span>
                        </div>
                    </div>

                    <div class="wpsd-etape-actions">
                        <button class="wpsd-etape-btn ${isActive ? 'selected' : ''}" data-select-step="${i}">
                            ${isActive ? '← Étape active' : 'Sélectionner'}
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        // Événements
        box.querySelectorAll('[data-select-step]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                state.activeStepIndex = parseInt(btn.dataset.selectStep);
                renderTimeline();
                loadMapDisponibilites();
            });
        });

        box.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const idOrIndex = btn.dataset.remove;
                const index = state.etapes.findIndex((e, idx) => (e.id == idOrIndex) || idx == idOrIndex);
                if (index === -1 || !confirm('Supprimer cette étape ?')) return;

                if (state.etapes[index].id) {
                    await WPSD_API.deleteRequest(`/wpsd/v2/etapes/${state.etapes[index].id}`);
                }
                state.etapes.splice(index, 1);
                state.steps = state.etapes;
                state.activeStepIndex = Math.min(state.activeStepIndex, state.etapes.length - 1);
                await recalcAllDates();
                renderTimeline();
                updateSummary();
                if (markers) markers.clearLayers();
            });
        });

        // Changement de durée
        box.querySelectorAll('[data-duree]').forEach(input => {
            input.addEventListener('change', async () => {
                const i = parseInt(input.dataset.duree);
                const etapeId = input.dataset.etapeId;
                state.etapes[i].duree = Math.max(1, parseInt(input.value) || 1);
                if (etapeId) {
                    await apiUpdateEtape(etapeId, { duree: state.etapes[i].duree });
                }
                await recalcAllDates();
                renderTimeline();
                updateSummary();
            });
        });

        // Changement mode de transport
        box.querySelectorAll('[data-travel-mode]').forEach(select => {
            select.addEventListener('change', async () => {
                const i = parseInt(select.dataset.travelMode);
                const etapeId = select.dataset.etapeId;
                state.etapes[i].travel_mode = select.value;
                if (etapeId) {
                    await apiUpdateEtape(etapeId, { travel_mode: select.value });
                }
            });
        });

        box.querySelectorAll('[data-travel-days]').forEach(input => {
            input.addEventListener('change', async () => {
                const i = parseInt(input.dataset.travelDays);
                const etapeId = input.dataset.etapeId;
                state.etapes[i].travel_days = parseInt(input.value) || 0;
                if (etapeId) {
                    await apiUpdateEtape(etapeId, { travel_days: state.etapes[i].travel_days });
                }
                await recalcAllDates();
                renderTimeline();
                updateSummary();
            });
        });

        box.querySelectorAll('[data-travel-hours]').forEach(input => {
            input.addEventListener('change', async () => {
                const i = parseInt(input.dataset.travelHours);
                const etapeId = input.dataset.etapeId;
                state.etapes[i].travel_hours = parseInt(input.value) || 0;
                if (etapeId) {
                    await apiUpdateEtape(etapeId, { travel_hours: state.etapes[i].travel_hours });
                }
            });
        });

        // Retirer activité/hébergement
        box.querySelectorAll('[data-remove-activity]').forEach(link => {
            link.addEventListener('click', async (e) => {
                e.preventDefault();
                const i = parseInt(link.dataset.removeActivity);
                const etapeId = state.etapes[i].id;
                state.etapes[i].activity_id = null;
                state.etapes[i].activity = null;
                if (etapeId) {
                    await apiUpdateEtape(etapeId, { activity_id: null });
                }
                renderTimeline();
            });
        });

        box.querySelectorAll('[data-remove-hebergement]').forEach(link => {
            link.addEventListener('click', async (e) => {
                e.preventDefault();
                const i = parseInt(link.dataset.removeHebergement);
                const etapeId = state.etapes[i].id;
                state.etapes[i].hebergement_id = null;
                state.etapes[i].hebergement = null;
                if (etapeId) {
                    await apiUpdateEtape(etapeId, { hebergement_id: null });
                }
                renderTimeline();
            });
        });
    }

    // ==================== RECALCUL DES DATES ====================

    async function recalcAllDates() {
        let current = new Date(state.startDate + 'T00:00:00');

        for (const etape of state.etapes) {
            const duree = Math.max(1, etape.duree || 1);
            const travelOffset = (etape.travel_days || 0) + ((etape.travel_hours || 0) > 0 ? 1 : 0);

            etape.date_debut = formatDateISO(current);
            current.setDate(current.getDate() + duree);
            etape.date_fin = formatDateISO(new Date(current.getTime() - 86400000));

            // Ajouter le temps de trajet (décalage de la date de début de l'étape suivante)
            if (travelOffset > 0) {
                current.setDate(current.getDate() + travelOffset);
            }
        }

        // Sauvegarder les dates dans la BDD si on a des étapes
        for (const etape of state.etapes) {
            if (etape.id) {
                await apiUpdateEtape(etape.id, {
                    date_debut: etape.date_debut,
                    date_fin: etape.date_fin,
                });
            }
        }

        renderTimeline();
        updateSummary();
    }

    // ==================== CARTE (disponibilités) ====================

    async function loadMapDisponibilites() {
        if (!markers) return;

        // Afficher les marqueurs des étapes actuelles
        renderStepMarkers();

        // Charger les disponibilités si une étape est active
        const step = state.etapes[state.activeStepIndex];
        if (!step || !step.date_debut || !step.date_fin) return;

        // Ajouter un buffer pour voir les dates environnantes
        const dateDebut = formatDateISO(new Date(step.date_debut));
        const dateFin = formatDateISO(new Date(step.date_fin));

        const r = await WPSD_API.get(`/wpsd/v2/disponibilites?date_debut=${dateDebut}&date_fin=${dateFin}`);
        if (!r) return;

        // Si pas de résultats, essayer un buffer plus large
        let points = Array.isArray(r) ? r : (r.items || [r] || []);

        if (points.length === 0) {
            // Chercher dans un rayon plus large (1 mois avant/après)
            const debutBuffer = new Date(dateDebut);
            debutBuffer.setDate(debutBuffer.getDate() - 15);
            const finBuffer = new Date(dateFin);
            finBuffer.setDate(finBuffer.getDate() + 15);
            const r2 = await WPSD_API.get(
                `/wpsd/v2/disponibilites?date_debut=${formatDateISO(debutBuffer)}&date_fin=${formatDateISO(finBuffer)}`
            );
            points = Array.isArray(r2) ? r2 : (r2?.items || []);
        }

        points.forEach(point => {
            if (!point.lat || !point.lng) return;

            const color = point.type_display === 'mixed' ? '#8B5CF6'
                : point.type === 'activity' ? '#005247'
                : '#B45309';

            const icon = L.divIcon({
                html: `<div style="
                    width: 18px; height: 18px;
                    background: ${color};
                    border: 2px solid white;
                    border-radius: 50%;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                    cursor: pointer;
                    ${point.type_display === 'mixed' ? 'border-radius: 4px; transform: rotate(45deg);' : ''}
                "></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9],
                className: '',
            });

            const memberCard = point.member_card || {};
            const photoHtml = memberCard.photo_url
                ? `<img src="${escapeHtml(memberCard.photo_url)}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;float:right;margin-left:8px;">`
                : '';

            const mk = L.marker([point.lat, point.lng], { icon }).addTo(markers);

            mk.bindPopup(`
                <div class="wpsd-activity-popup" style="min-width:250px;">
                    ${photoHtml}
                    <h4>${escapeHtml(point.title)}</h4>
                    <p><strong>${point.type_display === 'mixed' ? '🏠 Activité + Hébergement' : point.type === 'activity' ? '🎯 Activité' : '🏠 Hébergement'}</strong></p>
                    <p>👤 ${escapeHtml(point.owner_name || '')}</p>
                    ${memberCard.centre_interet ? `<p>🏷️ ${escapeHtml(memberCard.centre_interet)}</p>` : ''}
                    ${memberCard.langues?.length ? `<p>🌐 ${Array.isArray(memberCard.langues) ? memberCard.langues.join(', ') : memberCard.langues}</p>` : ''}
                    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                        ${point.type === 'activity' || point.type_display === 'mixed'
                            ? `<button class="wpsd-etape-btn primary add-to-step" data-type="activity" data-id="${point.id}" data-title="${escapeHtml(point.title)}">+ Activité</button>`
                            : ''}
                        ${point.type === 'accommodation' || point.type_display === 'mixed'
                            ? `<button class="wpsd-etape-btn primary add-to-step" data-type="accommodation" data-id="${point.id}" data-title="${escapeHtml(point.title)}">+ Hébergement</button>`
                            : ''}
                    </div>
                </div>
            `);
        });

        // Écouter les clics sur "Ajouter à l'étape"
        document.querySelectorAll('.add-to-step').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const type = btn.dataset.type;
                const objectId = parseInt(btn.dataset.id);
                const title = btn.dataset.title;
                const step = state.etapes[state.activeStepIndex];
                if (!step) {
                    msg('Sélectionnez d\'abord une étape.', 'error');
                    return;
                }

                const etapeId = step.id;
                const updateData = {};

                if (type === 'activity') {
                    updateData.activity_id = objectId;
                    state.etapes[state.activeStepIndex].activity_id = objectId;
                    state.etapes[state.activeStepIndex].activity = { id: objectId, title: title };
                } else {
                    updateData.hebergement_id = objectId;
                    state.etapes[state.activeStepIndex].hebergement_id = objectId;
                    state.etapes[state.activeStepIndex].hebergement = { id: objectId, title: title };
                }

                if (etapeId) {
                    await apiUpdateEtape(etapeId, updateData);
                }

                renderTimeline();
                if (map) map.closePopup();
                msg(`${type === 'activity' ? 'Activité' : 'Hébergement'} ajouté à l'étape ${state.activeStepIndex + 1}.`, 'success');
            });
        });
    }

    /**
     * Affiche les marqueurs du parcours (étapes avec pin numéroté)
     */
    function renderStepMarkers() {
        if (!markers) return;

        // On ne supprime pas les marqueurs de disponibilités, on ajoute seulement

        state.etapes.forEach((etape, i) => {
            // Chercher les coordonnées depuis l'activité ou l'hébergement
            let lat = null, lng = null, title = '';

            if (etape.activity_id) {
                // On peut récupérer via un élément de carte existant ou un meta
                lat = etape.activity?.lat || null;
                lng = etape.activity?.lng || null;
                title = etape.activity?.title || `Étape ${i + 1}`;
            }
            if (!lat && etape.hebergement_id) {
                lat = etape.hebergement?.lat || null;
                lng = etape.hebergement?.lng || null;
                title = title || etape.hebergement?.title || `Étape ${i + 1}`;
            }

            if (lat && lng) {
                const isActive = i === state.activeStepIndex;
                const icon = L.divIcon({
                    html: `<div style="
                        width: 24px; height: 24px;
                        background: ${isActive ? '#005247' : '#6b7280'};
                        border: 2px solid white;
                        border-radius: 50%;
                        color: white;
                        font-size: 11px;
                        font-weight: bold;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                    ">${i + 1}</div>`,
                    iconSize: [24, 24],
                    iconAnchor: [12, 12],
                    className: '',
                });

                L.marker([lat, lng], { icon }).addTo(markers)
                    .bindPopup(`<div style="text-align:center;"><strong>Étape ${i + 1}</strong><br>${escapeHtml(title)}</div>`);
            }
        });
    }

    // ==================== ENVOI DES DEMANDES ====================

    async function submitDemands() {
        if (!state.etapes.length) {
            msg('Aucune étape à soumettre.', 'error');
            return;
        }

        // Valider
        for (let i = 0; i < state.etapes.length; i++) {
            const e = state.etapes[i];
            if (!e.activity_id && !e.hebergement_id) {
                msg(`L'étape ${i + 1} n'a ni activité ni hébergement.`, 'error');
                return;
            }
        }

        if (!state.parcoursId) {
            msg('Erreur : aucun parcours créé.', 'error');
            return;
        }

        msg('Envoi des demandes aux prestataires...', 'info');

        const r = await WPSD_API.post(`/wpsd/v2/parcours/${state.parcoursId}/send-demands`, {});
        if (r && r.message) {
            msg('✅ ' + r.message, 'success');
            // Recharger le parcours
            await loadParcours();
        } else {
            msg('Erreur : ' + (r?.message || 'Échec de l\'envoi'), 'error');
        }
    }

    // ==================== UTILITAIRES ====================

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    }

    function formatDateISO(date) {
        return date.toISOString().slice(0, 10);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function msg(text, type) {
        const el = document.getElementById('wpsd_parcours_msg');
        if (!el) return;
        el.textContent = text || '';
        el.style.color = type === 'error' ? '#dc2626' : type === 'success' ? '#166534' : '#6b7280';
    }

    function updateSummary() {
        const el = document.getElementById('wpsd_parcours_summary');
        if (!el) return;
        const totalJours = state.etapes.reduce((sum, e) => sum + (e.duree || 1), 0);
        const complete = state.etapes.filter(e => e.activity_id && e.hebergement_id).length;
        el.innerHTML = `
            <div class="wpsd-parcours-summary-item">
                <div class="value">${state.etapes.length}</div>
                <div class="label">Étapes</div>
            </div>
            <div class="wpsd-parcours-summary-item">
                <div class="value">${totalJours}</div>
                <div class="label">Jours</div>
            </div>
            <div class="wpsd-parcours-summary-item">
                <div class="value">${complete}/${state.etapes.length}</div>
                <div class="label">Complètes</div>
            </div>
        `;
    }

    function showReservationsForParcours(reservations) {
        // Afficher un résumé des réservations
        const container = document.getElementById('wpsd_parcours_reservations');
        if (!container) return;

        if (!reservations.length) {
            container.innerHTML = '';
            return;
        }

        const pending = reservations.filter(r => r.status === 'pending');
        const accepted = reservations.filter(r => r.status === 'accepted');
        const completed = reservations.filter(r => r.status === 'completed');

        container.innerHTML = `
            <div class="wpsd-parcours-progress">
                <div class="wpsd-parcours-progress-bar">
                    <div class="wpsd-parcours-progress-fill"
                         style="width: ${reservations.length ? ((completed.length / reservations.length) * 100) : 0}%">
                    </div>
                </div>
                <div class="wpsd-parcours-progress-label">
                    <span>❓ ${pending.length} en attente</span>
                    <span>✅ ${accepted.length} acceptées</span>
                    <span>🎉 ${completed.length} terminées</span>
                </div>
            </div>
        `;
    }

    // ==================== PAIEMENT ====================

    async function initPayment() {
        if (!state.parcoursId) { msg('Aucun parcours à payer.', 'error'); return; }
        const payBtn = document.getElementById('wpsd_parcours_pay');
        const errEl = document.getElementById('wpsd_payment_error');
        if (payBtn) payBtn.disabled = true;
        if (errEl) errEl.textContent = '';

        try {
            // Créer le PaymentIntent
            const intent = await WPSD_API.post('/wpsd/v2/payment/create-payment-intent', { parcours_id: state.parcoursId });
            if (!intent || !intent.client_secret) {
                throw new Error(intent?.message || 'Erreur création du paiement');
            }

            // Stripe.js doit être chargé
            if (typeof Stripe === 'undefined') throw new Error('Stripe.js non chargé');
            // Récupérer la clé publishable depuis les meta
            const stripeKey = document.querySelector('meta[name="wpsd_stripe_key"]')?.content;
            if (!stripeKey) throw new Error('Clé Stripe manquante');

            const stripe = Stripe(stripeKey);
            const { error } = await stripe.confirmCardPayment(intent.client_secret);
            if (error) throw new Error(error.message);

            // Paiement réussi
            const confirm = await WPSD_API.post('/wpsd/v2/payment/confirm-parcours', {
                parcours_id: state.parcoursId,
                payment_intent_id: intent.payment_intent_id || '',
            });
            if (confirm && confirm.message) {
                msg('✅ Paiement confirmé ! ' + confirm.message, 'success');
                await loadParcours();
            }
        } catch (e) {
            msg(e.message, 'error');
            if (errEl) errEl.textContent = e.message;
        } finally {
            if (payBtn) payBtn.disabled = false;
        }
    }

    // ==================== API HELPERS ====================

    async function apiUpdateParcours(data) {
        if (!state.parcoursId) return;
        return await WPSD_API.putRequest(`/wpsd/v2/parcours/${state.parcoursId}`, data);
    }

    async function apiUpdateEtape(etapeId, data) {
        return await WPSD_API.putRequest(`/wpsd/v2/etapes/${etapeId}`, data);
    }

    // ==================== DRAFT LOCAL (fallback) ====================

    function saveDraft() {
        try {
            localStorage.setItem('wpsd_parcours_draft', JSON.stringify({
                parcoursId: state.parcoursId,
                startDate: state.startDate,
                activeStepIndex: state.activeStepIndex,
                timestamp: Date.now(),
            }));
        } catch (e) { /* ignore */ }
    }

    function loadDraft() {
        try {
            const raw = localStorage.getItem('wpsd_parcours_draft');
            if (raw) {
                const draft = JSON.parse(raw);
                if (draft.parcoursId) state.parcoursId = draft.parcoursId;
                if (draft.startDate) state.startDate = draft.startDate;
                if (draft.activeStepIndex !== undefined) state.activeStepIndex = draft.activeStepIndex;
            }
        } catch (e) { /* ignore */ }
    }

    // ==================== EXPOSITION PUBLIQUE ====================

    return { init };
})();