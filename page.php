<?php
/**
 * Template : Page WordPress générique (page.php)
 *
 * Utilisé pour les pages statiques standard
 * (À propos, Contact, Mentions légales, etc.)
 */
get_header();
?>

<main id="schilo-main" role="main">
  <div class="schilo-container" style="padding: 1.5rem 0 3rem;">
    <div class="schilo-grid-main">

      <article <?php post_class( 'schilo-card' ); ?>>
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();
                ?>
                <div class="schilo-card__head">
                  <div class="schilo-card__head-left">
                    <div class="schilo-card__icon schilo-card__icon--soft">
                      <i class="ti ti-file" aria-hidden="true"></i>
                    </div>
                    <span class="schilo-card__title">
                      <?php the_title(); ?>
                    </span>
                  </div>
                </div>
                <div class="schilo-card__body">
                  <?php the_content(); ?>
                </div>
                <?php
            endwhile;
        endif;
        ?>
      </article>

      <aside class="schilo-sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'schilo' ); ?>">
        <?php
        if ( is_active_sidebar( 'schilo-sidebar-article' ) ) {
            dynamic_sidebar( 'schilo-sidebar-article' );
        }
        ?>
      </aside>

    </div>
  </div>
</main>

<?php get_footer(); ?>
