<?php
defined( 'ABSPATH' ) || exit;

require_once SCHILO_DIR . '/template-parts/classement-shared.php';

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

$get_ordered_post_ids = function ( int $term_id ) use ( $taxonomy ): array {
	$query = new WP_Query( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id ] ],
		'meta_key'       => '_schilo_ordre_' . $term_id,
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
	] );
	return $query->posts;
};

$render_ordered_posts = function ( int $term_id ) use ( $get_ordered_post_ids ): void {
	$post_ids = $get_ordered_post_ids( $term_id );

	if ( empty( $post_ids ) ) {
		echo '<p style="color:var(--schilo-text-secondary,#64748b);">' . esc_html__( 'Aucun article classé ici pour le moment.', 'schilo' ) . '</p>';
		return;
	}

	// Les articles "complement" (ex: une Annexe, voir Parcours & Themes >
	// Configuration) ne doivent pas apparaitre comme une etape numerotee au
	// meme titre qu'un article "principal" : ils sont rattaches sous
	// l'article qui les reference, ou regroupes en fin de page sinon.
	$grouped = schilo_classement_group_articles_with_complements( $post_ids );

	echo '<ol class="schilo-parcours-articles">';
	foreach ( $grouped['groups'] as $group ) {
		schilo_classement_render_article_item( (int) $group['principal'] );
		if ( ! empty( $group['complements'] ) ) : ?>
			<li class="schilo-parcours-complements-block">
				<div class="schilo-parcours-complements-label"><i class="ti ti-paperclip" aria-hidden="true"></i> <?php esc_html_e( 'En complément', 'schilo' ); ?></div>
				<ul class="schilo-parcours-complements">
					<?php foreach ( $group['complements'] as $cid ) : schilo_classement_render_complement_item( (int) $cid ); endforeach; ?>
				</ul>
			</li>
		<?php endif;
	}
	echo '</ol>';

	if ( ! empty( $grouped['orphans'] ) ) : ?>
		<div class="schilo-parcours-complements-block schilo-parcours-complements-block--orphans">
			<div class="schilo-parcours-complements-label"><i class="ti ti-paperclip" aria-hidden="true"></i> <?php esc_html_e( 'Compléments', 'schilo' ); ?></div>
			<ul class="schilo-parcours-complements">
				<?php foreach ( $grouped['orphans'] as $cid ) : schilo_classement_render_complement_item( (int) $cid ); endforeach; ?>
			</ul>
		</div>
	<?php endif;
};

$children = get_term_children( $term->term_id, $taxonomy );
$children = is_array( $children ) ? $children : [];
$parent   = $term->parent ? get_term( $term->parent, $taxonomy ) : null;

// Articles de tout le parcours (terme + etapes enfants) pour la sidebar "En bref".
$all_post_ids = $get_ordered_post_ids( $term->term_id );
foreach ( $children as $child_id ) {
	$all_post_ids = array_merge( $all_post_ids, $get_ordered_post_ids( (int) $child_id ) );
}
$all_post_ids = array_unique( $all_post_ids );
$aggregate    = schilo_classement_aggregate_indexation( $all_post_ids );

get_header();
?>

<div class="schilo-hero">
	<div class="schilo-hero__inner">
		<div class="schilo-hero__eyebrow"><i class="ti ti-route"></i> <?php esc_html_e( 'Parcours', 'schilo' ); ?></div>
		<h1 class="schilo-hero__title schilo-serif"><?php echo esc_html( $term->name ); ?></h1>
		<?php if ( $term->description ) : ?>
			<?php schilo_classement_render_term_description( $term->description, 'schilo-hero__desc' ); ?>
		<?php endif; ?>
	</div>
</div>

<?php if ( ! empty( $children ) ) : ?>
<nav class="schilo-parcours-tabnav" id="schilo-parcours-tabnav" aria-label="Étapes du parcours">
	<div class="schilo-container schilo-parcours-tabnav__inner">
		<ul class="schilo-tabnav-list" role="list">
			<?php foreach ( $children as $child_id ) :
				$child = get_term( $child_id, $taxonomy );
				if ( is_wp_error( $child ) || ! $child ) continue;
			?>
			<li>
				<a class="schilo-tabnav-link" href="#sec-<?php echo esc_attr( $child->term_id ); ?>" data-anchor="sec-<?php echo esc_attr( $child->term_id ); ?>">
					<i class="ti ti-flag" aria-hidden="true"></i> <?php echo esc_html( $child->name ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</nav>
<?php endif; ?>

<main id="schilo-main" role="main">
<div class="schilo-container schilo-parcours-layout">

	<div class="schilo-parcours-main">
		<?php if ( $parent && ! is_wp_error( $parent ) ) : ?>
			<p><a href="<?php echo esc_url( get_term_link( $parent, $taxonomy ) ); ?>">&larr; <?php echo esc_html( $parent->name ); ?></a></p>
		<?php endif; ?>

		<?php if ( ! empty( $children ) ) : ?>
			<?php foreach ( $children as $child_id ) :
				$child = get_term( $child_id, $taxonomy );
				if ( is_wp_error( $child ) || ! $child ) continue;
			?>
			<div class="schilo-card" id="sec-<?php echo esc_attr( $child->term_id ); ?>" style="margin-bottom:1.25rem">
				<div class="schilo-card__head">
					<div class="schilo-card__head-left">
						<div class="schilo-card__icon schilo-card__icon--dark"><i class="ti ti-flag"></i></div>
						<span class="schilo-card__title"><?php echo esc_html( $child->name ); ?></span>
					</div>
				</div>
				<div class="schilo-card__body">
					<?php if ( $child->description ) : ?>
						<?php schilo_classement_render_term_description( $child->description, 'schilo-card__desc' ); ?>
					<?php endif; ?>
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

	<?php schilo_classement_render_sidebar( $aggregate, count( $all_post_ids ) ); ?>

</div>
</main>

<?php get_footer(); ?>
