<?php
use Schilo\Builder\Service\SectionStructureService;

$structureService = new SectionStructureService();
$structure = $structureService->get('evangiles');
$field = isset($structure['fields']['versets']) ? $structure['fields']['versets'] : array();

$classChoices = isset($field['class_choices']) && is_array($field['class_choices'])
    ? $field['class_choices']
    : array(
        'citation-matthieu' => 'citation-matthieu',
        'citation-marc' => 'citation-marc',
        'citation-luc' => 'citation-luc',
        'citation-jean' => 'citation-jean',
        'citation-bible' => 'citation-bible',
    );

$normalizedData = $structureService->normalizeSectionData('evangiles', isset($data) && is_array($data) ? $data : array());
$classChoiceKeys = array_keys($classChoices);
$defaultClass = !empty($classChoiceKeys) ? (string) $classChoiceKeys[0] : '';
$versets = isset($normalizedData['versets']) && is_array($normalizedData['versets']) ? $normalizedData['versets'] : array();
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
<input type="hidden" name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets_present]" value="1">

<div class="schilo-bible-ref-field">
    <strong>Références bibliques</strong>
    <p class="description">
        Saisis uniquement la référence biblique, par exemple <code>Luc 23.28-31</code>.
        Le shortcode <code>[bnv]...[/bnv]</code> sera ajouté automatiquement côté front.
    </p>

    <div class="schilo-bible-ref-items">
        <?php foreach ($versets as $itemIndex => $item) : ?>
            <?php
            $lineLabel = isset($item['label']) ? $item['label'] : '';
            $lineClass = isset($item['class']) ? $item['class'] : $defaultClass;
            $lineReference = isset($item['reference']) ? $item['reference'] : '';
            ?>
            <div class="schilo-bible-ref-item">
                <label>
                    <span>Ligne</span>
                    <input type="text" class="widefat"
                           name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][<?php echo esc_attr($itemIndex); ?>][label]"
                           value="<?php echo esc_attr($lineLabel); ?>">
                </label>

                <label>
                    <span>Classe CSS</span>
                    <select class="widefat"
                            name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][<?php echo esc_attr($itemIndex); ?>][class]">
                        <?php foreach ($classChoices as $classValue => $classLabel) : ?>
                            <option value="<?php echo esc_attr($classValue); ?>" <?php selected($lineClass, $classValue); ?>>
                                <?php echo esc_html($classLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="schilo-bible-ref-reference">
                    <span>Référence</span>
                    <input type="text" class="widefat"
                           name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][<?php echo esc_attr($itemIndex); ?>][reference]"
                           value="<?php echo esc_attr($lineReference); ?>"
                           placeholder="Exemple : Luc 23.28-31">
                </label>

                <button type="button" class="button schilo-remove-bible-ref-item">Retirer</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="button schilo-add-bible-ref-item">+ Ajouter une référence</button>

    <script type="text/template" class="schilo-bible-ref-template">
        <div class="schilo-bible-ref-item">
            <label>
                <span>Ligne</span>
                <input type="text" class="widefat" name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][__ITEM_INDEX__][label]" value="">
            </label>

            <label>
                <span>Classe CSS</span>
                <select class="widefat" name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][__ITEM_INDEX__][class]">
                    <?php foreach ($classChoices as $classValue => $classLabel) : ?>
                        <option value="<?php echo esc_attr($classValue); ?>" <?php selected($classValue, $defaultClass); ?>>
                            <?php echo esc_html($classLabel); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="schilo-bible-ref-reference">
                <span>Référence</span>
                <input type="text" class="widefat" name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][__ITEM_INDEX__][reference]" value="" placeholder="Exemple : Luc 23.28-31">
            </label>

            <button type="button" class="button schilo-remove-bible-ref-item">Retirer</button>
        </div>
    </script>
</div>
