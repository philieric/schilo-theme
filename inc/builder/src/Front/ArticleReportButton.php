<?php

declare(strict_types=1);

namespace Schilo\Builder\Front;

use Schilo\Builder\Service\ArticleReportService;

/**
 * Boutons de signalement affichés dans le hero d'un article (un par type de
 * problème : versets, migration), uniquement pour les administrateurs
 * connectés — les autres visiteurs ne reçoivent ni les boutons, ni le script,
 * ni l'endpoint AJAX.
 */
class ArticleReportButton
{
    public const CAPABILITY   = 'manage_options';
    public const AJAX_ACTION  = 'schilo_toggle_article_report';
    public const NONCE_ACTION = 'schilo_article_report';

    private ArticleReportService $service;

    public function __construct(ArticleReportService $service)
    {
        $this->service = $service;
    }

    public function register(): void
    {
        // Pas de variante nopriv : réservé aux utilisateurs connectés.
        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'ajaxToggle'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('schilo_single_hero_badges', array($this, 'render'));
    }

    public function enqueueAssets(): void
    {
        if (!is_singular('post') || !current_user_can(self::CAPABILITY)) {
            return;
        }

        wp_enqueue_script(
            'schilo-article-report',
            SCHILO_BUILDER_URL . 'assets/front/article-report.js',
            array(),
            SCHILO_BUILDER_VERSION,
            true
        );

        wp_localize_script('schilo-article-report', 'schiloArticleReport', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'action'         => self::AJAX_ACTION,
            'nonce'          => wp_create_nonce(self::NONCE_ACTION),
            'statusReported' => __('Signalement enregistré.', 'schilo'),
            'statusRemoved'  => __('Signalement retiré.', 'schilo'),
            'statusError'    => __('Échec de l’enregistrement, réessayez.', 'schilo'),
        ));
    }

    /**
     * Libellés du bouton d'un type : [à signaler, déjà signalé].
     *
     * @return array{0:string, 1:string}
     */
    private function buttonLabels(string $type): array
    {
        if ($type === ArticleReportService::TYPE_VERSES) {
            return array(
                __('Signaler : versets', 'schilo'),
                __('Versets signalés — retirer', 'schilo'),
            );
        }

        return array(
            __('Signaler : migration', 'schilo'),
            __('Migration signalée — retirer', 'schilo'),
        );
    }

    /**
     * @param int|mixed $postId Passé par do_action('schilo_single_hero_badges', $post_id).
     */
    public function render($postId): void
    {
        $postId = (int) $postId;

        if ($postId <= 0 || !current_user_can(self::CAPABILITY)) {
            return;
        }

        $reportedTypes = $this->service->getReportedTypes($postId);

        foreach (ArticleReportService::TYPES as $type) {
            list($labelReport, $labelReported) = $this->buttonLabels($type);
            $reported = in_array($type, $reportedTypes, true);
            ?>
            <button type="button"
                    class="schilo-hero-badge schilo-report-btn<?php echo $reported ? ' is-reported' : ''; ?>"
                    data-schilo-report
                    data-post-id="<?php echo esc_attr((string) $postId); ?>"
                    data-type="<?php echo esc_attr($type); ?>"
                    data-label-report="<?php echo esc_attr($labelReport); ?>"
                    data-label-reported="<?php echo esc_attr($labelReported); ?>">
                <i class="ti ti-flag" aria-hidden="true"></i>
                <span data-schilo-report-label><?php echo esc_html($reported ? $labelReported : $labelReport); ?></span>
            </button>
            <?php
        }
        ?>
        <span class="schilo-report-status" role="status" aria-live="polite" data-schilo-report-status></span>
        <?php
    }

    public function ajaxToggle(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'schilo')), 403);
        }

        $postId   = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $type     = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $reported = $this->service->toggle($postId, $type, get_current_user_id());

        if ($reported === null) {
            wp_send_json_error(array('message' => __('Article ou type de signalement invalide.', 'schilo')), 400);
        }

        wp_send_json_success(array('reported' => $reported, 'type' => $type));
    }
}
