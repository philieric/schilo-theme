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

$sections = [
	[
		'taxonomy'    => 'schilo_parcours',
		'title'       => __( 'Parcours', 'schilo' ),
		'icon'        => 'ti-route',
		'description' => __( 'Des suites de fiches à lire dans l’ordre, étape par étape.', 'schilo' ),
	],
	[
		'taxonomy'    => 'schilo_theme',
		'title'       => __( 'Thèmes', 'schilo' ),
		'icon'        => 'ti-category',
		'description' => __( 'Un classement thématique issu de l’indexation IA des articles.', 'schilo' ),
	],
	[
		'taxonomy'    => 'schilo_serie',
		'title'       => __( 'Séries', 'schilo' ),
		'icon'        => 'ti-stack-2',
		'description' => __( 'Des regroupements transverses d’articles autour d’un même fil.', 'schilo' ),
	],
];

get_header();
?>

<div class="schilo-hero">
	<div class="schilo-hero__inner">
		<div class="schilo-hero__eyebrow"><i class="ti ti-route"></i> <?php esc_html_e( 'Explorer', 'schilo' ); ?></div>
		<h1 class="schilo-hero__title schilo-serif"><?php esc_html_e( 'Parcours, thèmes & séries', 'schilo' ); ?></h1>
		<p class="schilo-hero__desc"><?php esc_html_e( 'Retrouvez les articles classés selon trois axes complémentaires : un parcours de lecture guidé, un thème d’étude, ou une série transverse.', 'schilo' ); ?></p>
	</div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">

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
							<p><?php echo esc_html( $term->description ); ?></p>
						<?php endif; ?>
						<span class="schilo-parcours-grid__meta">
							<?php
							printf(
								/* translators: 1: nombre d'articles, 2: nombre d'etapes/enfants */
								esc_html__( '%1$d article(s)%2$s', 'schilo' ),
								(int) $term->count,
								$children ? ' · ' . sprintf( esc_html__( '%d étape(s)', 'schilo' ), count( $children ) ) : ''
							);
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
