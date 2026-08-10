<?php
/**
 * Suite de tests "unitaires" pour ArticleVersionService — regles des
 * versions multiples (grand public / academique / ...).
 *
 * Pas de PHPUnit dans ce projet (theme WordPress classique, pas
 * d'environnement wp-tests installe) : ce script suit la convention deja
 * utilisee dans inc/builder/migration-scripts/, execute via WP-CLI contre
 * une vraie base (articles jetables crees et supprimes a chaque execution,
 * aucune donnee reelle touchee) :
 *
 *   wp eval-file inc/builder/tests/test-article-version-service.php --path=<chemin-wp>
 *
 * Couvre :
 *  - liaison de base (labels, primaire, symetrie des liens)
 *  - decochage en cascade (retrait du dernier lien / desactivation)
 *  - echange automatique de type a 2 et 3 membres (resolveLabelConflicts)
 *  - creation d'un nouveau type a la volee (addAvailableLabel), utilise
 *    ensuite pour lier un 3e article — le "processus complet" cote metier ;
 *    le declenchement de la popup "tous les types sont pris" et l'echange
 *    visible en direct restent du JS pur (builder-admin.js), verifies
 *    manuellement dans le navigateur, non couverts ici.
 *  - protection contre la suppression d'un type encore utilise
 *    (getUsedLabels(), meme logique que VersionSettingsPage::renderPage()).
 *
 * Ne modifie aucun reglage/donnee reelle : les types crees pour les tests
 * sont retires de la liste globale a la fin, meme en cas d'echec (cleanup
 * dans un finally-like via register_shutdown au besoin — ici simplement en
 * fin de script, le jeu de donnees etant entierement jetable).
 */

if (!defined('ABSPATH')) {
    exit;
}

$svc = new \Schilo\Builder\Service\ArticleVersionService();

$results = array();
$createdPosts = array();
$originalLabels = $svc->getAvailableLabels();

function schilo_test_assert(array &$results, $description, $actual, $expected)
{
    $pass = $actual === $expected;
    $results[] = array(
        'pass' => $pass,
        'description' => $description,
        'expected' => $expected,
        'actual' => $actual,
    );
}

function schilo_test_make_post(array &$createdPosts, $title)
{
    $id = wp_insert_post(array(
        'post_title' => $title,
        'post_type' => 'post',
        'post_status' => 'publish',
    ));
    $createdPosts[] = $id;
    return $id;
}

// ── 1. Liaison de base : labels, primaire, symetrie ────────────────────────

$a = schilo_test_make_post($createdPosts, 'TEST_UNIT_A');
$b = schilo_test_make_post($createdPosts, 'TEST_UNIT_B');

$svc->saveVersion($a, true, array($b), 'Grand public', true, array($b => 'Académique'));

schilo_test_assert($results, '1.1 Label de A apres liaison', $svc->getLabel($a), 'Grand public');
schilo_test_assert($results, '1.2 Label de B apres liaison', $svc->getLabel($b), 'Académique');
schilo_test_assert($results, '1.3 A est marque principal', $svc->isPrimary($a), true);
schilo_test_assert($results, '1.4 B n\'est pas principal', $svc->isPrimary($b), false);
schilo_test_assert($results, '1.5 Lien symetrique (B pointe vers A)', $svc->getLinkedIds($b), array($a));

// ── 2. Decochage en cascade : retrait du dernier lien ───────────────────────

$svc->saveVersion($a, true, array(), 'Grand public', true);

schilo_test_assert($results, '2.1 A decoche (plus aucun lien)', $svc->isEnabled($a), false);
schilo_test_assert($results, '2.2 B decoche en cascade', $svc->isEnabled($b), false);
schilo_test_assert($results, '2.3 Label de B nettoye', $svc->getLabel($b), '');

// Relier a nouveau pour la suite des tests.
$svc->saveVersion($a, true, array($b), 'Grand public', true, array($b => 'Académique'));

// ── 3. Desactivation explicite ─────────────────────────────────────────────

$svc->saveVersion($b, false, array(), '', false);

schilo_test_assert($results, '3.1 B desactive explicitement', $svc->isEnabled($b), false);
schilo_test_assert($results, '3.2 A decoche en cascade (plus de lien)', $svc->isEnabled($a), false);

// Relier a nouveau.
$svc->saveVersion($a, true, array($b), 'Grand public', true, array($b => 'Académique'));

// ── 4. Echange automatique de type — groupe a 2 membres ────────────────────

// Depuis l'ecran de B : B passe en "Grand public" (deja pris par A, non
// touche explicitement sur cette sauvegarde) -> A doit heriter de
// l'ancien type de B ("Académique").
$svc->saveVersion($b, true, array($a), 'Grand public', false);

schilo_test_assert($results, '4.1 B passe bien a Grand public', $svc->getLabel($b), 'Grand public');
schilo_test_assert($results, '4.2 A recupere automatiquement Académique', $svc->getLabel($a), 'Académique');

// Remise a plat pour le test suivant.
$svc->saveVersion($a, true, array($b), 'Grand public', true, array($b => 'Académique'));

// ── 5. Echange automatique de type — groupe a 3 membres ────────────────────

$c = schilo_test_make_post($createdPosts, 'TEST_UNIT_C');
if (!in_array('Intermédiaire', $svc->getAvailableLabels(), true)) {
    $svc->addAvailableLabel('Intermédiaire');
}

$svc->saveVersion($a, true, array($b, $c), 'Grand public', true, array(
    $b => 'Académique',
    $c => 'Intermédiaire',
));

// Depuis l'ecran de A : C passe en "Académique" (deja pris par B), A et sa
// propre valeur ne changent pas explicitement -> seul B doit se decaler
// vers l'ancien type de C ("Intermédiaire"), A doit rester intact.
$svc->saveVersion($a, true, array($b, $c), 'Grand public', true, array(
    $b => 'Académique',
    $c => 'Académique',
));

schilo_test_assert($results, '5.1 A reste inchange (Grand public)', $svc->getLabel($a), 'Grand public');
schilo_test_assert($results, '5.2 B decale vers l\'ancien type de C (Intermédiaire)', $svc->getLabel($b), 'Intermédiaire');
schilo_test_assert($results, '5.3 C prend bien Académique', $svc->getLabel($c), 'Académique');

// ── 6. Creation d'un nouveau type + liaison d'un 4e article ─────────────────
// (processus complet equivalent a la popup "tous les types sont pris" :
// le JS appelle addAvailableLabel() via AJAX puis relie l'article avec ce
// nouveau type — on verifie ici la partie service qui fait le travail reel)

$labelsBefore = $svc->getAvailableLabels();
$svc->addAvailableLabel('Jeunesse Unit Test');
$labelsAfterFirstAdd = $svc->getAvailableLabels();
$svc->addAvailableLabel('Jeunesse Unit Test'); // doublon volontaire
$labelsAfterDuplicateAdd = $svc->getAvailableLabels();

schilo_test_assert($results, '6.1 Nouveau type ajoute une seule fois', count($labelsAfterFirstAdd) - count($labelsBefore), 1);
schilo_test_assert($results, '6.2 Ajout en double sans effet (pas de doublon)', $labelsAfterDuplicateAdd, $labelsAfterFirstAdd);

$d = schilo_test_make_post($createdPosts, 'TEST_UNIT_D');
$svc->saveVersion($a, true, array($b, $c, $d), 'Grand public', true, array(
    $b => 'Académique',
    $c => 'Intermédiaire',
    $d => 'Jeunesse Unit Test',
));

schilo_test_assert($results, '6.3 D lie avec le nouveau type', $svc->getLabel($d), 'Jeunesse Unit Test');
schilo_test_assert($results, '6.4 D fait bien partie du groupe de A', in_array($d, $svc->getLinkedIds($a), true), true);

// ── 7. getUsedLabels() reflete l'usage reel ─────────────────────────────────

$usedNow = $svc->getUsedLabels();
schilo_test_assert($results, '7.1 "Jeunesse Unit Test" detecte comme utilise', in_array('Jeunesse Unit Test', $usedNow, true), true);

// D quitte le groupe (retrait de son lien) -> son label doit disparaitre de
// getUsedLabels() (plus aucun article ne l'utilise).
$svc->saveVersion($a, true, array($b, $c), 'Grand public', true, array(
    $b => 'Académique',
    $c => 'Intermédiaire',
));

$usedAfterRemoval = $svc->getUsedLabels();
schilo_test_assert($results, '7.2 D decoche (retire du groupe)', $svc->isEnabled($d), false);
schilo_test_assert($results, '7.3 "Jeunesse Unit Test" n\'est plus utilise', in_array('Jeunesse Unit Test', $usedAfterRemoval, true), false);

// ── 8. Protection contre la suppression d'un type utilise ─────────────────
// (meme logique que VersionSettingsPage::renderPage() : un type retire de
// la liste soumise mais toujours present dans getUsedLabels() bloque la
// sauvegarde cote page de reglages)

$currentLabels = $svc->getAvailableLabels(); // contient encore "Académique", utilise par B/C
$attemptedNewLabels = array_values(array_diff($currentLabels, array('Académique'))); // on tente de le retirer
$removed = array_diff($currentLabels, $attemptedNewLabels);
$blocked = array_values(array_intersect($removed, $svc->getUsedLabels()));

schilo_test_assert($results, '8.1 Suppression de "Académique" (encore utilise) detectee comme bloquee', $blocked, array('Académique'));

// À l'inverse, un type jamais utilise doit pouvoir etre retire librement.
$svc->addAvailableLabel('Type Jamais Utilise');
$currentLabels2 = $svc->getAvailableLabels();
$attemptedNewLabels2 = array_values(array_diff($currentLabels2, array('Type Jamais Utilise')));
$removed2 = array_diff($currentLabels2, $attemptedNewLabels2);
$blocked2 = array_values(array_intersect($removed2, $svc->getUsedLabels()));

schilo_test_assert($results, '8.2 Suppression d\'un type non utilise autorisee (rien de bloque)', $blocked2, array());

// ── Nettoyage ───────────────────────────────────────────────────────────────

foreach ($createdPosts as $postId) {
    wp_delete_post($postId, true);
}
$svc->saveAvailableLabels($originalLabels);

// ── Rapport ─────────────────────────────────────────────────────────────────

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
echo "Nettoyage effectué (" . count($createdPosts) . " articles jetables supprimés, types de version restaurés).\n";
