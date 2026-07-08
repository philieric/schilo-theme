<?php
/**
 * Recupere le sous-ensemble "de la semaine" (rotation periodique — Parcours &
 * Themes > Configuration) pour une taxonomie de premier niveau, meme logique
 * que template-parts/home-fallback.php. Retourne un tableau de WP_Term, vide
 * si la taxonomie/le service n'est pas disponible (le footer retombe alors
 * sur le menu WordPress statique correspondant, voir plus bas).
 */
if ( ! function_exists( 'schilo_footer_rotated_terms' ) ) :
function schilo_footer_rotated_terms( string $taxonomy, \Schilo\Builder\Service\ClassementService $service ): array {
    if ( ! taxonomy_exists( $taxonomy ) ) return [];

    $pool = get_terms( [
        'taxonomy'   => $taxonomy,
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'meta_value_num',
        'meta_key'   => 'schilo_ordre',
        'order'      => 'ASC',
    ] );
    if ( is_wp_error( $pool ) || empty( $pool ) ) return [];

    $pool_by_id  = array_column( $pool, null, 'term_id' );
    $rotated_ids = $service->getRotatedTermIds( $taxonomy, wp_list_pluck( $pool, 'term_id' ) );

    return array_values( array_filter( array_map( fn( $id ) => $pool_by_id[ $id ] ?? null, $rotated_ids ) ) );
}
endif;

$schilo_footer_classement = new \Schilo\Builder\Service\ClassementService();
$schilo_footer_parcours   = schilo_footer_rotated_terms( 'schilo_parcours', $schilo_footer_classement );
$schilo_footer_themes     = schilo_footer_rotated_terms( 'schilo_theme', $schilo_footer_classement );
?>
<footer class="schilo-footer" role="contentinfo">
  <div class="schilo-footer__inner">
    <div class="schilo-footer__grid">

      <!-- Brand + description -->
      <div>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="schilo-footer__brand">
          <div class="schilo-footer__brand-mark">
            <i class="ti ti-flame" aria-hidden="true"></i>
          </div>
          <div class="schilo-footer__brand-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
        </a>
        <p class="schilo-footer__desc">
          <?php esc_html_e( "Un site d'étude biblique consacré à la découverte de Jésus — à travers les quatre Évangiles, leurs contextes historiques et leurs messages intemporels.", 'schilo' ); ?>
        </p>
      </div>

      <!-- Menu footer 1 : Parcours de la semaine (rotation) -->
      <div>
        <div class="schilo-footer__col-title"><?php esc_html_e( 'Parcours', 'schilo' ); ?></div>
        <nav class="schilo-footer__links" aria-label="<?php esc_attr_e( 'Parcours', 'schilo' ); ?>">
          <?php if ( $schilo_footer_parcours ) : ?>
            <?php foreach ( $schilo_footer_parcours as $schilo_footer_term ) : ?>
              <a href="<?php echo esc_url( get_term_link( $schilo_footer_term, 'schilo_parcours' ) ); ?>">
                <?php echo esc_html( $schilo_footer_term->name ); ?>
              </a>
            <?php endforeach; ?>
          <?php else : ?>
            <?php wp_nav_menu( [ 'theme_location' => 'footer-1', 'container' => false, 'fallback_cb' => false, 'items_wrap' => '%3$s' ] ); ?>
          <?php endif; ?>
        </nav>
      </div>

      <!-- Menu footer 2 : Themes de la semaine (rotation) -->
      <div>
        <div class="schilo-footer__col-title"><?php esc_html_e( 'Thèmes', 'schilo' ); ?></div>
        <nav class="schilo-footer__links" aria-label="<?php esc_attr_e( 'Thèmes', 'schilo' ); ?>">
          <?php if ( $schilo_footer_themes ) : ?>
            <?php foreach ( $schilo_footer_themes as $schilo_footer_term ) : ?>
              <a href="<?php echo esc_url( get_term_link( $schilo_footer_term, 'schilo_theme' ) ); ?>">
                <?php echo esc_html( $schilo_footer_term->name ); ?>
              </a>
            <?php endforeach; ?>
          <?php else : ?>
            <?php wp_nav_menu( [ 'theme_location' => 'footer-2', 'container' => false, 'fallback_cb' => false, 'items_wrap' => '%3$s' ] ); ?>
          <?php endif; ?>
        </nav>
      </div>

      <!-- Menu footer 3 : Site -->
      <div>
        <div class="schilo-footer__col-title"><?php esc_html_e( 'Site', 'schilo' ); ?></div>
        <nav class="schilo-footer__links" aria-label="<?php esc_attr_e( 'Site', 'schilo' ); ?>">
          <?php wp_nav_menu( [ 'theme_location' => 'footer-3', 'container' => false, 'fallback_cb' => false, 'items_wrap' => '%3$s' ] ); ?>
          <?php
          // Lien À propos — détecté automatiquement
          $fap_url = home_url( '/a-propos/' );
          $by_tpl_ap = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-apropos.php' ] );
          if ( ! empty( $by_tpl_ap ) ) {
              $fap_url = get_permalink( $by_tpl_ap[0]->ID );
          } else {
              foreach ( [ 'a-propos', 'apropos', 'about' ] as $slug ) {
                  $p = get_page_by_path( $slug );
                  if ( $p ) { $fap_url = get_permalink( $p->ID ); break; }
              }
          }
          ?>
          <a href="<?php echo esc_url( $fap_url ); ?>">
            <?php esc_html_e( 'À propos', 'schilo' ); ?>
          </a>
          <?php
          // Lien Avancements — détecté automatiquement
          $fav_url = home_url( '/avancements/' );
          $by_tpl_av = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-avancements.php' ] );
          if ( ! empty( $by_tpl_av ) ) {
              $fav_url = get_permalink( $by_tpl_av[0]->ID );
          } else {
              foreach ( [ 'avancements', 'derniers-contenus', 'nouveautes' ] as $slug ) {
                  $p = get_page_by_path( $slug );
                  if ( $p ) { $fav_url = get_permalink( $p->ID ); break; }
              }
          }
          ?>
          <a href="<?php echo esc_url( $fav_url ); ?>">
            <?php esc_html_e( 'Avancements', 'schilo' ); ?>
          </a>
          <?php
          // Lien Plan du site — détecté automatiquement
          $fsm_url = home_url( '/plan-du-site/' );
          $by_tpl_sm = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-sitemap.php' ] );
          if ( ! empty( $by_tpl_sm ) ) {
              $fsm_url = get_permalink( $by_tpl_sm[0]->ID );
          } else {
              foreach ( [ 'plan-du-site', 'sitemap', 'plan-site' ] as $slug ) {
                  $p = get_page_by_path( $slug );
                  if ( $p ) { $fsm_url = get_permalink( $p->ID ); break; }
              }
          }
          ?>
          <a href="<?php echo esc_url( $fsm_url ); ?>">
            <?php esc_html_e( 'Plan du site', 'schilo' ); ?>
          </a>
          <?php
          // Lien Contact — détecté automatiquement
          $fc_url = home_url( '/contactez-nous/' );
          $by_tpl = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
          if ( ! empty( $by_tpl ) ) {
              $fc_url = get_permalink( $by_tpl[0]->ID );
          } else {
              foreach ( [ 'contactez-nous', 'contact', 'nous-contacter' ] as $slug ) {
                  $p = get_page_by_path( $slug );
                  if ( $p ) { $fc_url = get_permalink( $p->ID ); break; }
              }
          }
          ?>
          <a href="<?php echo esc_url( $fc_url ); ?>" class="schilo-footer__contact-link">
            <i class="ti ti-mail" aria-hidden="true"></i>
            <?php esc_html_e( 'Contactez-nous', 'schilo' ); ?>
          </a>
        </nav>
      </div>

    </div>

    <!-- Bas de footer -->
    <div class="schilo-footer__bottom">
      <div class="schilo-footer__copy">
        &copy; <?php echo esc_html( date( 'Y' ) ); ?>
        <?php echo esc_html( get_bloginfo( 'name' ) ); ?> —
        <?php esc_html_e( 'Tous droits réservés', 'schilo' ); ?>
      </div>
      <div class="schilo-footer__legend">
        <div class="schilo-footer__legend-item">
          <div class="schilo-footer__legend-dot" style="background:var(--schilo-mat)"></div>
          <?php esc_html_e( 'Matthieu', 'schilo' ); ?>
        </div>
        <div class="schilo-footer__legend-item">
          <div class="schilo-footer__legend-dot" style="background:var(--schilo-marc)"></div>
          <?php esc_html_e( 'Marc', 'schilo' ); ?>
        </div>
        <div class="schilo-footer__legend-item">
          <div class="schilo-footer__legend-dot" style="background:var(--schilo-luc)"></div>
          <?php esc_html_e( 'Luc', 'schilo' ); ?>
        </div>
        <div class="schilo-footer__legend-item">
          <div class="schilo-footer__legend-dot" style="background:var(--schilo-jean)"></div>
          <?php esc_html_e( 'Jean', 'schilo' ); ?>
        </div>
        <div class="schilo-footer__legend-item">
          <div class="schilo-footer__legend-dot" style="background:#8b14af"></div>
          <?php esc_html_e( 'Autres livres', 'schilo' ); ?>
        </div>
      </div>
    </div>

  </div>
</footer>

<?php if ( is_front_page() || is_archive() || is_category() || is_tag() || is_page_template( 'page-apropos.php' ) || is_page_template( 'page-contact.php' ) || is_page_template( 'page-avancements.php' ) ) : ?>
<div class="schilo-zoom-widget" role="group" aria-label="<?php esc_attr_e( 'Taille du texte', 'schilo' ); ?>">
    <button class="schilo-zoom-widget__btn" id="schilo-zoom-out" aria-label="<?php esc_attr_e( 'Réduire le texte', 'schilo' ); ?>" title="<?php esc_attr_e( 'Réduire', 'schilo' ); ?>">
        <span aria-hidden="true">A<sup>-</sup></span>
    </button>
    <span class="schilo-zoom-widget__level" id="schilo-zoom-level" aria-live="polite">100%</span>
    <button class="schilo-zoom-widget__btn" id="schilo-zoom-in" aria-label="<?php esc_attr_e( 'Agrandir le texte', 'schilo' ); ?>" title="<?php esc_attr_e( 'Agrandir', 'schilo' ); ?>">
        <span aria-hidden="true">A<sup>+</sup></span>
    </button>
</div>
<?php endif; ?>

<div class="schilo-search-modal" id="schilo-search-modal" aria-hidden="true">
  <div class="schilo-search-modal__overlay" data-schilo-search-close></div>
  <div class="schilo-search-modal__panel" role="dialog" aria-modal="true" aria-labelledby="schilo-search-modal-title">
    <div class="schilo-search-modal__header">
      <i class="ti ti-search schilo-search-modal__icon" aria-hidden="true"></i>
      <label for="schilo-search-modal-input" class="schilo-sr-only" id="schilo-search-modal-title">
        <?php esc_html_e( 'Rechercher', 'schilo' ); ?>
      </label>
      <input type="text"
             id="schilo-search-modal-input"
             class="schilo-search-modal__input"
             autocomplete="off"
             placeholder="<?php esc_attr_e( 'Un personnage, un lieu, un thème, une référence…', 'schilo' ); ?>">
      <button type="button" class="schilo-search-modal__close" data-schilo-search-close
              aria-label="<?php esc_attr_e( 'Fermer la recherche', 'schilo' ); ?>">
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
    </div>
    <div class="schilo-search-modal__body" id="schilo-search-modal-body">
      <p class="schilo-search-modal__hint">
        <?php esc_html_e( 'Tapez au moins 2 lettres pour découvrir des suggestions issues de nos articles.', 'schilo' ); ?>
      </p>
    </div>
  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
