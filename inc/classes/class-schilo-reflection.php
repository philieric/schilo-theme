<?php
/**
 * Réflexions bibliques (« verset du jour » enrichi).
 *
 * Les articles VER (ancien CPT « reflexions » de Wikilogy) associent un verset
 * à une courte réflexion rédigée. Le CPT disparaît avec Wikilogy : on normalise
 * donc ces contenus une fois — via {@see rebuild_store()} — dans une option
 * possédée par le thème, puis on en sert un par jour de façon déterministe.
 *
 * L'objet renvoyé par get_reflection_of_day() est volontairement compatible avec
 * celui de {@see Schilo_Bible::get_verse_of_day()} (book, chapter, verse,
 * version_name, verse_text) afin de réutiliser le même rendu, plus un champ
 * « reflection » supplémentaire.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Reflection {

    /** Option stockant les réflexions normalisées (autoload). */
    const OPTION = 'schilo_ver_reflections';

    /**
     * Réflexion du jour, déterministe (même toute la journée, change à minuit).
     *
     * @return object|null { book, chapter, verse, reference, version_name, verse_text, reflection }
     */
    public static function get_reflection_of_day(): ?object {
        $store = get_option( self::OPTION );
        if ( empty( $store ) || ! is_array( $store ) ) {
            return null;
        }

        // Même graine que Schilo_Bible : jour de l'année × année, sensible au fuseau.
        $seed  = (int) wp_date( 'z' ) + ( (int) wp_date( 'Y' ) * 365 );
        $index = $seed % count( $store );

        $obj = (object) $store[ $index ];
        $obj->version_name = self::version_display_name( (string) $obj->version_name );

        return $obj;
    }

    /**
     * Résout un code de version biblique (« S21 ») en nom complet via les tables
     * USX, pour afficher le même libellé que le verset du jour (ex. « Louis Segond
     * S21 »). Retombe sur le code si USX est absent.
     */
    private static function version_display_name( string $code ): string {
        if ( '' === $code ) {
            return $code;
        }
        static $cache = array();
        if ( isset( $cache[ $code ] ) ) {
            return $cache[ $code ];
        }
        global $wpdb;
        $name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}usx_versions WHERE code = %s LIMIT 1",
            $code
        ) );

        return $cache[ $code ] = ( $name ?: $code );
    }

    /**
     * (Re)construit le store depuis les articles VER encore présents en base.
     *
     * Lit directement en SQL (post_type='reflexions') pour ne pas dépendre de
     * l'enregistrement du CPT, absent une fois Wikilogy désactivé.
     *
     * @return int Nombre de réflexions normalisées.
     */
    public static function rebuild_store(): int {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT ID, post_content
               FROM {$wpdb->posts}
              WHERE post_type = 'reflexions'
                AND post_status = 'publish'
                AND post_title LIKE 'VER%'
              ORDER BY post_date ASC"
        );

        $store = array();
        foreach ( $rows as $row ) {
            $parsed = self::parse_content( $row->post_content );
            if ( $parsed && '' !== $parsed['verse_text'] && '' !== $parsed['reflection'] ) {
                $parsed['source_id'] = (int) $row->ID;
                $store[]             = $parsed;
            }
        }

        update_option( self::OPTION, $store, false );

        return count( $store );
    }

    /**
     * Extrait { référence, version, verset, réflexion } d'un post_content VER.
     *
     * Deux formats historiques sont gérés :
     *   <h3>Réf (S21) : verset</h3><em>réflexion</em>          (VER001–012)
     *   <strong>Réf (S21) verset</strong>\n\nréflexion           (VER013–024)
     *
     * @return array|null
     */
    public static function parse_content( string $html ): ?array {
        $html = trim( $html );
        if ( '' === $html ) {
            return null;
        }

        // Le verset vit dans le premier <h3> ou <strong> ; la réflexion suit.
        if ( ! preg_match( '#<(h3|strong)\b[^>]*>(.*?)</\1>#is', $html, $m, PREG_OFFSET_CAPTURE ) ) {
            return null;
        }

        $header = self::clean_text( $m[2][0] );
        $after  = substr( $html, $m[0][1] + strlen( $m[0][0] ) );
        $reflection = self::clean_text( $after );

        // "Matthieu 5.16 (S21) : « … »" → référence, version, verset.
        if ( ! preg_match( '#^(.*?)\(([^)]+)\)\s*:?\s*(.*)$#s', $header, $r ) ) {
            return null;
        }

        $reference = trim( $r[1] );
        $version   = trim( $r[2] );
        $verse     = self::strip_quotes( $r[3] );

        // "Matthieu 5.16" → book "Matthieu", chapter "5", verse "16" (ou "27-28").
        $book = $reference;
        $chapter = '';
        $verse_no = '';
        if ( preg_match( '#^(.+?)\s+(\d+)[.,](.+)$#u', $reference, $b ) ) {
            $book     = trim( $b[1] );
            $chapter  = trim( $b[2] );
            $verse_no = trim( $b[3] );
        }

        return array(
            'book'         => $book,
            'chapter'      => $chapter,
            'verse'        => $verse_no,
            'reference'    => $reference,
            'version_name' => $version,
            'verse_text'   => $verse,
            'reflection'   => $reflection,
        );
    }

    /** Décode/nettoie du HTML en texte : tags retirés, insécables et espaces normalisés. */
    private static function clean_text( string $html ): string {
        $text = wp_strip_all_tags( $html );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = str_replace( "\xc2\xa0", ' ', $text ); // espace insécable → espace
        $text = preg_replace( '/\s+/u', ' ', $text );
        return trim( $text );
    }

    /** Retire guillemets et deux-points superflus autour d'un verset. */
    private static function strip_quotes( string $text ): string {
        $text = self::clean_text( $text );
        $text = preg_replace( '/^[«»"\'\s:]+/u', '', $text );
        $text = preg_replace( '/[«»"\'\s]+$/u', '', $text );
        return trim( $text );
    }
}
