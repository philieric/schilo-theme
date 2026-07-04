<?php
/**
 * Template : Page 404
 */
get_header();
?>

<main id="schilo-main" role="main">
  <div class="schilo-container schilo-404-wrap">
    <div class="schilo-card schilo-404-card">
      <div class="schilo-card__body schilo-404-body">
        <div class="schilo-404-number">404</div>
        <h1 class="schilo-404-title">
          <?php esc_html_e( 'Page introuvable', 'schilo' ); ?>
        </h1>
        <p class="schilo-404-desc">
          <?php esc_html_e( 'La page que vous cherchez n\'existe pas ou a été déplacée.', 'schilo' ); ?>
        </p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="schilo-btn schilo-btn--primary">
          <i class="ti ti-home" aria-hidden="true"></i>
          <?php esc_html_e( 'Retour à l\'accueil', 'schilo' ); ?>
        </a>
      </div>
    </div>
  </div>
</main>

<?php get_footer(); ?>
