<?php
/**
 * Port en lecture seule de USX_Bible_Lookup (plugin Usx-import,
 * Service/class-usx-bible-lookup.php) — mêmes requêtes, mêmes tables
 * wp_usx_*, même schéma de retour. Ne crée ni ne modifie aucune table :
 * les données existent déjà (importées via le plugin). Permet au thème
 * de résoudre les références bibliques indépendamment de l'activation
 * du plugin — voir class-schilo-usx-integration.php.
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Bible_Lookup {

	/** Parse "Livre Chapitre.Verset1[-Verset2]" -> [bookToken, chapter, v1, v2] */
	public static function parse_reference( string $content ): ?array {
		$content = trim( strip_tags( $content ?? '' ) );
		if ( $content === '' ) {
			return null;
		}

		if ( ! preg_match( '#^\s*(.+?)\s+(\d+)\s*[:\.]\s*(\d+)(?:\s*-\s*(\d+))?\s*$#u', $content, $m ) ) {
			return null;
		}

		return [
			'bookToken'  => trim( $m[1] ),
			'chapter'    => (int) $m[2],
			'verseStart' => (int) $m[3],
			'verseEnd'   => isset( $m[4] ) ? (int) $m[4] : (int) $m[3],
		];
	}

	/** Version par défaut (fallback : plus ancien id) */
	public static function get_default_version() {
		global $wpdb;
		$t = $wpdb->prefix . 'usx_versions';
		return $wpdb->get_row( "SELECT * FROM $t ORDER BY is_default DESC, id ASC LIMIT 1" );
	}

	/**
	 * Récupère une version par code (ou name) si demandée via le contexte
	 * (ex. switcher AJAX). Retourne null si introuvable.
	 */
	public static function get_version_by_code( string $code ) {
		$code = trim( sanitize_text_field( $code ) );
		if ( $code === '' || $code === 'all' ) {
			return null;
		}

		global $wpdb;
		$t = $wpdb->prefix . 'usx_versions';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $t WHERE code = %s OR name = %s ORDER BY is_default DESC, id ASC LIMIT 1",
				$code,
				$code
			)
		);
	}

	/** Récupère le chapitre (ligne usx_chapters) */
	public static function get_chapter_row( int $book_id, int $chapter ) {
		global $wpdb;
		$t = $wpdb->prefix . 'usx_chapters';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $t WHERE book_id = %d AND number = %d LIMIT 1",
				$book_id,
				$chapter
			)
		);
	}

	/** Récupère la liste des versets (objets -> verse, verse_text) pour un intervalle */
	public static function get_verses( int $chapter_id, int $vStart, int $vEnd ) {
		global $wpdb;
		$t = $wpdb->prefix . 'usx_verses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT verse, verse_text FROM $t WHERE chapter_id = %d AND verse BETWEEN %d AND %d ORDER BY verse ASC",
				$chapter_id,
				$vStart,
				$vEnd
			)
		);
	}

	public static function get_version_copyright( int $version_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'usx_version_meta';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT copyright FROM $table WHERE version_id = %d LIMIT 1", $version_id )
		);
		return ( $row && ! empty( $row->copyright ) ) ? esc_html( $row->copyright ) : 'Copyright';
	}

	/**
	 * Résolution du livre : 1) titre exact (insensible casse/accents),
	 * 2) abréviations via les tables de référence globales, 3) fallback
	 * code direct (insensible à la casse).
	 *
	 * Note : la colonne `abreviation` (sans double 'b') est une coquille
	 * du schéma d'origine, conservée telle quelle pour rester compatible
	 * avec les tables existantes.
	 */
	public static function find_book_by_token( int $version_id, string $token ) {
		global $wpdb;
		$t_books = $wpdb->prefix . 'usx_books';

		$token_raw   = trim( $token );
		$token_code  = strtoupper( preg_replace( '#[^A-Z0-9]#i', '', $token_raw ) );
		$token_title = self::normalize_for_compare( $token_raw );

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, code, title, abreviation FROM $t_books WHERE version_id = %d", $version_id )
		);
		foreach ( $rows as $r ) {
			if ( self::normalize_for_compare( $r->title ) === $token_title ) {
				return $r;
			}
		}

		$by_abbr = self::find_book_by_abbr_ref( $version_id, $token_raw );
		if ( $by_abbr ) {
			return $by_abbr;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, code, title, abreviation FROM $t_books WHERE version_id = %d AND UPPER(code) = UPPER(%s) LIMIT 1",
				$version_id,
				$token_code
			)
		);
	}

	/** Recherche par abréviation via les tables de référence globales */
	public static function find_book_by_abbr_ref( int $version_id, string $token ) {
		global $wpdb;

		$t_ref   = $wpdb->prefix . 'usx_book_ref';
		$t_abbr  = $wpdb->prefix . 'usx_book_ref_abbr';
		$t_books = $wpdb->prefix . 'usx_books';

		$abbr_norm = self::abbr_norm( $token );
		if ( $abbr_norm === '' ) {
			return null;
		}

		$ref = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.canon_code AS code, r.canon_title AS title
				 FROM {$t_ref} AS r
				 WHERE EXISTS (
					SELECT 1 FROM {$t_abbr} AS a
					WHERE a.ref_id = r.id
					  AND (UPPER(a.abbr_norm) = UPPER(%s) OR UPPER(a.abbr) = UPPER(%s))
				 ) LIMIT 1",
				$abbr_norm,
				$abbr_norm
			)
		);
		if ( ! $ref ) {
			return null;
		}

		$by_code = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, code, title FROM $t_books WHERE version_id = %d AND UPPER(code) = UPPER(%s) LIMIT 1",
				$version_id,
				$ref->code
			)
		);
		if ( $by_code ) {
			return $by_code;
		}

		$rows        = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, code, title FROM $t_books WHERE version_id = %d", $version_id )
		);
		$target_norm = self::normalize_for_compare( $ref->title );
		foreach ( $rows as $r ) {
			if ( self::normalize_for_compare( $r->title ) === $target_norm ) {
				return $r;
			}
		}

		$by_user_code = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, code, title FROM $t_books WHERE version_id = %d AND UPPER(code) = UPPER(%s) LIMIT 1",
				$version_id,
				$abbr_norm
			)
		);
		return $by_user_code ?: null;
	}

	public static function normalize_for_compare( string $s ): string {
		$s = strtolower( $s );
		$s = function_exists( 'remove_accents' ) ? remove_accents( $s ) : @iconv( 'UTF-8', 'ASCII//TRANSLIT', $s );
		return trim( preg_replace( '#\s+#', ' ', $s ) );
	}

	public static function letters_only_noaccents( string $s ): string {
		$s = function_exists( 'remove_accents' ) ? remove_accents( $s ) : @iconv( 'UTF-8', 'ASCII//TRANSLIT', $s );
		return strtoupper( preg_replace( '#[^A-Za-z]#', '', $s ) );
	}

	public static function deaccent_uppercase_only( string $s ): string {
		$map = [
			'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A',
			'Ç' => 'C',
			'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
			'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
			'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
			'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
			'Ÿ' => 'Y',
			'Æ' => 'AE', 'Œ' => 'OE',
		];
		return strtr( $s, $map );
	}

	/** Normalisation d'abréviation utilisateur -> abbr_norm */
	public static function abbr_norm( string $s ): string {
		$s = function_exists( 'remove_accents' ) ? remove_accents( $s ) : @iconv( 'UTF-8', 'ASCII//TRANSLIT', $s );
		$s = preg_replace( '/[^A-Za-z0-9]/', '', $s );
		return strtoupper( $s );
	}

	/**
	 * Récupère toutes les données bibliques à partir d'un contenu comme
	 * "Luc 1.1-4". Retourne un tableau associatif avec : 'version', 'book',
	 * 'chapterRow', 'verses', 'reference', 'copyright', 'bookTitle',
	 * 'chapter', 'verseStart', 'verseEnd'.
	 * En cas d'erreur : ['error' => '<div class="usx-error">...</div>']
	 */
	public static function resolve_reference_data( string $content ): array {
		$content = trim( strip_tags( $content ?? '' ) );
		$parsed  = self::parse_reference( $content ?? '' );
		if ( ! $parsed ) {
			return [ 'error' => '<div class="usx-error">Format attendu : Livre Chapitre.Verset ou Livre Chapitre.Verset1-Verset2 (ex. « Ge 4.1-10 »).</div>' ];
		}

		$book_token  = $parsed['bookToken'];
		$chapter     = (int) $parsed['chapter'];
		$verse_start = (int) $parsed['verseStart'];
		$verse_end   = (int) $parsed['verseEnd'];

		$forced_code = isset( $_GET['usx_version'] ) ? (string) $_GET['usx_version'] : '';
		$version     = self::get_version_by_code( $forced_code );
		if ( ! $version ) {
			$version = self::get_default_version();
		}
		if ( ! $version ) {
			return [ 'error' => '<div class="usx-error">Aucune version biblique disponible.</div>' ];
		}

		$book = self::find_book_by_token( (int) $version->id, $book_token );
		if ( ! $book ) {
			return [ 'error' => '<div class="usx-error">Livre non reconnu : ' . esc_html( $book_token ) . '</div>' ];
		}

		$chapter_row = self::get_chapter_row( (int) $book->id, $chapter );
		if ( ! $chapter_row ) {
			return [ 'error' => '<div class="usx-error">Chapitre introuvable : ' . intval( $chapter ) . '</div>' ];
		}

		$verses = self::get_verses( (int) $chapter_row->id, $verse_start, $verse_end );
		if ( ! $verses ) {
			return [ 'error' => '<div class="usx-error">Verset(s) introuvable(s) : ' . intval( $verse_start ) . ( $verse_end > $verse_start ? '-' . intval( $verse_end ) : '' ) . '</div>' ];
		}

		$book_title = self::deaccent_uppercase_only( $book->title ?: $book->code );
		$reference  = sprintf(
			'%s %d.%d%s – %s',
			$book_title,
			$chapter,
			$verse_start,
			( $verse_end > $verse_start ? '-' . $verse_end : '' ),
			$version->name
		);
		$copyright_text = self::get_version_copyright( (int) $version->id );

		return [
			'version'    => $version,
			'book'       => $book,
			'chapterRow' => $chapter_row,
			'verses'     => $verses,
			'reference'  => $reference,
			'copyright'  => $copyright_text,
			'bookTitle'  => $book_title,
			'chapter'    => $chapter,
			'verseStart' => $verse_start,
			'verseEnd'   => $verse_end,
		];
	}
}
