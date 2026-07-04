<?php
/**
 * SCRIPT 3 : Migration batch WPBakery → Schilo Builder
 * Traite tous les articles non encore migrés, préfixe par préfixe.
 *
 * Usage :
 *   ?prefix=ALL         → tous les préfixes (défaut)
 *   ?prefix=PER         → un seul préfixe
 *   ?prefix=INF,MIR     → plusieurs préfixes
 *   ?dry=1              → simulation
 *   ?reset=1            → réinitialise le statut migré (reforce la migration)
 *   ?limit=50           → max articles par préfixe (debug)
 */

if (php_sapi_name() !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'], true) && !isset($_GET['token'])) {
        http_response_code(403); exit('Accès refusé.');
    }
}

define('WP_ROOT', dirname(__DIR__, 4));
require_once WP_ROOT . '/wp-load.php';

use Schilo\Builder\Service\Migration\MigrationModelService;
use Schilo\Builder\Service\Migration\MigrationApplier;
use Schilo\Builder\Service\Migration\ExtractorRegistry;
use Schilo\Builder\Service\Migration\MigrationSourceContent;

header('Content-Type: text/plain; charset=utf-8');

global $wpdb;

$dry   = isset($_GET['dry'])   && $_GET['dry']   === '1';
$reset = isset($_GET['reset']) && $_GET['reset']  === '1';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;

// Préfixes et modèles associés
$all_prefix_models = [
    'PER' => 'per_standard',
    'ANN' => 'ann_standard',
    'APO' => 'apo_standard',
    'BIB' => 'bib_standard',
    'CTD' => 'ctd_standard',
    'DAN' => 'dan_standard',
    'DOC' => 'doc_standard',
    'FDS' => 'fds_standard',
    'LGH' => 'lgh_standard',
    'PAR' => 'par_standard',
    'PDA' => 'pda_standard',
    'INF' => 'inf_standard',
    'MIR' => 'mir_standard',
    'PRB' => 'prb_standard',
];

// Sélection des préfixes à traiter
$requested = strtoupper(trim($_GET['prefix'] ?? 'ALL'));
if ($requested === 'ALL') {
    $prefixes = array_keys($all_prefix_models);
} else {
    $prefixes = array_filter(array_map('trim', explode(',', $requested)));
}

echo $dry ? "=== DRY RUN ===\n" : "=== MIGRATION RÉELLE ===\n";
echo "Préfixes : " . implode(', ', $prefixes) . "\n";
echo $reset ? "⚠ Reset statut activé\n" : "";
echo "\n";

$ms       = new MigrationModelService();
$registry = new ExtractorRegistry();
$applier  = new MigrationApplier();

$grand_total    = 0;
$grand_migrated = 0;
$grand_errors   = 0;
$start_time     = microtime(true);

foreach ($prefixes as $prefix) {
    $model_id = $all_prefix_models[$prefix] ?? null;
    if (!$model_id) {
        echo "[{$prefix}] ERREUR: aucun modèle défini\n";
        continue;
    }

    $model = $ms->getModel($model_id);
    if (!$model) {
        echo "[{$prefix}] ERREUR: modèle '{$model_id}' introuvable (lancez 02-setup-models.php)\n";
        continue;
    }

    // Réinitialiser le statut si demandé
    if ($reset && !$dry) {
        $wpdb->query($wpdb->prepare(
            "DELETE pm FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_schilo_migration_status'
               AND pm.meta_value = 'migrated'
               AND p.post_title LIKE %s",
            $prefix . '%'
        ));
    }

    $sql_limit = $limit > 0 ? "LIMIT {$limit}" : '';
    $ids = $wpdb->get_col($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts}
        WHERE post_type = 'post'
          AND post_status IN ('publish','draft')
          AND post_title LIKE %s
          AND ID NOT IN (
              SELECT post_id FROM {$wpdb->postmeta}
              WHERE meta_key = '_schilo_migration_status' AND meta_value = 'migrated'
          )
        ORDER BY post_title
        {$sql_limit}
    ", $prefix . '%'));

    $total    = count($ids);
    $migrated = 0;
    $errors   = 0;

    echo "[{$prefix}] {$total} articles à migrer ({$model_id})\n";

    if ($dry || $total === 0) {
        $grand_total += $total;
        continue;
    }

    foreach ($ids as $postId) {
        $rawContent = get_post_field('post_content', $postId);
        $source     = new MigrationSourceContent($postId, '', $rawContent);
        $elements   = $registry->extractAll($source);
        // Expansion des patterns (section_texte_content → section_texte_content_61…)
        $assignments = $ms->expandModelForElements($model, $elements);

        try {
            $applier->apply($postId, $prefix, $elements, $assignments, true);
            update_post_meta($postId, '_schilo_migration_status', 'migrated');
            $migrated++;
        } catch (\Throwable $e) {
            echo "  ERREUR [{$postId}]: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "  ✓ Migrés: {$migrated} | Erreurs: {$errors}\n";

    $grand_total    += $total;
    $grand_migrated += $migrated;
    $grand_errors   += $errors;
}

if (!$dry) {
    wp_cache_flush();
    if (function_exists('opcache_reset')) opcache_reset();
}

$elapsed = round(microtime(true) - $start_time, 1);
echo "\n=== Résumé ===\n";
echo "Total articles  : {$grand_total}\n";
echo "Migrés          : {$grand_migrated}\n";
echo "Erreurs         : {$grand_errors}\n";
echo "Durée           : {$elapsed}s\n";
echo ($dry ? "=== DRY RUN terminé ===" : "=== Migration terminée ===") . "\n";
