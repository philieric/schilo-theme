<?php
/* Template Name: Recherche avancée */
defined( 'ABSPATH' ) || exit;

/**
 * Page de recherche avancée : filtres cochables (thème/parcours/série/type
 * d'article/livre biblique) + texte libre, résultats en AJAX (voir
 * Schilo_Advanced_Search + assets/js/advanced-search.js).
 */

$schilo_filters = Schilo_Advanced_Search::get_filters_data();

get_header();
?>

<div class="schilo-hero">
    <div class="schilo-hero__inner">
        <div class="schilo-hero__eyebrow"><i class="ti ti-filter" aria-hidden="true"></i> <?php esc_html_e( 'Recherche avancée', 'schilo' ); ?></div>
        <h1 class="schilo-hero__title schilo-serif"><?php esc_html_e( 'Trouvez un article précis', 'schilo' ); ?></h1>
        <p class="schilo-hero__desc"><?php esc_html_e( 'Combinez texte libre, thème, parcours, série, type d’article et livre biblique.', 'schilo' ); ?></p>
    </div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container schilo-adv-search" id="schilo-adv-search" data-nonce="<?php echo esc_attr( wp_create_nonce( 'schilo_nonce' ) ); ?>">

    <div class="schilo-adv-search__filters" aria-label="<?php esc_attr_e( 'Filtres de recherche', 'schilo' ); ?>">

        <div class="schilo-adv-search__field">
            <div class="schilo-adv-search__input-wrap">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" id="schilo-adv-q" placeholder="<?php esc_attr_e( 'Titre, mot-clé, résumé…', 'schilo' ); ?>" autocomplete="off">
            </div>
        </div>

        <?php if ( ! empty( $schilo_filters['themes'] ) ) : ?>
        <details class="schilo-adv-search__group">
            <summary><i class="ti ti-category" aria-hidden="true"></i> <?php esc_html_e( 'Thème', 'schilo' ); ?></summary>
            <div class="schilo-adv-search__group-body">
                <?php Schilo_Advanced_Search::render_term_checkboxes( $schilo_filters['themes'], 'theme' ); ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if ( ! empty( $schilo_filters['parcours'] ) ) : ?>
        <details class="schilo-adv-search__group">
            <summary><i class="ti ti-route" aria-hidden="true"></i> <?php esc_html_e( 'Parcours', 'schilo' ); ?></summary>
            <div class="schilo-adv-search__group-body">
                <?php Schilo_Advanced_Search::render_term_checkboxes( $schilo_filters['parcours'], 'parcours' ); ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if ( ! empty( $schilo_filters['series'] ) ) : ?>
        <details class="schilo-adv-search__group">
            <summary><i class="ti ti-stack-2" aria-hidden="true"></i> <?php esc_html_e( 'Série', 'schilo' ); ?></summary>
            <div class="schilo-adv-search__group-body">
                <?php foreach ( $schilo_filters['series'] as $serie ) : ?>
                    <label class="schilo-adv-check">
                        <input type="checkbox" name="serie[]" value="<?php echo esc_attr( $serie['id'] ); ?>">
                        <span><?php echo esc_html( $serie['name'] ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if ( ! empty( $schilo_filters['types'] ) ) : ?>
        <details class="schilo-adv-search__group">
            <summary><i class="ti ti-tag" aria-hidden="true"></i> <?php esc_html_e( 'Type d’article', 'schilo' ); ?></summary>
            <div class="schilo-adv-search__group-body">
                <?php foreach ( $schilo_filters['types'] as $type ) : ?>
                    <label class="schilo-adv-check">
                        <input type="checkbox" name="type[]" value="<?php echo esc_attr( $type['code'] ); ?>">
                        <span><?php echo esc_html( $type['label'] ); ?> <small><?php echo esc_html( $type['code'] ); ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if ( ! empty( $schilo_filters['books']['ancien'] ) || ! empty( $schilo_filters['books']['nouveau'] ) ) : ?>
        <details class="schilo-adv-search__group">
            <summary><i class="ti ti-book-2" aria-hidden="true"></i> <?php esc_html_e( 'Livre biblique', 'schilo' ); ?></summary>
            <div class="schilo-adv-search__group-body">
                <?php if ( ! empty( $schilo_filters['books']['nouveau'] ) ) : ?>
                    <p class="schilo-adv-search__subgroup-title"><?php esc_html_e( 'Nouveau Testament', 'schilo' ); ?></p>
                    <?php foreach ( $schilo_filters['books']['nouveau'] as $book ) : ?>
                        <label class="schilo-adv-check">
                            <input type="checkbox" name="livre[]" value="<?php echo esc_attr( $book['code'] ); ?>">
                            <span><?php echo esc_html( $book['title'] ); ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ( ! empty( $schilo_filters['books']['ancien'] ) ) : ?>
                    <p class="schilo-adv-search__subgroup-title"><?php esc_html_e( 'Ancien Testament', 'schilo' ); ?></p>
                    <?php foreach ( $schilo_filters['books']['ancien'] as $book ) : ?>
                        <label class="schilo-adv-check">
                            <input type="checkbox" name="livre[]" value="<?php echo esc_attr( $book['code'] ); ?>">
                            <span><?php echo esc_html( $book['title'] ); ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <button type="button" class="schilo-adv-search__reset" id="schilo-adv-reset">
            <i class="ti ti-x" aria-hidden="true"></i> <?php esc_html_e( 'Réinitialiser', 'schilo' ); ?>
        </button>
    </div>

    <div class="schilo-adv-search__active" id="schilo-adv-active" aria-label="<?php esc_attr_e( 'Critères actifs', 'schilo' ); ?>" hidden></div>

    <section class="schilo-adv-search__results" aria-live="polite">
        <div class="schilo-adv-search__results-header">
            <p class="schilo-adv-search__count" id="schilo-adv-count"></p>

            <!-- Mode d'affichage — même bouton/même logique que les archives
                 (Schilo.ArchiveView dans schilo.js, préférence en localStorage
                 partagée avec le reste du site). -->
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
                <button class="schilo-archive-view-btn schilo-archive-view-btn--compact"
                        data-view="compact"
                        aria-pressed="false"
                        title="<?php esc_attr_e( 'Liste réduite (titres uniquement)', 'schilo' ); ?>">
                    <i class="ti ti-list-details" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <p class="schilo-adv-search__start" id="schilo-adv-start">
            <i class="ti ti-filter" aria-hidden="true"></i>
            <?php esc_html_e( 'Saisissez un texte ou cochez au moins un critère pour lancer la recherche.', 'schilo' ); ?>
        </p>

        <div class="schilo-archive-posts schilo-archive-posts--grid" id="schilo-archive-posts"></div>

        <p class="schilo-adv-search__empty" id="schilo-adv-empty" hidden>
            <i class="ti ti-mood-sad" aria-hidden="true"></i>
            <?php esc_html_e( 'Aucun article ne correspond à ces critères.', 'schilo' ); ?>
        </p>

        <div class="schilo-adv-search__loadmore-wrap">
            <button type="button" class="button schilo-adv-search__loadmore" id="schilo-adv-loadmore" hidden>
                <?php esc_html_e( 'Charger plus', 'schilo' ); ?>
            </button>
            <span class="schilo-adv-search__spinner" id="schilo-adv-spinner" aria-hidden="true" hidden></span>
        </div>
    </section>

</div>
</main>

<?php get_footer(); ?>
