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

// Categories retirees de l'accueil via Schilo Builder > Prefixes & categories
// (visibilite par categorie) — restent en ligne, juste absentes de ces grilles.
$home_excluded_category_ids = array_map( 'absint', (array) get_option( 'schilo_builder_home_excluded_categories', [] ) );

$main_categories  = [];
$other_categories = [];
$children_by_parent = [];

foreach ( $root_categories as $category ) {
    if ( 'non-classe' === $category->slug || 'uncategorized' === $category->slug ) {
        continue;
    }

    if ( in_array( (int) $category->term_id, $home_excluded_category_ids, true ) ) {
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

// Service partage pour la rotation periodique (Parcours & Themes > Configuration) :
// meme principe que Schilo_Featured::get(), sans cron, base sur le temps ecoule.
$classement_service = new \Schilo\Builder\Service\ClassementService();

// Parcours dynamiques : termes de premier niveau de la taxonomie schilo_parcours
// (Schilo Builder > Parcours & Thèmes), triés par ordre défini. Le sous-ensemble
// affiché peut tourner périodiquement (voir Configuration > Rotation).
$paths = [];
if ( taxonomy_exists( 'schilo_parcours' ) ) {
    $parcours_pool = get_terms( [
        'taxonomy'   => 'schilo_parcours',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'meta_value_num',
        'meta_key'   => 'schilo_ordre',
        'order'      => 'ASC',
    ] );

    if ( ! is_wp_error( $parcours_pool ) && ! empty( $parcours_pool ) ) {
        $pool_by_id     = array_column( $parcours_pool, null, 'term_id' );
        $rotated_ids    = $classement_service->getRotatedTermIds( 'schilo_parcours', wp_list_pluck( $parcours_pool, 'term_id' ) );
        $parcours_terms = array_values( array_filter( array_map( fn( $id ) => $pool_by_id[ $id ] ?? null, $rotated_ids ) ) );

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
// (Schilo Builder > Parcours & Thèmes), triées par nombre d'articles. Le
// sous-ensemble affiché peut tourner périodiquement (voir Configuration > Rotation).
$resources = [];
if ( taxonomy_exists( 'schilo_serie' ) ) {
    $serie_pool = get_terms( [
        'taxonomy'   => 'schilo_serie',
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ] );

    if ( ! is_wp_error( $serie_pool ) && ! empty( $serie_pool ) ) {
        $pool_by_id  = array_column( $serie_pool, null, 'term_id' );
        $rotated_ids = $classement_service->getRotatedTermIds( 'schilo_serie', wp_list_pluck( $serie_pool, 'term_id' ) );
        $serie_terms = array_values( array_filter( array_map( fn( $id ) => $pool_by_id[ $id ] ?? null, $rotated_ids ) ) );

        $serie_evs    = [ 'mat', 'marc', 'luc', 'jean' ];
        $serie_icons  = [ 'ti-stack-2', 'ti-list-details', 'ti-bookmarks', 'ti-layers-intersect' ];
        foreach ( $serie_terms as $index => $serie_term ) {
            $resources[] = [
                'ev'    => $serie_evs[ $index % count( $serie_evs ) ],
                'icon'  => $serie_icons[ $index % count( $serie_icons ) ],
                'title' => $serie_term->name,
                'desc'  => wp_strip_all_tags( $serie_term->description ),
                'meta'  => sprintf( _n( '%d fiche', '%d fiches', $serie_term->count, 'schilo' ), $serie_term->count ),
                'url'   => get_term_link( $serie_term, 'schilo_serie' ),
            ];
        }
    }
}

// Thèmes dynamiques : termes de premier niveau de la taxonomie schilo_theme
// (Schilo Builder > Parcours & Thèmes), avec la même rotation périodique optionnelle.
$themes = [];
if ( taxonomy_exists( 'schilo_theme' ) ) {
    $theme_pool = get_terms( [
        'taxonomy'   => 'schilo_theme',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'meta_value_num',
        'meta_key'   => 'schilo_ordre',
        'order'      => 'ASC',
    ] );

    if ( ! is_wp_error( $theme_pool ) && ! empty( $theme_pool ) ) {
        $pool_by_id  = array_column( $theme_pool, null, 'term_id' );
        $rotated_ids = $classement_service->getRotatedTermIds( 'schilo_theme', wp_list_pluck( $theme_pool, 'term_id' ) );
        $theme_terms = array_values( array_filter( array_map( fn( $id ) => $pool_by_id[ $id ] ?? null, $rotated_ids ) ) );

        $theme_evs   = [ 'jean', 'mat', 'marc', 'luc' ];
        $theme_icons = [ 'ti-category-2', 'ti-hash', 'ti-sparkles', 'ti-book-2' ];
        foreach ( $theme_terms as $index => $theme_term ) {
            $themes[] = [
                'ev'    => $theme_evs[ $index % count( $theme_evs ) ],
                'icon'  => $theme_icons[ $index % count( $theme_icons ) ],
                'title' => $theme_term->name,
                'desc'  => wp_strip_all_tags( $theme_term->description ),
                'meta'  => sprintf( _n( '%d fiche', '%d fiches', $theme_term->count, 'schilo' ), $theme_term->count ),
                'url'   => get_term_link( $theme_term, 'schilo_theme' ),
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
    'a-propos'                        => __( 'Découvrez la mission, les valeurs et la démarche éditoriale de Schilo.', 'schilo' ),
    'synopse'                         => __( 'Suivez chronologiquement les événements de la vie et du ministère de Jésus.', 'schilo' ),
    'analyse-comparative-des-textes'  => __( 'Comparez les récits parallèles des Évangiles pour en dégager les nuances propres à chaque auteur.', 'schilo' ),
    'details'                         => __( "Des précisions techniques et des points d'analyse approfondis sur des sujets ponctuels.", 'schilo' ),
    'exorcismes'                      => __( 'Les récits de délivrance et de confrontation avec les forces spirituelles dans les Évangiles.', 'schilo' ),
    'guerisons'                       => __( 'Les guérisons accomplies par Jésus, replacées dans leur contexte historique et théologique.', 'schilo' ),
    'miracles'                        => __( "L'ensemble des miracles rapportés dans les Évangiles, classés et analysés un à un.", 'schilo' ),
    'miracles-sur-la-nature'          => __( 'Les miracles de Jésus sur les éléments naturels : tempêtes apaisées, multiplication des pains, marche sur l\'eau.', 'schilo' ),
    'notes-geographiques'             => __( 'Des repères sur les lieux, régions et itinéraires mentionnés dans les récits bibliques.', 'schilo' ),
    'notes-historiques'               => __( 'Le contexte historique, politique et culturel qui éclaire les événements racontés dans la Bible.', 'schilo' ),
    'paraboles'                       => __( 'Les paraboles de Jésus, expliquées une à une pour en saisir le sens et la portée.', 'schilo' ),
    'resurrections'                   => __( "Les récits de résurrection rapportés dans les Évangiles, au-delà de celle de Jésus lui-même.", 'schilo' ),
    'series-bibliques'                => __( "Des séries d'articles organisées autour d'un même fil conducteur biblique.", 'schilo' ),
    'textes-biliques'                 => __( 'Les textes bibliques présentés et commentés, organisés par thème ou par livre.', 'schilo' ),
    'thematiques'                     => __( "Des dossiers thématiques regroupant plusieurs études autour d'un même sujet.", 'schilo' ),
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

    <?php $votd = Schilo_Reflection::get_reflection_of_day() ?: Schilo_Bible::get_verse_of_day(); ?>
    <div class="schilo-container schilo-home-verse">
        <?php if ( $votd ) : ?>
        <div class="schilo-home-verse__identity">
            <span class="schilo-home-verse__badge"><?php echo esc_html( mb_substr( $votd->book, 0, 1 ) ); ?></span>
            <span><?php echo esc_html( $votd->book ); ?></span>
        </div>
        <div class="schilo-home-verse__content">
            <div class="schilo-home-verse__eyebrow">
                <i class="ti ti-sun" aria-hidden="true"></i>
                <?php echo esc_html( ! empty( $votd->reflection ) ? __( 'Réflexion du jour', 'schilo' ) : __( 'Verset du jour', 'schilo' ) ); ?> — <?php echo esc_html( wp_date( 'j F Y' ) ); ?>
            </div>
            <div class="schilo-home-verse__reference">
                <?php echo Schilo_Bible::format_ref( $votd ); ?>
                <span class="schilo-home-verse__version"><?php echo esc_html( $votd->version_name ); ?></span>
            </div>
            <blockquote>« <?php echo esc_html( $votd->verse_text ); ?> »</blockquote>
            <?php if ( ! empty( $votd->reflection ) ) : ?>
            <p class="schilo-home-verse__reflection"><?php echo esc_html( $votd->reflection ); ?></p>
            <?php endif; ?>
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
                        <?php if ( ! empty( $resource['icon'] ) ) : ?>
                            <span class="schilo-home-resource__icon" aria-hidden="true"><i class="ti <?php echo esc_attr( $resource['icon'] ); ?>"></i></span>
                        <?php endif; ?>
                        <strong><?php echo esc_html( $resource['title'] ); ?></strong>
                        <?php if ( ! empty( $resource['desc'] ) ) : ?>
                            <span class="schilo-home-resource__desc"><?php echo esc_html( wp_trim_words( $resource['desc'], 16, '…' ) ); ?></span>
                        <?php endif; ?>
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

        <?php if ( ! empty( $themes ) ) : ?>
        <div class="schilo-home-series">
            <div class="schilo-home-series__heading">
                <span><?php esc_html_e( 'Bibliothèque Schilo', 'schilo' ); ?></span>
                <h3><?php esc_html_e( 'Thèmes', 'schilo' ); ?></h3>
            </div>
            <div class="schilo-home-resources">
                <?php foreach ( $themes as $theme ) :
                    $theme_letter = mb_strtoupper( mb_substr( $theme['title'], 0, 1 ) );
                ?>
                    <a href="<?php echo esc_url( $theme['url'] ); ?>"
                       class="schilo-home-resource schilo-home-resource--<?php echo esc_attr( $theme['ev'] ); ?>"
                       data-letter="<?php echo esc_attr( $theme_letter ); ?>">
                        <?php if ( ! empty( $theme['icon'] ) ) : ?>
                            <span class="schilo-home-resource__icon" aria-hidden="true"><i class="ti <?php echo esc_attr( $theme['icon'] ); ?>"></i></span>
                        <?php endif; ?>
                        <strong><?php echo esc_html( $theme['title'] ); ?></strong>
                        <?php if ( ! empty( $theme['desc'] ) ) : ?>
                            <span class="schilo-home-resource__desc"><?php echo esc_html( wp_trim_words( $theme['desc'], 16, '…' ) ); ?></span>
                        <?php endif; ?>
                        <span class="schilo-home-resource__meta"><?php echo esc_html( $theme['meta'] ); ?></span>
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
                            $lib_child_id = 'lib-ch-' . $category->term_id;
                            $lib_desc     = wp_strip_all_tags( category_description( $category->term_id ) );
                            if ( '' !== $lib_desc ) {
                                $lib_desc = wp_trim_words( $lib_desc, 16, '…' );
                            } elseif ( isset( $category_description_fallbacks[ $category->slug ] ) ) {
                                $lib_desc = $category_description_fallbacks[ $category->slug ];
                            } else {
                                $lib_desc = sprintf(
                                    _n( '%s article disponible', '%s articles disponibles', (int) $category->count, 'schilo' ),
                                    number_format_i18n( (int) $category->count )
                                );
                            }
                    ?>
                        <div class="schilo-home-lib__group schilo-home-lib__group--c<?php echo esc_attr( $lib_color ); ?> open">
                            <div class="schilo-home-lib__group-row">
                                <a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" class="schilo-home-lib__card schilo-home-lib__card--c<?php echo esc_attr( $lib_color ); ?>">
                                    <span class="schilo-home-lib__icon"><i class="ti <?php echo esc_attr( $lib_icon ); ?>" aria-hidden="true"></i></span>
                                    <span class="schilo-home-lib__body">
                                        <span class="schilo-home-lib__name"><?php echo esc_html( $category->name ); ?></span>
                                        <span class="schilo-home-lib__desc"><?php echo esc_html( $lib_desc ); ?></span>
                                    </span>
                                </a>
                                <button class="schilo-home-lib__toggle" aria-expanded="true" aria-controls="<?php echo esc_attr( $lib_child_id ); ?>">
                                    <i class="ti ti-chevron-down" aria-hidden="true"></i>
                                    <span><?php echo esc_html( sprintf( _n( '%s thème', '%s thèmes', count( $lib_children ), 'schilo' ), number_format_i18n( count( $lib_children ) ) ) ); ?></span>
                                </button>
                            </div>
                            <div class="schilo-home-lib__children" id="<?php echo esc_attr( $lib_child_id ); ?>">
                                <?php foreach ( $lib_children as $lib_child ) :
                                    $lib_child_icon  = $child_icons_by_slug[ $lib_child->slug ] ?? 'ti-folder';
                                    $lib_child_title = preg_replace( '/^\d+\s*-\s*/u', '', $lib_child->name );
                                    $lib_child_desc  = wp_strip_all_tags( category_description( $lib_child->term_id ) );
                                    $lib_child_desc  = $lib_child_desc
                                        ? wp_trim_words( $lib_child_desc, 14, '…' )
                                        : sprintf( _n( '%s article', '%s articles', $lib_child->count, 'schilo' ), number_format_i18n( (int) $lib_child->count ) );
                                ?>
                                    <a href="<?php echo esc_url( get_category_link( $lib_child->term_id ) ); ?>" class="schilo-home-lib__child">
                                        <span class="schilo-home-lib__child-icon"><i class="ti <?php echo esc_attr( $lib_child_icon ); ?>" aria-hidden="true"></i></span>
                                        <span class="schilo-home-lib__child-body">
                                            <span class="schilo-home-lib__child-top">
                                                <span class="schilo-home-lib__child-name"><?php echo esc_html( $lib_child_title ); ?></span>
                                                <small><?php echo esc_html( number_format_i18n( (int) $lib_child->count ) ); ?></small>
                                            </span>
                                            <span class="schilo-home-lib__child-desc"><?php echo esc_html( $lib_child_desc ); ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else :
                        $lib_desc = wp_strip_all_tags( category_description( $category->term_id ) );
                        if ( '' !== $lib_desc ) {
                            $lib_desc = wp_trim_words( $lib_desc, 16, '…' );
                        } elseif ( isset( $category_description_fallbacks[ $category->slug ] ) ) {
                            $lib_desc = $category_description_fallbacks[ $category->slug ];
                        } else {
                            $lib_desc = sprintf(
                                _n( '%s article disponible', '%s articles disponibles', (int) $category->count, 'schilo' ),
                                number_format_i18n( (int) $category->count )
                            );
                        }
                    ?>
                        <a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" class="schilo-home-lib__card schilo-home-lib__card--c<?php echo esc_attr( $lib_color ); ?>">
                            <span class="schilo-home-lib__icon"><i class="ti <?php echo esc_attr( $lib_icon ); ?>" aria-hidden="true"></i></span>
                            <span class="schilo-home-lib__body">
                                <span class="schilo-home-lib__name"><?php echo esc_html( $category->name ); ?></span>
                                <span class="schilo-home-lib__desc"><?php echo esc_html( $lib_desc ); ?></span>
                            </span>
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
