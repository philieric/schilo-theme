<?php
if (!defined('ABSPATH')) exit;

use Schilo\Builder\Service\ClassementService;

$service = new ClassementService();
$counts  = $service->getCounts();

$statut = isset($_GET['statut']) && in_array($_GET['statut'], ['classe', 'non_classe'], true)
    ? sanitize_key($_GET['statut'])
    : '';
$prefix = isset($_GET['prefix']) && preg_match('/^[A-Z]{3}$/', (string) $_GET['prefix'])
    ? sanitize_text_field($_GET['prefix'])
    : '';
$paged = max(1, absint($_GET['paged'] ?? 1));
$per_page = 30;

$list = $service->getList($per_page, $paged, $statut, $prefix);
$rows = $list['rows'];
$total = $list['total'];
$total_pages = (int) ceil($total / $per_page);
$prefixes = $service->getPrefixCounts();

$base_url = admin_url('admin.php?page=schilo-builder-classement');

// Construit une URL de filtre en conservant les autres filtres actifs (statut/prefix), paged reinitialise a 1.
$filter_url = function (array $override) use ($base_url, $statut, $prefix) {
    $args = array_filter(array_merge(['statut' => $statut, 'prefix' => $prefix], $override), fn($v) => $v !== '');
    return add_query_arg($args, $base_url);
};
?>
<div class="wrap schilo-builder-settings">
    <h1 class="scl-page-title">
        <span class="dashicons dashicons-networking" style="color:#2872d4;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Parcours &amp; Thèmes
        <span style="font-size:14px;font-weight:400;color:#64748b;margin-left:10px;"><?php echo esc_html($counts['total']); ?> articles indexés</span>
    </h1>

    <nav class="scl-tabs">
        <a href="<?php echo esc_url($base_url); ?>" class="scl-tab scl-tab-active">Classement</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'termes', $base_url)); ?>" class="scl-tab">Termes</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'config', $base_url)); ?>" class="scl-tab">Configuration</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'audit', $base_url)); ?>" class="scl-tab">Audit</a>
    </nav>

    <div class="scl-stats-row">
        <a href="<?php echo esc_url($filter_url(['statut' => ''])); ?>" class="scl-stat-card<?php echo $statut === '' ? ' scl-stat-active' : ''; ?>">
            <span class="scl-stat-num"><?php echo esc_html($counts['total']); ?></span>
            <span class="scl-stat-label">Total</span>
        </a>
        <a href="<?php echo esc_url($filter_url(['statut' => 'classe'])); ?>" class="scl-stat-card scl-stat-green<?php echo $statut === 'classe' ? ' scl-stat-active' : ''; ?>">
            <span class="scl-stat-num"><?php echo esc_html($counts['classes']); ?></span>
            <span class="scl-stat-label">Classés</span>
        </a>
        <a href="<?php echo esc_url($filter_url(['statut' => 'non_classe'])); ?>" class="scl-stat-card<?php echo $statut === 'non_classe' ? ' scl-stat-active' : ''; ?>">
            <span class="scl-stat-num" style="color:#64748b;"><?php echo esc_html($counts['non_classes']); ?></span>
            <span class="scl-stat-label">Non classés</span>
        </a>
        <?php if (!empty($counts['suggestions'])) : ?>
        <div class="scl-stat-card" style="cursor:default;">
            <span class="scl-stat-num" style="color:#92400e;"><?php echo esc_html($counts['suggestions']); ?></span>
            <span class="scl-stat-label">Suggestions en attente</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="scl-prefix-pills">
        <a href="<?php echo esc_url($filter_url(['prefix' => ''])); ?>" class="scl-prefix-tab<?php echo $prefix === '' ? ' current' : ''; ?>">
            Tous <span class="scl-tab-count">(<?php echo esc_html($counts['total']); ?>)</span>
        </a>
        <?php foreach ($prefixes as $pfx => $count) : ?>
        <a href="<?php echo esc_url($filter_url(['prefix' => $pfx])); ?>" class="scl-prefix-tab<?php echo $prefix === $pfx ? ' current' : ''; ?>">
            <?php echo esc_html($pfx); ?> <span class="scl-tab-count">(<?php echo (int) $count; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="scl-toolbar">
        <select id="scl-batch-provider">
            <option value="claude">Claude AI</option>
            <option value="openai">ChatGPT</option>
        </select>
        <button type="button" id="scl-btn-batch-ia" class="button button-primary">
            <span class="dashicons dashicons-superhero" style="vertical-align:middle;margin-top:2px;"></span>
            Classer la sélection en lot
        </button>
        <button type="button" id="scl-btn-select-all" class="button">Tout sélectionner</button>
        <span id="scl-batch-feedback" style="display:none;font-weight:600;margin-left:8px;"></span>
    </div>

    <?php if (empty($rows)) : ?>
        <p class="scl-empty">Aucun article indexé (valide) à afficher pour ce filtre.</p>
    <?php else : ?>
    <table class="widefat scl-table" id="scl-articles-table">
        <thead>
            <tr>
                <td class="check-column" style="width:32px;"><input type="checkbox" id="scl-check-all"></td>
                <th>Article</th>
                <th style="width:16%;">Thème indexé</th>
                <th style="width:16%;">Parcours indexé</th>
                <th style="width:12%;">Série indexée</th>
                <th style="width:110px;">Statut</th>
                <th style="width:140px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) :
                $post_id     = (int) $row['post_id'];
                $classe      = ($row['statut_classement'] ?? 'non_classe') === 'classe';
                $has_suggestion = !empty($row['has_suggestion']);
            ?>
            <tr data-post-id="<?php echo esc_attr($post_id); ?>">
                <td class="check-column"><input type="checkbox" class="scl-row-check" value="<?php echo esc_attr($post_id); ?>"></td>
                <td><strong><?php echo esc_html($row['titre']); ?></strong></td>
                <td><?php echo esc_html($row['theme_principal']); ?></td>
                <td><?php echo esc_html($row['parcours']); ?></td>
                <td><?php echo esc_html($row['serie']); ?></td>
                <td>
                    <?php if ($classe) : ?>
                        <span class="scl-badge scl-badge-green">Classé</span>
                    <?php elseif ($has_suggestion) : ?>
                        <span class="scl-badge scl-badge-orange" title="Une suggestion IA a ete generee (classement en lot) et attend votre validation.">Suggestion prête</span>
                    <?php else : ?>
                        <span class="scl-badge scl-badge-grey">Non classé</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo esc_url(add_query_arg(['tab' => 'validation', 'post_id' => $post_id], $base_url)); ?>" class="button button-small scl-action-link<?php echo $has_suggestion && !$classe ? ' button-primary' : ''; ?>">
                        <?php echo ($has_suggestion && !$classe) ? 'Valider' : 'Classer'; ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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
window.sclData = {
    nonce:   '<?php echo esc_js(wp_create_nonce('schilo_classement')); ?>',
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
};
</script>
