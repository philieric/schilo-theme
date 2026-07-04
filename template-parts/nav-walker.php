<?php
defined( 'ABSPATH' ) || exit;
/**
 * Schilo Nav Walker
 * Walker personnalisé pour la navigation principale.
 * Fichier inclus dans functions.php
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walker qui génère des <a> simples sans <ul>/<li>
 * pour s'intégrer au layout flex du header Schilo.
 */
class Schilo_Nav_Walker extends Walker_Nav_Menu {

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes  = empty( $item->classes ) ? [] : (array) $item->classes;
        $is_current = in_array( 'current-menu-item', $classes, true )
                   || in_array( 'current_page_item', $classes, true );

        $atts              = [];
        $atts['href']      = ! empty( $item->url ) ? $item->url : '#';
        $atts['title']     = ! empty( $item->attr_title ) ? $item->attr_title : '';
        $atts['target']    = ! empty( $item->target ) ? $item->target : '';
        $atts['rel']       = ! empty( $item->xfn ) ? $item->xfn : '';
        $atts['aria-current'] = $is_current ? 'page' : '';
        if ( $is_current ) {
            $atts['class'] = 'current-menu-item';
        }

        $atts   = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );
        $attr_str = '';
        foreach ( $atts as $attr => $value ) {
            if ( '' !== $value ) {
                $attr_str .= ' ' . $attr . '="' . esc_attr( $value ) . '"';
            }
        }

        $title  = apply_filters( 'the_title', $item->title, $item->ID );
        $output .= '<a' . $attr_str . '>' . esc_html( $title ) . '</a>';
    }

    // Pas de li, ul, div — juste les liens
    public function start_lvl( &$output, $depth = 0, $args = null ) {}
    public function end_lvl( &$output, $depth = 0, $args = null ) {}
    public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}

/**
 * Fallback navigation si aucun menu n'est assigné.
 */
function schilo_fallback_nav() {
    $pages = get_pages( [ 'sort_column' => 'menu_order' ] );
    foreach ( $pages as $page ) {
        $current = is_page( $page->ID ) ? ' class="current-menu-item" aria-current="page"' : '';
        echo '<a href="' . esc_url( get_permalink( $page->ID ) ) . '"' . $current . '>'
           . esc_html( $page->post_title )
           . '</a>';
    }
}
