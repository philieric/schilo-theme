<?php
/**
 * Balises meta SEO du thème Schilo.
 * Gère : description, Open Graph, Twitter Cards, canonical, Schema.org JSON-LD.
 * S'active automatiquement si aucun plugin SEO (Yoast, RankMath) n'est présent.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Meta {

    public static function init(): void {
        // Ne pas interférer avec les plugins SEO
        if ( self::seo_plugin_active() ) return;

        add_action( 'wp_head', [ __CLASS__, 'render_meta' ],     1 );
        add_action( 'wp_head', [ __CLASS__, 'render_json_ld' ],  2 );
        add_filter( 'document_title_separator', fn() => '—' );
        add_filter( 'document_title_parts',     [ __CLASS__, 'title_parts' ] );
        add_filter( 'wp_robots',                [ __CLASS__, 'filter_robots' ] );
    }

    // ── Retire le prefixe de reference interne ("PER001 - ", "ANN002 – "...)
    //    des titres exposes aux moteurs de recherche et reseaux sociaux.
    private static function clean_title( string $title ): string {
        // wptexturize() transforme "-" en entite "&#8211;" dans get_the_title() : on decode avant de matcher
        $decoded = html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
        return trim( preg_replace( '/^[A-Z]{2,5}\d{2,4}(-[A-Z])?\s*[-–—]\s*/u', '', $decoded ) );
    }

    // ── Détection plugin SEO actif ───────────────────────────────
    private static function seo_plugin_active(): bool {
        return defined( 'WPSEO_VERSION' )           // Yoast SEO
            || defined( 'RANK_MATH_VERSION' )       // RankMath
            || defined( 'AIOSEOP_VERSION' )         // All in One SEO
            || function_exists( 'the_seo_framework' );
    }

    // ── Robots (fusionne les directives WP core avec l'indexation Schilo) ──
    public static function filter_robots( array $robots ): array {
        $indexed = self::get_indexed_field( 'robots' );
        if ( $indexed ) {
            foreach ( explode( ',', $indexed ) as $directive ) {
                $directive = trim( $directive );
                if ( $directive !== '' ) $robots[ $directive ] = true;
            }
            return $robots;
        }

        if ( is_search() || is_404() || is_author() || is_paged() ) {
            $robots['noindex'] = true;
        }
        return $robots;
    }

    // ── Format du titre WordPress ───────────────────────────────
    public static function title_parts( array $parts ): array {
        // Retirer la tagline de la home si le titre est déjà explicite
        if ( is_front_page() ) unset( $parts['tagline'] );

        if ( is_singular( 'post' ) ) {
            $seo_titre      = self::get_indexed_field( 'seo_titre' );
            $parts['title'] = self::clean_title( $seo_titre ?: ( $parts['title'] ?? get_the_title() ) );
        }

        return $parts;
    }

    // ── Indexation Schilo : ligne validée pour l'article courant ──
    private static function get_indexation(): ?array {
        static $cache  = [];
        static $loaded = [];

        if ( ! is_singular( 'post' ) ) return null;

        $post_id = get_the_ID();
        if ( ! $post_id ) return null;

        if ( isset( $loaded[ $post_id ] ) ) return $cache[ $post_id ];
        $loaded[ $post_id ] = true;

        if ( ! class_exists( '\Schilo\Builder\Service\IndexationService' ) ) {
            return $cache[ $post_id ] = null;
        }

        $service = new \Schilo\Builder\Service\IndexationService();
        $row     = $service->getByPostId( $post_id );

        // Seuls les articles validés par un humain alimentent le SEO public
        if ( ! $row || ( $row['statut_indexation'] ?? '' ) !== 'valide' ) {
            return $cache[ $post_id ] = null;
        }

        return $cache[ $post_id ] = $row;
    }

    private static function get_indexed_field( string $field ): string {
        $row = self::get_indexation();
        if ( ! $row || empty( $row[ $field ] ) ) return '';
        return (string) $row[ $field ];
    }

    private static function get_indexed_json_field( string $field ): array {
        $row = self::get_indexation();
        if ( ! $row || empty( $row[ $field ] ) ) return [];
        $decoded = json_decode( (string) $row[ $field ], true );
        return is_array( $decoded ) ? $decoded : [];
    }

    // ── Balises meta principales ─────────────────────────────────
    public static function render_meta(): void {
        $desc      = self::get_description();
        $og_title  = self::clean_title( self::get_indexed_field( 'og_titre' ) ?: self::get_title() );
        $og_desc   = self::get_indexed_field( 'og_description' ) ?: $desc;
        $image     = self::get_image();
        $url       = self::get_canonical();
        $site_name = esc_html( get_bloginfo( 'name' ) );
        $type      = is_singular( 'post' ) ? 'article' : 'website';

        // ── Canonical
        echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";

        // ── Meta description
        if ( $desc ) {
            echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
        }

        // ── Open Graph
        echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_locale() ) ) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
        if ( $og_desc ) {
            echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
        if ( $image ) {
            echo '<meta property="og:image" content="' . esc_url( $image['url'] ) . '">' . "\n";
            if ( ! empty( $image['width'] ) )  echo '<meta property="og:image:width" content="' . esc_attr( $image['width'] ) . '">' . "\n";
            if ( ! empty( $image['height'] ) ) echo '<meta property="og:image:height" content="' . esc_attr( $image['height'] ) . '">' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr( $image['alt'] ) . '">' . "\n";
        }
        if ( $type === 'article' ) {
            echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
        }

        // ── Twitter Cards
        echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '">' . "\n";
        if ( $og_desc ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
        }
        if ( $image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image['url'] ) . '">' . "\n";
        }
    }

    // ── Schema.org JSON-LD ────────────────────────────────────────
    public static function render_json_ld(): void {
        $blocks = [];

        if ( is_front_page() ) {
            $blocks[] = self::schema_website();
        } elseif ( is_singular( 'post' ) ) {
            $blocks[] = self::schema_article();
            $blocks[] = self::schema_breadcrumb_single();
        } elseif ( is_category() || is_tag() || is_archive() ) {
            $blocks[] = self::schema_breadcrumb();
        }

        foreach ( array_filter( $blocks ) as $data ) {
            echo '<script type="application/ld+json">'
                . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
                . '</script>' . "\n";
        }
    }

    // ── Helpers privés ─────────────────────────────────────────────

    private static function get_title(): string {
        if ( is_front_page() ) {
            return get_bloginfo( 'name' ) . ' — ' . get_bloginfo( 'description' );
        }
        if ( is_singular() ) {
            $seo_titre = self::get_indexed_field( 'seo_titre' );
            return self::clean_title( $seo_titre ?: get_the_title() );
        }
        if ( is_category() || is_tag() ) {
            $term = get_queried_object();
            return $term instanceof WP_Term ? $term->name : get_bloginfo( 'name' );
        }
        if ( is_search() ) {
            return sprintf( __( 'Résultats pour « %s »', 'schilo' ), get_search_query() );
        }
        if ( is_404() ) {
            return __( 'Page introuvable', 'schilo' );
        }
        return get_bloginfo( 'name' );
    }

    private static function get_description(): string {
        if ( is_front_page() ) {
            return (string) get_bloginfo( 'description' );
        }
        if ( is_singular() ) {
            $seo_desc = self::get_indexed_field( 'seo_description' );
            if ( $seo_desc ) return $seo_desc;

            global $post;
            if ( $post ) {
                $meta = get_post_meta( $post->ID, '_schilo_meta_desc', true );
                if ( $meta ) return $meta;
                return wp_strip_all_tags( get_the_excerpt( $post ) );
            }
        }
        if ( is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term && $term->description ) {
                return wp_strip_all_tags( $term->description );
            }
        }
        return '';
    }

    private static function get_image(): ?array {
        $indexed_image = self::get_indexed_field( 'og_image_url' );
        if ( $indexed_image ) {
            return [
                'url'    => $indexed_image,
                'width'  => 0,
                'height' => 0,
                'alt'    => self::get_indexed_field( 'og_titre' ) ?: get_the_title(),
            ];
        }

        if ( is_singular() && has_post_thumbnail() ) {
            $id   = get_post_thumbnail_id();
            $src  = wp_get_attachment_image_src( $id, 'large' );
            $meta = wp_get_attachment_metadata( $id );
            if ( $src ) {
                return [
                    'url'    => $src[0],
                    'width'  => $src[1],
                    'height' => $src[2],
                    'alt'    => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title(),
                ];
            }
        }
        // Image par défaut : logo du site
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $src = wp_get_attachment_image_src( $logo_id, 'large' );
            if ( $src ) {
                return [
                    'url'    => $src[0],
                    'width'  => $src[1],
                    'height' => $src[2],
                    'alt'    => get_bloginfo( 'name' ),
                ];
            }
        }
        return null;
    }

    private static function get_canonical(): string {
        if ( is_front_page() ) return home_url( '/' );
        if ( is_singular() ) {
            $indexed_canonical = self::get_indexed_field( 'canonical_url' );
            return $indexed_canonical ?: (string) get_permalink();
        }
        if ( is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $link = get_term_link( $term );
                return is_wp_error( $link ) ? home_url( '/' ) : $link;
            }
        }
        if ( is_search() ) {
            return add_query_arg( 's', get_search_query(), home_url( '/' ) );
        }
        return home_url( '/' );
    }

    // ── Schémas JSON-LD ───────────────────────────────────────────

    private static function schema_website(): array {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => get_bloginfo( 'name' ),
            'description'     => get_bloginfo( 'description' ),
            'url'             => home_url( '/' ),
            'inLanguage'      => get_locale(),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url( '/?s={search_term_string}' ),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private static function schema_article(): array {
        global $post;
        if ( ! $post ) return [];

        // Schema entierement fourni par l'indexation Schilo : on l'utilise tel quel
        $custom_schema = self::get_indexed_json_field( 'schema_json' );
        if ( $custom_schema ) {
            $custom_schema['@context'] = $custom_schema['@context'] ?? 'https://schema.org';
            $custom_schema['@type']    = $custom_schema['@type']    ?? 'Article';
            return $custom_schema;
        }

        $image   = self::get_image();
        $schema  = [
            '@context'         => 'https://schema.org',
            '@type'            => self::get_indexed_field( 'schema_type' ) ?: 'Article',
            'headline'         => self::clean_title( self::get_indexed_field( 'seo_titre' ) ?: get_the_title() ),
            'url'              => (string) get_permalink(),
            'datePublished'    => get_the_date( 'c' ),
            'dateModified'     => get_the_modified_date( 'c' ),
            'inLanguage'       => get_locale(),
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url( '/' ),
            ],
        ];

        $desc = self::get_description();
        if ( $desc ) $schema['description'] = $desc;

        if ( $image ) {
            $schema['image'] = array_filter( [
                '@type'  => 'ImageObject',
                'url'    => $image['url'],
                'width'  => $image['width']  ?: null,
                'height' => $image['height'] ?: null,
            ] );
        }

        // Mots-cles de l'indexation Schilo, sinon catégories WordPress
        $mots_cles = self::get_indexed_json_field( 'mots_cles' );
        if ( $mots_cles ) {
            $schema['keywords'] = implode( ', ', $mots_cles );
        } else {
            $cats = get_the_category( $post->ID );
            if ( $cats ) {
                $schema['keywords'] = implode( ', ', array_map( fn( $c ) => $c->name, $cats ) );
            }
        }

        return $schema;
    }

    private static function schema_breadcrumb_single(): array {
        global $post;
        if ( ! $post ) return [];

        $items = [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => __( 'Accueil', 'schilo' ),
                'item'     => home_url( '/' ),
            ],
        ];

        $cats = get_the_category( $post->ID );
        if ( ! empty( $cats[0] ) ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $cats[0]->name,
                'item'     => (string) get_category_link( $cats[0] ),
            ];
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => count( $items ) + 1,
            'name'     => self::get_title(),
            'item'     => (string) get_permalink( $post ),
        ];

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private static function schema_breadcrumb(): array {
        $term = get_queried_object();
        if ( ! $term instanceof WP_Term ) return [];

        $items = [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => __( 'Accueil', 'schilo' ),
                'item'     => home_url( '/' ),
            ],
        ];

        // Catégorie parente
        if ( $term->parent ) {
            $parent = get_term( $term->parent, $term->taxonomy );
            if ( $parent && ! is_wp_error( $parent ) ) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $parent->name,
                    'item'     => (string) get_term_link( $parent ),
                ];
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => $term->name,
                    'item'     => (string) get_term_link( $term ),
                ];
            }
        } else {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $term->name,
                'item'     => (string) get_term_link( $term ),
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
