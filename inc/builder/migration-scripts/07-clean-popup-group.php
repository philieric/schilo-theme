<?php
/**
 * SCRIPT 7 : Nettoyage des blocs [popup_group]...[/popup_group] hérités de
 * l'ancien plugin de popups Wikilogy, encore présents en texte brut dans le
 * contenu de nombreux articles migrés (visible tel quel sur le front, plugin
 * désinstallé).
 *
 * Chaque [popup_post id="X" text="Y"] à l'intérieur du bloc référence un
 * article INF (déjà migré) dont le système de définitions contextuelles
 * (ContextualDefinitionService/ContextualDefinitionRenderer) sait détecter et
 * rendre cliquable une phrase déclencheuse dérivée du titre de l'INF. Ce
 * script remplace le bloc par une liste de phrases en texte simple (une par
 * ligne), en ajustant si besoin la ponctuation INTERNE (deux-points, virgule,
 * tiret hors apostrophe) qui empêcherait la correspondance exacte avec le
 * déclencheur — jamais le texte hors du bloc, jamais les apostrophes.
 *
 * Traite à la fois `_schilo_builder_sections` (source réelle du rendu) et
 * `post_content` (colonne cœur WP : extraits, recherche, flux RSS).
 *
 * DRY-RUN PAR DÉFAUT : n'écrit rien tant que `apply=1` n'est pas passé.
 * Idempotent : un article déjà nettoyé n'a plus de [popup_group] à traiter.
 *
 * Usage :
 *   ~/bin/wp eval-file .../07-clean-popup-group.php            (simulation)
 *   ~/bin/wp eval-file .../07-clean-popup-group.php apply=1    (exécution)
 */

if (!function_exists('update_option')) {
    define('WP_USE_THEMES', false);
    require 'wp-load.php';
}

$apply = (isset($_GET['apply']) && $_GET['apply']);
// `wp eval-file <file> <arg>...` expose les arguments via $args (pas $argv).
foreach ((array) ($args ?? $argv ?? array()) as $arg) {
    if ($arg === 'apply=1' || $arg === 'apply') {
        $apply = true;
    }
}

global $wpdb;

$service  = new \Schilo\Builder\Service\ContextualDefinitionService();
$settings = $service->getSettings();

/**
 * Termes déclencheurs pour un post donné (override curé si présent et actif,
 * sinon dérivés du titre) — même priorité que
 * ContextualDefinitionService::getDefinitions().
 */
$getTriggerTerms = function (int $postId, string $title) use ($settings, $service): array {
    $override = $settings['definitions'][$postId] ?? null;
    if (is_array($override)) {
        if (empty($override['enabled'])) {
            return array(); // définition désactivée par l'admin : ne matchera jamais.
        }
        if (!empty($override['terms'])) {
            return (array) $override['terms'];
        }
    }
    return $service->deriveTerms($title);
};

/** Reproduit exactement la construction du pattern de ContextualDefinitionRenderer::enhance(). */
$buildPattern = function (string $term): string {
    $quoted = preg_quote($term, '/');
    $quoted = str_replace("'", "['’]", $quoted);
    $quoted = str_replace('\\ ', '[\s\p{P}]+', $quoted);
    return '/(?<![\pL\pN])(' . $quoted . ')(?![\pL\pN])/iu';
};

/**
 * Répare la ponctuation qui empêche le texte de matcher son déclencheur, en
 * reproduisant la normalisation de ContextualDefinitionService::normalizeTrigger()
 * (toute ponctuation -> un espace, espaces multiples réduits) mais SANS changer
 * la casse ni retirer l'article de tête, et en protégeant les apostrophes
 * (droites/courbes) qui doivent rester intactes pour l'appariement.
 */
$fixInternalPunct = function (string $text): string {
    $placeholder = "\x01APOS\x01";
    $t = str_replace(array("'", "’"), $placeholder, $text);
    $t = preg_replace('/[\p{P}\p{S}]+/u', ' ', $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    $t = str_replace($placeholder, "'", $t);
    return trim($t);
};

$testMatch = function (string $text, array $terms) use ($buildPattern): bool {
    foreach ($terms as $term) {
        if ($term !== '' && preg_match($buildPattern($term), $text)) {
            return true;
        }
    }
    return false;
};

$pattern = '/\[popup_group\](.*?)\[\/popup_group\]/us';
$popupPostPattern = '/\[popup_post\s+id="(\d+)"\s+text="([^"]*)"\]/u';

// Articles publiés dont la section OU le post_content contient encore le bloc.
$rows = $wpdb->get_results("
    SELECT DISTINCT p.ID, p.post_title
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_schilo_builder_sections'
    WHERE p.post_type = 'post' AND p.post_status = 'publish'
      AND (p.post_content LIKE '%popup_group%' OR pm.meta_value LIKE '%popup_group%')
    ORDER BY p.ID
");

echo '=== Nettoyage [popup_group] ' . ($apply ? '(EXÉCUTION)' : '(SIMULATION)') . " ===\n";
echo count($rows) . " article(s) concerné(s).\n\n";

$totalArticles = 0;
$totalLines    = 0;
$totalUnmatched = 0;

foreach ($rows as $row) {
    $postId = (int) $row->ID;
    $unmatchedHere = array();

    // Construit UNE FOIS la fonction de remplacement du bloc pour cet article
    // (le même bloc, donc le même texte de remplacement, s'applique aux deux
    // champs s'ils le contiennent tous les deux).
    $buildReplacement = function (string $block) use ($getTriggerTerms, $testMatch, $fixInternalPunct, $popupPostPattern, &$unmatchedHere): string {
        if (!preg_match_all($popupPostPattern, $block, $matches, PREG_SET_ORDER)) {
            return $block; // pas de popup_post reconnu à l'intérieur : on laisse tel quel.
        }
        $lines = array();
        foreach ($matches as $m) {
            $refId = (int) $m[1];
            $text  = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') continue;

            $refPost = get_post($refId);
            $terms   = ($refPost && $refPost->post_status === 'publish')
                ? $getTriggerTerms($refId, $refPost->post_title)
                : array();

            $final = $text;
            if ($terms) {
                if (!$testMatch($text, $terms)) {
                    $fixed = $fixInternalPunct($text);
                    if ($testMatch($fixed, $terms)) {
                        $final = $fixed;
                    } else {
                        $unmatchedHere["{$refId}|{$text}"] = "#{$refId} : \"{$text}\" (déclencheur: " . implode(' | ', $terms) . ')';
                    }
                }
            } else {
                $unmatchedHere["{$refId}|{$text}"] = "#{$refId} introuvable/dépublié/désactivé : \"{$text}\"";
            }
            $lines[] = $final;
        }
        return implode("\n", $lines);
    };

    // get_post_meta direct sections
    $secs = get_post_meta($postId, '_schilo_builder_sections', true);
    $secsChanged = false;
    if (is_array($secs)) {
        foreach ($secs as $i => $s) {
            if (!empty($s['content']) && preg_match($pattern, $s['content'])) {
                $secs[$i]['content'] = preg_replace_callback($pattern, function ($m) use ($buildReplacement) {
                    return $buildReplacement($m[1]);
                }, $s['content']);
                $secsChanged = true;
            }
        }
    }

    $post = get_post($postId);
    $newPostContent = null;
    if ($post && preg_match($pattern, $post->post_content)) {
        $newPostContent = preg_replace_callback($pattern, function ($m) use ($buildReplacement) {
            return $buildReplacement($m[1]);
        }, $post->post_content);
    }

    if ($secsChanged || $newPostContent !== null) {
        $totalArticles++;
        echo "— #{$postId} {$row->post_title}\n";
        if ($secsChanged) {
            echo "   section(s) mises à jour\n";
        }
        if ($newPostContent !== null) {
            echo "   post_content mis à jour\n";
        }
        if ($unmatchedHere) {
            $totalUnmatched += count($unmatchedHere);
            foreach ($unmatchedHere as $u) {
                echo "   ⚠ NE MATCHE PAS : {$u}\n";
            }
        }

        if ($apply) {
            if ($secsChanged) {
                update_post_meta($postId, '_schilo_builder_sections', $secs);
            }
            if ($newPostContent !== null) {
                $wpdb->update($wpdb->posts, array('post_content' => $newPostContent), array('ID' => $postId));
            }
            clean_post_cache($postId);
        }
    }
}

if ($apply) {
    wp_cache_flush();
}

echo "\n=== Bilan : {$totalArticles} article(s) " . ($apply ? 'nettoyé(s)' : 'à nettoyer') . ", {$totalUnmatched} phrase(s) ne correspondant à aucun déclencheur (à revoir manuellement) ===\n";
if (!$apply) {
    echo "SIMULATION — aucune écriture effectuée. Relancer avec apply=1 pour exécuter.\n";
}
