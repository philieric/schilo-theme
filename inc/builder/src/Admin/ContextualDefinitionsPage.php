<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\ContextualDefinitionService;

class ContextualDefinitionsPage
{
    private ContextualDefinitionService $service;

    public function __construct()
    {
        $this->service = new ContextualDefinitionService();
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'addMenu'), 30);
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('wp_ajax_schilo_definition_suggest_terms', array($this, 'ajaxSuggestTerms'));
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'schilo-builder-definitions') === false) return;
        wp_enqueue_script(
            'schilo-contextual-definitions-admin',
            SCHILO_BUILDER_URL . 'assets/admin/contextual-definitions-admin.js',
            array('jquery'),
            SCHILO_BUILDER_VERSION,
            true
        );
        wp_localize_script('schilo-contextual-definitions-admin', 'schiloDefinitions', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('schilo_definition_suggestions'),
        ));
    }

    public function ajaxSuggestTerms(): void
    {
        check_ajax_referer('schilo_definition_suggestions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accès refusé.'), 403);
        }

        $provider = sanitize_key($_POST['provider'] ?? 'claude');
        $postId = absint($_POST['post_id'] ?? 0);
        $existingTerms = sanitize_textarea_field(wp_unslash($_POST['existing_terms'] ?? ''));
        $result = $this->service->suggestTermsViaIA($postId, $provider, $existingTerms);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success(array('terms' => $result));
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'schilo-builder',
            'Définitions contextuelles',
            'Définitions',
            'manage_options',
            'schilo-builder-definitions',
            array($this, 'render')
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) return;
        $saved = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('schilo_builder_save_definitions');
            $input = isset($_POST['schilo_definitions']) && is_array($_POST['schilo_definitions'])
                ? wp_unslash($_POST['schilo_definitions'])
                : array();
            $this->service->saveSettings($input);
            $saved = true;
        }

        $settings = $this->service->getSettings();
        $sources = $this->service->getSourcePosts();
        require SCHILO_BUILDER_PATH . 'views/admin/contextual-definitions-page.php';
    }
}

