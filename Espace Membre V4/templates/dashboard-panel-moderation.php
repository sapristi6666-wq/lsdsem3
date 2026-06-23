<?php if (!defined('ABSPATH')) exit; ?>
<div class="wpsd-panel" id="wpsd-panel-moderation">
    <div class="wpsd-card">
        <div class="wpsd-moderation-tabs">
            <button class="wpsd-moderation-subtab wpsd-btn is-active" data-subtab="pending">Inscriptions en attente</button>
            <button class="wpsd-moderation-subtab wpsd-btn" data-subtab="roles">Gestion des rôles</button>
        </div>
        <div id="wpsd-moderation-pending" class="wpsd-moderation-content"></div>
        <div id="wpsd-moderation-roles" class="wpsd-moderation-content" style="display:none;"></div>
    </div>
</div>