<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Entity\Section;
use Schilo\Builder\Repository\SectionRepository;
use Schilo\Builder\Service\PrefixDetector;
use Schilo\Builder\Service\ArticleTypeService;
use Schilo\Builder\Service\SectionTypeService;
use Schilo\Builder\Service\SectionStructureService;
use Schilo\Builder\Service\TemplateApplicationService;

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
