<?php
/**
 * SCRIPT 4 : Normalisation des réflexions VER (« verset du jour » enrichi).
 *
 * Lit les articles VER (post_type 'reflexions', publiés), en extrait
 * référence / verset / réflexion et remplit l'option `schilo_ver_reflections`
 * lue par le thème (Schilo_Reflection). Idempotent : rejouable sans risque.
 *
 * Usage :
 *   ~/bin/wp eval-file ~/migration-run/run.php 04-normalize-ver.php   (côté serveur)
 *   ~/bin/wp eval-file inc/builder/migration-scripts/04-normalize-ver.php  (direct)
 */

if ( php_sapi_name() !== 'cli' ) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ( ! in_array( $ip, array( '127.0.0.1', '::1' ), true ) && ! isset( $_GET['token'] ) ) {
        http_response_code( 403 );
        exit( 'Accès refusé.' );
    }
}

// WP est déjà chargé sous `wp eval-file` ; sinon on l'amorce (exécution directe).
defined( 'WP_ROOT' ) || define( 'WP_ROOT', dirname( __DIR__, 4 ) );
if ( ! function_exists( 'update_option' ) ) {
    require_once WP_ROOT . '/wp-load.php';
}

if ( ! class_exists( 'Schilo_Reflection' ) ) {
    exit( "ERREUR : classe Schilo_Reflection introuvable (thème schilo-theme actif ?).\n" );
}

$count = Schilo_Reflection::rebuild_store();

echo "=== Normalisation VER → option schilo_ver_reflections ===\n";
echo "Réflexions normalisées : {$count}\n";

foreach ( (array) get_option( Schilo_Reflection::OPTION ) as $r ) {
    printf( "  [%s] %s (%s)\n", $r['source_id'] ?? '?', $r['reference'], $r['version_name'] );
}
