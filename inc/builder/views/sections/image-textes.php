<?php
$data = $section->getData();
$imageId = isset($data['image_id']) ? (int) $data['image_id'] : 0;
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle()) : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>

    <div class="schilo-image-textes-layout">
        <?php if ($imageId > 0) : ?>
            <div class="schilo-image-textes-media">
                <?php echo wp_get_attachment_image($imageId, 'large'); ?>
            </div>
        <?php endif; ?>

        <div class="schilo-image-textes-content">
            <?php echo $contentFilter->render($section->getContent()); ?>
        </div>
    </div>
</section>
