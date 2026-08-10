<?php
/**
 * Tests pour ArticleTitleNumberer — numérotation manuelle des articles.
 *
 * wp eval-file inc/builder/tests/test-article-title-numberer.php --path=<chemin-wp>
 *
 * Couvre :
 *  - un numéro "en avance" sur la séquence (ex. PAR101 alors que le
 *    dernier existant est PAR001) est conservé tel quel, plus renumeroté
 *    vers le prochain numéro logique.
 *  - un numéro déjà utilisé par un AUTRE article est refusé (titre
 *    inchangé) et déclenche un avertissement pour l'utilisateur courant.
 *  - un préfixe sans numéro continue d'obtenir le prochain numéro
 *    disponible (comportement volontairement conservé).
 *
 * filterPostData()/normalizeTitle() sont appelées directement (pas via le
 * hook wp_insert_post_data, gate par is_admin() dans Plugin.php) : ce test
 * exerce donc la logique metier isolement, comme le ferait le hook réel.
 */

if (!defined('ABSPATH')) {
    exit;
}

$numberer = new \Schilo\Builder\Service\ArticleTitleNumberer();
$results = array();
$createdPosts = array();

function schilo_numberer_test_assert(array &$results, $description, $actual, $expected)
{
    $pass = $actual === $expected;
    $results[] = array('pass' => $pass, 'description' => $description, 'expected' => $expected, 'actual' => $actual);
}

// wp_set_current_user requis pour le transient d'avertissement (keyé par user).
$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
if (!empty($admins)) {
    wp_set_current_user((int) $admins[0]);
}

// ── 1. Numéro "en avance" sur la séquence : conservé tel quel ──────────────

$a = wp_insert_post(array('post_title' => 'PAR001 - Titre A', 'post_type' => 'post', 'post_status' => 'publish'));
$createdPosts[] = $a;

$dataB = $numberer->filterPostData(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'PAR101 - Titre B'), array('ID' => 0));
schilo_numberer_test_assert($results, '1.1 PAR101 conservé tel quel (pas ramené a PAR002)', $dataB['post_title'], 'PAR101 - Titre B');

$b = wp_insert_post(array('post_title' => $dataB['post_title'], 'post_type' => 'post', 'post_status' => 'publish'));
$createdPosts[] = $b;

// ── 2. Doublon : refusé, titre inchangé + avertissement enregistré ─────────

delete_transient('schilo_dup_number_' . get_current_user_id());

$dataC = $numberer->filterPostData(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'PAR101 - Titre C (doublon)'), array('ID' => 0));
schilo_numberer_test_assert($results, '2.1 Doublon PAR101 refusé (titre non modifié par le filtre)', $dataC['post_title'], 'PAR101 - Titre C (doublon)');

$warning = get_transient('schilo_dup_number_' . get_current_user_id());
schilo_numberer_test_assert($results, '2.2 Avertissement de doublon enregistré', is_array($warning) && $warning['number'] === 101, true);
schilo_numberer_test_assert($results, '2.3 Avertissement pointe vers le bon article en conflit', is_array($warning) ? (int) $warning['conflict_id'] : null, (int) $b);

delete_transient('schilo_dup_number_' . get_current_user_id());

// ── 3. Préfixe sans numéro : prochain numéro disponible (inchangé) ─────────

// getNextAvailableNumberForPrefix() se base sur le MAX existant (comportement
// préexistant, non modifié ici) : PAR101 étant le plus haut numéro utilisé,
// le prochain numéro logique est PAR102, pas un numéro "de la bande 0xx".
$dataD = $numberer->filterPostData(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'PAR - Titre D'), array('ID' => 0));
schilo_numberer_test_assert($results, '3.1 Préfixe sans numéro -> prochain numéro logique (max existant + 1)', $dataD['post_title'], 'PAR102 - Titre D');

// ── 4. Modifier un article existant vers un numéro déjà pris par un autre ──

$dataAtoConflict = $numberer->filterPostData(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'PAR101 - Titre A renommé'), array('ID' => $a));
schilo_numberer_test_assert($results, '4.1 Renommage de A vers un numéro pris par B refusé', $dataAtoConflict['post_title'], 'PAR101 - Titre A renommé');

// Mais A peut toujours choisir un numéro libre "en avance" (ex. 250) sans y être forcé.
$dataAtoFree = $numberer->filterPostData(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'PAR250 - Titre A renommé'), array('ID' => $a));
schilo_numberer_test_assert($results, '4.2 Renommage de A vers un numéro libre "en avance" (PAR250) accepté', $dataAtoFree['post_title'], 'PAR250 - Titre A renommé');

// ── Nettoyage ────────────────────────────────────────────────────────────

foreach ($createdPosts as $postId) {
    wp_delete_post($postId, true);
}
delete_transient('schilo_dup_number_' . get_current_user_id());

// ── Rapport ──────────────────────────────────────────────────────────────

$failures = array_filter($results, function ($r) { return !$r['pass']; });

echo "\n=== Résultats (" . count($results) . " assertions) ===\n";
foreach ($results as $r) {
    $status = $r['pass'] ? 'OK  ' : 'FAIL';
    echo "[{$status}] {$r['description']}\n";
    if (!$r['pass']) {
        echo '        attendu: ' . var_export($r['expected'], true) . "\n";
        echo '        obtenu : ' . var_export($r['actual'], true) . "\n";
    }
}

echo "\n" . (empty($failures) ? 'TOUS LES TESTS PASSENT (' . count($results) . '/' . count($results) . ')' : count($failures) . ' TEST(S) EN ECHEC sur ' . count($results)) . "\n";
echo "Nettoyage effectué (" . count($createdPosts) . " articles jetables supprimés).\n";
