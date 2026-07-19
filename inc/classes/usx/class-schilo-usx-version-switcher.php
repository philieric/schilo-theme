<?php
/**
 * Port de USX_Shortcode_Renderer, USX_Version_Switcher_Buttons et
 * USX_Version_Switcher_Global (plugin Usx-import) — mêmes noms d'action
 * AJAX (usx_switch_version_buttons, usx_switch_version_popup,
 * usx_switch_version_global) pour réutiliser les JS du plugin tels quels
 * (assets/js/usx-version-switcher-buttons.js, usx-version-switcher-global.js).
 */
defined( 'ABSPATH' ) || exit;

/**
 * Port de USX_Shortcode_Renderer : ré-exécute un shortcode encodé en
 * base64 dans un contexte de version forcée (utilisé par les AJAX de
 * changement de version).
 */
final class Schilo_Usx_Shortcode_Renderer {

	public static function render_raw( string $raw_shortcode, string $version_code = '' ): string {
		$raw_shortcode = trim( $raw_shortcode );
		if ( $raw_shortcode === '' ) {
			return '';
		}

		self::apply_version_context( $version_code );
		return do_shortcode( $raw_shortcode );
	}

	public static function render_base64( string $shortcode_b64, string $version_code = '' ): string {
		$raw = base64_decode( $shortcode_b64, true );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return '';
		}
		return self::render_raw( $raw, $version_code );
	}

	private static function apply_version_context( string $version_code ): void {
		$version_code = sanitize_text_field( $version_code );
		if ( $version_code === 'all' ) {
			$_GET['usx_version'] = 'all';
			return;
		}
		if ( $version_code !== '' ) {
			$_GET['usx_version'] = $version_code;
			return;
		}
		unset( $_GET['usx_version'] );
	}
}

/**
 * Port de USX_Version_Switcher_Buttons : toolbar de versions (inline +
 * popup) et ses 2 endpoints AJAX (rendu complet / rendu popup 3 zones).
 */
final class Schilo_Usx_Version_Switcher_Buttons {

	public const AJAX_ACTION       = 'usx_switch_version_buttons';
	public const AJAX_POPUP_ACTION = 'usx_switch_version_popup';

	private static ?self $instance = null;
	/** @var string[] */
	private array $shortcodes = [];
	/** @var array<int,array{code:string,name:string,is_default:int}> */
	private array $versions = [];
	private bool $assets_loaded = false;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->shortcodes = Schilo_Usx_Shortcodes::tags();
		$this->versions    = $this->fetch_versions();

		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_switch_version' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'ajax_switch_version' ] );

		add_action( 'wp_ajax_' . self::AJAX_POPUP_ACTION, [ $this, 'ajax_switch_version_popup' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_POPUP_ACTION, [ $this, 'ajax_switch_version_popup' ] );
	}

	/**
	 * MODE MANUEL : appelé depuis render_* uniquement quand on veut les boutons.
	 */
	public function wrap( string $output, string $content, string $tag, array $opts = [] ): string {
		if ( wp_doing_ajax() ) {
			return $output;
		}

		$enabled = $opts['enabled'] ?? true;
		if ( ! $enabled ) {
			return $output;
		}

		$tag = trim( $tag );
		if ( $tag === '' ) {
			return $output;
		}

		if ( ! empty( $this->shortcodes ) && ! in_array( $tag, $this->shortcodes, true ) ) {
			return $output;
		}

		if ( strpos( $output, 'usx-version-switcher' ) !== false ) {
			return $output;
		}

		$this->enqueue_assets_once();

		$id = 'usxv_' . wp_generate_uuid4();

		$toolbar = $this->render_toolbar(
			$tag,
			$content,
			[
				'class'        => 'usxv-toolbar',
				'role'         => 'toolbar',
				'show_default' => $opts['show_default'] ?? true,
				'show_all'     => $opts['show_all'] ?? false,
				'active'       => $opts['active'] ?? '',
				'attrs'        => $opts['toolbar_attrs'] ?? [],
			]
		);

		return '<span class="usx-version-switcher" id="' . esc_attr( $id ) . '">'
			. $toolbar
			. '<span class="usx-version-content" data-role="content">' . $output . '</span>'
			. '<span class="usxv-status" data-role="status" style="display:block;margin-top:6px;font-size:12px;opacity:.75;"></span>'
			. '</span>';
	}

	public function enqueue_assets_once(): void {
		if ( $this->assets_loaded ) {
			return;
		}
		$this->assets_loaded = true;

		$path = SCHILO_DIR . '/assets/js/usx-version-switcher-buttons.js';
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';

		wp_enqueue_script(
			'schilo-usx-version-switcher-buttons',
			SCHILO_ASSETS . '/js/usx-version-switcher-buttons.js',
			[],
			$ver,
			true
		);

		wp_localize_script(
			'schilo-usx-version-switcher-buttons',
			'USX_VersionSwitcherButtons',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php', 'relative' ),
				'action'      => self::AJAX_ACTION,
				'nonce'       => wp_create_nonce( self::AJAX_ACTION ),
				'popupAction' => self::AJAX_POPUP_ACTION,
				'popupNonce'  => wp_create_nonce( self::AJAX_POPUP_ACTION ),
			]
		);
	}

	/**
	 * Génère une toolbar de versions (générique, réutilisable partout).
	 */
	public function render_toolbar( string $tag, string $raw_content, array $options = [] ): string {
		$tag = trim( $tag );
		if ( $tag === '' ) {
			return '';
		}

		$this->enqueue_assets_once();

		$show_default = array_key_exists( 'show_default', $options ) ? (bool) $options['show_default'] : true;
		$show_all     = array_key_exists( 'show_all', $options ) ? (bool) $options['show_all'] : false;
		$active       = strtoupper( trim( (string) ( $options['active'] ?? '' ) ) );

		$class = trim( (string) ( $options['class'] ?? 'usxv-toolbar' ) );
		$role  = trim( (string) ( $options['role'] ?? 'toolbar' ) );
		$attrs = (array) ( $options['attrs'] ?? [] );

		// IMPORTANT : utilisé par l'AJAX pour rerender le shortcode
		$raw            = '[' . $tag . ']' . trim( (string) $raw_content ) . '[/' . $tag . ']';
		$shortcode_b64 = base64_encode( $raw );

		$html = '<div class="' . esc_attr( $class ) . '" data-role="' . esc_attr( $role ) . '" data-shortcode="' . esc_attr( $shortcode_b64 ) . '"';

		foreach ( $attrs as $k => $v ) {
			$k = trim( (string) $k );
			if ( $k === '' ) {
				continue;
			}
			$html .= ' ' . esc_attr( $k ) . '="' . esc_attr( (string) $v ) . '"';
		}

		$html .= '>';

		if ( $show_default ) {
			$html .= '<button type="button" class="usxv-btn' . ( $active === '' ? ' is-active' : '' ) . '" data-version="">Défaut</button>';
		}

		if ( $show_all ) {
			$html .= '<button type="button" class="usxv-btn' . ( $active === 'ALL' ? ' is-active' : '' ) . '" data-version="all">Toutes</button>';
		}

		foreach ( $this->get_versions_for_ui() as $code ) {
			$is_active = ( $active === $code );
			$html     .= '<button type="button" class="usxv-btn' . ( $is_active ? ' is-active' : '' ) . '" data-version="' . esc_attr( $code ) . '">' . esc_html( $code ) . '</button>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Retourne les codes versions exploitables pour l'UI (DRB, LSG, ...).
	 */
	public function get_versions_for_ui(): array {
		$out = [];

		foreach ( $this->versions as $v ) {
			$code = is_array( $v ) ? (string) ( $v['code'] ?? '' ) : '';
			$code = strtoupper( trim( $code ) );
			if ( $code === '' || $code === 'ALL' ) {
				continue;
			}
			$out[] = $code;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * AJAX inline : renvoie un HTML complet.
	 */
	public function ajax_switch_version(): void {
		try {
			check_ajax_referer( self::AJAX_ACTION, 'nonce' );

			$shortcode_b64 = isset( $_POST['shortcode'] ) ? (string) $_POST['shortcode'] : '';
			$version       = isset( $_POST['version'] ) ? sanitize_text_field( (string) $_POST['version'] ) : '';

			if ( $shortcode_b64 === '' ) {
				wp_send_json_error( [ 'message' => 'Shortcode manquant' ], 400 );
			}

			$html = Schilo_Usx_Shortcode_Renderer::render_base64( $shortcode_b64, $version );

			if ( ! is_string( $html ) ) {
				wp_send_json_error( [ 'message' => 'Rendu invalide (non string)' ], 500 );
			}

			wp_send_json_success( [ 'html' => $html ] );
		} catch ( \Throwable $e ) {
			error_log( '[Schilo USX AJAX] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( [ 'message' => 'Erreur AJAX: ' . $e->getMessage() ], 500 );
		}
	}

	/**
	 * AJAX POPUP : ne renvoie que 3 éléments à modifier — popup-ref (texte),
	 * popup-verses (innerHTML), copyright (texte).
	 */
	public function ajax_switch_version_popup(): void {
		try {
			check_ajax_referer( self::AJAX_POPUP_ACTION, 'nonce' );

			$shortcode_b64 = isset( $_POST['shortcode'] ) ? (string) $_POST['shortcode'] : '';
			$version       = isset( $_POST['version'] ) ? sanitize_text_field( (string) $_POST['version'] ) : '';

			if ( $shortcode_b64 === '' ) {
				wp_send_json_error( [ 'message' => 'Shortcode manquant' ], 400 );
			}

			$html = Schilo_Usx_Shortcode_Renderer::render_base64( $shortcode_b64, $version );

			if ( ! is_string( $html ) ) {
				wp_send_json_error( [ 'message' => 'Rendu invalide (non string)' ], 500 );
			}

			$parts = $this->extract_popup_parts( $html );
			$cp    = isset( $parts['copyright'] ) ? (string) $parts['copyright'] : '';
			$cp    = html_entity_decode( $cp, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$cp    = preg_replace( '/\x{00A0}/u', ' ', $cp );
			$cp    = trim( $cp );

			if ( $cp === '' || preg_match( '/^copyright$/i', $cp ) ) {
				$parts['copyright'] = '';
			} else {
				$parts['copyright'] = $cp;
			}

			$ref = isset( $parts['ref'] ) ? trim( (string) $parts['ref'] ) : '';
			$ver = isset( $_POST['version'] ) ? sanitize_text_field( (string) $_POST['version'] ) : '';

			// Badge de version affiché séparément de la référence (pas concaténé
			// dans le texte) — voir Schilo_Usx_Popup pour le rendu .popup-code.
			$ver_label = $ver;
			if ( $ver_label === '' ) {
				$ver_label = $this->get_default_version_code();
			} elseif ( $ver_label === 'all' ) {
				$ver_label = 'Toutes';
			}

			// Filet de sécurité : retire un éventuel suffixe " – XXX" hérité
			// d'anciennes données, la référence extraite ne devrait plus en avoir.
			if ( $ref !== '' ) {
				$parts['ref'] = trim( preg_replace( '/\s+[–-]\s+.+$/u', '', $ref ) );
			}

			wp_send_json_success(
				[
					'ref'         => $parts['ref'],
					'versionCode' => $ver_label,
					'verses_html' => $parts['verses_html'],
					'copyright'   => $parts['copyright'],
				]
			);
		} catch ( \Throwable $e ) {
			error_log( '[Schilo USX AJAX POPUP] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( [ 'message' => 'Erreur AJAX popup: ' . $e->getMessage() ], 500 );
		}
	}

	private function get_default_version_code(): string {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}

		$filtered = apply_filters( 'usx_default_version_code', null );
		if ( is_string( $filtered ) && trim( $filtered ) !== '' ) {
			$cached = strtoupper( trim( $filtered ) );
			return $cached;
		}

		$opt = get_option( 'usx_default_version_code', '' );
		if ( is_string( $opt ) && trim( $opt ) !== '' ) {
			$cached = strtoupper( trim( $opt ) );
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'usx_versions';
		$code  = $wpdb->get_var( "SELECT code FROM {$table} WHERE is_default = 1 LIMIT 1" );

		$code = strtoupper( trim( (string) $code ) );
		if ( $code === '' ) {
			$code = 'LSG';
		}

		$cached = $code;
		return $cached;
	}

	/**
	 * Extrait uniquement : popup-ref (texte), popup-verses (innerHTML),
	 * copyright (texte) — avec repli sur les attributs data-* de .bible-ref
	 * pour le cas du shortcode [bib]/[b].
	 */
	private function extract_popup_parts( string $html ): array {
		$out = [
			'ref'         => '',
			'verses_html' => '',
			'copyright'   => '',
		];

		if ( trim( $html ) === '' ) {
			return $out;
		}

		libxml_use_internal_errors( true );

		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML(
			'<!doctype html><html><head><meta charset="utf-8"></head><body><div id="__wrap__">' . $html . '</div></body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		$xp = new \DOMXPath( $dom );

		$ref_node = $xp->query( '//*[@id="__wrap__"]//*[contains(concat(" ", normalize-space(@class), " "), " popup-ref ")]' )->item( 0 );
		if ( $ref_node ) {
			$out['ref'] = trim( $ref_node->textContent ?? '' );
		}

		$cp_node = $xp->query( '//*[@id="__wrap__"]//*[contains(concat(" ", normalize-space(@class), " "), " copyright ")]' )->item( 0 );
		if ( $cp_node ) {
			$out['copyright'] = trim( $cp_node->textContent ?? '' );
		}

		$verses_node = $xp->query( '//*[@id="__wrap__"]//*[@data-role="popup-verses"]' )->item( 0 );
		if ( ! $verses_node ) {
			$verses_node = $xp->query( '//*[@id="__wrap__"]//*[contains(concat(" ", normalize-space(@class), " "), " usx-popup-verses ")]' )->item( 0 );
		}
		if ( $verses_node ) {
			$out['verses_html'] = $this->dom_inner_html( $verses_node );
		}

		if ( $out['ref'] === '' || $out['verses_html'] === '' || $out['copyright'] === '' ) {
			$bib_node = $xp->query( '//*[@id="__wrap__"]//*[contains(concat(" ", normalize-space(@class), " "), " bible-ref ")]' )->item( 0 );

			if ( $bib_node instanceof \DOMElement ) {
				if ( $out['ref'] === '' ) {
					$ref_attr = trim( (string) $bib_node->getAttribute( 'data-ref' ) );
					if ( $ref_attr !== '' ) {
						$out['ref'] = $ref_attr;
					}
				}

				if ( $out['copyright'] === '' ) {
					$cp_attr = trim( (string) $bib_node->getAttribute( 'data-copyright' ) );
					if ( $cp_attr !== '' ) {
						$out['copyright'] = $cp_attr;
					}
				}

				if ( $out['verses_html'] === '' ) {
					$content_attr = trim( (string) $bib_node->getAttribute( 'data-content' ) );

					if ( $content_attr !== '' ) {
						$dom2 = new \DOMDocument( '1.0', 'UTF-8' );
						$dom2->loadHTML(
							'<!doctype html><html><head><meta charset="utf-8"></head><body><div id="__c__">' . $content_attr . '</div></body></html>',
							LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
						);
						$xp2 = new \DOMXPath( $dom2 );

						$verses2 = $xp2->query( '//*[@id="__c__"]//*[@data-role="popup-verses"]' )->item( 0 );
						if ( ! $verses2 ) {
							$verses2 = $xp2->query( '//*[@id="__c__"]//*[contains(concat(" ", normalize-space(@class), " "), " usx-popup-verses ")]' )->item( 0 );
						}

						if ( $verses2 ) {
							$out['verses_html'] = $this->dom_inner_html( $verses2 );
						} else {
							$lines2 = $xp2->query( '//*[@id="__c__"]//*[contains(concat(" ", normalize-space(@class), " "), " popup-verse ")]' );
							if ( $lines2 && $lines2->length > 0 ) {
								$buf = '';
								foreach ( $lines2 as $n ) {
									$buf .= $dom2->saveHTML( $n );
								}
								$out['verses_html'] = $buf;
							}
						}
					}
				}
			}
		}

		libxml_clear_errors();
		return $out;
	}

	private function dom_inner_html( \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		return $html;
	}

	private function fetch_versions(): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'usx_versions';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return [];
		}

		$rows = $wpdb->get_results(
			"SELECT code, name, is_default FROM {$table} ORDER BY is_default DESC, name ASC",
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$clean = [];
		foreach ( $rows as $r ) {
			$clean[] = [
				'code'       => isset( $r['code'] ) ? sanitize_text_field( (string) $r['code'] ) : '',
				'name'       => isset( $r['name'] ) ? sanitize_text_field( (string) $r['name'] ) : '',
				'is_default' => isset( $r['is_default'] ) ? (int) $r['is_default'] : 0,
			];
		}
		return $clean;
	}
}

/**
 * Port de USX_Version_Switcher_Global : enveloppe automatiquement toute
 * sortie de shortcode USX pour la barre globale de version (bas de page),
 * avec garde-fou anti-double-wrap si Schilo_Usx_Version_Switcher_Buttons
 * a déjà enveloppé la sortie.
 */
final class Schilo_Usx_Version_Switcher_Global {

	const AJAX_ACTION = 'usx_switch_version_global';

	private $shortcodes         = [];
	private $needs_global_bar   = false;
	private $global_bar_printed = false;

	public function __construct() {
		$this->shortcodes = Schilo_Usx_Shortcodes::tags();

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_switch_version' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'ajax_switch_version' ] );
		add_filter( 'do_shortcode_tag', [ $this, 'wrap_shortcode_output' ], 10, 4 );
		add_action( 'wp_footer', [ $this, 'render_global_bar' ], 20 );
	}

	public function enqueue_assets() {
		wp_enqueue_script(
			'schilo-usx-version-switcher-global',
			SCHILO_ASSETS . '/js/usx-version-switcher-global.js',
			[],
			file_exists( SCHILO_DIR . '/assets/js/usx-version-switcher-global.js' ) ? (string) filemtime( SCHILO_DIR . '/assets/js/usx-version-switcher-global.js' ) : '1.0.0',
			true
		);

		wp_localize_script(
			'schilo-usx-version-switcher-global',
			'USX_VersionSwitcher',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
			]
		);
	}

	public function wrap_shortcode_output( $output, $tag, $attr, $m ) {
		if ( wp_doing_ajax() ) {
			return $output;
		}
		if ( ! in_array( $tag, $this->shortcodes, true ) ) {
			return $output;
		}

		$raw_shortcode = isset( $m[0] ) ? (string) $m[0] : '';
		if ( trim( $raw_shortcode ) === '' ) {
			return $output;
		}

		$this->needs_global_bar = true;

		// Si un switcher "local" (boutons) est déjà présent, on ne re-wrappe
		// pas, sinon on crée des switchers imbriqués.
		if ( strpos( $output, 'class="usx-version-switcher"' ) !== false || strpos( $output, "class='usx-version-switcher'" ) !== false ) {
			return $output;
		}

		$id      = 'usxv_' . wp_generate_uuid4();
		$encoded = base64_encode( $raw_shortcode );

		return '<span class="usx-version-switcher" id="' . esc_attr( $id ) . '" data-shortcode="' . esc_attr( $encoded ) . '">
					<span class="usx-version-content" data-role="content">' . $output . '</span>
				</span>';
	}

	public function render_global_bar() {
		if ( $this->global_bar_printed || ! $this->needs_global_bar || is_admin() ) {
			return;
		}
		$this->global_bar_printed = true;

		echo '<div id="usx-global-version-switcher" class="usx-version-bar" style="display:flex;gap:6px;margin:14px 0;padding:10px 0 6px 0;border-bottom:1px solid #eee;">
				<button type="button" class="usxv-btn is-active" data-version="">Défaut</button>
				<button type="button" class="usxv-btn" data-version="all">Toutes</button>
				<span class="usxv-status" data-role="status" style="margin-left:auto;font-size:12px;opacity:.7"></span>
			  </div>';
	}

	public function ajax_switch_version() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$shortcode_b64 = isset( $_POST['shortcode'] ) ? (string) $_POST['shortcode'] : '';
		$version       = isset( $_POST['version'] ) ? sanitize_text_field( (string) $_POST['version'] ) : '';

		if ( $shortcode_b64 === '' ) {
			wp_send_json_error( [ 'message' => 'Shortcode manquant.' ], 400 );
		}

		$html = Schilo_Usx_Shortcode_Renderer::render_base64( $shortcode_b64, $version );
		wp_send_json_success( [ 'html' => $html ] );
	}
}
