<?php
/** Variables : $section, $index, $data */
use Schilo\Builder\Service\SectionStructureService;

$structureService = new SectionStructureService();
$normalizedData = $structureService->normalizeSectionData('details-techniques', isset($data) && is_array($data) ? $data : array());

$imageId = isset($normalizedData['image_id']) ? (int) $normalizedData['image_id'] : 0;
$blocksBefore = isset($normalizedData['blocks_before']) && is_array($normalizedData['blocks_before']) ? $normalizedData['blocks_before'] : array();
$blocksAfter = isset($normalizedData['blocks_after']) && is_array($normalizedData['blocks_after']) ? $normalizedData['blocks_after'] : array();
?>

<div class="schilo-form-grid">
    <div class="schilo-field-row">
        <label>
            <strong>Titre</strong>
            <input type="text"
                   class="widefat schilo-title-input"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][title]"
                   value="<?php echo esc_attr($section->getTitle()); ?>">
        </label>
    </div>

    <div class="schilo-field-row">
        <label>
            <strong>Classe CSS personnalisée</strong>
            <input type="text"
                   class="widefat"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][custom_class]"
                   value="<?php echo esc_attr($section->getCustomClass()); ?>"
                   placeholder="ex: bloc-important">
        </label>
    </div>
</div>

<p class="description schilo-auto-class">
    Classe automatique : <code>schilo-section-<?php echo esc_html($section->getType()); ?></code>
    <?php if (!empty($prefix)) : ?>
        &nbsp;&mdash;&nbsp;Classe spécifique au template : <code>schilo-section-<?php echo esc_html($section->getType()); ?>-<?php echo esc_html(sanitize_html_class(strtolower($prefix))); ?></code>
    <?php endif; ?>
</p>

<input type="hidden" name="schilo_sections[<?php echo esc_attr($index); ?>][content]" value="">

<?php
/**
 * Affiche un groupe d'encarts (textes) répétables.
 *
 * @param string $groupKey  'blocks_before' ou 'blocks_after'.
 * @param string $groupLabel Libellé affiché.
 * @param array  $blocks     Liste des encarts existants.
 */
$renderBlocksGroup = function ($groupKey, $groupLabel, $helpText, $blocks) use ($index) {
    ?>
    <div class="schilo-detail-blocks-field" data-group="<?php echo esc_attr($groupKey); ?>">
        <strong><?php echo esc_html($groupLabel); ?></strong>
        <p class="description"><?php echo esc_html($helpText); ?></p>

        <div class="schilo-detail-blocks-items">
            <?php foreach ($blocks as $itemIndex => $block) : ?>
                <?php
                $content = isset($block['content']) ? $block['content'] : '';
                $editorId = 'schilo_detail_' . $groupKey . '_' . (int) $index . '_' . (int) $itemIndex;
                ?>
                <div class="schilo-detail-block-item">
                    <div class="schilo-editor-wrapper schilo-detail-editor-wrapper">
                        <?php
                        wp_editor(
                            $content,
                            $editorId,
                            array(
                                'textarea_name' => 'schilo_sections[' . (int) $index . '][data][' . $groupKey . '][' . (int) $itemIndex . '][content]',
                                'textarea_rows' => 4,
                                'media_buttons' => false,
                                'teeny' => false,
                                'quicktags' => true,
                                'tinymce' => array(
                                    'toolbar1' => 'schilo_h1,schilo_h2,schilo_h3,schilo_h4,schilo_h5,schilo_h6,schilo_p,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,pastetext,undo,redo',
                                    'toolbar2' => '',
                                    'height' => 130,
                                ),
                            )
                        );
                        ?>
                    </div>
                    <button type="button" class="button schilo-remove-detail-block">Retirer</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button schilo-add-detail-block" data-group="<?php echo esc_attr($groupKey); ?>">+ Ajouter un encart</button>
    </div>
    <?php
};

$renderBlocksGroup(
    'blocks_before',
    'Encarts avant l’image (lieu, date, mode opératoire...)',
    'Un encart par information. Utilisez <strong>texte</strong> pour mettre du texte en gras.',
    $blocksBefore
);
?>

<div class="schilo-image-field">
    <strong>Image principale</strong>

    <input type="hidden"
           class="schilo-image-id"
           name="schilo_sections[<?php echo esc_attr($index); ?>][data][image_id]"
           value="<?php echo esc_attr($imageId); ?>">

    <div class="schilo-image-preview <?php echo $imageId > 0 ? 'has-image' : ''; ?>">
        <?php if ($imageId > 0) : ?>
            <?php echo wp_get_attachment_image($imageId, 'medium'); ?>
        <?php else : ?>
            <span>Aucune image sélectionnée</span>
        <?php endif; ?>
    </div>

    <div class="schilo-image-actions">
        <button type="button" class="button schilo-select-image">Choisir une image</button>
        <button type="button" class="button schilo-remove-image">Retirer</button>
    </div>
</div>

<?php
$renderBlocksGroup(
    'blocks_after',
    'Encarts après l’image',
    'Un encart par information complémentaire affichée sous l’image.',
    $blocksAfter
);
?>
