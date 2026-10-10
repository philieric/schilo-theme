<?php

declare(strict_types=1);

namespace Schilo\Builder\Front;

use Schilo\Builder\Service\ArticleReportService;

/**
 * Bouton « Signaler » affiché dans le hero d'un article, uniquement pour les
 * administrateurs connectés (les autres visiteurs ne reçoivent ni le bouton,
 * ni le script, ni l'endpoint AJAX).
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
            'labelReport'    => __('Signaler l’article', 'schilo'),
            'labelReported'  => __('Signalé — retirer', 'schilo'),
            'statusReported' => __('Signalement enregistré.', 'schilo'),
            'statusRemoved'  => __('Signalement retiré.', 'schilo'),
            'statusError'    => __('Échec de l’enregistrement, réessayez.', 'schilo'),
        ));
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

        $reported = $this->service->isReported($postId);
        ?>
        <button type="button"
                class="schilo-hero-badge schilo-report-btn<?php echo $reported ? ' is-reported' : ''; ?>"
                data-schilo-report
                data-post-id="<?php echo esc_attr((string) $postId); ?>">
            <i class="ti ti-flag" aria-hidden="true"></i>
            <span data-schilo-report-label><?php
                echo esc_html($reported ? __('Signalé — retirer', 'schilo') : __('Signaler l’article', 'schilo'));
            ?></span>
        </button>
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
        $reported = $this->service->toggle($postId, get_current_user_id());

        if ($reported === null) {
            wp_send_json_error(array('message' => __('Article introuvable.', 'schilo')), 400);
        }

        wp_send_json_success(array('reported' => $reported));
    }
}
