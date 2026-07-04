<?php
$data = method_exists($section, 'getData') ? $section->getData() : array();

$imageId = isset($data['image_id']) ? (int) $data['image_id'] : 0;
$imagePosition = isset($data['image_position']) && $data['image_position'] === 'left' ? 'left' : 'right';
$blocks = isset($data['blocks']) && is_array($data['blocks']) ? $data['blocks'] : array();

$mediaColumn = '';
if ($imageId > 0) {
    ob_start();
    ?>
    <div class="schilo-colonnes-media">
        <?php echo wp_get_attachment_image($imageId, 'large'); ?>
    </div>
    <?php
    $mediaColumn = ob_get_clean();
}

$textColumn = '';
if (!empty($blocks)) {
    ob_start();
    ?>
    <div class="schilo-colonnes-blocks">
        <?php foreach ($blocks as $block) : ?>
            <?php $content = isset($block['content']) ? $block['content'] : ''; ?>
            <?php if (trim($content) === '') : continue; endif; ?>
            <div class="schilo-details-box">
                <span class="schilo-details-icon" aria-hidden="true">i</span>
                <div class="schilo-details-box-content">
                    <?php echo wpautop(wp_kses_post($content)); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    $textColumn = ob_get_clean();
}
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle() !== '') : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>

    <div class="schilo-details-colonnes-layout schilo-details-colonnes-image-<?php echo esc_attr($imagePosition); ?>">
        <?php if ($imagePosition === 'left') : ?>
            <?php echo $mediaColumn; ?>
            <?php echo $textColumn; ?>
        <?php else : ?>
            <?php echo $textColumn; ?>
            <?php echo $mediaColumn; ?>
        <?php endif; ?>
    </div>
</section>
