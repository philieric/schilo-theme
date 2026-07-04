<?php
/**
 * Accès à la base de données Bible (tables wp_usx_*).
 *
 * Fournit un verset du jour déterministe : même verset toute la journée,
 * change chaque minuit. Mis en cache en transient jusqu'à minuit.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Bible {

    const CACHE_PREFIX = 'schilo_votd_';

    /**
     * Retourne le verset du jour depuis la version Bible par défaut.
     *
     * @return object|null { verse_text, verse, chapter, book, version_name, version_code }
     */
    public static function get_verse_of_day(): ?object {
        $today     = gmdate( 'Y-m-d' );
        $cache_key = self::CACHE_PREFIX . $today;

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached ?: null;
        }

        global $wpdb;

        // Plage d'IDs pour la version par défaut (plus efficace qu'un OFFSET)
        $range = $wpdb->get_row( "
            SELECT MIN(v.id) AS min_id, MAX(v.id) AS max_id, COUNT(v.id) AS total,
                   ver.name AS version_name, ver.code AS version_code
            FROM {$wpdb->prefix}usx_versions ver
            JOIN {$wpdb->prefix}usx_books b    ON b.version_id  = ver.id
            JOIN {$wpdb->prefix}usx_chapters ch ON ch.book_id   = b.id
            JOIN {$wpdb->prefix}usx_verses v    ON v.chapter_id = ch.id
            WHERE ver.is_default = 1
        " );

        if ( ! $range || ! $range->total ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        // Seed déterministe : jour de l'année × année → 31 103 versets couvrent ~85 ans
        $seed   = (int) gmdate( 'z' ) + ( (int) gmdate( 'Y' ) * 365 );
        $offset = $seed % (int) $range->total;

        // ID cible = min_id + offset proportionnel à la plage
        $span      = (int) $range->max_id - (int) $range->min_id;
        $target_id = (int) $range->min_id + (int) round( $offset * $span / (int) $range->total );

        $verse = $wpdb->get_row( $wpdb->prepare( "
            SELECT v.id, v.verse_text, v.verse,
                   ch.number  AS chapter,
                   b.title    AS book,
                   b.code     AS book_code,
                   %s         AS version_name,
                   %s         AS version_code,
                   m.copyright AS copyright
            FROM {$wpdb->prefix}usx_verses v
            JOIN {$wpdb->prefix}usx_chapters ch  ON ch.id       = v.chapter_id
            JOIN {$wpdb->prefix}usx_books b      ON b.id        = ch.book_id
            JOIN {$wpdb->prefix}usx_versions ver ON ver.id      = b.version_id
            LEFT JOIN {$wpdb->prefix}usx_version_meta m ON m.version_id = ver.id
            WHERE v.id >= %d
              AND ver.is_default = 1
            ORDER BY v.id ASC
            LIMIT 1
        ", $range->version_name, $range->version_code, $target_id ) );

        // Secondes restantes avant minuit UTC
        $ttl = (int) ( strtotime( 'tomorrow midnight UTC' ) - time() );
        set_transient( $cache_key, $verse ?: 0, max( $ttl, HOUR_IN_SECONDS ) );

        return $verse ?: null;
    }

    /**
     * Formate la référence d'un verset : "Luc 9.23"
     */
    public static function format_ref( object $verse ): string {
        return esc_html( $verse->book . ' ' . $verse->chapter . '.' . $verse->verse );
    }
}
