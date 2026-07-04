<?php
/**
 * SCRIPT 1 : Transfert CPT → post_type 'post'
 * - content (INF, MIR, PRB) → post
 * - reflexions (PDA doublons → trash, autres → post si demandé)
 * - Migre les catégories content_category → category standard
 * - Migre les catégories categorie_reflexions → category standard
 *
 * Usage : http://site.local/wp-content/plugins/schilo-builder/migration-scripts/01-transfer-cpt.php
 *         ?dry=1  → simulation sans modification
 *         ?step=all|cpt|categories|trash (défaut: all)
 */

// Sécurité : exécution CLI ou depuis localhost uniquement
if (php_sapi_name() !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'], true) && !isset($_GET['token'])) {
        http_response_code(403);
        exit('Accès refusé. Ajoutez ?token=VOTRE_TOKEN ou exécutez en CLI.');
    }
}

define('WP_ROOT', dirname(__DIR__, 4)); // remonte de plugins/schilo-builder/migration-scripts
require_once WP_ROOT . '/wp-load.php';

global $wpdb;
header('Content-Type: text/plain; charset=utf-8');

$dry  = isset($_GET['dry']) && $_GET['dry'] === '1';
$step = $_GET['step'] ?? 'all';

echo $dry ? "=== MODE DRY RUN ===\n\n" : "=== MODE RÉEL ===\n\n";

// ── Préfixes à traiter selon le CPT source ──────────────────────────────────
$prefixes_content   = ['INF', 'MIR', 'PRB']; // CPT 'content'
$prefixes_reflexions = ['PDA'];               // CPT 'reflexions' (doublons → trash)

// ── 1. Catégories content_category → category ───────────────────────────────
if (in_array($step, ['all', 'categories'])) {
    echo "=== Migration catégories content_category → category ===\n";

    $used_terms = $wpdb->get_results("
        SELECT DISTINCT t.term_id, t.name, t.slug
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        WHERE p.post_type IN ('content', 'reflexions')
          AND tt.taxonomy = 'content_category'
          AND p.post_status IN ('publish', 'draft')
    ");

    $category_map = [];

    foreach ($used_terms as $term) {
        $existing = get_term_by('slug', $term->slug, 'category');
        if ($existing) {
            $new_id = $existing->term_id;
            echo "  [existe] {$term->name} → cat[{$new_id}]\n";
        } elseif (!$dry) {
            $result = wp_insert_term($term->name, 'category', ['slug' => $term->slug]);
            $new_id = is_wp_error($result) ? null : $result['term_id'];
            echo is_wp_error($result)
                ? "  [ERREUR] {$term->name}: " . $result->get_error_message() . "\n"
                : "  [créé]   {$term->name} → cat[{$new_id}]\n";
        } else {
            $new_id = 'NEW';
            echo "  [DRY]    {$term->name} → serait créé\n";
        }
        if ($new_id) {
            $category_map[$term->term_id] = $new_id;
        }
    }

    // Même chose pour categorie_reflexions
    $reflex_terms = $wpdb->get_results("
        SELECT DISTINCT t.term_id, t.name, t.slug
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        WHERE p.post_type = 'reflexions'
          AND tt.taxonomy = 'categorie_reflexions'
          AND p.post_status IN ('publish', 'draft')
    ");

    $reflexions_category_map = [];
    foreach ($reflex_terms as $term) {
        $existing = get_term_by('slug', $term->slug, 'category');
        if ($existing) {
            $new_id = $existing->term_id;
            echo "  [existe] reflexions:{$term->name} → cat[{$new_id}]\n";
        } elseif (!$dry) {
            $result = wp_insert_term($term->name, 'category', ['slug' => $term->slug]);
            $new_id = is_wp_error($result) ? null : $result['term_id'];
            echo is_wp_error($result)
                ? "  [ERREUR] reflexions:{$term->name}: " . $result->get_error_message() . "\n"
                : "  [créé]   reflexions:{$term->name} → cat[{$new_id}]\n";
        } else {
            $new_id = 'NEW';
            echo "  [DRY]    reflexions:{$term->name} → serait créé\n";
        }
        if ($new_id) {
            $reflexions_category_map[$term->term_id] = $new_id;
        }
    }

    // Migrer les relations content_category
    if (!$dry) {
        $count_rel = 0;
        foreach ($category_map as $old_term_id => $new_term_id) {
            $old_tt = $wpdb->get_var($wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='content_category'",
                $old_term_id
            ));
            $new_tt = $wpdb->get_var($wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='category'",
                $new_term_id
            ));
            if (!$old_tt || !$new_tt) continue;

            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                WHERE tr.term_taxonomy_id = %d AND p.post_type IN ('content','reflexions')
            ", $old_tt));

            foreach ($post_ids as $pid) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id=%d AND term_taxonomy_id=%d",
                    $pid, $new_tt
                ));
                if (!$exists) {
                    $wpdb->insert($wpdb->term_relationships, ['object_id' => $pid, 'term_taxonomy_id' => $new_tt, 'term_order' => 0]);
                    $count_rel++;
                }
            }
            wp_update_term_count_now([$new_term_id], 'category');
        }

        // Migrer les relations categorie_reflexions
        foreach ($reflexions_category_map as $old_term_id => $new_term_id) {
            $old_tt = $wpdb->get_var($wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='categorie_reflexions'",
                $old_term_id
            ));
            $new_tt = $wpdb->get_var($wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='category'",
                $new_term_id
            ));
            if (!$old_tt || !$new_tt) continue;

            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                WHERE tr.term_taxonomy_id = %d AND p.post_type = 'reflexions'
            ", $old_tt));

            foreach ($post_ids as $pid) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id=%d AND term_taxonomy_id=%d",
                    $pid, $new_tt
                ));
                if (!$exists) {
                    $wpdb->insert($wpdb->term_relationships, ['object_id' => $pid, 'term_taxonomy_id' => $new_tt, 'term_order' => 0]);
                    $count_rel++;
                }
            }
            wp_update_term_count_now([$new_term_id], 'category');
        }

        echo "  ✓ {$count_rel} relations catégories créées\n";
    }
}

// ── 2. Transfert content → post ──────────────────────────────────────────────
if (in_array($step, ['all', 'cpt'])) {
    echo "\n=== Transfert CPT content → post ===\n";
    $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='content' AND post_status IN ('publish','draft')");
    echo "  " . count($ids) . " articles trouvés\n";
    if (!$dry && !empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        $n  = $wpdb->query("UPDATE {$wpdb->posts} SET post_type='post' WHERE ID IN ($in)");
        echo "  ✓ {$n} articles → post_type='post'\n";
    } else {
        echo "  [DRY] seraient transférés\n";
    }

    // Transfert reflexions → post (sauf doublons PDA et VER)
    echo "\n=== Transfert CPT reflexions → post (non-doublons) ===\n";
    // Identifier les doublons PDA : même titre (normalisé) qu'un post existant
    $reflex_posts = $wpdb->get_results("
        SELECT r.ID, r.post_title
        FROM {$wpdb->posts} r
        WHERE r.post_type = 'reflexions'
          AND r.post_status IN ('publish','draft')
          AND r.post_title LIKE 'PDA%'
    ");

    $to_trash    = [];
    $to_transfer = [];

    foreach ($reflex_posts as $r) {
        // Vérifier si un post identique existe déjà dans post
        $normalized = preg_replace('/^PDA\s*0*(\d+)\s*[-–—:\.]*\s*/u', 'PDA$1 ', $r->post_title);
        $duplicate  = $wpdb->get_var($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'post'
              AND post_status IN ('publish','draft')
              AND post_title LIKE %s
        ", 'PDA%' . $wpdb->esc_like(preg_replace('/^PDA\s*0*\d+\s*[-–—:\.]*\s*/u', '', $r->post_title)) . '%'));

        if ($duplicate) {
            // Garder le plus récent
            $dup_mod = $wpdb->get_var($wpdb->prepare("SELECT post_modified FROM {$wpdb->posts} WHERE ID=%d", $duplicate));
            $ref_mod = $wpdb->get_var($wpdb->prepare("SELECT post_modified FROM {$wpdb->posts} WHERE ID=%d", $r->ID));
            if ($ref_mod > $dup_mod) {
                $to_trash[]    = $duplicate; // trash l'ancien post
                $to_transfer[] = $r->ID;     // transférer le reflexions plus récent
            } else {
                $to_trash[] = $r->ID; // trash le reflexions plus ancien
            }
        } else {
            $to_transfer[] = $r->ID;
        }
    }

    // Articles non-PDA dans reflexions (ex: VER) → ignorer sauf instruction explicite
    $non_pda = $wpdb->get_col("
        SELECT ID FROM {$wpdb->posts}
        WHERE post_type='reflexions' AND post_status IN ('publish','draft') AND post_title NOT LIKE 'PDA%'
    ");
    echo "  Non-PDA dans reflexions (ignorés): " . count($non_pda) . "\n";

    if (!$dry) {
        foreach ($to_trash as $id) {
            $wpdb->update($wpdb->posts, ['post_status' => 'trash'], ['ID' => $id]);
        }
        if (!empty($to_transfer)) {
            $in = implode(',', array_map('intval', $to_transfer));
            $wpdb->query("UPDATE {$wpdb->posts} SET post_type='post' WHERE ID IN ($in)");
        }
        echo "  ✓ Mis en trash: " . count($to_trash) . " | Transférés: " . count($to_transfer) . "\n";
    } else {
        echo "  [DRY] trash: " . count($to_trash) . " | transfert: " . count($to_transfer) . "\n";
    }
}

// ── 3. Nettoyage caches ──────────────────────────────────────────────────────
if (!$dry) {
    wp_cache_flush();
    if (function_exists('opcache_reset')) opcache_reset();
    echo "\n✓ Caches vidés\n";
}

echo "\n=== Terminé" . ($dry ? " (DRY RUN)" : "") . " ===\n";
