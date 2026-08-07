<?php
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle() !== '') : ?>
        <h2 class="schilo-article-part-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>
</section>
