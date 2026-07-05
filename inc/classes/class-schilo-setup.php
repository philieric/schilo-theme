<?php
/**
 * Configuration générale du thème Schilo.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Setup {

    // Préfixes Schilo — ordre d'affichage dans les filtres admin
    const PREFIXES = [ 'PER', 'CTD', 'ANN', 'APO', 'BIB', 'DAN', 'DOC', 'FDS', 'LGH', 'PAR', 'PDA', 'INF', 'MIR', 'PRB' ];

    public static function init(): void {
        add_action( 'after_setup_theme', [ __CLASS__, 'theme_setup' ] );
        add_action( 'widgets_init',      [ __CLASS__, 'register_sidebars' ] );
        add_action( 'admin_init',        [ __CLASS__, 'check_builder_plugin' ] );
        add_filter( 'template_include',  [ __CLASS__, 'force_archive_template' ], 100 );
        add_action( 'pre_get_posts',     [ __CLASS__, 'apply_archive_sort' ] );

        // ── Filtres admin liste articles ──────────────────────────────────
        add_filter( 'views_edit-post',              [ __CLASS__, 'add_prefix_views' ] );
        add_filter( 'views_edit-post',              [ __CLASS__, 'add_migration_views' ] );
        add_action( 'pre_get_posts',                [ __CLASS__, 'apply_prefix_filter' ] );
        add_action( 'pre_get_posts',                [ __CLASS__, 'apply_migration_filter' ] );
        add_action( 'admin_head-edit.php',          [ __CLASS__, 'prefix_views_style' ] );

        // ── Colonnes personnalisées ───────────────────────────────────────
        add_filter( 'manage_posts_columns',              [ __CLASS__, 'add_list_columns' ] );
        add_action( 'manage_posts_custom_column',        [ __CLASS__, 'render_list_column' ], 10, 2 );
        add_filter( 'manage_edit-post_sortable_columns', [ __CLASS__, 'sortable_list_columns' ] );
        add_action( 'pre_get_posts',                     [ __CLASS__, 'apply_orderby_prefix' ] );

        // ── Actions groupées Migration ────────────────────────────────────
        add_filter( 'bulk_actions-edit-post',        [ __CLASS__, 'add_bulk_migrate_action' ] );
        add_filter( 'handle_bulk_actions-edit-post', [ __CLASS__, 'handle_bulk_migrate' ], 10, 3 );
        add_filter( 'handle_bulk_actions-edit-post', [ __CLASS__, 'handle_bulk_reset_migration' ], 10, 3 );
        add_action( 'admin_notices',                 [ __CLASS__, 'bulk_migrate_notice' ] );

        // ── Désactiver Gutenberg ──────────────────────────────────────────
        add_filter( 'use_block_editor_for_post',          '__return_false', 99 );
        add_filter( 'use_block_editor_for_post_type',     '__return_false', 99 );
        add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
        add_filter( 'use_widgets_block_editor',           '__return_false' );

        // Excerpt
        add_filter( 'excerpt_length', fn() => 25, 999 );
        add_filter( 'excerpt_more',   fn() => '…' );

        // Masquer la barre admin en front
        add_filter( 'show_admin_bar', '__return_false' );
    }

    public static function theme_setup(): void {
        load_theme_textdomain( 'schilo', SCHILO_DIR . '/languages' );

        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'html5', [
            'search-form', 'comment-form', 'comment-list',
            'gallery', 'caption', 'style', 'script',
        ] );
        add_theme_support( 'automatic-feed-links' );
        add_theme_support( 'custom-logo', [
            'height'      => 60,
            'width'       => 200,
            'flex-width'  => true,
            'flex-height' => true,
        ] );

        register_nav_menus( [
            'primary'  => __( 'Navigation principale', 'schilo' ),
            'footer-1' => __( 'Footer — Parcours',     'schilo' ),
            'footer-2' => __( 'Footer — Thèmes',       'schilo' ),
            'footer-3' => __( 'Footer — Site',         'schilo' ),
        ] );
    }

    public static function register_sidebars(): void {
        $defaults = [
            'before_widget' => '<div class="schilo-sb-card" id="%1$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<div class="schilo-sb-title">',
            'after_title'   => '</div>',
        ];

        register_sidebar( $defaults + [
            'name'        => __( 'Sidebar article', 'schilo' ),
            'id'          => 'schilo-sidebar-article',
            'description' => __( 'Widgets affichés dans la sidebar des articles', 'schilo' ),
        ] );

        register_sidebar( $defaults + [
            'name'        => __( 'Sidebar accueil', 'schilo' ),
            'id'          => 'schilo-sidebar-home',
            'description' => __( 'Widgets affichés dans la sidebar de la page d\'accueil', 'schilo' ),
        ] );
    }

    /**
     * Force l'utilisation de archive.php du thème pour les archives de catégories,
     * en surchargeant le filtre du plugin modern-category-grid (priorité 99).
     */
    public static function force_archive_template( string $template ): string {
        if ( ! is_category() ) {
            return $template;
        }
        $theme_tpl = get_template_directory() . '/archive.php';
        return file_exists( $theme_tpl ) ? $theme_tpl : $template;
    }

    public static function apply_archive_sort( WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() ) return;
        if ( ! ( $query->is_category() || $query->is_tag() || $query->is_archive() ) ) return;

        $allowed_sorts = [
            'date-desc'     => [ 'orderby' => 'date',          'order' => 'DESC' ],
            'date-asc'      => [ 'orderby' => 'date',          'order' => 'ASC'  ],
            'title-asc'     => [ 'orderby' => 'title',         'order' => 'ASC'  ],
            'title-desc'    => [ 'orderby' => 'title',         'order' => 'DESC' ],
            'modified-desc' => [ 'orderby' => 'modified',      'order' => 'DESC' ],
            'comment-desc'  => [ 'orderby' => 'comment_count', 'order' => 'DESC' ],
        ];
        $sort = isset( $_GET['schilo_sort'] ) ? sanitize_key( $_GET['schilo_sort'] ) : 'date-desc';
        if ( ! array_key_exists( $sort, $allowed_sorts ) ) $sort = 'date-desc';
        $query->set( 'orderby', $allowed_sorts[ $sort ]['orderby'] );
        $query->set( 'order',   $allowed_sorts[ $sort ]['order'] );

        $allowed_pp = [ 10, 20, 50, -1 ];
        $pp = isset( $_GET['schilo_pp'] ) ? (int) $_GET['schilo_pp'] : 10;
        if ( ! in_array( $pp, $allowed_pp, true ) ) $pp = 10;
        $query->set( 'posts_per_page', $pp );
    }

    // ── Filtres par préfixe ───────────────────────────────────────────────

    /** Boutons de filtre par préfixe (PER, CTD…) dans la barre de vues. */
    public static function add_prefix_views( array $views ): array {
        global $wpdb;

        $current = isset( $_GET['schilo_prefix'] ) ? strtoupper( sanitize_text_field( $_GET['schilo_prefix'] ) ) : '';

        foreach ( self::PREFIXES as $prefix ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                   AND post_status NOT IN ('trash','auto-draft')
                   AND post_title LIKE %s",
                $wpdb->esc_like( $prefix ) . '%'
            ) );

            if ( $count === 0 ) continue;

            $url   = admin_url( 'edit.php?post_type=post&schilo_prefix=' . $prefix );
            $class = ( $current === $prefix ) ? ' class="current"' : '';
            $views[ 'schilo_' . $prefix ] =
                '<a href="' . esc_url( $url ) . '"' . $class . '>'
                . esc_html( $prefix )
                . ' <span class="count">(' . $count . ')</span></a>';
        }

        return $views;
    }

    /** Boutons de filtre par statut de migration. */
    public static function add_migration_views( array $views ): array {
        global $wpdb;

        $current = isset( $_GET['schilo_migration'] ) ? sanitize_key( $_GET['schilo_migration'] ) : '';

        $count_migrated = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE p.post_type = 'post'
               AND p.post_status NOT IN ('trash','auto-draft')
               AND m.meta_key = '_schilo_migration_status'
               AND m.meta_value = 'migrated'"
        );

        $count_total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'post'
               AND post_status NOT IN ('trash','auto-draft')"
        );

        $count_pending = $count_total - $count_migrated;

        $items = [
            'migrated' => [ 'label' => 'Migrés',     'count' => $count_migrated ],
            'pending'  => [ 'label' => 'Non migrés', 'count' => $count_pending  ],
        ];

        foreach ( $items as $key => $item ) {
            if ( $item['count'] === 0 ) continue;
            $url   = admin_url( 'edit.php?post_type=post&schilo_migration=' . $key );
            $class = ( $current === $key ) ? ' class="current"' : '';
            $views[ 'schilo_mig_' . $key ] =
                '<a href="' . esc_url( $url ) . '"' . $class . '>'
                . esc_html( $item['label'] )
                . ' <span class="count">(' . $item['count'] . ')</span></a>';
        }

        return $views;
    }

    /** Filtre WP_Query sur schilo_prefix. */
    public static function apply_prefix_filter( WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( ! isset( $_GET['schilo_prefix'] ) ) return;
        $prefix = strtoupper( sanitize_text_field( $_GET['schilo_prefix'] ) );
        if ( ! in_array( $prefix, self::PREFIXES, true ) ) return;

        add_filter( 'posts_where', static function ( string $where ) use ( $prefix ): string {
            global $wpdb;
            $like  = $wpdb->esc_like( $prefix ) . '%';
            $table = $wpdb->posts;
            $where .= $wpdb->prepare( " AND {$table}.post_title LIKE %s", $like );
            return $where;
        } );
    }

    /** Filtre WP_Query sur schilo_migration. */
    public static function apply_migration_filter( WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( empty( $_GET['schilo_migration'] ) ) return;

        $val = sanitize_key( $_GET['schilo_migration'] );

        if ( $val === 'migrated' ) {
            $query->set( 'meta_key',   '_schilo_migration_status' );
            $query->set( 'meta_value', 'migrated' );
        } elseif ( $val === 'pending' ) {
            $query->set( 'meta_query', [
                'relation' => 'OR',
                [ 'key' => '_schilo_migration_status', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_schilo_migration_status', 'value' => 'migrated', 'compare' => '!=' ],
            ] );
        }
    }

    // ── Colonnes personnalisées ───────────────────────────────────────────

    public static function add_list_columns( array $cols ): array {
        $hidden = [ 'categories', 'tags' ];
        $new    = [];
        foreach ( $cols as $k => $v ) {
            if ( $k === 'title' ) {
                $new['schilo_prefix'] = 'Préfixe';
            }
            if ( ! in_array( $k, $hidden, true ) ) {
                $new[ $k ] = $v;
            }
            if ( $k === 'categories' ) {
                $new['schilo_migration'] = 'Migration';
            }
        }
        return $new;
    }

    public static function render_list_column( string $col, int $post_id ): void {
        if ( $col === 'schilo_prefix' ) {
            $title = get_the_title( $post_id );
            if ( preg_match( '/^([A-Z]{2,4})\d/', $title, $m ) ) {
                $p   = $m[1];
                $url = admin_url( 'edit.php?post_type=post&schilo_prefix=' . $p );
                echo '<a href="' . esc_url( $url ) . '" style="font-weight:700;font-size:11px;letter-spacing:.05em;color:#2271b1;">'
                    . esc_html( $p ) . '</a>';
            } else {
                echo '<span style="color:#bbb">—</span>';
            }
        }

        if ( $col === 'schilo_migration' ) {
            $status = get_post_meta( $post_id, '_schilo_migration_status', true );
            if ( $status === 'migrated' ) {
                echo '<span style="color:#16a34a;font-size:16px;" title="Migré">✓</span>';
            } else {
                echo '<span style="color:#ddd;font-size:16px;" title="Non migré">○</span>';
            }
        }
    }

    public static function sortable_list_columns( array $cols ): array {
        $cols['schilo_prefix'] = 'schilo_prefix';
        return $cols;
    }

    /** Orderby schilo_prefix → orderby title (le titre commence par le préfixe). */
    public static function apply_orderby_prefix( WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'orderby' ) !== 'schilo_prefix' ) return;
        $query->set( 'orderby', 'title' );
    }

    // ── Action groupée Migration ──────────────────────────────────────────

    public static function add_bulk_migrate_action( array $actions ): array {
        $actions['schilo_migrate']       = 'Migrer vers Schilo Builder';
        $actions['schilo_reset_migrate'] = 'Réinitialiser la migration';
        return $actions;
    }

    public static function handle_bulk_reset_migration( string $redirect, string $action, array $ids ): string {
        if ( $action !== 'schilo_reset_migrate' ) return $redirect;

        $reset = 0;
        foreach ( $ids as $raw_id ) {
            $post_id = (int) $raw_id;
            if ( ! get_post( $post_id ) ) continue;
            delete_post_meta( $post_id, '_schilo_migration_status' );
            delete_post_meta( $post_id, '_schilo_builder_sections' );
            $reset++;
        }

        return add_query_arg( [ 'schilo_reset' => $reset ], $redirect );
    }

    public static function handle_bulk_migrate( string $redirect, string $action, array $ids ): string {
        if ( $action !== 'schilo_migrate' ) return $redirect;

        $migrated = 0;
        $skipped  = 0;
        $failed   = 0;

        $ms_class      = '\Schilo\Builder\Service\Migration\MigrationModelService';
        $applier_class = '\Schilo\Builder\Service\Migration\MigrationApplier';
        $registry_class= '\Schilo\Builder\Service\Migration\ExtractorRegistry';
        $source_class  = '\Schilo\Builder\Service\Migration\MigrationSourceContent';

        if ( ! class_exists( $ms_class ) ) {
            return add_query_arg( [ 'schilo_migrate_error' => 'plugin_missing' ], $redirect );
        }

        $ms       = new $ms_class();
        $applier  = new $applier_class();
        $registry = new $registry_class();

        // Cache des modèles par préfixe pour éviter N requêtes
        $models_cache = array();

        foreach ( $ids as $raw_id ) {
            $post_id = (int) $raw_id;
            $post    = get_post( $post_id );
            if ( ! $post ) { $failed++; continue; }

            if ( get_post_meta( $post_id, '_schilo_migration_status', true ) === 'migrated' ) {
                $skipped++; continue;
            }

            // Détection du préfixe depuis le titre
            if ( ! preg_match( '/^([A-Z]{2,4})\d/', $post->post_title, $m ) ) {
                $failed++; continue;
            }
            $prefix = $m[1];

            // Cherche le modèle par préfixe (ID dynamique, pas codé en dur)
            if ( ! isset( $models_cache[ $prefix ] ) ) {
                $found = $ms->getModelsForPrefix( $prefix );
                $models_cache[ $prefix ] = ! empty( $found ) ? reset( $found ) : null;
            }
            $model = $models_cache[ $prefix ];
            if ( ! $model ) {
                error_log( sprintf( '[Schilo Migration] post %d : aucun modèle pour le préfixe %s', $post_id, $prefix ) );
                $failed++; continue;
            }

            // Modèle sans assignments = non configuré, on saute proprement
            if ( empty( $model['assignments'] ) ) {
                error_log( sprintf( '[Schilo Migration] post %d : modèle %s sans assignments (préfixe %s)', $post_id, $model['id'], $prefix ) );
                $failed++; continue;
            }

            try {
                $source   = new $source_class( $post_id, '', $post->post_content );
                $elements = $registry->extractAll( $source );
                // Expand les règles pattern→ IDs concrets (ex: consultation_link_1, section_texte_content_61...)
                $assignments_expanded = $ms->expandModelForElements( $model, $elements );
                $applier->apply( $post_id, $prefix, $elements, $assignments_expanded, true );
                update_post_meta( $post_id, '_schilo_migration_status', 'migrated' );
                $migrated++;
            } catch ( \Throwable $e ) {
                error_log( sprintf( '[Schilo Migration] post %d (%s) : %s in %s:%d', $post_id, $post->post_title, $e->getMessage(), $e->getFile(), $e->getLine() ) );
                $failed++;
            }
        }

        return add_query_arg( [
            'schilo_migrated' => $migrated,
            'schilo_skipped'  => $skipped,
            'schilo_failed'   => $failed,
        ], $redirect );
    }

    public static function bulk_migrate_notice(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-post' ) return;

        if ( isset( $_GET['schilo_migrate_error'] ) && $_GET['schilo_migrate_error'] === 'plugin_missing' ) {
            echo '<div class="notice notice-error schilo-keep"><p>Migration : le plugin Schilo Builder est introuvable.</p></div>';
            return;
        }

        if ( isset( $_GET['schilo_reset'] ) ) {
            $reset = (int) $_GET['schilo_reset'];
            echo '<div class="notice notice-info schilo-keep is-dismissible"><p>Migration réinitialisée pour <strong>' . $reset . '</strong> article(s) — relancez la migration pour les re-traiter.</p></div>';
            return;
        }

        if ( ! isset( $_GET['schilo_migrated'] ) ) return;

        $migrated = (int) $_GET['schilo_migrated'];
        $skipped  = (int) ( $_GET['schilo_skipped'] ?? 0 );
        $failed   = (int) ( $_GET['schilo_failed']  ?? 0 );

        $parts = [];
        if ( $migrated ) $parts[] = "<strong>{$migrated}</strong> migré(s)";
        if ( $skipped )  $parts[] = "<strong>{$skipped}</strong> déjà migré(s)";
        if ( $failed )   $parts[] = "<strong>{$failed}</strong> échec(s)";

        $type = $failed ? 'warning' : 'success';
        echo '<div class="notice notice-' . $type . ' schilo-keep is-dismissible"><p>Migration Schilo Builder : '
            . implode( ', ', $parts ) . '.</p></div>';
    }

    // ── Stats par préfixe ─────────────────────────────────────────────────

    /** Retourne un tableau [prefix => [total, migrated]] pour la barre de stats. */
    private static function get_prefix_stats(): array {
        global $wpdb;
        $stats = [];

        foreach ( self::PREFIXES as $prefix ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                   AND post_status NOT IN ('trash','auto-draft')
                   AND post_title LIKE %s",
                $wpdb->esc_like( $prefix ) . '%'
            ) );

            if ( $total === 0 ) continue;

            $done = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                 WHERE p.post_type = 'post'
                   AND p.post_status NOT IN ('trash','auto-draft')
                   AND p.post_title LIKE %s
                   AND m.meta_key = '_schilo_migration_status'
                   AND m.meta_value = 'migrated'",
                $wpdb->esc_like( $prefix ) . '%'
            ) );

            $stats[] = [ 'prefix' => $prefix, 'total' => $total, 'done' => $done ];
        }

        return $stats;
    }

    // ── CSS + JS admin liste articles ─────────────────────────────────────

    public static function prefix_views_style(): void {
        $stats      = self::get_prefix_stats();
        $stats_json = wp_json_encode( $stats );
        ?>
        <style>
        /* Barre de statuts en boutons */
        .subsubsub { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:0 0 4px; padding:0; list-style:none; }
        .subsubsub li { margin:0; padding:0; }
        .subsubsub a {
            display:inline-block; padding:4px 10px;
            background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px;
            font-size:12px; font-weight:600; color:#50575e; text-decoration:none; line-height:1.4;
        }
        .subsubsub a:hover { background:#eaeaea; border-color:#8c8f94; color:#1d2327; }
        .subsubsub a.current { background:#50575e; border-color:#3c4146; color:#fff; }
        .subsubsub .count { font-weight:400; opacity:.75; }

        /* Barre de boutons préfixe */
        #schilo-prefix-bar, #schilo-migration-bar {
            display:flex; flex-wrap:wrap; gap:6px; margin:8px 0 0; clear:both; width:100%;
        }
        #schilo-prefix-bar a {
            display:inline-block; padding:4px 10px;
            background:#f0f4ff; border:1px solid #c5d0e8; border-radius:4px;
            font-size:12px; font-weight:700; letter-spacing:.05em; color:#2271b1; text-decoration:none; line-height:1.4;
        }
        #schilo-prefix-bar a:hover { background:#dce6f8; border-color:#2271b1; }
        #schilo-prefix-bar a.current { background:#2271b1; border-color:#135e96; color:#fff; }

        /* Barre de boutons migration */
        #schilo-migration-bar a {
            display:inline-block; padding:4px 10px;
            background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px;
            font-size:12px; font-weight:600; color:#16a34a; text-decoration:none; line-height:1.4;
        }
        #schilo-migration-bar a:hover { background:#dcfce7; border-color:#16a34a; }
        #schilo-migration-bar a.current { background:#16a34a; border-color:#15803d; color:#fff; }
        #schilo-prefix-bar .count,
        #schilo-migration-bar .count { font-weight:400; opacity:.75; }

        /* Barre de stats migration */
        #schilo-stats-bar {
            display:flex; flex-wrap:wrap; gap:10px; margin:12px 0 4px; clear:both; width:100%;
            background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:10px 14px;
        }
        .schilo-stat-item { display:flex; flex-direction:column; gap:3px; min-width:60px; }
        .schilo-stat-label { font-size:10px; font-weight:700; letter-spacing:.06em; color:#374151; }
        .schilo-stat-track { height:6px; background:#e5e7eb; border-radius:3px; overflow:hidden; width:60px; }
        .schilo-stat-fill  { height:100%; background:#16a34a; border-radius:3px; transition:width .3s; }
        .schilo-stat-count { font-size:10px; color:#6b7280; }

        /* Colonnes */
        .column-schilo_prefix    { width:60px; text-align:center; }
        .column-schilo_migration { width:80px; text-align:center; }
        </style>

        <script>
        (function () {
            var stats   = <?php echo $stats_json; ?>;
            var baseUrl = "edit.php?post_type=post";

            function getParam(name) {
                return new URLSearchParams(location.search).get(name) || "";
            }

            // Construit une URL en mergeant les params actifs avec un nouveau param
            function buildUrl(newParams) {
                var p = new URLSearchParams({ post_type: "post" });
                var prefix    = getParam("schilo_prefix");
                var migration = getParam("schilo_migration");
                var status    = getParam("post_status");
                if (prefix)    p.set("schilo_prefix",    prefix);
                if (migration) p.set("schilo_migration", migration);
                if (status)    p.set("post_status",      status);
                // Applique les nouveaux params (ecrase les existants)
                Object.keys(newParams).forEach(function (k) {
                    if (newParams[k]) p.set(k, newParams[k]);
                    else p.delete(k);
                });
                return "edit.php?" + p.toString();
            }

            document.addEventListener("DOMContentLoaded", function () {
                // ── 1. Supprimer les "|" dans les <li> de .subsubsub ──
                var sub = document.querySelector(".subsubsub");
                if (sub) {
                    sub.querySelectorAll("li").forEach(function (li) {
                        li.childNodes.forEach(function (node) {
                            if (node.nodeType === 3) node.remove();
                        });
                    });
                }

                // ── 2. Barre prefixe ──
                var bar = document.createElement("div");
                bar.id = "schilo-prefix-bar";
                document.querySelectorAll(".subsubsub a[href*='schilo_prefix']").forEach(function (a) {
                    var clone = a.cloneNode(true);
                    bar.appendChild(clone);
                    var li = a.closest("li");
                    if (li) li.style.display = "none";
                });
                if (bar.children.length > 0 && sub && sub.parentNode) {
                    sub.parentNode.insertBefore(bar, sub.nextSibling);
                }

                // ── 3. Barre migration ──
                var mbar = document.createElement("div");
                mbar.id = "schilo-migration-bar";
                document.querySelectorAll(".subsubsub a[href*='schilo_migration']").forEach(function (a) {
                    var clone = a.cloneNode(true);
                    mbar.appendChild(clone);
                    var li = a.closest("li");
                    if (li) li.style.display = "none";
                });
                if (mbar.children.length > 0 && sub && sub.parentNode) {
                    sub.parentNode.insertBefore(mbar, sub.nextSibling);
                }

                // ── 4. Barre de stats ──
                if (stats && stats.length > 0) {
                    var sbar = document.createElement("div");
                    sbar.id = "schilo-stats-bar";
                    stats.forEach(function (s) {
                        var pct = s.total > 0 ? Math.round(s.done / s.total * 100) : 0;
                        var item = document.createElement("div");
                        item.className = "schilo-stat-item";
                        item.innerHTML =
                            '<div class="schilo-stat-label">' + s.prefix + '</div>' +
                            '<div class="schilo-stat-track"><div class="schilo-stat-fill" style="width:' + pct + '%"></div></div>' +
                            '<div class="schilo-stat-count">' + s.done + '/' + s.total + '</div>';
                        sbar.appendChild(item);
                    });
                    if (sub && sub.parentNode) {
                        // Insere apres la barre de migration ou de prefixe
                        var insertAfter = document.getElementById("schilo-migration-bar")
                                       || document.getElementById("schilo-prefix-bar")
                                       || sub;
                        insertAfter.parentNode.insertBefore(sbar, insertAfter.nextSibling);
                    }
                }

                // ── 5. Chargement AJAX ──
                function schiloLoad(url, push) {
                    var list = document.querySelector("#the-list");
                    if (list) list.style.opacity = "0.4";

                    fetch(url, { credentials: "same-origin" })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, "text/html");

                            var newList = doc.querySelector("#the-list");
                            if (newList) {
                                var cur = document.querySelector("#the-list");
                                if (cur) cur.replaceWith(newList);
                            }

                            ["top", "bottom"].forEach(function (pos) {
                                var cur = document.querySelector(".tablenav." + pos);
                                var nxt = doc.querySelector(".tablenav." + pos);
                                if (cur && nxt) cur.replaceWith(nxt);
                            });

                            var curDisp = document.querySelector(".displaying-header");
                            var nxtDisp = doc.querySelector(".displaying-header");
                            if (curDisp && nxtDisp) curDisp.replaceWith(nxtDisp);

                            // Mise a jour des etats actifs
                            var loadedParams = new URLSearchParams(new URL(url, location.origin).search);

                            document.querySelectorAll("#schilo-prefix-bar a").forEach(function (a) {
                                var ap = new URLSearchParams(new URL(a.href, location.origin).search);
                                a.classList.toggle("current", ap.get("schilo_prefix") === loadedParams.get("schilo_prefix") && loadedParams.get("schilo_prefix"));
                            });
                            document.querySelectorAll("#schilo-migration-bar a").forEach(function (a) {
                                var ap = new URLSearchParams(new URL(a.href, location.origin).search);
                                a.classList.toggle("current", ap.get("schilo_migration") === loadedParams.get("schilo_migration") && loadedParams.get("schilo_migration"));
                            });
                            document.querySelectorAll(".subsubsub a").forEach(function (a) {
                                var ap = new URLSearchParams(new URL(a.href, location.origin).search);
                                a.classList.toggle("current", ap.get("post_status") === loadedParams.get("post_status") && !loadedParams.get("post_status") && !ap.get("post_status") || ap.get("post_status") === loadedParams.get("post_status") && !!ap.get("post_status"));
                            });

                            if (push) history.pushState({ url: url }, "", url);
                        })
                        .catch(function () { location.href = url; });
                }

                // Clic prefixe
                document.addEventListener("click", function (e) {
                    var a = e.target.closest("#schilo-prefix-bar a");
                    if (!a) return;
                    e.preventDefault();
                    var clicked = new URLSearchParams(new URL(a.href, location.origin).search).get("schilo_prefix");
                    var url = a.classList.contains("current")
                        ? buildUrl({ schilo_prefix: "" })
                        : buildUrl({ schilo_prefix: clicked });
                    schiloLoad(url, true);
                });

                // Clic migration
                document.addEventListener("click", function (e) {
                    var a = e.target.closest("#schilo-migration-bar a");
                    if (!a) return;
                    e.preventDefault();
                    var clicked = new URLSearchParams(new URL(a.href, location.origin).search).get("schilo_migration");
                    var url = a.classList.contains("current")
                        ? buildUrl({ schilo_migration: "" })
                        : buildUrl({ schilo_migration: clicked });
                    schiloLoad(url, true);
                });

                // Clic statut (Tous, Publies...)
                document.addEventListener("click", function (e) {
                    var a = e.target.closest(".subsubsub a");
                    if (!a) return;
                    e.preventDefault();
                    var clicked = new URLSearchParams(new URL(a.href, location.origin).search).get("post_status");
                    var url = buildUrl({ post_status: clicked || "" });
                    schiloLoad(url, true);
                });

                // Navigation navigateur
                window.addEventListener("popstate", function (e) {
                    if (e.state && e.state.url) schiloLoad(e.state.url, false);
                });
            });
        }());
        </script>
        <?php
    }

    public static function check_builder_plugin(): void {
        // Notice supprimée : le plugin Schilo Builder est requis et toujours actif.
    }
}
