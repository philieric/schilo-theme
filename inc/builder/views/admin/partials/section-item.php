<?php
use Schilo\Builder\Service\AdminSectionRenderer;
?>

<div class="schilo-section-card schilo-section-item" data-index="<?php echo esc_attr($index); ?>">
    <div class="schilo-section-header schilo-section-item-header">
        <span class="schilo-drag-handle">☰</span>

        <strong class="schilo-section-title">
            <span class="schilo-section-number"><?php echo esc_html((int) $index + 1); ?></span>
            <?php echo esc_html($section->getTitle() !== '' ? $section->getTitle() : ucfirst($section->getType())); ?>
        </strong>

        <div class="schilo-section-actions">
            <button type="button" class="button-link schilo-toggle-section">Replier</button>
            <button type="button" class="button-link schilo-duplicate-section">Dupliquer</button>
            <button type="button" class="button schilo-remove-section">Supprimer</button>
        </div>
    </div>

    <div class="schilo-section-body schilo-section-item-body">
        <input type="hidden"
               class="schilo-section-type-input"
               name="schilo_sections[<?php echo esc_attr($index); ?>][type]"
               value="<?php echo esc_attr($section->getType()); ?>">

        <?php (new AdminSectionRenderer())->render($section, $index, isset($prefix) ? $prefix : ''); ?>
    </div>
</div>
