<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aide partagee par les templates taxonomy-schilo_parcours.php,
 * taxonomy-schilo_theme.php et taxonomy-schilo_serie.php : exploite
 * les donnees deja presentes dans la table d'indexation (resume_court,
 * temps de lecture, personnages, lieux, mots-cles...) pour enrichir
 * l'affichage des articles classes.
 */

if ( ! function_exists( 'schilo_classement_aggregate_indexation' ) ) :

/**
 * Agrege les infos d'indexation (temps de lecture, personnages, lieux,
 * mots-cles, references bibliques) sur un ensemble d'articles, triees
 * par frequence decroissante.
 */
function schilo_classement_aggregate_indexation( array $post_ids ): array {
	$service = new \Schilo\Builder\Service\IndexationService();

	$agg = [
		'temps_lecture_min'    => 0,
		'personnages'          => [],
		'lieux'                => [],
		'mots_cles'            => [],
		'references_bibliques' => [],
	];

	foreach ( $post_ids as $post_id ) {
		$row = $service->getByPostId( (int) $post_id );
		if ( ! $row ) continue;

		$agg['temps_lecture_min'] += (int) ( $row['temps_lecture_min'] ?? 0 );

		foreach ( [ 'personnages', 'lieux', 'mots_cles', 'references_bibliques' ] as $field ) {
			$decoded = json_decode( $row[ $field ] ?? '[]', true );
			if ( ! is_array( $decoded ) ) continue;
			foreach ( $decoded as $val ) {
				$val = trim( (string) $val );
				if ( $val === '' ) continue;
				$agg[ $field ][ $val ] = ( $agg[ $field ][ $val ] ?? 0 ) + 1;
			}
		}
	}

	foreach ( [ 'personnages', 'lieux', 'mots_cles', 'references_bibliques' ] as $field ) {
		arsort( $agg[ $field ] );
		$agg[ $field ] = array_keys( $agg[ $field ] );
	}

	return $agg;
}

/**
 * Rendu de la sidebar "En bref" a partir des donnees agregees ci-dessus.
 */
function schilo_classement_render_sidebar( array $agg, int $article_count ): void {
	?>
	<aside class="schilo-parcours-sidebar" aria-label="Informations complémentaires">
		<div class="schilo-sidebar-card">
			<div class="schilo-sidebar-card__title">En bref</div>
			<ul class="schilo-sidebar-stats" role="list">
				<li><i class="ti ti-files" aria-hidden="true"></i> <span><?php echo (int) $article_count; ?> article<?php echo $article_count > 1 ? 's' : ''; ?></span></li>
				<?php if ( $agg['temps_lecture_min'] > 0 ) : ?>
				<li><i class="ti ti-clock" aria-hidden="true"></i> <span><strong><?php echo (int) $agg['temps_lecture_min']; ?></strong> min de lecture au total</span></li>
				<?php endif; ?>
			</ul>
		</div>

		<?php if ( ! empty( $agg['personnages'] ) ) : ?>
		<div class="schilo-sidebar-card">
			<div class="schilo-sidebar-card__title">Personnages</div>
			<div class="schilo-sidebar-themes">
				<?php foreach ( array_slice( $agg['personnages'], 0, 10 ) as $name ) : ?>
					<span class="schilo-sidebar-theme-tag"><?php echo esc_html( $name ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $agg['lieux'] ) ) : ?>
		<div class="schilo-sidebar-card">
			<div class="schilo-sidebar-card__title">Lieux</div>
			<div class="schilo-sidebar-themes">
				<?php foreach ( array_slice( $agg['lieux'], 0, 10 ) as $name ) : ?>
					<span class="schilo-sidebar-theme-tag"><?php echo esc_html( $name ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $agg['mots_cles'] ) ) : ?>
		<div class="schilo-sidebar-card">
			<div class="schilo-sidebar-card__title">Mots-clés</div>
			<div class="schilo-sidebar-themes">
				<?php foreach ( array_slice( $agg['mots_cles'], 0, 12 ) as $name ) : ?>
					<span class="schilo-sidebar-theme-tag"><?php echo esc_html( $name ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $agg['references_bibliques'] ) ) : ?>
		<div class="schilo-sidebar-card">
			<div class="schilo-sidebar-card__title">Références bibliques</div>
			<div class="schilo-sidebar-themes">
				<?php foreach ( array_slice( $agg['references_bibliques'], 0, 10 ) as $ref ) : ?>
					<span class="schilo-sidebar-theme-tag"><?php echo esc_html( $ref ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</aside>
	<?php
}

/**
 * Rendu enrichi d'un article dans une liste (titre + resume court + meta),
 * a partir de sa fiche d'indexation.
 */
function schilo_classement_render_article_item( int $post_id ): void {
	$service = new \Schilo\Builder\Service\IndexationService();
	$row     = $service->getByPostId( $post_id );

	$resume = $row['resume_court'] ?? '';
	$temps  = (int) ( $row['temps_lecture_min'] ?? 0 );
	$niveau = $row['niveau_lecture'] ?? '';
	?>
	<li class="schilo-parcours-article">
		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="schilo-parcours-article__title">
			<?php echo esc_html( get_the_title( $post_id ) ); ?>
		</a>
		<?php if ( $resume ) : ?>
			<p class="schilo-parcours-article__excerpt"><?php echo esc_html( wp_trim_words( $resume, 28 ) ); ?></p>
		<?php endif; ?>
		<?php if ( $temps || $niveau ) : ?>
			<p class="schilo-parcours-article__meta">
				<?php if ( $temps ) : ?><span><i class="ti ti-clock" aria-hidden="true"></i> <?php echo (int) $temps; ?> min</span><?php endif; ?>
				<?php if ( $niveau ) : ?><span><i class="ti ti-signal-3" aria-hidden="true"></i> <?php echo esc_html( $niveau ); ?></span><?php endif; ?>
			</p>
		<?php endif; ?>
	</li>
	<?php
}

endif;
