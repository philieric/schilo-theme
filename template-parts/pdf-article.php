<?php
/**
 * Template : Export PDF d'un article (fiche)
 * Déclenché par ?schilo_pdf=1 sur une page d'article
 */
defined( 'ABSPATH' ) || exit;

if ( ! have_posts() ) {
    wp_redirect( home_url( '/' ) );
    exit;
}
the_post();

$post_id     = get_the_ID();
$raw_title   = get_post_field( 'post_title', $post_id );
$per_code    = '';
$clean_title = $raw_title;

if ( preg_match( '/^([A-Z]+\d+)\s*[\x{2013}\x{2014}\-]+\s*/u', $raw_title, $m ) ) {
    $per_code    = $m[1];
    $clean_title = preg_replace( '/^[A-Z]+\d+\s*[\x{2013}\x{2014}\-]+\s*/u', '', $raw_title );
}

$cats        = get_the_category();
$primary_cat = ! empty( $cats ) ? $cats[0] : null;
$tags        = get_the_tags();
$excerpt     = get_the_excerpt();
$permalink   = get_permalink();

$sections_raw  = get_post_meta( $post_id, '_schilo_builder_sections', true );
$sections_raw  = is_array( $sections_raw ) ? $sections_raw : [];

// Compteurs
$verset_count   = 0;
$question_count = 0;
foreach ( $sections_raw as $sec ) {
    if ( ! empty( $sec['data']['versets'] ) ) {
        $verset_count += count( array_filter( $sec['data']['versets'], fn( $v ) => ! empty( $v['reference'] ) ) );
    }
    if ( $sec['type'] === 'questions' && ! empty( $sec['data']['questions'] ) ) {
        $question_count += count( $sec['data']['questions'] );
    }
}

$text_content = $excerpt . ' ';
foreach ( $sections_raw as $sec ) {
    $text_content .= isset( $sec['content'] ) ? strip_tags( $sec['content'] ) . ' ' : '';
}
$word_count   = str_word_count( $text_content );
$reading_time = max( 1, ceil( $word_count / 200 + $verset_count * 0.5 ) );

$export_date = wp_date( 'd/m/Y' );

?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?php echo esc_html( $clean_title ); ?> — Schilo</title>
  <link rel="stylesheet" href="<?php echo esc_url( SCHILO_ASSETS . '/css/pdf.css' ); ?>?v=<?php echo esc_attr( SCHILO_VERSION ); ?>">
  <style>
    /* Masquer les icônes Tabler dans la fiche PDF pour éviter les glyphes manquants */
    .ti { display: none !important; }
  </style>
</head>
<body>

<!-- ── Barre d'outils (screen uniquement) ─────────────────────────── -->
<div class="pdf-toolbar">
  <span class="pdf-toolbar__logo">Schilo — Export fiche</span>
  <button class="pdf-toolbar__btn" onclick="window.print()">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2"/>
      <path d="M17 9V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4"/>
      <rect x="7" y="13" width="10" height="8" rx="1"/>
    </svg>
    Imprimer / Enregistrer en PDF
  </button>
  <a href="<?php echo esc_url( $permalink ); ?>" class="pdf-toolbar__close">← Retour à l'article</a>
</div>

<!-- ── Page fiche ─────────────────────────────────────────────────── -->
<div class="pdf-page">

  <!-- Bandeau d'en-tête coloré -->
  <div class="pdf-header">
    <div class="pdf-header__brand">
      <div class="pdf-header__logo-mark" aria-hidden="true"></div>
      <div>
        <div class="pdf-header__site">Schilo</div>
        <div class="pdf-header__tagline">Découvrir Jésus</div>
      </div>
    </div>
    <div class="pdf-header__meta">
      <?php echo esc_html( $export_date ); ?><br>
      schilo.fr
    </div>
  </div>

  <!-- Corps -->
  <div class="pdf-body">

    <!-- Zone identité -->
    <div class="pdf-identity">

      <!-- Badges ref + catégorie -->
      <div class="pdf-ref-row">
        <?php if ( $per_code ) : ?>
          <span class="pdf-ref__code"><?php echo esc_html( $per_code ); ?></span>
        <?php endif; ?>
        <?php if ( $primary_cat ) : ?>
          <span class="pdf-ref__cat"><?php echo esc_html( schilo_strip_category_number( $primary_cat->name ) ); ?></span>
        <?php endif; ?>
        <?php if ( $tags ) : foreach ( $tags as $tag ) : ?>
          <span class="pdf-ref__cat"><?php echo esc_html( $tag->name ); ?></span>
        <?php endforeach; endif; ?>
      </div>

      <!-- Titre -->
      <h1 class="pdf-title"><?php echo esc_html( $clean_title ); ?></h1>

      <!-- Résumé -->
      <?php if ( $excerpt ) : ?>
        <p class="pdf-excerpt"><?php echo esc_html( $excerpt ); ?></p>
      <?php endif; ?>

    </div><!-- /pdf-identity -->

    <!-- Statistiques (chips) -->
    <div class="pdf-stats">
      <span class="pdf-stats__item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <?php echo esc_html( $reading_time ); ?> min de lecture
      </span>
      <?php if ( $verset_count ) : ?>
      <span class="pdf-stats__item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
        </svg>
        <?php echo esc_html( $verset_count ); ?> référence<?php echo $verset_count > 1 ? 's' : ''; ?> biblique<?php echo $verset_count > 1 ? 's' : ''; ?>
      </span>
      <?php endif; ?>
      <?php if ( $question_count ) : ?>
      <span class="pdf-stats__item">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <?php echo esc_html( $question_count ); ?> question<?php echo $question_count > 1 ? 's' : ''; ?>
      </span>
      <?php endif; ?>
    </div>

    <!-- Contenu de l'article -->
    <div class="pdf-content">
      <?php the_content(); ?>
    </div>

    <!-- Pied de fiche -->
    <div class="pdf-footer">
      <span>schilo.fr — <?php echo esc_html( $clean_title ); ?></span>
      <span class="pdf-footer__url"><?php echo esc_url( $permalink ); ?></span>
      <span><?php echo esc_html( $export_date ); ?></span>
    </div>

  </div><!-- /pdf-body -->

</div><!-- /pdf-page -->

<script>
(function () {
  // Auto-print uniquement si schilo_autoprint=1 dans l'URL
  if (window.location.search.indexOf('autoprint=1') !== -1) {
    window.addEventListener('load', function () {
      setTimeout(function () { window.print(); }, 600);
    });
  }
})();
</script>
</body>
</html>
<?php
// Stopper l'exécution de WordPress après l'output
exit;
