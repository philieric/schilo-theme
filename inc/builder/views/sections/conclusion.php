<?php
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle()) : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>

    <div class="schilo-section-content">
        <?php echo $contentFilter->render($section->getContent()); ?>
    </div>
</section>
