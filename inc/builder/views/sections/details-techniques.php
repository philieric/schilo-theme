<?php
$data = method_exists($section, 'getData') ? $section->getData() : array();

$imageId = isset($data['image_id']) ? (int) $data['image_id'] : 0;
$blocksBefore = isset($data['blocks_before']) && is_array($data['blocks_before']) ? $data['blocks_before'] : array();
$blocksAfter = isset($data['blocks_after']) && is_array($data['blocks_after']) ? $data['blocks_after'] : array();
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle() !== '') : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>

    <div class="schilo-details-techniques-layout">
        <?php if (!empty($blocksBefore)) : ?>
            <div class="schilo-details-blocks schilo-details-blocks-before">
                <?php foreach ($blocksBefore as $block) : ?>
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
        <?php endif; ?>

        <?php if ($imageId > 0) : ?>
            <div class="schilo-details-media">
                <?php echo wp_get_attachment_image($imageId, 'large'); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($blocksAfter)) : ?>
        <div class="schilo-details-blocks schilo-details-blocks-after">
            <?php foreach ($blocksAfter as $block) : ?>
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
    <?php endif; ?>
</section>
