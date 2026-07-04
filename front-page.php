<?php
/**
 * Template : Page d'accueil (front-page.php)
 *
 * Ce template s'active automatiquement quand une page statique
 * est définie comme page d'accueil dans Réglages > Lecture.
 *
 * Le contenu est géré par le plugin Schilo Builder.
 * Ce fichier fournit uniquement la structure nav + footer.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="schilo-main" role="main">
  <?php
  /**
   * Hook principal de la page d'accueil.
   * Le plugin Schilo Builder peut s'y accrocher pour injecter
   * le contenu de la home (hero, verset du jour, parcours, etc.)
   *
   * Si Schilo Builder n'est pas actif, on affiche le contenu
   * standard de la page WordPress.
   */
  if ( function_exists( 'schilo_builder_render_home' ) ) {
      schilo_builder_render_home();
  } else {
      get_template_part( 'template-parts/home-fallback' );
  }
  ?>
</main>

<?php get_footer(); ?>
