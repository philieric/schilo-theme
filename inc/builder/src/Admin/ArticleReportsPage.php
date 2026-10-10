<?php

declare(strict_types=1);

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\ArticleReportService;

/**
 * Schilo Builder > Signalements : liste des articles signalés depuis le
 * front (voir Schilo\Builder\Front\ArticleReportButton), avec retrait du
 * signalement une fois l'article corrigé.
 */
class ArticleReportsPage
{
    public const PAGE_SLUG     = 'schilo-builder-signalements';
    public const REMOVE_ACTION = 'schilo_remove_article_report';

    private ArticleReportService $service;

    public function __construct(ArticleReportService $service)
    {
        $this->service = $service;
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'addMenu'), 30);
        add_action('admin_post_' . self::REMOVE_ACTION, array($this, 'handleRemove'));
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'schilo-builder',
            'Articles signalés',
            'Signalements',
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render')
        );
    }

    public function handleRemove(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'schilo'), 403);
        }

        check_admin_referer(self::REMOVE_ACTION);

        $postId = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $this->service->remove($postId);

        wp_safe_redirect(add_query_arg(
            array('page' => self::PAGE_SLUG, 'removed' => '1'),
            admin_url('admin.php')
        ));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $removed = isset($_GET['removed']);
        $reports = array();

        foreach ($this->service->getAll() as $row) {
            $post = get_post($row['post_id']);
            $user = $row['user_id'] > 0 ? get_userdata($row['user_id']) : false;

            $reports[] = array(
                'post_id'    => $row['post_id'],
                'title'      => $post ? html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8') : '',
                'exists'     => (bool) $post,
                'edit_url'   => $post ? (string) get_edit_post_link($post->ID, 'raw') : '',
                'view_url'   => $post ? (string) get_permalink($post) : '',
                'user'       => $user ? $user->display_name : '',
                'created_at' => $row['created_at'],
            );
        }

        require SCHILO_BUILDER_PATH . 'views/admin/article-reports-page.php';
    }
}
