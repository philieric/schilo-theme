<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'schilo_indexation';

// --- Prefixes disponibles ---
$prefix_rows = $wpdb->get_results(
    "SELECT LEFT(post_title,3) as pfx, COUNT(*) as n
     FROM {$wpdb->posts}
     WHERE post_type='post' AND post_status IN ('publish','draft')
       AND post_title REGEXP '^[A-Z]{3}'
     GROUP BY pfx
     ORDER BY pfx ASC",
    ARRAY_A
);
$prefixes = [];
foreach ($prefix_rows as $r) {
    $prefixes[$r['pfx']] = (int) $r['n'];
}

// --- Compteurs par statut indexation ---
$stat_counts = [];
$stat_rows = $wpdb->get_results("SELECT statut_indexation, COUNT(*) as n FROM {$table} GROUP BY statut_indexation", ARRAY_A);
foreach ($stat_rows as $r) $stat_counts[$r['statut_indexation']] = (int)$r['n'];
$total_posts   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status IN ('publish','draft')");
$total_indexed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$total_valides = $stat_counts['valide'] ?? 0;
$total_attente = $stat_counts['en_attente'] ?? 0;
$non_indexes   = $total_posts - $total_indexed;

$ia_config    = get_option('schilo_ia_config', []);
$default_prov = get_option('schilo_indexation_default_provider', 'claude');
?>
<div class="wrap schilo-builder-settings" id="sia-wrap">

    <h1 class="sia-page-title">
        <span class="dashicons dashicons-search" style="color:#6d3fc0;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Indexation des articles
        <span style="font-size:14px;font-weight:400;color:#64748b;margin-left:10px;"><?php echo esc_html($total_posts); ?> articles</span>
    </h1>

    <?php if (empty($ia_config['claude']['api_key']) && empty($ia_config['openai']['api_key'])) : ?>
    <div class="notice notice-warning is-dismissible"><p>
        <strong>IA non configuree.</strong>
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-ia')); ?>">Configurez les APIs IA</a> pour indexer automatiquement.
    </p></div>
    <?php endif; ?>

    <!-- Stats rapides -->
    <div class="sia-stats-row">
        <div class="sia-stat-card sia-stat-filter sia-stat-active" data-statut="">
            <span class="sia-stat-num"><?php echo $total_posts; ?></span>
            <span class="sia-stat-label">Total</span>
        </div>
        <div class="sia-stat-card sia-stat-filter sia-stat-green" data-statut="valide">
            <span class="sia-stat-num"><?php echo $total_valides; ?></span>
            <span class="sia-stat-label">Valides</span>
        </div>
        <div class="sia-stat-card sia-stat-filter sia-stat-orange" data-statut="en_attente">
            <span class="sia-stat-num"><?php echo $total_attente; ?></span>
            <span class="sia-stat-label">En attente</span>
        </div>
        <div class="sia-stat-card sia-stat-filter" data-statut="non_indexe">
            <span class="sia-stat-num" style="color:#64748b;"><?php echo $non_indexes; ?></span>
            <span class="sia-stat-label">Non indexes</span>
        </div>
    </div>

    <!-- Onglets prefixes — style pills -->
    <div class="sia-prefix-pills" id="sia-prefix-tabs">
        <a href="#" class="sia-prefix-tab current" data-prefix="">
            Tous <span class="sia-tab-count">(<?php echo $total_posts; ?>)</span>
        </a>
        <?php foreach ($prefixes as $pfx => $count) : ?>
        <a href="#" class="sia-prefix-tab" data-prefix="<?php echo esc_attr($pfx); ?>">
            <?php echo esc_html($pfx); ?> <span class="sia-tab-count">(<?php echo $count; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar compact -->
    <div class="sia-toolbar" id="sia-toolbar">
        <input type="search" id="sia-search" placeholder="Rechercher un article..." value="">
        <select id="sia-indexed-filter" title="Filtrer selon l'etat d'indexation">
            <option value="">Tous les articles</option>
            <option value="non_indexe">Masquer les articles deja indexes</option>
            <option value="indexe">Afficher seulement les articles indexes</option>
        </select>
        <select id="sia-provider-select">
            <option value="claude" <?php selected($default_prov, 'claude'); ?>>Claude AI</option>
            <option value="openai" <?php selected($default_prov, 'openai'); ?>>ChatGPT</option>
        </select>
        <div class="sia-toolbar-actions">
            <button type="button" id="sia-btn-batch-ia" class="button button-primary">
                <span class="dashicons dashicons-superhero" style="font-size:15px;height:15px;width:15px;vertical-align:middle;margin-right:3px;"></span>
                Indexer la selection
            </button>
            <button type="button" id="sia-btn-select-all" class="button">Tout selectionner</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-indexation&tab=config')); ?>" class="button" title="Configuration">
                <span class="dashicons dashicons-admin-settings" style="font-size:15px;height:15px;width:15px;vertical-align:middle;"></span>
            </a>
        </div>
    </div>

    <!-- Tableau AJAX -->
    <form id="sia-list-form">
    <table class="widefat sia-table" id="sia-articles-table">
        <thead>
            <tr>
                <td class="manage-column check-column"><input type="checkbox" id="sia-check-all"></td>
                <th class="manage-column column-title">Article</th>
                <th class="manage-column" style="width:58px;">Prefixe</th>
                <th class="manage-column" style="width:108px;">Statut</th>
                <th class="manage-column" style="width:85px;">Source</th>
                <th class="manage-column" style="width:100px;">Date</th>
                <th class="manage-column" style="width:185px;">Actions</th>
            </tr>
        </thead>
        <tbody id="sia-tbody">
            <tr><td colspan="7" style="text-align:center;padding:30px;color:#64748b;">Chargement...</td></tr>
        </tbody>
    </table>
    </form>

    <!-- Pagination -->
    <div id="sia-pagination" class="tablenav bottom" style="display:none;">
        <div class="tablenav-pages" style="display:flex;align-items:center;gap:8px;float:right;">
            <span id="sia-page-info" style="font-size:13px;color:#64748b;"></span>
            <span id="sia-page-buttons"></span>
        </div>
        <br class="clear">
    </div>

    <!-- Modal IA -->
    <div id="sia-ia-modal" style="display:none;" class="sia-modal-overlay">
        <div class="sia-modal">
            <div class="sia-modal-header">
                <strong id="sia-modal-title">Resultats IA — Validation requise</strong>
                <button type="button" class="sia-modal-close">&times;</button>
            </div>
            <div class="sia-modal-body">
                <p class="sia-modal-info">Verifiez et corrigez chaque champ avant de valider. Rien n'est enregistre sans votre confirmation.</p>
                <div id="sia-ia-fields"></div>
                <input type="hidden" id="sia-current-post-id" value="">
            </div>
            <div class="sia-modal-footer">
                <button type="button" id="sia-btn-validate" class="button button-primary">
                    <span class="dashicons dashicons-yes" style="vertical-align:middle;"></span> Valider et enregistrer
                </button>
                <button type="button" id="sia-btn-reject" class="button">Rejeter</button>
                <button type="button" class="sia-modal-close button">Annuler</button>
            </div>
        </div>
    </div>

    <!-- Modal XML -->
    <div id="sia-xml-modal" style="display:none;" class="sia-modal-overlay">
        <div class="sia-modal sia-modal-wide">
            <div class="sia-modal-header">
                <strong>Template XML — a copier dans votre IA</strong>
                <button type="button" class="sia-xml-close">&times;</button>
            </div>
            <div class="sia-modal-body">
                <p style="font-size:13px;color:#64748b;margin-bottom:8px;">Cliquez <strong>Copier pour l'IA</strong> puis collez directement dans Claude ou ChatGPT. Les instructions et le contenu de l'article sont inclus automatiquement.</p>
                <details style="margin-bottom:10px;">
                    <summary style="font-size:12px;font-weight:600;cursor:pointer;color:#6d3fc0;">Voir le message complet envoy&eacute; &agrave; l'IA</summary>
                    <textarea id="sia-ia-instructions" style="width:100%;height:160px;font-family:monospace;font-size:11px;margin-top:6px;background:#f8fafc;" readonly></textarea>
                </details>
                <textarea id="sia-xml-content" style="width:100%;height:220px;font-family:monospace;font-size:11px;" readonly></textarea>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="sia-btn-copy-xml" class="button button-primary">Copier pour l'IA</button>
                    <button type="button" id="sia-btn-show-import" class="button button-primary">Importer une reponse IA</button>
                </div>
                <div id="sia-import-zone" style="display:none;margin-top:12px;border-top:1px solid #e2e8f0;padding-top:12px;">
                    <p style="font-weight:600;margin-bottom:6px;">Collez ici la reponse de l'IA (XML ou JSON) :</p>
                    <textarea id="sia-import-content" style="width:100%;height:180px;font-family:monospace;font-size:11px;"></textarea>
                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                        <select id="sia-import-format">
                            <option value="xml">XML</option>
                            <option value="json">JSON</option>
                        </select>
                        <button type="button" id="sia-btn-do-import" class="button button-primary">Analyser et valider</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Spinner -->
    <div id="sia-spinner-overlay" style="display:none;" class="sia-spinner-overlay">
        <div class="sia-spinner-box">
            <span class="spinner is-active" style="float:none;margin:0;"></span>
            <span id="sia-spinner-text">Traitement en cours...</span>
        </div>
    </div>
</div>

<script>
window.siaData = {
    nonce:   '<?php echo esc_js(wp_create_nonce('schilo_indexation')); ?>',
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    valUrl:  '<?php echo esc_js(admin_url('admin.php?page=schilo-builder-indexation&tab=validation&post_id=')); ?>',
    editUrl: '<?php echo esc_js(admin_url('post.php?post=__ID__&action=edit')); ?>',
    viewUrl: '<?php echo esc_js(home_url('/?p=__ID__')); ?>',
};

/* ---- DEBUG PANEL ---- */
(function() {
    var nonce   = window.siaData ? window.siaData.nonce : '(vide)';
    var ajaxUrl = window.siaData ? window.siaData.ajaxUrl : '(vide)';
    var jqOk    = typeof jQuery !== 'undefined' ? 'OK v' + jQuery.fn.jquery : 'ABSENT';

    var panel = document.getElementById('sia-debug-panel');
    if (!panel) return;

    panel.innerHTML =
        '<strong>jQuery :</strong> ' + jqOk + ' &nbsp;|&nbsp; ' +
        '<strong>Nonce :</strong> ' + nonce.substring(0, 10) + '... &nbsp;|&nbsp; ' +
        '<strong>Ajax URL :</strong> ' + ajaxUrl + ' &nbsp;|&nbsp; ' +
        '<button type="button" id="sia-debug-ping" class="button button-small">Test AJAX ping</button>' +
        ' <span id="sia-debug-result" style="margin-left:8px;font-weight:600;"></span>';

    document.getElementById('sia-debug-ping').addEventListener('click', function() {
        var res = document.getElementById('sia-debug-result');
        res.style.color = '#64748b';
        res.textContent = 'Envoi...';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.timeout = 10000;
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    res.style.color = '#059669';
                    res.textContent = 'OK — action AJAX repond correctement';
                } else {
                    res.style.color = '#d97706';
                    res.textContent = 'AJAX repond mais erreur : ' + JSON.stringify(data.data);
                }
            } catch(e) {
                res.style.color = '#dc2626';
                res.textContent = 'Reponse non-JSON : [' + xhr.responseText.substring(0, 60) + ']';
            }
        };
        xhr.onerror = function() { res.style.color = '#dc2626'; res.textContent = 'Erreur reseau'; };
        xhr.ontimeout = function() { res.style.color = '#dc2626'; res.textContent = 'Timeout'; };
        xhr.send('action=schilo_indexation_get_articles&nonce=' + encodeURIComponent(nonce) + '&paged=1&per_page=1');
    });
})();
</script>

<div id="sia-debug-panel" style="margin:8px 0 12px;padding:8px 12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;font-size:12px;color:#0c4a6e;">
    Chargement debug...
</div>
