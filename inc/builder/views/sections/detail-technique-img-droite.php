<?php
$data = method_exists($section, 'getData') ? $section->getData() : array();

$imageId = isset($data['image_id']) ? (int) $data['image_id'] : 0;
$imageHautId = isset($data['image_haut_id']) ? (int) $data['image_haut_id'] : 0;
$imageBasId = isset($data['image_bas_id']) ? (int) $data['image_bas_id'] : 0;

$rows = array(
    array('label' => 'Lieu :', 'value' => isset($data['lieu']) ? $data['lieu'] : ''),
    array('label' => 'Date :', 'value' => isset($data['date']) ? $data['date'] : ''),
    array('label' => 'Mode opératoire :', 'value' => isset($data['mode_operatoire']) ? $data['mode_operatoire'] : ''),
    array('label' => 'Note sur le mode opératoire :', 'value' => isset($data['note_mode_operatoire']) ? $data['note_mode_operatoire'] : ''),
);

$texteDessous = isset($data['texte_dessous']) ? $data['texte_dessous'] : '';
$texteAvant = isset($data['texte_avant']) ? $data['texte_avant'] : '';
$texteMilieu = isset($data['texte_milieu']) ? $data['texte_milieu'] : '';
$texteApres = isset($data['texte_apres']) ? $data['texte_apres'] : '';
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle() !== '') : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>

    <?php if (trim((string) $texteAvant) !== '') : ?>
        <div class="schilo-details-blocks schilo-details-blocks-before-top">
            <div class="schilo-details-box">
                <span class="schilo-details-icon" aria-hidden="true">i</span>
                <div class="schilo-details-box-content">
                    <p><?php echo esc_html($texteAvant); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($imageHautId > 0) : ?>
        <div class="schilo-colonnes-media schilo-detail-img-extra schilo-detail-img-haut">
            <?php echo wp_get_attachment_image($imageHautId, 'large'); ?>
        </div>
    <?php endif; ?>

    <div class="schilo-detail-img-droite-layout">
        <div class="schilo-details-blocks">
            <?php foreach ($rows as $row) : ?>
                <?php if (trim((string) $row['value']) === '') : continue; endif; ?>
                <div class="schilo-details-box">
                    <span class="schilo-details-icon" aria-hidden="true">i</span>
                    <div class="schilo-details-box-content">
                        <p>
                            <strong><?php echo esc_html($row['label']); ?></strong>
                            <?php echo esc_html($row['value']); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($imageId > 0) : ?>
            <div class="schilo-colonnes-media">
                <?php echo wp_get_attachment_image($imageId, 'large'); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (trim((string) $texteMilieu) !== '') : ?>
        <div class="schilo-details-blocks schilo-details-blocks-after">
            <div class="schilo-details-box">
                <span class="schilo-details-icon" aria-hidden="true">i</span>
                <div class="schilo-details-box-content">
                    <p><?php echo esc_html($texteMilieu); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (trim((string) $texteDessous) !== '') : ?>
        <div class="schilo-details-blocks schilo-details-blocks-after">
            <div class="schilo-details-box">
                <span class="schilo-details-icon" aria-hidden="true">i</span>
                <div class="schilo-details-box-content">
                    <p><?php echo esc_html($texteDessous); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($imageBasId > 0) : ?>
        <div class="schilo-colonnes-media schilo-detail-img-extra schilo-detail-img-bas">
            <?php echo wp_get_attachment_image($imageBasId, 'large'); ?>
        </div>
    <?php endif; ?>

    <?php if (trim((string) $texteApres) !== '') : ?>
        <div class="schilo-details-blocks schilo-details-blocks-after">
            <div class="schilo-details-box">
                <span class="schilo-details-icon" aria-hidden="true">i</span>
                <div class="schilo-details-box-content">
                    <p><?php echo esc_html($texteApres); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
