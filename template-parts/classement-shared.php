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
 * Choisit une reference biblique au hasard parmi celles indexees pour un
 * article et rend la carte de verset via le shortcode [brc] (Usx-import),
 * qui applique le code couleur par evangile (citation-matthieu/marc/luc/
 * jean, mauve pour le reste de la Bible). Essaie plusieurs references si
 * la premiere n'est pas reconnue par le shortcode (format libre issu de
 * l'IA), sans jamais afficher d'erreur publiquement.
 */
function schilo_classement_pick_bible_verse_html( array $references ): string {
	if ( empty( $references ) || ! shortcode_exists( 'brc' ) ) return '';

	$refs = $references;
	shuffle( $refs );

	foreach ( array_slice( $refs, 0, 4 ) as $ref ) {
		$ref = trim( str_replace( ',', '.', (string) $ref ) );
		if ( $ref === '' ) continue;

		$html = do_shortcode( '[brc]' . $ref . '[/brc]' );
		if ( $html && strpos( $html, 'usx-error' ) === false ) {
			return $html;
		}
	}

	return '';
}

/**
 * Rendu enrichi d'un article dans une liste : verset biblique au hasard,
 * resume court, lien "En savoir plus", puis meta (niveau/public/temps).
 * Le titre reste present mais en retrait (repere visuel discret), a
 * partir de la fiche d'indexation de l'article.
 */
function schilo_classement_render_article_item( int $post_id ): void {
	$service = new \Schilo\Builder\Service\IndexationService();
	$row     = $service->getByPostId( $post_id );

	$resume       = $row['resume_court'] ?? '';
	$temps        = (int) ( $row['temps_lecture_min'] ?? 0 );
	$niveau       = $row['niveau_lecture'] ?? '';
	$public_cible = $row['public_cible'] ?? '';

	$references = json_decode( $row['references_bibliques'] ?? '[]', true );
	$verse_html = is_array( $references ) ? schilo_classement_pick_bible_verse_html( $references ) : '';
	?>
	<li class="schilo-parcours-article">
		<div class="schilo-parcours-article__eyebrow">
			<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
		</div>

		<?php if ( $resume ) : ?>
			<p class="schilo-parcours-article__excerpt"><?php echo esc_html( $resume ); ?></p>
		<?php endif; ?>

		<?php if ( $verse_html ) : ?>
			<div class="schilo-parcours-article__verse"><?php echo $verse_html; ?></div>
		<?php endif; ?>

		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="schilo-parcours-article__more">
			<?php esc_html_e( 'En savoir plus', 'schilo' ); ?> <i class="ti ti-arrow-right" aria-hidden="true"></i>
		</a>

		<?php if ( $temps || $niveau || $public_cible ) : ?>
			<p class="schilo-parcours-article__meta">
				<?php if ( $temps ) : ?><span><i class="ti ti-clock" aria-hidden="true"></i> <?php echo (int) $temps; ?> min</span><?php endif; ?>
				<?php if ( $niveau ) : ?><span><i class="ti ti-signal-3" aria-hidden="true"></i> Niveau : <?php echo esc_html( $niveau ); ?></span><?php endif; ?>
				<?php if ( $public_cible ) : ?><span><i class="ti ti-users" aria-hidden="true"></i> Public : <?php echo esc_html( $public_cible ); ?></span><?php endif; ?>
			</p>
		<?php endif; ?>
	</li>
	<?php
}

endif;
