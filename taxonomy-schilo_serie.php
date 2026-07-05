<?php
defined( 'ABSPATH' ) || exit;

$term     = get_queried_object();
$taxonomy = 'schilo_serie';

if ( ! $term instanceof WP_Term ) {
	get_header();
	echo '<main id="schilo-main" role="main"><div class="schilo-container" style="padding:3rem 0;">';
	echo '<p>' . esc_html__( 'Série introuvable.', 'schilo' ) . '</p>';
	echo '</div></main>';
	get_footer();
	return;
}

$query = new WP_Query( [
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ] ],
	'meta_key'       => '_schilo_ordre_' . $term->term_id,
	'orderby'        => 'meta_value_num',
	'order'          => 'ASC',
] );

get_header();
?>

<div class="schilo-hero">
	<div class="schilo-hero__inner">
		<div class="schilo-hero__eyebrow"><i class="ti ti-stack-2"></i> <?php esc_html_e( 'Série', 'schilo' ); ?></div>
		<h1 class="schilo-hero__title schilo-serif"><?php echo esc_html( $term->name ); ?></h1>
		<?php if ( $term->description ) : ?>
			<p class="schilo-hero__desc"><?php echo esc_html( $term->description ); ?></p>
		<?php endif; ?>
	</div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">
	<div class="schilo-card" style="margin-bottom:1.25rem">
		<div class="schilo-card__body">
			<?php if ( ! $query->have_posts() ) : ?>
				<p style="color:var(--schilo-text-secondary,#64748b);"><?php esc_html_e( 'Aucun article classé ici pour le moment.', 'schilo' ); ?></p>
			<?php else : ?>
				<ol class="schilo-parcours-articles">
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<li><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></li>
					<?php endwhile; wp_reset_postdata(); ?>
				</ol>
			<?php endif; ?>
		</div>
	</div>
</div>
</main>

<?php get_footer(); ?>
