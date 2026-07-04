<?php
/** Variables : $section, $index, $data */
use Schilo\Builder\Service\SectionStructureService;

$structureService = new SectionStructureService();
$normalizedData = $structureService->normalizeSectionData('detail-technique-img-droite', isset($data) && is_array($data) ? $data : array());

$imageId = isset($normalizedData['image_id']) ? (int) $normalizedData['image_id'] : 0;
$imageHautId = isset($normalizedData['image_haut_id']) ? (int) $normalizedData['image_haut_id'] : 0;
$imageBasId = isset($normalizedData['image_bas_id']) ? (int) $normalizedData['image_bas_id'] : 0;

$renderImageField = function ($fieldKey, $label, $currentId) use ($index) {
    ?>
    <div class="schilo-image-field">
        <strong><?php echo esc_html($label); ?></strong>

        <input type="hidden"
               class="schilo-image-id"
               name="schilo_sections[<?php echo esc_attr($index); ?>][data][<?php echo esc_attr($fieldKey); ?>]"
               value="<?php echo esc_attr($currentId); ?>">

        <div class="schilo-image-preview <?php echo $currentId > 0 ? 'has-image' : ''; ?>">
            <?php if ($currentId > 0) : ?>
                <?php echo wp_get_attachment_image($currentId, 'medium'); ?>
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
};

$renderTextField = function ($fieldKey, $label, $placeholder, $value) use ($index) {
    ?>
    <div class="schilo-field-row">
        <label>
            <strong><?php echo esc_html($label); ?></strong>
            <input type="text" class="widefat"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][data][<?php echo esc_attr($fieldKey); ?>]"
                   value="<?php echo esc_attr($value); ?>"
                   placeholder="<?php echo esc_attr($placeholder); ?>">
        </label>
    </div>
    <button type="button" class="button-link schilo-remove-text-field">Retirer ce texte</button>
    <?php
};
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

<div class="schilo-optional-text <?php echo trim((string) $normalizedData['texte_avant']) !== '' ? 'schilo-optional-expanded' : 'schilo-optional-collapsed'; ?>" data-field="texte_avant" data-label="Texte libre au-dessus" data-placeholder="Texte libre affiché en haut de la section">
    <?php if (trim((string) $normalizedData['texte_avant']) !== '') : ?>
        <?php $renderTextField('texte_avant', 'Texte libre au-dessus', 'Texte libre affiché en haut de la section', $normalizedData['texte_avant']); ?>
    <?php else : ?>
        <button type="button" class="button schilo-add-optional-text" data-field="texte_avant" data-label="Texte libre au-dessus" data-placeholder="Texte libre affiché en haut de la section">+ Ajouter un texte</button>
    <?php endif; ?>
</div>

<div class="schilo-optional-image <?php echo $imageHautId > 0 ? 'schilo-optional-expanded' : 'schilo-optional-collapsed'; ?>" data-field="image_haut_id" data-label="Image au-dessus de « Lieu : »">
    <?php if ($imageHautId > 0) : ?>
        <?php $renderImageField('image_haut_id', 'Image au-dessus de « Lieu : »', $imageHautId); ?>
        <button type="button" class="button-link schilo-remove-image-field">Retirer ce bloc image</button>
    <?php else : ?>
        <button type="button" class="button schilo-add-optional-image" data-field="image_haut_id" data-label="Image au-dessus de « Lieu : »">+ Ajouter une image</button>
    <?php endif; ?>
</div>

<div class="schilo-detail-fixed-fields">
    <div class="schilo-field-row">
        <label>
            <strong>Lieu :</strong>
            <input type="text" class="widefat"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][data][lieu]"
                   value="<?php echo esc_attr($normalizedData['lieu']); ?>"
                   placeholder="Ex : Le mont Golgotha">
        </label>
    </div>

    <div class="schilo-field-row">
        <label>
            <strong>Date :</strong>
            <input type="text" class="widefat"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][data][date]"
                   value="<?php echo esc_attr($normalizedData['date']); ?>"
                   placeholder="Ex : le vendredi 1er avril, le matin">
        </label>
    </div>

    <div class="schilo-field-row">
        <label>
            <strong>Mode opératoire :</strong>
            <input type="text" class="widefat"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][data][mode_operatoire]"
                   value="<?php echo esc_attr($normalizedData['mode_operatoire']); ?>"
                   placeholder="Ex : Nous suivons maintenant Luc">
        </label>
    </div>

    <div class="schilo-field-row">
        <label>
            <strong>Note sur le mode opératoire :</strong>
            <input type="text" class="widefat"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][data][note_mode_operatoire]"
                   value="<?php echo esc_attr($normalizedData['note_mode_operatoire']); ?>"
                   placeholder="Ex : Matthieu, Marc et Jean rapportent aussi ce fait">
        </label>
    </div>
</div>

<div class="schilo-optional-text <?php echo trim((string) $normalizedData['texte_milieu']) !== '' ? 'schilo-optional-expanded' : 'schilo-optional-collapsed'; ?>" data-field="texte_milieu" data-label="Texte libre avant l’image principale" data-placeholder="Texte libre affiché avant l’image principale">
    <?php if (trim((string) $normalizedData['texte_milieu']) !== '') : ?>
        <?php $renderTextField('texte_milieu', 'Texte libre avant l’image principale', 'Texte libre affiché avant l’image principale', $normalizedData['texte_milieu']); ?>
    <?php else : ?>
        <button type="button" class="button schilo-add-optional-text" data-field="texte_milieu" data-label="Texte libre avant l’image principale" data-placeholder="Texte libre affiché avant l’image principale">+ Ajouter un texte</button>
    <?php endif; ?>
</div>

<?php $renderImageField('image_id', 'Image principale', $imageId); ?>

<div class="schilo-field-row">
    <label>
        <strong>Texte sous l’image</strong>
        <input type="text" class="widefat"
               name="schilo_sections[<?php echo esc_attr($index); ?>][data][texte_dessous]"
               value="<?php echo esc_attr($normalizedData['texte_dessous']); ?>"
               placeholder="Ex : Arrivé au mont Golgotha, Jésus est cloué sur la croix par les mains et les pieds.">
    </label>
</div>

<div class="schilo-optional-image <?php echo $imageBasId > 0 ? 'schilo-optional-expanded' : 'schilo-optional-collapsed'; ?>" data-field="image_bas_id" data-label="Image sous le texte">
    <?php if ($imageBasId > 0) : ?>
        <?php $renderImageField('image_bas_id', 'Image sous le texte', $imageBasId); ?>
        <button type="button" class="button-link schilo-remove-image-field">Retirer ce bloc image</button>
    <?php else : ?>
        <button type="button" class="button schilo-add-optional-image" data-field="image_bas_id" data-label="Image sous le texte">+ Ajouter une image</button>
    <?php endif; ?>
</div>

<div class="schilo-optional-text <?php echo trim((string) $normalizedData['texte_apres']) !== '' ? 'schilo-optional-expanded' : 'schilo-optional-collapsed'; ?>" data-field="texte_apres" data-label="Texte libre en dessous" data-placeholder="Texte libre affiché en bas de la section">
    <?php if (trim((string) $normalizedData['texte_apres']) !== '') : ?>
        <?php $renderTextField('texte_apres', 'Texte libre en dessous', 'Texte libre affiché en bas de la section', $normalizedData['texte_apres']); ?>
    <?php else : ?>
        <button type="button" class="button schilo-add-optional-text" data-field="texte_apres" data-label="Texte libre en dessous" data-placeholder="Texte libre affiché en bas de la section">+ Ajouter un texte</button>
    <?php endif; ?>
</div>
