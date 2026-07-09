<?php
/**
 * Fonctions helper utilisées dans les templates.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Retourne l'URL d'un asset du thème.
 */
function schilo_asset( string $path ): string {
    return SCHILO_ASSETS . '/' . ltrim( $path, '/' );
}

/**
 * Retourne la couleur CSS d'un Évangile.
 * @param string $ev  'mat' | 'marc' | 'luc' | 'jean'
 */
function schilo_ev_color( string $ev ): string {
    return [
        'mat'  => '#e05a2b',
        'marc' => '#2e9e4f',
        'luc'  => '#2872d4',
        'jean' => '#7c4db8',
    ][ $ev ] ?? '#8a96a8';
}

/**
 * Retourne la lettre d'un Évangile.
 */
function schilo_ev_letter( string $ev ): string {
    return [
        'mat'  => 'M',
        'marc' => 'M',
        'luc'  => 'L',
        'jean' => 'J',
    ][ $ev ] ?? '?';
}

/**
 * Retourne le nom complet d'un Évangile.
 */
function schilo_ev_name( string $ev ): string {
    return [
        'mat'  => 'Matthieu',
        'marc' => 'Marc',
        'luc'  => 'Luc',
        'jean' => 'Jean',
    ][ $ev ] ?? $ev;
}

/**
 * Affiche le badge d'un Évangile.
 */
function schilo_ev_badge( string $ev, string $size = '32px' ): void {
    echo '<span class="schilo-ev-badge schilo-ev-badge--' . esc_attr( $ev )
        . '" style="width:' . esc_attr( $size ) . ';height:' . esc_attr( $size ) . '">'
        . esc_html( schilo_ev_letter( $ev ) )
        . '</span>';
}

/**
 * Retire le préfixe numérique ("1 - ", "12 - "…) utilisé pour ordonner les
 * catégories principales dans les menus admin, avant affichage public
 * (cartes d'articles, badges, fils d'Ariane…).
 */
function schilo_strip_category_number( string $name ): string {
    return preg_replace( '/^\d+\s*-\s*/u', '', $name );
}

/**
 * Masque une clé API pour l'affichage admin (garde les 6 derniers caractères).
 */
if ( ! function_exists( 'schilo_mask_key' ) ) :
function schilo_mask_key( string $k ): string {
    if ( strlen( $k ) < 8 ) return $k ? str_repeat( '*', strlen( $k ) ) : '';
    return str_repeat( '*', strlen( $k ) - 6 ) . substr( $k, -6 );
}
endif;

/**
 * Titres canoniques des 66 livres bibliques (table de référence du plugin
 * Usx-import, wp_usx_book_ref), utilisés pour détecter les références
 * bibliques tapées en texte libre dans l'éditeur (proposition de shortcode
 * [bib]/[bvc]/[brc]/[bnv] avant enregistrement — voir admin-bib-ref-detect.js).
 * Mis en cache 1 jour : cette table ne change quasiment jamais.
 */
function schilo_get_bible_book_titles(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'usx_book_ref';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        return [];
    }

    $cached = get_transient( 'schilo_bible_book_titles' );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    $titles = $wpdb->get_col( "SELECT canon_title FROM {$table} WHERE canon_title != '' ORDER BY CHAR_LENGTH(canon_title) DESC" );
    $titles = array_values( array_unique( array_filter( array_map( 'trim', $titles ) ) ) );

    set_transient( 'schilo_bible_book_titles', $titles, DAY_IN_SECONDS );

    return $titles;
}
