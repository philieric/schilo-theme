<?php
use Schilo\Builder\Service\SectionStructureService;

$structureService = new SectionStructureService();
$structure = $structureService->get('evangiles');
$field = isset($structure['fields']['versets']) ? $structure['fields']['versets'] : array();

$classChoices = isset($field['class_choices']) && is_array($field['class_choices']) ? $field['class_choices'] : array();
$defaultItems = isset($field['default_items']) && is_array($field['default_items']) ? $field['default_items'] : array();
$classChoiceKeys = array_keys($classChoices);
$defaultClass = !empty($classChoiceKeys) ? (string) $classChoiceKeys[0] : '';

$sectionData = isset($sectionData) && is_array($sectionData) ? $sectionData : array();
$normalizedData = $structureService->normalizeSectionData('evangiles', $sectionData);
$versets = isset($normalizedData['versets']) && is_array($normalizedData['versets']) ? $normalizedData['versets'] : array();

if (empty($versets)) {
    $versets = $defaultItems;
}
?>

<div class="schilo-evangiles-fields" data-section-index="<?php echo esc_attr($index); ?>">
    <p class="description">
        Saisis uniquement la référence, par exemple <code>Luc 23.28-31</code>.
        Le shortcode <code>[bnv]...[/bnv]</code> sera ajouté automatiquement côté front.
    </p>

    <div class="schilo-evangiles-lines">
        <?php foreach ($versets as $lineIndex => $line) : ?>
            <div class="schilo-evangile-line">
                <label>
                    <span>Ligne</span>
                    <input type="text"
                           name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][<?php echo esc_attr($lineIndex); ?>][label]"
                           value="<?php echo esc_attr(isset($line['label']) ? $line['label'] : ''); ?>"
                           class="regular-text">
                </label>

                <label>
                    <span>Classe CSS</span>
                    <select name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][<?php echo esc_attr($lineIndex); ?>][class]">
                        <?php foreach ($classChoices as $classValue => $classLabel) : ?>
                            <option value="<?php echo esc_attr($classValue); ?>" <?php selected(isset($line['class']) ? $line['class'] : '', $classValue); ?>>
                                <?php echo esc_html($classLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="schilo-evangile-reference-field">
                    <span>Référence</span>
                    <input type="text"
                           name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][<?php echo esc_attr($lineIndex); ?>][reference]"
                           value="<?php echo esc_attr(isset($line['reference']) ? $line['reference'] : ''); ?>"
                           class="large-text"
                           placeholder="Exemple : Luc 23.28-31">
                </label>

                <button type="button" class="button schilo-remove-evangile-line">Supprimer</button>
            </div>
        <?php endforeach; ?>
    </div>

    <p>
        <button type="button" class="button schilo-add-evangile-line">+ Ajouter une référence</button>
    </p>

    <script type="text/template" class="schilo-evangile-line-template">
        <div class="schilo-evangile-line">
            <label>
                <span>Ligne</span>
                <input type="text" name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][__LINE_INDEX__][label]" value="" class="regular-text">
            </label>
            <label>
                <span>Classe CSS</span>
                <select name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][__LINE_INDEX__][class]">
                    <?php foreach ($classChoices as $classValue => $classLabel) : ?>
                        <option value="<?php echo esc_attr($classValue); ?>" <?php selected($classValue, $defaultClass); ?>><?php echo esc_html($classLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="schilo-evangile-reference-field">
                <span>Référence</span>
                <input type="text" name="schilo_sections[<?php echo esc_attr($index); ?>][data][versets][__LINE_INDEX__][reference]" value="" class="large-text" placeholder="Exemple : Luc 23.28-31">
            </label>
            <button type="button" class="button schilo-remove-evangile-line">Supprimer</button>
        </div>
    </script>
</div>
