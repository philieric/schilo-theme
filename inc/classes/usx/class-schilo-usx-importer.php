<?php
/**
 * Port de USX_Importer (plugin Usx-import, class-usx-importer.php) —
 * upload + parsing USX, suppression version/livre, menu d'admin.
 *
 * Différences volontaires avec l'original :
 * - Pas de register_activation_hook() (n'existe pas côté thème) : la
 *   création des tables est gérée par Schilo_Usx_Table_Creator::maybe_upgrade()
 *   sur admin_init, voir class-schilo-usx-integration.php.
 * - Pas de add_action('wp_footer', [...'inject_popup_script']) ici : déjà
 *   fait par Schilo_Usx_Shortcodes::render_bib_shortcode() quand un [bib]
 *   est réellement rendu (évite un hook redondant).
 * - Menu renommé "Import USX" (le parsing .csv n'a jamais été implémenté
 *   dans le plugin — parse_csv_lsg() retourne toujours des stats à zéro,
 *   comportement conservé ici à l'identique, mais le libellé n'affiche
 *   plus "/CSV" pour ne pas laisser croire que c'est fonctionnel).
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Importer {

	private static $instance;
	private $verset_processor;
	private $display_admin;
	private $meta_admin;
	private $log      = [];
	private $log_path = '';

	public static function init() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->verset_processor = new Schilo_Usx_Verset_Processor();
		$this->display_admin    = new Schilo_Usx_Display_Admin( $this );
		$this->meta_admin       = new Schilo_Usx_Version_Meta_Admin();

		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_filter( 'upload_mimes', [ $this, 'allow_usx_upload' ] );
		add_filter( 'user_has_cap', [ $this, 'bypass_file_type_check' ], 10, 4 );
	}

	public function allow_usx_upload( $mimes ) {
		$mimes['usx'] = 'application/xml';
		$mimes['xml'] = 'application/xml';
		$mimes['csv'] = 'text/csv';
		return $mimes;
	}

	/**
	 * Contourne la restriction WP sur les types de fichiers uploadés, pour
	 * permettre .usx/.xml. Contrairement à l'original (qui accorde
	 * 'unfiltered_upload' à n'importe quel utilisateur dès que la
	 * capacité est vérifiée, y compris hors contexte d'import), on ne
	 * l'accorde qu'aux utilisateurs qui ont déjà manage_options — les
	 * seuls habilités à accéder à l'écran d'import (voir register_menu()).
	 */
	public function bypass_file_type_check( $allcaps, $cap, $args, $user ) {
		if ( isset( $args[0] ) && $args[0] === 'unfiltered_upload' && user_can( $user, 'manage_options' ) ) {
			$allcaps[ $args[0] ] = true;
		}
		return $allcaps;
	}

	public function register_menu() {
		add_menu_page(
			'Import USX',
			'Import USX',
			'manage_options',
			'usx-importer',
			[ $this->display_admin, 'import_page' ],
			'dashicons-upload'
		);
		add_submenu_page(
			null,
			'Modifier une version',
			'Modifier une version',
			'manage_options',
			'usx-importer-edit',
			[ $this->display_admin, 'edit_version_page' ]
		);
	}

	public function handle_delete_version() {
		if ( ! isset( $_POST['delete_version_id'] ) ) {
			return;
		}

		$version_id  = (int) $_POST['delete_version_id'];
		$nonce_field = 'delete_version_nonce';

		if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( $_POST[ $nonce_field ], 'delete_version_' . $version_id ) ) {
			$this->display_admin->show_error( 'Échec de sécurité lors de la suppression.' );
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}usx_notes WHERE verse_id IN (
					SELECT id FROM {$prefix}usx_verses WHERE chapter_id IN (
						SELECT id FROM {$prefix}usx_chapters WHERE book_id IN (
							SELECT id FROM {$prefix}usx_books WHERE version_id = %d
						)
					)
				)",
				$version_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}usx_verses WHERE chapter_id IN (
					SELECT c.id FROM {$prefix}usx_chapters c
					INNER JOIN {$prefix}usx_books b ON c.book_id = b.id
					WHERE b.version_id = %d
				)",
				$version_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}usx_chapters WHERE book_id IN (
					SELECT id FROM {$prefix}usx_books WHERE version_id = %d
				)",
				$version_id
			)
		);

		$wpdb->delete( "{$prefix}usx_books", [ 'version_id' => $version_id ] );
		$wpdb->delete( "{$prefix}usx_version_meta", [ 'version_id' => $version_id ] );
		$wpdb->delete( "{$prefix}usx_version_meta_extra", [ 'version_id' => $version_id ] );
		$wpdb->delete( "{$prefix}usx_versions", [ 'id' => $version_id ] );

		$this->display_admin->show_updated( 'Version supprimée avec succès, ainsi que toutes ses données associées.' );
	}

	public function handle_delete_book() {
		if ( ! isset( $_POST['delete_book_id'] ) ) {
			return;
		}

		$book_id     = (int) $_POST['delete_book_id'];
		$nonce_field = 'delete_book_nonce';

		if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( $_POST[ $nonce_field ], 'delete_book_' . $book_id ) ) {
			$this->display_admin->show_error( 'Échec de sécurité lors de la suppression du livre.' );
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}usx_notes WHERE verse_id IN (
					SELECT id FROM {$prefix}usx_verses WHERE chapter_id IN (
						SELECT id FROM {$prefix}usx_chapters WHERE book_id = %d
					)
				)",
				$book_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}usx_verses WHERE chapter_id IN (
					SELECT id FROM {$prefix}usx_chapters WHERE book_id = %d
				)",
				$book_id
			)
		);

		$wpdb->delete( "{$prefix}usx_chapters", [ 'book_id' => $book_id ] );
		$wpdb->delete( "{$prefix}usx_books", [ 'id' => $book_id ] );

		$this->display_admin->show_updated( 'Livre supprimé avec succès, ainsi que toutes ses données associées.' );
	}

	public function handle_upload() {
		if ( empty( $_FILES['usx_file'] ) ) {
			$this->display_admin->show_error( 'Erreur : aucun fichier reçu.' );
			return;
		}

		$files = $_FILES['usx_file'];

		$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

		if ( ! empty( $_POST['version_id'] ) && intval( $_POST['version_id'] ) > 0 ) {
			$version_id = intval( $_POST['version_id'] );
		} else {
			$version_id = $this->insert_version( $_POST['version_name'], $_POST['version_code'] );
			if ( $version_id === 0 ) {
				$this->display_admin->show_error( 'Code de version déjà utilisé.' );
				return;
			}
		}

		$global_stats = [ 'versets' => 0, 'titles' => 0, 'subtitles' => 0, 'notes' => 0 ];

		for ( $i = 0; $i < $file_count; $i++ ) {
			$tmp_name = is_array( $files['tmp_name'] ) ? $files['tmp_name'][ $i ] : $files['tmp_name'];
			$name     = is_array( $files['name'] ) ? $files['name'][ $i ] : $files['name'];
			$error    = is_array( $files['error'] ) ? $files['error'][ $i ] : $files['error'];

			if ( $error !== UPLOAD_ERR_OK || ! is_uploaded_file( $tmp_name ) ) {
				continue;
			}

			$upload_dir  = wp_upload_dir();
			$destination = trailingslashit( $upload_dir['path'] ) . basename( $name );

			if ( ! move_uploaded_file( $tmp_name, $destination ) ) {
				continue;
			}

			$ext = strtolower( pathinfo( $destination, PATHINFO_EXTENSION ) );

			if ( $ext === 'csv' ) {
				$stats = $this->parse_csv_lsg( $destination, $version_id );
			} else {
				$stats = $this->parse_usx_normalized( $destination, $version_id );
			}

			if ( is_array( $stats ) ) {
				foreach ( $global_stats as $k => $v ) {
					if ( isset( $stats[ $k ] ) ) {
						$global_stats[ $k ] += (int) $stats[ $k ];
					}
				}
			}
		}

		$this->display_admin->show_import_summary( $global_stats );
	}

	public function insert_version( $name, $code ) {
		global $wpdb;
		$table = $wpdb->prefix . 'usx_versions';
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE code = %s", $code ) ) ) {
			return 0;
		}

		$wpdb->insert(
			$table,
			[
				'name'     => sanitize_text_field( $name ),
				'code'     => sanitize_text_field( $code ),
				'language' => 'fr',
			]
		);
		return $wpdb->insert_id;
	}

	/**
	 * Jamais implémenté dans le plugin d'origine (parse_csv_lsg y
	 * retournait déjà des stats à zéro) — comportement conservé à
	 * l'identique, hors périmètre de ce portage.
	 */
	public function parse_csv_lsg( $file, $version_id ) {
		return [ 'versets' => 0, 'titles' => 0, 'subtitles' => 0, 'notes' => 0 ];
	}

	public function parse_usx_normalized( $file, $version_id ) {
		global $wpdb;
		$prefix           = $wpdb->prefix;
		$this->log        = [];
		$upload_dir       = wp_upload_dir();
		$this->log_path   = $upload_dir['basedir'] . '/usx-import-log.txt';

		$xml = @simplexml_load_file( $file );
		if ( ! $xml ) {
			$this->log[] = 'Erreur de lecture du fichier.';
			$this->write_log();
			return 0;
		}

		$book_code     = '';
		$book_title    = '';
		$book_id       = null;
		$chapter       = 0;
		$chapter_title = '';
		$sub_title     = '';
		$chapter_id    = null;
		$section_order = 0;
		$count         = 0;
		$nb_titles     = 0;
		$nb_subtitles  = 0;
		$nb_notes      = 0;

		$context = [
			'wpdb'          => $wpdb,
			'prefix'        => $prefix,
			'book_code'     => &$book_code,
			'book_title'    => &$book_title,
			'book_id'       => &$book_id,
			'chapter'       => &$chapter,
			'chapter_title' => &$chapter_title,
			'sub_title'     => &$sub_title,
			'chapter_id'    => &$chapter_id,
			'section_order' => &$section_order,
			'count'         => &$count,
			'nb_titles'     => &$nb_titles,
			'nb_subtitles'  => &$nb_subtitles,
			'nb_notes'      => &$nb_notes,
			'log'           => &$this->log,
		];

		foreach ( $xml->children() as $node ) {
			switch ( $node->getName() ) {
				case 'book':
					if ( $book_id ) {
						$this->update_book_counters(
							$book_id,
							[
								'chapters'  => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}usx_chapters WHERE book_id = %d", $book_id ) ),
								'versets'   => $count,
								'titles'    => $nb_titles,
								'subtitles' => $nb_subtitles,
								'notes'     => $nb_notes,
							]
						);
						$count        = 0;
						$nb_titles    = 0;
						$nb_subtitles = 0;
						$nb_notes     = 0;
					}
					$book_code = (string) $node['code'];
					break;

				case 'para':
					$style = (string) $node['style'];
					switch ( $style ) {
						case 'toc1':
							$book_title = (string) $node;
							$book_row   = $wpdb->get_row(
								$wpdb->prepare(
									"SELECT id FROM {$prefix}usx_books WHERE version_id = %d AND code = %s",
									$version_id,
									$book_code
								)
							);
							if ( ! $book_row ) {
								$wpdb->insert(
									"{$prefix}usx_books",
									[
										'version_id' => $version_id,
										'code'       => $book_code,
										'title'      => $book_title,
									]
								);
								$book_id     = $wpdb->insert_id;
								$this->log[] = "Livre ajouté : $book_code - $book_title";
							} else {
								$book_id = $book_row->id;
							}
							break;
						case 'ms':
							$chapter_title = (string) $node;
							$context       = $this->verset_processor->handle_new_chapter_title( $chapter_title, $context );
							break;
						case 'mr':
							break;
						case 's':
							$sub_title = (string) $node;
							$context   = $this->verset_processor->handle_new_sub_title( $sub_title, $context );
							break;
						case 'p':
						case 'q':
						case 'd':
						case 'q1':
						case 'v':
						case 'nb':
							$context = $this->verset_processor->process_verses( $node, $context );
							break;
					}
					break;

				case 'chapter':
					$chapter       = (int) $node['number'];
					$chapter_title = '';
					$sub_title     = '';
					$chapter_id    = null;
					break;
			}
		}

		if ( $book_id ) {
			$this->update_book_counters(
				$book_id,
				[
					'chapters'  => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}usx_chapters WHERE book_id = %d", $book_id ) ),
					'versets'   => $count,
					'titles'    => $nb_titles,
					'subtitles' => $nb_subtitles,
					'notes'     => $nb_notes,
				]
			);
		}

		$this->log[] = "IMPORT TERMINÉ. Versets insérés : $count";
		$this->write_log();

		return [
			'versets'   => (int) $context['count'],
			'titles'    => (int) $context['nb_titles'],
			'subtitles' => (int) $context['nb_subtitles'],
			'notes'     => (int) $context['nb_notes'],
		];
	}

	private function write_log() {
		if ( ! $this->log_path ) {
			return;
		}
		file_put_contents( $this->log_path, implode( PHP_EOL, $this->log ) );
	}

	private function update_book_counters( $book_id, $counters ) {
		global $wpdb;
		$table = $wpdb->prefix . 'usx_books';
		$wpdb->update(
			$table,
			[
				'chapters_count'  => intval( $counters['chapters'] ),
				'verses_count'    => intval( $counters['versets'] ),
				'notes_count'     => intval( $counters['notes'] ),
				'titles_count'    => intval( $counters['titles'] ),
				'subtitles_count' => intval( $counters['subtitles'] ),
			],
			[ 'id' => intval( $book_id ) ]
		);
	}
}
