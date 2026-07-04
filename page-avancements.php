<?php
/**
 * Template Name: Avancements
 * Description: Dernières modifications et nouvelles publications du site
 */
defined( 'ABSPATH' ) || exit;

/* ── Requêtes ── */
$args_base = [
    'post_type'      => [ 'post', 'page' ],
    'posts_per_page' => 20,
    'post_status'    => 'publish',
];

$query_modifies = new WP_Query( array_merge( $args_base, [
    'orderby'    => 'modified',
    'order'      => 'DESC',
    'date_query' => [ [ 'column' => 'post_modified', 'after' => '3 months ago' ] ],
] ) );

$query_nouveaux = new WP_Query( array_merge( $args_base, [
    'orderby'    => 'date',
    'order'      => 'DESC',
    'date_query' => [ [ 'column' => 'post_date', 'after' => '3 months ago' ] ],
] ) );

get_header();
?>

<!-- ── HERO ── -->
<div class="schilo-hero">
  <div class="schilo-hero__inner">
    <div class="schilo-hero__eyebrow">
      <i class="ti ti-history" aria-hidden="true"></i>
      <?php esc_html_e( 'Avancements', 'schilo' ); ?>
    </div>
    <h1 class="schilo-hero__title schilo-serif">
      <?php esc_html_e( 'Ce qui a changé sur le site', 'schilo' ); ?>
    </h1>
    <p class="schilo-hero__desc">
      <?php esc_html_e( 'Suivez en temps réel l\'évolution du contenu de Schilo.org — mises à jour des fiches existantes et nouvelles publications parues ces trois derniers mois.', 'schilo' ); ?>
    </p>
  </div>
</div>

<main id="schilo-main" role="main">
  <div class="schilo-container schilo-avancement-wrap">

    <!-- ── STATS RAPIDES ── -->
    <div class="schilo-avancement-stats">

      <div class="schilo-avancement-stat">
        <div class="schilo-avancement-stat__icon schilo-avancement-stat__icon--blue">
          <i class="ti ti-refresh" aria-hidden="true"></i>
        </div>
        <div>
          <div class="schilo-avancement-stat__number"><?php echo esc_html( $query_modifies->found_posts ); ?></div>
          <div class="schilo-avancement-stat__label"><?php esc_html_e( 'Mises à jour récentes', 'schilo' ); ?></div>
        </div>
      </div>

      <div class="schilo-avancement-stat">
        <div class="schilo-avancement-stat__icon schilo-avancement-stat__icon--green">
          <i class="ti ti-sparkles" aria-hidden="true"></i>
        </div>
        <div>
          <div class="schilo-avancement-stat__number"><?php echo esc_html( $query_nouveaux->found_posts ); ?></div>
          <div class="schilo-avancement-stat__label"><?php esc_html_e( 'Nouvelles publications', 'schilo' ); ?></div>
        </div>
      </div>

      <div class="schilo-avancement-stat">
        <div class="schilo-avancement-stat__icon schilo-avancement-stat__icon--gold">
          <i class="ti ti-calendar" aria-hidden="true"></i>
        </div>
        <div>
          <div class="schilo-avancement-stat__number--md"><?php echo esc_html( wp_date( 'd/m/Y' ) ); ?></div>
          <div class="schilo-avancement-stat__label"><?php esc_html_e( 'Dernière consultation', 'schilo' ); ?></div>
        </div>
      </div>

    </div>

    <!-- ── GRILLE PRINCIPALE + SIDEBAR ── -->
    <div class="schilo-grid-main">

      <!-- COLONNE PRINCIPALE -->
      <div>

        <!-- Texte d'introduction -->
        <div class="schilo-card" style="margin-bottom:1.25rem">
          <div class="schilo-card__body">
            <p class="schilo-avancement-intro">
              <?php esc_html_e( 'Schilo.org est un site vivant, régulièrement enrichi de nouvelles fiches d\'étude et de corrections. Cette page vous permet de rester informé des dernières évolutions sans avoir à parcourir l\'ensemble du site.', 'schilo' ); ?>
            </p>
            <p class="schilo-avancement-intro">
              <?php esc_html_e( 'Les tableaux ci-dessous couvrent une fenêtre glissante de 3 mois. Cliquez sur un titre pour accéder directement à la fiche concernée.', 'schilo' ); ?>
            </p>
          </div>
        </div>

        <!-- ── TABLE : MISES À JOUR ── -->
        <div class="schilo-apropos-section-title">
          <i class="ti ti-refresh" aria-hidden="true"></i>
          <?php esc_html_e( 'Contenus récemment mis à jour', 'schilo' ); ?>
        </div>

        <div class="schilo-card" style="margin-bottom:2rem">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-edit" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Mises à jour — 3 derniers mois', 'schilo' ); ?></span>
            </div>
            <div class="schilo-avancement-badge">
              <?php printf( esc_html__( '%d résultat(s)', 'schilo' ), $query_modifies->found_posts ); ?>
            </div>
          </div>
          <div class="schilo-card__body" style="padding:0">
            <?php if ( $query_modifies->have_posts() ) : ?>
            <table class="schilo-avancement-table">
              <thead>
                <tr>
                  <th class="col-title"><?php esc_html_e( 'Titre', 'schilo' ); ?></th>
                  <th class="col-nowrap"><?php esc_html_e( 'Modifié le', 'schilo' ); ?></th>
                  <th class="col-nowrap"><?php esc_html_e( 'Type', 'schilo' ); ?></th>
                </tr>
              </thead>
              <tbody>
              <?php
              while ( $query_modifies->have_posts() ) :
                  $query_modifies->the_post();
                  $post_type_obj = get_post_type_object( get_post_type() );
                  $type_label    = $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type();
                  $is_page       = get_post_type() === 'page';
                  $type_color    = $is_page ? '#7c4db8' : '#2872d4';
                  $type_bg       = $is_page ? '#ede4f8' : '#e2eefb';
              ?>
                <tr>
                  <td>
                    <a href="<?php the_permalink(); ?>" class="schilo-avancement-table__link">
                      <i class="ti ti-chevron-right schilo-avancement-table__chevron" aria-hidden="true"></i>
                      <?php the_title(); ?>
                    </a>
                  </td>
                  <td class="schilo-avancement-table__date">
                    <i class="ti ti-clock schilo-avancement-table__date-icon" aria-hidden="true"></i>
                    <?php echo esc_html( get_the_modified_date( 'd/m/Y' ) ); ?>
                    <span class="schilo-avancement-table__time"><?php echo esc_html( get_the_modified_date( 'H:i' ) ); ?></span>
                  </td>
                  <td>
                    <span style="display:inline-flex;align-items:center;background:<?php echo esc_attr( $type_bg ); ?>;color:<?php echo esc_attr( $type_color ); ?>;border-radius:99px;padding:3px 10px;font-size:11px;font-weight:500">
                      <?php echo esc_html( $type_label ); ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; wp_reset_postdata(); ?>
              </tbody>
            </table>
            <?php else : ?>
            <div class="schilo-avancement-table__empty">
              <i class="ti ti-inbox schilo-avancement-table__empty-icon" aria-hidden="true"></i>
              <?php esc_html_e( 'Aucune mise à jour récente trouvée.', 'schilo' ); ?>
            </div>
            <?php wp_reset_postdata(); endif; ?>
          </div>
        </div>

        <!-- ── TABLE : NOUVELLES PUBLICATIONS ── -->
        <div class="schilo-apropos-section-title">
          <i class="ti ti-sparkles" aria-hidden="true"></i>
          <?php esc_html_e( 'Nouvelles publications', 'schilo' ); ?>
        </div>

        <div class="schilo-card" style="margin-bottom:1.5rem">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-file-plus" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Nouvelles publications — 3 derniers mois', 'schilo' ); ?></span>
            </div>
            <div class="schilo-avancement-badge">
              <?php printf( esc_html__( '%d résultat(s)', 'schilo' ), $query_nouveaux->found_posts ); ?>
            </div>
          </div>
          <div class="schilo-card__body" style="padding:0">
            <?php if ( $query_nouveaux->have_posts() ) : ?>
            <table class="schilo-avancement-table">
              <thead>
                <tr>
                  <th class="col-title"><?php esc_html_e( 'Titre', 'schilo' ); ?></th>
                  <th class="col-nowrap"><?php esc_html_e( 'Publié le', 'schilo' ); ?></th>
                  <th class="col-nowrap"><?php esc_html_e( 'Type', 'schilo' ); ?></th>
                </tr>
              </thead>
              <tbody>
              <?php
              while ( $query_nouveaux->have_posts() ) :
                  $query_nouveaux->the_post();
                  $post_type_obj = get_post_type_object( get_post_type() );
                  $type_label    = $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type();
                  $is_page       = get_post_type() === 'page';
                  $type_color    = $is_page ? '#7c4db8' : '#2e9e4f';
                  $type_bg       = $is_page ? '#ede4f8' : '#e6f7ec';
              ?>
                <tr>
                  <td>
                    <a href="<?php the_permalink(); ?>" class="schilo-avancement-table__link">
                      <i class="ti ti-chevron-right schilo-avancement-table__chevron" aria-hidden="true"></i>
                      <?php the_title(); ?>
                    </a>
                  </td>
                  <td class="schilo-avancement-table__date">
                    <i class="ti ti-calendar-plus schilo-avancement-table__date-icon" aria-hidden="true"></i>
                    <?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?>
                    <span class="schilo-avancement-table__time"><?php echo esc_html( get_the_date( 'H:i' ) ); ?></span>
                  </td>
                  <td>
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?php echo esc_attr( $type_bg ); ?>;color:<?php echo esc_attr( $type_color ); ?>;border-radius:99px;padding:3px 10px;font-size:11px;font-weight:500">
                      <i class="ti ti-sparkles" style="font-size:10px" aria-hidden="true"></i>
                      <?php echo esc_html( $type_label ); ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; wp_reset_postdata(); ?>
              </tbody>
            </table>
            <?php else : ?>
            <div class="schilo-avancement-table__empty">
              <i class="ti ti-inbox schilo-avancement-table__empty-icon" aria-hidden="true"></i>
              <?php esc_html_e( 'Aucune nouvelle publication récente trouvée.', 'schilo' ); ?>
            </div>
            <?php wp_reset_postdata(); endif; ?>
          </div>
        </div>

        <!-- Texte de bas de page -->
        <div class="schilo-avancement-infobox">
          <div class="schilo-avancement-infobox__inner">
            <i class="ti ti-info-circle schilo-avancement-infobox__icon" aria-hidden="true"></i>
            <div>
              <div class="schilo-avancement-infobox__title">
                <?php esc_html_e( 'Comment sont sélectionnés ces contenus ?', 'schilo' ); ?>
              </div>
              <p class="schilo-avancement-infobox__text">
                <?php esc_html_e( 'Seuls les articles et pages publiés et modifiés au cours des 90 derniers jours apparaissent ici. Les pages système (accueil, archives) peuvent également figurer dans la liste lorsqu\'elles sont mises à jour.', 'schilo' ); ?>
              </p>
            </div>
          </div>
        </div>

        <!-- CTA -->
        <div class="schilo-avancement-cta">
          <div class="schilo-avancement-cta__text">
            <div class="schilo-avancement-cta__title">
              <?php esc_html_e( 'Envie d\'explorer davantage ?', 'schilo' ); ?>
            </div>
            <p class="schilo-avancement-cta__desc">
              <?php esc_html_e( 'Parcourez les Évangiles fiche par fiche ou suivez un parcours guidé.', 'schilo' ); ?>
            </p>
          </div>
          <div class="schilo-avancement-cta__btns">
            <a href="<?php echo esc_url( home_url( '/parcours/' ) ); ?>" class="schilo-avancement-cta__btn schilo-avancement-cta__btn--primary">
              <i class="ti ti-route" aria-hidden="true"></i>
              <?php esc_html_e( 'Commencer un parcours', 'schilo' ); ?>
            </a>
            <?php
            $fc_url = home_url( '/contactez-nous/' );
            $by_tpl = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
            if ( ! empty( $by_tpl ) ) $fc_url = get_permalink( $by_tpl[0]->ID );
            ?>
            <a href="<?php echo esc_url( $fc_url ); ?>" class="schilo-avancement-cta__btn schilo-avancement-cta__btn--secondary">
              <i class="ti ti-mail" aria-hidden="true"></i>
              <?php esc_html_e( 'Nous écrire', 'schilo' ); ?>
            </a>
          </div>
        </div>

      </div>

      <!-- ── SIDEBAR ── -->
      <aside class="schilo-sidebar">

        <!-- Bloc : À propos de cette page -->
        <div class="schilo-card" style="margin-bottom:1rem">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-radar" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Suivi en direct', 'schilo' ); ?></span>
            </div>
          </div>
          <div class="schilo-card__body">
            <p class="schilo-avancement-sidebar-text">
              <?php esc_html_e( 'Cette page se met à jour automatiquement à chaque visite. Aucune inscription n\'est nécessaire pour suivre l\'évolution du site.', 'schilo' ); ?>
            </p>
            <div class="schilo-avancement-live">
              <span class="schilo-avancement-live__dot"></span>
              <?php esc_html_e( 'Données en temps réel', 'schilo' ); ?>
            </div>
          </div>
        </div>

        <!-- Bloc : Légende des types -->
        <div class="schilo-card" style="margin-bottom:1rem">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-tags" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Types de contenu', 'schilo' ); ?></span>
            </div>
          </div>
          <div class="schilo-card__body">
            <div class="schilo-avancement-legend">
              <div class="schilo-avancement-legend__row">
                <span class="schilo-avancement-legend__badge" style="background:var(--schilo-luc-bg);color:var(--schilo-luc)"><?php esc_html_e( 'Article', 'schilo' ); ?></span>
                <span class="schilo-avancement-legend__text"><?php esc_html_e( 'Fiche d\'étude biblique (texte, verset, thème)', 'schilo' ); ?></span>
              </div>
              <div class="schilo-avancement-legend__row">
                <span class="schilo-avancement-legend__badge" style="background:var(--schilo-jean-bg);color:var(--schilo-jean)"><?php esc_html_e( 'Page', 'schilo' ); ?></span>
                <span class="schilo-avancement-legend__text"><?php esc_html_e( 'Page du site (parcours, index, À propos…)', 'schilo' ); ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Bloc : Fréquence des mises à jour -->
        <div class="schilo-card" style="margin-bottom:1rem">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-calendar-stats" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Activité récente', 'schilo' ); ?></span>
            </div>
          </div>
          <div class="schilo-card__body">
            <div class="schilo-contact-info">
              <div class="schilo-contact-info__item">
                <div class="schilo-contact-info__icon"><i class="ti ti-refresh" aria-hidden="true"></i></div>
                <div>
                  <div class="schilo-contact-info__label"><?php esc_html_e( 'Mises à jour (90 j)', 'schilo' ); ?></div>
                  <div class="schilo-avancement-stat-value"><?php echo esc_html( $query_modifies->found_posts ); ?></div>
                </div>
              </div>
              <div class="schilo-contact-info__item" style="border:none">
                <div class="schilo-contact-info__icon"><i class="ti ti-file-plus" aria-hidden="true"></i></div>
                <div>
                  <div class="schilo-contact-info__label"><?php esc_html_e( 'Nouvelles fiches (90 j)', 'schilo' ); ?></div>
                  <div class="schilo-avancement-stat-value"><?php echo esc_html( $query_nouveaux->found_posts ); ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bloc : Suggérer du contenu -->
        <div class="schilo-card">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-bulb" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Une idée ?', 'schilo' ); ?></span>
            </div>
          </div>
          <div class="schilo-card__body">
            <p class="schilo-avancement-sidebar-text" style="margin-bottom:1rem">
              <?php esc_html_e( 'Vous souhaitez qu\'un sujet biblique soit traité ou qu\'une fiche existante soit approfondie ? Écrivez-nous.', 'schilo' ); ?>
            </p>
            <?php
            $fc_url = home_url( '/contactez-nous/' );
            $by_tpl = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
            if ( ! empty( $by_tpl ) ) $fc_url = get_permalink( $by_tpl[0]->ID );
            ?>
            <a href="<?php echo esc_url( $fc_url ); ?>" class="schilo-avancement-suggest">
              <i class="ti ti-send" aria-hidden="true"></i>
              <?php esc_html_e( 'Suggérer un contenu', 'schilo' ); ?>
            </a>
          </div>
        </div>

      </aside>

    </div>
  </div>
</main>

<?php get_footer(); ?>
