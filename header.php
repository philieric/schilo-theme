<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <link rel="profile" href="https://gmpg.org/xfn/11">
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="schilo-nav" role="banner">
  <div class="schilo-nav__inner">

    <!-- Brand -->
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="schilo-nav__brand" rel="home">
      <div class="schilo-nav__brand-mark">
        <i class="ti ti-flame" aria-hidden="true"></i>
      </div>
      <div>
        <div class="schilo-nav__brand-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
        <div class="schilo-nav__brand-tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></div>
      </div>
    </a>

    <!-- Navigation principale -->
    <nav class="schilo-nav__links" id="schilo-primary-nav"
         aria-label="<?php esc_attr_e( 'Navigation principale', 'schilo' ); ?>">
      <?php
      wp_nav_menu( [
        'theme_location' => 'primary',
        'container'      => false,
        'fallback_cb'    => 'schilo_fallback_nav',
        'items_wrap'     => '%3$s',
        'walker'         => new Schilo_Nav_Walker(),
      ] );
      ?>
    </nav>

    <!-- Droite -->
    <div class="schilo-nav__right">

      <!-- Recherche -->
      <button class="schilo-btn-search" id="schilo-search-toggle"
              aria-label="<?php esc_attr_e( 'Rechercher', 'schilo' ); ?>">
        <i class="ti ti-search" aria-hidden="true"></i>
        <?php esc_html_e( 'Rechercher…', 'schilo' ); ?>
      </button>

      <!-- Bouton À propos -->
      <?php
      $apropos_url    = home_url( '/a-propos/' );
      $apropos_active = false;
      $by_tpl_ap = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-apropos.php' ] );
      if ( ! empty( $by_tpl_ap ) ) {
          $apropos_url    = get_permalink( $by_tpl_ap[0]->ID );
          $apropos_active = is_page( $by_tpl_ap[0]->ID );
      } else {
          foreach ( [ 'a-propos', 'apropos', 'about' ] as $slug ) {
              $p = get_page_by_path( $slug );
              if ( $p ) {
                  $apropos_url    = get_permalink( $p->ID );
                  $apropos_active = is_page( $p->ID );
                  break;
              }
          }
      }
      ?>
      <a href="<?php echo esc_url( $apropos_url ); ?>"
         class="schilo-btn-contact<?php echo $apropos_active ? ' active' : ''; ?>"
         aria-label="<?php esc_attr_e( 'À propos', 'schilo' ); ?>">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
      </a>

      <!-- Bouton Avancements -->
      <?php
      $avancements_url    = home_url( '/avancements/' );
      $avancements_active = false;
      $by_tpl_av = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-avancements.php' ] );
      if ( ! empty( $by_tpl_av ) ) {
          $avancements_url    = get_permalink( $by_tpl_av[0]->ID );
          $avancements_active = is_page( $by_tpl_av[0]->ID );
      } else {
          foreach ( [ 'avancements', 'derniers-contenus', 'nouveautes' ] as $slug ) {
              $p = get_page_by_path( $slug );
              if ( $p ) {
                  $avancements_url    = get_permalink( $p->ID );
                  $avancements_active = is_page( $p->ID );
                  break;
              }
          }
      }
      ?>
      <a href="<?php echo esc_url( $avancements_url ); ?>"
         class="schilo-btn-contact<?php echo $avancements_active ? ' active' : ''; ?>"
         aria-label="<?php esc_attr_e( 'Avancements', 'schilo' ); ?>">
        <i class="ti ti-history" aria-hidden="true"></i>
      </a>

      <!-- Bouton Plan du site -->
      <?php
      $sitemap_url    = home_url( '/plan-du-site/' );
      $sitemap_active = false;
      $by_tpl_sm = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-sitemap.php' ] );
      if ( ! empty( $by_tpl_sm ) ) {
          $sitemap_url    = get_permalink( $by_tpl_sm[0]->ID );
          $sitemap_active = is_page( $by_tpl_sm[0]->ID );
      } else {
          foreach ( [ 'plan-du-site', 'sitemap', 'plan-site' ] as $slug ) {
              $p = get_page_by_path( $slug );
              if ( $p ) {
                  $sitemap_url    = get_permalink( $p->ID );
                  $sitemap_active = is_page( $p->ID );
                  break;
              }
          }
      }
      ?>
      <a href="<?php echo esc_url( $sitemap_url ); ?>"
         class="schilo-btn-contact<?php echo $sitemap_active ? ' active' : ''; ?>"
         aria-label="<?php esc_attr_e( 'Plan du site', 'schilo' ); ?>">
        <i class="ti ti-map" aria-hidden="true"></i>
      </a>

      <!-- Bouton Contact -->
      <?php
      $contact_url    = home_url( '/contactez-nous/' );
      $contact_active = false;

      // Chercher par template d'abord
      $by_tpl = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
      if ( ! empty( $by_tpl ) ) {
          $contact_url    = get_permalink( $by_tpl[0]->ID );
          $contact_active = is_page( $by_tpl[0]->ID );
      } else {
          // Fallback par slug
          foreach ( [ 'contactez-nous', 'contact', 'nous-contacter' ] as $slug ) {
              $p = get_page_by_path( $slug );
              if ( $p ) {
                  $contact_url    = get_permalink( $p->ID );
                  $contact_active = is_page( $p->ID );
                  break;
              }
          }
      }
      ?>
      <a href="<?php echo esc_url( $contact_url ); ?>"
         class="schilo-btn-contact<?php echo $contact_active ? ' active' : ''; ?>"
         aria-label="<?php esc_attr_e( 'Contact', 'schilo' ); ?>">
        <i class="ti ti-mail" aria-hidden="true"></i>
      </a>

      <!-- Sélecteur de langue GTranslate -->
      <div class="schilo-lang" id="schilo-lang-selector" aria-live="polite"></div>

      <!-- CTA Commencer -->
      <a href="<?php echo esc_url( home_url( '/#parcours' ) ); ?>" class="schilo-btn-primary">
        <?php esc_html_e( 'Commencer', 'schilo' ); ?>
      </a>

      <!-- Burger mobile -->
      <button class="schilo-nav__toggle" id="schilo-menu-toggle"
              aria-controls="schilo-primary-nav" aria-expanded="false"
              aria-label="<?php esc_attr_e( 'Ouvrir le menu', 'schilo' ); ?>">
        <i class="ti ti-menu-2" aria-hidden="true"></i>
      </button>
    </div>

  </div>
</header>
