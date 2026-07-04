<?php
$data = method_exists($section, 'getData') ? $section->getData() : array();
$versets = isset($data['versets']) && is_array($data['versets']) ? $data['versets'] : array();
?>

<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle() !== '') : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>

    <div class="schilo-evangiles-list">
        <?php foreach ($versets as $line) : ?>
            <?php
            $reference = isset($line['reference']) ? trim((string) $line['reference']) : '';
            $label = isset($line['label']) ? trim((string) $line['label']) : '';
            $class = isset($line['class']) ? sanitize_html_class($line['class']) : 'citation-bible';

            if ($reference === '' && $label === '') {
                continue;
            }
            ?>
            <div class="<?php echo esc_attr($class); ?>">
                <?php if ($label !== '') : ?>
                    <strong class="schilo-evangile-label"><?php echo esc_html($label); ?></strong>
                <?php endif; ?>

                <?php if ($reference !== '') : ?>
                <div class="schilo-evangile-reference">
                    <?php echo do_shortcode('[bvc]' . $reference . '[/bvc]'); ?>
                </div>
                <?php elseif ($label !== '') : ?>
                <div class="schilo-evangile-reference">
                    <?php echo do_shortcode('[bnv]' . $label . '[/bnv]'); ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
