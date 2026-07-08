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

