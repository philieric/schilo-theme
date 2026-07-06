<?php
/**
 * Suggestions de recherche enrichies à partir de l'indexation IA des articles.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Search_Suggest {

    const TRANSIENT_KEY = 'schilo_search_vocab';
    const TRANSIENT_TTL = 6 * HOUR_IN_SECONDS;
    const MAX_SUGGESTIONS = 8;
    const MAX_ARTICLES    = 3;

    /**
     * Champs JSON de wp_schilo_indexation à indexer, avec leur type de suggestion.
     */
    private static $json_fields = [
        'mots_cles'            => 'mot_cle',
        'personnages'          => 'personnage',
        'lieux'                => 'lieu',
        'concepts'             => 'concept',
        'references_bibliques' => 'reference',
    ];

    /**
     * Champs texte simple de wp_schilo_indexation à indexer.
     */
    private static $scalar_fields = [
        'theme_principal' => 'theme',
        'sous_theme'      => 'sous_theme',
    ];

    /**
     * Métadonnées d'affichage par type de suggestion.
     */
    private static $type_meta = [
        'mot_cle'    => [ 'label' => 'Mot-clé',    'icon' => 'ti-tag' ],
        'personnage' => [ 'label' => 'Personnage', 'icon' => 'ti-user' ],
        'lieu'       => [ 'label' => 'Lieu',       'icon' => 'ti-map-pin' ],
        'concept'    => [ 'label' => 'Concept',    'icon' => 'ti-bulb' ],
        'reference'  => [ 'label' => 'Référence',  'icon' => 'ti-book' ],
        'theme'      => [ 'label' => 'Thème',      'icon' => 'ti-category' ],
        'sous_theme' => [ 'label' => 'Sous-thème', 'icon' => 'ti-category-2' ],
    ];

    public static function init(): void {
        add_action( 'wp_ajax_schilo_search_suggest', [ __CLASS__, 'ajax_suggest' ] );
        add_action( 'wp_ajax_nopriv_schilo_search_suggest', [ __CLASS__, 'ajax_suggest' ] );
    }

    /**
     * Invalide le cache de vocabulaire (à appeler après une validation d'indexation).
     */
    public static function flush_vocab(): void {
        delete_transient( self::TRANSIENT_KEY );
    }

    /**
     * Construit (ou lit depuis le cache) le vocabulaire de suggestions
     * à partir des articles indexés et validés.
     *
     * @return array<string, array{term:string,type:string,post_ids:int[]}>
     */
    private static function get_vocab(): array {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'schilo_indexation';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            set_transient( self::TRANSIENT_KEY, [], self::TRANSIENT_TTL );
            return [];
        }

        $fields  = array_merge( array_keys( self::$json_fields ), array_keys( self::$scalar_fields ) );
        $columns = implode( ', ', array_merge( [ 'post_id' ], $fields ) );

        $rows = $wpdb->get_results(
            "SELECT {$columns} FROM {$table} WHERE statut_indexation = 'valide'",
            ARRAY_A
        );

        $vocab = [];

        foreach ( (array) $rows as $row ) {
            $post_id = (int) $row['post_id'];

            foreach ( self::$json_fields as $field => $type ) {
                $items = json_decode( (string) ( $row[ $field ] ?? '' ), true );
                if ( ! is_array( $items ) ) {
                    continue;
                }
                foreach ( $items as $term ) {
                    if ( is_string( $term ) ) {
                        self::add_term( $vocab, $term, $type, $post_id );
                    }
                }
            }

            foreach ( self::$scalar_fields as $field => $type ) {
                $term = trim( (string) ( $row[ $field ] ?? '' ) );
                if ( $term !== '' ) {
                    self::add_term( $vocab, $term, $type, $post_id );
                }
            }
        }

        set_transient( self::TRANSIENT_KEY, $vocab, self::TRANSIENT_TTL );

        return $vocab;
    }

    private static function add_term( array &$vocab, string $term, string $type, int $post_id ): void {
        $term = trim( $term );
        if ( $term === '' ) {
            return;
        }

        $key = $type . '|' . self::normalize( $term );

        if ( ! isset( $vocab[ $key ] ) ) {
            $vocab[ $key ] = [
                'term'     => $term,
                'type'     => $type,
                'post_ids' => [],
            ];
        }

        if ( ! in_array( $post_id, $vocab[ $key ]['post_ids'], true ) ) {
            $vocab[ $key ]['post_ids'][] = $post_id;
        }
    }

    /**
     * Normalise un terme pour une comparaison insensible à la casse et aux accents.
     */
    private static function normalize( string $value ): string {
        return mb_strtolower( remove_accents( $value ), 'UTF-8' );
    }

    /**
     * Retourne le résumé court indexé (resume_court) pour une liste de post_id.
     *
     * @param int[] $post_ids
     * @return array<int, string> post_id => resume_court
     */
    private static function get_summaries( array $post_ids ): array {
        if ( empty( $post_ids ) ) {
            return [];
        }

        global $wpdb;
        $table        = $wpdb->prefix . 'schilo_indexation';
        $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, resume_court FROM {$table} WHERE post_id IN ({$placeholders})",
                $post_ids
            ),
            ARRAY_A
        );

        $summaries = [];
        foreach ( (array) $rows as $row ) {
            $summaries[ (int) $row['post_id'] ] = (string) $row['resume_court'];
        }

        return $summaries;
    }

    /**
     * Endpoint AJAX (public) : retourne des suggestions de vocabulaire
     * + des articles correspondants pour un terme partiel saisi par l'utilisateur.
     */
    public static function ajax_suggest(): void {
        check_ajax_referer( 'schilo_nonce', 'nonce' );

        $term = sanitize_text_field( wp_unslash( $_REQUEST['term'] ?? '' ) );
        $term = trim( $term );

        if ( mb_strlen( $term, 'UTF-8' ) < 2 ) {
            wp_send_json_success( [ 'suggestions' => [], 'articles' => [] ] );
        }

        $needle = self::normalize( $term );
        $vocab  = self::get_vocab();

        $starts_with = [];
        $contains    = [];

        foreach ( $vocab as $entry ) {
            $haystack = self::normalize( $entry['term'] );
            $pos      = mb_strpos( $haystack, $needle, 0, 'UTF-8' );

            if ( $pos === false ) {
                continue;
            }

            if ( $pos === 0 ) {
                $starts_with[] = $entry;
            } else {
                $contains[] = $entry;
            }
        }

        $by_length = function ( array $a, array $b ): int {
            return mb_strlen( $a['term'], 'UTF-8' ) <=> mb_strlen( $b['term'], 'UTF-8' );
        };
        usort( $starts_with, $by_length );
        usort( $contains, $by_length );

        $ranked = array_slice( array_merge( $starts_with, $contains ), 0, self::MAX_SUGGESTIONS );

        $suggestions = array_map( function ( array $entry ): array {
            $meta = self::$type_meta[ $entry['type'] ] ?? [ 'label' => '', 'icon' => 'ti-tag' ];
            return [
                'term'      => $entry['term'],
                'type'      => $entry['type'],
                'typeLabel' => $meta['label'],
                'icon'      => $meta['icon'],
                'url'       => get_search_link( $entry['term'] ),
            ];
        }, $ranked );

        // Articles suggérés : postids issus des meilleures correspondances (préfixe d'abord).
        $article_ids = [];
        foreach ( array_merge( $starts_with, $contains ) as $entry ) {
            foreach ( $entry['post_ids'] as $post_id ) {
                if ( count( $article_ids ) >= self::MAX_ARTICLES ) {
                    break 2;
                }
                if ( ! in_array( $post_id, $article_ids, true ) ) {
                    $article_ids[] = $post_id;
                }
            }
        }

        $summaries = self::get_summaries( $article_ids );

        $articles = [];
        foreach ( $article_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                continue;
            }
            $summary = html_entity_decode( trim( (string) ( $summaries[ $post_id ] ?? '' ) ), ENT_QUOTES, 'UTF-8' );
            $articles[] = [
                'title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
                'url'     => get_permalink( $post ),
                'summary' => $summary !== '' ? wp_trim_words( $summary, 20, '…' ) : '',
            ];
        }

        wp_send_json_success( [
            'suggestions' => $suggestions,
            'articles'    => $articles,
        ] );
    }
}
