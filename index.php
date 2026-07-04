<?php
/**
 * Template de base — fallback.
 * Ce fichier est requis par WordPress.
 * Les templates spécifiques (front-page.php, single.php, page.php)
 * prennent le dessus selon la hiérarchie WordPress.
 */
get_header();
?>

<main id="schilo-main" class="schilo-main" role="main">
  <div class="schilo-container" style="padding: 3rem 0;">

    <?php if ( have_posts() ) : ?>

      <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class( 'schilo-card' ); ?> style="margin-bottom:1.5rem">
          <div class="schilo-card__body">
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div><?php the_excerpt(); ?></div>
          </div>
        </article>
      <?php endwhile; ?>

      <?php the_posts_navigation(); ?>

    <?php else : ?>

      <div class="schilo-card">
        <div class="schilo-card__body">
          <p><?php esc_html_e( 'Aucun contenu trouvé.', 'schilo' ); ?></p>
        </div>
      </div>

    <?php endif; ?>

  </div>
</main>

<?php get_footer(); ?>
