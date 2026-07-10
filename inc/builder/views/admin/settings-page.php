<?php
/** @var array $mappings */
/** @var WP_Term[] $categories */
/** @var bool $saved */
/** @var WP_Term[] $homeRootCategories */
/** @var int[] $homeExcludedCategoryIds */
?>

<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Préfixes & catégories</h1>

        <p class="schilo-admin-back">
            <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">
                ← Retour au tableau de bord
            </a>
        </p>


    <?php if (!empty($saved)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Correspondances préfixes & catégories enregistrées.</p>
        </div>
    <?php endif; ?>

    <p>
        Cette page permet de relier automatiquement un préfixe d’article à une catégorie WordPress.
    </p>

    <div class="schilo-settings-card">
        <h2>Catégories automatiques par préfixe</h2>

        <p class="description">
            Exemple : <code>CTD</code> → <strong>Les contradictions</strong>,
            <code>PER</code> → catégorie des présentations.
        </p>

        <form method="post" action="">
            <?php wp_nonce_field(\Schilo\Builder\Admin\SettingsPage::NONCE_ACTION, \Schilo\Builder\Admin\SettingsPage::NONCE_NAME); ?>

            <table class="widefat striped schilo-prefix-table">
                <thead>
                    <tr>
                        <th style="width:160px;">Préfixe</th>
                        <th>Catégorie principale</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>
                <tbody id="schilo-prefix-category-rows">
                    <?php
                    $index = 0;
                    foreach ($mappings as $prefix => $categoryId) :
                    ?>
                        <tr>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES); ?>[<?php echo esc_attr($index); ?>][prefix]"
                                       value="<?php echo esc_attr($prefix); ?>"
                                       maxlength="3"
                                       class="regular-text schilo-prefix-input">
                            </td>
                            <td>
                                <select name="<?php echo esc_attr(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES); ?>[<?php echo esc_attr($index); ?>][category_id]">
                                    <option value="">— Choisir une catégorie —</option>
                                    <?php foreach ($categories as $category) : ?>
                                        <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected((int) $categoryId, (int) $category->term_id); ?>>
                                            <?php echo esc_html($category->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button schilo-remove-prefix-row">Supprimer</button>
                            </td>
                        </tr>
                    <?php
                        $index++;
                    endforeach;
                    ?>

                    <?php if (empty($mappings)) : ?>
                        <tr>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES); ?>[0][prefix]"
                                       value="CTD"
                                       maxlength="3"
                                       class="regular-text schilo-prefix-input">
                            </td>
                            <td>
                                <select name="<?php echo esc_attr(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES); ?>[0][category_id]">
                                    <option value="">— Choisir une catégorie —</option>
                                    <?php foreach ($categories as $category) : ?>
                                        <option value="<?php echo esc_attr($category->term_id); ?>">
                                            <?php echo esc_html($category->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button schilo-remove-prefix-row">Supprimer</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="schilo-add-prefix-row">+ Ajouter une correspondance</button>
            </p>

            <h2>Visibilité sur la page d'accueil</h2>
            <p class="description">
                Décochez une catégorie pour la retirer des grilles de la page d'accueil
                (« Explorer par thème » et « Bibliothèque Schilo »). Une catégorie décochée
                reste en ligne et accessible via son URL — elle disparaît seulement de l'accueil.
            </p>

            <table class="widefat striped" style="max-width:640px;">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th style="width:140px;">Afficher sur l'accueil</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($homeRootCategories as $category) : ?>
                        <tr>
                            <td><?php echo esc_html($category->name); ?></td>
                            <td>
                                <input type="checkbox"
                                       name="schilo_home_visible_categories[]"
                                       value="<?php echo esc_attr($category->term_id); ?>"
                                       <?php checked(!in_array((int) $category->term_id, $homeExcludedCategoryIds, true)); ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button('Enregistrer les réglages'); ?>
        </form>
    </div>

    <script type="text/template" id="schilo-prefix-row-template">
        <tr>
            <td>
                <input type="text"
                       name="<?php echo esc_attr(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES); ?>[__INDEX__][prefix]"
                       value=""
                       maxlength="3"
                       class="regular-text schilo-prefix-input">
            </td>
            <td>
                <select name="<?php echo esc_attr(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES); ?>[__INDEX__][category_id]">
                    <option value="">— Choisir une catégorie —</option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>">
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <button type="button" class="button schilo-remove-prefix-row">Supprimer</button>
            </td>
        </tr>
    </script>
</div>
