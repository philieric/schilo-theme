<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Entity\Section;
use Schilo\Builder\Repository\SectionRepository;
use Schilo\Builder\Service\PrefixDetector;
use Schilo\Builder\Service\ArticleTypeService;
use Schilo\Builder\Service\SectionTypeService;
use Schilo\Builder\Service\SectionStructureService;
use Schilo\Builder\Service\TemplateApplicationService;
use Schilo\Builder\Service\ArticleVersionService;

class BuilderMetabox
{
    private $sectionRepository;
    private $prefixDetector;
    private $articleTypeService;

    public function __construct()
    {
        $this->sectionRepository = new SectionRepository();
        $this->prefixDetector = new PrefixDetector();
        $this->articleTypeService = new ArticleTypeService();
    }

    public function register()
    {
        add_action('edit_form_after_title', array($this, 'renderAfterTitle'));
        add_action('save_post', array($this, 'save'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('admin_post_schilo_apply_template', array($this, 'handleApplyTemplate'));
        add_action('wp_ajax_schilo_search_version_articles', array($this, 'ajaxSearchVersionArticles'));
    }

    /**
     * Recherche live (par titre) pour le combobox "Articles liés" de la
     * carte Versions — contrairement a #schilo-articles-data (plafonne a
     * 300 articles, trie alphabetiquement : les prefixes tardifs comme PER
     * n'y apparaissent jamais sur un site de plusieurs milliers d'articles),
     * interroge la base a la demande, sans limite de couverture.
     */
    public function ajaxSearchVersionArticles()
    {
        check_ajax_referer('schilo_search_version_articles', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Accès refusé.'), 403);
        }

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        $excludeId = isset($_GET['exclude']) ? (int) $_GET['exclude'] : 0;

        global $wpdb;

        // Recherche par TITRE uniquement (LIKE direct), pas la recherche WP
        // generique 's' qui matche aussi le contenu et melange la pertinence :
        // pour retrouver un article par son code (ex. "PER" -> PER001, PER002...)
        // seul un match sur le titre a du sens ici.
        $sql = "SELECT ID, post_title FROM {$wpdb->posts}
                WHERE post_type IN ('post', 'page')
                AND post_status = 'publish'";
        $params = array();

        if ($term !== '') {
            $sql .= ' AND post_title LIKE %s';
            $params[] = '%' . $wpdb->esc_like($term) . '%';
        }

        if ($excludeId > 0) {
            $sql .= ' AND ID != %d';
            $params[] = $excludeId;
        }

        $sql .= ' ORDER BY post_title ASC LIMIT 20';

        $rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

        $results = array();
        foreach ($rows as $row) {
            $results[] = array(
                'id' => (int) $row->ID,
                'title' => html_entity_decode((string) $row->post_title, ENT_QUOTES, 'UTF-8'),
            );
        }

        wp_send_json_success($results);
    }

    public function enqueueAssets($hook)
    {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_editor();

        wp_enqueue_style(
            'schilo-builder-admin',
            SCHILO_BUILDER_URL . 'assets/admin/builder-admin.css',
            array(),
            SCHILO_BUILDER_VERSION
        );

        wp_enqueue_script(
            'schilo-builder-admin',
            SCHILO_BUILDER_URL . 'assets/admin/builder-admin.js',
            array('jquery', 'jquery-ui-sortable', 'editor', 'quicktags', 'wplink'),
            SCHILO_BUILDER_VERSION,
            true
        );

        $sectionStructureService = new SectionStructureService();
        $sectionStructures = $sectionStructureService->getAll();

        /* ── Données pour la navigation par sections ── */
        $postIdForNav   = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        $prefixForNav   = $postIdForNav ? (new \Schilo\Builder\Service\ArticleTypeService())->resolveType($postIdForNav) : '';
        $templateForNav = (new \Schilo\Builder\Service\TemplateService())->getTemplateForPrefix($prefixForNav);
        $templateSectionOrder = isset($templateForNav['sections']) && is_array($templateForNav['sections'])
            ? array_values($templateForNav['sections'])
            : array();

        $sectionTypeLabels = array();
        $allTypes = (new \Schilo\Builder\Service\SectionTypeService())->getActiveTypes();
        foreach ((array) $allTypes as $typeKey => $typeCfg) {
            $sectionTypeLabels[$typeKey] = isset($typeCfg['label']) ? $typeCfg['label'] : $typeKey;
        }

        wp_localize_script(
            'schilo-builder-admin',
            'SchiloBuilderAdmin',
            array(
                'confirmDelete'        => 'Supprimer cette section ?',
                'confirmDuplicate'     => 'Dupliquer cette section ?',
                'mediaTitle'           => 'Choisir une image',
                'mediaButton'          => 'Utiliser cette image',
                'editorReady'          => true,
                'sectionStructures'    => $sectionStructures,
                'templateSectionOrder' => $templateSectionOrder,
                'sectionTypeLabels'    => $sectionTypeLabels,
                'ajaxUrl'              => admin_url('admin-ajax.php'),
                'versionSearchNonce'   => wp_create_nonce('schilo_search_version_articles'),
                'currentPostId'        => $postIdForNav,
                'versionAvailableLabels' => (new \Schilo\Builder\Service\ArticleVersionService())->getAvailableLabels(),
            )
        );
    }

    public function renderAfterTitle($post)
    {
        if (!$post || !isset($post->post_type) || $post->post_type !== 'post') {
            return;
        }

        wp_nonce_field('schilo_builder_save', 'schilo_builder_nonce');

        $postId = (int) $post->ID;

        $prefix = $this->articleTypeService->resolveType($postId);
        $selectedType = $this->articleTypeService->getSelectedType($postId);
        $availableTypes = $this->articleTypeService->getAvailableTypes();
        $sectionTypes = (new SectionTypeService())->getActiveTypes();
        $sections = $this->sectionRepository->findByPostId($postId);

        if (!is_array($availableTypes) || empty($availableTypes)) {
            $availableTypes = array(
                'AUTO' => array(
                    'label' => 'Automatique',
                    'description' => 'Détection automatique depuis le préfixe du titre',
                ),
            );
        }

        if (!is_array($sectionTypes)) {
            $sectionTypes = array();
        }

        if (!is_array($sections)) {
            $sections = array();
        }

        $lastAppliedTemplate = '';
        if (class_exists('\\Schilo\\Builder\\Service\\TemplateApplicationService')) {
            $lastAppliedTemplate = (new TemplateApplicationService())->getLastAppliedTemplate($postId);
        }

        $applyTemplateUrlBase = admin_url('admin-post.php?action=schilo_apply_template&post_id=' . $postId);
        $applyTemplateNonce = wp_create_nonce('schilo_apply_template_' . $postId);
        $applyTemplateUrl = add_query_arg(
            array(
                'template' => rawurlencode($selectedType),
                '_wpnonce' => $applyTemplateNonce,
            ),
            $applyTemplateUrlBase
        );

        $versionService = new ArticleVersionService();
        $versionEnabled = $versionService->isEnabled($postId);
        $versionLabel = $versionService->getLabel($postId);
        $versionIsPrimary = $versionService->isPrimary($postId);
        $versionAvailableLabels = $versionService->getAvailableLabels();
        $versionLinkedPosts = array();
        foreach ($versionService->getLinkedIds($postId) as $linkedId) {
            $linkedPost = get_post($linkedId);
            if ($linkedPost) {
                $linkedLabel = $versionService->getLabel($linkedId);
                $versionLinkedPosts[] = array(
                    'id' => $linkedId,
                    'title' => html_entity_decode(get_the_title($linkedPost), ENT_QUOTES, 'UTF-8'),
                    'label' => $linkedLabel !== '' ? $linkedLabel : '',
                );
            }
        }

        include SCHILO_BUILDER_PATH . 'views/admin/metabox-builder.php';
    }

    public function handleApplyTemplate()
    {
        $postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $template = isset($_GET['template']) ? sanitize_key(wp_unslash($_GET['template'])) : 'AUTO';

        if ($postId <= 0 || !current_user_can('edit_post', $postId)) {
            wp_die('Action non autorisée.');
        }

        check_admin_referer('schilo_apply_template_' . $postId);

        $this->articleTypeService->saveSelectedType($postId, $template);

        if (class_exists('\\Schilo\\Builder\\Service\\TemplateApplicationService')) {
            (new TemplateApplicationService())->applyTemplateToPost($postId, $template);
        }

        wp_safe_redirect(add_query_arg('schilo_template_applied', '1', get_edit_post_link($postId, 'raw')));
        exit;
    }

    public function save($postId, $post)
    {
        if (!$this->canSave($postId, $post)) {
            return;
        }

        $selectedType = isset($_POST['schilo_builder_type'])
            ? wp_unslash($_POST['schilo_builder_type'])
            : 'AUTO';

        $this->articleTypeService->saveSelectedType($postId, $selectedType);

        $versionEnabled = !empty($_POST['schilo_version_enabled']);
        $versionLinkedIds = isset($_POST['schilo_version_linked_ids']) && is_array($_POST['schilo_version_linked_ids'])
            ? array_map('intval', wp_unslash($_POST['schilo_version_linked_ids']))
            : array();
        $versionLabel = isset($_POST['schilo_version_label']) ? sanitize_text_field(wp_unslash($_POST['schilo_version_label'])) : '';
        $versionIsPrimary = !empty($_POST['schilo_version_is_primary']);

        $versionLinkedLabels = array();
        if (isset($_POST['schilo_version_linked_labels']) && is_array($_POST['schilo_version_linked_labels'])) {
            foreach (wp_unslash($_POST['schilo_version_linked_labels']) as $linkedId => $linkedLabelValue) {
                $versionLinkedLabels[(int) $linkedId] = sanitize_text_field((string) $linkedLabelValue);
            }
        }

        (new ArticleVersionService())->saveVersion($postId, $versionEnabled, $versionLinkedIds, $versionLabel, $versionIsPrimary, $versionLinkedLabels);

        $rawSections = (isset($_POST['schilo_sections']) && is_array($_POST['schilo_sections']))
            ? wp_unslash($_POST['schilo_sections'])
            : array();

        $sections = array();
        $structureService = new SectionStructureService();

        foreach ($rawSections as $index => $rawSection) {
            if (!is_array($rawSection)) {
                continue;
            }

            $sectionType = isset($rawSection['type']) ? $rawSection['type'] : 'paragraphe';

            $sections[] = Section::fromArray(array(
                'type' => $sectionType,
                'title' => isset($rawSection['title']) ? $rawSection['title'] : '',
                'content' => isset($rawSection['content']) ? $rawSection['content'] : '',
                'custom_class' => isset($rawSection['custom_class']) ? $rawSection['custom_class'] : '',
                'order' => (int) $index,
                'data' => $structureService->normalizeSectionData(
                    $sectionType,
                    isset($rawSection['data']) && is_array($rawSection['data']) ? $rawSection['data'] : array()
                ),
            ));
        }

        if (!empty($_POST['schilo_apply_template_sections']) && class_exists('\\Schilo\\Builder\\Service\\TemplateApplicationService')) {
            $sections = (new TemplateApplicationService())->completeSectionsForTemplate(
                (int) $postId,
                $selectedType,
                $sections
            );
        }

        $this->sectionRepository->save($postId, $sections);
    }

    private function canSave($postId, $post)
    {
        if (!isset($_POST['schilo_builder_nonce'])) {
            return false;
        }

        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['schilo_builder_nonce'])),
            'schilo_builder_save'
        )) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (!isset($post->post_type) || $post->post_type !== 'post') {
            return false;
        }

        return current_user_can('edit_post', (int) $postId);
    }
}
