<?php
/** @var \Schilo\Builder\Service\WPBakeryMigrationService $service */
/** @var WP_Post[] $candidatePosts */
/** @var string $message */
/** @var string $error */
/** @var string $preview */
/** @var WP_Post|null $previewPost */
?>

<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Migration WPBakery</h1>

        <div class="notice notice-info">
            <p><strong>Sécurité :</strong> la migration ne supprime pas WPBakery ni Wikilogy du contenu original. Elle crée seulement une version Schilo Builder dans les métadonnées. Le bouton de restauration remet l’ancienne mise en page.</p>
        </div>

    <p class="schilo-admin-back">
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">
            ← Retour au tableau de bord
        </a>
    </p>

    <?php if (!empty($message)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="schilo-settings-card">
        <h2>Articles détectés avec WPBakery</h2>

        <p class="description">
            Cette version migre l’article vers plusieurs sections Schilo lorsque des éléments Wikilogy utiles sont détectés.
            Le contenu original WPBakery / Wikilogy n’est pas modifié. La migration crée uniquement des sections Schilo dans les métadonnées. Les éléments Wikilogy utiles sont transformés en sections Schilo : titre, contenu migré et articles liés. Le contenu original est sauvegardé dans
            <code>_schilo_backup_post_content</code>.
        </p>

        <?php if (empty($candidatePosts)) : ?>
            <p>Aucun article WPBakery détecté.</p>
        <?php else : ?>
            <?php
            $prefixDetector = new \Schilo\Builder\Service\PrefixDetector();
            $availablePrefixes = array();
            foreach ($candidatePosts as $candidate) {
                $availablePrefixes[$prefixDetector->detectFromPostId($candidate->ID)] = true;
            }
            $availablePrefixes = array_keys($availablePrefixes);
            sort($availablePrefixes);
            ?>

            <div class="schilo-migration-filters">
                <input type="search" id="schilo-migration-search" class="regular-text" placeholder="Rechercher un article par titre...">

                <select id="schilo-migration-prefix-filter">
                    <option value="">Tous les types</option>
                    <?php foreach ($availablePrefixes as $availablePrefix) : ?>
                        <option value="<?php echo esc_attr($availablePrefix); ?>"><?php echo esc_html($availablePrefix); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="schilo-migration-status-filter">
                    <option value="">Tous les statuts</option>
                    <option value="not_migrated">Non migré</option>
                    <option value="migrated">Migré</option>
                </select>

                <span id="schilo-migration-count" class="schilo-migration-count"></span>
            </div>

            <table class="widefat striped schilo-migration-table" id="schilo-migration-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:90px;">Type</th>
                        <th>Article</th>
                        <th style="width:150px;">Statut</th>
                        <th style="width:320px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidatePosts as $candidate) : ?>
                        <?php
                        $status = $service->getMigrationStatus($candidate->ID);
                        $prefix = $prefixDetector->detectFromPostId($candidate->ID);
                        $title = get_the_title($candidate->ID);
                        ?>
                        <tr class="schilo-migration-row"
                            data-prefix="<?php echo esc_attr($prefix); ?>"
                            data-status="<?php echo esc_attr($status); ?>"
                            data-title="<?php echo esc_attr(strtolower($title)); ?>">
                            <td><?php echo esc_html($candidate->ID); ?></td>
                            <td><span class="schilo-badge-primary"><?php echo esc_html($prefix); ?></span></td>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($candidate->ID)); ?>">
                                        <?php echo esc_html($title); ?>
                                    </a>
                                </strong>
                                <br>
                                <small><?php echo esc_html(get_permalink($candidate->ID)); ?></small>
                            </td>
                            <td>
                                <span class="schilo-migration-status schilo-status-<?php echo esc_attr($status); ?>">
                                    <?php echo esc_html($status); ?>
                                </span>
                            </td>
                            <td>
                                <a class="button" href="<?php echo esc_url(add_query_arg(array('schilo_configure' => $candidate->ID), admin_url('admin.php?page=schilo-builder-migration'))); ?>">
                                    Configurer
                                </a>

                                <form method="post" action="" class="schilo-inline-form">
                                    <?php wp_nonce_field('schilo_builder_migration', 'schilo_migration_nonce'); ?>
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr($candidate->ID); ?>">
                                    <input type="hidden" name="schilo_migration_action" value="preview">
                                    <button type="submit" class="button">Prévisualiser</button>
                                </form>

                                <form method="post" action="" class="schilo-inline-form">
                                    <?php wp_nonce_field('schilo_builder_migration', 'schilo_migration_nonce'); ?>
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr($candidate->ID); ?>">
                                    <input type="hidden" name="schilo_migration_action" value="migrate">
                                    <button type="submit" class="button button-primary" onclick="return confirm('Migrer cet article vers Schilo Builder ?');">
                                        Migrer
                                    </button>
                                </form>

                                <form method="post" action="" class="schilo-inline-form">
                                    <?php wp_nonce_field('schilo_builder_migration', 'schilo_migration_nonce'); ?>
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr($candidate->ID); ?>">
                                    <input type="hidden" name="schilo_migration_action" value="restore">
                                    <button type="submit" class="button" onclick="return confirm('Restaurer la mise en page originale WPBakery / Wikilogy depuis la sauvegarde ?');">
                                        Restaurer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="schilo-migration-pagination">
                <button type="button" class="button" id="schilo-migration-prev">&laquo; Précédent</button>
                <span id="schilo-migration-page-info"></span>
                <button type="button" class="button" id="schilo-migration-next">Suivant &raquo;</button>

                <label for="schilo-migration-per-page">
                    Par page :
                    <select id="schilo-migration-per-page">
                        <option value="20">20</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="0">Tous</option>
                    </select>
                </label>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($previewPost)) : ?>
        <div class="schilo-settings-card">
            <h2>Aperçu de migration</h2>
            <p>
                Article :
                <strong><?php echo esc_html(get_the_title($previewPost->ID)); ?></strong>
            </p>

            <?php
            $previewPrefix = (new \Schilo\Builder\Service\PrefixDetector())->detectFromPostId($previewPost->ID);
            $previewTemplate = (new \Schilo\Builder\Service\TemplateService())->getTemplateForPrefix($previewPrefix);
            ?>
            <p class="description">
                Type détecté : <span class="schilo-badge-primary"><?php echo esc_html($previewPrefix); ?></span>
                — Template appliqué : <strong><?php echo esc_html($previewTemplate['label']); ?></strong>
                (sections : <?php echo esc_html(implode(' → ', $previewTemplate['sections'])); ?>)
            </p>

            <div class="schilo-migration-preview">
                <?php echo wp_kses_post(wpautop($preview)); ?>
            </div>
        </div>
    <?php endif; ?>


    <script>
    (function () {
        const searchInput = document.getElementById('schilo-migration-search');
        const prefixFilter = document.getElementById('schilo-migration-prefix-filter');
        const statusFilter = document.getElementById('schilo-migration-status-filter');
        const countEl = document.getElementById('schilo-migration-count');
        const perPageSelect = document.getElementById('schilo-migration-per-page');
        const prevBtn = document.getElementById('schilo-migration-prev');
        const nextBtn = document.getElementById('schilo-migration-next');
        const pageInfo = document.getElementById('schilo-migration-page-info');
        const rows = document.querySelectorAll('#schilo-migration-table .schilo-migration-row');

        if (!rows.length) {
            return;
        }

        let currentPage = 1;

        function getMatchingRows() {
            const search = (searchInput.value || '').trim().toLowerCase();
            const prefix = prefixFilter.value;
            const status = statusFilter.value;

            return Array.prototype.filter.call(rows, function (row) {
                const matchesSearch = !search || row.dataset.title.indexOf(search) !== -1;
                const matchesPrefix = !prefix || row.dataset.prefix === prefix;
                const matchesStatus = !status || row.dataset.status === status;

                return matchesSearch && matchesPrefix && matchesStatus;
            });
        }

        function applyFilters() {
            const matching = getMatchingRows();
            const perPage = parseInt(perPageSelect.value, 10) || 0;
            const totalPages = perPage > 0 ? Math.max(1, Math.ceil(matching.length / perPage)) : 1;

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            if (currentPage < 1) {
                currentPage = 1;
            }

            const start = perPage > 0 ? (currentPage - 1) * perPage : 0;
            const end = perPage > 0 ? start + perPage : matching.length;

            rows.forEach(function (row) {
                row.style.display = 'none';
            });

            matching.slice(start, end).forEach(function (row) {
                row.style.display = '';
            });

            countEl.textContent = matching.length + ' / ' + rows.length + ' article(s)';

            if (perPage > 0) {
                pageInfo.textContent = 'Page ' + currentPage + ' / ' + totalPages;
            } else {
                pageInfo.textContent = '';
            }

            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = perPage <= 0 || currentPage >= totalPages;
        }

        searchInput.addEventListener('input', function () { currentPage = 1; applyFilters(); });
        prefixFilter.addEventListener('change', function () { currentPage = 1; applyFilters(); });
        statusFilter.addEventListener('change', function () { currentPage = 1; applyFilters(); });
        perPageSelect.addEventListener('change', function () { currentPage = 1; applyFilters(); });

        prevBtn.addEventListener('click', function () {
            currentPage -= 1;
            applyFilters();
        });

        nextBtn.addEventListener('click', function () {
            currentPage += 1;
            applyFilters();
        });

        applyFilters();
    })();
    </script>
</div>
