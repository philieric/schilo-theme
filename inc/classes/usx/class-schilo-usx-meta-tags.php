<?php
/**
 * Port de USX_Meta_Tags (plugin Usx-import, Service/class-usx-meta-tags.php)
 * — injecte des balises <meta> de métadonnées de la version biblique par
 * défaut (langue, licence, copyright, traducteur...) sur toutes les pages.
 * Rendu public, indépendant des autres classes usx/ — porté séparément.
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Meta_Tags {

	/** Active l'injection <meta> dans <head> */
	public static function init() {
		add_action( 'wp_head', [ __CLASS__, 'output_meta_tags' ], 20 );
	}

	/** Écrit les balises <meta ...> dans le <head> */
	public static function output_meta_tags() {
		$meta = self::get_current_version_meta();
		if ( empty( $meta ) ) {
			return;
		}

		foreach ( $meta as $key => $value ) {
			if ( $value === '' || $value === null ) {
				continue;
			}
			$name = sanitize_key( $key );

			printf(
				"<meta name=\"%s\" content=\"%s\" />\n",
				esc_attr( $name ),
				esc_attr( wp_strip_all_tags( (string) $value ) )
			);
		}
	}

	/**
	 * Retourne un tableau associatif de métadonnées fusionnées pour la
	 * version courante (par défaut, ou la plus ancienne si aucune par
	 * défaut) : colonnes de wp_usx_version_meta + paires (meta_key,
	 * meta_value) de wp_usx_version_meta_extra (extra écrase meta si même clé).
	 */
	public static function get_current_version_meta(): array {
		global $wpdb;

		$t_versions = $wpdb->prefix . 'usx_versions';
		$t_meta     = $wpdb->prefix . 'usx_version_meta';
		$t_extra    = $wpdb->prefix . 'usx_version_meta_extra';

		$version = $wpdb->get_row( "SELECT * FROM {$t_versions} WHERE is_default = 1 LIMIT 1" );
		if ( ! $version ) {
			$version = $wpdb->get_row( "SELECT * FROM {$t_versions} ORDER BY id ASC LIMIT 1" );
		}
		if ( ! $version ) {
			return [];
		}
		$version_id = (int) $version->id;

		$out = [];

		$base = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$t_meta} WHERE version_id = %d LIMIT 1", $version_id ),
			ARRAY_A
		);
		if ( is_array( $base ) ) {
			unset( $base['id'], $base['version_id'], $base['created_at'], $base['updated_at'] );
			foreach ( $base as $k => $v ) {
				if ( $v === null || $v === '' ) {
					continue;
				}
				$out[ trim( (string) $k ) ] = $v;
			}
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT meta_key, meta_value FROM {$t_extra} WHERE version_id = %d", $version_id ),
			ARRAY_A
		);
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$k = isset( $r['meta_key'] ) ? trim( (string) $r['meta_key'] ) : '';
				$v = isset( $r['meta_value'] ) ? $r['meta_value'] : '';
				if ( $k === '' || $v === '' || $v === null ) {
					continue;
				}
				$out[ $k ] = $v;
			}
		}

		$out['version_id']   = $version_id;
		$out['version_code'] = $version->code ?? '';
		$out['version_name'] = $version->name ?? '';
		$out['version_lang'] = $version->language ?? '';

		return $out;
	}
}
