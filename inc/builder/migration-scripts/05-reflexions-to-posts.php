<?php
/**
 * SCRIPT 5 : Conversion des réflexions VER (ancien CPT « reflexions » de
 * Wikilogy, non ré-enregistré) en articles standard (post_type='post').
 *
 * Ne touche QUE les VER publiés (post_status='publish', post_title LIKE 'VER%').
 * Le brouillon VER002 et les 19 réflexions PDA en corbeille sont laissés
 * intacts. Les VER gardent titre, contenu, date, slug et leur catégorie
 * « versets du jour » — seul le post_type change.
 *
 * Idempotent : rejouable sans risque (un VER déjà en 'post' n'est plus
 * retrouvé par la requête sur post_type='reflexions').
 *
 * Le widget « verset du jour » continue de fonctionner : Schilo_Reflection
 * lit désormais les VER sur post_type IN ('post','reflexions').
 *
 * Usage :
 *   ~/bin/wp eval-file ~/migration-run/run.php 05-reflexions-to-posts.php [dry=1]   (serveur)
 *   ~/bin/wp eval-file inc/builder/migration-scripts/05-reflexions-to-posts.php dry=1
 */

if ( php_sapi_name() !== 'cli' ) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ( ! in_array( $ip, array( '127.0.0.1', '::1' ), true ) && ! isset( $_GET['token'] ) ) {
        http_response_code( 403 );
        exit( 'Accès refusé.' );
    }
}

// WP est déjà chargé sous `wp eval-file` ; sinon on l'amorce (exécution directe)
// en remontant les dossiers jusqu'à trouver wp-load.php — robuste quelle que
// soit la profondeur (structure thème comme plugin).
if ( ! function_exists( 'update_option' ) ) {
    $schilo_wp_root = __DIR__;
    for ( $i = 0; $i < 8 && ! file_exists( $schilo_wp_root . '/wp-load.php' ); $i++ ) {
        $schilo_wp_root = dirname( $schilo_wp_root );
    }
    require_once $schilo_wp_root . '/wp-load.php';
}

// Mode simulation : `dry=1` (argv) ou `?dry=1`.
$dry = ( isset( $_GET['dry'] ) && $_GET['dry'] );
foreach ( (array) ( $argv ?? array() ) as $arg ) {
    if ( $arg === 'dry=1' || $arg === 'dry' ) {
        $dry = true;
    }
}

global $wpdb;

$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_type = 'reflexions'
        AND post_status = 'publish'
        AND post_title LIKE 'VER%'
      ORDER BY post_date ASC"
);

echo "=== Conversion des réflexions VER en articles (post) ===\n";
echo ( $dry ? '[SIMULATION] ' : '' ) . count( $ids ) . " VER publié(s) à convertir.\n";

$done = 0;
foreach ( $ids as $id ) {
    $id    = (int) $id;
    $title = get_the_title( $id );
    echo '  ' . ( $dry ? '[dry] ' : '' ) . "#{$id}  {$title}\n";

    if ( ! $dry ) {
        $wpdb->update( $wpdb->posts, array( 'post_type' => 'post' ), array( 'ID' => $id ) );
        clean_post_cache( $id );
        $done++;
    }
}

if ( ! $dry && $done > 0 ) {
    // Recompte la catégorie « versets du jour » (les compteurs par type de post
    // ne se mettent pas à jour tout seuls après un UPDATE SQL direct).
    $cat = get_term_by( 'slug', 'versets-du-jour', 'category' );
    if ( $cat ) {
        wp_update_term_count_now( array( (int) $cat->term_taxonomy_id ), 'category' );
    }

    // Rafraîchit le store du widget « verset du jour » (pointe désormais sur les
    // VER en post_type='post').
    if ( class_exists( 'Schilo_Reflection' ) ) {
        Schilo_Reflection::rebuild_store();
    }

    // Invalide le cache des préfixes admin (VER devient un préfixe filtrable) et
    // le cache objet.
    delete_transient( 'schilo_article_prefixes' );
    wp_cache_flush();
}

echo ( $dry ? 'SIMULATION — aucune écriture effectuée.' : "{$done} réflexion(s) VER converti(es) en article(s)." ) . "\n";
