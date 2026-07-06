<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\SectionTypeService;
use Schilo\Builder\Service\TemplateService;
use Schilo\Builder\Service\Migration\MigrationContentFetcher;
use Schilo\Builder\Service\Migration\ExtractorRegistry;
use Schilo\Builder\Service\Migration\MigrationDestinationFields;
use Schilo\Builder\Service\Migration\MigrationModelService;
use Schilo\Builder\Service\Migration\MigrationApplier;
use Schilo\Builder\Service\Migration\MigrationSourceContent;

class SettingsPage
{
    const OPTION_PREFIX_CATEGORIES = 'schilo_builder_prefix_categories';
    const NONCE_ACTION = 'schilo_builder_save_settings';
    const NONCE_NAME = 'schilo_builder_settings_nonce';

    public function register()
    {
        add_action('admin_menu', array($this, 'addMenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('wp_ajax_schilo_load_tool', array($this, 'ajaxLoadTool'));
        add_action('wp_ajax_schilo_test_ia',   array($this, 'ajaxTestIa'));

        // Indexation
        $indexationPage = new IndexationPage();
        $indexationPage->register();

        // Classement (parcours, themes, series)
        $classementPage = new ClassementPage();
        $classementPage->register();
    }

    public function addMenu()
    {
        add_menu_page(
            'Schilo Builder',
            'Schilo Builder',
            'manage_options',
            'schilo-builder',
            array($this, 'renderDashboardPage'),
            'dashicons-layout',
            58
        );

        add_submenu_page(
            'schilo-builder',
            'Tableau de bord',
            'Tableau de bord',
            'manage_options',
            'schilo-builder',
            array($this, 'renderDashboardPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Préfixes & catégories',
            'Préfixes & catégories',
            'manage_options',
            'schilo-builder-prefix-categories',
            array($this, 'renderPrefixCategoriesPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Sections',
            'Sections',
            'manage_options',
            'schilo-builder-sections',
            array($this, 'renderSectionsPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Types & templates',
            'Types & templates',
            'manage_options',
            'schilo-builder-types',
            array($this, 'renderTemplatesPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Migration (test)',
            'Migration (test)',
            'manage_options',
            'schilo-builder-migration-test',
            array($this, 'renderMigrationTestPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Outils',
            'Outils',
            'manage_options',
            'schilo-builder-outils',
            array($this, 'renderOutilsPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Sitemap',
            'Sitemap',
            'manage_options',
            'schilo-builder-sitemap',
            array($this, 'renderSitemapPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Intelligence Artificielle',
            'IA',
            'manage_options',
            'schilo-builder-ia',
            array($this, 'renderIaPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Grille catégories (MCG)',
            'Grille catégories',
            'manage_options',
            'schilo-builder-mcg',
            array($this, 'renderMcgPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Indexation',
            'Indexation',
            'manage_options',
            'schilo-builder-indexation',
            array($this, 'renderIndexationPage')
        );

        add_submenu_page(
            'schilo-builder',
            'Parcours & Thèmes',
            'Parcours & Thèmes',
            'manage_options',
            'schilo-builder-classement',
            array($this, 'renderClassementPage')
        );
    }

    public function enqueueAssets($hook)
    {
        if (strpos((string) $hook, 'schilo-builder') === false) {
            return;
        }

        wp_enqueue_style(
            'schilo-builder-admin',
            SCHILO_BUILDER_URL . 'assets/admin/builder-admin.css',
            array(),
            SCHILO_BUILDER_VERSION
        );

        wp_enqueue_script(
            'schilo-builder-settings',
            SCHILO_BUILDER_URL . 'assets/admin/builder-settings.js',
            array('jquery'),
            SCHILO_BUILDER_VERSION,
            true
        );

        wp_localize_script('schilo-builder-settings', 'schiloBuilder', array(
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'loadToolNonce'     => wp_create_nonce('schilo_load_tool'),
            'iaNonce'           => wp_create_nonce('schilo_test_ia'),
            'indexationNonce'   => wp_create_nonce('schilo_indexation'),
            'classementNonce'   => wp_create_nonce('schilo_classement'),
        ));

        // Assets specifiques a la page Indexation
        if (strpos((string) $hook, 'schilo-builder-indexation') !== false) {
            wp_enqueue_style(
                'schilo-indexation-admin',
                SCHILO_BUILDER_URL . 'assets/admin/indexation-admin.css',
                array(),
                SCHILO_BUILDER_VERSION
            );
            wp_enqueue_script(
                'schilo-indexation-admin',
                SCHILO_BUILDER_URL . 'assets/admin/indexation-admin.js',
                array('jquery'),
                SCHILO_BUILDER_VERSION,
                true
            );
        }

        // Assets specifiques a la page Classement
        if (strpos((string) $hook, 'schilo-builder-classement') !== false) {
            wp_enqueue_style(
                'schilo-classement-admin',
                SCHILO_BUILDER_URL . 'assets/admin/classement-admin.css',
                array(),
                SCHILO_BUILDER_VERSION
            );
            wp_enqueue_script(
                'schilo-classement-admin',
                SCHILO_BUILDER_URL . 'assets/admin/classement-admin.js',
                array('jquery'),
                SCHILO_BUILDER_VERSION,
                true
            );
        }
    }

    public function renderDashboardPage()
    {
        $prefixCount = count((array) get_option(self::OPTION_PREFIX_CATEGORIES, array()));
        $sectionService = new SectionTypeService();
        $sectionCount = count($sectionService->getAllTypes());
        $activeSectionCount = count($sectionService->getActiveTypes());

        include SCHILO_BUILDER_PATH . 'views/admin/dashboard-page.php';
    }

    public function renderPrefixCategoriesPage()
    {
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[self::NONCE_NAME])) {
            $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));

            if (wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                $rawMappings = isset($_POST[self::OPTION_PREFIX_CATEGORIES]) && is_array($_POST[self::OPTION_PREFIX_CATEGORIES])
                    ? wp_unslash($_POST[self::OPTION_PREFIX_CATEGORIES])
                    : array();

                update_option(self::OPTION_PREFIX_CATEGORIES, $this->sanitizePrefixCategories($rawMappings), false);
                $saved = true;
            }
        }

        $mappings = get_option(self::OPTION_PREFIX_CATEGORIES, array());
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        include SCHILO_BUILDER_PATH . 'views/admin/settings-page.php';
    }

    public function renderSectionsPage()
    {
        $service = new SectionTypeService();
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schilo_builder_sections_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['schilo_builder_sections_nonce']));

            if (wp_verify_nonce($nonce, 'schilo_builder_save_sections')) {
                if (isset($_POST['schilo_reset_sections'])) {
                    $service->resetDefaults();
                } else {
                    $rawTypes = isset($_POST[SectionTypeService::OPTION_SECTION_TYPES]) && is_array($_POST[SectionTypeService::OPTION_SECTION_TYPES])
                        ? wp_unslash($_POST[SectionTypeService::OPTION_SECTION_TYPES])
                        : array();

                    $service->saveTypes($rawTypes);
                }

                $saved = true;
            }
        }

        $sectionTypes = $service->getAllTypes();

        include SCHILO_BUILDER_PATH . 'views/admin/sections-page.php';
    }

    public function renderTemplatesPage()
    {
        $templateService = new TemplateService();
        $sectionService = new SectionTypeService();
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schilo_builder_templates_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['schilo_builder_templates_nonce']));

            if (wp_verify_nonce($nonce, 'schilo_builder_save_templates')) {
                if (isset($_POST['schilo_reset_templates'])) {
                    $templateService->resetDefaults();
                } else {
                    $rawTemplates = isset($_POST[TemplateService::OPTION_TEMPLATES]) && is_array($_POST[TemplateService::OPTION_TEMPLATES])
                        ? wp_unslash($_POST[TemplateService::OPTION_TEMPLATES])
                        : array();

                    $templateService->saveTemplates($rawTemplates);
                }

                $saved = true;
            }
        }

        $templates = $templateService->getAllTemplates();
        $sectionTypes = $sectionService->getAllTypes();

        include SCHILO_BUILDER_PATH . 'views/admin/templates-page.php';
    }

    public function renderMigrationTestPage()
    {
        $testPostId = isset($_GET['schilo_test_post']) ? (int) $_GET['schilo_test_post'] : 0;
        $selectedPrefix = isset($_GET['schilo_test_prefix']) ? strtoupper(sanitize_key(wp_unslash($_GET['schilo_test_prefix']))) : '';
        $selectedModelId = isset($_GET['schilo_test_model']) ? sanitize_key(wp_unslash($_GET['schilo_test_model'])) : '';
        $mappingSaved = false;
        $modelSaved = false;
        $modelDeleted = false;
        $migrationApplied = false;
        $migrationError = '';
        $batchResult = null;

        $modelService = new MigrationModelService();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schilo_migration_test_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['schilo_migration_test_nonce']));

            if (wp_verify_nonce($nonce, 'schilo_builder_migration_test')) {
                $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
                $testPostId = $postId;
                $selectedPrefix = isset($_POST['schilo_test_prefix']) ? strtoupper(sanitize_key(wp_unslash($_POST['schilo_test_prefix']))) : $selectedPrefix;

                if (isset($_POST['schilo_batch_migrate'])) {
                    $batchPrefix = isset($_POST['schilo_batch_prefix']) ? strtoupper(sanitize_key(wp_unslash($_POST['schilo_batch_prefix']))) : '';
                    $batchModelId = isset($_POST['schilo_batch_model_id']) ? sanitize_key(wp_unslash($_POST['schilo_batch_model_id'])) : '';
                    $batchSkipMigrated = !isset($_POST['schilo_batch_redo']);

                    if ($batchPrefix !== '' && $batchModelId !== '') {
                        $batchModel = $modelService->getModel($batchModelId);

                        if ($batchModel) {
                            global $wpdb;
                            $batchIds = $wpdb->get_col($wpdb->prepare(
                                "SELECT ID FROM {$wpdb->posts}
                                 WHERE post_type = 'post'
                                 AND post_status IN ('publish', 'draft')
                                 AND post_title LIKE %s
                                 ORDER BY post_title ASC",
                                $wpdb->esc_like($batchPrefix) . '%'
                            ));

                            $fetcher  = new MigrationContentFetcher();
                            $registry = new ExtractorRegistry();
                            $applier  = new MigrationApplier();

                            $batchResult = array('ok' => array(), 'skip' => array(), 'error' => array());

                            foreach ($batchIds as $batchPostId) {
                                $batchPostId = (int) $batchPostId;

                                if ($batchSkipMigrated) {
                                    $status = get_post_meta($batchPostId, \Schilo\Builder\Service\WPBakeryMigrationService::STATUS_META_KEY, true);
                                    if ($status === 'migrated') {
                                        $batchResult['skip'][] = $batchPostId;
                                        continue;
                                    }
                                }

                                try {
                                    $batchRawPost = get_post($batchPostId);
                                    $batchRaw     = $batchRawPost ? $batchRawPost->post_content : '';

                                    try {
                                        $batchRendered = $fetcher->getRenderedContent($batchPostId);
                                    } catch (\Throwable $renderErr) {
                                        $batchRendered = '';
                                    }

                                    $batchSource   = new MigrationSourceContent($batchPostId, $batchRendered, $batchRaw);
                                    $batchElements = $registry->extractAll($batchSource);

                                    $batchAssignments      = $modelService->expandModelForElements($batchModel, $batchElements);
                                    $batchContentOverrides = $modelService->expandContentOverridesForElements($batchModel, $batchElements);

                                    foreach ($batchElements as &$batchEl) {
                                        if (isset($batchContentOverrides[$batchEl['id']])) {
                                            $batchEl['content'] = $batchContentOverrides[$batchEl['id']];
                                        }
                                    }
                                    unset($batchEl);

                                    // Sauvegarde le contenu original avant toute modification
                                    if (!get_post_meta($batchPostId, \Schilo\Builder\Service\WPBakeryMigrationService::BACKUP_META_KEY, true)) {
                                        update_post_meta($batchPostId, \Schilo\Builder\Service\WPBakeryMigrationService::BACKUP_META_KEY, $batchRaw);
                                    }

                                    $applier->apply($batchPostId, $batchPrefix, $batchElements, $batchAssignments, true);

                                    update_post_meta($batchPostId, \Schilo\Builder\Service\WPBakeryMigrationService::STATUS_META_KEY, 'migrated');
                                    update_post_meta($batchPostId, \Schilo\Builder\Service\WPBakeryMigrationService::DATE_META_KEY, current_time('mysql'));

                                    $batchResult['ok'][] = $batchPostId;
                                } catch (\Throwable $e) {
                                    $batchResult['error'][] = array('id' => $batchPostId, 'msg' => $e->getMessage());
                                }
                            }

                            $selectedPrefix = $batchPrefix;
                        }
                    }
                } elseif (isset($_POST['schilo_delete_model'])) {
                    $modelService->deleteModel(sanitize_key(wp_unslash($_POST['schilo_delete_model'])));
                    $selectedModelId = '';
                    $modelDeleted = true;
                } elseif ($postId > 0) {
                    $rawGroupSections = isset($_POST['schilo_group_section']) && is_array($_POST['schilo_group_section'])
                        ? wp_unslash($_POST['schilo_group_section'])
                        : array();

                    $groupSections = array();
                    foreach ($rawGroupSections as $groupKey => $sectionType) {
                        $groupSections[sanitize_key((string) $groupKey)] = sanitize_key((string) $sectionType);
                    }

                    $rawAssignments = isset($_POST['schilo_element_assignment']) && is_array($_POST['schilo_element_assignment'])
                        ? wp_unslash($_POST['schilo_element_assignment'])
                        : array();

                    $clean = array();

                    foreach ($rawAssignments as $elementId => $assignment) {
                        $elementId = sanitize_key((string) $elementId);
                        $groupKey = isset($assignment['group']) ? sanitize_key((string) $assignment['group']) : '';
                        $field = isset($assignment['field']) ? sanitize_key((string) $assignment['field']) : '';
                        $sectionType = isset($groupSections[$groupKey]) ? $groupSections[$groupKey] : '';

                        if ($elementId === '' || $sectionType === '' || $sectionType === 'ignore' || $field === '') {
                            continue;
                        }

                        $clean[$elementId] = array(
                            'section_type' => $sectionType,
                            'field' => $field,
                        );
                    }

                    $rawContentOverrides = isset($_POST['schilo_element_content']) && is_array($_POST['schilo_element_content'])
                        ? wp_unslash($_POST['schilo_element_content'])
                        : array();

                    $contentOverrides = array();
                    foreach ($rawContentOverrides as $elementId => $overrideValue) {
                        $elementId = sanitize_key((string) $elementId);
                        $overrideValue = sanitize_text_field((string) $overrideValue);

                        if ($elementId === '' || $overrideValue === '') {
                            continue;
                        }

                        $contentOverrides[$elementId] = $overrideValue;
                    }

                    update_post_meta($postId, '_schilo_migration_element_assignment', $clean);
                    update_post_meta($postId, '_schilo_migration_element_content_overrides', $contentOverrides);
                    $mappingSaved = true;

                    if (!empty($_POST['schilo_save_as_model'])) {
                        $modelName = isset($_POST['schilo_model_name']) ? sanitize_text_field(wp_unslash($_POST['schilo_model_name'])) : '';
                        $modelIdToUpdate = isset($_POST['schilo_model_id']) ? sanitize_key(wp_unslash($_POST['schilo_model_id'])) : '';

                        $selectedModelId = $modelService->saveModel($modelName, $selectedPrefix, $clean, $modelIdToUpdate !== '' ? $modelIdToUpdate : null, $contentOverrides);
                        $modelSaved = true;
                    }

                    if (!empty($_POST['schilo_apply_migration'])) {
                        $fetcher = new MigrationContentFetcher();
                        try {
                            $renderedHtmlForApply = $fetcher->getRenderedContent($postId);
                        } catch (\Throwable $e) {
                            $renderedHtmlForApply = '';
                        }
                        $applyPost = get_post($postId);
                        $rawContentForApply = $applyPost ? $applyPost->post_content : '';

                        $registry = new ExtractorRegistry();
                        $sourceForApply = new MigrationSourceContent($postId, $renderedHtmlForApply, $rawContentForApply);
                        $elementsForApply = $registry->extractAll($sourceForApply);

                        foreach ($elementsForApply as &$elementForApply) {
                            if (isset($contentOverrides[$elementForApply['id']])) {
                                $elementForApply['content'] = $contentOverrides[$elementForApply['id']];
                            }
                        }
                        unset($elementForApply);

                        $applier = new MigrationApplier();
                        $applier->apply($postId, $selectedPrefix, $elementsForApply, $clean, true);

                        $migrationApplied = true;
                    }
                }
            }
        }

        $prefixDetector = new \Schilo\Builder\Service\PrefixDetector();

        // Toutes les publications candidates (titre commençant par un préfixe à 3 lettres).
        $allPosts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $availablePrefixes = array();
        $postsByPrefix = array();

        foreach ($allPosts as $candidatePost) {
            $prefix = $prefixDetector->detectFromTitle(get_the_title($candidatePost));

            $availablePrefixes[$prefix] = true;
            $postsByPrefix[$prefix][] = $candidatePost;
        }

        $availablePrefixes = array_keys($availablePrefixes);
        sort($availablePrefixes);

        if ($selectedPrefix === '' && !empty($availablePrefixes)) {
            $selectedPrefix = $availablePrefixes[0];
        }

        $candidates = isset($postsByPrefix[$selectedPrefix]) ? $postsByPrefix[$selectedPrefix] : array();

        $testPost = null;
        $renderedHtml = '';
        $extractedElements = array();
        $templateForPrefix = null;
        $sectionTypes = array();
        $destinationFieldsByType = array();
        $savedAssignment = array();
        $contentOverrides = array();

        if ($testPostId > 0) {
            $testPost = get_post($testPostId);

            if ($testPost) {
                $fetcher = new MigrationContentFetcher();
                try {
                    $renderedHtml = $fetcher->getRenderedContent($testPostId);
                } catch (\Throwable $e) {
                    $renderedHtml = '';
                }

                $registry = new ExtractorRegistry();
                $source = new MigrationSourceContent($testPostId, $renderedHtml, $testPost->post_content);
                $extractedElements = $registry->extractAll($source);

                $savedAssignment = get_post_meta($testPostId, '_schilo_migration_element_assignment', true);

                if (!is_array($savedAssignment)) {
                    $savedAssignment = array();
                }

                $contentOverrides = get_post_meta($testPostId, '_schilo_migration_element_content_overrides', true);

                if (!is_array($contentOverrides)) {
                    $contentOverrides = array();
                }
            }
        }

        $modelsForPrefix = $selectedPrefix !== '' ? $modelService->getModelsForPrefix($selectedPrefix) : array();

        // Si un modèle de migration est explicitement sélectionné, ses règles
        // (par motif, ex: "tous les consultation_link*") sont étendues aux
        // éléments réellement détectés sur cet article — qu'il y en ait 0, 2,
        // 4 ou 10 — et pré-remplissent le formulaire (prioritaire sur un
        // éventuel mapping déjà enregistré pour cet article précis).
        if ($selectedModelId !== '' && isset($modelsForPrefix[$selectedModelId])) {
            $loadedModel = $modelsForPrefix[$selectedModelId];
            $savedAssignment = $modelService->expandModelForElements($loadedModel, $extractedElements);

            $modelContentOverrides = $modelService->expandContentOverridesForElements($loadedModel, $extractedElements);

            foreach ($modelContentOverrides as $overrideElementId => $overrideValue) {
                $contentOverrides[$overrideElementId] = $overrideValue;
            }
        }

        // Applique les textes personnalisés (édités manuellement ou hérités
        // du modèle) sur les éléments affichés, pour que l'aperçu et le champ
        // éditable reflètent la valeur effective.
        foreach ($extractedElements as &$extractedElement) {
            if (isset($contentOverrides[$extractedElement['id']])) {
                $extractedElement['content'] = $contentOverrides[$extractedElement['id']];
            }
        }
        unset($extractedElement);

        if ($selectedPrefix !== '') {
            $templateService = new TemplateService();
            $templateForPrefix = $templateService->getTemplateForPrefix($selectedPrefix);

            $sectionTypeService = new SectionTypeService();
            $sectionTypes = $sectionTypeService->getAllTypes();

            $destinationFieldsService = new MigrationDestinationFields();

            foreach ((array) $templateForPrefix['sections'] as $sectionKey) {
                $destinationFieldsByType[$sectionKey] = $destinationFieldsService->getFieldsForType($sectionKey);
            }
        }

        include SCHILO_BUILDER_PATH . 'views/admin/migration-test-page.php';
    }

    public function renderIndexationPage(): void
    {
        $indexationPage = new IndexationPage();
        $indexationPage->renderPage();
    }

    public function renderClassementPage(): void
    {
        $classementPage = new ClassementPage();
        $classementPage->renderPage();
    }

    public function renderComingSoonPage()
    {
        include SCHILO_BUILDER_PATH . 'views/admin/coming-soon-page.php';
    }

    private function sanitizePrefixCategories($input)
    {
        $clean = array();

        if (!is_array($input)) {
            return $clean;
        }

        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }

            $prefix = isset($row['prefix']) ? strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $row['prefix'])) : '';
            $prefix = substr($prefix, 0, 3);
            $categoryId = isset($row['category_id']) ? (int) $row['category_id'] : 0;

            if ($prefix === '' || strlen($prefix) !== 3 || $categoryId <= 0) {
                continue;
            }

            $clean[$prefix] = $categoryId;
        }

        return $clean;
    }

    public function renderMcgPage()
    {
        $categories = get_categories(['hide_empty' => false, 'orderby' => 'name']);
        ?>
        <div class="wrap schilo-builder-settings">
            <h1>Grille catégories — Modern Category Grid</h1>
            <p class="schilo-dashboard-intro">Shortcode <code>[mcg_grid]</code> — grille d'articles avec filtres, tri et pagination AJAX.</p>

            <div class="schilo-tool-card">
                <h2><span class="dashicons dashicons-shortcode" style="vertical-align:middle;margin-right:6px;"></span>Utilisation du shortcode</h2>
                <table class="widefat fixed" style="max-width:700px;">
                    <thead><tr><th>Attribut</th><th>Défaut</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>cat</code></td><td><code>0</code></td><td>ID de catégorie (0 = toutes)</td></tr>
                        <tr><td><code>per_page</code></td><td><code>9</code></td><td>Articles par page</td></tr>
                        <tr><td><code>orderby</code></td><td><code>date</code></td><td>Tri : <code>date</code>, <code>title</code>, <code>modified</code></td></tr>
                        <tr><td><code>show_filters</code></td><td><code>1</code></td><td>Afficher les filtres catégorie</td></tr>
                        <tr><td><code>show_sort</code></td><td><code>1</code></td><td>Afficher le sélecteur de tri</td></tr>
                        <tr><td><code>mode</code></td><td><code>loadmore</code></td><td>Pagination : <code>loadmore</code> ou <code>pagination</code></td></tr>
                    </tbody>
                </table>

                <h3 style="margin-top:20px;">Exemples</h3>
                <p><code>[mcg_grid]</code> — toutes catégories, 9 articles, tri date</p>
                <p><code>[mcg_grid cat="<?php echo esc_html($categories[0]->term_id ?? 0); ?>" per_page="6" orderby="title" mode="pagination"]</code></p>
            </div>

            <div class="schilo-tool-card">
                <h2><span class="dashicons dashicons-category" style="vertical-align:middle;margin-right:6px;"></span>Catégories disponibles</h2>
                <table class="widefat fixed striped" style="max-width:600px;">
                    <thead><tr><th>ID</th><th>Catégorie</th><th>Articles</th><th>Shortcode rapide</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $cat) : ?>
                        <tr>
                            <td><?php echo esc_html($cat->term_id); ?></td>
                            <td><?php echo esc_html($cat->name); ?></td>
                            <td><?php echo esc_html($cat->count); ?></td>
                            <td><code>[mcg_grid cat="<?php echo esc_html($cat->term_id); ?>"]</code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function renderSitemapPage()
    {
        if (class_exists('Sitemap_Par_Categorie_Admin')) {
            $admin = new \Sitemap_Par_Categorie_Admin();
            $admin->render_admin_page();
        }
    }

    public function ajaxLoadTool()
    {
        check_ajax_referer('schilo_load_tool', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('', '', 403);
        }

        $tool    = isset($_POST['tool']) ? sanitize_key($_POST['tool']) : '';
        $allowed = array('inherit_cat', 'delete_cats', 'delete_media', 'raccourcis', 'ia_config', 'doublons_prefixe');
        if (!in_array($tool, $allowed, true)) {
            wp_die('Outil inconnu.', '', 400);
        }

        $result              = null;
        $result_empty_cats   = null;
        $result_orphan_media = null;
        $result_raccourcis   = null;
        $selected_parent_id  = 0;
        $parent_categories   = array();
        $raccourcis_map      = $this->getDefaultRaccourcisMap();

        if ($tool === 'inherit_cat') {
            $all_parent_cats   = get_terms(array('taxonomy' => 'category', 'hide_empty' => false, 'parent' => 0));
            $parent_categories = array_filter(
                is_array($all_parent_cats) ? $all_parent_cats : array(),
                function ($cat) { return !empty(get_term_children($cat->term_id, 'category')); }
            );
        } elseif ($tool === 'raccourcis') {
            $saved = get_option('raccourcis_live_map');
            $raccourcis_map = is_array($saved) && !empty($saved) ? $saved : $this->getDefaultRaccourcisMap();
        }

        include SCHILO_BUILDER_PATH . 'views/admin/partials/tool-' . $tool . '.php';
        wp_die();
    }

    public function renderOutilsPage()
    {
        $result                  = null;
        $result_empty_cats       = null;
        $result_orphan_media     = null;
        $result_raccourcis       = null;
        $result_doublons_prefixe = null;
        $selected_parent_id      = 0;
        $active_tool             = '';

        $action = isset($_POST['schilo_tool_action']) ? sanitize_key($_POST['schilo_tool_action']) : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {

            if (
                $action === 'inherit_parent_category'
                && isset($_POST['schilo_inherit_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['schilo_inherit_nonce'])), 'schilo_inherit_parent_cat')
            ) {
                $active_tool        = 'inherit_cat';
                $selected_parent_id = (int) ($_POST['schilo_parent_cat_id'] ?? 0);
                $dry                = (int) ($_POST['schilo_inherit_dry'] ?? 1) === 1;
                $result             = $this->runInheritParentCategory($selected_parent_id, $dry);
            }

            if (
                $action === 'delete_empty_categories'
                && isset($_POST['schilo_delete_cats_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['schilo_delete_cats_nonce'])), 'schilo_delete_empty_cats')
            ) {
                $active_tool       = 'delete_cats';
                $dry               = (int) ($_POST['schilo_delete_cats_dry'] ?? 1) === 1;
                $result_empty_cats = $this->runDeleteEmptyCategories($dry);
            }

            if (
                $action === 'delete_orphan_media'
                && isset($_POST['schilo_delete_media_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['schilo_delete_media_nonce'])), 'schilo_delete_orphan_media')
            ) {
                $active_tool         = 'delete_media';
                $dry                 = (int) ($_POST['schilo_delete_media_dry'] ?? 1) === 1;
                $limit               = max(1, min(2000, (int) ($_POST['schilo_delete_media_limit'] ?? 200)));
                $result_orphan_media = $this->runDeleteOrphanMedia($dry, $limit);
            }

            if (
                $action === 'save_raccourcis'
                && isset($_POST['schilo_raccourcis_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['schilo_raccourcis_nonce'])), 'schilo_save_raccourcis')
            ) {
                $active_tool       = 'raccourcis';
                $result_raccourcis = $this->runSaveRaccourcis();
            }

            if (
                $action === 'fix_duplicate_prefixes'
                && isset($_POST['schilo_doublons_prefixe_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['schilo_doublons_prefixe_nonce'])), 'schilo_fix_duplicate_prefixes')
            ) {
                $active_tool             = 'doublons_prefixe';
                $dry                     = (int) ($_POST['schilo_doublons_prefixe_dry'] ?? 1) === 1;
                $result_doublons_prefixe = $this->runFixDuplicatePrefixes($dry);
            }
        }

        // Variables nécessaires au panel actif (rendu côté serveur après POST)
        $parent_categories = array();
        $raccourcis_map    = $this->getDefaultRaccourcisMap();

        if ($active_tool === 'inherit_cat') {
            $all_parent_cats   = get_terms(array('taxonomy' => 'category', 'hide_empty' => false, 'parent' => 0));
            $parent_categories = array_filter(
                is_array($all_parent_cats) ? $all_parent_cats : array(),
                function ($cat) { return !empty(get_term_children($cat->term_id, 'category')); }
            );
        } elseif ($active_tool === 'raccourcis') {
            $saved = get_option('raccourcis_live_map');
            $raccourcis_map = is_array($saved) && !empty($saved) ? $saved : $this->getDefaultRaccourcisMap();
        }

        include SCHILO_BUILDER_PATH . 'views/admin/outils-page.php';
    }

    private function getDefaultRaccourcisMap()
    {
        return array(
            array('token' => ';bb',  'snippet' => '[/bib]',      'place_caret' => 'none',    'in_tinymce' => false, 'label' => ''),
            array('token' => ';bv',  'snippet' => '[/bvc]',      'place_caret' => 'none',    'in_tinymce' => false, 'label' => ''),
            array('token' => ';bi',  'snippet' => '[/bib]',      'place_caret' => 'none',    'in_tinymce' => false, 'label' => ''),
            array('token' => ';bn',  'snippet' => '[/bnv]',      'place_caret' => 'none',    'in_tinymce' => false, 'label' => ''),
            array('token' => ';bib', 'snippet' => '[bib][/bib]', 'place_caret' => 'between', 'in_tinymce' => true,  'label' => 'Bible'),
            array('token' => ';bvc', 'snippet' => '[bvc][/bvc]', 'place_caret' => 'between', 'in_tinymce' => true,  'label' => 'Vidéo'),
            array('token' => ';brc', 'snippet' => '[brc][/brc]', 'place_caret' => 'between', 'in_tinymce' => true,  'label' => 'Bloc riche'),
            array('token' => ';bnv', 'snippet' => '[bnv][/bnv]', 'place_caret' => 'between', 'in_tinymce' => true,  'label' => 'Navigation'),
        );
    }

    private function runSaveRaccourcis()
    {
        $raw = isset($_POST['raccourcis_map']) && is_array($_POST['raccourcis_map'])
            ? wp_unslash($_POST['raccourcis_map'])
            : array();

        $map = array();
        foreach ($raw as $entry) {
            if (!is_array($entry)) continue;
            $token      = isset($entry['token'])      ? sanitize_text_field((string) $entry['token'])   : '';
            $snippet    = isset($entry['snippet'])    ? sanitize_text_field((string) $entry['snippet']) : '';
            $place_caret = isset($entry['place_caret']) ? sanitize_key((string) $entry['place_caret'])  : 'none';
            $in_tinymce = !empty($entry['in_tinymce']);
            $label      = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';

            if ($token === '' || $snippet === '') continue;
            if (!in_array($place_caret, array('none', 'between'), true)) $place_caret = 'none';

            $map[] = array(
                'token'       => $token,
                'snippet'     => $snippet,
                'place_caret' => $place_caret,
                'in_tinymce'  => $in_tinymce,
                'label'       => $label,
            );
        }

        update_option('raccourcis_live_map', $map, false);

        return array(
            'type'    => 'success',
            'message' => count($map) . ' raccourci(s) enregistré(s).',
        );
    }

    private function runInheritParentCategory($parent_id, $dry)
    {
        global $wpdb;

        // Récupère les couples (parent, enfant) à traiter
        $parents = $parent_id > 0
            ? [get_term($parent_id, 'category')]
            : get_terms(['taxonomy' => 'category', 'parent' => 0, 'hide_empty' => false]);

        if (is_wp_error($parents) || empty($parents)) {
            return ['type' => 'error', 'message' => 'Aucune catégorie parente trouvée.', 'details' => []];
        }

        $details      = [];
        $total_linked = 0;

        foreach ($parents as $parent) {
            if (!$parent || is_wp_error($parent)) {
                continue;
            }

            $children = get_terms([
                'taxonomy'   => 'category',
                'parent'     => $parent->term_id,
                'hide_empty' => false,
            ]);

            if (empty($children) || is_wp_error($children)) {
                continue;
            }

            // term_taxonomy_id de la catégorie parente
            $parent_tt_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='category'",
                $parent->term_id
            ));

            foreach ($children as $child) {
                // Articles appartenant à cet enfant mais PAS encore au parent
                $post_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT tr.object_id
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                    WHERE tt.term_id = %d
                      AND tt.taxonomy = 'category'
                      AND p.post_type = 'post'
                      AND p.post_status IN ('publish','draft')
                      AND tr.object_id NOT IN (
                          SELECT object_id FROM {$wpdb->term_relationships}
                          WHERE term_taxonomy_id = %d
                      )
                ", $child->term_id, $parent_tt_id));

                $all_child_posts = (int) $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT tr.object_id)
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                    WHERE tt.term_id = %d AND tt.taxonomy='category'
                      AND p.post_type='post' AND p.post_status IN ('publish','draft')
                ", $child->term_id));

                $updated = 0;

                if (!$dry && !empty($post_ids)) {
                    foreach ($post_ids as $pid) {
                        $wpdb->insert($wpdb->term_relationships, [
                            'object_id'        => (int) $pid,
                            'term_taxonomy_id' => $parent_tt_id,
                            'term_order'       => 0,
                        ]);
                        $updated++;
                    }
                    if ($updated > 0) {
                        wp_update_term_count_now([$parent->term_id], 'category');
                    }
                    $total_linked += $updated;
                } elseif (!$dry) {
                    // Rien à faire (déjà tous liés)
                } else {
                    $updated = count($post_ids); // dry : ce qui serait mis à jour
                    $total_linked += $updated;
                }

                $details[] = [
                    'parent_name' => $parent->name,
                    'child_name'  => $child->name,
                    'post_count'  => $all_child_posts,
                    'updated'     => $updated,
                ];
            }
        }

        $mode = $dry ? 'Simulation' : 'Exécution réelle';
        $msg  = $dry
            ? sprintf('%s : %d article(s) recevraient leur catégorie parente.', $mode, $total_linked)
            : sprintf('%s : %d article(s) mis à jour.', $mode, $total_linked);

        return [
            'type'    => 'success',
            'message' => $msg,
            'details' => $details,
        ];
    }

    private function runDeleteEmptyCategories($dry)
    {
        $all_cats = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'number'     => 0,
        ]);

        if (is_wp_error($all_cats) || empty($all_cats)) {
            return ['message' => 'Aucune catégorie trouvée.', 'cats' => []];
        }

        $to_delete = [];

        foreach ($all_cats as $cat) {
            // Préserver "Non classé"
            if ($cat->slug === 'uncategorized') {
                continue;
            }

            // Compter les articles publiés ou brouillons liés à cette catégorie
            global $wpdb;
            $count = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE tt.term_id = %d
                  AND tt.taxonomy = 'category'
                  AND p.post_type = 'post'
                  AND p.post_status IN ('publish','draft')
            ", $cat->term_id));

            if ($count === 0) {
                $parent_name = '';
                if ($cat->parent) {
                    $parent = get_term($cat->parent, 'category');
                    $parent_name = !is_wp_error($parent) ? $parent->name : '';
                }
                $to_delete[] = [
                    'term_id' => $cat->term_id,
                    'name'    => $cat->name,
                    'slug'    => $cat->slug,
                    'parent'  => $parent_name,
                ];
            }
        }

        if (!$dry) {
            foreach ($to_delete as $cat) {
                wp_delete_term($cat['term_id'], 'category');
            }
        }

        $count = count($to_delete);
        $mode  = $dry ? 'Simulation' : 'Supprimées';
        return [
            'message' => "{$mode} : {$count} catégorie(s) vide(s)" . ($dry ? ' seraient supprimées.' : ' supprimées.'),
            'cats'    => $to_delete,
        ];
    }

    private function runDeleteOrphanMedia($dry, $limit)
    {
        global $wpdb;

        // Médias non attachés à un post parent (post_parent = 0)
        // ET dont l'URL n'apparaît dans aucun post_content publié
        $attachments = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_mime_type, post_date, guid
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
              AND post_status = 'inherit'
              AND post_parent = 0
            ORDER BY post_date ASC
            LIMIT %d
        ", $limit));

        if (empty($attachments)) {
            return ['message' => 'Aucun média orphelin trouvé.', 'items' => []];
        }

        // Récupérer tous les post_content en une requête pour éviter N+1
        $all_content = (string) $wpdb->get_var("
            SELECT GROUP_CONCAT(post_content SEPARATOR ' ')
            FROM {$wpdb->posts}
            WHERE post_type IN ('post','page')
              AND post_status IN ('publish','draft')
        ");

        $to_delete = [];

        foreach ($attachments as $att) {
            $url      = wp_get_attachment_url($att->ID);
            $filename = basename($url ?: $att->guid);

            // Vérifier si l'URL ou le nom de fichier apparaît dans un contenu
            if ($url && strpos($all_content, $filename) !== false) {
                continue; // utilisé dans un contenu, on garde
            }

            $path = get_attached_file($att->ID);
            $size = ($path && file_exists($path)) ? size_format(filesize($path)) : '—';

            $to_delete[] = [
                'id'       => $att->ID,
                'filename' => $filename,
                'mime'     => $att->post_mime_type,
                'date'     => date('d/m/Y', strtotime($att->post_date)),
                'size'     => $size,
            ];
        }

        $deleted = 0;
        if (!$dry) {
            foreach ($to_delete as $item) {
                if (wp_delete_attachment($item['id'], true)) {
                    $deleted++;
                }
            }
        }

        $count = count($to_delete);
        $mode  = $dry ? 'Simulation' : 'Supprimés';
        $msg   = $dry
            ? "{$mode} : {$count} média(s) orphelin(s) seraient supprimés (sur {$limit} analysés)."
            : "{$mode} : {$deleted}/{$count} média(s) supprimés.";

        return ['message' => $msg, 'items' => $to_delete];
    }

    /**
     * Detecte les articles partageant le meme couple prefixe+numero
     * (ex: deux articles "INF144 - ...") et renumerote les doublons
     * en cascade : le plus ancien (ID le plus bas) garde son numero,
     * les suivants recoivent le prochain numero disponible pour ce
     * prefixe, en incrementant a chaque assignation pour eviter toute
     * nouvelle collision entre doublons traites dans le meme lot.
     * Meme convention de format que ArticleTitleNumberer (PREFIX+3 chiffres).
     */
    private function runFixDuplicatePrefixes($dry)
    {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT ID, post_title
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
              AND post_status NOT IN ('trash', 'auto-draft')
            ORDER BY ID ASC
        ");

        $groups      = array(); // prefixe => numero => [ {id, title, rest}, ... ]
        $maxByPrefix = array(); // prefixe => plus grand numero observe

        foreach ($rows as $row) {
            $title = html_entity_decode((string) $row->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = trim($title);

            if (!preg_match('/^([A-Za-z]{3})(\d+)(.*)$/u', $title, $m)) {
                continue;
            }

            $prefix = strtoupper($m[1]);
            $number = (int) $m[2];
            $rest   = $this->cleanTitleSuffix($m[3]);

            $groups[$prefix][$number][] = array(
                'id'    => (int) $row->ID,
                'title' => $title,
                'rest'  => $rest,
            );

            if (!isset($maxByPrefix[$prefix]) || $number > $maxByPrefix[$prefix]) {
                $maxByPrefix[$prefix] = $number;
            }
        }

        $duplicates = array();

        foreach ($groups as $prefix => $numbers) {
            ksort($numbers);
            foreach ($numbers as $number => $posts) {
                if (count($posts) < 2) {
                    continue;
                }

                $keep = array_shift($posts); // le plus ancien conserve son numero

                foreach ($posts as $dup) {
                    $maxByPrefix[$prefix]++;
                    $newNumber = $maxByPrefix[$prefix];

                    $suffix   = $dup['rest'] !== '' ? $dup['rest'] : $dup['title'];
                    $newTitle = sprintf('%s%03d - %s', $prefix, $newNumber, $suffix);

                    $duplicates[] = array(
                        'prefix'     => $prefix,
                        'number'     => $number,
                        'kept_id'    => $keep['id'],
                        'kept_title' => $keep['title'],
                        'dup_id'     => $dup['id'],
                        'old_title'  => $dup['title'],
                        'new_title'  => $newTitle,
                    );
                }
            }
        }

        if (!$dry) {
            foreach ($duplicates as $d) {
                wp_update_post(array(
                    'ID'         => $d['dup_id'],
                    'post_title' => $d['new_title'],
                    'post_name'  => sanitize_title($d['new_title']),
                ));
            }
        }

        $count = count($duplicates);
        $mode  = $dry ? 'Simulation' : 'Corrigés';

        return array(
            'message'    => "{$mode} : {$count} doublon(s) de préfixe" . ($dry ? ' seraient renumérotés en cascade.' : ' renumérotés en cascade.'),
            'duplicates' => $duplicates,
        );
    }

    private function cleanTitleSuffix($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);
        $text = preg_replace('/^[\s\-\–\—\:\/\\\\|]+/u', '', $text);
        $text = preg_replace('/[\s\-\–\—\:\/\\\\|]+$/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $first = mb_substr($text, 0, 1, 'UTF-8');
            $rest  = mb_substr($text, 1, null, 'UTF-8');
            return mb_strtoupper($first, 'UTF-8') . $rest;
        }

        return strtoupper(substr($text, 0, 1)) . substr($text, 1);
    }

    public function renderIaPage()
    {
        $saved      = false;
        $save_error = '';
        $ia_config  = get_option( 'schilo_ia_config', array() );

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset( $_POST['schilo_ia_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['schilo_ia_nonce'] ) ), 'schilo_save_ia_config' )
            && current_user_can( 'manage_options' )
        ) {
            /* Clé Claude */
            $claude_key_raw = isset( $_POST['sia_claude_key'] ) ? sanitize_text_field( wp_unslash( $_POST['sia_claude_key'] ) ) : '';
            $claude_key = ( $claude_key_raw && strpos( $claude_key_raw, '*' ) === false )
                ? $claude_key_raw
                : ( isset( $ia_config['claude']['api_key'] ) ? $ia_config['claude']['api_key'] : '' );

            /* Clé OpenAI */
            $openai_key_raw = isset( $_POST['sia_openai_key'] ) ? sanitize_text_field( wp_unslash( $_POST['sia_openai_key'] ) ) : '';
            $openai_key = ( $openai_key_raw && strpos( $openai_key_raw, '*' ) === false )
                ? $openai_key_raw
                : ( isset( $ia_config['openai']['api_key'] ) ? $ia_config['openai']['api_key'] : '' );

            $allowed_claude  = array( 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001', 'claude-opus-4-8' );
            $allowed_openai  = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' );
            $allowed_provs   = array( 'claude', 'openai' );

            $claude_model    = in_array( $_POST['sia_claude_model'] ?? '', $allowed_claude, true )  ? $_POST['sia_claude_model'] : 'claude-sonnet-4-6';
            $openai_model    = in_array( $_POST['sia_openai_model'] ?? '', $allowed_openai, true )  ? $_POST['sia_openai_model'] : 'gpt-4o';
            $default_prov    = in_array( $_POST['sia_default_provider'] ?? '', $allowed_provs, true ) ? $_POST['sia_default_provider'] : 'claude';
            $temperature     = min( 1.0, max( 0.0, (float) ( $_POST['sia_temperature'] ?? 0.7 ) ) );

            $ia_config = array(
                'claude' => array( 'api_key' => $claude_key, 'model' => $claude_model ),
                'openai' => array( 'api_key' => $openai_key, 'model' => $openai_model ),
                'default_provider' => $default_prov,
                'temperature'      => $temperature,
            );

            update_option( 'schilo_ia_config', $ia_config, false );
            $saved = true;
        }

        include SCHILO_BUILDER_PATH . 'views/admin/ia-page.php';
    }

    public function ajaxTestIa()
    {
        check_ajax_referer( 'schilo_test_ia', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Non autorisé.' ), 403 );
        }

        $provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : '';
        $model    = isset( $_POST['model'] )    ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
        $api_key  = isset( $_POST['api_key'] )  ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        /* Si la clé n'a pas été modifiée, utiliser celle enregistrée */
        if ( $api_key === '__USE_SAVED__' ) {
            $ia_config = get_option( 'schilo_ia_config', array() );
            $api_key   = isset( $ia_config[ $provider ]['api_key'] ) ? $ia_config[ $provider ]['api_key'] : '';
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => 'Clé API manquante.' ) );
        }

        if ( $provider === 'claude' ) {
            $allowed_models = array( 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001', 'claude-opus-4-8' );
            if ( ! in_array( $model, $allowed_models, true ) ) $model = 'claude-haiku-4-5-20251001';

            $response = wp_remote_post(
                'https://api.anthropic.com/v1/messages',
                array(
                    'headers' => array(
                        'x-api-key'         => $api_key,
                        'anthropic-version' => '2023-06-01',
                        'content-type'      => 'application/json',
                    ),
                    'body'    => wp_json_encode( array(
                        'model'      => $model,
                        'max_tokens' => 5,
                        'messages'   => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
                    ) ),
                    'timeout' => 15,
                )
            );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( array( 'message' => $response->get_error_message() ) );
            }
            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( $code === 200 ) {
                wp_send_json_success( array( 'message' => 'Claude connecté — modèle ' . $model ) );
            } else {
                $err = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Code HTTP ' . $code;
                wp_send_json_error( array( 'message' => $err ) );
            }

        } elseif ( $provider === 'openai' ) {
            $allowed_models = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' );
            if ( ! in_array( $model, $allowed_models, true ) ) $model = 'gpt-4o-mini';

            $response = wp_remote_post(
                'https://api.openai.com/v1/chat/completions',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( array(
                        'model'      => $model,
                        'max_tokens' => 5,
                        'messages'   => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
                    ) ),
                    'timeout' => 15,
                )
            );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( array( 'message' => $response->get_error_message() ) );
            }
            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( $code === 200 ) {
                wp_send_json_success( array( 'message' => 'OpenAI connecté — modèle ' . $model ) );
            } else {
                $err = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Code HTTP ' . $code;
                wp_send_json_error( array( 'message' => $err ) );
            }

        } else {
            wp_send_json_error( array( 'message' => 'Provider inconnu.' ) );
        }
    }

}