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
 * Nettoie un texte legacy avant affichage public.
 *
 * Les anciens contenus peuvent contenir des shortcodes WPBakery ([vc_row],
 * [vc_column_text], [vc_message]...) parfois tronques dans les extraits
 * automatiques. Ils ne doivent jamais ressortir sur le front si le builder est
 * desactive ou desinstalle.
 */
function schilo_clean_legacy_builder_text( string $text ): string {
    $clean = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );

    $clean = preg_replace( '/<!--[\s\S]*?-->/u', ' ', $clean );
    $clean = preg_replace( '/\[(\/?)(vc|wpb|vcv|mk|dt)_[^\]]*\]/iu', ' ', $clean );
    $clean = preg_replace( '/\[(\/?)[a-zA-Z][a-zA-Z0-9_-]*(?:\s[^\]]*)?\]/u', ' ', $clean );
    $clean = preg_replace( '/\[(\/?)(vc|wpb|vcv|mk|dt)_[^\[]*$/iu', ' ', $clean );
    $clean = preg_replace( '/\[(\/?)[a-zA-Z][a-zA-Z0-9_-]*(?:\s[^\[]*)?$/u', ' ', $clean );
    $clean = wp_strip_all_tags( $clean, true );
    $clean = str_replace( "\xc2\xa0", ' ', $clean );
    $clean = preg_replace( '/\s+/u', ' ', $clean );

    return trim( (string) $clean );
}

function schilo_clean_term_description( string $description ): string {
    return schilo_clean_legacy_builder_text( $description );
}

/**
 * Détecte l'évangile lié à une référence biblique en texte libre.
 *
 * Retourne "bible" pour les livres hors évangiles, ou quand la référence n'est
 * pas assez structurée pour être rattachée à Matthieu, Marc, Luc ou Jean.
 */
function schilo_detect_gospel_from_bible_ref( string $ref ): string {
    $ref = trim( $ref );
    if ( $ref === '' ) {
        return 'bible';
    }

    static $map = [
        'matthieu' => 'matthieu', 'matthew' => 'matthieu', 'matt' => 'matthieu', 'mt' => 'matthieu', 'mat' => 'matthieu',
        'marc'     => 'marc',     'mark'    => 'marc',     'mc'   => 'marc',     'mr' => 'marc', 'mrk' => 'marc',
        'luc'      => 'luc',      'luke'    => 'luc',      'lc'   => 'luc',      'lk' => 'luc',  'lu'  => 'luc',
        'jean'     => 'jean',     'john'    => 'jean',     'jn'   => 'jean',     'jo' => 'jean', 'jhn' => 'jean',
    ];

    if ( preg_match( '/^([a-zà-ÿ]+)/iu', $ref, $m ) ) {
        $word = strtolower( remove_accents( $m[1] ) );
        return $map[ $word ] ?? 'bible';
    }

    return 'bible';
}

/**
 * Retourne l'évangile dominant d'un article avec la même logique que le rendu
 * Schilo Builder : comptage des références bibliques, puis priorité
 * Matthieu > Marc > Luc > Jean > Bible en cas d'égalité.
 */
function schilo_get_post_dominant_gospel( int $post_id ): string {
    static $cache = [];

    if ( isset( $cache[ $post_id ] ) ) {
        return $cache[ $post_id ];
    }

    $counts = [ 'matthieu' => 0, 'marc' => 0, 'luc' => 0, 'jean' => 0, 'bible' => 0 ];

    if (
        class_exists( '\Schilo\Builder\Repository\SectionRepository' )
        && class_exists( '\Schilo\Builder\Service\SectionRenderer' )
    ) {
        $repository = new \Schilo\Builder\Repository\SectionRepository();
        $sections = $repository->findByPostId( $post_id );

        foreach ( $sections as $section ) {
            foreach ( \Schilo\Builder\Service\SectionRenderer::countGospels( $section ) as $gospel => $count ) {
                if ( isset( $counts[ $gospel ] ) ) {
                    $counts[ $gospel ] += (int) $count;
                }
            }
        }
    }

    if ( array_sum( $counts ) === 0 && class_exists( '\Schilo\Builder\Service\IndexationService' ) ) {
        $row = ( new \Schilo\Builder\Service\IndexationService() )->getByPostId( $post_id );
        $references = $row ? json_decode( $row['references_bibliques'] ?? '[]', true ) : [];

        if ( is_array( $references ) ) {
            foreach ( $references as $reference ) {
                $gospel = schilo_detect_gospel_from_bible_ref( (string) $reference );
                $counts[ isset( $counts[ $gospel ] ) ? $gospel : 'bible' ]++;
            }
        }
    }

    $cache[ $post_id ] = class_exists( '\Schilo\Builder\Service\SectionRenderer' )
        ? \Schilo\Builder\Service\SectionRenderer::pickDominantGospel( $counts )
        : '';

    return $cache[ $post_id ];
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
