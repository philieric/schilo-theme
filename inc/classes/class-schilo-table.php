<?php
/**
 * Schilo_Table — Tableaux responsives intégrés au thème
 *
 * CPT `schilo_table`, éditeur admin JS, shortcode [schilo_table id="X"].
 * Mobile : mode carte (label:valeur), pas de scroll horizontal.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Table {

    const CPT   = 'schilo_table';
    const META  = '_schilo_table_data';
    const NONCE = 'schilo_table_save';

    // ── Init ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'init',                        [ __CLASS__, 'register_cpt'  ] );
        add_action( 'add_meta_boxes',              [ __CLASS__, 'add_meta_box'  ] );
        add_action( 'save_post_' . self::CPT,      [ __CLASS__, 'save_meta'     ] );
        add_action( 'admin_enqueue_scripts',       [ __CLASS__, 'admin_scripts' ] );
        add_action( 'wp_enqueue_scripts',          [ __CLASS__, 'front_scripts' ] );
        add_shortcode( 'schilo_table',             [ __CLASS__, 'shortcode'     ] );
    }

    // ── CPT ──────────────────────────────────────────────────────────────

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels'        => [
                'name'               => 'Tableaux',
                'singular_name'      => 'Tableau',
                'add_new'            => 'Ajouter',
                'add_new_item'       => 'Nouveau tableau',
                'edit_item'          => 'Modifier le tableau',
                'new_item'           => 'Nouveau tableau',
                'view_item'          => 'Voir le tableau',
                'search_items'       => 'Chercher un tableau',
                'not_found'          => 'Aucun tableau trouvé',
                'not_found_in_trash' => 'Aucun tableau dans la corbeille',
                'menu_name'          => 'Tableaux',
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'menu_icon'     => 'dashicons-grid-view',
            'menu_position' => 26,
            'supports'      => [ 'title' ],
            'rewrite'       => false,
        ] );
    }

    // ── Méta box éditeur ─────────────────────────────────────────────────

    public static function add_meta_box(): void {
        add_meta_box(
            'schilo-table-editor',
            'Données du tableau',
            [ __CLASS__, 'render_meta_box' ],
            self::CPT,
            'normal',
            'high'
        );
    }

    public static function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( self::NONCE, '_schilo_table_nonce' );

        $data    = get_post_meta( $post->ID, self::META, true );
        $caption = '';
        $header  = true;
        $rows    = [ [ '', '' ], [ '', '' ] ];
        $ncols   = 2;

        if ( is_array( $data ) ) {
            $caption = $data['caption']    ?? '';
            $header  = $data['has_header'] ?? true;
            $rows    = $data['rows']       ?? $rows;
            $ncols   = ! empty( $rows[0] ) ? count( $rows[0] ) : 2;
        }

        $rows_json = wp_json_encode( $rows, JSON_UNESCAPED_UNICODE );
        $ncols_val = (int) $ncols;
        ?>
        <div id="sct-app" data-rows="<?php echo esc_attr( $rows_json ); ?>" data-ncols="<?php echo $ncols_val; ?>">

          <!-- Caption + options -->
          <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
            <label style="flex:1;min-width:200px;">
              <span style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;">Légende / titre du tableau</span>
              <input type="text" id="sct-caption" name="sct_caption"
                     value="<?php echo esc_attr( $caption ); ?>"
                     style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
            </label>
            <label style="display:flex;align-items:center;gap:.4rem;font-size:13px;cursor:pointer;white-space:nowrap;">
              <input type="checkbox" id="sct-has-header" name="sct_has_header" value="1"
                     <?php checked( $header ); ?>>
              Première ligne = en-tête
            </label>
          </div>

          <!-- Tableau éditable -->
          <div style="overflow-x:auto;margin-bottom:.75rem;">
            <table id="sct-table"
                   style="border-collapse:collapse;width:100%;min-width:400px;font-size:13px;">
              <tbody id="sct-tbody"></tbody>
            </table>
          </div>

          <!-- Actions -->
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
            <button type="button" id="sct-add-row"
                    style="padding:6px 14px;background:#1e40af;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">
              + Ligne
            </button>
            <button type="button" id="sct-add-col"
                    style="padding:6px 14px;background:#1e40af;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">
              + Colonne
            </button>
          </div>

          <!-- Aperçu de l'usage -->
          <div style="background:#f0f4ff;border:1px solid #bfdbfe;border-radius:4px;padding:.75rem 1rem;font-size:12px;color:#334155;">
            <strong>Shortcode :</strong>
            <code style="background:#fff;border:1px solid #ddd;padding:2px 8px;border-radius:3px;font-size:12px;margin-left:.5rem;">[schilo_table id="<?php echo esc_html( $post->ID ); ?>"]</code>
            &nbsp;— à coller dans une page ou une section d'article.
          </div>

          <!-- Champ caché JSON -->
          <input type="hidden" id="sct-json" name="sct_json" value="">
        </div>
        <?php
    }

    public static function save_meta( int $post_id ): void {
        if ( ! isset( $_POST['_schilo_table_nonce'] )
             || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_schilo_table_nonce'] ) ), self::NONCE ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $json_raw = isset( $_POST['sct_json'] ) ? wp_unslash( $_POST['sct_json'] ) : '';
        $rows     = json_decode( $json_raw, true );
        if ( ! is_array( $rows ) ) $rows = [];

        // Sanitize chaque cellule
        $rows = array_map( function ( array $row ): array {
            return array_map( 'wp_kses_post', $row );
        }, $rows );

        $data = [
            'caption'    => sanitize_text_field( wp_unslash( $_POST['sct_caption'] ?? '' ) ),
            'has_header' => ! empty( $_POST['sct_has_header'] ),
            'rows'       => $rows,
        ];

        update_post_meta( $post_id, self::META, $data );
    }

    // ── Assets admin ─────────────────────────────────────────────────────

    public static function admin_scripts( string $hook ): void {
        global $post_type;
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        if ( $post_type !== self::CPT ) return;

        wp_enqueue_script(
            'schilo-table-admin',
            SCHILO_ASSETS . '/js/schilo-table-admin.js',
            [],
            SCHILO_VERSION,
            true
        );
        wp_enqueue_style(
            'schilo-table-admin',
            SCHILO_ASSETS . '/css/schilo-table-admin.css',
            [],
            SCHILO_VERSION
        );
    }

    // ── Assets front ─────────────────────────────────────────────────────

    public static function front_scripts(): void {
        wp_register_style(
            'schilo-table',
            SCHILO_ASSETS . '/css/schilo-table.css',
            [],
            SCHILO_VERSION
        );
    }

    // ── Rendu HTML ───────────────────────────────────────────────────────

    public static function render_table( array $data ): string {
        $rows       = $data['rows']       ?? [];
        $has_header = $data['has_header'] ?? true;
        $caption    = $data['caption']    ?? '';

        if ( empty( $rows ) ) return '';

        // Colonnes pour data-label sur mobile
        $headers = [];
        if ( $has_header && ! empty( $rows[0] ) ) {
            $headers = $rows[0];
        }

        $ncols = ! empty( $rows[0] ) ? count( $rows[0] ) : 0;
        if ( $ncols === 0 ) return '';

        wp_enqueue_style( 'schilo-table' );

        $html  = '<div class="sct-wrap">';
        $html .= '<table class="sct">';

        if ( $caption ) {
            $html .= '<caption>' . esc_html( $caption ) . '</caption>';
        }

        foreach ( $rows as $ri => $row ) {
            $is_head = ( $ri === 0 && $has_header );
            $html   .= $is_head ? '<thead><tr>' : ( $ri === 1 && $has_header ? '<tbody>' : '' );
            if ( $ri > 0 && ! $has_header && $ri === 0 ) $html .= '<tbody>';

            $html .= '<tr>';
            foreach ( $row as $ci => $cell ) {
                $label = isset( $headers[ $ci ] ) ? esc_attr( wp_strip_all_tags( $headers[ $ci ] ) ) : '';
                if ( $is_head ) {
                    $html .= '<th scope="col">' . wp_kses_post( $cell ) . '</th>';
                } else {
                    $html .= '<td data-label="' . $label . '">' . wp_kses_post( $cell ) . '</td>';
                }
            }
            $html .= '</tr>';

            if ( $is_head ) $html .= '</thead>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    // ── Shortcode ────────────────────────────────────────────────────────

    public static function shortcode( array $atts, ?string $content = null ): string {
        $atts = shortcode_atts( [ 'id' => 0, 'class' => '' ], $atts, 'schilo_table' );
        $id   = (int) $atts['id'];

        if ( $id <= 0 ) return '';

        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::CPT || $post->post_status !== 'publish' ) {
            return '';
        }

        $data = get_post_meta( $id, self::META, true );
        if ( ! is_array( $data ) ) return '';

        return self::render_table( $data );
    }
}
