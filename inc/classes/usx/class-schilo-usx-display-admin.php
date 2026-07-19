<?php
/**
 * Port de USX_Display_Admin (plugin Usx-import, class-usx-display-admin.php)
 * — rendu des écrans "Import USX" et "Modifier une version" (édition,
 * ajout de livres, édition inline des abréviations, suppression).
 *
 * L'accès à ces méthodes est protégé en amont par la capacité
 * 'manage_options' exigée sur les pages (add_menu_page/add_submenu_page
 * dans Schilo_Usx_Importer::register_menu()) — WordPress n'appelle pas
 * ces callbacks si l'utilisateur n'a pas la capacité, donc pas de
 * vérification redondante ici (même comportement que l'original).
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Display_Admin {

	private $plugin;

	public function __construct( $plugin = null ) {
		$this->plugin = $plugin;
	}

	public function import_page() {
		echo '<div class="wrap"><h1>Import USX</h1>';
		$this->plugin->handle_delete_version();
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'usx_import_action', 'usx_import_nonce' );
		echo '<p><input type="text" name="version_name" placeholder="Nom de la version" required /></p>';
		echo '<p><input type="text" name="version_code" placeholder="Code (ex. LSG1910)" required /></p>';
		echo '<p><input type="file" name="usx_file" accept=".usx,.xml,.txt,.csv" required /></p>';
		echo '<p><input type="submit" name="import_usx" class="button button-primary" value="Importer" /></p>';
		echo '</form>';

		if ( isset( $_POST['import_usx'] ) ) {
			if ( ! isset( $_POST['usx_import_nonce'] ) || ! wp_verify_nonce( $_POST['usx_import_nonce'], 'usx_import_action' ) ) {
				$this->show_error( 'Échec de sécurité : nonce invalide.' );
				return;
			}
			$this->plugin->handle_upload();
		}

		$this->display_existing_versions();
		echo '</div>';
	}

	public function display_existing_versions() {
		global $wpdb;
		$table   = $wpdb->prefix . 'usx_versions';
		$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );

		if ( ! $results ) {
			echo '<h2>Versions enregistrées</h2><p>Aucune version enregistrée pour le moment.</p>';
			return;
		}

		echo '<h2>Versions enregistrées</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>Nom</th><th>Code</th><th>Langue</th><th>Date</th><th>Statistiques</th><th>Modifier</th><th>Supprimer</th>';
		echo '</tr></thead><tbody>';

		foreach ( $results as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row->id ) . '</td>';
			echo '<td>' . esc_html( $row->name );
			if ( ! empty( $row->is_default ) ) {
				echo ' <span style="background:#2271b1;color:white;padding:2px 6px;border-radius:4px;font-size:0.75em;margin-left:6px;">par défaut</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $row->code ) . '</td>';
			echo '<td>' . esc_html( $row->language ) . '</td>';
			echo '<td>' . esc_html( $row->created_at ) . '</td>';
			echo '<td style="font-size:0.95em"></td>';
			echo '<td><a class="button" href="' . admin_url( 'admin.php?page=usx-importer-edit&version_id=' . esc_attr( $row->id ) ) . '">Modifier</a></td>';
			echo '<td><form method="post" onsubmit="return confirm(\'Confirmer la suppression ?\');">';
			wp_nonce_field( 'delete_version_' . $row->id, 'delete_version_nonce' );
			echo '<input type="hidden" name="delete_version_id" value="' . esc_attr( $row->id ) . '" />';
			echo '<input type="submit" class="button button-secondary" value="🗑 Supprimer" />';
			echo '</form></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	public function edit_version_page() {
		if ( ! isset( $_GET['version_id'] ) ) {
			$this->show_error( 'ID de version manquant.' );
			return;
		}
		$this->plugin->handle_delete_book();

		global $wpdb;
		$version_id = (int) $_GET['version_id'];
		$version    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}usx_versions WHERE id = %d", $version_id ) );

		if ( ! $version ) {
			$this->show_error( 'Version introuvable.' );
			return;
		}

		$this->handle_update_abreviation( $version_id );

		if ( isset( $_POST['update_version'] ) ) {
			$is_default = isset( $_POST['is_default'] ) ? 1 : 0;

			if ( $is_default ) {
				$wpdb->update( $wpdb->prefix . 'usx_versions', [ 'is_default' => 0 ], [ 'is_default' => 1 ] );
			}

			check_admin_referer( 'update_version_' . $version_id );
			$wpdb->update(
				$wpdb->prefix . 'usx_versions',
				[
					'name'       => sanitize_text_field( $_POST['version_name'] ),
					'code'       => sanitize_text_field( $_POST['version_code'] ),
					'language'   => sanitize_text_field( $_POST['version_language'] ),
					'is_default' => $is_default,
				],
				[ 'id' => $version_id ]
			);
			$this->show_updated( 'Version mise à jour.' );
			$version = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}usx_versions WHERE id = %d", $version_id ) );
		}

		if ( isset( $_POST['import_usx'] ) ) {
			if ( ! isset( $_POST['usx_import_nonce'] ) || ! wp_verify_nonce( $_POST['usx_import_nonce'], 'usx_import_action' ) ) {
				$this->show_error( 'Échec de sécurité : nonce invalide.' );
			} else {
				$_POST['version_name'] = $version->name;
				$_POST['version_code'] = $version->code;
				$_POST['version_id']   = $version_id;
				$this->plugin->handle_upload( $version_id );
			}
		}

		echo '<div class="wrap">';
		echo '<a href="' . admin_url( 'admin.php?page=usx-importer' ) . '"  class="button" >&larr; Retour à la liste des versions</a>';
		echo '<h1 style="font-size:1.6em;margin-bottom:1em;">Édition de la version : <span style="color:#0366d6">' . esc_html( $version->name ) . '</span></h1>';

		echo '<div style="display:flex; gap: 30px; align-items: flex-start;">';
		$this->block_edit_version( $version );
		$this->block_add_book( $version_id );
		$this->block_add_meta( $version_id );
		echo '</div>';

		$this->block_list_books( $version_id );

		echo '</div>';
	}

	private function block_edit_version( $version ) {
		?>
		<div style="background:#fff;border-radius:10px;padding:24px;flex:1;min-width:340px;max-width:500px;box-shadow:0 2px 8px #e8e8e8">
			<form method="post">
				<?php wp_nonce_field( 'update_version_' . $version->id ); ?>
				<h2 style="font-size:1.3em;margin-top:0">Modifier la version</h2>
				<p>
					<label style="font-weight:bold;font-size:1.05em;">Nom :</label><br>
					<input type="text" name="version_name" value="<?php echo esc_attr( $version->name ); ?>" required style="width:100%;font-size:1.15em;padding:5px 8px;" />
				</p>
				<p>
					<label style="font-weight:bold;font-size:1.05em;">Code :</label><br>
					<input type="text" name="version_code" value="<?php echo esc_attr( $version->code ); ?>" required style="width:100%;font-size:1.15em;padding:5px 8px;" />
				</p>
				<p>
					<label style="font-weight:bold;font-size:1.05em;">Langue :</label><br>
					<input type="text" name="version_language" value="<?php echo esc_attr( $version->language ); ?>" required style="width:100%;font-size:1.15em;padding:5px 8px;" />
				</p>
				<p>
					<label>
						<input type="checkbox" name="is_default" value="1" <?php checked( $version->is_default, 1 ); ?>>
						Définir comme version par défaut
					</label>
				</p>

				<p>
					<input type="submit" class="button button-primary" name="update_version" value="Enregistrer les modifications" />
				</p>
			</form>
		</div>
		<?php
	}

	private function block_add_meta( $version_id ) {
		?>
		<div style="background:#fff;border-radius:10px;padding:24px;flex:1;min-width:340px;max-width:500px;box-shadow:0 2px 8px #e8e8e8">
			<h2 style="font-size:1.3em;margin-top:0">Modifier la Métadonnées</h2>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=usx_version_meta&version_id=' . intval( $version_id ) ) ); ?>" class="button button-secondary">📝 Modifier les métadonnées de la version</a></p>
		</div>
		<?php
	}

	private function block_add_book( $version_id ) {
		?>
		<div style="background:#f8f9fa;border-radius:10px;padding:24px;flex:1;min-width:340px;max-width:500px;box-shadow:0 2px 8px #e8e8e8">
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'usx_import_action', 'usx_import_nonce' ); ?>
				<input type="hidden" name="version_id" value="<?php echo esc_attr( $version_id ); ?>" />
				<h2 style="font-size:1.2em;margin-top:0">Ajouter un livre à cette version</h2>
				<p>
					<label style="font-weight:bold;font-size:1.05em;">Fichier USX à importer :</label><br>
					<input type="file" name="usx_file[]" accept=".usx,.xml,.txt,.csv" multiple required style="width:100%;" />
				</p>
				<p>
					<input type="submit" name="import_usx" class="button button-primary" value="Importer le livre" />
				</p>
			</form>
		</div>
		<?php
	}

	private function block_list_books( $version_id ) {
		global $wpdb;

		$t_books = $wpdb->prefix . 'usx_books';
		$t_ref   = $wpdb->prefix . 'usx_book_ref';

		$canon = $wpdb->get_results( "SELECT id, canon_code, canon_title FROM {$t_ref} ORDER BY id ASC" );

		$books = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.id, b.code, b.title, b.abreviation,
					b.chapters_count, b.verses_count, b.notes_count, b.titles_count, b.subtitles_count,
					r.id AS canon_order
				FROM {$t_books} AS b
				LEFT JOIN {$t_ref} AS r ON r.canon_code = b.code
				WHERE b.version_id = %d
				ORDER BY r.id, b.id",
				$version_id
			)
		);

		$canon_codes  = wp_list_pluck( $canon, 'canon_code' );
		$import_codes = wp_list_pluck( $books, 'code' );

		$missing = array_diff( $canon_codes, $import_codes );

		if ( ! empty( $missing ) ) {
			echo '<div class="notice notice-warning" style="padding:10px;margin-top:15px">';
			echo '<strong>Attention :</strong> certains livres du canon sont absents de cette version :<br><br>';

			foreach ( $canon as $ref ) {
				if ( in_array( $ref->canon_code, $missing, true ) ) {
					echo '• <strong>' . esc_html( $ref->canon_code ) . '</strong> – ' . esc_html( $ref->canon_title ) . '<br>';
				}
			}

			echo '</div>';
		}

		if ( ! $books ) {
			echo '<p><em>Aucun livre trouvé pour cette version.</em></p>';
			return;
		}

		echo '<h2 style="margin-top:2em">Livres importés dans cette version</h2>';
		echo '<table class="widefat striped" style="width:100%;max-width:100%">';
		echo '<thead><tr>';
		echo '<th>ID</th>';
		echo '<th>Code</th><th>Titre</th>';
		echo '<th>Abréviations</th>';
		echo '<th>Chapitres</th><th>Versets</th><th>Notes</th><th>Titres</th><th>Sous-titres</th><th>Actions</th>';
		echo '</tr></thead><tbody>';

		foreach ( $books as $book ) {
			echo '<tr>';
			echo '<td>' . esc_html( $book->id ) . '</td>';
			echo '<td>' . esc_html( $book->code ) . '</td>';
			echo '<td>' . esc_html( $book->title ) . '</td>';

			echo '<td>';
			echo '<form method="post" style="display:flex;gap:8px;align-items:center">';
			wp_nonce_field( 'update_abreviation_' . $book->id, 'update_abreviation_nonce' );
			echo '<input type="hidden" name="update_abreviation" value="1">';
			echo '<input type="hidden" name="book_id" value="' . esc_attr( $book->id ) . '">';
			echo '<input type="text" name="abreviation" value="' . esc_attr( $book->abreviation ?? '' ) . '" placeholder="exemple : GE;GEN;GENE" style="min-width:240px;width:100%;">';
			echo '<button type="submit" class="button button-secondary">Enregistrer</button>';
			echo '</form>';
			echo '</td>';

			echo '<td>' . intval( $book->chapters_count ) . '</td>';
			echo '<td>' . intval( $book->verses_count ) . '</td>';
			echo '<td>' . intval( $book->notes_count ) . '</td>';
			echo '<td>' . intval( $book->titles_count ) . '</td>';
			echo '<td>' . intval( $book->subtitles_count ) . '</td>';

			echo '<td>';
			echo '<form method="post" style="display:inline-block;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer ce livre et toutes ses données associées ?\');">';
			wp_nonce_field( 'delete_book_' . $book->id, 'delete_book_nonce' );
			echo '<input type="hidden" name="delete_book_id" value="' . esc_attr( $book->id ) . '">';
			submit_button( 'Supprimer', 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function handle_update_abreviation( $version_id ) {
		if ( empty( $_POST['update_abreviation'] ) ) {
			return;
		}
		if ( empty( $_POST['book_id'] ) ) {
			$this->show_error( 'Livre manquant pour la mise à jour des abréviations.' );
			return;
		}
		$book_id = (int) $_POST['book_id'];

		if ( ! isset( $_POST['update_abreviation_nonce'] ) || ! wp_verify_nonce( $_POST['update_abreviation_nonce'], 'update_abreviation_' . $book_id ) ) {
			$this->show_error( 'Échec de sécurité (nonce).' );
			return;
		}

		$raw        = isset( $_POST['abreviation'] ) ? wp_unslash( $_POST['abreviation'] ) : '';
		$normalized = $this->normalize_abreviation( $raw );

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'usx_books',
			[ 'abreviation' => $normalized ],
			[ 'id' => $book_id, 'version_id' => $version_id ],
			[ '%s' ],
			[ '%d', '%d' ]
		);

		if ( $updated === false ) {
			$this->show_error( 'Erreur SQL lors de la mise à jour des abréviations.' );
		} else {
			$this->show_updated( 'Abréviations mises à jour : ' . esc_html( $normalized ) );
		}
	}

	/** Normalise une saisie d'abréviations en forme canonique ;AAA;BBB;CCC; */
	private function normalize_abreviation( string $raw ): string {
		$raw = str_replace( [ ',', '|', '/', '\\' ], ';', $raw );
		$raw = str_replace( [ '’', '\'', ' ' ], '', $raw );
		$raw = str_replace( '.', '', $raw );

		$parts = array_filter(
			array_map( 'trim', explode( ';', $raw ) ),
			static function ( $v ) {
				return $v !== '';
			}
		);

		$clean = [];
		foreach ( $parts as $p ) {
			$p = remove_accents( $p );
			$p = preg_replace( '/[^A-Za-z]/', '', $p );
			if ( $p === '' ) {
				continue;
			}
			$clean[] = strtoupper( $p );
		}

		$clean = array_values( array_unique( $clean ) );

		return $clean ? ';' . implode( ';', $clean ) . ';' : '';
	}

	public function show_error( $message ) {
		echo '<div class="error"><p>' . esc_html( $message ) . '</p></div>';
	}

	public function show_updated( $message ) {
		echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
	}

	public function show_import_summary( $stats ) {
		if ( ! is_array( $stats ) ) {
			$stats = [
				'versets'   => intval( $stats ),
				'titles'    => 0,
				'subtitles' => 0,
				'notes'     => 0,
			];
		}
		echo '<div class="updated"><p>';
		echo 'Importation réussie. ';
		echo 'Versets insérés&nbsp;: <strong>' . intval( $stats['versets'] ) . '</strong> — ';
		echo 'Titres&nbsp;: <strong>' . intval( $stats['titles'] ) . '</strong> — ';
		echo 'Sous-titres&nbsp;: <strong>' . intval( $stats['subtitles'] ) . '</strong> — ';
		echo 'Notes&nbsp;: <strong>' . intval( $stats['notes'] ) . '</strong>';
		echo '</p></div>';
	}
}
