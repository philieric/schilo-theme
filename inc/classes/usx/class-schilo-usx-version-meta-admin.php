<?php
/**
 * Port de Usx_Version_Meta_Admin (plugin Usx-import,
 * class_Usx_Version_Meta_Admin.php) — écran "Métadonnées de la version"
 * (10 champs structurés + paires clé/valeur libres).
 *
 * Note : parent slug 'usx_versions' ne correspond à aucun menu top-level
 * réel (comportement d'origine conservé à l'identique) — l'écran
 * n'apparaît donc pas dans la barre latérale, seulement via le lien
 * direct depuis Schilo_Usx_Display_Admin::block_add_meta().
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Version_Meta_Admin {

	private $plugin_slug = 'usx_version_meta';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'usx_versions',
			'Métadonnées de la version',
			'Métadonnées',
			'manage_options',
			$this->plugin_slug,
			[ $this, 'render_meta_page' ]
		);
	}

	public function render_meta_page() {
		global $wpdb;
		$version_id = isset( $_GET['version_id'] ) ? (int) $_GET['version_id'] : 0;
		if ( ! $version_id ) {
			$this->render_error( 'Identifiant de version manquant.' );
			return;
		}

		$prefix  = $wpdb->prefix;
		$version = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}usx_versions WHERE id = %d", $version_id ) );
		$meta    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}usx_version_meta WHERE version_id = %d", $version_id ) );
		$extra   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$prefix}usx_version_meta_extra WHERE version_id = %d", $version_id ) );

		$this->render_meta_form( $version_id, $version, $meta, $extra );
	}

	private function render_error( $message ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}

	private function render_meta_form( $version_id, $version, $meta, $extra ) {
		?>
		<p><a href="<?php echo admin_url( 'admin.php?page=usx-importer-edit&version_id=' . intval( $version_id ) ); ?>" class="button">Retour à l'édition de la version</a></p>
		<div class="wrap">
			<h1>Métadonnées de la version : <?php echo esc_html( $version->code . ' – ' . $version->name ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'update_version_meta_' . $version_id, 'version_meta_nonce' ); ?>
				<input type="hidden" name="version_id" value="<?php echo esc_attr( $version_id ); ?>">

				<h2>Données standard</h2>
				<table class="form-table">
					<?php
					$fields = [
						'language'         => 'Langue',
						'iso_language'     => 'Code ISO',
						'abbreviation'     => 'Abréviation',
						'full_name'        => 'Nom complet',
						'publication_year' => 'Année de publication',
						'source_url'       => 'Source URL',
						'license'          => 'Licence',
						'copyright'        => 'Copyright',
						'translator_name'  => 'Traducteur(s)',
						'notes'            => 'Notes',
					];

					foreach ( $fields as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<?php if ( in_array( $key, [ 'notes', 'source_url', 'copyright' ], true ) ) : ?>
									<textarea name="<?php echo esc_attr( $key ); ?>" rows="4" style="width: 60%;"><?php echo esc_textarea( $meta->$key ?? '' ); ?></textarea>
								<?php else : ?>
									<input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $meta->$key ?? '' ); ?>" style="width: 60%;">
								<?php endif; ?>
							</td>
						</tr>
						<?php
					endforeach;
					?>
				</table>

				<h2>Données personnalisées</h2>
				<table id="meta-extra-table" class="form-table" style="width: 100%;">
					<colgroup>
						<col style="width: 15%;">
						<col style="width: 70%;">
						<col style="width: 15%;">
					</colgroup>
					<thead>
						<tr>
							<th>Clé</th>
							<th>Valeur</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $extra ) : foreach ( $extra as $row ) : ?>
							<tr>
								<td>
									<input type="text" name="extra_keys[]" value="<?php echo esc_attr( $row->meta_key ); ?>" placeholder="Clé">
								</td>
								<td>
									<textarea name="extra_values[]" rows="2" placeholder="Valeur" style="width: 100%;"><?php echo esc_textarea( $row->meta_value ); ?></textarea>
								</td>
								<td>
									<button type="button" class="button delete-meta-row" onclick="deleteMetaRow(this)">Supprimer</button>
								</td>
							</tr>
							<?php endforeach;
						endif;
						?>
						<tr>
							<td><input type="text" name="extra_keys[]" placeholder="Nouvelle clé"></td>
							<td><textarea name="extra_values[]" placeholder="Nouvelle valeur" rows="2" style="width: 100%;"></textarea></td>
							<td></td>
						</tr>
					</tbody>
				</table>
				<p><button type="button" class="button" onclick="addMetaRow()">Ajouter une ligne</button></p>

				<p><input type="submit" name="submit_meta" class="button button-primary" value="Enregistrer les métadonnées"></p>
			</form>

			<script>
				function addMetaRow() {
					const table = document.getElementById("meta-extra-table");
					const row = table.insertRow(-1);
					row.innerHTML = `
						<td><input type="text" name="extra_keys[]" placeholder="Nouvelle clé"></td>
						<td><textarea name="extra_values[]" placeholder="Nouvelle valeur" rows="2" style="width: 100%;"></textarea></td>
						<td></td>
					`;
				}

				function deleteMetaRow(button) {
					const row = button.closest("tr");
					row.remove();
				}
			</script>
		</div>
		<?php
	}

	public function handle_form_submission() {
		if ( ! isset( $_POST['submit_meta'], $_POST['version_id'] ) ) {
			return;
		}

		$version_id = (int) $_POST['version_id'];
		if ( ! wp_verify_nonce( $_POST['version_meta_nonce'], 'update_version_meta_' . $version_id ) ) {
			echo '<div class="notice notice-error"><p>Échec de vérification de sécurité.</p></div>';
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		$standard_fields = [
			'language', 'iso_language', 'abbreviation', 'full_name',
			'publication_year', 'source_url', 'license',
			'copyright', 'translator_name', 'notes',
		];

		$data = [ 'version_id' => $version_id, 'last_updated' => current_time( 'mysql' ) ];
		foreach ( $standard_fields as $field ) {
			$data[ $field ] = sanitize_text_field( $_POST[ $field ] ?? '' );
		}

		$wpdb->replace( "{$prefix}usx_version_meta", $data );

		$wpdb->delete( "{$prefix}usx_version_meta_extra", [ 'version_id' => $version_id ] );

		if ( ! empty( $_POST['extra_keys'] ) ) {
			foreach ( $_POST['extra_keys'] as $i => $key ) {
				$val = $_POST['extra_values'][ $i ] ?? '';
				if ( trim( $key ) !== '' ) {
					$wpdb->insert(
						"{$prefix}usx_version_meta_extra",
						[
							'version_id' => $version_id,
							'meta_key'   => sanitize_text_field( $key ),
							'meta_value' => sanitize_textarea_field( $val ),
						]
					);
				}
			}
		}

		echo '<div class="notice notice-success"><p>Métadonnées enregistrées avec succès.</p></div>';
	}
}
