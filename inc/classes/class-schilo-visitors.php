<?php
/**
 * Compteur de visiteurs uniques quotidiens.
 *
 * Stockage :
 *   - schilo_visitors_total  (wp_options) — total cumulatif persistant en base
 *   - transient schilo_vips_YYYY-MM-DD    — IPs hashées du jour (expire à J+1)
 *
 * Un cron quotidien purge les transients de plus de 7 jours.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Visitors {

    const OPT_TOTAL  = 'schilo_visitors_total';
    const OPT_PREFIX = 'schilo_vips_';
    const CRON_HOOK  = 'schilo_visitors_cleanup';

    // ── Initialisation ───────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp',               [ __CLASS__, 'record' ] );
        add_action( self::CRON_HOOK,    [ __CLASS__, 'cleanup' ] );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', self::CRON_HOOK );
        }
    }

    // ── Enregistrement d'une visite ──────────────────────────────────

    public static function record(): void {
        if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return;
        }

        $ip  = self::get_ip_hash();
        $key = self::OPT_PREFIX . gmdate( 'Y-m-d' );

        $ips = get_transient( $key );
        if ( ! is_array( $ips ) ) {
            $ips = [];
        }

        if ( in_array( $ip, $ips, true ) ) {
            return; // Déjà comptabilisé aujourd'hui
        }

        $ips[] = $ip;
        set_transient( $key, $ips, DAY_IN_SECONDS + HOUR_IN_SECONDS );

        // Incrémenter le total persistant en base (autoload désactivé)
        $total = (int) get_option( self::OPT_TOTAL, 0 );
        update_option( self::OPT_TOTAL, $total + 1, false );
    }

    // ── Lecture ──────────────────────────────────────────────────────

    public static function get_total(): int {
        return (int) get_option( self::OPT_TOTAL, 0 );
    }

    public static function get_today(): int {
        $ips = get_transient( self::OPT_PREFIX . gmdate( 'Y-m-d' ) );
        return is_array( $ips ) ? count( $ips ) : 0;
    }

    // ── Nettoyage cron (quotidien) ───────────────────────────────────

    public static function cleanup(): void {
        global $wpdb;

        $cutoff = '_transient_' . self::OPT_PREFIX . gmdate( 'Y-m-d', strtotime( '-7 days' ) );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                  WHERE option_name LIKE %s
                    AND option_name < %s",
                $wpdb->esc_like( '_transient_' . self::OPT_PREFIX ) . '%',
                $cutoff
            )
        );

        // Supprimer aussi les timeouts orphelins
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                  WHERE option_name LIKE %s
                    AND option_name < %s",
                $wpdb->esc_like( '_transient_timeout_' . self::OPT_PREFIX ) . '%',
                '_transient_timeout_' . self::OPT_PREFIX . gmdate( 'Y-m-d', strtotime( '-7 days' ) )
            )
        );
    }

    // ── IP hashée (anonymisation RGPD) ──────────────────────────────

    private static function get_ip_hash(): string {
        $ip = '0.0.0.0';

        // Proxies de confiance en premier
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ] as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
                break;
            }
        }

        if ( $ip === '0.0.0.0' && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Sel quotidien → impossible de retrouver l'IP, pas de tracking cross-jours
        return hash( 'sha256', $ip . gmdate( 'Y-m-d' ) );
    }
}
