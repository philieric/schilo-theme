<?php
/**
 * Port de USX_Table_Creator (plugin Usx-import, class-usx-table-creator.php)
 * — création/mise à jour des tables wp_usx_* via dbDelta() (idempotent).
 * Le thème n'a pas de hook d'« activation » comme un plugin : la création
 * est déclenchée sur admin_init, gardée par une option de version pour ne
 * pas rappeler dbDelta() à chaque requête admin — voir
 * Schilo_Usx_Table_Creator::maybe_upgrade() et class-schilo-usx-integration.php.
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Table_Creator {

	const DB_VERSION_OPTION = 'schilo_usx_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Crée/met à jour les tables si le schéma attendu a changé depuis la
	 * dernière fois (option comparée à DB_VERSION). Sans effet sinon —
	 * appelé sur chaque admin_init, doit rester bon marché dans le cas
	 * courant (une simple lecture d'option).
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::create_all_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Crée toutes les tables nécessaires (compatibles dbDelta).
	 */
	public static function create_all_tables( $with_seed = false ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables           = [];

		$tables[] = "CREATE TABLE {$wpdb->prefix}usx_versions (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(255) NOT NULL,
			code VARCHAR(20) NOT NULL UNIQUE,
			language VARCHAR(10) DEFAULT 'fr',
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}usx_version_meta (
			version_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
			language VARCHAR(10) DEFAULT '',
			iso_language VARCHAR(10) DEFAULT '',
			abbreviation VARCHAR(10) DEFAULT '',
			full_name VARCHAR(255) DEFAULT '',
			publication_year YEAR DEFAULT NULL,
			source_url TEXT,
			license VARCHAR(255) DEFAULT '',
			copyright VARCHAR(255) DEFAULT '',
			translator_name VARCHAR(255) DEFAULT '',
			notes TEXT,
			last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (version_id) REFERENCES {$wpdb->prefix}usx_versions(id) ON DELETE CASCADE
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}usx_version_meta_extra (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			version_id BIGINT UNSIGNED NOT NULL,
			meta_key VARCHAR(255) NOT NULL,
			meta_value TEXT,
			UNIQUE KEY unique_meta (version_id, meta_key(191)),
			FOREIGN KEY (version_id) REFERENCES {$wpdb->prefix}usx_versions(id) ON DELETE CASCADE
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}usx_books (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			version_id BIGINT UNSIGNED NOT NULL,
			code VARCHAR(10) NOT NULL,
			title VARCHAR(255),
			abreviation VARCHAR(255),
			chapters_count INT DEFAULT 0,
			verses_count INT DEFAULT 0,
			notes_count INT DEFAULT 0,
			titles_count INT DEFAULT 0,
			subtitles_count INT DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (version_id) REFERENCES {$wpdb->prefix}usx_versions(id) ON DELETE CASCADE
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}usx_chapters (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			book_id BIGINT UNSIGNED NOT NULL,
			number INT NOT NULL,
			title VARCHAR(255) DEFAULT NULL,
			sub_title VARCHAR(255) DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (book_id) REFERENCES {$wpdb->prefix}usx_books(id) ON DELETE CASCADE
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}usx_verses (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			chapter_id BIGINT UNSIGNED NOT NULL,
			verse INT NOT NULL,
			verse_text TEXT NOT NULL,
			section_order INT DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (chapter_id) REFERENCES {$wpdb->prefix}usx_chapters(id) ON DELETE CASCADE
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}usx_notes (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			verse_id BIGINT UNSIGNED NOT NULL,
			note_type VARCHAR(10) DEFAULT NULL,
			caller VARCHAR(10) DEFAULT NULL,
			content TEXT NOT NULL,
			references_json TEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (verse_id) REFERENCES {$wpdb->prefix}usx_verses(id) ON DELETE CASCADE
		) $charset_collate;";

		foreach ( $tables as $table ) {
			dbDelta( $table );
		}

		self::create_reference_tables();

		if ( $with_seed ) {
			self::seed_reference_min();
		}
	}

	/**
	 * Tables de référence globales (communes à toutes les versions) :
	 * canon des livres bibliques + leurs abréviations reconnues.
	 */
	public static function create_reference_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$t_ref            = $wpdb->prefix . 'usx_book_ref';
		$t_abbr           = $wpdb->prefix . 'usx_book_ref_abbr';

		$sql_ref = "CREATE TABLE {$t_ref} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			canon_code VARCHAR(16) NOT NULL,
			canon_title VARCHAR(255) NOT NULL,
			canon_title_norm VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_code (canon_code),
			KEY idx_title_norm (canon_title_norm)
		) {$charset_collate};";

		$sql_abbr = "CREATE TABLE {$t_abbr} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ref_id BIGINT UNSIGNED NOT NULL,
			abbr VARCHAR(64) NOT NULL,
			abbr_norm VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_global (abbr_norm),
			KEY idx_ref (ref_id)
		) {$charset_collate};";

		dbDelta( $sql_ref );
		dbDelta( $sql_abbr );
	}

	/**
	 * Seed de référence : 66 livres canoniques (AT+NT) + leurs abréviations
	 * françaises reconnues. Idempotent (UPSERT ref, INSERT IGNORE abbr).
	 */
	public static function seed_reference_min() {
		global $wpdb;

		self::create_reference_tables();

		$t_ref  = $wpdb->prefix . 'usx_book_ref';
		$t_abbr = $wpdb->prefix . 'usx_book_ref_abbr';

		$norm_title = function ( $s ) {
			$s = function_exists( 'remove_accents' ) ? remove_accents( $s ) : @iconv( 'UTF-8', 'ASCII//TRANSLIT', $s );
			$s = strtolower( $s );
			return trim( preg_replace( '/\s+/', ' ', $s ) );
		};
		$norm_abbr = function ( $s ) {
			$s = function_exists( 'remove_accents' ) ? remove_accents( $s ) : @iconv( 'UTF-8', 'ASCII//TRANSLIT', $s );
			return strtoupper( preg_replace( '/[^A-Z0-9]/', '', $s ) );
		};

		$items = [
			// Ancien Testament (39)
			[ 'GEN', 'Genèse', [ 'GE', 'GEN', 'GENE', 'GENES', 'GENESE' ] ],
			[ 'EXO', 'Exode', [ 'EX', 'EXO', 'EXOD', 'EXODE' ] ],
			[ 'LEV', 'Lévitique', [ 'LE', 'LEV', 'LEVI', 'LEVIT', 'LEVITIQ', 'LEVITIQUE', 'LV' ] ],
			[ 'NUM', 'Nombres', [ 'NO', 'NOM', 'NOMB', 'NOMBR', 'NOMBRES', 'NB' ] ],
			[ 'DEU', 'Deutéronome', [ 'DE', 'DEU', 'DEUT', 'DEUTERO', 'DEUTERONOME', 'DT' ] ],
			[ 'JOS', 'Josué', [ 'JOS', 'JOSU', 'JOSUE' ] ],
			[ 'JDG', 'Juges', [ 'JG', 'JUG', 'JUGES' ] ],
			[ 'RUT', 'Ruth', [ 'RU', 'RUT', 'RUTH', 'RT' ] ],
			[ '1SA', '1 Samuel', [ '1S', '1SA', '1SAM', '1SAMU', '1SAMUE', '1SAMUEL' ] ],
			[ '2SA', '2 Samuel', [ '2S', '2SA', '2SAM', '2SAMU', '2SAMUE', '2SAMUEL' ] ],
			[ '1KI', '1 Rois', [ '1R', '1RO', '1ROI', '1ROIS' ] ],
			[ '2KI', '2 Rois', [ '2R', '2RO', '2ROI', '2ROIS' ] ],
			[ '1CH', '1 Chroniques', [ '1C', '1CH', '1CHR', '1CHRO', '1CHRON', '1CHRONI', '1CHRONIQ', '1CHRONIQU', '1CHRONIQUES' ] ],
			[ '2CH', '2 Chroniques', [ '2C', '2CH', '2CHR', '2CHRO', '2CHRON', '2CHRONI', '2CHRONIQ', '2CHRONIQU', '2CHRONIQUES' ] ],
			[ 'EZR', 'Esdras', [ 'ESD', 'ESDR', 'ESDRA', 'ESDRAS' ] ],
			[ 'NEH', 'Néhémie', [ 'NE', 'NEH', 'NEHE', 'NEHEM', 'NEHEMI', 'NEHEMIE' ] ],
			[ 'EST', 'Esther', [ 'EST', 'ESTH', 'ESTHE', 'ESTHER' ] ],
			[ 'JOB', 'Job', [ 'JOB' ] ],
			[ 'PSA', 'Psaumes', [ 'PS', 'PSA', 'PSAU', 'PSAUM', 'PSAUME', 'PSAUMES' ] ],
			[ 'PRO', 'Proverbes', [ 'PR', 'PRO', 'PROV', 'PROVE', 'PROVER', 'PROVERB', 'PROVERBE', 'PROVERBES' ] ],
			[ 'ECC', 'Ecclésiaste', [ 'EC', 'ECC', 'ECCL', 'ECCLE', 'ECCLES', 'ECCLESI', 'ECCLESIA', 'ECCLESIAS', 'ECCLESIAST', 'ECCLESIASTE' ] ],
			[ 'SNG', 'Cantique des cantiques', [ 'CA', 'CAN', 'CANT', 'CANTI', 'CANTIQ', 'CANTIQU', 'CANTIQUES', 'CQ', 'CT' ] ],
			[ 'ISA', 'Ésaïe', [ 'ES', 'ESA', 'ESAI', 'ESAIE' ] ],
			[ 'JER', 'Jérémie', [ 'JE', 'JER', 'JERE', 'JEREM', 'JEREMI', 'JEREMIE' ] ],
			[ 'LAM', 'Lamentations', [ 'LA', 'LAM', 'LAME', 'LAMEN', 'LAMENT', 'LAMENTA', 'LAMENTAT', 'LAMENTATI', 'LAMENTATIO', 'LAMENTATIONS', 'LT' ] ],
			[ 'EZK', 'Ézéchiel', [ 'EZ', 'EZE', 'EZEC', 'EZECH', 'EZECHI', 'EZECHIE', 'EZECHIEL' ] ],
			[ 'DAN', 'Daniel', [ 'DA', 'DAN', 'DANI', 'DANIE', 'DANIEL' ] ],
			[ 'HOS', 'Osée', [ 'OS', 'OSE', 'OSEE' ] ],
			[ 'JOL', 'Joël', [ 'JOE', 'JOEL' ] ],
			[ 'AMO', 'Amos', [ 'AM', 'AMO', 'AMOS' ] ],
			[ 'OBA', 'Abdias', [ 'AB', 'ABD', 'ABDI', 'ABDIA', 'ABDIAS' ] ],
			[ 'JON', 'Jonas', [ 'JON', 'JONA', 'JONAS' ] ],
			[ 'MIC', 'Michée', [ 'MI', 'MIC', 'MICH', 'MICHE', 'MICHEE' ] ],
			[ 'NAM', 'Nahum', [ 'NA', 'NAH', 'NAHU', 'NAHUM' ] ],
			[ 'HAB', 'Habacuc', [ 'HA', 'HAB', 'HABA', 'HABAC', 'HABACU', 'HABACUC' ] ],
			[ 'ZEP', 'Sophonie', [ 'SO', 'SOP', 'SOPH', 'SOPHO', 'SOPHON', 'SOPHONI', 'SOPHONIE' ] ],
			[ 'HAG', 'Aggée', [ 'AG', 'AGG', 'AGGE', 'AGGEE' ] ],
			[ 'ZEC', 'Zacharie', [ 'ZA', 'ZAC', 'ZACH', 'ZACHA', 'ZACHAR', 'ZACHARI', 'ZACHARIE' ] ],
			[ 'MAL', 'Malachie', [ 'MAL', 'MALA', 'MALAC', 'MALACH', 'MALACHI', 'MALACHIE' ] ],
			// Nouveau Testament (27)
			[ 'MAT', 'Matthieu', [ 'MT', 'MAT', 'MATT', 'MATTH', 'MATTHI', 'MATTHIE', 'MATTHIEU' ] ],
			[ 'MRK', 'Marc', [ 'MC', 'MAR', 'MARC' ] ],
			[ 'LUK', 'Luc', [ 'LU', 'LUC', 'LC' ] ],
			[ 'JHN', 'Jean', [ 'JN', 'JEA', 'JEAN' ] ],
			[ 'ACT', 'Actes', [ 'AC', 'ACT', 'ACTE', 'ACTES' ] ],
			[ 'ROM', 'Romains', [ 'RO', 'ROM', 'ROMA', 'ROMAI', 'ROMAIN', 'ROMAINS' ] ],
			[ '1CO', '1 Corinthiens', [ '1CO', '1COR', '1CORI', '1CORIN', '1CORINT', '1CORINTH', '1CORINTHI', '1CORINTHIE', '1CORINTHIEN', '1CORINTHIENS' ] ],
			[ '2CO', '2 Corinthiens', [ '2CO', '2COR', '2CORI', '2CORIN', '2CORINT', '2CORINTH', '2CORINTHI', '2CORINTHIE', '2CORINTHIEN', '2CORINTHIENS' ] ],
			[ 'GAL', 'Galates', [ 'GA', 'GAL', 'GALA', 'GALAT', 'GALATE', 'GALATES' ] ],
			[ 'EPH', 'Éphésiens', [ 'EP', 'EPH', 'EPHE', 'EPHES', 'EPHESI', 'EPHESIE', 'EPHESIEN', 'EPHESIENS' ] ],
			[ 'PHP', 'Philippiens', [ 'PH', 'PHI', 'PHIL', 'PHILI', 'PHILIP', 'PHILIPP', 'PHILIPPI', 'PHILIPPIE', 'PHILIPPIEN', 'PHILIPPIENS' ] ],
			[ 'COL', 'Colossiens', [ 'COL', 'COLO', 'COLOS', 'COLOSSI', 'COLOSSIEN', 'COLOSSIENS' ] ],
			[ '1TH', '1 Thessaloniciens', [ '1TH', '1THES', '1THESS', '1THESSA', '1THESSAL', '1THESSALO', '1THESSALON', '1THESSALONI', '1THESSALONIC', '1THESSALONICI', '1THESSALONICIE', '1THESSALONICIEN', '1THESSALONICIENS' ] ],
			[ '2TH', '2 Thessaloniciens', [ '2TH', '2THES', '2THESS', '2THESSA', '2THESSAL', '2THESSALO', '2THESSALON', '2THESSALONI', '2THESSALONIC', '2THESSALONICI', '2THESSALONICIE', '2THESSALONICIEN', '2THESSALONICIENS' ] ],
			[ '1TI', '1 Timothée', [ '1TI', '1TIM', '1TIMO', '1TIMOT', '1TIMOTH', '1TIMOTHE', '1TIMOTHEE' ] ],
			[ '2TI', '2 Timothée', [ '2TI', '2TIM', '2TIMO', '2TIMOT', '2TIMOTH', '2TIMOTHE', '2TIMOTHEE' ] ],
			[ 'TIT', 'Tite', [ 'TIT', 'TITE' ] ],
			[ 'PHM', 'Philémon', [ 'PHM', 'PHIL', 'PHILE', 'PHILEM', 'PHILEMO', 'PHILEMON' ] ],
			[ 'HEB', 'Hébreux', [ 'HE', 'HEB', 'HEBR', 'HEBRE', 'HEBREU', 'HEBREUX' ] ],
			[ 'JAS', 'Jacques', [ 'JA', 'JAC', 'JACQ', 'JACQUE', 'JACQUES', 'JC' ] ],
			[ '1PE', '1 Pierre', [ '1PI', '1PIE', '1PIER', '1PIERR', '1PIERRE' ] ],
			[ '2PE', '2 Pierre', [ '2PI', '2PIE', '2PIER', '2PIERR', '2PIERRE' ] ],
			[ '1JN', '1 Jean', [ '1JN', '1JEA', '1JEAN' ] ],
			[ '2JN', '2 Jean', [ '2JN', '2JEA', '2JEAN' ] ],
			[ '3JN', '3 Jean', [ '3JN', '3JEA', '3JEAN' ] ],
			[ 'JUD', 'Jude', [ 'JUD', 'JUDE', 'JD' ] ],
			[ 'REV', 'Apocalypse', [ 'AP', 'APO', 'APOC', 'APOCA', 'APOCAL', 'APOCALY', 'APOCALYP', 'APOCALYPS', 'APOCALYPSE' ] ],
		];

		foreach ( $items as [ $code, $title, $abbrs ] ) {
			$ref = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$t_ref} WHERE canon_code = %s", $code ) );
			if ( ! $ref ) {
				$ok = $wpdb->insert(
					$t_ref,
					[
						'canon_code'       => $code,
						'canon_title'      => $title,
						'canon_title_norm' => $norm_title( $title ),
					],
					[ '%s', '%s', '%s' ]
				);

				if ( $ok === false ) {
					continue;
				}
				$ref_id = (int) $wpdb->insert_id;
			} else {
				$ref_id = (int) $ref->id;
			}

			foreach ( $abbrs as $abbr ) {
				$abbr_norm = $norm_abbr( $abbr );
				if ( $abbr_norm === '' ) {
					continue;
				}

				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$t_abbr} (ref_id, abbr, abbr_norm) VALUES (%d, %s, %s)",
						$ref_id,
						$abbr,
						$abbr_norm
					)
				);
			}
		}
	}
}
