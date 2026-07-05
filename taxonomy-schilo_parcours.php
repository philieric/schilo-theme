<?php
defined( 'ABSPATH' ) || exit;

$term     = get_queried_object();
$taxonomy = 'schilo_parcours';

if ( ! $term instanceof WP_Term ) {
	get_header();
	echo '<main id="schilo-main" role="main"><div class="schilo-container" style="padding:3rem 0;">';
	echo '<p>' . esc_html__( 'Parcours introuvable.', 'schilo' ) . '</p>';
	echo '</div></main>';
	get_footer();
	return;
}

$render_ordered_posts = function ( int $term_id ) use ( $taxonomy ): void {
	$query = new WP_Query( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id ] ],
		'meta_key'       => '_schilo_ordre_' . $term_id,
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
	] );

	if ( ! $query->have_posts() ) {
		echo '<p style="color:var(--schilo-text-secondary,#64748b);">' . esc_html__( 'Aucun article classé ici pour le moment.', 'schilo' ) . '</p>';
		return;
	}

	echo '<ol class="schilo-parcours-articles">';
	while ( $query->have_posts() ) {
		$query->the_post();
		echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
	}
	echo '</ol>';
	wp_reset_postdata();
};

$children = get_term_children( $term->term_id, $taxonomy );
$children = is_array( $children ) ? $children : [];
$parent   = $term->parent ? get_term( $term->parent, $taxonomy ) : null;

get_header();
?>

<div class="schilo-hero">
	<div class="schilo-hero__inner">
		<div class="schilo-hero__eyebrow"><i class="ti ti-route"></i> <?php esc_html_e( 'Parcours', 'schilo' ); ?></div>
		<h1 class="schilo-hero__title schilo-serif"><?php echo esc_html( $term->name ); ?></h1>
		<?php if ( $term->description ) : ?>
			<p class="schilo-hero__desc"><?php echo esc_html( $term->description ); ?></p>
		<?php endif; ?>
	</div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">

	<?php if ( $parent && ! is_wp_error( $parent ) ) : ?>
		<p><a href="<?php echo esc_url( get_term_link( $parent, $taxonomy ) ); ?>">&larr; <?php echo esc_html( $parent->name ); ?></a></p>
	<?php endif; ?>

	<?php if ( ! empty( $children ) ) : ?>
		<?php foreach ( $children as $child_id ) :
			$child = get_term( $child_id, $taxonomy );
			if ( is_wp_error( $child ) || ! $child ) continue;
		?>
		<div class="schilo-card" style="margin-bottom:1.25rem">
			<div class="schilo-card__head">
				<div class="schilo-card__head-left">
					<div class="schilo-card__icon schilo-card__icon--dark"><i class="ti ti-flag"></i></div>
					<span class="schilo-card__title"><?php echo esc_html( $child->name ); ?></span>
				</div>
			</div>
			<div class="schilo-card__body">
				<?php $render_ordered_posts( (int) $child->term_id ); ?>
			</div>
		</div>
		<?php endforeach; ?>
	<?php else : ?>
		<div class="schilo-card" style="margin-bottom:1.25rem">
			<div class="schilo-card__body">
				<?php $render_ordered_posts( (int) $term->term_id ); ?>
			</div>
		</div>
	<?php endif; ?>

</div>
</main>

<?php get_footer(); ?>
