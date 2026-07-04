<?php
/** @var array $sectionTypes */
/** @var bool $saved */
?>

<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Sections</h1>

    <p class="schilo-admin-back">
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">
            ← Retour au tableau de bord
        </a>
    </p>

    <?php if (!empty($saved)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Configuration des sections enregistrée.</p>
        </div>
    <?php endif; ?>

    <div class="schilo-settings-card">
        <h2>Sections disponibles dans le builder</h2>

        <p class="description">
            Chaque section peut maintenant définir deux vues PHP :
            <br>— <strong>Vue front PHP</strong> : affichage public dans l’article.
            <br>— <strong>Vue admin PHP</strong> : formulaire de saisie dans l’administration.
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('schilo_builder_save_sections', 'schilo_builder_sections_nonce'); ?>

            <table class="widefat striped schilo-sections-config-table">
                <thead>
                    <tr>
                        <th style="width:70px;">Actif</th>
                        <th style="width:150px;">Clé</th>
                        <th style="width:190px;">Libellé</th>
                        <th>Description</th>
                        <th style="width:170px;">Vue front PHP</th>
                        <th style="width:170px;">Vue admin PHP</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>
                <tbody id="schilo-section-type-rows">
                    <?php $index = 0; ?>
                    <?php foreach ($sectionTypes as $typeKey => $typeConfig) : ?>
                        <tr>
                            <td>
                                <input type="checkbox"
                                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[<?php echo esc_attr($index); ?>][active]"
                                       value="1"
                                       <?php checked(!empty($typeConfig['active'])); ?>>
                            </td>

                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[<?php echo esc_attr($index); ?>][key]"
                                       value="<?php echo esc_attr($typeKey); ?>"
                                       class="regular-text schilo-section-key-input">
                            </td>

                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[<?php echo esc_attr($index); ?>][label]"
                                       value="<?php echo esc_attr(isset($typeConfig['label']) ? $typeConfig['label'] : $typeKey); ?>"
                                       class="regular-text">
                            </td>

                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[<?php echo esc_attr($index); ?>][description]"
                                       value="<?php echo esc_attr(isset($typeConfig['description']) ? $typeConfig['description'] : ''); ?>"
                                       class="large-text">
                            </td>

                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[<?php echo esc_attr($index); ?>][view]"
                                       value="<?php echo esc_attr(isset($typeConfig['view']) ? $typeConfig['view'] : $typeKey . '.php'); ?>"
                                       class="regular-text"
                                       placeholder="ex: paragraphe.php">
                            </td>

                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[<?php echo esc_attr($index); ?>][admin_view]"
                                       value="<?php echo esc_attr(isset($typeConfig['admin_view']) ? $typeConfig['admin_view'] : 'default.php'); ?>"
                                       class="regular-text"
                                       placeholder="ex: default.php">
                            </td>

                            <td>
                                <button type="button" class="button schilo-remove-section-type-row">Supprimer</button>
                            </td>
                        </tr>
                        <?php $index++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="schilo-add-section-type-row">+ Ajouter une section</button>
            </p>

            <?php submit_button('Enregistrer les sections'); ?>

            <p>
                <button type="submit"
                        name="schilo_reset_sections"
                        value="1"
                        class="button"
                        onclick="return confirm('Réinitialiser les sections par défaut ?');">
                    Réinitialiser les sections par défaut
                </button>
            </p>
        </form>
    </div>

    <script type="text/template" id="schilo-section-type-row-template">
        <tr>
            <td>
                <input type="checkbox"
                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[__INDEX__][active]"
                       value="1"
                       checked>
            </td>

            <td>
                <input type="text"
                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[__INDEX__][key]"
                       value=""
                       class="regular-text schilo-section-key-input">
            </td>

            <td>
                <input type="text"
                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[__INDEX__][label]"
                       value=""
                       class="regular-text">
            </td>

            <td>
                <input type="text"
                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[__INDEX__][description]"
                       value=""
                       class="large-text">
            </td>

            <td>
                <input type="text"
                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[__INDEX__][view]"
                       value=""
                       class="regular-text"
                       placeholder="ex: ma-section.php">
            </td>

            <td>
                <input type="text"
                       name="<?php echo esc_attr(\Schilo\Builder\Service\SectionTypeService::OPTION_SECTION_TYPES); ?>[__INDEX__][admin_view]"
                       value="default.php"
                       class="regular-text"
                       placeholder="ex: default.php">
            </td>

            <td>
                <button type="button" class="button schilo-remove-section-type-row">Supprimer</button>
            </td>
        </tr>
    </script>
</div>
