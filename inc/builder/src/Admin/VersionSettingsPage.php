<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\ArticleVersionService;
use Schilo\Builder\Service\AnnexeService;
use Schilo\Builder\Service\ClassementService;

/**
 * Page de réglages listant les types de version disponibles (Grand public,
 * Académique, ...), proposés ensuite dans le sélecteur de la metabox de
 * chaque article.
 */
class VersionSettingsPage
{
    const NONCE_ACTION = 'schilo_builder_save_version_types';
    const NONCE_NAME = 'schilo_builder_version_types_nonce';

    const ANNEXE_NONCE_ACTION = 'schilo_builder_save_annexe_settings';
    const ANNEXE_NONCE_NAME = 'schilo_builder_annexe_settings_nonce';

    public function register()
    {
        add_action('admin_menu', array($this, 'addMenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAnnexeIaAssets'));
        add_action('wp_ajax_schilo_generate_annexe_text', array($this, 'ajaxGenerateAnnexeText'));
    }

    /**
     * Charge le bouton "Générer via IA" du texte d'introduction des annexes,
     * uniquement sur cette page de réglages.
     */
    public function enqueueAnnexeIaAssets()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'schilo-builder-versions') {
            return;
        }

        wp_enqueue_style(
            'schilo-ai-overlay',
            SCHILO_BUILDER_URL . 'assets/admin/ai-overlay.css',
            array(),
            SCHILO_BUILDER_VERSION
        );
        wp_enqueue_script(
            'schilo-ai-overlay',
            SCHILO_BUILDER_URL . 'assets/admin/ai-overlay.js',
            array(),
            SCHILO_BUILDER_VERSION,
            true
        );
        wp_enqueue_script(
            'schilo-annexe-settings-ia',
            SCHILO_BUILDER_URL . 'assets/admin/annexe-settings-ia.js',
            array('jquery', 'schilo-ai-overlay'),
            SCHILO_BUILDER_VERSION,
            true
        );
        wp_localize_script('schilo-annexe-settings-ia', 'schiloAnnexeIa', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('schilo_annexe_ia'),
        ));
    }

    /**
     * Genere une phrase d'introduction pour le bloc "annexes" en bas des
     * articles, a partir du libelle personnalise choisi par l'admin (pas de
     * contenu d'article a echantillonner ici, contrairement a la description
     * de categorie : le prompt reste generique).
     */
    public function ajaxGenerateAnnexeText()
    {
        @set_time_limit(90);

        check_ajax_referer('schilo_annexe_ia', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accès refusé.'), 403);
        }

        $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : AnnexeService::DEFAULT_LABEL;

        $iaConfig = get_option('schilo_ia_config', array());
        $provider = isset($iaConfig['default_provider']) ? sanitize_key($iaConfig['default_provider']) : 'claude';

        if (empty($iaConfig[$provider]['api_key'] ?? '')) {
            wp_send_json_error(array('message' => 'Clé API ' . $provider . ' non configurée (Schilo Builder > Intelligence Artificielle).'));
        }

        $prompt = "Redige une seule phrase courte (20 mots maximum), en français, destinee a introduire "
            . "un bloc \"" . $label . "\" affiche en bas des articles d'etude biblique du site Schilo. "
            . "Ce bloc liste des articles secondaires (annexes) qui apportent des precisions complementaires "
            . "sur le sujet traite. Ton neutre et informatif, pas de guillemets autour de la phrase, "
            . "renvoie uniquement la phrase.";

        $raw = (new ClassementService())->callIaRaw($provider, $prompt, 200);

        if (is_wp_error($raw)) {
            wp_send_json_error(array('message' => $raw->get_error_message()));
        }

        $text = trim((string) $raw, " \t\n\r\0\x0B\"'");
        if ($text === '') {
            wp_send_json_error(array('message' => "L'IA n'a pas renvoyé de texte."));
        }

        wp_send_json_success(array('text' => $text));
    }

    public function addMenu()
    {
        add_submenu_page(
            'schilo-builder',
            'Versions d\'article',
            'Versions d\'article',
            'manage_options',
            'schilo-builder-versions',
            array($this, 'renderPage')
        );
    }

    public function renderPage()
    {
        $service = new ArticleVersionService();
        $saved = false;
        $blockedLabels = array();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[self::NONCE_NAME])) {
            $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));

            if (wp_verify_nonce($nonce, self::NONCE_ACTION) && current_user_can('manage_options')) {
                $rawLabels = isset($_POST['schilo_version_types']) && is_array($_POST['schilo_version_types'])
                    ? wp_unslash($_POST['schilo_version_types'])
                    : array();
                $rawLabels = array_values(array_filter(array_map(function ($l) {
                    return trim(sanitize_text_field((string) $l));
                }, $rawLabels)));

                // Filet de sécurité serveur (le JS bloque déjà le retrait d'un
                // type utilisé au clic sur "Retirer") : un type actuellement
                // affecté à au moins un article ne doit jamais disparaître de
                // la liste, sinon la metabox de cet article n'aurait plus son
                // type dans le sélecteur.
                $before = $service->getAvailableLabels();
                $removed = array_diff($before, $rawLabels);
                $blockedLabels = array_values(array_intersect($removed, $service->getUsedLabels()));

                if (empty($blockedLabels)) {
                    $service->saveAvailableLabels($rawLabels);
                    $saved = true;
                }
            }
        }

        $labels = $service->getAvailableLabels();
        $usedLabels = $service->getUsedLabels();

        $annexeService = new AnnexeService();
        $annexeSaved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[self::ANNEXE_NONCE_NAME])) {
            $nonce = sanitize_text_field(wp_unslash($_POST[self::ANNEXE_NONCE_NAME]));

            if (wp_verify_nonce($nonce, self::ANNEXE_NONCE_ACTION) && current_user_can('manage_options')) {
                $annexeLabel = isset($_POST['schilo_annexe_label']) ? wp_unslash($_POST['schilo_annexe_label']) : '';
                $annexeText = isset($_POST['schilo_annexe_text']) ? wp_unslash($_POST['schilo_annexe_text']) : '';
                $annexeService->saveSettings($annexeLabel, $annexeText);
                $annexeSaved = true;
            }
        }

        $annexeLabel = $annexeService->getLabel();
        $annexeText = $annexeService->getIntroText();

        ?>
        <div class="wrap schilo-builder-settings">
            <h1>Versions d'article</h1>
            <p class="schilo-dashboard-intro">
                Types de version proposés dans la metabox de chaque article (ex. « Grand public », « Académique »).
                Un article peut être lié à un ou plusieurs autres articles représentant d'autres versions du même contenu ;
                seul l'article marqué « principal » apparaît dans les archives.
            </p>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p>Types de version enregistrés.</p></div>
            <?php endif; ?>

            <?php if (!empty($blockedLabels)) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        Impossible de supprimer <?php echo count($blockedLabels) > 1 ? 'les types suivants, encore utilisés' : 'le type suivant, encore utilisé'; ?>
                        par au moins un article : <strong><?php echo esc_html(implode(', ', $blockedLabels)); ?></strong>.
                        Retirez d'abord ce type des articles concernés (metabox « Versions de l'article »), puis réessayez.
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" class="schilo-tool-card" id="schilo-version-types-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

                <table class="widefat fixed" style="max-width:520px;">
                    <thead><tr><th>Libellé</th><th style="width:60px;"></th></tr></thead>
                    <tbody id="schilo-version-types-rows">
                        <?php foreach ($labels as $index => $label) : ?>
                            <tr>
                                <td>
                                    <input type="text" class="regular-text" name="schilo_version_types[]" value="<?php echo esc_attr($label); ?>">
                                </td>
                                <td>
                                    <button type="button" class="button schilo-version-type-remove">Retirer</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button" id="schilo-version-type-add">+ Ajouter un type</button>
                </p>

                <p class="submit">
                    <button type="submit" class="button button-primary">Enregistrer</button>
                </p>
            </form>

            <h2>Notes complémentaires (annexes)</h2>
            <p class="schilo-dashboard-intro">
                Personnalise l'affichage du bloc listant les articles secondaires (annexes) en bas de chaque
                article concerné — voir la carte « Articles secondaires (annexes) » dans l'édition d'un article.
            </p>

            <?php if ($annexeSaved) : ?>
                <div class="notice notice-success is-dismissible"><p>Réglages des annexes enregistrés.</p></div>
            <?php endif; ?>

            <form method="post" class="schilo-tool-card" id="schilo-annexe-settings-form" style="max-width:640px;">
                <?php wp_nonce_field(self::ANNEXE_NONCE_ACTION, self::ANNEXE_NONCE_NAME); ?>

                <p>
                    <label for="schilo-annexe-label"><strong>Libellé</strong></label><br>
                    <input type="text" id="schilo-annexe-label" name="schilo_annexe_label" class="regular-text"
                           value="<?php echo esc_attr($annexeLabel); ?>" placeholder="<?php echo esc_attr(AnnexeService::DEFAULT_LABEL); ?>">
                    <p class="description">Titre du bloc affiché en bas des articles, et intitulé repris dans la popup d'aperçu.</p>
                </p>

                <p>
                    <label for="schilo-annexe-text"><strong>Texte d'introduction</strong> <span style="font-weight:400;color:#646970;">(facultatif)</span></label><br>
                    <textarea id="schilo-annexe-text" name="schilo_annexe_text" class="large-text" rows="3"><?php echo esc_textarea($annexeText); ?></textarea>
                    <p class="description">Courte phrase affichée sous le titre du bloc, avant la liste des annexes.</p>
                </p>

                <p>
                    <button type="button" class="button" id="schilo-annexe-ia-generate">
                        <span class="dashicons dashicons-superhero" style="font-size:15px;height:15px;width:15px;line-height:15px;vertical-align:middle;margin-right:3px;margin-top:0;"></span>
                        Générer via IA
                    </button>
                    <span id="schilo-annexe-ia-status" style="margin-left:8px;"></span>
                </p>

                <p class="submit">
                    <button type="submit" class="button button-primary">Enregistrer</button>
                </p>
            </form>
        </div>

        <script>
        (function () {
            var rows = document.getElementById('schilo-version-types-rows');
            var addBtn = document.getElementById('schilo-version-type-add');
            var usedLabels = <?php echo wp_json_encode($usedLabels); ?>;

            addBtn.addEventListener('click', function () {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td><input type="text" class="regular-text" name="schilo_version_types[]" value=""></td>'
                    + '<td><button type="button" class="button schilo-version-type-remove">Retirer</button></td>';
                rows.appendChild(tr);
            });

            rows.addEventListener('click', function (e) {
                if (!e.target || !e.target.classList.contains('schilo-version-type-remove')) {
                    return;
                }
                var tr = e.target.closest('tr');
                var input = tr.querySelector('input[name="schilo_version_types[]"]');
                var value = input ? input.value.trim() : '';

                if (value && usedLabels.indexOf(value) !== -1) {
                    window.alert(
                        'Impossible de supprimer « ' + value + ' » : ce type est actuellement utilisé par au moins un article.\n\n' +
                        'Retirez-le d\'abord des articles concernés (metabox « Versions de l\'article »), puis réessayez.'
                    );
                    return;
                }

                tr.remove();
            });
        })();
        </script>
        <?php
    }
}
