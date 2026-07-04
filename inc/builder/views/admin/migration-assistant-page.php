<?php
/** @var \Schilo\Builder\Service\WPBakeryMigrationService $service */
/** @var WP_Post $configurePost */
/** @var array $configureElements */
/** @var array $configureTemplates */
/** @var array $configureSectionTypes */
/** @var array|null $configureDetectedTemplate */
/** @var array $configureFieldMappings */
/** @var array $configureTemplateMapping */
/** @var array $configureAllTemplateMappings */
?>

<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Assistant de migration</h1>

    <p class="schilo-admin-back">
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-migration')); ?>" class="button">
            ← Retour à la liste des articles
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-migration-mapping')); ?>" class="button">
            Configurer le mapping de migration
        </a>
    </p>

    <?php if (!empty($message)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="schilo-settings-card" id="schilo-migration-assistant">
        <h2>Assistant de migration</h2>
        <p>
            Article :
            <strong><?php echo esc_html(get_the_title($configurePost->ID)); ?></strong>
            (<a href="<?php echo esc_url(get_edit_post_link($configurePost->ID)); ?>">modifier l’article</a>)
        </p>

        <?php if (empty($configureElements)) : ?>
            <p>Aucun élément WPBakery / Wikilogy détecté dans cet article. La migration standard créera directement les sections vides du template choisi.</p>
        <?php else : ?>
            <p class="description">
                Pour chaque élément détecté dans l’article, choisissez la section de destination.
                Les sections du template non utilisées seront créées vides et pourront être complétées dans l’admin de la section.
            </p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-migration')); ?>">
            <?php wp_nonce_field('schilo_builder_migration', 'schilo_migration_nonce'); ?>
            <input type="hidden" name="post_id" value="<?php echo esc_attr($configurePost->ID); ?>">
            <input type="hidden" name="schilo_migration_action" value="configure_migrate">

            <div class="schilo-field-row">
                <label>
                    <strong>Template à appliquer</strong>
                    <select name="schilo_template_key" id="schilo-migration-template-select">
                        <?php foreach ($configureTemplates as $templateKey => $templateConfig) : ?>
                            <?php
                            $isDetected = $configureDetectedTemplate && $configureDetectedTemplate['key'] === $templateKey;
                            ?>
                            <option value="<?php echo esc_attr($templateKey); ?>" <?php selected($isDetected); ?> data-sections="<?php echo esc_attr(implode(',', $templateConfig['sections'])); ?>">
                                <?php echo esc_html($templateConfig['label']); ?> (<?php echo esc_html($templateKey); ?>)
                                <?php if ($isDetected) : ?> — détecté pour cet article<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="schilo-migration-assistant-layout">
                <div class="schilo-migration-template-structure">
                    <strong>Structure du template (sections, dans l’ordre)</strong>
                    <ol id="schilo-migration-template-sections-list">
                        <?php
                        $structureSections = $configureDetectedTemplate ? $configureDetectedTemplate['sections'] : array();
                        foreach ($structureSections as $structureSectionKey) :
                            $structureLabel = isset($configureSectionTypes[$structureSectionKey]['label'])
                                ? $configureSectionTypes[$structureSectionKey]['label']
                                : $structureSectionKey;
                        ?>
                            <li><?php echo esc_html($structureLabel); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <div class="schilo-migration-mapping">
                    <?php if (!empty($configureElements)) : ?>
                        <table class="widefat striped schilo-migration-mapping-table">
                            <thead>
                                <tr>
                                    <th style="width:200px;">Élément détecté</th>
                                    <th>Aperçu du contenu</th>
                                    <th style="width:240px;">Destination (section)</th>
                                    <th style="width:240px;">Champ de destination</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($configureElements as $element) : ?>
                                    <?php
                                    $previewText = wp_strip_all_tags($element['content']);
                                    $previewText = trim(preg_replace('/\s+/', ' ', $previewText));
                                    if (strlen($previewText) > 200) {
                                        $previewText = substr($previewText, 0, 200) . '…';
                                    }

                                    $plainText = trim(wp_strip_all_tags($element['content']));
                                    $hasAnnexeLinks = (bool) preg_match('/consulter l.{1,2}annexe/iu', $plainText);
                                    $isShortText = ($plainText !== '' && mb_strlen($plainText) <= 60 && !$hasAnnexeLinks);

                                    $templateSectionKeys = $configureDetectedTemplate ? (array) $configureDetectedTemplate['sections'] : array();

                                    $savedMapping = isset($configureTemplateMapping[$element['id']]) ? $configureTemplateMapping[$element['id']] : null;

                                    if ($savedMapping && !empty($savedMapping['type'])) {
                                        // 1. Un mapping enregistré pour ce template (PER, ANN...) est prioritaire.
                                        $preferredType = $savedMapping['type'];
                                        $preferredField = !empty($savedMapping['field']) ? $savedMapping['field'] : 'content';
                                    } else {
                                        $preferredType = $element['default_type'];

                                        if ($element['id'] === 'wikilogy_title' && in_array('titre-simple', $templateSectionKeys, true)) {
                                            $preferredType = 'titre-simple';
                                        } elseif (!in_array($preferredType, $templateSectionKeys, true)) {
                                            // Si le type par défaut n'est pas dans le template, on cherche
                                            // un type équivalent qui y figure.
                                            $equivalents = array(
                                                'references' => array('liens-articles'),
                                                'liens-articles' => array('references'),
                                                'intro' => array('titre-simple'),
                                            );

                                            if (!empty($equivalents[$preferredType])) {
                                                foreach ($equivalents[$preferredType] as $equivalent) {
                                                    if (in_array($equivalent, $templateSectionKeys, true)) {
                                                        $preferredType = $equivalent;
                                                        break;
                                                    }
                                                }
                                            }
                                        }

                                        // Champ de destination par défaut, selon le type préféré et le contenu détecté.
                                        if ($preferredType === 'liens-articles') {
                                            if ($hasAnnexeLinks) {
                                                $preferredField = 'links_auto';
                                            } elseif ($element['id'] === 'wikilogy_title' || $isShortText) {
                                                $preferredField = 'section_title';
                                            } else {
                                                $preferredField = 'intro';
                                            }
                                        } elseif ($preferredType === 'titre-simple') {
                                            $preferredField = 'section_title';
                                        } else {
                                            $preferredField = 'content';
                                        }
                                    }
                                    ?>
                                    <tr class="schilo-migration-mapping-row"
                                        data-has-annexe-links="<?php echo $hasAnnexeLinks ? '1' : '0'; ?>"
                                        data-is-short-text="<?php echo $isShortText ? '1' : '0'; ?>"
                                        data-element-id="<?php echo esc_attr($element['id']); ?>">
                                        <td>
                                            <strong><?php echo esc_html($element['label']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="schilo-migration-element-preview"><?php echo esc_html($previewText !== '' ? $previewText : '(vide)'); ?></span>
                                        </td>
                                        <td>
                                            <select name="schilo_element_mapping[<?php echo esc_attr($element['id']); ?>]" class="schilo-migration-section-select">
                                                <option value="ignore">— Ignorer cet élément —</option>
                                                <?php foreach ($configureSectionTypes as $sectionTypeKey => $sectionTypeConfig) : ?>
                                                    <?php
                                                    $selected = ($sectionTypeKey === $preferredType);
                                                    $inTemplate = in_array($sectionTypeKey, $templateSectionKeys, true);
                                                    ?>
                                                    <option value="<?php echo esc_attr($sectionTypeKey); ?>" <?php selected($selected); ?>>
                                                        <?php echo esc_html($sectionTypeConfig['label']); ?>
                                                        <?php if ($inTemplate) : ?> (dans le template)<?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="schilo_element_field[<?php echo esc_attr($element['id']); ?>]" class="schilo-migration-field-select" data-preferred="<?php echo esc_attr($preferredField); ?>">
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ($configureDetectedTemplate) : ?>
                        <p>
                            <label>
                                <input type="checkbox" name="schilo_save_as_template_mapping" value="1">
                                Enregistrer cette configuration comme mapping par défaut pour le template
                                <strong><?php echo esc_html($configureDetectedTemplate['label']); ?> (<?php echo esc_html($configureDetectedTemplate['key']); ?>)</strong>
                                — elle sera proposée automatiquement pour les prochaines migrations de ce type d’article.
                            </label>
                        </p>
                    <?php endif; ?>

                    <p>
                        <button type="submit" class="button button-primary" onclick="return confirm('Appliquer cette migration ? Le contenu original reste sauvegardé.');">
                            Appliquer la migration avec cette configuration
                        </button>
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const select = document.getElementById('schilo-migration-template-select');
    const list = document.getElementById('schilo-migration-template-sections-list');

    const labels = <?php
        $labelsMap = array();
        foreach ($configureSectionTypes as $sectionTypeKey => $sectionTypeConfig) {
            $labelsMap[$sectionTypeKey] = $sectionTypeConfig['label'];
        }
        echo wp_json_encode($labelsMap);
    ?>;

    const templateMappings = <?php echo wp_json_encode($configureAllTemplateMappings); ?>;

    function applyTemplateMappingPresets(templateKey) {
        const preset = templateMappings[templateKey];

        if (!preset) {
            return;
        }

        document.querySelectorAll('.schilo-migration-mapping-row').forEach(function (row) {
            const elementId = row.getAttribute('data-element-id');
            const entry = preset[elementId];

            if (!entry) {
                return;
            }

            const sectionSelect = row.querySelector('.schilo-migration-section-select');
            const fieldSelect = row.querySelector('.schilo-migration-field-select');

            if (sectionSelect && entry.type) {
                const hasOption = Array.prototype.some.call(sectionSelect.options, function (opt) {
                    return opt.value === entry.type;
                });

                if (hasOption) {
                    sectionSelect.value = entry.type;
                }
            }

            if (fieldSelect && entry.field) {
                fieldSelect.setAttribute('data-preferred', entry.field);
            }

            populateFieldSelect(row);
        });
    }

    if (select && list) {
        select.addEventListener('change', function () {
            const sections = select.options[select.selectedIndex].getAttribute('data-sections') || '';
            const keys = sections === '' ? [] : sections.split(',');

            list.innerHTML = '';

            keys.forEach(function (key) {
                const li = document.createElement('li');
                li.textContent = labels[key] || key;
                list.appendChild(li);
            });

            applyTemplateMappingPresets(select.value);
        });
    }

    // Champs disponibles selon le type de section choisi (configurable via
    // Schilo Builder → Mapping de migration).
    const fieldOptions = <?php echo wp_json_encode($configureFieldMappings); ?>;

    function populateFieldSelect(row) {
        const sectionSelect = row.querySelector('.schilo-migration-section-select');
        const fieldSelect = row.querySelector('.schilo-migration-field-select');

        if (!sectionSelect || !fieldSelect) {
            return;
        }

        const sectionType = sectionSelect.value;
        const preferred = fieldSelect.getAttribute('data-preferred') || 'content';

        fieldSelect.innerHTML = '';

        if (sectionType === 'ignore') {
            fieldSelect.disabled = true;
            return;
        }

        fieldSelect.disabled = false;

        const options = fieldOptions[sectionType] || fieldOptions['__default__'];

        options.forEach(function (option) {
            const optionEl = document.createElement('option');
            optionEl.value = option.value;
            optionEl.textContent = option.label;

            if (option.value === preferred) {
                optionEl.selected = true;
            }

            fieldSelect.appendChild(optionEl);
        });

        if (!fieldSelect.value && options.length) {
            fieldSelect.value = options[0].value;
        }
    }

    document.querySelectorAll('.schilo-migration-mapping-row').forEach(function (row) {
        populateFieldSelect(row);

        const sectionSelect = row.querySelector('.schilo-migration-section-select');

        if (sectionSelect) {
            sectionSelect.addEventListener('change', function () {
                populateFieldSelect(row);
            });
        }
    });
})();
</script>
