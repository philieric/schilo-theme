<?php
/**
 * Template : Résultats de recherche
 */
defined( 'ABSPATH' ) || exit;

$search_query = get_search_query();
$found_posts  = (int) $GLOBALS['wp_query']->found_posts;
$total_pages  = (int) $GLOBALS['wp_query']->max_num_pages;
$current_page = max( 1, get_query_var( 'paged' ) );

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
                    <p class="schilo-search-hero__count">
                        <?php
                        printf(
                            esc_html( _n( '%s résultat trouvé', '%s résultats trouvés', $found_posts, 'schilo' ) ),
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
    <div class="schilo-container schilo-search">

        <?php if ( have_posts() ) : ?>

            <div class="schilo-search-results" id="schilo-search-results">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php
                    $has_thumb = has_post_thumbnail();
                    $created   = get_the_date( 'j F Y' );
                    $cats      = get_the_category();
                    $cat       = ! empty( $cats ) ? $cats[0] : null;
                    ?>
                    <article <?php post_class( 'schilo-search-card' ); ?> id="post-<?php the_ID(); ?>">

                        <?php if ( $has_thumb ) : ?>
                            <a href="<?php the_permalink(); ?>" class="schilo-search-card__thumb-link" tabindex="-1" aria-hidden="true">
                                <?php the_post_thumbnail( 'medium', [
                                    'class'   => 'schilo-search-card__thumb',
                                    'loading' => 'lazy',
                                    'alt'     => esc_attr( get_the_title() ),
                                ] ); ?>
                            </a>
                        <?php endif; ?>

                        <div class="schilo-search-card__body">
                            <?php if ( $cat ) : ?>
                                <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"
                                   class="schilo-search-card__cat">
                                    <?php echo esc_html( schilo_strip_category_number( $cat->name ) ); ?>
                                </a>
                            <?php endif; ?>

                            <h2 class="schilo-search-card__title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h2>

                            <p class="schilo-search-card__excerpt">
                                <?php echo wp_trim_words( get_the_excerpt(), 20, '…' ); ?>
                            </p>

                            <div class="schilo-search-card__meta">
                                <span class="schilo-search-card__date">
                                    <i class="ti ti-calendar" aria-hidden="true"></i>
                                    <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( $created ); ?></time>
                                </span>
                                <a href="<?php the_permalink(); ?>" class="schilo-search-card__read-more">
                                    <?php esc_html_e( 'Lire l\'étude', 'schilo' ); ?>
                                    <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
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
                        'add_args'  => $search_query ? [ 's' => $search_query ] : [],
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
