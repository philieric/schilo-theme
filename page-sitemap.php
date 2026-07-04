<?php
/**
 * Template Name: Plan du site
 *
 * Page template for the HTML sitemap — wraps the plugin shortcode
 * in the schilo design.
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>

<!-- ── HERO ── -->
<div class="schilo-hero">
  <div class="schilo-hero__inner">
    <div class="schilo-hero__eyebrow">
      <i class="ti ti-map" aria-hidden="true"></i>
      <?php esc_html_e( 'Plan du site', 'schilo' ); ?>
    </div>
    <h1 class="schilo-hero__title schilo-serif">
      <?php esc_html_e( 'Tout le contenu de Schilo, organisé par catégorie', 'schilo' ); ?>
    </h1>
    <p class="schilo-hero__desc">
      <?php esc_html_e( 'Retrouvez l\'ensemble des articles, fiches d\'étude et parcours du site — classés par thème et sous-thème pour une navigation rapide.', 'schilo' ); ?>
    </p>
  </div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">

  <!-- ── INTRO ── -->
  <div class="schilo-card" style="margin-bottom:1.25rem">
    <div class="schilo-card__head">
      <div class="schilo-card__head-left">
        <div class="schilo-card__icon schilo-card__icon--dark">
          <i class="ti ti-compass" aria-hidden="true"></i>
        </div>
        <span class="schilo-card__title"><?php esc_html_e( 'Comment naviguer ?', 'schilo' ); ?></span>
      </div>
    </div>
    <div class="schilo-card__body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem">

        <div style="display:flex;align-items:flex-start;gap:.75rem">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--schilo-luc-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-layout-sidebar" style="color:var(--schilo-luc);font-size:1.05rem" aria-hidden="true"></i>
          </div>
          <div>
            <div style="font-size:.875rem;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.25rem"><?php esc_html_e( 'Menu catégories', 'schilo' ); ?></div>
            <p style="font-size:.8rem;color:var(--schilo-text-secondary);margin:0;line-height:1.5"><?php esc_html_e( 'Cliquez sur une catégorie dans la barre gauche pour afficher son contenu.', 'schilo' ); ?></p>
          </div>
        </div>

        <div style="display:flex;align-items:flex-start;gap:.75rem">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--schilo-marc-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-search" style="color:var(--schilo-marc);font-size:1.05rem" aria-hidden="true"></i>
          </div>
          <div>
            <div style="font-size:.875rem;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.25rem"><?php esc_html_e( 'Recherche rapide', 'schilo' ); ?></div>
            <p style="font-size:.8rem;color:var(--schilo-text-secondary);margin:0;line-height:1.5"><?php esc_html_e( 'La barre de recherche filtre les articles en temps réel dans toutes les catégories.', 'schilo' ); ?></p>
          </div>
        </div>

        <div style="display:flex;align-items:flex-start;gap:.75rem">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--schilo-mat-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-sparkles" style="color:var(--schilo-mat);font-size:1.05rem" aria-hidden="true"></i>
          </div>
          <div>
            <div style="font-size:.875rem;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.25rem"><?php esc_html_e( 'Badge Nouveau', 'schilo' ); ?></div>
            <p style="font-size:.8rem;color:var(--schilo-text-secondary);margin:0;line-height:1.5"><?php esc_html_e( 'Les articles publiés récemment sont signalés par un badge rouge « Nouveau ».', 'schilo' ); ?></p>
          </div>
        </div>

        <div style="display:flex;align-items:flex-start;gap:.75rem">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--schilo-jean-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti ti-grid-dots" style="color:var(--schilo-jean);font-size:1.05rem" aria-hidden="true"></i>
          </div>
          <div>
            <div style="font-size:.875rem;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.25rem"><?php esc_html_e( 'Vue liste / grille', 'schilo' ); ?></div>
            <p style="font-size:.8rem;color:var(--schilo-text-secondary);margin:0;line-height:1.5"><?php esc_html_e( 'Basculez entre la vue liste et la vue grille avec les boutons en haut à droite de chaque section.', 'schilo' ); ?></p>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ── SITEMAP ── -->
  <div class="schilo-card schilo-sitemap-page" style="margin-bottom:1.25rem">
    <div class="schilo-card__head">
      <div class="schilo-card__head-left">
        <div class="schilo-card__icon schilo-card__icon--dark">
          <i class="ti ti-folder-open" aria-hidden="true"></i>
        </div>
        <span class="schilo-card__title"><?php esc_html_e( 'Toutes les catégories', 'schilo' ); ?></span>
      </div>
    </div>
    <div class="schilo-card__body">
      <?php
      if ( shortcode_exists( 'sitemap_par_categorie' ) ) {
          echo do_shortcode( '[sitemap_par_categorie]' );
      } else {
          echo '<p style="color:var(--schilo-text-muted);font-style:italic">';
          esc_html_e( 'Le plugin Sitemap par Catégorie n\'est pas activé.', 'schilo' );
          echo '</p>';
      }
      ?>
    </div>
  </div>

  <!-- ── CTA DARK ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;gap:2rem;background:var(--schilo-bg-dark,#1e2a3a);border-radius:var(--schilo-radius-lg,14px);padding:2rem 2.5rem;flex-wrap:wrap">
    <div style="flex:1">
      <div style="font-size:18px;font-weight:500;color:#fff;margin-bottom:.4rem">
        <?php esc_html_e( 'Vous cherchez quelque chose de précis ?', 'schilo' ); ?>
      </div>
      <p style="font-size:13px;color:rgba(255,255,255,.55);margin:0">
        <?php esc_html_e( 'Parcourez les Évangiles par thème, suivez un parcours guidé ou posez-nous votre question.', 'schilo' ); ?>
      </p>
    </div>
    <div style="display:flex;gap:10px;flex-shrink:0;flex-wrap:wrap">
      <a href="<?php echo esc_url( home_url( '/parcours/' ) ); ?>"
         style="display:inline-flex;align-items:center;gap:7px;background:var(--schilo-accent,#2872d4);color:#fff!important;border:none;border-radius:99px;padding:10px 20px;font-size:13px;font-weight:500;text-decoration:none">
        <i class="ti ti-route" aria-hidden="true"></i>
        <?php esc_html_e( 'Commencer un parcours', 'schilo' ); ?>
      </a>
      <?php
      $ct_url = home_url( '/contactez-nous/' );
      $by_ct  = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
      if ( ! empty( $by_ct ) ) $ct_url = get_permalink( $by_ct[0]->ID );
      ?>
      <a href="<?php echo esc_url( $ct_url ); ?>"
         style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.1);color:#fff!important;border:1px solid rgba(255,255,255,.3);border-radius:99px;padding:10px 20px;font-size:13px;font-weight:500;text-decoration:none">
        <i class="ti ti-mail" aria-hidden="true"></i>
        <?php esc_html_e( 'Nous écrire', 'schilo' ); ?>
      </a>
      <a href="<?php echo esc_url( home_url( '/sitemap-par-categorie.xml' ) ); ?>"
         target="_blank" rel="noopener"
         style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.7)!important;border:1px solid rgba(255,255,255,.15);border-radius:99px;padding:10px 20px;font-size:13px;font-weight:500;text-decoration:none">
        <i class="ti ti-code" aria-hidden="true"></i>
        <?php esc_html_e( 'Sitemap XML', 'schilo' ); ?>
      </a>
    </div>
  </div>

</div>
</main>

<?php get_footer(); ?>
