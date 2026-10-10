<?php

declare(strict_types=1);

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\ArticleReportService;

/**
 * Colonne « Signalé » de la liste des articles (wp-admin > Articles) : un
 * badge par type de problème signalé sur l'article (orange : à corriger,
 * vert : corrigé, en attente de vérification), suivi de sa note.
 */
class ArticleReportColumn
{
    public const COLUMN = 'schilo_signalement';

    private ArticleReportService $service;

    /** @var array<int, array<string, array{note:string, status:string}>>|null Chargé une seule fois par page. */
    private ?array $reportsByPost = null;

    public function __construct(ArticleReportService $service)
    {
        $this->service = $service;
    }

    public function register(): void
    {
        add_filter('manage_post_posts_columns', array($this, 'addColumn'));
        add_action('manage_post_posts_custom_column', array($this, 'renderCell'), 10, 2);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addColumn(array $columns): array
    {
        $columns[self::COLUMN] = '<span class="dashicons dashicons-flag" style="font-size:14px;height:14px;width:14px;vertical-align:middle;" aria-hidden="true"></span> '
            . esc_html__('Signalé', 'schilo');

        return $columns;
    }

    /**
     * @param string    $column
     * @param int|mixed $postId
     */
    public function renderCell($column, $postId): void
    {
        if ($column !== self::COLUMN) {
            return;
        }

        if ($this->reportsByPost === null) {
            $this->reportsByPost = $this->service->getAllByPost();
        }

        $reports = $this->reportsByPost[(int) $postId] ?? array();

        if (empty($reports)) {
            echo '<span style="color:#cbd5e1;" aria-hidden="true">—</span>'
                . '<span class="screen-reader-text">' . esc_html__('Non signalé', 'schilo') . '</span>';

            return;
        }

        $labels       = $this->service->getTypeLabels();
        $statusLabels = $this->service->getStatusLabels();

        foreach ($reports as $type => $report) {
            $fixed = $report['status'] === ArticleReportService::STATUS_FIXED;

            // Le statut est aussi écrit en toutes lettres : la couleur seule
            // ne suffit pas à le distinguer.
            echo '<div style="margin-bottom:4px;">'
                . '<span style="background:' . ($fixed ? '#dcfce7;color:#166534' : '#fef3c7;color:#92400e')
                . ';padding:2px 7px;border-radius:20px;font-size:11px;font-weight:700;">'
                . esc_html($labels[$type] ?? $type)
                . '</span>'
                . '<br><span style="font-size:11px;color:#475569;">'
                . esc_html($statusLabels[$report['status']] ?? $report['status'])
                . '</span>';

            if ($report['note'] !== '') {
                echo '<br><span style="font-size:11px;color:#64748b;">' . esc_html($report['note']) . '</span>';
            }

            echo '</div>';
        }
    }
}
