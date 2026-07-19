<?php
/* Template Name: Parcours, thèmes & séries */
defined( 'ABSPATH' ) || exit;

/**
 * Page-index listant les 3 axes de classement issus de Schilo Builder :
 * parcours, thèmes et séries. Chaque terme de premier niveau renvoie vers
 * son archive (taxonomy-schilo_parcours.php / taxonomy-schilo_theme.php /
 * taxonomy-schilo_serie.php).
 */

$schilo_top_terms = function ( string $taxonomy ): array {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return [];
	}

	$terms = get_terms( [
		'taxonomy'   => $taxonomy,
		'parent'     => 0,
		'hide_empty' => true,
		'orderby'    => 'meta_value_num',
		'meta_key'   => 'schilo_ordre',
		'order'      => 'ASC',
	] );

	return is_wp_error( $terms ) ? [] : $terms;
};

$all_sections = [
	'parcours' => [
		'axe'         => 'parcours',
		'taxonomy'    => 'schilo_parcours',
		'title'       => __( 'Parcours', 'schilo' ),
		'icon'        => 'ti-route',
		'description' => __( 'Des suites de fiches à lire dans l’ordre, étape par étape.', 'schilo' ),
	],
	'theme' => [
		'axe'         => 'theme',
		'taxonomy'    => 'schilo_theme',
		'title'       => __( 'Thèmes', 'schilo' ),
		'icon'        => 'ti-category',
		'description' => __( 'Un classement thématique issu de l’indexation IA des articles.', 'schilo' ),
	],
	'serie' => [
		'axe'         => 'serie',
		'taxonomy'    => 'schilo_serie',
		'title'       => __( 'Séries', 'schilo' ),
		'icon'        => 'ti-stack-2',
		'description' => __( 'Des regroupements transverses d’articles autour d’un même fil.', 'schilo' ),
	],
];

// Axe demandé (?axe=parcours|theme|serie) : n'affiche que cet axe. Sans
// paramètre valide, affiche les trois.
$current_axe = isset( $_GET['axe'] ) ? sanitize_key( wp_unslash( $_GET['axe'] ) ) : '';
if ( ! isset( $all_sections[ $current_axe ] ) ) {
	$current_axe = '';
}

$sections = $current_axe !== '' ? [ $all_sections[ $current_axe ] ] : array_values( $all_sections );

// Hero adapté à l'axe courant.
if ( $current_axe !== '' ) {
	$hero_title = $all_sections[ $current_axe ]['title'];
	$hero_desc  = $all_sections[ $current_axe ]['description'];
} else {
	$hero_title = __( 'Parcours, thèmes & séries', 'schilo' );
	$hero_desc  = __( 'Retrouvez les articles classés selon trois axes complémentaires : un parcours de lecture guidé, un thème d’étude, ou une série transverse.', 'schilo' );
}

$base_url = schilo_parcours_index_base_url();

get_header();
?>

<div class="schilo-hero">
	<div class="schilo-hero__inner">
		<div class="schilo-hero__eyebrow"><i class="ti ti-route"></i> <?php esc_html_e( 'Explorer', 'schilo' ); ?></div>
		<h1 class="schilo-hero__title schilo-serif"><?php echo esc_html( $hero_title ); ?></h1>
		<p class="schilo-hero__desc"><?php echo esc_html( $hero_desc ); ?></p>
	</div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">

	<nav class="schilo-axe-tabs" aria-label="<?php esc_attr_e( 'Filtrer par axe', 'schilo' ); ?>">
		<a class="schilo-axe-tab<?php echo $current_axe === '' ? ' is-active' : ''; ?>" href="<?php echo esc_url( $base_url ); ?>"<?php echo $current_axe === '' ? ' aria-current="page"' : ''; ?>>
			<i class="ti ti-layout-grid" aria-hidden="true"></i> <?php esc_html_e( 'Tout', 'schilo' ); ?>
		</a>
		<?php foreach ( $all_sections as $tab ) : $active = $current_axe === $tab['axe']; ?>
			<a class="schilo-axe-tab<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'axe', $tab['axe'], $base_url ) ); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
				<i class="ti <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></i> <?php echo esc_html( $tab['title'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php foreach ( $sections as $section ) :
		$terms = $schilo_top_terms( $section['taxonomy'] );
	?>
	<div class="schilo-card" style="margin-bottom:1.25rem">
		<div class="schilo-card__head">
			<div class="schilo-card__head-left">
				<div class="schilo-card__icon schilo-card__icon--dark"><i class="ti <?php echo esc_attr( $section['icon'] ); ?>"></i></div>
				<span class="schilo-card__title"><?php echo esc_html( $section['title'] ); ?></span>
			</div>
		</div>
		<div class="schilo-card__body">
			<p style="color:var(--schilo-text-secondary,#64748b);margin-top:0;"><?php echo esc_html( $section['description'] ); ?></p>

			<?php if ( empty( $terms ) ) : ?>
				<p style="color:var(--schilo-text-secondary,#64748b);"><?php esc_html_e( 'Aucun terme classé pour le moment.', 'schilo' ); ?></p>
			<?php else : ?>
				<div class="schilo-parcours-grid">
					<?php foreach ( $terms as $term ) :
						$children = get_term_children( $term->term_id, $section['taxonomy'] );
						$children = is_array( $children ) ? $children : [];
					?>
					<a href="<?php echo esc_url( get_term_link( $term, $section['taxonomy'] ) ); ?>" class="schilo-parcours-grid__item">
						<h3><?php echo esc_html( $term->name ); ?></h3>
						<?php if ( $term->description ) : ?>
							<p><?php echo esc_html( wp_trim_words( $term->description, 22, '…' ) ); ?></p>
						<?php endif; ?>
						<span class="schilo-parcours-grid__meta">
							<?php
							$meta_articles = sprintf(
								/* translators: %s: nombre d'articles */
								_n( '%s article', '%s articles', (int) $term->count, 'schilo' ),
								number_format_i18n( (int) $term->count )
							);
							$meta_etapes = $children
								? ' · ' . sprintf(
									/* translators: %s: nombre d'étapes (sous-termes) */
									_n( '%s étape', '%s étapes', count( $children ), 'schilo' ),
									number_format_i18n( count( $children ) )
								)
								: '';
							echo esc_html( $meta_articles . $meta_etapes );
							?>
						</span>
					</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>

</div>
</main>

<?php get_footer(); ?>
