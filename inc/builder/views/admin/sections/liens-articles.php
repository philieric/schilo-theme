<?php
/** Variables : $section, $index, $data */
use Schilo\Builder\Service\SectionStructureService;

$structureService = new SectionStructureService();
$normalizedData = $structureService->normalizeSectionData('liens-articles', isset($data) && is_array($data) ? $data : array());

$links = isset($normalizedData['links']) && is_array($normalizedData['links']) ? $normalizedData['links'] : array();
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

<div class="schilo-field-row">
    <label>
        <strong>Texte d'introduction</strong>
        <input type="text" class="widefat"
               name="schilo_sections[<?php echo esc_attr($index); ?>][data][intro]"
               value="<?php echo esc_attr($normalizedData['intro']); ?>"
               placeholder="Vous pouvez consulter les annexes">
    </label>
</div>

<div class="schilo-optional-text <?php echo trim((string) $normalizedData['texte_libre']) !== '' ? 'schilo-optional-expanded' : 'schilo-optional-collapsed'; ?>" data-field="texte_libre" data-label="Texte libre" data-placeholder="Texte libre affiché au-dessus des liens">
    <?php if (trim((string) $normalizedData['texte_libre']) !== '') : ?>
        <div class="schilo-field-row">
            <label>
                <strong>Texte libre</strong>
                <input type="text" class="widefat"
                       name="schilo_sections[<?php echo esc_attr($index); ?>][data][texte_libre]"
                       value="<?php echo esc_attr($normalizedData['texte_libre']); ?>"
                       placeholder="Texte libre affiché au-dessus des liens">
            </label>
        </div>
        <button type="button" class="button-link schilo-remove-text-field">Retirer ce texte</button>
    <?php else : ?>
        <button type="button" class="button schilo-add-optional-text" data-field="texte_libre" data-label="Texte libre" data-placeholder="Texte libre affiché au-dessus des liens">+ Ajouter un texte</button>
    <?php endif; ?>
</div>

<div class="schilo-links-field">
    <strong>Liens</strong>
    <p class="description">Un lien par ligne : texte affiché + URL (vers un autre article, péricope, page...).</p>

    <div class="schilo-links-items">
        <?php foreach ($links as $linkIndex => $link) : ?>
            <?php
            $label = isset($link['label']) ? $link['label'] : '';
            $url = isset($link['url']) ? $link['url'] : '';

            $articleSearchValue = $url;
            if ($url !== '') {
                $matchedPostId = url_to_postid($url);
                if ($matchedPostId > 0) {
                    $articleSearchValue = html_entity_decode(get_the_title($matchedPostId), ENT_QUOTES, 'UTF-8');
                }
            }
            ?>
            <div class="schilo-link-item">
                <input type="text" class="widefat schilo-link-label"
                       name="schilo_sections[<?php echo esc_attr($index); ?>][data][links][<?php echo esc_attr($linkIndex); ?>][label]"
                       value="<?php echo esc_attr($label); ?>"
                       placeholder="Texte affiché, ex : Voir la péricope suivante">
                <div class="schilo-combobox">
                    <input type="text" class="widefat schilo-link-article-search"
                           autocomplete="off"
                           value="<?php echo esc_attr($articleSearchValue); ?>"
                           placeholder="Rechercher un article, ou coller une URL">
                    <ul class="schilo-combobox-list"></ul>
                </div>
                <input type="url" class="widefat schilo-link-url"
                       name="schilo_sections[<?php echo esc_attr($index); ?>][data][links][<?php echo esc_attr($linkIndex); ?>][url]"
                       value="<?php echo esc_attr($url); ?>"
                       placeholder="https://...">
                <button type="button" class="button schilo-remove-link">Retirer</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="button schilo-add-link">+ Ajouter un lien</button>
</div>
