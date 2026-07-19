<?php
/**
 * Port de USX_Shortcode_BibleCard + USX_Shortcode_Registry (plugin
 * Usx-import) — même HTML généré (mêmes classes) pour que
 * assets/css/usx-integration.css s'applique sans changement. Le CSS est
 * géré séparément par Schilo_Assets, pas par cette classe.
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Shortcodes {

	const MAP = [
		'bib' => 'render_bib_shortcode',
		'b'   => 'render_bib_shortcode', // [b] est un alias de [bib]
		'bvc' => 'render_bvc_shortcode',
		'brc' => 'render_brc_shortcode',
		'bnv' => 'render_bnv_shortcode',
	];

	public static function tags(): array {
		return array_keys( self::MAP );
	}

	public static function register(): void {
		foreach ( self::MAP as $tag => $callback ) {
			add_shortcode( $tag, [ __CLASS__, $callback ] );
		}
	}

	/**
	 * Normalise un retour array/stdClass en tableau PHP (récursif).
	 */
	private static function to_array_recursive( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::to_array_recursive( $v );
			}
		}
		return $value;
	}

	/**
	 * Extrait un champ qui peut s'appeler différemment selon les versions (ex: verse/verset).
	 */
	private static function pick( array $data, array $keys, $default = null ) {
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $data ) && $data[ $k ] !== null && $data[ $k ] !== '' ) {
				return $data[ $k ];
			}
		}
		return $default;
	}

	private static function parse_reference( string $content ) {
		$content = trim( $content );

		$data = Schilo_Usx_Bible_Lookup::resolve_reference_data( $content );
		$data = self::to_array_recursive( $data );

		if ( ! is_array( $data ) ) {
			return '<div class="usx-bible-card usx-error">Référence invalide.</div>';
		}

		if ( ! empty( $data['error'] ) ) {
			return $data['error'];
		}

		$version = self::to_array_recursive( $data['version'] ?? null );
		$book    = self::to_array_recursive( $data['book'] ?? null );

		$version_id   = is_array( $version ) ? (int) ( $version['id'] ?? 0 ) : 0;
		$version_code = is_array( $version ) ? (string) ( $version['name'] ?? '' ) : '';

		$book_code = is_array( $book ) ? (string) ( $book['code'] ?? '' ) : '';
		$book_code = trim( $book_code );

		$book_name = '';
		if ( is_array( $book ) ) {
			$book_name = (string) ( $book['title'] ?? $book['name'] ?? '' );
		}
		if ( $book_name === '' && ! empty( $data['bookTitle'] ) ) {
			$book_name = (string) $data['bookTitle'];
		}
		$book_name = trim( $book_name );
		if ( $book_name === '' ) {
			$book_name = $book_code;
		}

		$chapter_row = self::to_array_recursive( $data['chapterRow'] ?? null );
		$chapter_id  = is_array( $chapter_row ) ? (int) ( $chapter_row['id'] ?? 0 ) : 0;

		$chapter = (int) self::pick( $data, [ 'chapter', 'chapitre' ], 0 );

		$v_start = self::pick( $data, [ 'verseStart', 'verse_start', 'verse', 'verset', 'startVerse', 'start_verse' ], null );
		$v_end   = self::pick( $data, [ 'verseEnd', 'verse_end', 'endVerse', 'end_verse', 'endVerset', 'end_verset' ], null );

		if ( ( $v_start === null || $v_start === '' ) && ! empty( $data['verses'][0]['verse'] ) ) {
			$v_start = $data['verses'][0]['verse'];
		}
		if ( ( $v_end === null || $v_end === '' ) && ! empty( $data['verseEnd'] ) ) {
			$v_end = $data['verseEnd'];
		}

		$v_start = ( $v_start !== null && $v_start !== '' ) ? (int) $v_start : 0;
		$v_end   = ( $v_end !== null && $v_end !== '' ) ? (int) $v_end : 0;

		if ( $v_end <= 0 ) {
			$v_end = $v_start;
		}
		if ( $v_end > 0 && $v_start > 0 && $v_end < $v_start ) {
			$v_end = $v_start;
		}

		if ( $version_id <= 0 || $book_code === '' || $chapter_id <= 0 || $chapter <= 0 || $v_start <= 0 ) {
			return '<div class="usx-bible-card usx-error">Référence incomplète ou non reconnue.</div>';
		}

		return [
			'version_id'   => $version_id,
			'version_code' => $version_code,
			'book_code'    => $book_code,
			'book_name'    => $book_name,
			'chapter_id'   => $chapter_id,
			'chapter'      => $chapter,
			'vStart'       => $v_start,
			'vEnd'         => $v_end,
			'verse'        => $v_start,
			'endVerse'     => $v_end,
		];
	}

	public static function render_bib_shortcode( $atts = [], $content = '', $tag = 'bib' ) {
		$data = Schilo_Usx_Bible_Lookup::resolve_reference_data( $content );

		if ( isset( $data['error'] ) ) {
			return $data['error'];
		}
		extract( $data ); // Crée $version, $book, $chapterRow, $verses, $reference, $bookTitle, $chapter, $verseStart, $verseEnd, $copyright

		$reference = sprintf(
			'%s %d.%d%s – %s',
			$bookTitle,
			$chapter,
			$verseStart,
			( $verseEnd > $verseStart ? '-' . $verseEnd : '' ),
			$version->name
		);
		$copyright_text = Schilo_Usx_Bible_Lookup::get_version_copyright( (int) $version->id );

		$verses_html = '';
		foreach ( $verses as $v ) {
			$verses_html .= '<div class="popup-verse"><strong>' . intval( $v->verse ) . '.</strong> ' . esc_html( $v->verse_text ) . '</div>';
		}

		$toolbar_html = Schilo_Usx_Version_Switcher_Buttons::instance()->render_toolbar(
			'bib',
			(string) $content,
			[
				'class'        => 'usxv-toolbar usxv-toolbar-popup',
				'role'         => 'popup-toolbar',
				'show_default' => true,
				'show_all'     => false,
				'active'       => '',
				'attrs'        => [ 'data-context' => 'popup' ],
			]
		);

		$popup_content  = '<div class="usx-popup-switcher" data-role="popup-switcher">';
		$popup_content .= $toolbar_html;
		$popup_content .= '<div class="usx-popup-verses" data-role="popup-verses">';
		$popup_content .= $verses_html;
		$popup_content .= '</div>';
		$popup_content .= '</div>';

		$output  = '<span class="bible-ref" data-content="' . esc_attr( $popup_content ) . '" data-ref="' . esc_attr( $reference ) . '" data-copyright="' . esc_attr( $copyright_text ) . '">';
		$output .= esc_html( $bookTitle . ' ' . $chapter . '.' . $verseStart . ( $verseEnd > $verseStart ? '-' . $verseEnd : '' ) );
		$output .= '</span>';

		add_action( 'wp_footer', [ 'Schilo_Usx_Popup', 'inject_popup_script' ] );

		return $output;
	}

	public static function render_bvc_shortcode( $atts = [], $content = '', $tag = 'bvc' ) {
		$ref = self::parse_reference( (string) $content );
		if ( ! is_array( $ref ) ) {
			return $ref;
		}

		$verses = Schilo_Usx_Bible_Lookup::get_verses( (int) $ref['chapter_id'], (int) $ref['vStart'], (int) $ref['vEnd'] );
		if ( empty( $verses ) ) {
			return '<div class="usx-bible-card usx-error">Aucun verset trouvé.</div>';
		}

		$start     = (int) ( $ref['vStart'] ?? $ref['verse'] ?? 0 );
		$end       = (int) ( $ref['vEnd'] ?? $ref['endVerse'] ?? $start );
		$reference = esc_html( $ref['book_name'] . ' ' . $ref['chapter'] . '.' . $start . ( $end && $end !== $start ? '-' . $end : '' ) );

		$html  = '<div class="usx-bvc">';
		$html .= '<div class="usx-bvc-header">';
		$html .= '<span class="usx-bvc-ref">' . $reference . '</span>';
		$html .= '<span class="usx-bvc-version"> (' . esc_html( $ref['version_code'] ) . ')</span>';
		$html .= '</div>';

		$html .= '<div class="usx-bvc-body">';
		foreach ( $verses as $v ) {
			$html .= '<p class="usx-bvc-line">' . esc_html( is_array( $v ) ? ( $v['verse_text'] ?? $v['text'] ?? '' ) : ( $v->verse_text ?? $v->text ?? '' ) ) . '</p>';
		}
		$html .= '</div>';
		$html .= '<div class="usx-bvc-copyright">' . esc_html( Schilo_Usx_Bible_Lookup::get_version_copyright( (int) $ref['version_id'] ) ) . '</div>';
		$html .= '</div>';

		if ( wp_doing_ajax() ) {
			return $html;
		}

		return '<div class="bvc-container">' .
			Schilo_Usx_Version_Switcher_Buttons::instance()->wrap( $html, (string) $content, (string) $tag ) .
		'</div>';
	}

	public static function render_brc_shortcode( $atts = [], $content = '', $tag = 'brc' ) {
		$ref = self::parse_reference( (string) $content );
		if ( ! is_array( $ref ) ) {
			return $ref;
		}

		$verses = Schilo_Usx_Bible_Lookup::get_verses( (int) $ref['chapter_id'], (int) $ref['vStart'], (int) $ref['vEnd'] );
		if ( empty( $verses ) ) {
			return '<div class="usx-bible-card usx-error">Aucun verset trouvé.</div>';
		}

		$start     = (int) ( $ref['vStart'] ?? $ref['verse'] ?? 0 );
		$end       = (int) ( $ref['vEnd'] ?? $ref['endVerse'] ?? $start );
		$reference = esc_html( $ref['book_name'] . ' ' . $ref['chapter'] . '.' . $start . ( $end && $end !== $start ? '-' . $end : '' ) );

		// Classe couleur RVBJM selon le livre
		$gospel_map = [ 'MAT' => 'matthieu', 'MRK' => 'marc', 'LUK' => 'luc', 'JHN' => 'jean' ];
		$book_class = $gospel_map[ strtoupper( $ref['book_code'] ) ] ?? 'bible';

		$html  = '<div class="usx-brc citation-' . esc_attr( $book_class ) . '">';
		$html .= '<span class="usx-brc-ref">' . $reference . '</span>';
		$html .= '<span class="usx-brc-version"> (' . esc_html( $ref['version_code'] ) . ')</span>';
		$html .= '<span class="usx-brc-ref">' . ' : ' . '</span>';
		$html .= '<div class="usx-brc-quote">';
		foreach ( $verses as $v ) {
			$html .= '<span class="usx-brc-text">' . esc_html( is_array( $v ) ? ( $v['verse_text'] ?? $v['text'] ?? '' ) : ( $v->verse_text ?? $v->text ?? '' ) ) . '</span>';
		}
		$html .= '</div>';
		$html .= '<div class="usx-brc-copyright">' . esc_html( Schilo_Usx_Bible_Lookup::get_version_copyright( (int) $ref['version_id'] ) ) . '</div>';
		$html .= '</div>';

		if ( wp_doing_ajax() ) {
			return $html;
		}

		return '<div class="brc-container citation-' . esc_attr( $book_class ) . '">' . Schilo_Usx_Version_Switcher_Buttons::instance()->wrap( $html, (string) $content, (string) $tag ) . '</div>';
	}

	public static function render_bnv_shortcode( $atts = [], $content = '' ) {
		$content = trim( (string) $content );

		$book_token = '';
		if ( $content !== '' ) {
			$parts      = preg_split( '/\s+/', $content );
			$book_token = isset( $parts[0] ) ? trim( $parts[0] ) : '';
		}
		if ( $book_token === '' ) {
			$book_token = $content;
		}

		$content = trim( $book_token . ' 1.1' );

		$ref = self::parse_reference( $content );
		if ( ! is_array( $ref ) ) {
			return $ref;
		}

		$ref['no_verset'] = 'non cité dans le livre';

		$verses = Schilo_Usx_Bible_Lookup::get_verses( (int) $ref['chapter_id'], (int) $ref['vStart'], (int) $ref['vEnd'] );
		if ( empty( $verses ) ) {
			return '<div class="usx-bible-card usx-error">Aucun verset trouvé.</div>';
		}

		$reference = esc_html( $ref['book_name'] );

		$html  = '<div class="usx-bnv">';
		$html .= '<span class="usx-bnv-ref">' . $reference . '</span>';
		$html .= '<span class="usx-bnv-version"> (' . esc_html( $ref['version_code'] ) . ')</span>';
		$html .= '<span class="usx-bnv-noverset"> ' . esc_html( $ref['no_verset'] ) . '</span>';
		$html .= '<div class="usx-bnv-body">';
		$html .= '</div>';
		$html .= '</div>';

		return '<div class="bnv-container">' . $html . '</div>';
	}
}
