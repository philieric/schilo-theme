<?php
defined( 'ABSPATH' ) || exit;

/**
 * Page d'index virtuelle pour un personnage / lieu / mot-clé / référence
 * biblique (texte libre indexé par IA, pas une taxonomie WP) : liste tous
 * les articles indexés dont le champ correspondant contient exactement
 * cette valeur. Routée via ClassementService::registerIndexRewrites()
 * + le filtre template_include (Plugin.php), pas par la hiérarchie de
 * templates WordPress classique (il n'y a ni post ni terme réel ici).
 */

require_once SCHILO_DIR . '/template-parts/classement-shared.php';

$field = (string) get_query_var( 'schilo_index_field' );
$value = rawurldecode( (string) get_query_var( 'schilo_index_value' ) );

if ( ! isset( \Schilo\Builder\Service\ClassementService::INDEX_FIELDS[ $field ] ) || $value === '' ) {
	get_header();
	echo '<main id="schilo-main" role="main"><div class="schilo-container" style="padding:3rem 0;">';
	echo '<p>' . esc_html__( 'Page introuvable.', 'schilo' ) . '</p>';
	echo '</div></main>';
	get_footer();
	return;
}

$meta     = \Schilo\Builder\Service\ClassementService::INDEX_FIELDS[ $field ];
$service  = new \Schilo\Builder\Service\ClassementService();
$post_ids = $service->getPostIdsByIndexedValue( $field, $value );

if ( ! empty( $post_ids ) ) {
	$query = new WP_Query( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post__in'       => $post_ids,
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );
	$post_ids = $query->posts;
}

$aggregate = schilo_classement_aggregate_indexation( $post_ids );

get_header();
?>

<div class="schilo-hero">
	<div class="schilo-hero__inner">
		<div class="schilo-hero__eyebrow"><i class="ti <?php echo esc_attr( $meta['icon'] ); ?>"></i> <?php echo esc_html( $meta['label'] ); ?></div>
		<h1 class="schilo-hero__title schilo-serif"><?php echo esc_html( $value ); ?></h1>
		<p class="schilo-hero__desc">
			<?php
			printf(
				/* translators: %s: le personnage, lieu, mot-clé ou référence biblique recherché */
				esc_html__( 'Tous les articles indexés associés à « %s ».', 'schilo' ),
				esc_html( $value )
			);
			?>
		</p>
	</div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container schilo-parcours-layout">

	<div class="schilo-parcours-main">
		<div class="schilo-card" style="margin-bottom:1.25rem">
			<div class="schilo-card__body">
				<?php if ( empty( $post_ids ) ) : ?>
					<p style="color:var(--schilo-text-secondary,#64748b);"><?php esc_html_e( 'Aucun article indexé pour le moment.', 'schilo' ); ?></p>
				<?php else : ?>
					<ul class="schilo-parcours-articles schilo-parcours-articles--unordered">
						<?php foreach ( $post_ids as $post_id ) : ?>
							<?php schilo_classement_render_article_item( (int) $post_id ); ?>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php schilo_classement_render_sidebar( $aggregate, count( $post_ids ) ); ?>

</div>
</main>

<?php get_footer(); ?>
