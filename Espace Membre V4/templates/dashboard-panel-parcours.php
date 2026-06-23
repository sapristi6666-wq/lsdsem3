<div class="wpsd-panel" id="wpsd-panel-parcours">
    <div class="wpsd-parcours-layout">
        <div class="wpsd-parcours-map">
            <div class="wpsd-card" style="height:100%;">
                <div class="wpsd-card-header">
                    <h3>Carte des activités et hébergements</h3>
                </div>
                <div id="wpsd_parcours_map" style="flex:1;min-height:450px;"></div>
                <div class="wpsd-parcours-map-hint" style="padding:8px 16px;background:#005247;font-size:13px;color:#FBF1CA;text-align:center;">
                    Sélectionnez une étape, puis cliquez sur un point de la carte pour ajouter une activité ou un hébergement
                </div>
            </div>
        </div>

        <div class="wpsd-parcours-timeline">
            <div class="wpsd-card">
                <div class="wpsd-card-header">
                    <h3>Mon parcours</h3>
                </div>

                <!-- Résumé -->
                <div id="wpsd_parcours_summary" class="wpsd-parcours-summary">
                    <div class="wpsd-parcours-summary-item">
                        <div class="value">0</div>
                        <div class="label">Étapes</div>
                    </div>
                    <div class="wpsd-parcours-summary-item">
                        <div class="value">0</div>
                        <div class="label">Jours</div>
                    </div>
                    <div class="wpsd-parcours-summary-item">
                        <div class="value">0/0</div>
                        <div class="label">Complètes</div>
                    </div>
                </div>

                <!-- Progression des réservations -->
                <div id="wpsd_parcours_reservations"></div>

                <!-- Date de début -->
                <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                    <div class="wpsd-form-group" style="margin:0;">
                        <label style="font-size:13px;font-weight:600;color:#374151;">Date de début du voyage</label>
                        <input type="date" id="wpsd_parcours_start_date" style="width:100%;margin-top:4px;">
                    </div>
                </div>

                <!-- Étapes -->
                <div id="wpsd_parcours_steps" style="max-height:400px;overflow-y:auto;padding:12px 4px;">
                    <p class="wpsd-hint">Ajoutez une première étape pour commencer votre parcours.</p>
                </div>

                <!-- Actions -->
                                <div style="padding:12px 16px;border-top:1px solid #e5e7eb;display:flex;flex-direction:column;gap:8px;">
                    <button class="wpsd-btn wpsd-primary" id="wpsd_parcours_add_step" style="width:100%;">
                        + Ajouter une étape
                    </button>
                    <button class="wpsd-btn wpsd-primary" id="wpsd_parcours_submit" style="width:100%;background:#FBF1CA;border-color:#005247;color:#005247;">
                        Envoyer toutes les demandes
                    </button>
                    <div id="wpsd_parcours_payment" style="display:none;">
                        <button class="wpsd-btn wpsd-primary" id="wpsd_parcours_pay" style="width:100%;background:#059669;border-color:#059669;">
                            Payer le parcours (stripe)
                        </button>
                        <div id="wpsd_payment_error" style="color:#dc2626;font-size:13px;text-align:center;margin-top:4px;"></div>
                    </div>
                    <button class="wpsd-btn" id="wpsd_parcours_clear" style="width:100%;">Vider le parcours</button>
                    <span id="wpsd_parcours_msg" class="wpsd-hint" style="text-align:center;font-weight:500;"></span>
                </div>
            </div>
        </div>
    </div>
</div>
