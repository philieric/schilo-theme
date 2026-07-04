<?php
/**
 * Habillage de la page de connexion WordPress aux couleurs de Schilo.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Login {

    public static function init(): void {
        add_action( 'login_header', [ __CLASS__, 'render_header' ] );
        add_action( 'login_footer', [ __CLASS__, 'render_footer' ] );
        add_filter( 'login_headerurl', [ __CLASS__, 'header_url' ] );
        add_filter( 'login_headertext', [ __CLASS__, 'header_text' ] );
        add_filter( 'login_display_language_dropdown', '__return_false' );
    }

    /**
     * Retourne l'accueil pour le lien du logo WordPress.
     */
    public static function header_url(): string {
        return home_url( '/' );
    }

    /**
     * Retourne le nom du site pour le libellé accessible du logo.
     */
    public static function header_text(): string {
        return get_bloginfo( 'name' );
    }

    /**
     * Affiche l'en-tête compact de la page de connexion.
     */
    public static function render_header(): void {
        ?>
        <header class="schilo-login-header" role="banner">
            <div class="schilo-login-header__inner">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="schilo-login-brand" rel="home">
                    <span class="schilo-login-brand__mark" aria-hidden="true">
                        <i class="ti ti-flame"></i>
                    </span>
                    <span class="schilo-login-brand__text">
                        <span class="schilo-login-brand__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                        <span class="schilo-login-brand__tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></span>
                    </span>
                </a>

                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="schilo-login-header__back">
                    <i class="ti ti-arrow-left" aria-hidden="true"></i>
                    <span><?php esc_html_e( 'Retour au site', 'schilo' ); ?></span>
                </a>
            </div>
        </header>
        <?php
    }

    /**
     * Affiche le pied de page compact de la page de connexion.
     */
    public static function render_footer(): void {
        $contact_url = self::_find_page_url(
            'page-contact.php',
            [ 'contactez-nous', 'contact', 'nous-contacter' ],
            '/contactez-nous/'
        );
        ?>
        <footer class="schilo-login-footer" role="contentinfo">
            <div class="schilo-login-footer__inner">
                <div class="schilo-login-footer__top">
                    <div class="schilo-login-footer__identity">
                        <span class="schilo-login-footer__mark" aria-hidden="true">
                            <i class="ti ti-flame"></i>
                        </span>
                        <div>
                            <div class="schilo-login-footer__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
                            <div class="schilo-login-footer__description">
                                <?php esc_html_e( 'Découvrir Jésus à travers les quatre Évangiles.', 'schilo' ); ?>
                            </div>
                        </div>
                    </div>

                    <nav class="schilo-login-footer__links" aria-label="<?php esc_attr_e( 'Liens utiles', 'schilo' ); ?>">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                            <?php esc_html_e( 'Accueil', 'schilo' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $contact_url ); ?>">
                            <?php esc_html_e( 'Contactez-nous', 'schilo' ); ?>
                        </a>
                    </nav>
                </div>

                <div class="schilo-login-footer__bottom">
                    <div class="schilo-login-footer__copy">
                        &copy; <?php echo esc_html( wp_date( 'Y' ) ); ?>
                        <?php echo esc_html( get_bloginfo( 'name' ) ); ?> —
                        <?php esc_html_e( 'Tous droits réservés', 'schilo' ); ?>
                    </div>
                    <div class="schilo-login-footer__legend" aria-label="<?php esc_attr_e( 'Les quatre Évangiles', 'schilo' ); ?>">
                        <?php self::_render_legend_item( 'mat', __( 'Matthieu', 'schilo' ) ); ?>
                        <?php self::_render_legend_item( 'marc', __( 'Marc', 'schilo' ) ); ?>
                        <?php self::_render_legend_item( 'luc', __( 'Luc', 'schilo' ) ); ?>
                        <?php self::_render_legend_item( 'jean', __( 'Jean', 'schilo' ) ); ?>
                    </div>
                </div>
            </div>
        </footer>
        <?php
    }

    /**
     * Cherche une page par template puis par slug avant d'utiliser le fallback.
     */
    private static function _find_page_url( string $template, array $slugs, string $fallback ): string {
        $pages = get_pages( [
            'meta_key'   => '_wp_page_template',
            'meta_value' => $template,
        ] );

        if ( ! empty( $pages ) ) {
            return get_permalink( $pages[0]->ID );
        }

        foreach ( $slugs as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page ) {
                return get_permalink( $page->ID );
            }
        }

        return home_url( $fallback );
    }

    /**
     * Affiche un élément de la légende des Évangiles.
     */
    private static function _render_legend_item( string $evangel, string $label ): void {
        ?>
        <span class="schilo-login-footer__legend-item">
            <span class="schilo-login-footer__legend-dot schilo-login-footer__legend-dot--<?php echo esc_attr( $evangel ); ?>" aria-hidden="true"></span>
            <?php echo esc_html( $label ); ?>
        </span>
        <?php
    }
}
