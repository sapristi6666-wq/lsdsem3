<?php if (!defined('ABSPATH')) exit; ?>
<nav class="wpsd-tabs" role="tablist">
    <?php $first = true; foreach ($tabs as $id => $tab): ?>
        <?php if (!$tab['visible']) continue; ?>
        <button class="wpsd-tab<?= $first ? ' is-active' : '' ?>" data-tab="<?= esc_attr($id) ?>" role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>">
            <span class="wpsd-tab-label"><?= esc_html($tab['label']) ?></span>
        </button>
    <?php $first = false; endforeach; ?>
</nav>