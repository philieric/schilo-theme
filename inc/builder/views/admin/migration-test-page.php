<?php
/** @var array $candidates */
/** @var array $availablePrefixes */
/** @var array $postsByPrefix */
/** @var string $selectedPrefix */
/** @var int $testPostId */
/** @var WP_Post|null $testPost */
/** @var string $renderedHtml */
/** @var array $extractedElements */
/** @var array|null $templateForPrefix */
/** @var array $sectionTypes */
/** @var array $destinationFieldsByType */
/** @var array $savedAssignment */
/** @var bool $mappingSaved */
/** @var bool $modelSaved */
/** @var bool $modelDeleted */
/** @var array $modelsForPrefix */
/** @var string $selectedModelId */
/** @var bool $migrationApplied */
/** @var string $migrationError */
/** @var array|null $batchResult */
?>

<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Migration (test extracteurs)</h1>

    <nav class="scl-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-migration-test')); ?>" class="scl-tab">Liste</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'test', admin_url('admin.php?page=schilo-builder-migration-test'))); ?>" class="scl-tab scl-tab-active">Test / Mapping</a>
    </nav>

    <p class="schilo-admin-back">
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">
            ← Retour au tableau de bord
        </a>
    </p>

    <?php if (!empty($mappingSaved)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Correspondances enregistrées pour cet article.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($modelSaved)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Modèle de migration enregistré — il peut maintenant être réutilisé pour les autres articles <?php echo esc_html($selectedPrefix); ?>.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($modelDeleted)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Modèle de migration supprimé.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($migrationApplied)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                Migration appliquée : les sections ont été créées dans le builder.
                <?php if ($testPostId > 0) : ?>
                    <a href="<?php echo esc_url(get_edit_post_link($testPostId)); ?>">Voir l'article dans l'éditeur</a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="schilo-migration-test-layout">
        <div class="schilo-migration-test-prefixes">
            <strong>Type d'article</strong>
            <ul class="schilo-migration-prefix-list">
                <?php foreach ($availablePrefixes as $prefixOption) : ?>
                    <?php $prefixCandidateCount = isset($postsByPrefix[$prefixOption]) ? count($postsByPrefix[$prefixOption]) : 0; ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg(array('tab' => 'test', 'schilo_test_prefix' => $prefixOption, 'schilo_test_post' => false), admin_url('admin.php?page=schilo-builder-migration-test'))); ?>"
                           class="<?php echo $prefixOption === $selectedPrefix ? 'is-active' : ''; ?>">
                            <?php echo esc_html($prefixOption); ?>
                            <span class="schilo-migration-prefix-count">(<?php echo esc_html((string) $prefixCandidateCount); ?>)</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="schilo-migration-test-main">
            <div class="schilo-settings-card">
                <h2>Choisir un article <?php echo esc_html($selectedPrefix); ?> à tester</h2>
                <form method="get" action="">
                    <input type="hidden" name="page" value="schilo-builder-migration-test">
                    <input type="hidden" name="tab" value="test">
                    <input type="hidden" name="schilo_test_prefix" value="<?php echo esc_attr($selectedPrefix); ?>">
                    <select name="schilo_test_post">
                        <option value="">— Choisir un article (<?php echo esc_html(count($candidates)); ?> disponible(s)) —</option>
                        <?php foreach ($candidates as $candidate) : ?>
                            <option value="<?php echo esc_attr($candidate->ID); ?>" <?php selected($testPostId, $candidate->ID); ?>>
                                <?php echo esc_html(get_the_title($candidate)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary">Tester l'extraction</button>
                </form>
            </div>

            <div class="schilo-settings-card">
                <h2>Modèles de migration <?php echo esc_html($selectedPrefix); ?> enregistrés</h2>
                <p class="description">
                    Un modèle s'applique automatiquement à tous les éléments répétables trouvés sur l'article
                    (ex : tous les liens « Consultation »), quel que soit leur nombre exact.
                </p>

                <?php if (empty($modelsForPrefix)) : ?>
                    <p class="description">Aucun modèle enregistré pour ce type d'article pour le moment.</p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Nom du modèle</th>
                                <th style="width:160px;">Dernière mise à jour</th>
                                <th style="width:220px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modelsForPrefix as $modelIdOption => $modelOption) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($modelOption['name']); ?></strong>
                                        <?php if ($modelIdOption === $selectedModelId) : ?>
                                            <span class="schilo-badge-primary">chargé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($modelOption['updated_at']); ?></td>
                                    <td>
                                        <?php if ($testPost) : ?>
                                            <a class="button"
                                               href="<?php echo esc_url(add_query_arg(array('tab' => 'test', 'schilo_test_prefix' => $selectedPrefix, 'schilo_test_post' => $testPost->ID, 'schilo_test_model' => $modelIdOption), admin_url('admin.php?page=schilo-builder-migration-test'))); ?>">
                                                Charger sur cet article
                                            </a>
                                        <?php endif; ?>
                                        <form method="post" action="" class="schilo-inline-form" onsubmit="return confirm('Supprimer ce modèle de migration ?');">
                                            <?php wp_nonce_field('schilo_builder_migration_test', 'schilo_migration_test_nonce'); ?>
                                            <input type="hidden" name="post_id" value="<?php echo esc_attr($testPostId); ?>">
                                            <input type="hidden" name="schilo_test_prefix" value="<?php echo esc_attr($selectedPrefix); ?>">
                                            <input type="hidden" name="schilo_delete_model" value="<?php echo esc_attr($modelIdOption); ?>">
                                            <button type="submit" class="button-link-delete">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

    <?php if (!is_null($batchResult)) : ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>Migration en masse terminée.</strong>
                    Migrés : <strong><?php echo count($batchResult['ok']); ?></strong>.
                    Ignorés (déjà migrés) : <strong><?php echo count($batchResult['skip']); ?></strong>.
                    Erreurs : <strong><?php echo count($batchResult['error']); ?></strong>.
                    <?php if (!empty($batchResult['error'])) : ?>
                        <br>Erreurs : <?php foreach ($batchResult['error'] as $batchErr) { echo esc_html('Article #' . $batchErr['id'] . ' — ' . $batchErr['msg'] . ' / '); } ?>
                    <?php endif; ?>
                </p>
            </div>
    <?php endif; ?>

    <?php if (!empty($modelsForPrefix)) : ?>
            <div class="schilo-settings-card">
                <h2>Migration en masse — <?php echo esc_html($selectedPrefix); ?></h2>
                <p class="description">
                    Applique un modèle enregistré à tous les articles <strong><?php echo esc_html($selectedPrefix); ?></strong> non encore migrés, en une seule opération.
                </p>
                <form method="post" action="" onsubmit="return confirm('Lancer la migration en masse sur tous les articles <?php echo esc_js($selectedPrefix); ?> non migrés ? Cette opération est réversible article par article via « Restaurer ».');">
                    <?php wp_nonce_field('schilo_builder_migration_test', 'schilo_migration_test_nonce'); ?>
                    <input type="hidden" name="post_id" value="0">
                    <input type="hidden" name="schilo_test_prefix" value="<?php echo esc_attr($selectedPrefix); ?>">
                    <input type="hidden" name="schilo_batch_prefix" value="<?php echo esc_attr($selectedPrefix); ?>">
                    <input type="hidden" name="schilo_batch_migrate" value="1">

                    <div class="schilo-field-row">
                        <label>
                            <strong>Modèle à appliquer</strong>
                            <select name="schilo_batch_model_id" required>
                                <?php foreach ($modelsForPrefix as $modelIdOption => $modelOption) : ?>
                                    <option value="<?php echo esc_attr($modelIdOption); ?>">
                                        <?php echo esc_html($modelOption['name']); ?>
                                        (mis à jour le <?php echo esc_html(substr($modelOption['updated_at'], 0, 10)); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <p>
                        <label>
                            <input type="checkbox" name="schilo_batch_redo" value="1">
                            Ré-appliquer aussi aux articles déjà migrés (écrase les sections existantes)
                        </label>
                    </p>

                    <p>
                        <button type="submit" class="button button-primary">
                            Appliquer à tous les articles <?php echo esc_html($selectedPrefix); ?> non migrés
                        </button>
                    </p>
                </form>
            </div>
    <?php endif; ?>

    <?php if ($testPost) : ?>
            <div class="schilo-settings-card">
                <h2>Construire le mapping de migration</h2>
                <p>
                    Article : <strong><?php echo esc_html(get_the_title($testPost)); ?></strong>
                    (<a href="<?php echo esc_url(get_edit_post_link($testPost->ID)); ?>">modifier l'article</a>)
                </p>

                <?php if (empty($extractedElements)) : ?>
                    <p>Aucun élément détecté par les extracteurs actuellement enregistrés.</p>
                <?php else : ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('schilo_builder_migration_test', 'schilo_migration_test_nonce'); ?>
                        <input type="hidden" name="post_id" value="<?php echo esc_attr($testPost->ID); ?>">
                        <input type="hidden" name="schilo_test_prefix" value="<?php echo esc_attr($selectedPrefix); ?>">

                        <div class="schilo-migration-assistant-layout">
                            <div class="schilo-migration-template-structure">
                                <strong>Structure du template <?php echo esc_html($selectedPrefix); ?> (dans l'ordre)</strong>
                                <ol>
                                    <?php if ($templateForPrefix) : ?>
                                        <?php foreach ((array) $templateForPrefix['sections'] as $structureSectionKey) : ?>
                                            <?php
                                            $structureLabel = isset($sectionTypes[$structureSectionKey]['label'])
                                                ? $sectionTypes[$structureSectionKey]['label']
                                                : $structureSectionKey;
                                            ?>
                                            <li><?php echo esc_html($structureLabel); ?></li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ol>
                            </div>

                            <div class="schilo-migration-mapping">
                                <?php
                                $groupedElements = array();
                                foreach ($extractedElements as $element) {
                                    $groupKey = isset($element['extractor_key']) ? $element['extractor_key'] : 'autre';
                                    $groupedElements[$groupKey][] = $element;
                                }
                                ?>

                                <?php
                                // Suggestions automatiques : extractor_key => section cible + champs
                                $extractorSuggestions = array(
                                    "consultation"      => array("section" => "liens-articles",             "fields" => array("heading" => "section_title", "link" => "links_auto",   "text" => "intro")),
                                    "evangile"          => array("section" => "evangiles",                  "fields" => array("heading" => "section_title", "verset" => "versets_auto")),
                                    "details_techniques"=> array("section" => "detail-technique-img-droite","fields" => array("heading" => "section_title", "lieu" => "lieu", "date" => "date", "mode_operatoire" => "mode_operatoire", "note" => "note_mode_operatoire", "image" => "image_id", "texte_dessous" => "texte_dessous")),
                                    "image_textes"      => array("section" => "image-textes",              "fields" => array("heading" => "section_title", "image" => "image_id")),
                                    "references"        => array("section" => "liens-articles",             "fields" => array("heading" => "section_title", "link" => "links_auto")),
                                    "commentaire"       => array("section" => "paragraphe",                 "fields" => array("heading" => "section_title", "content" => "content")),
                                    "section_texte"     => array("section" => "paragraphe",                 "fields" => array("heading" => "section_title", "content" => "content")),
                                );
                                ?>

                                <?php foreach ($groupedElements as $groupKey => $groupElements) : ?>
                                    <?php
                                    $firstElementId = $groupElements[0]['id'];
                                    $existingGroupType = isset($savedAssignment[$firstElementId]['section_type']) ? $savedAssignment[$firstElementId]['section_type'] : '';
                                    // Si pas d'assignation sauvegardée, on propose la section suggérée
                                    $activeGroupType = $existingGroupType !== '' ? $existingGroupType
                                        : (isset($extractorSuggestions[$groupKey]['section']) ? $extractorSuggestions[$groupKey]['section'] : '');
                                    $groupLabel = isset($groupElements[0]['label']) ? preg_replace('/\s*—.*/u', '', $groupElements[0]['label']) : $groupKey;
                                    ?>
                                    <div class="schilo-migration-group" data-group-key="<?php echo esc_attr($groupKey); ?>">
                                        <div class="schilo-migration-group-header">
                                            <strong><?php echo esc_html($groupLabel); ?></strong>

                                            <label class="schilo-migration-group-destination">
                                                Destination (section) :
                                                <select name="schilo_group_section[<?php echo esc_attr($groupKey); ?>]" class="schilo-migration-section-select" data-group-key="<?php echo esc_attr($groupKey); ?>">
                                                    <option value="ignore">— Ignorer —</option>
                                                    <?php if ($templateForPrefix) : ?>
                                                        <?php foreach ((array) $templateForPrefix['sections'] as $structureSectionKey) : ?>
                                                            <?php
                                                            $structureLabel = isset($sectionTypes[$structureSectionKey]['label'])
                                                                ? $sectionTypes[$structureSectionKey]['label']
                                                                : $structureSectionKey;
                                                            ?>
                                                            <option value="<?php echo esc_attr($structureSectionKey); ?>" <?php selected($activeGroupType, $structureSectionKey); ?>>
                                                                <?php echo esc_html($structureLabel); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </label>
                                        </div>

                                        <table class="widefat striped schilo-migration-mapping-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:220px;">Élément détecté</th>
                                                    <th>Contenu extrait</th>
                                                    <th style="width:280px;">Champ de destination</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($groupElements as $elementIndex => $element) : ?>
                                                    <?php
                                                    $elementId = $element['id'];
                                                    $existing = isset($savedAssignment[$elementId]) ? $savedAssignment[$elementId] : array();
                                                    $existingField = isset($existing['field']) ? $existing['field'] : '';

                                                    // Détecte s'il s'agit d'une occurrence répétée (ex: "consultation_link_2")
                                                    // d'un élément déjà représenté plus haut dans le groupe (ex: "consultation_link").
                                                    // Pour la construction du modèle, seule la première occurrence de chaque
                                                    // motif a besoin d'un champ de destination : les suivantes seront générées
                                                    // automatiquement à l'identique lors de l'application, quel que soit leur
                                                    // nombre réel sur l'article.
                                                    $isRepeatedOccurrence = (bool) preg_match('/_\d+$/', $elementId);

                                                    if ($existingField === '' && $isRepeatedOccurrence) {
                                                        $existingField = 'ignore';
                                                    }

                                                    $isEditableText = !empty($element['meta']['editable']);

                                                    // Suggestion de champ basée sur le suffixe de l'ID de l'élément
                                                    $suggestedField = "";
                                                    if ($existingField === "" && !$isRepeatedOccurrence && isset($extractorSuggestions[$groupKey]["fields"])) {
                                                        // Extrait le suffixe : "consultation_link" -> "link", "details_techniques_heading" -> "heading"
                                                        $idWithoutGroup = ltrim(str_replace($groupKey . "_", "", $elementId), "_");
                                                        // Retire le numéro final si présent : "link_1" -> "link"
                                                        $fieldKey = preg_replace("/_\d+$/", "", $idWithoutGroup);
                                                        if (isset($extractorSuggestions[$groupKey]["fields"][$fieldKey])) {
                                                            $suggestedField = $extractorSuggestions[$groupKey]["fields"][$fieldKey];
                                                        }
                                                    }
                                                    ?>
                                                    <tr class="schilo-migration-mapping-row" data-element-id="<?php echo esc_attr($elementId); ?>" data-group-key="<?php echo esc_attr($groupKey); ?>">
                                                        <td>
                                                            <strong><?php echo esc_html($element['label']); ?></strong>
                                                            <p class="description"><code><?php echo esc_html($elementId); ?></code></p>
                                                        </td>
                                                        <td>
                                                            <?php if ($isEditableText) : ?>
                                                                <input type="text" class="widefat"
                                                                       name="schilo_element_content[<?php echo esc_attr($elementId); ?>]"
                                                                       value="<?php echo esc_attr($element['content']); ?>">
                                                            <?php else : ?>
                                                                <span class="schilo-migration-element-preview">
                                                                    <?php echo esc_html($element['content']); ?>
                                                                    <?php if (!empty($element['meta']['url'])) : ?>
                                                                        <br><code><?php echo esc_html($element['meta']['url']); ?></code>
                                                                    <?php endif; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <input type="hidden" name="schilo_element_assignment[<?php echo esc_attr($elementId); ?>][group]" value="<?php echo esc_attr($groupKey); ?>">
                                                            <select name="schilo_element_assignment[<?php echo esc_attr($elementId); ?>][field]" class="schilo-migration-field-select" data-existing="<?php echo esc_attr($existingField); ?>" data-suggested="<?php echo esc_attr($suggestedField); ?>">
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>

                                <p>
                                    <button type="submit" class="button button-primary">Enregistrer les correspondances</button>
                                    <button type="submit" name="schilo_apply_migration" value="1" class="button button-primary"
                                            onclick="return confirm('Appliquer la migration ? Les sections existantes de cet article (créées via Schilo Builder) seront remplacées par celles générées depuis ce mapping.');">
                                        Appliquer la migration sur cet article
                                    </button>
                                </p>

                                <div class="schilo-migration-save-model">
                                    <label>
                                        <input type="checkbox" name="schilo_save_as_model" value="1" id="schilo-save-as-model-checkbox" <?php checked($selectedModelId !== ''); ?>>
                                        Enregistrer aussi comme modèle de migration réutilisable, nommé :
                                        <input type="text"
                                               name="schilo_model_name"
                                               value="<?php echo esc_attr($selectedModelId !== '' && isset($modelsForPrefix[$selectedModelId]) ? $modelsForPrefix[$selectedModelId]['name'] : $selectedPrefix . ' standard'); ?>"
                                               placeholder="ex : PER standard">
                                    </label>
                                    <input type="hidden" name="schilo_model_id" value="<?php echo esc_attr($selectedModelId); ?>">
                                    <p class="description">
                                        Ce modèle sera proposé pour tous les futurs articles <?php echo esc_html($selectedPrefix); ?>.
                                        <?php if ($selectedModelId !== '' && isset($modelsForPrefix[$selectedModelId])) : ?>
                                            Cochée, la case mettra à jour le modèle existant <strong><?php echo esc_html($modelsForPrefix[$selectedModelId]['name']); ?></strong> ; décochée, elle ne touchera que cet article.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="schilo-settings-card">
                <h2>HTML rendu complet (debug)</h2>
                <p class="description">Affiché pour vérifier que le rendu fonctionne correctement (shortcodes exécutés).</p>
                <textarea readonly rows="20" class="widefat code" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea($renderedHtml); ?></textarea>
            </div>
        <?php endif; ?>
    <?php if ($testPost && !empty($extractedElements)) : ?>
        <script>
        (function () {
            const fieldOptionsByType = <?php echo wp_json_encode($destinationFieldsByType); ?>;

            function getGroupSectionSelect(groupKey) {
                return document.querySelector('.schilo-migration-section-select[data-group-key="' + groupKey + '"]');
            }

            function populateFieldSelect(row) {
                const groupKey = row.getAttribute('data-group-key');
                const sectionSelect = getGroupSectionSelect(groupKey);
                const fieldSelect = row.querySelector('.schilo-migration-field-select');

                if (!sectionSelect || !fieldSelect) {
                    return;
                }

                const sectionType = sectionSelect.value;
                const existing = fieldSelect.getAttribute('data-existing') || '';
                const suggested = fieldSelect.getAttribute('data-suggested') || '';
                // Valeur active : existante si déjà sauvegardée, sinon suggestion, sinon vide
                const activeValue = existing !== '' ? existing : suggested;

                fieldSelect.innerHTML = '';

                if (sectionType === 'ignore' || sectionType === '') {
                    fieldSelect.disabled = true;
                    return;
                }

                fieldSelect.disabled = false;

                const ignoreOption = document.createElement('option');
                ignoreOption.value = 'ignore';
                ignoreOption.textContent = '— Ignorer —';

                if (activeValue === 'ignore' || activeValue === '') {
                    ignoreOption.selected = true;
                }

                fieldSelect.appendChild(ignoreOption);

                const options = fieldOptionsByType[sectionType] || [];

                options.forEach(function (option) {
                    const optionEl = document.createElement('option');
                    optionEl.value = option.value;
                    optionEl.textContent = option.label;

                    if (option.value === activeValue) {
                        optionEl.selected = true;
                    }

                    fieldSelect.appendChild(optionEl);
                });
            }

            function populateGroup(groupKey) {
                document.querySelectorAll('.schilo-migration-mapping-row[data-group-key="' + groupKey + '"]').forEach(function (row) {
                    populateFieldSelect(row);
                });
            }

            document.querySelectorAll('.schilo-migration-section-select').forEach(function (sectionSelect) {
                const groupKey = sectionSelect.getAttribute('data-group-key');

                populateGroup(groupKey);

                sectionSelect.addEventListener('change', function () {
                    populateGroup(groupKey);
                });
            });
        })();
        </script>
    <?php endif; ?>

        </div>
    </div>
</div>

