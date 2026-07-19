<?php
/**
 * Bootstrap du portage USX (voir inc/classes/usx/) : quand le plugin
 * Usx-import est désactivé, le thème prend le relais pour le rendu
 * public des références bibliques ([b]/[bib]/[bvc]/[brc]/[bnv],
 * boutons de version, popup) — logique lue en direct dans les tables
 * wp_usx_* du plugin, sans dépendre de son code PHP.
 *
 * Si le plugin est actif, cette classe ne fait rien : il garde la main
 * comme avant, aucun risque de double enregistrement des shortcodes/AJAX.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Usx_Integration {

	const PLUGIN_BASENAME = 'Usx-import/usx-import.php';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_bootstrap' ] );
	}

	public static function maybe_bootstrap(): void {
		if ( self::is_plugin_active() ) {
			return;
		}

		$dir = SCHILO_DIR . '/inc/classes/usx/';
		require_once $dir . 'class-schilo-usx-bible-lookup.php';
		require_once $dir . 'class-schilo-usx-shortcodes.php';
		require_once $dir . 'class-schilo-usx-version-switcher.php';
		require_once $dir . 'class-schilo-usx-popup.php';

		Schilo_Usx_Shortcodes::register();

		// Les deux switchers s'enregistrent eux-mêmes (AJAX, wp_footer,
		// do_shortcode_tag) dans leur constructeur — même pattern que le
		// plugin d'origine (USX_Plugin::init()).
		Schilo_Usx_Version_Switcher_Buttons::instance();
		new Schilo_Usx_Version_Switcher_Global();
	}

	/**
	 * Vérifie l'activation sans dépendre de wp-admin/includes/plugin.php
	 * (non chargé en front) — même convention que le reste du thème pour
	 * détecter les plugins tiers.
	 */
	private static function is_plugin_active(): bool {
		$active = (array) get_option( 'active_plugins', [] );
		if ( in_array( self::PLUGIN_BASENAME, $active, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_active = (array) get_site_option( 'active_sitewide_plugins', [] );
			if ( isset( $network_active[ self::PLUGIN_BASENAME ] ) ) {
				return true;
			}
		}

		return false;
	}
}
