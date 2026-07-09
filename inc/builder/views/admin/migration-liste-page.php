<?php
if (!defined('ABSPATH')) exit;

/** Variables fournies par SettingsPage::renderMigrationListeTab() :
 * @var array       $counts
 * @var array        $prefixCounts
 * @var array        $prefixesWithModel
 * @var array        $rows
 * @var int          $total
 * @var int          $total_pages
 * @var int          $paged
 * @var int          $per_page
 * @var string       $statut
 * @var string       $prefix
 * @var array|null   $listeResult
 */

$base_url = admin_url('admin.php?page=schilo-builder-migration-test');

$filter_url = function (array $override) use ($base_url, $statut, $prefix) {
    $args = array_filter(array_merge(['statut' => $statut, 'prefix' => $prefix], $override), fn($v) => $v !== '');
    return add_query_arg($args, $base_url);
};

$status_labels = [
    'migrated'     => ['label' => 'Migré', 'class' => 'scl-badge-green'],
    'not_migrated' => ['label' => 'Non migré', 'class' => 'scl-badge-grey'],
    'restored'     => ['label' => 'Restauré (annulé)', 'class' => 'scl-badge-orange'],
];
?>
<div class="wrap schilo-builder-settings">
    <h1 class="scl-page-title">
        <span class="dashicons dashicons-randomize" style="color:#2872d4;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Migration
        <span style="font-size:14px;font-weight:400;color:#64748b;margin-left:10px;"><?php echo esc_html($counts['total']); ?> articles candidats</span>
    </h1>

    <nav class="scl-tabs">
        <a href="<?php echo esc_url($base_url); ?>" class="scl-tab scl-tab-active">Liste</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'test', $base_url)); ?>" class="scl-tab">Test / Mapping</a>
    </nav>

    <?php if ($listeResult) : ?>
        <?php if (!empty($listeResult['ok'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo count($listeResult['ok']); ?> article(s) migré(s) avec succès.</p>
        </div>
        <?php endif; ?>
        <?php if (!empty($listeResult['skip'])) : ?>
        <div class="notice notice-info is-dismissible">
            <p><?php echo count($listeResult['skip']); ?> article(s) déjà migré(s), ignoré(s) (case "Forcer une nouvelle migration" décochée).</p>
        </div>
        <?php endif; ?>
        <?php if (!empty($listeResult['error'])) : ?>
        <div class="notice notice-error is-dismissible">
            <p><strong><?php echo count($listeResult['error']); ?> erreur(s) :</strong></p>
            <ul style="margin-left:20px;list-style:disc;">
                <?php foreach ($listeResult['error'] as $err) : ?>
                    <li><?php echo esc_html(get_the_title((int) $err['id'])); ?> — <?php echo esc_html($err['msg']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="scl-stats-row">
        <a href="<?php echo esc_url($filter_url(['statut' => ''])); ?>" class="scl-stat-card<?php echo $statut === '' ? ' scl-stat-active' : ''; ?>">
            <span class="scl-stat-num"><?php echo esc_html($counts['total']); ?></span>
            <span class="scl-stat-label">Total</span>
        </a>
        <a href="<?php echo esc_url($filter_url(['statut' => 'migrated'])); ?>" class="scl-stat-card scl-stat-green<?php echo $statut === 'migrated' ? ' scl-stat-active' : ''; ?>">
            <span class="scl-stat-num"><?php echo esc_html($counts['migrated']); ?></span>
            <span class="scl-stat-label">Migrés</span>
        </a>
        <a href="<?php echo esc_url($filter_url(['statut' => 'not_migrated'])); ?>" class="scl-stat-card<?php echo $statut === 'not_migrated' ? ' scl-stat-active' : ''; ?>">
            <span class="scl-stat-num" style="color:#64748b;"><?php echo esc_html($counts['not_migrated']); ?></span>
            <span class="scl-stat-label">Non migrés</span>
        </a>
    </div>

    <div class="scl-prefix-pills">
        <a href="<?php echo esc_url($filter_url(['prefix' => ''])); ?>" class="scl-prefix-tab<?php echo $prefix === '' ? ' current' : ''; ?>">
            Tous <span class="scl-tab-count">(<?php echo esc_html($counts['total']); ?>)</span>
        </a>
        <?php foreach ($prefixCounts as $pfx => $count) : ?>
        <a href="<?php echo esc_url($filter_url(['prefix' => $pfx])); ?>" class="scl-prefix-tab<?php echo $prefix === $pfx ? ' current' : ''; ?>">
            <?php echo esc_html($pfx); ?>
            <?php if (empty($prefixesWithModel[$pfx])) : ?><span title="Aucun modèle de migration configuré pour ce préfixe" style="color:#dc2626;">⚠</span><?php endif; ?>
            <span class="scl-tab-count">(<?php echo (int) $count; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($rows)) : ?>
        <p class="scl-empty">Aucun article à afficher pour ce filtre.</p>
    <?php else : ?>
    <form method="post" id="schilo-migration-liste-form">
        <?php wp_nonce_field('schilo_migration_liste', 'schilo_migration_liste_nonce'); ?>

        <div class="scl-toolbar">
            <button type="submit" name="schilo_migrate_selected" class="button button-primary">
                <span class="dashicons dashicons-randomize" style="font-size:15px;height:15px;width:15px;line-height:15px;vertical-align:middle;margin-right:3px;margin-top:0;"></span>
                Migrer la sélection
            </button>
            <button type="button" id="scl-btn-select-all" class="button">Tout sélectionner</button>
            <label style="margin-left:10px;font-size:13px;display:inline-flex;align-items:center;gap:5px;">
                <input type="checkbox" name="schilo_liste_redo" value="1">
                Forcer une nouvelle migration (même les articles déjà migrés)
            </label>
        </div>

        <table class="widefat scl-table" id="scl-migration-table">
            <thead>
                <tr>
                    <td class="check-column" style="width:32px;"><input type="checkbox" id="scl-check-all"></td>
                    <th>Article</th>
                    <th style="width:90px;">Préfixe</th>
                    <th style="width:130px;">Statut</th>
                    <th style="width:160px;">Date migration</th>
                    <th style="width:160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) :
                    $post_id  = (int) $row['post_id'];
                    $status   = $row['status'];
                    $hasModel = !empty($prefixesWithModel[$row['prefix']]);
                    $badge    = $status_labels[$status] ?? ['label' => esc_html($status), 'class' => 'scl-badge-grey'];
                ?>
                <tr data-post-id="<?php echo esc_attr($post_id); ?>">
                    <td class="check-column"><input type="checkbox" class="scl-row-check" name="post_ids[]" value="<?php echo esc_attr($post_id); ?>"></td>
                    <td>
                        <strong><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php echo esc_html($row['title']); ?></a></strong>
                    </td>
                    <td><?php echo esc_html($row['prefix']); ?></td>
                    <td><span class="scl-badge <?php echo esc_attr($badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span></td>
                    <td><?php echo $row['date'] ? esc_html(mysql2date('d/m/Y H:i', $row['date'])) : '—'; ?></td>
                    <td>
                        <?php if (!$hasModel) : ?>
                            <a href="<?php echo esc_url(add_query_arg(['tab' => 'test', 'schilo_test_prefix' => $row['prefix']], $base_url)); ?>" class="button button-small" title="Configurer un modèle pour ce préfixe">
                                Configurer un modèle
                            </a>
                        <?php else : ?>
                            <button type="submit" name="single_post_id" value="<?php echo esc_attr($post_id); ?>" class="button button-small<?php echo $status !== 'migrated' ? ' button-primary' : ''; ?>">
                                <?php echo $status === 'migrated' ? 'Remigrer' : 'Migrer'; ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <?php if ($total_pages > 1) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
                <a href="<?php echo esc_url($filter_url(['paged' => $p])); ?>"
                   class="button<?php echo $p === $paged ? ' button-primary' : ''; ?>" style="margin:0 2px;">
                    <?php echo (int) $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.getElementById('scl-btn-select-all')?.addEventListener('click', function () {
    var boxes = document.querySelectorAll('#scl-migration-table .scl-row-check');
    var allChecked = Array.from(boxes).every(function (c) { return c.checked; });
    boxes.forEach(function (c) { c.checked = !allChecked; });
});
document.getElementById('scl-check-all')?.addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('#scl-migration-table .scl-row-check').forEach(function (c) { c.checked = checked; });
});
</script>
