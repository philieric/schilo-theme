<?php
/**
 * Texte alternatif automatique des images.
 *
 * L'attribut « alt » n'a jamais été renseigné dans la médiathèque : plutôt que
 * de laisser toutes les images de contenu en alt="" (muettes pour les lecteurs
 * d'écran et les plages braille), on dérive un alt lisible à partir des
 * métadonnées réelles de la pièce jointe — légende, puis titre débarrassé du
 * préfixe de référence interne (« ADA033 Simon le magicien » → « Simon le
 * magicien », « PER001 - Titre » → « Titre »).
 *
 * Un alt réellement saisi en médiathèque reste prioritaire ; les titres qui ne
 * sont qu'un nom de fichier (IMG_1234, capture-2…) sont écartés et l'image est
 * alors traitée comme décorative (alt="").
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Image_Alt {

	public static function init(): void {
		add_filter( 'wp_get_attachment_image_attributes', [ __CLASS__, 'fill_alt' ], 10, 2 );
	}

	/**
	 * Complète l'attribut alt lorsqu'il est vide.
	 *
	 * @param array   $attr       Attributs de la balise <img>.
	 * @param WP_Post $attachment Pièce jointe.
	 * @return array
	 */
	public static function fill_alt( $attr, $attachment ): array {
		// Un alt déjà saisi en médiathèque est prioritaire — on n'y touche pas.
		if ( trim( (string) ( $attr['alt'] ?? '' ) ) !== '' ) {
			return $attr;
		}
		if ( ! $attachment instanceof WP_Post ) {
			return $attr;
		}

		$candidate = self::derive_alt( $attachment );
		if ( $candidate !== '' ) {
			$attr['alt'] = $candidate;
		}
		return $attr;
	}

	/** Dérive un alt lisible depuis la légende puis le titre de la pièce jointe. */
	public static function derive_alt( WP_Post $attachment ): string {
		// 1. Légende (post_excerpt) : la source la plus intentionnelle.
		$caption = trim( wp_strip_all_tags( (string) $attachment->post_excerpt ) );
		if ( $caption !== '' ) {
			return $caption;
		}

		// 2. Titre (post_title), débarrassé du préfixe de code interne.
		$title = self::strip_ref_prefix( trim( (string) $attachment->post_title ) );

		// On écarte les titres qui ne sont qu'un nom de fichier : ils ne
		// décrivent pas l'image → on la laisse décorative (alt="").
		if ( self::looks_like_filename( $title ) ) {
			return '';
		}
		return $title;
	}

	/** Retire « ADA033 », « PER001 - », « ANN128 – »… en tête de titre. */
	private static function strip_ref_prefix( string $title ): string {
		return trim( preg_replace( '/^[A-Z]{2,5}\d{2,4}\s*[-\x{2013}\x{2014}]?\s*/u', '', $title ) );
	}

	/** Vrai si la chaîne ressemble à un nom de fichier plutôt qu'à une description. */
	private static function looks_like_filename( string $s ): bool {
		if ( $s === '' ) {
			return true;
		}
		if ( preg_match( '/^(img|dsc|dscn|image|photo|screenshot|capture|scan|p)[-_ ]?\d+$/i', $s ) ) {
			return true;
		}
		if ( preg_match( '/\.(jpe?g|png|gif|webp|bmp|tiff?)$/i', $s ) ) {
			return true;
		}
		return false;
	}
}
