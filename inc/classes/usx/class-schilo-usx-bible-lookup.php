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

	/**
	 * Séparateur d'intervalle : le trait d'union « - » ET les tirets/traits
	 * typographiques fréquents dans le contenu issu de la migration — U+2010
	 * (trait d'union), U+2011 (insécable, « Luc 2.21‑24 »), U+2012 (figure),
	 * U+2013 (en-dash, « Marc 13.1–4 »), U+2014 (em-dash), U+2015 (barre),
	 * U+2212 (signe moins).
	 */
	const DASH_CLASS = '[-\x{2010}-\x{2015}\x{2212}]';

	/** Nombre maximal de chapitres entiers affichables d'un coup (« Livre 6-19 »). */
	const MAX_CHAPTER_RANGE = 25;

	/**
	 * Parse une référence biblique -> [bookToken, chapter, verseStart, verseEnd, …].
	 * Le verset est facultatif : "Livre Chapitre" (ex. « Daniel 1 ») cible le
	 * chapitre entier et renvoie verseStart/verseEnd = null (le chapitre complet
	 * est alors résolu par resolve_reference_data()).
	 *
	 * Formes acceptées (clés supplémentaires du retour entre parenthèses) :
	 *  - « Luc 1.5 », « Luc 1.1-4 »                     (segments = [[1,4]])
	 *  - « Joël 1.7,12 », « Marc 11.12-14,20-25 »       (segments = plusieurs
	 *    versets ou intervalles du MÊME chapitre, séparés par des virgules)
	 *  - « Daniel 1 »                                   (chapitre entier)
	 *  - « Matthieu 21-23 »                             (chapterEnd = 23 :
	 *    intervalle de chapitres entiers)
	 * verseStart/verseEnd restent renseignés (début du premier segment / fin
	 * du dernier) pour les appelants qui ne connaissent qu'un intervalle.
	 */
	public static function parse_reference( string $content ): ?array {
		$content = trim( strip_tags( $content ?? '' ) );
		if ( $content === '' ) {
			return null;
		}

		$dash    = self::DASH_CLASS;
		$segment = '\d+(?:\s*' . $dash . '\s*\d+)?';

		// 1) Livre Chapitre.Versets — un ou plusieurs segments séparés par des virgules.
		if ( preg_match( '#^\s*(.+?)\s+(\d+)\s*[:\.]\s*(' . $segment . '(?:\s*,\s*' . $segment . ')*)\s*$#u', $content, $m ) ) {
			$segments = [];
			foreach ( preg_split( '#\s*,\s*#u', trim( $m[3] ) ) as $part ) {
				$bounds = preg_split( '#\s*' . $dash . '\s*#u', $part );
				$start  = (int) $bounds[0];
				$end    = ( isset( $bounds[1] ) && $bounds[1] !== '' ) ? (int) $bounds[1] : $start;
				$segments[] = [ $start, max( $start, $end ) ];
			}

			return [
				'bookToken'  => trim( $m[1] ),
				'chapter'    => (int) $m[2],
				'chapterEnd' => null,
				'verseStart' => $segments[0][0],
				'verseEnd'   => $segments[ count( $segments ) - 1 ][1],
				'segments'   => $segments,
			];
		}

		// 2) Livre Chapitre[-ChapitreFin] — chapitre(s) entier(s).
		if ( preg_match( '#^\s*(.+?)\s+(\d+)(?:\s*' . $dash . '\s*(\d+))?\s*$#u', $content, $m ) ) {
			$chapter     = (int) $m[2];
			$chapter_end = ( isset( $m[3] ) && $m[3] !== '' ) ? (int) $m[3] : null;

			return [
				'bookToken'  => trim( $m[1] ),
				'chapter'    => $chapter,
				'chapterEnd' => ( $chapter_end !== null && $chapter_end > $chapter ) ? $chapter_end : null,
				'verseStart' => null,
				'verseEnd'   => null,
				'segments'   => [],
			];
		}

		return null;
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

	/** Récupère tous les versets d'un chapitre (objets -> id, verse, verse_text) */
	public static function get_chapter_verses( int $chapter_id ) {
		global $wpdb;
		$t = $wpdb->prefix . 'usx_verses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, verse, verse_text FROM $t WHERE chapter_id = %d ORDER BY verse ASC",
				$chapter_id
			)
		);
	}

	/** Récupère la liste des versets (objets -> id, verse, verse_text) pour un intervalle */
	public static function get_verses( int $chapter_id, int $vStart, int $vEnd ) {
		global $wpdb;
		$t = $wpdb->prefix . 'usx_verses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, verse, verse_text FROM $t WHERE chapter_id = %d AND verse BETWEEN %d AND %d ORDER BY verse ASC",
				$chapter_id,
				$vStart,
				$vEnd
			)
		);
	}

	/**
	 * Récupère, en une seule requête groupée, les notes (wp_usx_notes) liées
	 * à un ensemble de versets — appelé après get_verses() avec les `id` de
	 * versets obtenus, pour éviter une requête par verset.
	 *
	 * @param int[] $verse_ids
	 * @return array<int, object[]> Notes indexées par verse_id, dans l'ordre d'import.
	 */
	public static function get_notes_for_verse_ids( array $verse_ids ): array {
		$verse_ids = array_values( array_unique( array_map( 'intval', $verse_ids ) ) );
		if ( empty( $verse_ids ) ) {
			return [];
		}

		global $wpdb;
		$t            = $wpdb->prefix . 'usx_notes';
		$placeholders = implode( ',', array_fill( 0, count( $verse_ids ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, verse_id, note_type, caller, content FROM $t WHERE verse_id IN ($placeholders) ORDER BY verse_id ASC, id ASC",
				$verse_ids
			)
		);

		$by_verse = [];
		foreach ( $rows as $row ) {
			$by_verse[ (int) $row->verse_id ][] = $row;
		}

		return $by_verse;
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
	 * 'chapterRow' (premier chapitre), 'verses' (chaque verset porte ->chapter),
	 * 'reference', 'copyright', 'bookTitle', 'chapter', 'verseStart',
	 * 'verseEnd', 'wholeChapter', 'chapterEnd', 'multiChapter', 'segments' et
	 * 'refSpec' (la partie « 1.7,12 » / « 21-23 » normalisée, pour les libellés).
	 * En cas d'erreur : ['error' => '<div class="usx-error">...</div>']
	 */
	public static function resolve_reference_data( string $content ): array {
		$content = trim( strip_tags( $content ?? '' ) );
		$parsed  = self::parse_reference( $content ?? '' );
		if ( ! $parsed ) {
			return [ 'error' => '<div class="usx-error">Format attendu : Livre Chapitre, Livre Chapitre.Verset, Livre Chapitre.Verset1-Verset2 ou une liste « 7,12 » (ex. « Ge 4.1-10 »).</div>' ];
		}

		$book_token    = $parsed['bookToken'];
		$chapter       = (int) $parsed['chapter'];
		$chapter_end   = $parsed['chapterEnd'] !== null ? (int) $parsed['chapterEnd'] : null;
		$segments      = $parsed['segments'];
		$whole_chapter = empty( $segments );
		$verse_start   = (int) $parsed['verseStart'];
		$verse_end     = (int) $parsed['verseEnd'];

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

		// Chaque verset porte son numéro de chapitre ($v->chapter) : nécessaire à
		// l'affichage dès que la référence couvre plusieurs chapitres.
		$verses = [];

		if ( $whole_chapter ) {
			$last_chapter = $chapter_end ?? $chapter;
			if ( $last_chapter - $chapter + 1 > self::MAX_CHAPTER_RANGE ) {
				return [ 'error' => '<div class="usx-error">Intervalle de chapitres trop large : ' . intval( $chapter ) . '-' . intval( $last_chapter ) . ' (maximum ' . self::MAX_CHAPTER_RANGE . ' chapitres).</div>' ];
			}

			for ( $c = $chapter; $c <= $last_chapter; $c++ ) {
				$row = ( $c === $chapter ) ? $chapter_row : self::get_chapter_row( (int) $book->id, $c );
				if ( ! $row ) {
					return [ 'error' => '<div class="usx-error">Chapitre introuvable : ' . intval( $c ) . '</div>' ];
				}
				foreach ( (array) self::get_chapter_verses( (int) $row->id ) as $v ) {
					$v->chapter = $c;
					$verses[]   = $v;
				}
			}
			if ( ! $verses ) {
				return [ 'error' => '<div class="usx-error">Chapitre vide : ' . intval( $chapter ) . '</div>' ];
			}
			// Bornes réelles (premier verset du premier chapitre / dernier du dernier).
			$verse_start = (int) $verses[0]->verse;
			$verse_end   = (int) $verses[ count( $verses ) - 1 ]->verse;
			$ref_spec    = $chapter_end !== null ? $chapter . '-' . $chapter_end : (string) $chapter;
		} else {
			$seen  = [];
			$parts = [];
			foreach ( $segments as $seg ) {
				$parts[] = $seg[1] > $seg[0] ? $seg[0] . '-' . $seg[1] : (string) $seg[0];
				foreach ( (array) self::get_verses( (int) $chapter_row->id, (int) $seg[0], (int) $seg[1] ) as $v ) {
					if ( isset( $seen[ (int) $v->id ] ) ) {
						continue; // segments qui se chevauchent : pas de doublon
					}
					$seen[ (int) $v->id ] = true;
					$v->chapter           = $chapter;
					$verses[]             = $v;
				}
			}
			$ref_spec = $chapter . '.' . implode( ',', $parts );
			if ( ! $verses ) {
				return [ 'error' => '<div class="usx-error">Verset(s) introuvable(s) : ' . esc_html( implode( ',', $parts ) ) . '</div>' ];
			}
		}

		$book_title     = self::deaccent_uppercase_only( $book->title ?: $book->code );
		$reference      = sprintf( '%s %s – %s', $book_title, $ref_spec, $version->name );
		$copyright_text = self::get_version_copyright( (int) $version->id );

		return [
			'version'      => $version,
			'book'         => $book,
			'chapterRow'   => $chapter_row,
			'verses'       => $verses,
			'reference'    => $reference,
			'copyright'    => $copyright_text,
			'bookTitle'    => $book_title,
			'chapter'      => $chapter,
			'verseStart'   => $verse_start,
			'verseEnd'     => $verse_end,
			'wholeChapter' => $whole_chapter,
			'chapterEnd'   => $chapter_end,
			'multiChapter' => $chapter_end !== null,
			'segments'     => $segments,
			'refSpec'      => $ref_spec,
		];
	}
}
