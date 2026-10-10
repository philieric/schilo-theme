<?php

declare(strict_types=1);

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\ArticleReportService;

/**
 * Schilo Builder > Signalements : liste des signalements posés depuis le
 * front (voir Schilo\Builder\Front\ArticleReportButton), filtrable par type
 * de problème et par statut. Un article corrigé passe « en attente de
 * vérification » ; son signalement n'est retiré qu'une fois la correction
 * vérifiée.
 */
class ArticleReportsPage
{
    public const PAGE_SLUG     = 'schilo-builder-signalements';
    public const REMOVE_ACTION = 'schilo_remove_article_report';
    public const STATUS_ACTION = 'schilo_set_article_report_status';

    private ArticleReportService $service;

    public function __construct(ArticleReportService $service)
    {
        $this->service = $service;
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'addMenu'), 30);
        add_action('admin_post_' . self::REMOVE_ACTION, array($this, 'handleRemove'));
        add_action('admin_post_' . self::STATUS_ACTION, array($this, 'handleStatus'));
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

    private function currentTypeFilter(): string
    {
        $type = isset($_REQUEST['type_filter']) ? sanitize_key(wp_unslash($_REQUEST['type_filter'])) : '';

        return $this->service->isValidType($type) ? $type : '';
    }

    private function currentStatusFilter(): string
    {
        $status = isset($_REQUEST['status_filter']) ? sanitize_key(wp_unslash($_REQUEST['status_filter'])) : '';

        return $this->service->isValidStatus($status) ? $status : '';
    }

    /**
     * Retour à la liste après une action, en conservant les filtres actifs.
     *
     * @param array<string, string> $args
     */
    private function redirectToList(array $args): void
    {
        $args = array_merge(array('page' => self::PAGE_SLUG), $args);

        $typeFilter = $this->currentTypeFilter();
        if ($typeFilter !== '') {
            $args['type_filter'] = $typeFilter;
        }

        $statusFilter = $this->currentStatusFilter();
        if ($statusFilter !== '') {
            $args['status_filter'] = $statusFilter;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handleStatus(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'schilo'), 403);
        }

        check_admin_referer(self::STATUS_ACTION);

        $postId = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $type   = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';

        $this->redirectToList(
            $this->service->setStatus($postId, $type, $status)
                ? array('status_updated' => $status)
                : array('status_error' => '1')
        );
    }

    public function handleRemove(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'schilo'), 403);
        }

        check_admin_referer(self::REMOVE_ACTION);

        $postId = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $type   = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $this->service->remove($postId, $type);

        $this->redirectToList(array('removed' => '1'));
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $removed       = isset($_GET['removed']);
        $statusError   = isset($_GET['status_error']);
        $statusUpdated = isset($_GET['status_updated']) ? sanitize_key(wp_unslash($_GET['status_updated'])) : '';
        $typeLabels    = $this->service->getTypeLabels();
        $typeFilter    = $this->currentTypeFilter();
        $typeCounts    = array_fill_keys(array_keys($typeLabels), 0);
        $statusLabels  = $this->service->getStatusLabels();
        $statusFilter  = $this->currentStatusFilter();
        $statusCounts  = array_fill_keys(array_keys($statusLabels), 0);
        $pageUrl       = add_query_arg('page', self::PAGE_SLUG, admin_url('admin.php'));
        $reports       = array();
        $total         = 0;

        if (!isset($statusLabels[$statusUpdated])) {
            $statusUpdated = '';
        }

        foreach ($this->service->getAll() as $row) {
            $total++;
            if (isset($typeCounts[$row['type']])) {
                $typeCounts[$row['type']]++;
            }
            if (isset($statusCounts[$row['status']])) {
                $statusCounts[$row['status']]++;
            }

            if ($typeFilter !== '' && $row['type'] !== $typeFilter) {
                continue;
            }
            if ($statusFilter !== '' && $row['status'] !== $statusFilter) {
                continue;
            }

            $post = get_post($row['post_id']);
            $user = $row['user_id'] > 0 ? get_userdata($row['user_id']) : false;

            $reports[] = array(
                'post_id'    => $row['post_id'],
                'type'       => $row['type'],
                'type_label' => $typeLabels[$row['type']] ?? $row['type'],
                'note'       => $row['note'],
                'status'     => $row['status'],
                'status_label' => $statusLabels[$row['status']] ?? $row['status'],
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
