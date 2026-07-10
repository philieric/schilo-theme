<?php
/**
 * Double authentification TOTP pour l'administration WordPress.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_OTP {

    private const OPTION_ENABLED = 'schilo_otp_enabled';
    private const OPTION_ROLES = 'schilo_otp_roles';
    private const META_SECRET = '_schilo_otp_secret';
    private const META_ENABLED = '_schilo_otp_enabled';
    private const TRANSIENT_PREFIX = 'schilo_otp_verified_';
    private const SECRET_LENGTH = 20;
    private const STEP = 30;
    private const DIGITS = 6;

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_page' ] );
        add_action( 'admin_post_schilo_otp_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_post_schilo_otp_save_user', [ __CLASS__, 'save_user_settings' ] );
        add_action( 'admin_init', [ __CLASS__, 'enforce_admin_otp' ], 1 );
        add_action( 'login_init', [ __CLASS__, 'handle_login_action' ] );
        add_action( 'wp_login', [ __CLASS__, 'clear_verified_session_on_login' ], 10, 2 );
        add_action( 'clear_auth_cookie', [ __CLASS__, 'clear_current_verified_session' ] );
    }

    public static function add_admin_page(): void {
        add_options_page(
            __( 'OTP administration', 'schilo' ),
            __( 'OTP administration', 'schilo' ),
            'manage_options',
            'schilo-otp',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    public static function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'schilo' ) );
        }

        $user = wp_get_current_user();
        $secret = self::get_or_create_user_secret( $user->ID );
        $enabled = self::is_user_otp_enabled( $user->ID );
        $global_enabled = self::is_globally_enabled();
        $roles = self::get_required_roles();
        $available_roles = wp_roles()->roles;
        $verify_url = wp_login_url( admin_url( 'options-general.php?page=schilo-otp' ) );
        ?>
        <div class="wrap schilo-otp-admin">
            <h1><?php esc_html_e( 'OTP administration', 'schilo' ); ?></h1>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible schilo-keep"><p><?php esc_html_e( 'Réglages enregistrés.', 'schilo' ); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width: 920px;">
                <h2><?php esc_html_e( 'Réglages généraux', 'schilo' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'schilo_otp_save_settings' ); ?>
                    <input type="hidden" name="action" value="schilo_otp_save_settings">

                    <p>
                        <label>
                            <input type="checkbox" name="schilo_otp_enabled" value="1" <?php checked( $global_enabled ); ?>>
                            <?php esc_html_e( 'Activer l’OTP pour l’administration', 'schilo' ); ?>
                        </label>
                    </p>

                    <p class="description">
                        <?php esc_html_e( 'Quand cette option est active, les comptes ciblés doivent valider un code à 6 chiffres après la connexion WordPress.', 'schilo' ); ?>
                    </p>

                    <fieldset>
                        <legend><strong><?php esc_html_e( 'Rôles concernés', 'schilo' ); ?></strong></legend>
                        <?php foreach ( $available_roles as $role_key => $role ) : ?>
                            <label style="display:block;margin:.35rem 0;">
                                <input type="checkbox" name="schilo_otp_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $roles, true ) ); ?>>
                                <?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer les réglages', 'schilo' ); ?></button>
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: 920px;">
                <h2><?php esc_html_e( 'Mon application OTP', 'schilo' ); ?></h2>
                <p><?php esc_html_e( 'Scanne ce QR code avec Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden ou une application compatible TOTP.', 'schilo' ); ?></p>

                <div id="schilo-otp-qrcode" data-otp-uri="<?php echo esc_attr( self::build_otpauth_uri( $user, $secret ) ); ?>" style="width:180px;height:180px;margin:1rem 0;"></div>
                <p>
                    <strong><?php esc_html_e( 'Clé manuelle :', 'schilo' ); ?></strong>
                    <code><?php echo esc_html( $secret ); ?></code>
                </p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'schilo_otp_save_user' ); ?>
                    <input type="hidden" name="action" value="schilo_otp_save_user">

                    <p>
                        <label for="schilo_otp_code"><strong><?php esc_html_e( 'Code de vérification', 'schilo' ); ?></strong></label><br>
                        <input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" name="schilo_otp_code" id="schilo_otp_code" class="regular-text" placeholder="123456">
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="schilo_otp_user_enabled" value="1" <?php checked( $enabled ); ?>>
                            <?php esc_html_e( 'Activer l’OTP sur mon compte', 'schilo' ); ?>
                        </label>
                    </p>

                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Valider et enregistrer', 'schilo' ); ?></button>
                        <button type="submit" name="schilo_otp_regenerate" value="1" class="button" onclick="return confirm('<?php echo esc_js( __( 'Regénérer la clé OTP ? L’ancienne application ne fonctionnera plus.', 'schilo' ) ); ?>');">
                            <?php esc_html_e( 'Regénérer la clé', 'schilo' ); ?>
                        </button>
                    </p>
                </form>

                <p class="description">
                    <?php
                    printf(
                        /* translators: %s is a wp-login.php URL. */
                        esc_html__( 'Pour tester le flux complet, déconnecte-toi puis reconnecte-toi depuis %s.', 'schilo' ),
                        '<a href="' . esc_url( $verify_url ) . '">' . esc_html__( 'la page de connexion', 'schilo' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var target = document.getElementById('schilo-otp-qrcode');
            if (!target || !window.QRCode) {
                return;
            }
            new QRCode(target, {
                text: target.getAttribute('data-otp-uri'),
                width: 180,
                height: 180,
                colorDark: '#1e2a3a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
        </script>
        <?php
    }

    public static function save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'schilo' ) );
        }
        check_admin_referer( 'schilo_otp_save_settings' );

        update_option( self::OPTION_ENABLED, ! empty( $_POST['schilo_otp_enabled'] ) ? '1' : '0', false );

        $roles = isset( $_POST['schilo_otp_roles'] ) && is_array( $_POST['schilo_otp_roles'] )
            ? array_map( 'sanitize_key', wp_unslash( $_POST['schilo_otp_roles'] ) )
            : [];
        $registered_roles = array_keys( wp_roles()->roles );
        $roles = array_values( array_intersect( $roles, $registered_roles ) );
        update_option( self::OPTION_ROLES, $roles ?: [ 'administrator' ], false );

        wp_safe_redirect( admin_url( 'options-general.php?page=schilo-otp&updated=1' ) );
        exit;
    }

    public static function save_user_settings(): void {
        if ( ! is_user_logged_in() ) {
            auth_redirect();
        }
        check_admin_referer( 'schilo_otp_save_user' );

        $user_id = get_current_user_id();

        if ( ! empty( $_POST['schilo_otp_regenerate'] ) ) {
            update_user_meta( $user_id, self::META_SECRET, self::generate_secret() );
            update_user_meta( $user_id, self::META_ENABLED, '0' );
            self::clear_verified_session( $user_id );
            wp_safe_redirect( admin_url( 'options-general.php?page=schilo-otp&updated=1' ) );
            exit;
        }

        $enable = ! empty( $_POST['schilo_otp_user_enabled'] );
        $code = isset( $_POST['schilo_otp_code'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['schilo_otp_code'] ) ) : '';
        $secret = self::get_or_create_user_secret( $user_id );

        if ( $enable && ! self::verify_totp( $secret, $code ) ) {
            wp_die( esc_html__( 'Code OTP invalide. Reviens en arrière et saisis le code actuel de ton application.', 'schilo' ) );
        }

        update_user_meta( $user_id, self::META_ENABLED, $enable ? '1' : '0' );
        if ( $enable ) {
            self::mark_verified( $user_id );
        } else {
            self::clear_verified_session( $user_id );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=schilo-otp&updated=1' ) );
        exit;
    }

    public static function enforce_admin_otp(): void {
        if ( self::is_disabled_by_constant() || ! self::is_globally_enabled() || ! is_user_logged_in() ) {
            return;
        }

        if ( ! self::user_requires_otp( wp_get_current_user() ) || self::is_current_session_verified() ) {
            return;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            wp_send_json_error( [ 'message' => __( 'Validation OTP requise.', 'schilo' ) ], 403 );
        }

        $action = self::is_user_otp_enabled( get_current_user_id() ) ? 'schilo_otp' : 'schilo_otp_setup';
        wp_safe_redirect( add_query_arg( 'action', $action, wp_login_url( admin_url() ) ) );
        exit;
    }

    public static function handle_login_action(): void {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
        if ( ! in_array( $action, [ 'schilo_otp', 'schilo_otp_setup' ], true ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( admin_url() ) );
            exit;
        }

        $user = wp_get_current_user();
        if ( self::is_disabled_by_constant() || ! self::is_globally_enabled() || ! self::user_requires_otp( $user ) ) {
            wp_safe_redirect( admin_url() );
            exit;
        }

        $secret = self::get_or_create_user_secret( $user->ID );
        $error = '';

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            check_admin_referer( 'schilo_otp_verify' );
            $code = isset( $_POST['schilo_otp_code'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['schilo_otp_code'] ) ) : '';

            if ( self::verify_totp( $secret, $code ) ) {
                update_user_meta( $user->ID, self::META_ENABLED, '1' );
                self::mark_verified( $user->ID );
                wp_safe_redirect( admin_url() );
                exit;
            }

            $error = __( 'Code OTP invalide ou expiré.', 'schilo' );
        }

        self::render_login_otp_page( $user, $secret, 'schilo_otp_setup' === $action, $error );
        exit;
    }

    public static function clear_verified_session_on_login( string $user_login, WP_User $user ): void {
        self::clear_verified_session( $user->ID );
    }

    public static function clear_current_verified_session(): void {
        if ( is_user_logged_in() ) {
            self::clear_verified_session( get_current_user_id() );
        }
    }

    private static function render_login_otp_page( WP_User $user, string $secret, bool $setup, string $error = '' ): void {
        login_header(
            $setup ? __( 'Configuration OTP', 'schilo' ) : __( 'Validation OTP', 'schilo' ),
            $error ? '<p class="message schilo-otp-error">' . esc_html( $error ) . '</p>' : ''
        );
        ?>
        <form name="schilo-otp-form" id="schilo-otp-form" action="<?php echo esc_url( add_query_arg( 'action', $setup ? 'schilo_otp_setup' : 'schilo_otp', wp_login_url( admin_url() ) ) ); ?>" method="post">
            <?php wp_nonce_field( 'schilo_otp_verify' ); ?>

            <?php if ( $setup ) : ?>
                <p class="schilo-otp-intro"><?php esc_html_e( 'Scanne le QR code avec ton application d’authentification, puis saisis le code généré.', 'schilo' ); ?></p>
                <div id="schilo-otp-qrcode" data-otp-uri="<?php echo esc_attr( self::build_otpauth_uri( $user, $secret ) ); ?>"></div>
                <p class="schilo-otp-secret">
                    <?php esc_html_e( 'Clé manuelle :', 'schilo' ); ?>
                    <code><?php echo esc_html( $secret ); ?></code>
                </p>
            <?php else : ?>
                <p class="schilo-otp-intro"><?php esc_html_e( 'Saisis le code à 6 chiffres de ton application d’authentification.', 'schilo' ); ?></p>
            <?php endif; ?>

            <p>
                <label for="schilo_otp_code"><?php esc_html_e( 'Code OTP', 'schilo' ); ?></label>
                <input type="text" name="schilo_otp_code" id="schilo_otp_code" class="input" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" required autofocus>
            </p>

            <p class="submit">
                <button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Valider', 'schilo' ); ?></button>
            </p>
        </form>

        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var target = document.getElementById('schilo-otp-qrcode');
            if (!target || !window.QRCode) {
                return;
            }
            new QRCode(target, {
                text: target.getAttribute('data-otp-uri'),
                width: 180,
                height: 180,
                colorDark: '#1e2a3a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
        </script>
        <?php
        login_footer();
    }

    private static function is_globally_enabled(): bool {
        return '1' === get_option( self::OPTION_ENABLED, '0' );
    }

    private static function get_required_roles(): array {
        $roles = get_option( self::OPTION_ROLES, [ 'administrator' ] );
        return is_array( $roles ) && $roles ? array_values( array_map( 'sanitize_key', $roles ) ) : [ 'administrator' ];
    }

    private static function user_requires_otp( WP_User $user ): bool {
        if ( ! $user->exists() ) {
            return false;
        }
        return (bool) array_intersect( self::get_required_roles(), (array) $user->roles );
    }

    private static function is_user_otp_enabled( int $user_id ): bool {
        return '1' === get_user_meta( $user_id, self::META_ENABLED, true );
    }

    private static function is_disabled_by_constant(): bool {
        return defined( 'SCHILO_OTP_DISABLE' ) && SCHILO_OTP_DISABLE;
    }

    private static function get_or_create_user_secret( int $user_id ): string {
        $secret = (string) get_user_meta( $user_id, self::META_SECRET, true );
        if ( '' === $secret ) {
            $secret = self::generate_secret();
            update_user_meta( $user_id, self::META_SECRET, $secret );
        }
        return $secret;
    }

    private static function generate_secret(): string {
        return self::base32_encode( random_bytes( self::SECRET_LENGTH ) );
    }

    private static function build_otpauth_uri( WP_User $user, string $secret ): string {
        $issuer = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $label = $issuer . ':' . $user->user_login;

        return 'otpauth://totp/' . rawurlencode( $label )
            . '?secret=' . rawurlencode( $secret )
            . '&issuer=' . rawurlencode( $issuer )
            . '&algorithm=SHA1'
            . '&digits=' . self::DIGITS
            . '&period=' . self::STEP;
    }

    private static function verify_totp( string $secret, string $code ): bool {
        if ( ! preg_match( '/^\d{6}$/', $code ) ) {
            return false;
        }

        $counter = (int) floor( time() / self::STEP );
        for ( $offset = -1; $offset <= 1; $offset++ ) {
            if ( hash_equals( self::totp_code( $secret, $counter + $offset ), $code ) ) {
                return true;
            }
        }

        return false;
    }

    private static function totp_code( string $secret, int $counter ): string {
        $key = self::base32_decode( $secret );
        $time = pack( 'N*', 0 ) . pack( 'N*', $counter );
        $hash = hash_hmac( 'sha1', $time, $key, true );
        $offset = ord( substr( $hash, -1 ) ) & 0x0F;
        $value = (
            ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 ) |
            ( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 ) |
            ( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 ) |
            ( ord( $hash[ $offset + 3 ] ) & 0xFF )
        );

        return str_pad( (string) ( $value % 1000000 ), self::DIGITS, '0', STR_PAD_LEFT );
    }

    private static function mark_verified( int $user_id ): void {
        $token = wp_get_session_token();
        if ( ! $token ) {
            return;
        }
        $secret = (string) get_user_meta( $user_id, self::META_SECRET, true );
        set_transient( self::verified_key( $user_id, $token ), self::session_secret_hash( $secret ), 12 * HOUR_IN_SECONDS );
    }

    private static function is_current_session_verified(): bool {
        $user_id = get_current_user_id();
        $token = wp_get_session_token();
        if ( $user_id <= 0 || ! $token ) {
            return false;
        }

        $secret = (string) get_user_meta( $user_id, self::META_SECRET, true );
        return hash_equals(
            self::session_secret_hash( $secret ),
            (string) get_transient( self::verified_key( $user_id, $token ) )
        );
    }

    private static function clear_verified_session( int $user_id ): void {
        $token = wp_get_session_token();
        if ( $token ) {
            delete_transient( self::verified_key( $user_id, $token ) );
        }
    }

    private static function verified_key( int $user_id, string $token ): string {
        return self::TRANSIENT_PREFIX . $user_id . '_' . hash( 'sha256', $token );
    }

    private static function session_secret_hash( string $secret ): string {
        return hash_hmac( 'sha256', $secret, wp_salt( 'auth' ) );
    }

    private static function base32_encode( string $data ): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $encoded = '';

        foreach ( str_split( $data ) as $char ) {
            $bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
        }

        foreach ( str_split( $bits, 5 ) as $chunk ) {
            if ( strlen( $chunk ) < 5 ) {
                $chunk = str_pad( $chunk, 5, '0', STR_PAD_RIGHT );
            }
            $encoded .= $alphabet[ bindec( $chunk ) ];
        }

        return $encoded;
    }

    private static function base32_decode( string $secret ): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper( preg_replace( '/[^A-Z2-7]/i', '', $secret ) );
        $bits = '';
        $decoded = '';

        foreach ( str_split( $secret ) as $char ) {
            $position = strpos( $alphabet, $char );
            if ( false === $position ) {
                continue;
            }
            $bits .= str_pad( decbin( $position ), 5, '0', STR_PAD_LEFT );
        }

        foreach ( str_split( $bits, 8 ) as $byte ) {
            if ( strlen( $byte ) === 8 ) {
                $decoded .= chr( bindec( $byte ) );
            }
        }

        return $decoded;
    }
}
