<?php
/**
 * Classe de sécurité du thème Schilo.
 * Regroupe toutes les protections en un seul endroit.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Security {

    /**
     * Init — appelle toutes les méthodes de sécurité.
     */
    public static function init() {
        self::remove_version_hints();
        self::harden_headers();
        self::disable_xml_rpc();
        self::protect_login();
        self::disable_file_edit();
        self::remove_unnecessary_meta();
        self::protect_rest_api();
        self::sanitize_uploads();
    }

    /**
     * Supprimer les indices de version WordPress
     * (WordPress, plugins, thème) des URLs et du HTML.
     */
    public static function remove_version_hints() {
        // Supprimer ?ver= des assets
        add_filter( 'style_loader_src',  [ __CLASS__, '_strip_ver' ], 9999 );
        add_filter( 'script_loader_src', [ __CLASS__, '_strip_ver' ], 9999 );
        // Supprimer la version du flux RSS (le remove_action wp_generator est dans remove_unnecessary_meta)
        add_filter( 'the_generator', '__return_empty_string' );
    }

    public static function _strip_ver( $src ) {
        if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    /**
     * Ajouter des headers de sécurité HTTP.
     */
    public static function harden_headers() {
        add_action( 'send_headers', function () {
            if ( headers_sent() ) return;

            // Protection clickjacking
            header( 'X-Frame-Options: SAMEORIGIN' );

            // Empêcher le sniffing MIME
            header( 'X-Content-Type-Options: nosniff' );

            // Protection XSS legacy (navigateurs anciens)
            header( 'X-XSS-Protection: 1; mode=block' );

            // Referrer policy
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );

            // Permissions policy (désactiver APIs inutiles)
            header( 'Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()' );

            // HSTS — activer uniquement en HTTPS (évite boucle sur localhost)
            if ( is_ssl() ) {
                header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
            }

            // Content-Security-Policy
            // Sources autorisées pour ce thème :
            //   - Google Fonts (fonts.googleapis.com + fonts.gstatic.com)
            //   - jsDelivr (Tabler Icons webfont)
            //   - GTranslate (translate.googleapis.com + translate-pa.googleapis.com)
            $csp = implode( '; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' translate.google.com translate.googleapis.com //www.google.com",
                "style-src 'self' 'unsafe-inline' fonts.googleapis.com cdn.jsdelivr.net",
                "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net data:",
                "img-src 'self' data: https: blob:",
                "connect-src 'self' translate.googleapis.com translate-pa.googleapis.com",
                "frame-src 'self' translate.google.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "upgrade-insecure-requests",
            ] );
            header( 'Content-Security-Policy: ' . $csp );
        } );
    }

    /**
     * Désactiver XML-RPC (vecteur d'attaque fréquent).
     */
    public static function disable_xml_rpc() {
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'wp_headers', function ( $headers ) {
            unset( $headers['X-Pingback'] );
            return $headers;
        } );
        // rsd_link et wlwmanifest_link sont supprimés dans remove_unnecessary_meta()
    }

    /**
     * Masquer les erreurs de connexion (évite d'indiquer
     * si c'est l'identifiant ou le mot de passe qui est faux).
     */
    public static function protect_login() {
        add_filter( 'login_errors', function () {
            return __( 'Identifiants incorrects.', 'schilo' );
        } );
        // Désactiver les hints "Mot de passe oublié ?" qui révèlent les emails
        add_filter( 'lostpassword_errors', function ( $errors ) {
            if ( $errors->has_errors() ) {
                return new WP_Error( 'invalid_request', __( 'Si un compte est associé à cette adresse, vous recevrez un e-mail.', 'schilo' ) );
            }
            return $errors;
        } );
    }

    /**
     * Désactiver l'édition de fichiers depuis l'admin WordPress.
     */
    public static function disable_file_edit() {
        if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
            define( 'DISALLOW_FILE_EDIT', true );
        }
    }

    /**
     * Supprimer les meta inutiles du <head>
     * (réduire la surface d'information).
     */
    public static function remove_unnecessary_meta() {
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles',     'print_emoji_styles' );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'admin_print_styles',  'print_emoji_styles' );
    }

    /**
     * Protéger l'API REST — requérir l'authentification
     * pour les endpoints non publics.
     */
    public static function protect_rest_api() {
        add_filter( 'rest_authentication_errors', function ( $result ) {
            if ( ! empty( $result ) ) return $result;
            // Autoriser les requêtes authentifiées et les endpoints publics
            if ( ! is_user_logged_in() ) {
                $route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
                    ? $GLOBALS['wp']->query_vars['rest_route']
                    : '';
                // Bloquer l'accès à la liste des utilisateurs
                if ( strpos( $route, '/wp/v2/users' ) !== false ) {
                    return new WP_Error(
                        'rest_forbidden',
                        __( 'Accès non autorisé.', 'schilo' ),
                        [ 'status' => 403 ]
                    );
                }
            }
            return $result;
        } );
    }

    /**
     * Valider les types de fichiers uploadés.
     * Bloquer les extensions dangereuses.
     */
    public static function sanitize_uploads() {
        add_filter( 'upload_mimes', function ( $mimes ) {
            // Supprimer les types potentiellement dangereux
            unset( $mimes['swf'] );
            unset( $mimes['exe'] );
            unset( $mimes['php'] );
            unset( $mimes['js']  );
            unset( $mimes['htm'] );
            unset( $mimes['html'] );
            return $mimes;
        } );

        // Vérifier le vrai type MIME (pas seulement l'extension)
        add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
            if ( ! empty( $data['ext'] ) ) return $data;
            $filetype = wp_check_filetype( $filename );
            return [
                'ext'             => $filetype['ext'],
                'type'            => $filetype['type'],
                'proper_filename' => $data['proper_filename'],
            ];
        }, 10, 3 );
    }
}
