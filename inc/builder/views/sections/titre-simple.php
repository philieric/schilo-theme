<?php
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle() !== '') : ?>
        <h1 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h1>
    <?php endif; ?>
</section>
