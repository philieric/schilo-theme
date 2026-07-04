<?php
/** @var array $mappings */
/** @var array $sectionTypes */
/** @var bool $saved */
?>

<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Mapping de migration</h1>

    <p class="schilo-admin-back">
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">
            ← Retour au tableau de bord
        </a>
    </p>

    <?php if (!empty($saved)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Mapping de migration enregistré.</p>
        </div>
    <?php endif; ?>

    <div class="schilo-settings-card">
        <h2>Champs de destination disponibles par type de section</h2>

        <p class="description">
            Pour chaque type de section, défini ici la liste des "champs de destination" proposés dans
            l’assistant de migration (page "Migration WPBakery" → "Configurer"). La ligne <code>__default__</code>
            s’applique à tous les types de section qui n’ont pas leur propre ligne ci-dessous.
            <br>
            Valeurs de champ reconnues par l’assistant : <code>content</code> (contenu de l’éditeur),
            <code>section_title</code> (titre de la section), et pour le type <code>liens-articles</code> :
            <code>intro</code>, <code>texte_libre</code>, <code>links_auto</code> (extraction automatique des liens
            "Vous pouvez consulter l’annexe...").
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('schilo_builder_save_migration_mapping', 'schilo_builder_migration_mapping_nonce'); ?>

            <table class="widefat striped schilo-migration-mapping-config-table">
                <thead>
                    <tr>
                        <th style="width:220px;">Type de section</th>
                        <th>Champs de destination proposés</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>
                <tbody id="schilo-migration-mapping-rows">
                    <?php $rowIndex = 0; ?>
                    <?php foreach ($mappings as $typeKey => $fields) : ?>
                        <tr data-row-index="<?php echo esc_attr($rowIndex); ?>">
                            <td>
                                <input type="text"
                                       name="schilo_migration_field_mapping[<?php echo esc_attr($rowIndex); ?>][type]"
                                       value="<?php echo esc_attr($typeKey); ?>"
                                       class="regular-text schilo-migration-mapping-type-input"
                                       placeholder="ex: liens-articles ou __default__">
                                <?php if ($typeKey !== '__default__' && isset($sectionTypes[$typeKey]['label'])) : ?>
                                    <p class="description"><?php echo esc_html($sectionTypes[$typeKey]['label']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="schilo-migration-mapping-fields">
                                    <?php foreach ($fields as $fieldIndex => $field) : ?>
                                        <div class="schilo-migration-mapping-field-item">
                                            <input type="text"
                                                   name="schilo_migration_field_mapping[<?php echo esc_attr($rowIndex); ?>][fields][<?php echo esc_attr($fieldIndex); ?>][value]"
                                                   value="<?php echo esc_attr($field['value']); ?>"
                                                   class="regular-text"
                                                   placeholder="clé (ex: content)">
                                            <input type="text"
                                                   name="schilo_migration_field_mapping[<?php echo esc_attr($rowIndex); ?>][fields][<?php echo esc_attr($fieldIndex); ?>][label]"
                                                   value="<?php echo esc_attr($field['label']); ?>"
                                                   class="regular-text"
                                                   placeholder="libellé affiché">
                                            <button type="button" class="button schilo-remove-mapping-field">Retirer</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button schilo-add-mapping-field">+ Ajouter un champ</button>
                            </td>
                            <td>
                                <button type="button" class="button schilo-remove-mapping-row">Supprimer</button>
                            </td>
                        </tr>
                        <?php $rowIndex++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="schilo-add-mapping-row">+ Ajouter un type de section</button>
            </p>

            <?php submit_button('Enregistrer le mapping'); ?>

            <p>
                <button type="submit"
                        name="schilo_reset_migration_mapping"
                        value="1"
                        class="button"
                        onclick="return confirm('Réinitialiser le mapping de migration par défaut ?');">
                    Réinitialiser le mapping par défaut
                </button>
            </p>
        </form>
    </div>

    <script type="text/template" id="schilo-migration-mapping-row-template">
        <tr data-row-index="__ROW_INDEX__">
            <td>
                <input type="text"
                       name="schilo_migration_field_mapping[__ROW_INDEX__][type]"
                       value=""
                       class="regular-text schilo-migration-mapping-type-input"
                       placeholder="ex: liens-articles ou __default__">
            </td>
            <td>
                <div class="schilo-migration-mapping-fields"></div>
                <button type="button" class="button schilo-add-mapping-field">+ Ajouter un champ</button>
            </td>
            <td>
                <button type="button" class="button schilo-remove-mapping-row">Supprimer</button>
            </td>
        </tr>
    </script>

    <script type="text/template" id="schilo-migration-mapping-field-template">
        <div class="schilo-migration-mapping-field-item">
            <input type="text"
                   name="schilo_migration_field_mapping[__ROW_INDEX__][fields][__FIELD_INDEX__][value]"
                   value=""
                   class="regular-text"
                   placeholder="clé (ex: content)">
            <input type="text"
                   name="schilo_migration_field_mapping[__ROW_INDEX__][fields][__FIELD_INDEX__][label]"
                   value=""
                   class="regular-text"
                   placeholder="libellé affiché">
            <button type="button" class="button schilo-remove-mapping-field">Retirer</button>
        </div>
    </script>
</div>
