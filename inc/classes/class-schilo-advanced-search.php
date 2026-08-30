<?php
/**
 * Recherche avancée : filtres cochables (thème/parcours/série/type d'article/
 * livre biblique) + texte libre, combinés en ET, résultats en AJAX.
 *
 * Page dédiée (page-recherche-avancee.php), accessible depuis l'icône du
 * header à droite du bouton de recherche rapide (schilo-search-modal, qui
 * reste inchangé — deux entrées différentes pour deux usages différents).
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Advanced_Search {

    const RESULTS_PER_PAGE = 20;

    const TRANSIENT_BOOK_INDEX = 'schilo_search_book_index';
    const TRANSIENT_TTL        = 6 * HOUR_IN_SECONDS;

    /** Codes canoniques (wp_usx_book_ref.canon_code) de l'Ancien Testament, pour le regroupement AT/NT du filtre livre. */
    private static $ot_books = [
        'GEN', 'EXO', 'LEV', 'NUM', 'DEU', 'JOS', 'JDG', 'RUT', '1SA', '2SA',
        '1KI', '2KI', '1CH', '2CH', 'EZR', 'NEH', 'EST', 'JOB', 'PSA', 'PRO',
        'ECC', 'SNG', 'ISA', 'JER', 'LAM', 'EZK', 'DAN', 'HOS', 'JOL', 'AMO',
        'OBA', 'JON', 'MIC', 'NAM', 'HAB', 'ZEP', 'HAG', 'ZEC', 'MAL',
    ];

    public static function init(): void {
        add_action( 'wp_ajax_schilo_advanced_search', [ __CLASS__, 'ajax_search' ] );
        add_action( 'wp_ajax_nopriv_schilo_advanced_search', [ __CLASS__, 'ajax_search' ] );
    }

    /**
     * Invalide le cache de l'index livre <-> articles (à appeler après une
     * validation d'indexation, comme Schilo_Search_Suggest::flush_vocab()).
     */
    public static function flush_book_index(): void {
        delete_transient( self::TRANSIENT_BOOK_INDEX );
    }

    /* =========================================================
       DONNÉES POUR LE RENDU DE LA PAGE (server-side, pas d'AJAX)
    ========================================================= */

    /**
     * Toutes les données nécessaires au rendu du panneau de filtres.
     *
     * @return array{themes:array,parcours:array,series:array,types:array,books:array}
     */
    public static function get_filters_data(): array {
        return [
            'themes'   => self::get_terms_tree( 'schilo_theme' ),
            'parcours' => self::get_terms_tree( 'schilo_parcours' ),
            'series'   => self::get_terms_flat( 'schilo_serie' ),
            'types'    => self::get_article_types(),
            'books'    => self::get_book_groups(),
        ];
    }

    private static function get_terms_tree( string $taxonomy ): array {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return [];
        }

        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        $by_id = [];
        foreach ( $terms as $term ) {
            $by_id[ $term->term_id ] = [
                'id'       => (int) $term->term_id,
                'name'     => $term->name,
                'children' => [],
            ];
        }

        $tree = [];
        foreach ( $terms as $term ) {
            if ( $term->parent && isset( $by_id[ $term->parent ] ) ) {
                $by_id[ $term->parent ]['children'][] = &$by_id[ $term->term_id ];
            } else {
                $tree[] = &$by_id[ $term->term_id ];
            }
        }

        return $tree;
    }

    private static function get_terms_flat( string $taxonomy ): array {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return [];
        }

        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ] );
        if ( is_wp_error( $terms ) ) {
            return [];
        }

        return array_map( static function ( $term ): array {
            return [ 'id' => (int) $term->term_id, 'name' => $term->name ];
        }, $terms );
    }

    /**
     * Types d'article (préfixes) disponibles, avec leur libellé configuré
     * (Schilo Builder > Types & templates) — ANX exclu : les annexes ne sont
     * jamais des résultats de recherche (voir excludeFromListings()).
     */
    private static function get_article_types(): array {
        $labels = [];
        if ( class_exists( '\\Schilo\\Builder\\Service\\TemplateService' ) ) {
            $templates = ( new \Schilo\Builder\Service\TemplateService() )->getAllTemplates();
            foreach ( $templates as $key => $tpl ) {
                $labels[ $key ] = isset( $tpl['label'] ) ? (string) $tpl['label'] : $key;
            }
        }

        $types = [];
        foreach ( Schilo_Prefixes::all() as $prefix ) {
            if ( $prefix === 'ANX' ) {
                continue;
            }
            $types[] = [
                'code'  => $prefix,
                'label' => $labels[ $prefix ] ?? $prefix,
            ];
        }

        return $types;
    }

    /** Livres bibliques canoniques, regroupés Ancien/Nouveau Testament. */
    private static function get_book_groups(): array {
        global $wpdb;
        $t_ref = $wpdb->prefix . 'usx_book_ref';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_ref ) ) !== $t_ref ) {
            return [ 'ancien' => [], 'nouveau' => [] ];
        }

        $rows = $wpdb->get_results( "SELECT canon_code, canon_title FROM {$t_ref} ORDER BY id ASC" );

        $groups = [ 'ancien' => [], 'nouveau' => [] ];
        foreach ( $rows as $row ) {
            $bucket = in_array( $row->canon_code, self::$ot_books, true ) ? 'ancien' : 'nouveau';
            $groups[ $bucket ][] = [ 'code' => $row->canon_code, 'title' => $row->canon_title ];
        }

        return $groups;
    }

    /* =========================================================
       INDEX LIVRE <-> ARTICLES (dérivé de references_bibliques)
    ========================================================= */

    /**
     * @return array<string, int[]> code canonique => IDs d'articles indexés
     */
    private static function get_book_index(): array {
        $cached = get_transient( self::TRANSIENT_BOOK_INDEX );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'schilo_indexation';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            set_transient( self::TRANSIENT_BOOK_INDEX, [], self::TRANSIENT_TTL );
            return [];
        }

        $rows = $wpdb->get_results(
            "SELECT post_id, references_bibliques FROM {$table}
             WHERE statut_indexation = 'valide' AND references_bibliques IS NOT NULL AND references_bibliques != ''",
            ARRAY_A
        );

        $resolved_tokens = []; // cache local pour la durée du build (un meme token revient tres souvent)
        $index = [];

        foreach ( (array) $rows as $row ) {
            $post_id = (int) $row['post_id'];
            $refs    = json_decode( (string) $row['references_bibliques'], true );
            if ( ! is_array( $refs ) ) {
                continue;
            }

            foreach ( $refs as $ref ) {
                if ( ! is_string( $ref ) ) {
                    continue;
                }
                $token = self::extract_book_token( $ref );
                if ( $token === '' ) {
                    continue;
                }

                if ( ! array_key_exists( $token, $resolved_tokens ) ) {
                    $resolved_tokens[ $token ] = self::resolve_canonical_book( $token );
                }
                $book = $resolved_tokens[ $token ];
                if ( ! $book ) {
                    continue;
                }

                if ( ! isset( $index[ $book['code'] ] ) ) {
                    $index[ $book['code'] ] = [];
                }
                if ( ! in_array( $post_id, $index[ $book['code'] ], true ) ) {
                    $index[ $book['code'] ][] = $post_id;
                }
            }
        }

        set_transient( self::TRANSIENT_BOOK_INDEX, $index, self::TRANSIENT_TTL );

        return $index;
    }

    /**
     * Extrait le "token livre" en tête d'une référence libre du type
     * "Luc 1,1-4" / "Jn 1.1-18" / "1 Corinthiens 13.4" : tout ce qui précède
     * le premier chiffre. Volontairement plus permissif que
     * Schilo_Usx_Bible_Lookup::parse_reference() (qui n'accepte que ':' ou
     * '.' comme séparateur chapitre.verset) car les références générées par
     * l'IA d'indexation utilisent parfois la virgule.
     */
    public static function extract_book_token( string $reference ): string {
        $reference = trim( $reference );
        // Le chiffre de tete d'un livre numerote ("1 Corinthiens", "2 Timothee"…)
        // fait partie du nom du livre, pas du chapitre : on l'autorise en
        // option avant la portion non-numerique, sinon "1 Corinthiens 13.4"
        // ne matcherait rien du tout (la chaine commence par un chiffre).
        if ( preg_match( '/^((?:\d\s+)?[^\d]+?)\s*\d/u', $reference, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    /**
     * Résout un token de livre vers le livre canonique (wp_usx_book_ref),
     * par titre exact puis par abréviation connue — même logique que
     * Schilo_Usx_Bible_Lookup::find_book_by_abbr_ref(), mais au niveau
     * canonique (indépendant de toute version biblique particulière).
     *
     * @return array{code:string,title:string}|null
     */
    private static function resolve_canonical_book( string $token ): ?array {
        if ( ! class_exists( 'Schilo_Usx_Bible_Lookup' ) ) {
            return null;
        }

        global $wpdb;
        $t_ref  = $wpdb->prefix . 'usx_book_ref';
        $t_abbr = $wpdb->prefix . 'usx_book_ref_abbr';

        $norm_title = Schilo_Usx_Bible_Lookup::normalize_for_compare( $token );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT canon_code, canon_title FROM {$t_ref} WHERE canon_title_norm = %s LIMIT 1",
            $norm_title
        ) );
        if ( $row ) {
            return [ 'code' => $row->canon_code, 'title' => $row->canon_title ];
        }

        $abbr_norm = Schilo_Usx_Bible_Lookup::abbr_norm( $token );
        if ( $abbr_norm === '' ) {
            return null;
        }
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.canon_code, r.canon_title FROM {$t_abbr} a
             INNER JOIN {$t_ref} r ON r.id = a.ref_id
             WHERE a.abbr_norm = %s LIMIT 1",
            $abbr_norm
        ) );

        return $row ? [ 'code' => $row->canon_code, 'title' => $row->canon_title ] : null;
    }

    /**
     * Rendu récursif d'un groupe de cases à cocher hiérarchique
     * (thème/parcours) — utilisé par page-recherche-avancee.php.
     */
    public static function render_term_checkboxes( array $terms, string $field, int $depth = 0 ): void {
        foreach ( $terms as $term ) {
            ?>
            <label class="schilo-adv-check schilo-adv-check--depth-<?php echo esc_attr( min( $depth, 2 ) ); ?>">
                <input type="checkbox" name="<?php echo esc_attr( $field ); ?>[]" value="<?php echo esc_attr( $term['id'] ); ?>">
                <span><?php echo esc_html( $term['name'] ); ?></span>
            </label>
            <?php
            if ( ! empty( $term['children'] ) ) {
                self::render_term_checkboxes( $term['children'], $field, $depth + 1 );
            }
        }
    }

    /* =========================================================
       AJAX
    ========================================================= */

    public static function ajax_search(): void {
        check_ajax_referer( 'schilo_nonce', 'nonce' );

        $q        = sanitize_text_field( wp_unslash( $_REQUEST['q'] ?? '' ) );
        $themes   = self::sanitize_int_list( $_REQUEST['theme'] ?? [] );
        $parcours = self::sanitize_int_list( $_REQUEST['parcours'] ?? [] );
        $series   = self::sanitize_int_list( $_REQUEST['serie'] ?? [] );
        $types    = self::sanitize_prefix_list( $_REQUEST['type'] ?? [] );
        $books    = self::sanitize_code_list( $_REQUEST['livre'] ?? [] );
        $offset   = max( 0, (int) ( $_REQUEST['offset'] ?? 0 ) );

        $result = self::run_search( $q, $themes, $parcours, $series, $types, $books, $offset );

        wp_send_json_success( $result );
    }

    public static function sanitize_int_list( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        return array_values( array_unique( array_filter( array_map( 'intval', $raw ) ) ) );
    }

    public static function sanitize_prefix_list( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $clean = [];
        foreach ( $raw as $v ) {
            $v = strtoupper( sanitize_key( (string) $v ) );
            if ( Schilo_Prefixes::is_valid( $v ) && $v !== 'ANX' ) {
                $clean[] = $v;
            }
        }
        return array_values( array_unique( $clean ) );
    }

    public static function sanitize_code_list( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $clean = [];
        foreach ( $raw as $v ) {
            $v = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $v ) );
            if ( $v !== '' ) {
                $clean[] = $v;
            }
        }
        return array_values( array_unique( $clean ) );
    }

    /**
     * Construit et exécute la requête combinant tous les critères actifs
     * (ET logique entre familles de critères, OU à l'intérieur d'une même
     * famille — ex: thème A OU thème B, ET type PER OU type CTD).
     */
    private static function run_search(
        string $q,
        array $theme_ids,
        array $parcours_ids,
        array $serie_ids,
        array $types,
        array $book_codes,
        int $offset
    ): array {
        global $wpdb;

        $joins  = [];
        $wheres = [ "p.post_type = 'post'", "p.post_status = 'publish'", "p.post_title NOT LIKE 'ANX%'" ];
        $params = [];

        // Exclusion des versions non-principales — même règle que les listings publics.
        if ( class_exists( '\\Schilo\\Builder\\Service\\ArticleVersionService' ) ) {
            $excluded = ( new \Schilo\Builder\Service\ArticleVersionService() )->getExcludedFromListingsIds();
            $excluded = array_map( 'intval', $excluded );
            if ( ! empty( $excluded ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $excluded ), '%d' ) );
                $wheres[]     = "p.ID NOT IN ({$placeholders})";
                array_push( $params, ...$excluded );
            }
        }

        // Texte libre : titre OU résumé/mots-clés indexés.
        if ( $q !== '' ) {
            $joins[] = "LEFT JOIN {$wpdb->prefix}schilo_indexation si ON si.post_id = p.ID";
            $like    = '%' . $wpdb->esc_like( $q ) . '%';
            $wheres[] = '(p.post_title LIKE %s OR si.resume_court LIKE %s OR si.resume LIKE %s OR si.mots_cles LIKE %s)';
            array_push( $params, $like, $like, $like, $like );
        }

        // Taxonomies : une jointure par famille active (thème/parcours/série).
        foreach ( [ 'schilo_theme' => $theme_ids, 'schilo_parcours' => $parcours_ids, 'schilo_serie' => $serie_ids ] as $taxonomy => $term_ids ) {
            if ( empty( $term_ids ) ) {
                continue;
            }
            $alias        = 'tt_' . substr( $taxonomy, -6 );
            $placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
            $joins[]      = "INNER JOIN {$wpdb->term_relationships} {$alias} ON {$alias}.object_id = p.ID
                              INNER JOIN {$wpdb->term_taxonomy} {$alias}_tt ON {$alias}_tt.term_taxonomy_id = {$alias}.term_taxonomy_id
                                  AND {$alias}_tt.taxonomy = '{$taxonomy}' AND {$alias}_tt.term_id IN ({$placeholders})";
            array_push( $params, ...array_map( 'intval', $term_ids ) );
        }

        // Type d'article (préfixe du titre) : OU entre les préfixes cochés.
        if ( ! empty( $types ) ) {
            $ors = [];
            foreach ( $types as $prefix ) {
                $ors[]    = 'p.post_title LIKE %s';
                $params[] = $wpdb->esc_like( $prefix ) . '%';
            }
            $wheres[] = '(' . implode( ' OR ', $ors ) . ')';
        }

        // Livre biblique : résolu via l'index dérivé (pas de colonne dédiée).
        if ( ! empty( $book_codes ) ) {
            $book_index = self::get_book_index();
            $ids        = [];
            foreach ( $book_codes as $code ) {
                foreach ( $book_index[ $code ] ?? [] as $id ) {
                    $ids[] = $id;
                }
            }
            $ids = array_values( array_unique( $ids ) );

            if ( empty( $ids ) ) {
                return [ 'items' => [], 'hasMore' => false, 'total' => 0 ];
            }
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wheres[]     = "p.ID IN ({$placeholders})";
            array_push( $params, ...$ids );
        }

        $join_sql  = implode( "\n", $joins );
        $where_sql = implode( ' AND ', $wheres );

        // IMPORTANT : ne jamais imbriquer un $wpdb->prepare() dans le texte
        // SQL avant l'appel prepare() final ci-dessous — la valeur déjà
        // substituée contient un '%' litteral (le joker LIKE) qui serait
        // réinterprété comme un nouveau format à ce second passage. On garde
        // donc un simple '%s' ici, alimenté au bon endroit dans $params.
        if ( $q !== '' ) {
            $order_sql = '(p.post_title LIKE %s) DESC, p.post_title ASC';
            $params[]  = $wpdb->esc_like( $q ) . '%';
        } else {
            $order_sql = 'p.post_title ASC';
        }

        $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                {$join_sql}
                WHERE {$where_sql}
                ORDER BY {$order_sql}
                LIMIT %d OFFSET %d";

        // +1 pour savoir s'il reste des résultats, sans COUNT(*) séparé.
        $params[] = self::RESULTS_PER_PAGE + 1;
        $params[] = $offset;

        $ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
        $ids = array_map( 'intval', $ids );

        $has_more = count( $ids ) > self::RESULTS_PER_PAGE;
        if ( $has_more ) {
            array_pop( $ids );
        }

        return [
            'items'   => self::format_results( $ids ),
            'hasMore' => $has_more,
        ];
    }

    /**
     * @param int[] $post_ids
     * @return array<int, array<string, mixed>>
     */
    private static function format_results( array $post_ids ): array {
        if ( empty( $post_ids ) ) {
            return [];
        }

        global $wpdb;
        $table        = $wpdb->prefix . 'schilo_indexation';
        $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
        $summaries    = [];
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT post_id, resume_court FROM {$table} WHERE post_id IN ({$placeholders})", $post_ids ),
                ARRAY_A
            );
            foreach ( (array) $rows as $row ) {
                $summaries[ (int) $row['post_id'] ] = (string) $row['resume_court'];
            }
        }

        $items = [];
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }

            $raw_title   = get_post_field( 'post_title', $post_id );
            $prefix      = '';
            $clean_title = $raw_title;
            if ( preg_match( '/^([A-Z]+\d+)\s*[\x{2013}\x{2014}\-]+\s*/u', $raw_title, $m ) ) {
                $prefix      = $m[1];
                $clean_title = preg_replace( '/^[A-Z]+\d+\s*[\x{2013}\x{2014}\-]+\s*/u', '', $raw_title );
            }

            $cats     = get_the_category( $post_id );
            $cat      = ! empty( $cats ) ? $cats[0] : null;
            $summary  = html_entity_decode( trim( $summaries[ $post_id ] ?? '' ), ENT_QUOTES, 'UTF-8' );
            if ( $summary === '' ) {
                $summary = wp_trim_words( wp_strip_all_tags( $post->post_content ), 24, '…' );
            } else {
                $summary = wp_trim_words( $summary, 24, '…' );
            }

            $thumb_id  = get_post_thumbnail_id( $post_id );
            $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';

            $items[] = [
                'id'        => $post_id,
                'title'     => html_entity_decode( $clean_title, ENT_QUOTES, 'UTF-8' ),
                'prefix'    => $prefix,
                'url'       => get_permalink( $post_id ),
                'excerpt'   => $summary,
                'thumbnail' => $thumb_url ?: '',
                'category'  => $cat ? [
                    'name' => schilo_strip_category_number( $cat->name ),
                    'url'  => get_category_link( $cat->term_id ),
                ] : null,
            ];
        }

        return $items;
    }
}
