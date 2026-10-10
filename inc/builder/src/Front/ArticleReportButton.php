<?php

declare(strict_types=1);

namespace Schilo\Builder\Front;

use Schilo\Builder\Service\ArticleReportService;

/**
 * Boutons de signalement affichés dans le hero d'un article (un par type de
 * problème : versets, migration) et champ de note facultative pour chaque
 * signalement actif, uniquement pour les administrateurs connectés — les
 * autres visiteurs ne reçoivent ni les boutons, ni le script, ni les
 * endpoints AJAX.
 */
class ArticleReportButton
{
    public const CAPABILITY       = 'manage_options';
    public const AJAX_ACTION      = 'schilo_toggle_article_report';
    public const AJAX_NOTE_ACTION = 'schilo_save_article_report_note';
    public const NONCE_ACTION     = 'schilo_article_report';

    private ArticleReportService $service;

    public function __construct(ArticleReportService $service)
    {
        $this->service = $service;
    }

    public function register(): void
    {
        // Pas de variante nopriv : réservé aux utilisateurs connectés.
        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'ajaxToggle'));
        add_action('wp_ajax_' . self::AJAX_NOTE_ACTION, array($this, 'ajaxSaveNote'));
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
            'noteAction'     => self::AJAX_NOTE_ACTION,
            'nonce'          => wp_create_nonce(self::NONCE_ACTION),
            'statusReported' => __('Signalement enregistré. Vous pouvez ajouter une note.', 'schilo'),
            'statusRemoved'  => __('Signalement retiré.', 'schilo'),
            'statusNote'     => __('Note enregistrée.', 'schilo'),
            'statusError'    => __('Échec de l’enregistrement, réessayez.', 'schilo'),
        ));
    }

    /**
     * Libellés d'un type : bouton à signaler, bouton déjà signalé, champ note.
     *
     * @return array{0:string, 1:string, 2:string}
     */
    private function labels(string $type): array
    {
        if ($type === ArticleReportService::TYPE_VERSES) {
            return array(
                __('Signaler : versets', 'schilo'),
                __('Versets signalés — retirer', 'schilo'),
                __('Note versets (facultative)', 'schilo'),
            );
        }

        return array(
            __('Signaler : migration', 'schilo'),
            __('Migration signalée — retirer', 'schilo'),
            __('Note migration (facultative)', 'schilo'),
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

        $reports = $this->service->getReports($postId);

        foreach (ArticleReportService::TYPES as $type) {
            list($labelReport, $labelReported) = $this->labels($type);
            $reported = array_key_exists($type, $reports);
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

        <div class="schilo-report-notes">
            <?php foreach (ArticleReportService::TYPES as $type) :
                $noteLabel = $this->labels($type)[2];
                $reported  = array_key_exists($type, $reports);
                $inputId   = 'schilo-report-note-' . $type;
                ?>
                <div class="schilo-report-note"
                     data-schilo-report-note="<?php echo esc_attr($type); ?>"
                     data-post-id="<?php echo esc_attr((string) $postId); ?>"<?php echo $reported ? '' : ' hidden'; ?>>
                    <label for="<?php echo esc_attr($inputId); ?>"><?php echo esc_html($noteLabel); ?></label>
                    <input type="text"
                           id="<?php echo esc_attr($inputId); ?>"
                           maxlength="<?php echo esc_attr((string) ArticleReportService::NOTE_MAX_LENGTH); ?>"
                           value="<?php echo esc_attr($reported ? $reports[$type] : ''); ?>"
                           autocomplete="off">
                    <button type="button" class="schilo-report-note__save" data-schilo-report-note-save>
                        <?php esc_html_e('Enregistrer la note', 'schilo'); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Contrôles communs aux deux endpoints ; termine la requête en erreur si
     * l'appelant n'est pas un administrateur muni d'un nonce valide.
     */
    private function guardAjax(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'schilo')), 403);
        }
    }

    public function ajaxToggle(): void
    {
        $this->guardAjax();

        $postId   = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $type     = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $reported = $this->service->toggle($postId, $type, get_current_user_id());

        if ($reported === null) {
            wp_send_json_error(array('message' => __('Article ou type de signalement invalide.', 'schilo')), 400);
        }

        wp_send_json_success(array('reported' => $reported, 'type' => $type));
    }

    public function ajaxSaveNote(): void
    {
        $this->guardAjax();

        $postId = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $type   = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $note   = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
        $saved  = $this->service->saveNote($postId, $type, $note);

        if ($saved === null) {
            wp_send_json_error(array('message' => __('Aucun signalement de ce type sur cet article.', 'schilo')), 400);
        }

        wp_send_json_success(array('note' => $saved, 'type' => $type));
    }
}
