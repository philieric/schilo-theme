<?php
/**
 * Template : Résultats de recherche
 */
defined( 'ABSPATH' ) || exit;

$search_query = get_search_query();
$found_posts  = (int) $GLOBALS['wp_query']->found_posts;
$total_pages  = (int) $GLOBALS['wp_query']->max_num_pages;
$current_page = max( 1, get_query_var( 'paged' ) );

// ── Tri courant (même logique que archive.php) ──────────────────
$sort_options = [
    'date-desc'     => __( 'Plus récent', 'schilo' ),
    'date-asc'      => __( 'Plus ancien', 'schilo' ),
    'title-asc'     => __( 'Titre A → Z', 'schilo' ),
    'title-desc'    => __( 'Titre Z → A', 'schilo' ),
    'modified-desc' => __( 'Mis à jour récemment', 'schilo' ),
    'comment-desc'  => __( 'Plus commentés', 'schilo' ),
];
$allowed_sorts = array_keys( $sort_options );
$current_sort  = isset( $_GET['schilo_sort'] ) && in_array( $_GET['schilo_sort'], $allowed_sorts, true )
    ? sanitize_key( $_GET['schilo_sort'] )
    : 'date-desc';

// ── Articles par page ────────────────────────────────────────────
$pp_options  = [ 10, 20, 50, -1 ]; // -1 = tous
$pp_labels   = [ 10 => '10', 20 => '20', 50 => '50', -1 => __( 'Tous', 'schilo' ) ];
$current_pp  = isset( $_GET['schilo_pp'] ) ? (int) $_GET['schilo_pp'] : 10;
if ( ! in_array( $current_pp, $pp_options, true ) ) $current_pp = 10;

// URL de base (avec le "s=" courant, sans paged/schilo_sort/schilo_pp)
$base_url = add_query_arg( 's', $search_query, home_url( '/' ) );

// Paramètres extra à propager dans la pagination
$extra_args = [ 's' => $search_query ];
if ( $current_sort !== 'date-desc' ) $extra_args['schilo_sort'] = $current_sort;
if ( $current_pp   !== 10          ) $extra_args['schilo_pp']   = $current_pp;

get_header();
?>

<div class="schilo-search-hero">
    <div class="schilo-container">
        <div class="schilo-search-hero__inner">
            <div class="schilo-search-hero__icon" aria-hidden="true">
                <i class="ti ti-search"></i>
            </div>
            <div>
                <?php if ( $search_query ) : ?>
                    <h1 class="schilo-search-hero__title">
                        <?php
                        printf(
                            esc_html__( 'Résultats pour « %s »', 'schilo' ),
                            '<em>' . esc_html( $search_query ) . '</em>'
                        );
                        ?>
                    </h1>
                <?php else : ?>
                    <h1 class="schilo-search-hero__title"><?php esc_html_e( 'Recherche', 'schilo' ); ?></h1>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nouveau formulaire de recherche -->
        <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>"
              class="schilo-search-form">
            <label for="schilo-search-input" class="schilo-sr-only">
                <?php esc_html_e( 'Rechercher', 'schilo' ); ?>
            </label>
            <input type="search"
                   id="schilo-search-input"
                   class="schilo-search-form__input"
                   name="s"
                   value="<?php echo esc_attr( $search_query ); ?>"
                   placeholder="<?php esc_attr_e( 'Chercher un article, une thématique…', 'schilo' ); ?>"
                   autocomplete="off">
            <button type="submit" class="schilo-search-form__btn" aria-label="<?php esc_attr_e( 'Lancer la recherche', 'schilo' ); ?>">
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</div>

<main id="schilo-main" role="main">
    <div class="schilo-container schilo-archive">

        <?php if ( have_posts() ) : ?>

            <!-- ── Barre d'outils ── -->
            <div class="schilo-archive-toolbar" role="toolbar">
                <p class="schilo-archive-toolbar__count">
                    <?php
                    printf(
                        esc_html( _n( '%s résultat', '%s résultats', $found_posts, 'schilo' ) ),
                        '<strong>' . esc_html( number_format_i18n( $found_posts ) ) . '</strong>'
                    );
                    if ( $total_pages > 1 ) {
                        echo ' — ' . sprintf(
                            esc_html__( 'page %1$s / %2$s', 'schilo' ),
                            '<strong>' . esc_html( $current_page ) . '</strong>',
                            '<strong>' . esc_html( $total_pages ) . '</strong>'
                        );
                    }
                    ?>
                </p>

                <div class="schilo-archive-toolbar__actions">

                    <!-- Tri -->
                    <div class="schilo-archive-sort-wrap">
                        <i class="ti ti-arrows-sort schilo-archive-sort-icon" aria-hidden="true"></i>
                        <label for="schilo-archive-sort" class="schilo-sr-only">
                            <?php esc_html_e( 'Trier par', 'schilo' ); ?>
                        </label>
                        <select id="schilo-archive-sort"
                                class="schilo-archive-select"
                                data-param="schilo_sort"
                                data-default="date-desc"
                                data-base-url="<?php echo esc_url( $base_url ); ?>"
                                data-current-pp="<?php echo esc_attr( $current_pp !== 10 ? $current_pp : '' ); ?>">
                            <?php foreach ( $sort_options as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"
                                    <?php selected( $current_sort, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Articles par page -->
                    <div class="schilo-archive-sort-wrap">
                        <i class="ti ti-list-numbers schilo-archive-sort-icon" aria-hidden="true"></i>
                        <label for="schilo-archive-pp" class="schilo-sr-only">
                            <?php esc_html_e( 'Articles par page', 'schilo' ); ?>
                        </label>
                        <select id="schilo-archive-pp"
                                class="schilo-archive-select schilo-archive-select--narrow"
                                data-param="schilo_pp"
                                data-default="10"
                                data-base-url="<?php echo esc_url( $base_url ); ?>"
                                data-current-sort="<?php echo esc_attr( $current_sort !== 'date-desc' ? $current_sort : '' ); ?>">
                            <?php foreach ( $pp_options as $pp ) : ?>
                                <option value="<?php echo esc_attr( $pp ); ?>"
                                    <?php selected( $current_pp, $pp ); ?>>
                                    <?php echo esc_html( $pp_labels[ $pp ] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Zoom texte -->
                    <div class="schilo-archive-zoom" title="<?php esc_attr_e( 'Taille du texte', 'schilo' ); ?>">
                        <span class="schilo-archive-zoom__icon schilo-archive-zoom__icon--sm" aria-hidden="true">A</span>
                        <input type="range"
                               class="schilo-archive-zoom__range"
                               id="schilo-archive-zoom"
                               min="80" max="130" step="5" value="100"
                               aria-label="<?php esc_attr_e( 'Taille du texte', 'schilo' ); ?>">
                        <span class="schilo-archive-zoom__icon schilo-archive-zoom__icon--lg" aria-hidden="true">A</span>
                    </div>

                    <!-- Mode d'affichage -->
                    <div class="schilo-archive-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Mode d\'affichage', 'schilo' ); ?>">
                        <button class="schilo-archive-view-btn schilo-archive-view-btn--grid is-active"
                                data-view="grid"
                                aria-pressed="true"
                                title="<?php esc_attr_e( 'Vue grille', 'schilo' ); ?>">
                            <i class="ti ti-layout-grid" aria-hidden="true"></i>
                        </button>
                        <button class="schilo-archive-view-btn schilo-archive-view-btn--list"
                                data-view="list"
                                aria-pressed="false"
                                title="<?php esc_attr_e( 'Vue liste', 'schilo' ); ?>">
                            <i class="ti ti-list" aria-hidden="true"></i>
                        </button>
                    </div>

                </div>
            </div>

            <!-- ── Résultats ── -->
            <div class="schilo-archive-posts schilo-archive-posts--grid" id="schilo-archive-posts">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php
                    $has_thumb    = has_post_thumbnail();
                    $created      = get_the_date( 'j F Y' );
                    $modified     = get_the_modified_date( 'j F Y' );
                    $same_date    = get_the_date( 'Ymd' ) === get_the_modified_date( 'Ymd' );
                    $cats         = get_the_category();
                    $cat          = ! empty( $cats ) ? $cats[0] : null;

                    // Extraire le code PER/ANN du titre depuis le titre brut (sans wptexturize)
                    $raw_title    = get_post_field( 'post_title', get_the_ID() );
                    $per_code     = '';
                    $clean_title  = $raw_title;
                    if ( preg_match( '/^([A-Z]+\d+)\s*[\x{2013}\x{2014}\-]+\s*/u', $raw_title, $m ) ) {
                        $per_code    = $m[1];
                        $clean_title = preg_replace( '/^[A-Z]+\d+\s*[\x{2013}\x{2014}\-]+\s*/u', '', $raw_title );
                    }
                    ?>
                    <article <?php post_class( 'schilo-archive-card' ); ?> id="post-<?php the_ID(); ?>">

                        <a href="<?php the_permalink(); ?>" class="schilo-archive-card__thumb-link" tabindex="-1" aria-hidden="true">
                            <?php if ( $has_thumb ) : ?>
                                <?php the_post_thumbnail( 'medium_large', [
                                    'class'   => 'schilo-archive-card__thumb',
                                    'loading' => 'lazy',
                                    'alt'     => esc_attr( $raw_title ),
                                ] ); ?>
                            <?php else : ?>
                                <div class="schilo-archive-card__thumb schilo-archive-card__thumb--placeholder" aria-hidden="true">
                                    <i class="ti ti-book-2"></i>
                                </div>
                            <?php endif; ?>
                            <?php if ( $per_code ) : ?>
                                <span class="schilo-archive-card__per-badge" aria-label="<?php echo esc_attr( $per_code ); ?>"><?php echo esc_html( $per_code ); ?></span>
                            <?php endif; ?>
                        </a>

                        <div class="schilo-archive-card__body">

                            <?php if ( $cat ) : ?>
                                <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"
                                   class="schilo-archive-card__cat">
                                    <?php echo esc_html( $cat->name ); ?>
                                </a>
                            <?php endif; ?>

                            <h2 class="schilo-archive-card__title">
                                <a href="<?php the_permalink(); ?>"><?php echo esc_html( $clean_title ); ?></a>
                            </h2>

                            <p class="schilo-archive-card__excerpt"><?php echo wp_trim_words( get_the_excerpt(), 22, '…' ); ?></p>

                            <footer class="schilo-archive-card__footer">
                                <div class="schilo-archive-card__meta">
                                    <span class="schilo-archive-card__meta-item" title="<?php esc_attr_e( 'Date de création', 'schilo' ); ?>">
                                        <i class="ti ti-calendar-plus" aria-hidden="true"></i>
                                        <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( $created ); ?></time>
                                    </span>
                                    <?php if ( ! $same_date ) : ?>
                                        <span class="schilo-archive-card__meta-item schilo-archive-card__meta-item--modified" title="<?php esc_attr_e( 'Dernière modification', 'schilo' ); ?>">
                                            <i class="ti ti-refresh" aria-hidden="true"></i>
                                            <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( $modified ); ?></time>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php the_permalink(); ?>" class="schilo-archive-card__read-more">
                                    <?php esc_html_e( 'Lire l\'étude', 'schilo' ); ?>
                                    <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                </a>
                            </footer>

                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <nav class="schilo-archive-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'schilo' ); ?>">
                    <?php
                    echo paginate_links( [
                        'base'      => str_replace( PHP_INT_MAX, '%#%', esc_url( get_pagenum_link( PHP_INT_MAX, false ) ) ),
                        'format'    => '',
                        'total'     => $total_pages,
                        'current'   => $current_page,
                        'prev_text' => '<i class="ti ti-chevron-left" aria-hidden="true"></i>' . esc_html__( 'Précédent', 'schilo' ),
                        'next_text' => esc_html__( 'Suivant', 'schilo' ) . '<i class="ti ti-chevron-right" aria-hidden="true"></i>',
                        'type'      => 'list',
                        'add_args'  => $extra_args,
                    ] );
                    ?>
                </nav>
            <?php endif; ?>

        <?php elseif ( $search_query ) : ?>

            <div class="schilo-search-empty">
                <div class="schilo-search-empty__icon" aria-hidden="true">
                    <i class="ti ti-mood-sad"></i>
                </div>
                <h2 class="schilo-search-empty__title">
                    <?php esc_html_e( 'Aucun résultat trouvé', 'schilo' ); ?>
                </h2>
                <p class="schilo-search-empty__text">
                    <?php
                    printf(
                        esc_html__( 'Votre recherche « %s » n\'a retourné aucun résultat. Essayez avec d\'autres mots-clés.', 'schilo' ),
                        '<strong>' . esc_html( $search_query ) . '</strong>'
                    );
                    ?>
                </p>
                <div class="schilo-search-empty__suggestions">
                    <p><?php esc_html_e( 'Suggestions :', 'schilo' ); ?></p>
                    <ul>
                        <li><?php esc_html_e( 'Vérifiez l\'orthographe de votre recherche', 'schilo' ); ?></li>
                        <li><?php esc_html_e( 'Essayez des termes plus généraux', 'schilo' ); ?></li>
                        <li><?php esc_html_e( 'Parcourez nos catégories depuis l\'accueil', 'schilo' ); ?></li>
                    </ul>
                </div>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="schilo-btn schilo-btn--primary">
                    <i class="ti ti-home" aria-hidden="true"></i>
                    <?php esc_html_e( 'Retour à l\'accueil', 'schilo' ); ?>
                </a>
            </div>

        <?php else : ?>

            <div class="schilo-search-empty">
                <div class="schilo-search-empty__icon" aria-hidden="true">
                    <i class="ti ti-search"></i>
                </div>
                <p class="schilo-search-empty__text">
                    <?php esc_html_e( 'Entrez un terme dans le champ de recherche ci-dessus.', 'schilo' ); ?>
                </p>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>
