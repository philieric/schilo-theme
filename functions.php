<?php
/**
 * Schilo Theme Ã¢â‚¬â€ functions.php
 */
defined( 'ABSPATH' ) || exit;

define( 'SCHILO_VERSION', '1.1.20' );
define( 'SCHILO_DIR',     get_template_directory() );
define( 'SCHILO_URI',     get_template_directory_uri() );
define( 'SCHILO_ASSETS',  SCHILO_URI . '/assets' );

// Ã¢â€â‚¬Ã¢â€â‚¬ Schilo Builder (intÃƒÂ©grÃƒÂ© au thÃƒÂ¨me) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
if ( ! defined( 'SCHILO_BUILDER_VERSION' ) ) {
    define( 'SCHILO_BUILDER_VERSION', '1.6.0' );
    define( 'SCHILO_BUILDER_PATH',    SCHILO_DIR . '/inc/builder/' );
    define( 'SCHILO_BUILDER_URL',     SCHILO_URI . '/inc/builder/' );

    spl_autoload_register( function ( string $class ): void {
        $prefix  = 'Schilo\\Builder\\';
        $baseDir = SCHILO_BUILDER_PATH . 'src/';
        if ( strpos( $class, $prefix ) !== 0 ) return;
        $rel  = substr( $class, strlen( $prefix ) );
        $file = $baseDir . str_replace( '\\', '/', $rel ) . '.php';
        if ( file_exists( $file ) ) require_once $file;
    } );

    // Appel direct Ã¢â‚¬â€ Plugin::run() ne fait qu'enregistrer des hooks (admin_menu, save_postÃ¢â‚¬Â¦)
    // qui tirent tous plus tard, donc l'appel depuis functions.php est sÃƒÂ»r.
    ( new \Schilo\Builder\Core\Plugin() )->run();
}

require_once SCHILO_DIR . '/inc/helpers.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-table.php';
Schilo_Table::init();
require_once SCHILO_DIR . '/inc/classes/class-schilo-setup.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-assets.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-login.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-security.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-meta.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-visitors.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-bible.php';
require_once SCHILO_DIR . '/inc/classes/class-schilo-featured.php';
require_once SCHILO_DIR . '/template-parts/nav-walker.php';

Schilo_Setup::init();
Schilo_Assets::init();

// Ã¢â€â‚¬Ã¢â€â‚¬ Favicon SVG (ti-flame sur fond #121c2e) Ã¢â‚¬â€ front + admin Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
add_action( 'wp_head',    'schilo_inject_favicon' );
add_action( 'admin_head', 'schilo_inject_favicon' );
function schilo_inject_favicon() {
    $url = SCHILO_ASSETS . '/img/favicon.svg';
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $url ) . '">' . "\n";
    echo '<link rel="alternate icon" href="' . esc_url( $url ) . '">' . "\n";
}
// DÃƒÂ©sactive le favicon WordPress (customizer) pour ÃƒÂ©viter le doublon
add_filter( 'get_site_icon_url', '__return_empty_string' );

// Ã¢â€â‚¬Ã¢â€â‚¬ Notices admin : supprimer tout sauf les confirmations de sauvegarde Ã¢â€â‚¬Ã¢â€â‚¬
add_action( 'admin_head', function () {
    // Les messages natifs WP de sauvegarde/publication passent par $_GET['message']
    // sur edit.php et post.php Ã¢â‚¬â€ on les laisse. Tout le reste (plugins, thÃƒÂ¨mes) est masquÃƒÂ©.
    echo '<style>
        /* Masque tous les admin-notices sauf ceux gÃƒÂ©nÃƒÂ©rÃƒÂ©s par WP core (post save) */
        .notice,
        .update-nag,
        .updated:not(.schilo-keep),
        .error:not(.schilo-keep),
        div.update-message,
        #wpbody .vc_welcome-notice { display:none !important; }
    </style>' . "\n";
}, 1 );

// Supprime les hooks de notices des plugins tiers avant qu'ils s'exÃƒÂ©cutent
add_action( 'admin_init', function () {
    // WPBakery notices
    remove_action( 'admin_notices', array( 'Vc_Updates_Manager', 'showUpdateNagger' ) );
    remove_action( 'admin_notices', array( 'WPBakeryVisualComposer', 'addNotice' ) );
    // Suppression gÃƒÂ©nÃƒÂ©rique : retirer toutes les actions admin_notices de plugins/thÃƒÂ¨mes tiers
    // en prÃƒÂ©servant uniquement celles du core WP (vide par dÃƒÂ©faut Ã¢â‚¬â€ WP utilise $_GET['message'])
    global $wp_filter;
    if ( isset( $wp_filter['admin_notices'] ) ) {
        foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $key => $callback ) {
                $fn = $callback['function'];
                // Garder les callbacks du core WP (fichiers dans wp-admin/ ou wp-includes/)
                $file = '';
                try {
                    if ( is_array( $fn ) ) {
                        $ref  = new \ReflectionMethod( $fn[0], $fn[1] );
                    } elseif ( $fn instanceof \Closure ) {
                        $ref  = new \ReflectionFunction( $fn );
                    } else {
                        $ref  = new \ReflectionFunction( $fn );
                    }
                    $file = wp_normalize_path( $ref->getFileName() );
                } catch ( \Exception $e ) {
                    // ignore
                }
                $wp_core = wp_normalize_path( ABSPATH );
                // Si le callback vient d'un plugin ou du thÃƒÂ¨me Ã¢â€ â€™ supprimer
                if ( $file && strpos( $file, $wp_core . 'wp-content/' ) !== false ) {
                    unset( $wp_filter['admin_notices']->callbacks[ $priority ][ $key ] );
                }
            }
        }
    }
}, 99 );
Schilo_Login::init();
Schilo_Meta::init();
Schilo_Visitors::init();
add_action( 'init', [ 'Schilo_Security', 'init' ] );

// Ã¢â€â‚¬Ã¢â€â‚¬ Admin : page d'ÃƒÂ©dition article Ã¢â‚¬â€ layout moderne Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'post' ) return;

    wp_enqueue_style(
        'schilo-post-edit',
        SCHILO_ASSETS . '/css/admin-post-edit.css',
        [],
        SCHILO_VERSION
    );
    wp_enqueue_script(
        'schilo-post-edit',
        SCHILO_ASSETS . '/js/admin-post-edit.js',
        [],
        SCHILO_VERSION,
        true
    );
} );

// -- Admin : nettoyage automatique du collage Word dans l'editeur classique --
// -- + boutons H1-H6 / P a la place du menu deroulant "Paragraphe / Titre" --
add_filter( 'mce_buttons', function ( array $buttons ): array {
    $heading_buttons = array( 'schilo_h1', 'schilo_h2', 'schilo_h3', 'schilo_h4', 'schilo_h5', 'schilo_h6', 'schilo_p' );
    $key = array_search( 'formatselect', $buttons, true );
    if ( false !== $key ) {
        array_splice( $buttons, $key, 1, $heading_buttons );
    } else {
        array_splice( $buttons, 0, 0, $heading_buttons );
    }
    $italic_key = array_search( 'italic', $buttons, true );
    if ( false !== $italic_key ) {
        array_splice( $buttons, $italic_key + 1, 0, array( 'underline' ) );
    }
    $buttons[] = 'pastetext';
    return $buttons;
} );

add_filter( 'tiny_mce_before_init', function ( array $init ): array {
    $init['setup'] = <<<'JS'
function (editor) {
    editor.on('PastePreProcess', function (e) {
        var html = e.content;
        html = html.replace(/<!--[\s\S]*?-->/g, '');
        html = html.replace(/<\/?[a-zA-Z]+:[a-zA-Z0-9]+[^>]*>/g, '');
        html = html.replace(/ (style|lang|class)="[^"]*"/gi, '');
        html = html.replace(/<p[^>]*>(\s|&nbsp;|<br\s*\/?>)*<\/p>/gi, '');
        html = html.replace(/<\/?span[^>]*>/gi, '');
        html = html.replace(/<font[^>]*>/gi, '').replace(/<\/font>/gi, '');
        e.content = html;
    });

    var schiloBlocks = [
        {name: 'schilo_h1', text: 'H1', tag: 'h1', tooltip: 'Titre 1'},
        {name: 'schilo_h2', text: 'H2', tag: 'h2', tooltip: 'Titre 2'},
        {name: 'schilo_h3', text: 'H3', tag: 'h3', tooltip: 'Titre 3'},
        {name: 'schilo_h4', text: 'H4', tag: 'h4', tooltip: 'Titre 4'},
        {name: 'schilo_h5', text: 'H5', tag: 'h5', tooltip: 'Titre 5'},
        {name: 'schilo_h6', text: 'H6', tag: 'h6', tooltip: 'Titre 6'},
        {name: 'schilo_p',  text: 'P',  tag: 'p',  tooltip: 'Paragraphe'}
    ];
    schiloBlocks.forEach(function (b) {
        editor.addButton(b.name, {
            text: b.text,
            tooltip: b.tooltip,
            classes: 'widget btn schilo-format-btn',
            onclick: function () {
                editor.execCommand('FormatBlock', false, b.tag);
            },
            onPostRender: function () {
                var btn = this;
                editor.on('NodeChange', function () {
                    btn.active(editor.formatter.match(b.tag));
                });
            }
        });
    });
}
JS;
    return $init;
}, 20 );



// Ã¢â€â‚¬Ã¢â€â‚¬ Export PDF : ?schilo_pdf=1 sur un article Ã¢â€ â€™ template fiche standalone Ã¢â€â‚¬Ã¢â€â‚¬
add_filter( 'template_include', function ( string $template ): string {
    if ( ! is_singular( 'post' ) ) return $template;
    if ( empty( $_GET['schilo_pdf'] ) ) return $template;
    $pdf_tpl = get_template_directory() . '/template-parts/pdf-article.php';
    return file_exists( $pdf_tpl ) ? $pdf_tpl : $template;
} );

// PrioritÃƒÂ© 1 : si l'article a des sections Schilo Builder, vider le post_content
// avant que do_shortcode (prioritÃƒÂ© 11) n'exÃƒÂ©cute les anciens shortcodes WPBakery/Wikilogy.
// ContentRenderer (prioritÃƒÂ© 20) prend ensuite le relais pour afficher les sections.
add_filter( 'the_content', function ( string $content ): string {
    if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }
    $sections = get_post_meta( get_the_ID(), '_schilo_builder_sections', true );
    if ( ! empty( $sections ) && is_array( $sections ) ) {
        return '';
    }
    return $content;
}, 1 );

