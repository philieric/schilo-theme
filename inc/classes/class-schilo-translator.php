<?php
/**
 * Fournisseur de traduction configurable (Google redirection, Microsoft
 * Translator, ou Google Cloud Translation).
 *
 * "google"       : redirection cote client vers le proxy translate.google.com
 *                  (gere entierement dans assets/js/schilo-lang.js, aucune
 *                  cle requise, mais quitte temporairement schilo.org).
 *
 * "microsoft"    : traduction du DOM sur place, via l'API Azure Translator.
 * "google_cloud" : traduction du DOM sur place, via l'API Google Cloud
 *                  Translation v2. Meme principe que Microsoft (texte par
 *                  texte, cote serveur), reste sur schilo.org.
 *
 * Pour les deux fournisseurs "sur place", la cle API n'est lue que cote
 * serveur (ici) et n'atteint jamais le navigateur — le front-end
 * (schilo-lang.js) n'appelle que admin-ajax.php.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Translator {

    const OPTION              = 'schilo_translator_config';
    const CACHE_TTL           = DAY_IN_SECONDS;
    const RATE_LIMIT_PER_HOUR = 60;
    const MAX_TEXTS           = 200;
    const MAX_CHARS           = 100000;
    const CHUNK_SIZE          = 80;

    const IN_PLACE_PROVIDERS = [ 'microsoft', 'google_cloud' ];

    /* Codes acceptes, alignes sur _config.langs dans assets/js/schilo-lang.js */
    const ALLOWED_LANGS = [ 'fr', 'en', 'es', 'de', 'pt', 'ar', 'zh-CN', 'ru', 'it', 'nl' ];

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'wp_ajax_schilo_test_translator', [ __CLASS__, 'ajax_test_translator' ] );
        add_action( 'wp_ajax_schilo_translate',        [ __CLASS__, 'ajax_translate' ] );
        add_action( 'wp_ajax_nopriv_schilo_translate', [ __CLASS__, 'ajax_translate' ] );
    }

    /**
     * Configuration active, avec valeurs par defaut.
     */
    public static function get_config(): array {
        $config = get_option( self::OPTION, [] );
        if ( ! is_array( $config ) ) $config = [];

        return array_merge( [
            'active_provider'  => 'google',
            'selector_enabled' => true,
            'microsoft'        => [ 'api_key' => '', 'region' => '', 'enabled' => false ],
            'google_cloud'     => [ 'api_key' => '', 'enabled' => false ],
        ], $config );
    }

    /**
     * Le fournisseur actif est-il un fournisseur "sur place" correctement
     * configure et active ?
     */
    public static function is_in_place_ready( array $config ): bool {
        $provider = $config['active_provider'] ?? 'google';
        if ( ! in_array( $provider, self::IN_PLACE_PROVIDERS, true ) ) return false;

        $provider_config = $config[ $provider ] ?? [];
        if ( empty( $provider_config['enabled'] ) || empty( $provider_config['api_key'] ) ) return false;
        if ( $provider === 'microsoft' && empty( $provider_config['region'] ) ) return false;

        return true;
    }

    /* ────────────────────────────────────────────
       ADMIN : page de reglages
    ──────────────────────────────────────────── */

    public static function add_admin_menu(): void {
        add_submenu_page(
            'schilo-builder',
            'Traduction',
            'Traduction',
            'manage_options',
            'schilo-builder-translator',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $config = self::get_config();
        $saved  = false;

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset( $_POST['schilo_translator_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['schilo_translator_nonce'] ) ), 'schilo_save_translator_config' )
            && current_user_can( 'manage_options' )
        ) {
            $prev = $config;

            $active_provider = in_array( $_POST['st_active_provider'] ?? '', [ 'google', 'microsoft', 'google_cloud' ], true )
                ? $_POST['st_active_provider']
                : 'google';

            $ms_key_raw = isset( $_POST['st_microsoft_key'] ) ? sanitize_text_field( wp_unslash( $_POST['st_microsoft_key'] ) ) : '';
            $ms_api_key = ( $ms_key_raw !== '' && strpos( $ms_key_raw, '*' ) === false )
                ? $ms_key_raw
                : ( $prev['microsoft']['api_key'] ?? '' );
            $ms_region  = isset( $_POST['st_microsoft_region'] ) ? sanitize_text_field( wp_unslash( $_POST['st_microsoft_region'] ) ) : ( $prev['microsoft']['region'] ?? '' );
            $ms_enabled = ! empty( $_POST['st_microsoft_enabled'] );

            $gc_key_raw = isset( $_POST['st_google_cloud_key'] ) ? sanitize_text_field( wp_unslash( $_POST['st_google_cloud_key'] ) ) : '';
            $gc_api_key = ( $gc_key_raw !== '' && strpos( $gc_key_raw, '*' ) === false )
                ? $gc_key_raw
                : ( $prev['google_cloud']['api_key'] ?? '' );
            $gc_enabled = ! empty( $_POST['st_google_cloud_enabled'] );
            $selector_enabled = ! empty( $_POST['st_selector_enabled'] );

            $config = [
                'active_provider'  => $active_provider,
                'selector_enabled' => $selector_enabled,
                'microsoft'        => [
                    'api_key' => $ms_api_key,
                    'region'  => $ms_region,
                    'enabled' => $ms_enabled,
                ],
                'google_cloud'     => [
                    'api_key' => $gc_api_key,
                    'enabled' => $gc_enabled,
                ],
            ];
            update_option( self::OPTION, $config, false );
            $saved = true;
        }

        include SCHILO_DIR . '/inc/views/admin/translator-page.php';
    }

    /* ────────────────────────────────────────────
       AJAX : test de connexion (admin uniquement)
    ──────────────────────────────────────────── */

    public static function ajax_test_translator(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Non autorisé.' ], 403 );
        }
        check_ajax_referer( 'schilo_test_translator', 'nonce' );

        $config   = self::get_config();
        $provider = in_array( $_POST['provider'] ?? '', self::IN_PLACE_PROVIDERS, true ) ? $_POST['provider'] : 'microsoft';
        $key_raw  = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        $api_key  = ( $key_raw !== '' && $key_raw !== '__USE_SAVED__' ) ? $key_raw : ( $config[ $provider ]['api_key'] ?? '' );

        if ( $provider === 'microsoft' ) {
            $region_raw = sanitize_text_field( wp_unslash( $_POST['region'] ?? '' ) );
            $region     = $region_raw !== '' ? $region_raw : ( $config['microsoft']['region'] ?? '' );

            if ( $api_key === '' || $region === '' ) {
                wp_send_json_error( [ 'message' => 'Clé API et région requises.' ] );
            }
            $result = self::call_microsoft( [ 'test' ], 'fr', $api_key, $region );
        } else {
            if ( $api_key === '' ) {
                wp_send_json_error( [ 'message' => 'Clé API requise.' ] );
            }
            $result = self::call_google_cloud( [ 'test' ], 'fr', $api_key );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Connexion réussie.' ] );
    }

    /* ────────────────────────────────────────────
       AJAX : proxy de traduction (public)
    ──────────────────────────────────────────── */

    /**
     * Appelé par n'importe quel visiteur via le sélecteur de langue du site
     * (schilo-lang.js). Volontairement sans gate current_user_can() — c'est
     * une fonctionnalité front-end publique, protégée par nonce + validation
     * d'entrée + rate-limit par IP + cache, plutôt que par capacité WP
     * (même logique que wp_ajax_nopriv_schilo_search_suggest).
     */
    public static function ajax_translate(): void {
        check_ajax_referer( 'schilo_translate', 'nonce' );

        $config = self::get_config();
        if ( ! self::is_in_place_ready( $config ) ) {
            wp_send_json_error( [ 'message' => 'Traduction non configurée.' ] );
        }
        $provider = $config['active_provider'];

        $target_lang = sanitize_text_field( wp_unslash( $_POST['target_lang'] ?? '' ) );
        if ( ! in_array( $target_lang, self::ALLOWED_LANGS, true ) ) {
            wp_send_json_error( [ 'message' => 'Langue non prise en charge.' ] );
        }

        $texts = isset( $_POST['texts'] ) && is_array( $_POST['texts'] )
            ? array_map( 'sanitize_textarea_field', wp_unslash( $_POST['texts'] ) )
            : [];

        if ( empty( $texts ) || count( $texts ) > self::MAX_TEXTS ) {
            wp_send_json_error( [ 'message' => 'Requête invalide.' ] );
        }
        if ( array_sum( array_map( 'strlen', $texts ) ) > self::MAX_CHARS ) {
            wp_send_json_error( [ 'message' => 'Contenu trop volumineux.' ] );
        }

        if ( ! self::rate_limit_ok() ) {
            wp_send_json_error( [ 'message' => 'Trop de requêtes, réessayez plus tard.' ], 429 );
        }

        $cache_key = 'schilo_tr_' . $provider . '_' . md5( $target_lang . '|' . implode( "\x1F", $texts ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            wp_send_json_success( [ 'translations' => $cached, 'cached' => true ] );
        }

        $result = ( $provider === 'microsoft' )
            ? self::call_microsoft( $texts, $target_lang, $config['microsoft']['api_key'], $config['microsoft']['region'] )
            : self::call_google_cloud( $texts, $target_lang, $config['google_cloud']['api_key'] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        set_transient( $cache_key, $result, self::CACHE_TTL );
        wp_send_json_success( [ 'translations' => $result, 'cached' => false ] );
    }

    /* ────────────────────────────────────────────
       Appel Microsoft Translator (server-side)
    ──────────────────────────────────────────── */

    /**
     * @param string[] $texts
     * @return string[]|WP_Error  Traductions dans le meme ordre que $texts.
     */
    private static function call_microsoft( array $texts, string $lang, string $api_key, string $region ) {
        $translations = [];

        foreach ( array_chunk( $texts, self::CHUNK_SIZE ) as $chunk ) {
            $body = array_map( function ( $t ) { return [ 'Text' => $t ]; }, $chunk );

            $response = wp_remote_post(
                'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&to=' . rawurlencode( $lang ),
                [
                    'headers' => [
                        'Ocp-Apim-Subscription-Key'    => $api_key,
                        'Ocp-Apim-Subscription-Region' => $region,
                        'Content-Type'                 => 'application/json',
                    ],
                    'body'    => wp_json_encode( $body ),
                    'timeout' => 20,
                ]
            );

            if ( is_wp_error( $response ) ) return $response;

            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $code !== 200 || ! is_array( $data ) ) {
                $msg = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : 'Erreur Microsoft Translator (HTTP ' . $code . ').';
                return new WP_Error( 'schilo_translator_error', $msg );
            }

            foreach ( $data as $item ) {
                $translations[] = $item['translations'][0]['text'] ?? '';
            }
        }

        return $translations;
    }

    /* ────────────────────────────────────────────
       Appel Google Cloud Translation API v2 (server-side)
    ──────────────────────────────────────────── */

    /**
     * @param string[] $texts
     * @return string[]|WP_Error  Traductions dans le meme ordre que $texts.
     */
    private static function call_google_cloud( array $texts, string $lang, string $api_key ) {
        $translations = [];

        foreach ( array_chunk( $texts, self::CHUNK_SIZE ) as $chunk ) {
            $response = wp_remote_post(
                'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode( $api_key ),
                [
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'body'    => wp_json_encode( [
                        'q'      => array_values( $chunk ),
                        'target' => $lang,
                        'source' => 'fr',
                        'format' => 'text',
                    ] ),
                    'timeout' => 20,
                ]
            );

            if ( is_wp_error( $response ) ) return $response;

            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $code !== 200 || ! isset( $data['data']['translations'] ) ) {
                $msg = $data['error']['message'] ?? 'Erreur Google Cloud Translation (HTTP ' . $code . ').';
                return new WP_Error( 'schilo_translator_error', $msg );
            }

            foreach ( $data['data']['translations'] as $item ) {
                $translations[] = $item['translatedText'] ?? '';
            }
        }

        return $translations;
    }

    /* ────────────────────────────────────────────
       Rate-limit par IP (anonymisee, fenetre glissante horaire)
    ──────────────────────────────────────────── */

    private static function rate_limit_ok(): bool {
        $key   = 'schilo_tr_rl_' . self::get_ip_hash() . '_' . gmdate( 'YmdH' );
        $count = (int) get_transient( $key );

        if ( $count >= self::RATE_LIMIT_PER_HOUR ) return false;

        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        return true;
    }

    private static function get_ip_hash(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
        return hash( 'sha256', $ip . gmdate( 'YmdH' ) );
    }
}
