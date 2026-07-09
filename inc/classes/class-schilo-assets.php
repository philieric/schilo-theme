<?php
/**
 * Gestion de tous les assets CSS et JS du thème Schilo.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Assets {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'enqueue' ] );
        add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'dequeue_blocks' ], 100 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'dequeue_gutenberg_admin' ], 100 );
        add_action( 'wp_head',               [ __CLASS__, 'enqueue_gtranslate_early' ], 1 );
        add_action( 'wp_footer',             [ __CLASS__, 'inject_gtranslate_widget' ], 5 );
        add_action( 'login_enqueue_scripts', [ __CLASS__, 'enqueue_login' ] );
    }

    /**
     * Retourne les 8 premiers caractères du hash MD5 du fichier.
     * Indépendant de tout cache système (stat cache, OPcache).
     * La version change automatiquement dès que le contenu du fichier change.
     */
    private static function ver( string $path ): string {
        return substr( md5_file( $path ), 0, 8 );
    }

    /**
     * Enqueue principal — styles et scripts front-end.
     */
    public static function enqueue(): void {
        $dir = SCHILO_DIR;

        // ── Styles ──────────────────────────────────────────────

        wp_enqueue_style(
            'schilo-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Lora:ital,wght@0,400;0,500;1,400&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'tabler-icons',
            'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css',
            [],
            '2.44.0'
        );

        wp_enqueue_style(
            'schilo-compat',
            SCHILO_ASSETS . '/css/compat.css',
            [ 'schilo-fonts', 'tabler-icons' ],
            self::ver( $dir . '/assets/css/compat.css' )
        );

        wp_enqueue_style(
            'schilo-main',
            get_stylesheet_uri(),
            [ 'schilo-fonts', 'tabler-icons', 'schilo-compat' ],
            self::ver( $dir . '/style.css' )
        );

        $responsive_dependencies = [ 'schilo-main' ];

        if ( is_front_page() ) {
            wp_enqueue_style(
                'schilo-home',
                SCHILO_ASSETS . '/css/home.css',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/css/home.css' )
            );
            $responsive_dependencies = [ 'schilo-home' ];
        }

        wp_enqueue_style(
            'schilo-responsive',
            SCHILO_ASSETS . '/css/responsive.css',
            $responsive_dependencies,
            self::ver( $dir . '/assets/css/responsive.css' )
        );

        // Modale de recherche : le bouton déclencheur est dans le header, présent sur toutes les pages
        wp_enqueue_style(
            'schilo-search-modal',
            SCHILO_ASSETS . '/css/search-modal.css',
            [ 'schilo-main' ],
            self::ver( $dir . '/assets/css/search-modal.css' )
        );

        // ── Scripts ─────────────────────────────────────────────

        wp_enqueue_script(
            'schilo-polyfills',
            SCHILO_ASSETS . '/js/schilo-polyfills.js',
            [],
            self::ver( $dir . '/assets/js/schilo-polyfills.js' ),
            false
        );

        wp_enqueue_script(
            'schilo-main',
            SCHILO_ASSETS . '/js/schilo.js',
            [ 'schilo-polyfills' ],
            self::ver( $dir . '/assets/js/schilo.js' ),
            true
        );

        wp_enqueue_script(
            'schilo-lang',
            SCHILO_ASSETS . '/js/schilo-lang.js',
            [],
            self::ver( $dir . '/assets/js/schilo-lang.js' ),
            true
        );

        wp_localize_script( 'schilo-main', 'schiloData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'schilo_nonce' ),
            'homeUrl'  => home_url( '/' ),
            'themeUrl' => SCHILO_URI,
            'version'  => SCHILO_VERSION,
        ] );

        $translator_config = class_exists( 'Schilo_Translator' ) ? Schilo_Translator::get_config() : [];
        wp_localize_script( 'schilo-lang', 'schiloTranslator', [
            'activeProvider'  => $translator_config['active_provider'] ?? 'google',
            'selectorEnabled' => $translator_config['selector_enabled'] ?? true,
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'schilo_translate' ),
            /* true si le fournisseur actif est "sur place" (Microsoft ou
               Google Cloud) et correctement configuré/activé */
            'inPlaceReady'    => class_exists( 'Schilo_Translator' ) && Schilo_Translator::is_in_place_ready( $translator_config ),
        ] );

        wp_enqueue_script(
            'schilo-search-modal',
            SCHILO_ASSETS . '/js/search-modal.js',
            [ 'schilo-main' ],
            self::ver( $dir . '/assets/js/search-modal.js' ),
            true
        );

        // ── Conditionnels ────────────────────────────────────────

        if ( is_page_template( 'page-contact.php' ) ) {
            wp_enqueue_script(
                'schilo-contact',
                SCHILO_ASSETS . '/js/schilo-contact.js',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/js/schilo-contact.js' ),
                true
            );
        }

        if ( is_archive() || is_category() || is_tag() || is_search() ) {
            wp_enqueue_style(
                'schilo-archive',
                SCHILO_ASSETS . '/css/archive.css',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/css/archive.css' )
            );
        }

        if ( is_singular( 'post' ) ) {
            wp_enqueue_style(
                'schilo-single',
                SCHILO_ASSETS . '/css/single.css',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/css/single.css' )
            );
            wp_enqueue_script(
                'schilo-single',
                SCHILO_ASSETS . '/js/schilo-single.js',
                [],
                self::ver( $dir . '/assets/js/schilo-single.js' ),
                true
            );
        }

        if ( is_page_template( 'page-avancements.php' ) ) {
            wp_enqueue_style(
                'schilo-avancements',
                SCHILO_ASSETS . '/css/avancements.css',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/css/avancements.css' )
            );
        }

        // La page de recherche reutilise la barre d'outils / cartes / pagination
        // des archives (schilo-archive.css, enqueue plus haut) pour une UX identique.

        if ( is_page_template( 'page-parcours.php' ) || is_tax( [ 'schilo_parcours', 'schilo_theme', 'schilo_serie' ] ) || get_query_var( 'schilo_index_field' ) !== '' ) {
            wp_enqueue_style(
                'schilo-parcours',
                SCHILO_ASSETS . '/css/parcours.css',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/css/parcours.css' )
            );
            wp_enqueue_script(
                'schilo-parcours-modal',
                SCHILO_ASSETS . '/js/parcours-modal.js',
                [],
                self::ver( $dir . '/assets/js/parcours-modal.js' ),
                true
            );
        }

        if ( is_page_template( 'page-sitemap.php' ) ) {
            wp_enqueue_style(
                'schilo-sitemap',
                SCHILO_ASSETS . '/css/sitemap.css',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/css/sitemap.css' )
            );
            wp_enqueue_script(
                'schilo-sitemap',
                SCHILO_ASSETS . '/js/sitemap.js',
                [ 'schilo-main' ],
                self::ver( $dir . '/assets/js/sitemap.js' ),
                true
            );
        }
    }

    /**
     * Supprime les styles Gutenberg / block editor en front.
     */
    public static function dequeue_blocks(): void {
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'wc-block-style' );
        wp_dequeue_style( 'global-styles' );
        wp_dequeue_style( 'classic-theme-styles' );
    }

    /**
     * Supprime les scripts Gutenberg inutiles en back-office.
     */
    public static function dequeue_gutenberg_admin( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        wp_dequeue_script( 'wp-blocks' );
        wp_dequeue_script( 'wp-element' );
        wp_dequeue_script( 'wp-block-editor' );
    }

    /**
     * Enqueue styles page de connexion WordPress.
     */
    public static function enqueue_login(): void {
        $dir = SCHILO_DIR;

        wp_enqueue_style(
            'schilo-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'tabler-icons',
            'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css',
            [],
            '2.44.0'
        );

        wp_enqueue_style(
            'schilo-compat',
            SCHILO_ASSETS . '/css/compat.css',
            [ 'schilo-fonts', 'tabler-icons' ],
            self::ver( $dir . '/assets/css/compat.css' )
        );

        wp_enqueue_style(
            'schilo-login',
            SCHILO_ASSETS . '/css/login.css',
            [ 'schilo-compat' ],
            self::ver( $dir . '/assets/css/login.css' )
        );
    }

    /**
     * Injecte le CSS GTranslate très tôt dans le <head> (priorité 1)
     * pour éviter un flash du widget natif avant masquage.
     */
    public static function enqueue_gtranslate_early(): void {
        $file = SCHILO_DIR . '/assets/css/gtranslate.css';
        $css  = file_get_contents( $file );
        if ( $css ) {
            echo '<style id="schilo-gtranslate-css">' . $css . '</style>' . "\n";
        }
    }

    /**
     * Injecte le widget GTranslate caché dans le footer
     * pour que le JS custom puisse cliquer ses liens data-gt-lang.
     */
    public static function inject_gtranslate_widget(): void {
        if ( ! function_exists( 'do_shortcode' ) ) {
            return;
        }
        echo '<div id="schilo-gt-hidden" aria-hidden="true">';
        echo do_shortcode( '[GTranslate]' );
        echo '</div>';
    }
}
