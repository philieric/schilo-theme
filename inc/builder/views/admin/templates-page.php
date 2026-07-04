<?php /** @var array $templates */ /** @var array $sectionTypes */ /** @var bool $saved */ ?>
<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Types & templates</h1>
    <p class="schilo-admin-back"><a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">← Retour au tableau de bord</a></p>

    <?php if (!empty($saved)) : ?>
        <div class="notice notice-success is-dismissible"><p>Templates enregistrés.</p></div>
    <?php endif; ?>

    <div class="schilo-settings-card">
        <h2>Templates par type d’article</h2>
        <p class="description">Chaque template correspond à un type/préfixe : <code>PER</code>, <code>CTD</code>, <code>ANN</code>. Il définit les sections de base utilisées lors de la création ou de la migration.</p>

        <p>
            <button type="button" class="button" id="schilo-collapse-all-templates">Tout replier</button>
            <button type="button" class="button" id="schilo-expand-all-templates">Tout déplier</button>
            <button type="button" class="button" id="schilo-sections-view-toggle" data-mode="grid">Mode liste</button>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('schilo_builder_save_templates', 'schilo_builder_templates_nonce'); ?>
            <table class="widefat striped schilo-template-table schilo-sections-grid-mode" id="schilo-templates-table">
                <thead><tr><th>Actif</th><th>Type</th><th>Libellé</th><th>Description</th><th>Action</th></tr></thead>
                <tbody id="schilo-template-rows">
                    <?php $index = 0; foreach ($templates as $templateKey => $templateConfig) : ?>
                        <tr class="schilo-template-row-main">
                            <td><input type="checkbox" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[<?php echo esc_attr($index); ?>][active]" value="1" <?php checked(!empty($templateConfig['active'])); ?>></td>
                            <td><input type="text" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($templateKey); ?>" class="regular-text schilo-template-key-input"></td>
                            <td><input type="text" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($templateConfig['label']); ?>" class="regular-text"></td>
                            <td><input type="text" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[<?php echo esc_attr($index); ?>][description]" value="<?php echo esc_attr($templateConfig['description']); ?>" class="large-text"></td>
                            <td>
                                <button type="button" class="button schilo-toggle-template-sections">Déplier</button>
                                <button type="button" class="button schilo-remove-template-row">Supprimer</button>
                            </td>
                        </tr>
                        <tr class="schilo-template-row-sections" style="display:none;">
                            <td colspan="5">
                                <strong>Sections utilisées</strong>
                                <div class="schilo-section-checkbox-list">
                                    <?php
                                    $orderedSectionKeys = array_keys($sectionTypes);
                                    usort($orderedSectionKeys, function ($a, $b) use ($templateConfig) {
                                        $posA = array_search($a, (array) $templateConfig['sections'], true);
                                        $posB = array_search($b, (array) $templateConfig['sections'], true);
                                        $posA = $posA === false ? 9999 : $posA;
                                        $posB = $posB === false ? 9999 : $posB;
                                        if ($posA === $posB) {
                                            return 0;
                                        }
                                        return $posA < $posB ? -1 : 1;
                                    });
                                    ?>
                                    <?php foreach ($orderedSectionKeys as $sectionKey) : ?>
                                        <?php $sectionConfig = $sectionTypes[$sectionKey]; ?>
                                        <?php
                                        $sectionPosition = array_search($sectionKey, (array) $templateConfig['sections'], true);
                                        $orderValue = $sectionPosition !== false ? (($sectionPosition + 1) * 10) : '';
                                        ?>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[<?php echo esc_attr($index); ?>][sections][]" value="<?php echo esc_attr($sectionKey); ?>" <?php checked(in_array($sectionKey, (array) $templateConfig['sections'], true)); ?>>
                                            <input type="number" min="1" step="1" class="small-text schilo-section-order" placeholder="Ordre" title="Ordre d'affichage" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[<?php echo esc_attr($index); ?>][sections_order][<?php echo esc_attr($sectionKey); ?>]" value="<?php echo esc_attr($orderValue); ?>">
                                            <?php echo esc_html($sectionConfig['label']); ?> <small class="schilo-section-view-info">Front: <?php echo esc_html(isset($sectionConfig['view']) ? $sectionConfig['view'] : ''); ?> / Admin: <?php echo esc_html(isset($sectionConfig['admin_view']) ? $sectionConfig['admin_view'] : 'default.php'); ?></small>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php $index++; endforeach; ?>
                </tbody>
            </table>

            <p><button type="button" class="button" id="schilo-add-template-row">+ Ajouter un template</button></p>
            <?php submit_button('Enregistrer les templates'); ?>
            <p><button type="submit" name="schilo_reset_templates" value="1" class="button" onclick="return confirm('Réinitialiser les templates par défaut ?');">Réinitialiser les templates par défaut</button></p>
        </form>
    </div>

    <script type="text/template" id="schilo-template-row-template">
        <tr class="schilo-template-row-main">
            <td><input type="checkbox" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[__INDEX__][active]" value="1" checked></td>
            <td><input type="text" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[__INDEX__][key]" value="" class="regular-text schilo-template-key-input"></td>
            <td><input type="text" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[__INDEX__][label]" value="" class="regular-text"></td>
            <td><input type="text" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[__INDEX__][description]" value="" class="large-text"></td>
            <td>
                <button type="button" class="button schilo-toggle-template-sections">Déplier</button>
                <button type="button" class="button schilo-remove-template-row">Supprimer</button>
            </td>
        </tr>
        <tr class="schilo-template-row-sections" style="display:none;">
            <td colspan="5">
                <strong>Sections utilisées</strong>
                <div class="schilo-section-checkbox-list"><?php foreach ($sectionTypes as $sectionKey => $sectionConfig) : ?><label><input type="checkbox" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[__INDEX__][sections][]" value="<?php echo esc_attr($sectionKey); ?>"> <input type="number" min="1" step="1" class="small-text schilo-section-order" placeholder="Ordre" title="Ordre d'affichage" name="<?php echo esc_attr(\Schilo\Builder\Service\TemplateService::OPTION_TEMPLATES); ?>[__INDEX__][sections_order][<?php echo esc_attr($sectionKey); ?>]" value=""> <?php echo esc_html($sectionConfig['label']); ?> <small class="schilo-section-view-info">Front: <?php echo esc_html(isset($sectionConfig['view']) ? $sectionConfig['view'] : ''); ?> / Admin: <?php echo esc_html(isset($sectionConfig['admin_view']) ? $sectionConfig['admin_view'] : 'default.php'); ?></small></label><?php endforeach; ?></div>
            </td>
        </tr>
    </script>
</div>
