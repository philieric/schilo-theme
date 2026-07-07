<?php
defined( 'ABSPATH' ) || exit;
/**
 * Accueil autonome du thème, utilisé tant que Schilo Builder
 * ne fournit pas schilo_builder_render_home().
 */
defined( 'ABSPATH' ) || exit;

$post_counts      = wp_count_posts( 'post' );
$page_counts      = wp_count_posts( 'page' );
$published_pages  = isset( $page_counts->publish ) ? (int) $page_counts->publish : 0;

// Fiches = tous les posts publiés sauf les annexes (catégorie slug "annexes")
$annexe_term      = get_term_by( 'slug', 'annexes', 'category' );
$annexe_count     = $annexe_term ? (int) $annexe_term->count : 0;
$published_posts  = ( isset( $post_counts->publish ) ? (int) $post_counts->publish : 0 ) - $annexe_count;
$synopsis_url     = home_url( '/liste-des-periodes-des-pericopes/' );
$sitemap_url      = home_url( '/plan-du-site/' );
$gospels_url      = get_search_link( 'Évangiles' );

$root_categories = get_categories( [
    'taxonomy'   => 'category',
    'parent'     => 0,
    'hide_empty' => true,
    'exclude'    => [ (int) get_option( 'default_category' ) ],
    'orderby'    => 'name',
    'order'      => 'ASC',
] );

$main_categories  = [];
$other_categories = [];
$children_by_parent = [];

foreach ( $root_categories as $category ) {
    if ( 'non-classe' === $category->slug || 'uncategorized' === $category->slug ) {
        continue;
    }

    if ( preg_match( '/^(\d+)\s*-\s*/u', $category->name ) ) {
        $main_categories[] = $category;
    } else {
        $other_categories[] = $category;
    }
}

$child_categories = get_categories( [
    'taxonomy'   => 'category',
    'hide_empty' => true,
    'orderby'    => 'name',
    'order'      => 'ASC',
] );

foreach ( $child_categories as $child_category ) {
    $parent_id = (int) $child_category->parent;
    if ( 0 === $parent_id ) {
        continue;
    }

    if ( ! isset( $children_by_parent[ $parent_id ] ) ) {
        $children_by_parent[ $parent_id ] = [];
    }

    $children_by_parent[ $parent_id ][] = $child_category;
}

$category_count = count( $main_categories ) + count( $other_categories );

$featured = Schilo_Featured::get();

// Parcours codés en dur, utilisés tant qu'aucun parcours n'a été classé
// via Schilo Builder > Parcours & Thèmes (voir plus bas pour la version dynamique).
$paths_fallback = [
    [
        'ev'          => 'luc',
        'letter'      => 'L',
        'icon'        => 'ti-route',
        'count'       => __( '12 fiches', 'schilo' ),
        'title'       => __( 'Pourquoi et comment Luc a-t-il écrit son Évangile ?', 'schilo' ),
        'description' => __( "Une enquête structurée sur la méthode de l'auteur, le contexte historique et le troisième Évangile.", 'schilo' ),
        'url'         => get_search_link( 'Évangile de Luc' ),
    ],
    [
        'ev'          => 'mat',
        'letter'      => 'M',
        'icon'        => 'ti-mountain',
        'count'       => __( '18 fiches', 'schilo' ),
        'title'       => __( 'Le Sermon sur la Montagne selon Matthieu', 'schilo' ),
        'description' => __( 'Les Béatitudes, la prière et les enseignements de Jésus étudiés pas à pas.', 'schilo' ),
        'url'         => get_search_link( 'Sermon sur la Montagne' ),
    ],
    [
        'ev'          => 'jean',
        'letter'      => 'J',
        'icon'        => 'ti-sun',
        'count'       => __( '9 fiches', 'schilo' ),
        'title'       => __( 'Les « Je suis » de Jean — la divinité de Jésus', 'schilo' ),
        'description' => __( 'Pain de vie, lumière du monde et résurrection : les révélations de Jésus dans Jean.', 'schilo' ),
        'url'         => get_search_link( 'Je suis Évangile de Jean' ),
    ],
];

// Parcours dynamiques : termes de premier niveau de la taxonomie schilo_parcours
// (Schilo Builder > Parcours & Thèmes), triés par ordre défini, limités à 3 pour l'accueil.
$paths = [];
if ( taxonomy_exists( 'schilo_parcours' ) ) {
    $parcours_terms = get_terms( [
        'taxonomy'   => 'schilo_parcours',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'meta_value_num',
        'meta_key'   => 'schilo_ordre',
        'order'      => 'ASC',
        'number'     => 3,
    ] );

    if ( ! is_wp_error( $parcours_terms ) && ! empty( $parcours_terms ) ) {
        // Reutilise les 3 variantes visuelles (degrades) deja definies dans home.css
        // pour .schilo-home-path--luc/--mat/--jean, independamment du contenu reel.
        $path_icons = [ 'ti-route', 'ti-mountain', 'ti-sun' ];
        $path_evs   = [ 'luc', 'mat', 'jean' ];

        foreach ( $parcours_terms as $index => $parcours_term ) {
            $children_ids  = get_term_children( $parcours_term->term_id, 'schilo_parcours' );
            $tax_query_ids = array_merge( [ $parcours_term->term_id ], is_array( $children_ids ) ? $children_ids : [] );

            $fiche_count = ( new WP_Query( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
                'tax_query'      => [ [ 'taxonomy' => 'schilo_parcours', 'field' => 'term_id', 'terms' => $tax_query_ids ] ],
            ] ) )->found_posts;

            $paths[] = [
                'ev'          => $path_evs[ $index % count( $path_evs ) ],
                'letter'      => mb_strtoupper( mb_substr( $parcours_term->name, 0, 1 ) ),
                'icon'        => $path_icons[ $index % count( $path_icons ) ],
                'count'       => sprintf( _n( '%d fiche', '%d fiches', $fiche_count, 'schilo' ), $fiche_count ),
                'title'       => $parcours_term->name,
                'description' => $parcours_term->description ?: __( 'Un parcours de lecture guidé, étape par étape.', 'schilo' ),
                'url'         => get_term_link( $parcours_term, 'schilo_parcours' ),
            ];
        }
    }
}

if ( empty( $paths ) ) {
    $paths = $paths_fallback;
}

// Séries thématiques dynamiques : termes de la taxonomie schilo_serie
// (Schilo Builder > Parcours & Thèmes), triées par nombre d'articles, limitées à 8.
$resources = [];
if ( taxonomy_exists( 'schilo_serie' ) ) {
    $serie_terms = get_terms( [
        'taxonomy'   => 'schilo_serie',
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => 8,
    ] );

    if ( ! is_wp_error( $serie_terms ) ) {
        $serie_evs = [ 'mat', 'marc', 'luc', 'jean' ];
        foreach ( $serie_terms as $index => $serie_term ) {
            $resources[] = [
                'ev'    => $serie_evs[ $index % count( $serie_evs ) ],
                'title' => $serie_term->name,
                'meta'  => sprintf( _n( '%d fiche', '%d fiches', $serie_term->count, 'schilo' ), $serie_term->count ),
                'url'   => get_term_link( $serie_term, 'schilo_serie' ),
            ];
        }
    }
}

$category_tones = [ 'blue', 'orange', 'green', 'violet', 'gold', 'orange', 'violet', 'blue' ];
$category_icons = [ 'ti-book-2', 'ti-baby-carriage', 'ti-users', 'ti-building', 'ti-heart-handshake', 'ti-cross', 'ti-sparkles', 'ti-shield-check' ];
$category_icons_by_slug = [
    'a-propos'                       => 'ti-info-circle',
    'annexes'                        => 'ti-files',
    'apocalypse'                     => 'ti-book-2',
    'doctrines-de-la-bible'          => 'ti-certificate',
    'faits-de-societe'               => 'ti-scale',
    'histoire-de-la-bible'           => 'ti-history',
    'la-revelation-de-daniel'        => 'ti-hourglass',
    'les-contradictions'             => 'ti-arrows-diff',
    'les-dix-plaies-degypte'         => 'ti-biohazard',
    'les-grands-hommes'              => 'ti-users',
    'les-paraboles'                  => 'ti-message-circle',
    'parole-dun-ami'                 => 'ti-heart-handshake',
    'renseignements-complementaires' => 'ti-notes',
    'societe'                        => 'ti-building-community',
    'synopse'                        => 'ti-timeline',
];
$child_icons_by_slug = [
    '1-la-vie-de-jesus-jusqua-12-ans'                         => 'ti-baby-carriage',
    '2-le-ministere-de-jean-le-baptiste'                      => 'ti-ripple',
    '3-le-debut-du-ministere-en-galilee'                      => 'ti-sunrise',
    '4-de-la-2eme-a-la-3eme-fete-de-paque'                    => 'ti-calendar-event',
    '5-de-la-3eme-paque-au-debut-de-la-derniere-semaine'      => 'ti-route',
    '6-la-derniere-semaine-avant-larrestation'                => 'ti-clock-hour-11',
    '7-la-derniere-journee-jusqua-la-crucifixion'             => 'ti-cross',
];
$category_description_fallbacks = [
    'a-propos' => __( 'Découvrez la mission, les valeurs et la démarche éditoriale de Schilo.', 'schilo' ),
    'synopse'  => __( 'Suivez chronologiquement les événements de la vie et du ministère de Jésus.', 'schilo' ),
];
?>

<section class="schilo-home-hero" aria-labelledby="schilo-home-title">
    <div class="schilo-container schilo-home-hero__grid">
        <div class="schilo-home-hero__content">
            <div class="schilo-home-hero__eyebrow">
                <i class="ti ti-flame" aria-hidden="true"></i>
                <?php esc_html_e( 'Schilo.org · Enquête sur Jésus', 'schilo' ); ?>
            </div>
            <h1 id="schilo-home-title" class="schilo-home-hero__title">
                <?php esc_html_e( 'Découvrir', 'schilo' ); ?>
                <em><?php esc_html_e( 'Jésus', 'schilo' ); ?></em>,<br>
                <?php esc_html_e( 'au-delà du grand homme', 'schilo' ); ?>
            </h1>
            <p class="schilo-home-hero__description">
                <?php esc_html_e( "Un site d'étude biblique structuré, rigoureux et accessible. Parcourez les Évangiles verset par verset, thème par thème, à votre propre rythme.", 'schilo' ); ?>
            </p>
            <div class="schilo-home-hero__actions">
                <a href="#parcours" class="schilo-home-button schilo-home-button--primary">
                    <i class="ti ti-route" aria-hidden="true"></i>
                    <?php esc_html_e( 'Commencer un parcours', 'schilo' ); ?>
                </a>
                <a href="#themes" class="schilo-home-button schilo-home-button--ghost">
                    <i class="ti ti-layout-grid" aria-hidden="true"></i>
                    <?php esc_html_e( 'Parcourir les thèmes', 'schilo' ); ?>
                </a>
            </div>
        </div>

        <div class="schilo-home-featured" aria-label="<?php esc_attr_e( 'Études à découvrir', 'schilo' ); ?>">
            <?php foreach ( $featured as $index => $item ) : ?>
                <a href="<?php echo esc_url( $item['url'] ); ?>"
                   class="schilo-home-featured__card schilo-home-featured__card--<?php echo esc_attr( $item['ev'] ); ?><?php echo 0 === $index ? ' schilo-home-featured__card--main' : ''; ?>">
                    <span class="schilo-home-featured__label"><?php echo esc_html( $item['label'] ); ?></span>
                    <strong><?php echo esc_html( $item['title'] ); ?></strong>
                    <span class="schilo-home-featured__meta">
                        <?php esc_html_e( "Découvrir l'étude", 'schilo' ); ?>
                        <i class="ti ti-arrow-right" aria-hidden="true"></i>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="schilo-container schilo-home-stats" aria-label="<?php esc_attr_e( 'Le contenu de Schilo', 'schilo' ); ?>">
        <div class="schilo-home-stat"><strong>4</strong><span><?php esc_html_e( 'Évangiles', 'schilo' ); ?></span></div>
        <div class="schilo-home-stat"><strong><?php echo esc_html( number_format_i18n( $published_posts ) ); ?>+</strong><span><?php esc_html_e( "Fiches d'étude", 'schilo' ); ?></span></div>
        <div class="schilo-home-stat"><strong><?php echo esc_html( number_format_i18n( $annexe_count ) ); ?></strong><span><?php esc_html_e( 'Annexes', 'schilo' ); ?></span></div>
        <div class="schilo-home-stat"><strong><?php echo esc_html( number_format_i18n( $published_pages ) ); ?>+</strong><span><?php esc_html_e( 'Ressources', 'schilo' ); ?></span></div>
        <div class="schilo-home-stat"><strong><?php echo esc_html( number_format_i18n( $category_count ) ); ?>+</strong><span><?php esc_html_e( 'Thèmes', 'schilo' ); ?></span></div>
        <div class="schilo-home-stat"><strong><?php echo esc_html( number_format_i18n( Schilo_Visitors::get_total() ) ); ?></strong><span><?php esc_html_e( 'Visiteurs', 'schilo' ); ?></span></div>
    </div>

    <?php $votd = Schilo_Bible::get_verse_of_day(); ?>
    <div class="schilo-container schilo-home-verse">
        <?php if ( $votd ) : ?>
        <div class="schilo-home-verse__identity">
            <span class="schilo-home-verse__badge"><?php echo esc_html( mb_substr( $votd->book, 0, 1 ) ); ?></span>
            <span><?php echo esc_html( $votd->book ); ?></span>
        </div>
        <div class="schilo-home-verse__content">
            <div class="schilo-home-verse__eyebrow">
                <i class="ti ti-sun" aria-hidden="true"></i>
                <?php esc_html_e( 'Verset du jour', 'schilo' ); ?> — <?php echo esc_html( wp_date( 'j F Y' ) ); ?>
            </div>
            <div class="schilo-home-verse__reference">
                <?php echo Schilo_Bible::format_ref( $votd ); ?>
                <span class="schilo-home-verse__version"><?php echo esc_html( $votd->version_name ); ?></span>
            </div>
            <blockquote>« <?php echo esc_html( $votd->verse_text ); ?> »</blockquote>
            <?php if ( ! empty( $votd->copyright ) ) : ?>
            <p class="schilo-home-verse__copyright"><?php echo esc_html( $votd->copyright ); ?></p>
            <?php endif; ?>
        </div>
        <div class="schilo-home-verse__actions">
            <a href="<?php echo esc_url( get_search_link( Schilo_Bible::format_ref( $votd ) ) ); ?>" class="schilo-home-button schilo-home-button--primary">
                <?php esc_html_e( 'Voir les études', 'schilo' ); ?>
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<section id="parcours" class="schilo-home-section schilo-home-section--muted" aria-labelledby="schilo-paths-title">
    <div class="schilo-container">
        <div class="schilo-home-section__heading">
            <div>
                <h2 id="schilo-paths-title"><?php esc_html_e( "Parcours d'étude", 'schilo' ); ?></h2>
                <p><?php esc_html_e( 'Étudiez les Évangiles fiche par fiche, à votre rythme.', 'schilo' ); ?></p>
            </div>
            <a href="<?php echo esc_url( $synopsis_url ); ?>">
                <?php esc_html_e( 'Voir la synopse', 'schilo' ); ?>
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="schilo-home-paths">
            <?php foreach ( $paths as $path ) : ?>
                <a href="<?php echo esc_url( $path['url'] ); ?>" class="schilo-home-path schilo-home-path--<?php echo esc_attr( $path['ev'] ); ?>">
                    <div class="schilo-home-path__visual">
                        <div class="schilo-home-path__top">
                            <span class="schilo-home-path__badge"><?php echo esc_html( $path['letter'] ); ?></span>
                            <span class="schilo-home-path__count"><?php echo esc_html( $path['count'] ); ?></span>
                        </div>
                        <i class="ti <?php echo esc_attr( $path['icon'] ); ?> schilo-home-path__icon" aria-hidden="true"></i>
                    </div>
                    <div class="schilo-home-path__body">
                        <h3><?php echo esc_html( $path['title'] ); ?></h3>
                        <p><?php echo esc_html( wp_trim_words( $path['description'], 18, '…' ) ); ?></p>
                        <span class="schilo-home-path__link">
                            <?php esc_html_e( 'Commencer le parcours', 'schilo' ); ?>
                            <i class="ti ti-arrow-right" aria-hidden="true"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ( ! empty( $resources ) ) : ?>
        <div class="schilo-home-series">
            <div class="schilo-home-series__heading">
                <span><?php esc_html_e( 'Bibliothèque Schilo', 'schilo' ); ?></span>
                <h3><?php esc_html_e( 'Séries thématiques', 'schilo' ); ?></h3>
            </div>
            <div class="schilo-home-resources">
                <?php foreach ( $resources as $resource ) :
                    $resource_letter = mb_strtoupper( mb_substr( $resource['title'], 0, 1 ) );
                ?>
                    <a href="<?php echo esc_url( $resource['url'] ); ?>"
                       class="schilo-home-resource schilo-home-resource--<?php echo esc_attr( $resource['ev'] ); ?>"
                       data-letter="<?php echo esc_attr( $resource_letter ); ?>">
                        <strong><?php echo esc_html( $resource['title'] ); ?></strong>
                        <span class="schilo-home-resource__meta"><?php echo esc_html( $resource['meta'] ); ?></span>
                        <span class="schilo-home-resource__link">
                            <?php esc_html_e( 'Découvrir', 'schilo' ); ?>
                            <i class="ti ti-arrow-right" aria-hidden="true"></i>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<section id="themes" class="schilo-home-section" aria-labelledby="schilo-themes-title">
    <div class="schilo-container">
        <div class="schilo-home-section__heading">
            <div>
                <h2 id="schilo-themes-title"><?php esc_html_e( 'Explorer par thème', 'schilo' ); ?></h2>
                <p><?php esc_html_e( 'Parcourez les grandes catégories éditoriales du site.', 'schilo' ); ?></p>
            </div>
            <a href="<?php echo esc_url( $sitemap_url ); ?>">
                <?php esc_html_e( 'Tous les thèmes', 'schilo' ); ?>
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="schilo-home-themes">
            <?php foreach ( $main_categories as $index => $category ) : ?>
                <?php
                $matches              = [];
                $category_number      = '';
                $category_title       = $category->name;
                $category_description = wp_strip_all_tags( category_description( $category->term_id ) );
                $tone                 = $category_tones[ $index % count( $category_tones ) ];
                $icon                 = $category_icons[ $index % count( $category_icons ) ];

                if ( preg_match( '/^(\d+)\s*-\s*/u', $category->name, $matches ) ) {
                    $category_number = $matches[1];
                    $category_title  = schilo_strip_category_number( $category->name );
                }

                if ( '' === $category_description ) {
                    $category_description = sprintf(
                        _n( '%s article disponible', '%s articles disponibles', (int) $category->count, 'schilo' ),
                        number_format_i18n( (int) $category->count )
                    );
                }
                ?>
                <a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" class="schilo-home-theme schilo-home-theme--<?php echo esc_attr( $tone ); ?>">
                    <span class="schilo-home-theme__top">
                        <span class="schilo-home-theme__icon"><i class="ti <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i></span>
                        <span class="schilo-home-theme__number"><?php echo esc_html( str_pad( $category_number, 2, '0', STR_PAD_LEFT ) ); ?></span>
                    </span>
                    <h3><?php echo esc_html( $category_title ); ?></h3>
                    <p><?php echo esc_html( $category_description ); ?></p>
                    <span class="schilo-home-theme__link">
                        <?php esc_html_e( 'Voir les articles', 'schilo' ); ?>
                        <i class="ti ti-arrow-right" aria-hidden="true"></i>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ( ! empty( $other_categories ) ) : ?>
            <div class="schilo-home-lib">
                <div class="schilo-home-lib__heading">
                    <div>
                        <span><?php esc_html_e( 'Bibliothèque Schilo', 'schilo' ); ?></span>
                        <h3><?php esc_html_e( 'Ressources et catégories éditoriales', 'schilo' ); ?></h3>
                    </div>
                </div>
                <div class="schilo-home-lib__grid">
                    <?php foreach ( $other_categories as $lib_index => $category ) :
                        $lib_children  = $children_by_parent[ (int) $category->term_id ] ?? [];
                        $lib_icon      = $category_icons_by_slug[ $category->slug ] ?? 'ti-folder-open';
                        $lib_color     = ( $lib_index % 6 ) + 1;
                        $lib_has_child = ! empty( $lib_children );
                        if ( $lib_has_child ) :
                            $lib_child_total = array_sum( array_column( (array) $lib_children, 'count' ) );
                            $lib_child_id    = 'lib-ch-' . $category->term_id;
                    ?>
                        <div class="schilo-home-lib__group schilo-home-lib__group--c<?php echo esc_attr( $lib_color ); ?>">
                            <div class="schilo-home-lib__group-row">
                                <a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" class="schilo-home-lib__card schilo-home-lib__card--c<?php echo esc_attr( $lib_color ); ?>">
                                    <span class="schilo-home-lib__icon"><i class="ti <?php echo esc_attr( $lib_icon ); ?>" aria-hidden="true"></i></span>
                                    <span class="schilo-home-lib__name"><?php echo esc_html( $category->name ); ?></span>
                                    <span class="schilo-home-lib__badge"><?php echo esc_html( count( $lib_children ) ); ?> <span><?php esc_html_e( 'thèmes', 'schilo' ); ?></span></span>
                                </a>
                                <button class="schilo-home-lib__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $lib_child_id ); ?>">
                                    <i class="ti ti-chevron-down" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="schilo-home-lib__children" id="<?php echo esc_attr( $lib_child_id ); ?>" hidden>
                                <?php foreach ( $lib_children as $lib_child ) :
                                    $lib_child_icon = $child_icons_by_slug[ $lib_child->slug ] ?? 'ti-folder';
                                ?>
                                    <a href="<?php echo esc_url( get_category_link( $lib_child->term_id ) ); ?>" class="schilo-home-lib__child">
                                        <i class="ti <?php echo esc_attr( $lib_child_icon ); ?>" aria-hidden="true"></i>
                                        <span class="schilo-home-lib__child-name"><?php echo esc_html( $lib_child->name ); ?></span>
                                        <small><?php echo esc_html( number_format_i18n( (int) $lib_child->count ) ); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else : ?>
                        <a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" class="schilo-home-lib__card schilo-home-lib__card--c<?php echo esc_attr( $lib_color ); ?>">
                            <span class="schilo-home-lib__icon"><i class="ti <?php echo esc_attr( $lib_icon ); ?>" aria-hidden="true"></i></span>
                            <span class="schilo-home-lib__name"><?php echo esc_html( $category->name ); ?></span>
                            <span class="schilo-home-lib__badge"><?php echo esc_html( number_format_i18n( (int) $category->count ) ); ?></span>
                            <i class="ti ti-arrow-right schilo-home-lib__arrow" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="schilo-home-cta">
            <div>
                <span class="schilo-home-cta__eyebrow"><?php esc_html_e( 'Une étude structurée et accessible', 'schilo' ); ?></span>
                <h2><?php esc_html_e( 'Choisissez un sujet et avancez à votre rythme.', 'schilo' ); ?></h2>
            </div>
            <div class="schilo-home-cta__actions">
                <a href="<?php echo esc_url( $gospels_url ); ?>" class="schilo-home-button schilo-home-button--primary">
                    <?php esc_html_e( 'Découvrir les Évangiles', 'schilo' ); ?>
                </a>
                <a href="<?php echo esc_url( $sitemap_url ); ?>" class="schilo-home-button schilo-home-button--light">
                    <?php esc_html_e( 'Consulter le plan du site', 'schilo' ); ?>
                </a>
            </div>
        </div>
    </div>
</section>
