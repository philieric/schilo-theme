<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\ArticleVersionService;

/**
 * Page de réglages listant les types de version disponibles (Grand public,
 * Académique, ...), proposés ensuite dans le sélecteur de la metabox de
 * chaque article.
 */
class VersionSettingsPage
{
    const NONCE_ACTION = 'schilo_builder_save_version_types';
    const NONCE_NAME = 'schilo_builder_version_types_nonce';

    public function register()
    {
        add_action('admin_menu', array($this, 'addMenu'));
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
