<?php
/**
 * Plugin Name: Sitemap par Catégorie
 * Description: Sitemap HTML avancé — recherche, badges, tooltips, partage, stats admin.
 * Version: 2.0
 * Author: Eric Philippot
 */

if (!defined('ABSPATH')) exit;

class Sitemap_Par_Categorie {

    private $option_name     = 'sitemap_par_categorie_exclusions';
    private $new_days_option = 'sitemap_par_categorie_new_days';

    public function __construct() {
        add_shortcode('sitemap_par_categorie', [$this, 'render_sitemap']);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue_styles']);
        // admin_menu géré par Schilo Builder (SettingsPage)
        add_action('admin_post_sitemap_save_exclusions', [$this, 'save_exclusions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_spc_get_stats', [$this, 'ajax_get_stats']);
    }

    /* ════════════════════════════════════════════════════════════
       SAUVEGARDE
    ════════════════════════════════════════════════════════════ */
    public function save_exclusions() {
        if (!current_user_can('manage_options')) wp_die('Accès refusé');
        check_admin_referer('sitemap_save_exclusions_nonce');

        $raw  = isset($_POST[$this->option_name]) ? (array) $_POST[$this->option_name] : [];
        $ids  = array_values(array_filter(array_map('intval', $raw)));
        update_option($this->option_name, $ids);

        $days = isset($_POST['spc_new_days']) ? max(1, intval($_POST['spc_new_days'])) : 30;
        update_option($this->new_days_option, $days);

        wp_redirect(add_query_arg(['page' => 'schilo-builder-sitemap', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       AJAX STATS
    ════════════════════════════════════════════════════════════ */
    public function ajax_get_stats() {
        if (!current_user_can('manage_options')) wp_send_json_error();

        $cats     = get_categories(['hide_empty' => false]);
        $excluded = (array) get_option($this->option_name, []);
        $new_days = (int) get_option($this->new_days_option, 30);
        $cutoff   = date('Y-m-d H:i:s', strtotime("-{$new_days} days"));

        $total_posts  = wp_count_posts()->publish;
        $no_thumb     = 0;
        $new_articles = 0;
        $empty_cats   = 0;
        $cat_stats    = [];

        foreach ($cats as $cat) {
            $count = $cat->count;
            if ($count === 0) $empty_cats++;
            $cat_stats[] = ['name' => $cat->name, 'count' => $count, 'excluded' => in_array($cat->term_id, $excluded)];
        }

        /* Articles sans image */
        $posts = get_posts(['posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids']);
        foreach ($posts as $id) {
            if (!has_post_thumbnail($id)) $no_thumb++;
            $post = get_post($id);
            if ($post->post_date >= $cutoff) $new_articles++;
        }

        usort($cat_stats, function($a, $b) { return $b['count'] - $a['count']; });

        wp_send_json_success([
            'total_posts'   => $total_posts,
            'total_cats'    => count($cats),
            'no_thumb'      => $no_thumb,
            'new_articles'  => $new_articles,
            'empty_cats'    => $empty_cats,
            'excluded_cats' => count($excluded),
            'top_cats'      => array_slice($cat_stats, 0, 8),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       MENU ADMIN
    ════════════════════════════════════════════════════════════ */
    public function register_admin_menu() {
        add_menu_page('Sitemap par Catégorie', 'Sitemap Catégorie', 'manage_options',
            'sitemap-par-categorie', [$this, 'admin_page_html'], 'dashicons-list-view', 26);
    }

    /* ════════════════════════════════════════════════════════════
       ASSETS ADMIN
    ════════════════════════════════════════════════════════════ */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'schilo-builder-sitemap') === false) return;

        wp_add_inline_style('wp-admin', '
            .spc-wrap{max-width:900px;margin-top:1.5rem}
            .spc-wrap h1{font-size:1.4rem;margin-bottom:.2rem;color:#1d2327;display:flex;align-items:center;gap:.4rem}
            .spc-subtitle{color:#646970;margin-bottom:1.75rem;font-size:.875rem}
            .spc-notice{background:#d1e7dd;border-left:4px solid #0f5132;color:#0f5132;padding:.7rem 1rem;border-radius:4px;margin-bottom:1.5rem;font-weight:500}
            .spc-tabs{display:flex;gap:0;border-bottom:2px solid #56548C;margin-bottom:1.5rem}
            .spc-tab{padding:.55rem 1.2rem;cursor:pointer;background:none;border:none;font-size:.875rem;color:#646970;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s}
            .spc-tab.active,.spc-tab:hover{color:#56548C;border-bottom-color:#56548C;font-weight:500}
            .spc-tab-panel{display:none}.spc-tab-panel.active{display:block}
            .spc-card{background:#fff;border:1px solid #dde0e8;border-radius:10px;overflow:hidden;margin-bottom:1.5rem}
            .spc-card-head{background:linear-gradient(135deg,#56548C,#3a3870);color:#fff;padding:.8rem 1.2rem;display:flex;align-items:center;gap:.5rem;font-weight:600;font-size:.9rem}
            .spc-card-head .dashicons{font-size:1.05rem;width:1.05rem;height:1.05rem}
            .spc-card-body{padding:1.2rem}
            .spc-toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap}
            .spc-toolbar input[type=text]{flex:1;min-width:160px;padding:.4rem .75rem;border:1px solid #c3c4c7;border-radius:6px;font-size:.875rem}
            .spc-toolbar input:focus{border-color:#56548C;box-shadow:0 0 0 2px rgba(86,84,140,.15);outline:none}
            .spc-btn{font-size:.8rem;padding:.35rem .75rem;border:1px solid #c3c4c7;border-radius:5px;background:#f6f7f7;cursor:pointer;color:#2c3338;transition:background .15s;white-space:nowrap}
            .spc-btn:hover{background:#e0e2e4}
            .spc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.45rem;max-height:440px;overflow-y:auto;padding:2px}
            .spc-item{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .85rem;border:1px solid #eef0f5;border-radius:7px;background:#fafbfc;transition:background .15s,border-color .15s}
            .spc-item:hover{background:#f4f3ff;border-color:#c5c3e8}
            .spc-item.excluded{background:#fff5f5;border-color:#f5c2c7}
            .spc-item.spc-hidden{display:none!important}
            .spc-cat-name{font-size:.875rem;color:#1d2327;display:flex;align-items:center;gap:.35rem;flex:1;min-width:0}
            .spc-cat-name .dashicons{color:#56548C;font-size:.95rem;width:.95rem;height:.95rem;flex-shrink:0}
            .spc-item.excluded .spc-cat-name .dashicons{color:#b02a37}
            .spc-cat-name span.label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .spc-badge-count{font-size:.72rem;color:#646970;background:#eef0f5;padding:1px 6px;border-radius:10px;flex-shrink:0}
            .spc-toggle{position:relative;display:inline-flex;width:40px;height:21px;flex-shrink:0;cursor:pointer}
            .spc-toggle input{opacity:0;width:0;height:0;position:absolute}
            .spc-slider{position:absolute;inset:0;background:#c3c4c7;border-radius:21px;transition:background .2s}
            .spc-slider::before{content:"";position:absolute;width:15px;height:15px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
            .spc-toggle input:checked~.spc-slider{background:#56548C}
            .spc-toggle input:checked~.spc-slider::before{transform:translateX(19px)}
            .spc-footer-bar{display:flex;justify-content:space-between;align-items:center;margin-top:.85rem;padding:.5rem .75rem;background:#f6f7f7;border-radius:6px;font-size:.8rem;color:#646970}
            .spc-footer-bar strong{color:#1d2327}
            .spc-actions{display:flex;align-items:center;gap:.75rem;margin-top:1.25rem}
            .spc-actions .button-primary{background:#56548C!important;border-color:#3a3870!important;padding:.45rem 1.4rem!important;height:auto!important;font-size:.875rem!important}
            .spc-actions .button-primary:hover{background:#3a3870!important}
            .spc-new-days-row{display:flex;align-items:center;gap:.6rem;margin-top:1rem;font-size:.875rem}
            .spc-new-days-row input{width:70px;padding:.35rem .6rem;border:1px solid #c3c4c7;border-radius:6px}
            /* Stats */
            .spc-stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;margin-bottom:1.25rem}
            .spc-stat-card{background:#f6f7f7;border-radius:8px;padding:.85rem 1rem;text-align:center}
            .spc-stat-card .val{font-size:1.8rem;font-weight:700;color:#56548C;line-height:1}
            .spc-stat-card .lbl{font-size:.78rem;color:#646970;margin-top:.2rem}
            .spc-bar-row{display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;font-size:.82rem}
            .spc-bar-bg{flex:1;background:#eef0f5;border-radius:4px;height:8px;overflow:hidden}
            .spc-bar-fill{height:100%;background:#56548C;border-radius:4px;transition:width .4s}
            .spc-bar-val{min-width:32px;text-align:right;color:#646970}
            .spc-bar-name{min-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1d2327}
            .spc-stats-loading{color:#646970;font-style:italic;font-size:.875rem;padding:.5rem 0}
        ');

        wp_add_inline_script('jquery', '
        jQuery(function($){
            /* Onglets admin */
            $(".spc-tab").on("click", function(){
                var t = $(this).data("tab");
                $(".spc-tab").removeClass("active");
                $(".spc-tab-panel").removeClass("active");
                $(this).addClass("active");
                $("#spc-panel-" + t).addClass("active");
                if (t === "stats") loadStats();
            });

            /* Recherche catégories */
            var $items = $(".spc-item");
            $("#spc-search").on("input", function(){
                var q = $(this).val().toLowerCase().trim();
                $items.each(function(){
                    $(this).toggleClass("spc-hidden", q !== "" && $(this).find(".label").text().toLowerCase().indexOf(q) === -1);
                });
                refreshCount();
            });
            $("#spc-show-all").on("click", function(){
                $items.not(".spc-hidden").find("input[type=checkbox]").prop("checked", false).closest(".spc-item").removeClass("excluded");
                refreshCount();
            });
            $("#spc-hide-all").on("click", function(){
                $items.not(".spc-hidden").find("input[type=checkbox]").prop("checked", true).closest(".spc-item").addClass("excluded");
                refreshCount();
            });
            $(document).on("change", ".spc-toggle input", function(){
                $(this).closest(".spc-item").toggleClass("excluded", $(this).is(":checked"));
                refreshCount();
            });
            function refreshCount(){
                var excl = $items.filter(".excluded").length;
                $("#spc-nb-visible").text($items.length - excl);
                $("#spc-nb-excluded").text(excl);
            }
            refreshCount();

            /* Stats */
            var statsLoaded = false;
            function loadStats(){
                if (statsLoaded) return;
                statsLoaded = true;
                $.post(ajaxurl, {action:"spc_get_stats", _ajax_nonce: spcAdmin.nonce}, function(res){
                    if (!res.success) return;
                    var d = res.data;
                    $("#spc-stat-posts").text(d.total_posts);
                    $("#spc-stat-cats").text(d.total_cats);
                    $("#spc-stat-nothumb").text(d.no_thumb);
                    $("#spc-stat-new").text(d.new_articles);
                    $("#spc-stat-empty").text(d.empty_cats);
                    $("#spc-stat-excl").text(d.excluded_cats);
                    var maxCount = d.top_cats[0] ? d.top_cats[0].count : 1;
                    var html = "";
                    d.top_cats.forEach(function(c){
                        var pct = maxCount > 0 ? Math.round(c.count / maxCount * 100) : 0;
                        var style = c.excluded ? "opacity:.5" : "";
                        html += \'<div class="spc-bar-row" style="\' + style + \'">\';
                        html += \'<span class="spc-bar-name" title="\' + c.name + \'">\' + c.name + \'</span>\';
                        html += \'<div class="spc-bar-bg"><div class="spc-bar-fill" style="width:\' + pct + \'%"></div></div>\';
                        html += \'<span class="spc-bar-val">\' + c.count + \'</span>\';
                        html += "</div>";
                    });
                    $("#spc-top-cats").html(html);
                    $(".spc-stats-loading").hide();
                    $(".spc-stats-content").show();
                });
            }
        });
        ');

        wp_localize_script('jquery', 'spcAdmin', [
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       PAGE ADMIN HTML
    ════════════════════════════════════════════════════════════ */
    public function admin_page_html() {
        $excluded_ids   = (array) get_option($this->option_name, []);
        $all_categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
        $total          = count($all_categories);
        $nb_excluded    = count(array_intersect(array_column($all_categories, 'term_id'), $excluded_ids));
        $new_days       = (int) get_option($this->new_days_option, 30);
        ?>
        <div class="wrap spc-wrap">
            <h1><span class="dashicons dashicons-list-view" style="color:#56548C;font-size:1.4rem;width:1.4rem;height:1.4rem"></span>Sitemap par Catégorie</h1>
            <p class="spc-subtitle">Gérez les catégories, les options d'affichage et consultez les statistiques.</p>

            <?php if (!empty($_GET['saved'])): ?>
                <div class="spc-notice">✔ Paramètres enregistrés avec succès.</div>
            <?php endif; ?>

            <div class="spc-tabs">
                <button class="spc-tab active" data-tab="cats">📂 Catégories</button>
                <button class="spc-tab" data-tab="options">⚙️ Options</button>
                <button class="spc-tab" data-tab="stats">📊 Statistiques</button>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sitemap_save_exclusions_nonce'); ?>
                <input type="hidden" name="action" value="sitemap_save_exclusions">

                <!-- ONGLET CATÉGORIES -->
                <div id="spc-panel-cats" class="spc-tab-panel active">
                    <div class="spc-card">
                        <div class="spc-card-head"><span class="dashicons dashicons-category"></span>Catégories affichées dans le sitemap</div>
                        <div class="spc-card-body">
                            <div class="spc-toolbar">
                                <input type="text" id="spc-search" placeholder="🔍 Rechercher une catégorie…" autocomplete="off">
                                <button type="button" id="spc-show-all" class="spc-btn">✅ Tout afficher</button>
                                <button type="button" id="spc-hide-all" class="spc-btn">🚫 Tout masquer</button>
                            </div>
                            <div class="spc-grid">
                                <?php foreach ($all_categories as $cat):
                                    $excluded = in_array($cat->term_id, $excluded_ids); ?>
                                <div class="spc-item <?php echo $excluded ? 'excluded' : ''; ?>">
                                    <span class="spc-cat-name">
                                        <span class="dashicons dashicons-category"></span>
                                        <span class="label"><?php echo esc_html($cat->name); ?></span>
                                        <span class="spc-badge-count"><?php echo (int)$cat->count; ?></span>
                                    </span>
                                    <label class="spc-toggle" title="<?php echo $excluded ? 'Masqué' : 'Affiché'; ?>">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[]"
                                               value="<?php echo esc_attr($cat->term_id); ?>" <?php checked($excluded); ?>>
                                        <span class="spc-slider"></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="spc-footer-bar">
                                <span><strong id="spc-nb-visible"><?php echo $total - $nb_excluded; ?></strong> affichées &nbsp;·&nbsp; <strong id="spc-nb-excluded"><?php echo $nb_excluded; ?></strong> masquées sur <?php echo $total; ?></span>
                                <span style="color:#888">toggle violet = masqué</span>
                            </div>
                        </div>
                    </div>
                    <div class="spc-actions">
                        <?php submit_button('Enregistrer', 'primary', 'submit', false); ?>
                        <a href="<?php echo esc_url(home_url('/sitemap-par-categorie.xml')); ?>" target="_blank" class="button">🗺 Voir le sitemap XML</a>
                    </div>
                </div>

                <!-- ONGLET OPTIONS -->
                <div id="spc-panel-options" class="spc-tab-panel">
                    <div class="spc-card">
                        <div class="spc-card-head"><span class="dashicons dashicons-admin-settings"></span>Options d'affichage</div>
                        <div class="spc-card-body">
                            <div class="spc-new-days-row">
                                <label for="spc_new_days">Badge <strong>« Nouveau »</strong> sur les articles publiés depuis moins de</label>
                                <input type="number" id="spc_new_days" name="spc_new_days" value="<?php echo esc_attr($new_days); ?>" min="1" max="365">
                                <span>jours</span>
                            </div>
                        </div>
                    </div>
                    <div class="spc-actions">
                        <?php submit_button('Enregistrer', 'primary', 'submit', false); ?>
                    </div>
                </div>

            </form>

            <!-- ONGLET STATS (hors form) -->
            <div id="spc-panel-stats" class="spc-tab-panel">
                <div class="spc-card">
                    <div class="spc-card-head"><span class="dashicons dashicons-chart-bar"></span>Statistiques du contenu</div>
                    <div class="spc-card-body">
                        <p class="spc-stats-loading">Chargement…</p>
                        <div class="spc-stats-content" style="display:none">
                            <div class="spc-stat-grid">
                                <div class="spc-stat-card"><div class="val" id="spc-stat-posts">–</div><div class="lbl">Articles publiés</div></div>
                                <div class="spc-stat-card"><div class="val" id="spc-stat-cats">–</div><div class="lbl">Catégories</div></div>
                                <div class="spc-stat-card"><div class="val" id="spc-stat-new">–</div><div class="lbl">Nouveaux articles</div></div>
                                <div class="spc-stat-card"><div class="val" id="spc-stat-nothumb" style="color:#c0392b">–</div><div class="lbl">Sans image vedette</div></div>
                                <div class="spc-stat-card"><div class="val" id="spc-stat-empty">–</div><div class="lbl">Catégories vides</div></div>
                                <div class="spc-stat-card"><div class="val" id="spc-stat-excl">–</div><div class="lbl">Catégories masquées</div></div>
                            </div>
                            <p style="font-weight:600;font-size:.875rem;margin-bottom:.6rem;color:#1d2327">Top catégories par nombre d'articles</p>
                            <div id="spc-top-cats"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ════════════════════════════════════════════════════════════
       CSS FRONT
    ════════════════════════════════════════════════════════════ */
    public function enqueue_styles() {
        wp_register_style('sitemap-par-categorie-style', false);
        wp_enqueue_style('sitemap-par-categorie-style');
        wp_add_inline_style('sitemap-par-categorie-style', '

            /* ════════════════════════════════════════════════════════
               VARIABLES DE THÈME — Schilo
               Modifiez ici pour adapter aux couleurs de n\'importe quel site.
               Le mode sombre est géré automatiquement via .spc-dark.
            ════════════════════════════════════════════════════════ */
            :root {
                --spc-accent:        #56548C;
                --spc-accent-dark:   #3a3870;
                --spc-accent-light:  #6e6ba0;
                --spc-accent-pale:   #f4f3ff;
                --spc-accent-border: #c5c3e8;

                --spc-bg:            #ffffff;
                --spc-bg-2:          #fafbfc;
                --spc-bg-3:          #eef0f5;
                --spc-border:        #dde0e8;
                --spc-border-light:  #eef0f5;

                --spc-text:          #222;
                --spc-text-2:        #444;
                --spc-text-muted:    #888;
                --spc-text-faint:    #bbb;

                --spc-node-1:        linear-gradient(135deg, #56548C, #3a3870);
                --spc-node-2:        linear-gradient(135deg, #6e6ba0, #56548C);
                --spc-node-3:        linear-gradient(135deg, #8e8cb8, #6e6ba0);
                --spc-node-misc-bg:  #f0eff8;
                --spc-node-misc-fg:  #56548C;

                --spc-shadow:        rgba(86,84,140,0.08);
                --spc-shadow-hover:  rgba(86,84,140,0.18);
                --spc-highlight:     #fff3a3;
                --spc-new-badge:     #e74c3c;

                --spc-radius:        8px;
                --spc-radius-sm:     5px;
                --spc-transition:    .15s;
            }

            /* ── MODE SOMBRE ── */
            /* ═══════════════════════════════════════════════
               MODE SOMBRE — variables + overrides complets
            ═══════════════════════════════════════════════ */
            .spc-dark {
                /* Fonds */
                --spc-bg:            #1e1e2e;
                --spc-bg-2:          #181825;
                --spc-bg-3:          #2a2a3e;
                --spc-border:        #3a3a5c;
                --spc-border-light:  #2d2d48;

                /* Textes — tous clairs et lisibles */
                --spc-text:          #cdd6f4;
                --spc-text-2:        #bac2de;
                --spc-text-muted:    #7f849c;
                --spc-text-faint:    #585b70;

                /* Accent — conservé mais adapté */
                --spc-accent:        #89b4fa;
                --spc-accent-dark:   #74c7ec;
                --spc-accent-light:  #b4befe;
                --spc-accent-pale:   #24243e;
                --spc-accent-border: #45475a;

                /* Nœuds accordéon */
                --spc-node-1:        linear-gradient(135deg, #45475a, #313244);
                --spc-node-2:        linear-gradient(135deg, #585b70, #45475a);
                --spc-node-3:        linear-gradient(135deg, #6c7086, #585b70);
                --spc-node-misc-bg:  #24243e;
                --spc-node-misc-fg:  #89b4fa;

                /* Ombres */
                --spc-shadow:        rgba(0,0,0,0.4);
                --spc-shadow-hover:  rgba(0,0,0,0.6);
                --spc-highlight:     #3d3500;
                --spc-new-badge:     #f38ba8;
            }

            /* Sidebar en mode sombre */
            .spc-dark .sitemap-summary {
                background: var(--spc-bg-2);
            }
            .spc-dark .sitemap-summary li a {
                color: var(--spc-text-2);
            }
            .spc-dark .sitemap-summary li a:hover {
                background: var(--spc-accent-pale);
                color: var(--spc-accent);
            }
            .spc-dark .sitemap-summary li a.active {
                background: var(--spc-accent);
                color: #1e1e2e;
            }
            .spc-dark .sitemap-summary li a .nav-count {
                background: var(--spc-bg-3);
                color: var(--spc-text-muted);
            }
            .spc-dark .sitemap-summary li a.active .nav-count {
                background: rgba(30,30,46,0.35);
                color: #1e1e2e;
            }

            /* Barre de recherche en mode sombre */
            .spc-dark .spc-search-wrap {
                background: var(--spc-bg-2);
                border-color: var(--spc-border);
            }
            .spc-dark .spc-search-input {
                color: var(--spc-text);
            }
            .spc-dark .spc-search-input::placeholder {
                color: var(--spc-text-faint);
            }

            /* Topbar titre en mode sombre */
            .spc-dark .spc-topbar {
                border-bottom-color: var(--spc-accent);
            }
            .spc-dark .spc-topbar-title {
                color: var(--spc-accent-light);
            }
            .spc-dark .spc-breadcrumb span {
                color: var(--spc-accent);
            }

            /* Boutons expand/share/view en mode sombre */
            .spc-dark .spc-expand-btn,
            .spc-dark .spc-share-btn {
                background: var(--spc-accent-pale);
                border-color: var(--spc-accent-border);
                color: var(--spc-accent);
            }
            .spc-dark .spc-expand-btn:hover,
            .spc-dark .spc-share-btn:hover {
                background: var(--spc-accent);
                color: #1e1e2e;
            }
            .spc-dark .spc-view-toggle {
                border-color: var(--spc-border);
            }
            .spc-dark .spc-view-btn {
                background: var(--spc-accent-pale);
                color: var(--spc-text-muted);
            }
            .spc-dark .spc-view-btn:hover {
                background: var(--spc-bg-3);
                color: var(--spc-accent);
            }
            .spc-dark .spc-view-btn.active {
                background: var(--spc-accent);
                color: #1e1e2e;
            }
            .spc-dark .spc-theme-btn {
                background: var(--spc-accent-pale);
                border-color: var(--spc-accent-border);
                color: var(--spc-accent);
            }
            .spc-dark .spc-theme-btn:hover {
                background: var(--spc-accent);
                color: #1e1e2e;
            }

            /* Articles en mode sombre */
            .spc-dark .spc-article {
                border-bottom-color: var(--spc-border-light);
            }
            .spc-dark .spc-article:hover {
                background: var(--spc-accent-pale);
            }
            .spc-dark .spc-article-title {
                color: var(--spc-text);
            }
            .spc-dark .spc-article-title:hover {
                color: var(--spc-accent);
            }
            .spc-dark .spc-article-date {
                color: var(--spc-text-muted);
            }
            .spc-dark .spc-thumb-ph-list,
            .spc-dark .spc-thumb-ph-grid {
                background: var(--spc-bg-3);
            }

            /* Accordéon en mode sombre */
            .spc-dark .spc-node-body {
                border-left-color: var(--spc-border);
            }
            .spc-dark .spc-node-misc > .spc-node-header,
            .spc-dark .spc-node-misc.spc-depth-1 > .spc-node-header,
            .spc-dark .spc-node-misc.spc-depth-2 > .spc-node-header,
            .spc-dark .spc-node-misc.spc-depth-3 > .spc-node-header {
                background: var(--spc-node-misc-bg);
                color: var(--spc-node-misc-fg);
                border-color: var(--spc-accent-border);
            }

            /* Grille en mode sombre */
            .spc-dark .spc-view-grid .spc-article {
                background: var(--spc-bg-2);
                border-color: var(--spc-border);
            }
            .spc-dark .spc-view-grid .spc-article:hover {
                background: var(--spc-bg-2);
            }

            /* Tooltip en mode sombre */
            .spc-dark .spc-tooltip-box {
                background: var(--spc-bg-2);
                border-color: var(--spc-border);
                color: var(--spc-text-2);
            }
            .spc-dark .spc-tooltip-box::before {
                background: var(--spc-bg-2);
                border-color: var(--spc-border);
            }

            /* Badge Nouveau en mode sombre */
            .spc-dark .spc-badge-new {
                background: var(--spc-new-badge);
                color: #1e1e2e;
            }

            /* ── BOUTON THÈME (toggle clair/sombre) ── */
            .spc-theme-toggle {
                display: flex;
                align-items: center;
                gap: .4rem;
                margin-bottom: .85rem;
                justify-content: flex-end;
            }
            .spc-theme-btn {
                display: flex;
                align-items: center;
                gap: .35rem;
                padding: .3rem .7rem;
                background: var(--spc-accent-pale);
                border: 1px solid var(--spc-accent-border);
                border-radius: 20px;
                cursor: pointer;
                font-size: .78rem;
                color: var(--spc-accent);
                font-weight: 500;
                transition: background var(--spc-transition), color var(--spc-transition);
                line-height: 1;
            }
            .spc-theme-btn:hover { background: var(--spc-accent); color: #fff; }
            .spc-theme-label { pointer-events: none; }

            /* ── LAYOUT ── */
            .sitemap-wrapper { display:flex; align-items:flex-start; gap:0; font-family:inherit; }

            /* ── BARRE DE RECHERCHE ── */
            .spc-search-bar { margin-bottom: 1rem; }
            .spc-search-wrap {
                display: flex; align-items: center; gap: .5rem;
                background: var(--spc-bg);
                border: 1.5px solid var(--spc-border);
                border-radius: var(--spc-radius);
                padding: .4rem .75rem;
                transition: border-color var(--spc-transition), box-shadow var(--spc-transition);
            }
            .spc-search-wrap:focus-within {
                border-color: var(--spc-accent);
                box-shadow: 0 0 0 3px rgba(86,84,140,.12);
            }
            .spc-search-icon { color: var(--spc-text-faint); font-size: 1rem; flex-shrink: 0; }
            .spc-search-input {
                flex: 1; border: none; outline: none; font-size: .88rem;
                background: transparent; color: var(--spc-text);
            }
            .spc-search-input::placeholder { color: var(--spc-text-faint); }
            .spc-search-clear { background:none; border:none; cursor:pointer; color:var(--spc-text-faint); font-size:.9rem; padding:0; display:none; line-height:1; }
            .spc-search-clear.visible { display:block; }
            .spc-search-count { font-size:.78rem; color:var(--spc-text-muted); white-space:nowrap; }
            .spc-highlight { background:var(--spc-highlight); border-radius:2px; padding:0 1px; }

            /* ── SIDEBAR ── */
            .sitemap-summary {
                width: 240px; min-width: 240px;
                position: sticky; top: 80px;
                max-height: calc(100vh - 100px); overflow-y: auto;
                background: var(--spc-bg);
                border: 1px solid var(--spc-border);
                border-radius: 10px;
                box-shadow: 0 2px 12px var(--spc-shadow);
                margin-right: 1.5rem;
                scrollbar-width: thin;
                scrollbar-color: var(--spc-accent) var(--spc-bg-3);
            }
            .sitemap-summary::-webkit-scrollbar { width:5px; }
            .sitemap-summary::-webkit-scrollbar-thumb { background:var(--spc-accent); border-radius:3px; }
            .sitemap-summary ul { list-style:none; padding:0; margin:0; }
            .sitemap-summary>ul>li { border-bottom: 1px solid var(--spc-border-light); }
            .sitemap-summary>ul>li:last-child { border-bottom:none; }
            .sitemap-summary li a {
                display:flex; align-items:center; gap:.5rem;
                padding:.6rem 1rem;
                color: var(--spc-text-2);
                text-decoration:none; font-size:1rem;
                transition: background var(--spc-transition), color var(--spc-transition), padding-left var(--spc-transition);
                cursor:pointer; border-left: 3px solid transparent;
            }
            .sitemap-summary li a:hover {
                background: var(--spc-accent-pale); color: var(--spc-accent);
                padding-left:1.2rem; border-left-color:var(--spc-accent);
            }
            .sitemap-summary li a.active {
                background: var(--spc-accent); color:#fff;
                font-weight:600; border-left-color:var(--spc-accent-dark);
            }
            .sitemap-summary li:first-child a::before { content:"🗂️"; }
            .sitemap-summary li:not(:first-child) a::before { content:"📁"; font-size:.9em; }
            .sitemap-summary li a.active::before { filter:brightness(10); }
            .sitemap-summary li a .nav-count {
                margin-left:auto; background:var(--spc-bg-3); color:var(--spc-text-muted);
                font-size:.75rem; padding:1px 6px; border-radius:10px; min-width:22px; text-align:center;
            }
            .sitemap-summary li a.active .nav-count { background:rgba(255,255,255,.22); color:#fff; }

            /* ── CONTENU ── */
            .sitemap-categories { flex:1; min-width:0; }
            .cat-parent { display:none; }
            .cat-parent.visible { display:block; animation:spcFade .22s ease; }
            @keyframes spcFade { from{opacity:0;transform:translateY(7px)} to{opacity:1;transform:translateY(0)} }

            /* ── TOPBAR ── */
            .spc-topbar {
                display:flex; align-items:center; justify-content:space-between;
                gap:.75rem; margin-bottom:1.1rem; padding-bottom:.8rem;
                border-bottom: 2px solid var(--spc-accent); flex-wrap:wrap;
            }
            .spc-topbar-title {
                font-size:1.1em; font-weight:700; color:var(--spc-accent-dark);
                display:flex; align-items:center; gap:.4rem; margin:0;
            }
            .spc-dark .spc-topbar-title { color: var(--spc-accent-light); }

            /* Fil d\'Ariane */
            .spc-breadcrumb {
                font-size:.78rem; color:var(--spc-text-muted);
                margin-bottom:.6rem; display:flex; align-items:center;
                gap:.3rem; flex-wrap:wrap;
            }
            .spc-breadcrumb span { color:var(--spc-accent); cursor:default; }
            .spc-breadcrumb .sep { color:var(--spc-text-faint); }

            .spc-topbar-controls { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }

            .spc-expand-btn, .spc-share-btn {
                padding:.28rem .65rem;
                background: var(--spc-accent-pale);
                border: 1px solid var(--spc-accent-border);
                border-radius:6px; cursor:pointer; font-size:.78rem;
                color: var(--spc-accent); font-weight:500;
                transition: background var(--spc-transition), color var(--spc-transition);
                white-space:nowrap; line-height:1.4;
            }
            .spc-expand-btn:hover, .spc-share-btn:hover {
                background: var(--spc-accent); color:#fff; border-color:var(--spc-accent);
            }
            .spc-share-btn { position:relative; }
            .spc-share-toast {
                position:absolute; top:-2rem; left:50%; transform:translateX(-50%);
                background:#333; color:#fff; font-size:.75rem; padding:.2rem .6rem;
                border-radius:4px; white-space:nowrap; opacity:0; transition:opacity .2s;
                pointer-events:none;
            }
            .spc-share-toast.show { opacity:1; }

            .spc-view-toggle {
                display:flex; border:1px solid var(--spc-border);
                border-radius:7px; overflow:hidden; flex-shrink:0;
            }
            .spc-view-btn {
                padding:.3rem .6rem; background:var(--spc-accent-pale);
                border:none; cursor:pointer; font-size:1rem;
                color:var(--spc-text-faint);
                transition: background var(--spc-transition), color var(--spc-transition); line-height:1;
            }
            .spc-view-btn+.spc-view-btn { border-left:1px solid var(--spc-border); }
            .spc-view-btn:hover { background:var(--spc-bg-3); color:var(--spc-accent); }
            .spc-view-btn.active { background:var(--spc-accent); color:#fff; }

            /* ── ACCORDÉON ── */
            .spc-node { margin-bottom:.75rem; }
            .spc-node-header {
                display:flex; align-items:center; gap:.5rem;
                padding:.55rem .9rem;
                background: var(--spc-node-1);
                color:#fff; border-radius:7px; cursor:pointer;
                user-select:none; font-weight:600; font-size:.92em;
                transition:opacity var(--spc-transition);
            }
            .spc-node-header:hover { opacity:.9; }
            .spc-depth-2>.spc-node-header { background:var(--spc-node-2); font-size:.88em; padding:.45rem .85rem; }
            .spc-depth-3>.spc-node-header, .spc-depth-4>.spc-node-header { background:var(--spc-node-3); font-size:.85em; padding:.4rem .8rem; }
            .spc-node-misc>.spc-node-header,
            .spc-node-misc.spc-depth-1>.spc-node-header,
            .spc-node-misc.spc-depth-2>.spc-node-header,
            .spc-node-misc.spc-depth-3>.spc-node-header {
                background: var(--spc-node-misc-bg);
                color: var(--spc-node-misc-fg);
                border: 1px dashed var(--spc-accent-border);
                font-style:italic; font-weight:500;
            }
            .spc-node-misc>.spc-node-header:hover { opacity:.85; }
            .spc-node-misc .spc-chevron { color:var(--spc-node-misc-fg); }
            .spc-chevron { margin-left:auto; font-style:normal; font-size:.8em; transition:transform .2s; flex-shrink:0; }
            .spc-node.open>.spc-node-header .spc-chevron { transform:rotate(90deg); }
            .spc-node-body {
                display:none; padding:.5rem 0 0 1rem;
                border-left: 2px solid var(--spc-border);
                margin-left:.6rem; margin-top:.3rem;
            }
            .spc-node.open>.spc-node-body { display:block; }

            /* ── ARTICLES ── */
            .spc-articles { margin:0; padding:0; list-style:none; }
            .spc-img-grid { display:none; }
            .spc-img-list { display:contents; }

            .spc-article {
                display:flex; align-items:center; gap:.65rem;
                padding:.38rem .75rem;
                border-bottom: 1px solid var(--spc-border-light);
                transition: background var(--spc-transition);
                font-size:.875rem; position:relative;
                background: transparent;
            }
            .spc-article:last-child { border-bottom:none; }
            .spc-article:hover { background:var(--spc-accent-pale); border-radius:var(--spc-radius-sm); }

            .spc-badge-new {
                display:inline-block; background:var(--spc-new-badge); color:#fff;
                font-size:.65rem; font-weight:700; padding:1px 5px; border-radius:3px;
                margin-left:.35rem; vertical-align:middle;
                text-transform:uppercase; letter-spacing:.03em; line-height:1.5; flex-shrink:0;
            }
            .spc-article-date { font-size:.72rem; color:var(--spc-text-muted); white-space:nowrap; flex-shrink:0; }

            .spc-thumb-list {
                width:40px; height:40px; border-radius:var(--spc-radius-sm);
                object-fit:cover; flex-shrink:0; background:var(--spc-bg-3);
            }
            .spc-thumb-ph-list {
                width:40px; height:40px; border-radius:var(--spc-radius-sm);
                background:var(--spc-bg-3); flex-shrink:0;
                display:flex; align-items:center; justify-content:center;
                font-size:1.05em; color:var(--spc-text-faint);
            }
            .spc-article-info { flex:1; min-width:0; display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
            .spc-article-title { text-decoration:none; color:var(--spc-text); line-height:1.35; }
            .spc-article-title:hover { color:var(--spc-accent); text-decoration:underline; }

            /* Tooltip */
            .spc-tooltip-wrap { position:relative; }
            .spc-tooltip-btn {
                background:none; border:none; cursor:pointer;
                color:var(--spc-accent-border); font-size:.85rem;
                padding:0 .15rem; line-height:1;
                transition:color var(--spc-transition); flex-shrink:0;
            }
            .spc-tooltip-btn:hover { color:var(--spc-accent); }
            .spc-tooltip-box {
                display:none; position:absolute; left:0; top:calc(100% + 6px);
                width:280px; background:var(--spc-bg);
                border:1px solid var(--spc-border); border-radius:var(--spc-radius);
                box-shadow:0 6px 20px var(--spc-shadow-hover);
                padding:.75rem .9rem; font-size:.95rem;
                color:var(--spc-text-2); line-height:1.6; z-index:9999;
            }
            .spc-tooltip-box.open { display:block; animation:spcFade .15s ease; }
            .spc-tooltip-box::before {
                content:""; position:absolute; top:-6px; left:16px;
                width:10px; height:10px; background:var(--spc-bg);
                border-left:1px solid var(--spc-border);
                border-top:1px solid var(--spc-border);
                transform:rotate(45deg);
            }

            /* ── MODE GRILLE ── */
            .spc-view-grid .spc-img-list { display:none; }
            .spc-view-grid .spc-img-grid { display:block; }
            .spc-view-grid .spc-articles {
                display:grid!important;
                grid-template-columns:repeat(auto-fill,minmax(145px,1fr));
                gap:.65rem; padding:.5rem 0;
            }
            .spc-view-grid .spc-article {
                flex-direction:column; padding:0;
                border:1px solid var(--spc-border); border-radius:var(--spc-radius);
                overflow:hidden; background:var(--spc-bg);
                box-shadow:0 1px 4px var(--spc-shadow);
                transition:box-shadow .18s, transform .18s;
                gap:0; align-items:stretch;
            }
            .spc-view-grid .spc-article:hover {
                box-shadow:0 5px 18px var(--spc-shadow-hover);
                transform:translateY(-2px);
            }
            .spc-view-grid .spc-article-info { flex-direction:column; align-items:flex-start; padding:.45rem .55rem .5rem; gap:.2rem; }
            .spc-view-grid .spc-article-title { font-size:.78rem; }
            .spc-view-grid .spc-article-date { font-size:.7rem; }
            .spc-view-grid .spc-tooltip-wrap { display:none; }
            .spc-thumb-grid { width:100%; aspect-ratio:16/9; object-fit:cover; display:block; background:var(--spc-bg-3); }
            .spc-thumb-ph-grid {
                width:100%; aspect-ratio:16/9;
                background: linear-gradient(135deg, var(--spc-bg-3), var(--spc-accent-pale));
                display:flex; align-items:center; justify-content:center;
                font-size:1.8em; color:var(--spc-accent-border);
            }

            .cat-parent>h2 { display:none; }
            .sitemap-categories.show-all .cat-parent { display:block; margin-bottom:2.5rem; animation:spcFade .22s ease; }

            .spc-no-results { display:none; text-align:center; padding:2rem 1rem; color:var(--spc-text-muted); font-style:italic; font-size:.9rem; }
            .spc-no-results.visible { display:block; }

            /* ── RESPONSIVE ── */
            @media(max-width:900px){.spc-view-grid .spc-articles{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))}}
            @media(max-width:768px){
                .sitemap-wrapper{flex-direction:column}
                .sitemap-summary{width:100%;min-width:unset;position:static;max-height:none;margin-right:0;margin-bottom:1rem;border-radius:var(--spc-radius);overflow:hidden}
                .sitemap-summary>ul{display:flex;flex-wrap:wrap;gap:4px;padding:.5rem}
                .sitemap-summary>ul>li{border-bottom:none}
                .sitemap-summary li a{border:1px solid var(--spc-border);border-radius:6px;font-size:.8rem;padding:.38rem .65rem;border-left:3px solid transparent}
                .sitemap-summary li a .nav-count{display:none}
                .spc-node-body{padding-left:.5rem;margin-left:.25rem}
                .spc-tooltip-box{width:220px}
            }
            @media(max-width:480px){.spc-view-grid .spc-articles{grid-template-columns:repeat(2,1fr)}}

        ');

        wp_register_script('sitemap-par-categorie-script', false);
        wp_enqueue_script('sitemap-par-categorie-script');
        /* Passer les options de thème au JS via wp_localize_script */
        $theme_option = get_option('sitemap_par_categorie_theme', 'auto');
        wp_localize_script('sitemap-par-categorie-script', 'spcFront', [
            'theme' => $theme_option,
        ]);
        wp_add_inline_script('sitemap-par-categorie-script', '
        document.addEventListener("DOMContentLoaded", function() {

            var summary   = document.querySelector(".sitemap-summary");
            var container = document.querySelector(".sitemap-categories");
            if (!summary || !container) return;

            /* ── Helpers thème ── */
            function getSystemDark() {
                return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
            }
            function applyTheme(mode) {
                var root  = document.querySelector(".spc-sitemap-root") || document.body;
                var isDark = mode === "dark" || (mode === "auto" && getSystemDark());
                root.classList.toggle("spc-dark", isDark);
                updateThemeBtn(mode);
                localStorage.setItem("spc_theme", mode);
            }
            function updateThemeBtn(mode) {
                var btn = document.getElementById("spc-theme-btn");
                if (!btn) return;
                if (mode === "dark")       btn.innerHTML = "☀️ <span class=\'spc-theme-label\'>Mode clair</span>";
                else if (mode === "light") btn.innerHTML = "🌙 <span class=\'spc-theme-label\'>Mode sombre</span>";
                else                       btn.innerHTML = "🌓 <span class=\'spc-theme-label\'>Thème auto</span>";
            }

            /* ── Conteneur racine pour le thème ── */
            var spcRoot = document.createElement("div");
            spcRoot.className = "spc-sitemap-root";
            summary.parentNode.insertBefore(spcRoot, summary);

            /* ── Bouton thème + recherche (au-dessus du wrapper) ── */
            var topZone = document.createElement("div");
            topZone.style.cssText = "display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;";

            var themeToggle = document.createElement("div");
            themeToggle.className = "spc-theme-toggle";
            themeToggle.innerHTML = \'<button id="spc-theme-btn" class="spc-theme-btn" title="Changer de thème">🌓 <span class="spc-theme-label">Thème auto</span></button>\';

            var searchBar = document.createElement("div");
            searchBar.className = "spc-search-bar";
            searchBar.innerHTML =
                \'<div class="spc-search-wrap">\' +
                    \'<span class="spc-search-icon">🔍</span>\' +
                    \'<input type="text" class="spc-search-input" placeholder="Rechercher un article…" autocomplete="off" id="spc-front-search">\' +
                    \'<button class="spc-search-clear" id="spc-search-clear" title="Effacer">✕</button>\' +
                    \'<span class="spc-search-count" id="spc-search-count"></span>\' +
                \'</div>\';

            topZone.appendChild(themeToggle);
            topZone.appendChild(searchBar);
            spcRoot.appendChild(topZone);

            /* ── Wrapper deux colonnes ── */
            var wrapper = document.createElement("div");
            wrapper.className = "sitemap-wrapper";
            spcRoot.appendChild(wrapper);
            wrapper.appendChild(summary);
            wrapper.appendChild(container);

            /* ── Appliquer le thème APRÈS que spcRoot existe dans le DOM ── */
            var savedTheme = localStorage.getItem("spc_theme") ||
                (typeof spcFront !== "undefined" && spcFront.theme ? spcFront.theme : "auto");
            applyTheme(savedTheme);
            updateThemeBtn(savedTheme);

            /* Écouter les changements système */
            if (window.matchMedia) {
                window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function() {
                    if ((localStorage.getItem("spc_theme") || "auto") === "auto") applyTheme("auto");
                });
            }

            /* Clic bouton thème */
            document.addEventListener("click", function(e) {
                if (!e.target.closest("#spc-theme-btn")) return;
                var modes = ["auto", "light", "dark"];
                var cur   = localStorage.getItem("spc_theme") || "auto";
                var next  = modes[(modes.indexOf(cur) + 1) % modes.length];
                applyTheme(next);
            });

            var links  = summary.querySelectorAll("a[data-cat]");
            var blocks = container.querySelectorAll(".cat-parent");

            /* ── Préférence vue ── */
            var savedView = localStorage.getItem("spc_view") || "list";

            /* ── URL cat ── */
            var urlCat = new URLSearchParams(window.location.search).get("spc-cat") || "";

            /* ── Accordéon ── */
            container.addEventListener("click", function(e) {
                var hdr = e.target.closest(".spc-node-header");
                if (hdr) { hdr.parentElement.classList.toggle("open"); return; }
            });

            /* ── Toggle vue ── */
            container.addEventListener("click", function(e) {
                var btn = e.target.closest(".spc-view-btn");
                if (!btn) return;
                var view = btn.getAttribute("data-view");
                localStorage.setItem("spc_view", view);
                blocks.forEach(function(b) { applyView(b, view); updateViewBtns(b, view); });
            });

            /* ── Tout ouvrir / fermer ── */
            container.addEventListener("click", function(e) {
                var btn = e.target.closest(".spc-expand-btn");
                if (!btn) return;
                var action = btn.getAttribute("data-action");
                btn.closest(".cat-parent").querySelectorAll(".spc-node").forEach(function(n) {
                    n.classList.toggle("open", action === "expand");
                });
            });

            /* ── Partage ── */
            container.addEventListener("click", function(e) {
                var btn = e.target.closest(".spc-share-btn");
                if (!btn) return;
                var cat   = btn.getAttribute("data-cat");
                var url   = window.location.href.split("?")[0] + "?spc-cat=" + encodeURIComponent(cat);
                var toast = btn.querySelector(".spc-share-toast");
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function() {
                        toast.classList.add("show");
                        setTimeout(function(){ toast.classList.remove("show"); }, 1800);
                    });
                } else { window.prompt("Lien de partage :", url); }
            });

            /* ── Tooltips ── */
            container.addEventListener("click", function(e) {
                var btn = e.target.closest(".spc-tooltip-btn");
                if (!btn) return;
                e.stopPropagation();
                var box    = btn.nextElementSibling;
                var isOpen = box.classList.contains("open");
                document.querySelectorAll(".spc-tooltip-box.open").forEach(function(b){ b.classList.remove("open"); });
                if (!isOpen) box.classList.add("open");
            });
            document.addEventListener("click", function() {
                document.querySelectorAll(".spc-tooltip-box.open").forEach(function(b){ b.classList.remove("open"); });
            });

            function applyView(block, view) {
                block.classList.toggle("spc-view-grid", view === "grid");
                block.classList.toggle("spc-view-list", view === "list");
            }
            function updateViewBtns(block, view) {
                block.querySelectorAll(".spc-view-btn").forEach(function(b) {
                    b.classList.toggle("active", b.getAttribute("data-view") === view);
                });
            }

            /* ── Topbar par bloc ── */
            blocks.forEach(function(block) {
                var h2      = block.querySelector("h2");
                var catName = h2 ? h2.textContent.trim() : "";
                var catSlug = block.getAttribute("data-cat");

                var bc = document.createElement("div");
                bc.className = "spc-breadcrumb";
                bc.innerHTML = \'<span>🏠 Plan du site</span><span class="sep">›</span><span>\' + catName + \'</span>\';
                block.insertBefore(bc, block.firstChild);

                var topbar = document.createElement("div");
                topbar.className = "spc-topbar";

                var titleEl = document.createElement("div");
                titleEl.className = "spc-topbar-title";
                titleEl.innerHTML = "📂 " + catName;

                var controls = document.createElement("div");
                controls.className = "spc-topbar-controls";
                controls.innerHTML =
                    \'<button class="spc-expand-btn" data-action="expand">&#8853; Tout ouvrir</button>\' +
                    \'<button class="spc-expand-btn" data-action="collapse">&#8854; Tout fermer</button>\' +
                    \'<button class="spc-share-btn" data-cat="\' + catSlug + \'" title="Copier le lien">🔗 Partager<span class="spc-share-toast">Lien copié !</span></button>\' +
                    \'<div class="spc-view-toggle">\' +
                        \'<button class="spc-view-btn" data-view="list" title="Vue liste">&#9776;</button>\' +
                        \'<button class="spc-view-btn" data-view="grid" title="Vue grille">&#8862;</button>\' +
                    \'</div>\';

                topbar.appendChild(titleEl);
                topbar.appendChild(controls);
                block.insertBefore(topbar, bc);

                applyView(block, savedView);
                updateViewBtns(block, savedView);
            });

            /* ── Ouvrir 1er nœud par défaut ── */
            blocks.forEach(function(block) {
                var first = block.querySelector(".spc-node");
                if (first) first.classList.add("open");
            });

            /* ── Compteurs nav ── */
            links.forEach(function(link) {
                var cat = link.getAttribute("data-cat");
                if (cat === "all") return;
                var block = container.querySelector(".cat-parent[data-cat=\"" + cat + "\"]");
                if (block) {
                    var badge = document.createElement("span");
                    badge.className = "nav-count";
                    badge.textContent = block.querySelectorAll(".spc-article").length;
                    link.appendChild(badge);
                }
            });

            /* ── Catégorie initiale ── */
            var initialCat = "";
            if (urlCat) {
                var found = container.querySelector(".cat-parent[data-cat=\"" + urlCat + "\"]");
                if (found) initialCat = urlCat;
            }
            if (!initialCat && blocks.length > 0) initialCat = blocks[0].getAttribute("data-cat");
            if (initialCat) {
                var initBlock = container.querySelector(".cat-parent[data-cat=\"" + initialCat + "\"]");
                var initLink  = summary.querySelector("a[data-cat=\"" + initialCat + "\"]");
                if (initBlock) initBlock.classList.add("visible");
                if (initLink)  initLink.classList.add("active");
            }

            /* ── Navigation ── */
            links.forEach(function(link) {
                link.addEventListener("click", function(e) {
                    e.preventDefault();
                    var target = this.getAttribute("data-cat");
                    links.forEach(function(l){ l.classList.remove("active"); });
                    link.classList.add("active");

                    if (history.replaceState) {
                        var newUrl = target === "all"
                            ? window.location.pathname
                            : window.location.pathname + "?spc-cat=" + encodeURIComponent(target);
                        history.replaceState(null, "", newUrl);
                    }

                    if (target === "all") {
                        container.classList.add("show-all");
                        blocks.forEach(function(b){ b.classList.add("visible"); b.classList.remove("hidden"); });
                    } else {
                        container.classList.remove("show-all");
                        blocks.forEach(function(b) {
                            var match = b.getAttribute("data-cat") === target;
                            b.classList.toggle("visible", match);
                            b.classList.toggle("hidden", !match);
                        });
                    }

                    /* Scroll vers le haut si hors écran */
                    var rect = spcRoot.getBoundingClientRect();
                    if (rect.top < 0) window.scrollTo({ top: spcRoot.getBoundingClientRect().top + window.pageYOffset - 20, behavior: "smooth" });

                    clearSearch();
                });
            });

            /* ══════════════════════
               RECHERCHE
            ══════════════════════ */
            var searchInput = document.getElementById("spc-front-search");
            var searchClear = document.getElementById("spc-search-clear");
            var searchCount = document.getElementById("spc-search-count");
            var noResults   = document.createElement("div");
            noResults.className = "spc-no-results";
            noResults.textContent = "Aucun article trouvé.";
            container.appendChild(noResults);

            function clearSearch() {
                searchInput.value = "";
                searchClear.classList.remove("visible");
                searchCount.textContent = "";
                noResults.classList.remove("visible");
                container.querySelectorAll(".spc-article").forEach(function(li){
                    li.style.display = "";
                    var t = li.querySelector(".spc-article-title");
                    if (t) t.innerHTML = t.textContent;
                });
                container.querySelectorAll(".spc-node").forEach(function(n){ n.style.display = ""; });
            }

            function escapeRe(s) { return s.replace(/[.*+?^${}()|[\]\\\\]/g, "\\\\$&"); }

            searchInput.addEventListener("input", function() {
                var q = this.value.trim();
                searchClear.classList.toggle("visible", q.length > 0);
                if (!q) { clearSearch(); return; }

                var re    = new RegExp("(" + escapeRe(q) + ")", "gi");
                var found = 0;

                container.querySelectorAll(".spc-node").forEach(function(n){ n.classList.add("open"); n.style.display = ""; });
                blocks.forEach(function(b){ b.classList.add("visible"); });

                container.querySelectorAll(".spc-article").forEach(function(li) {
                    var titleEl = li.querySelector(".spc-article-title");
                    var text    = titleEl ? titleEl.textContent : "";
                    if (text.toLowerCase().indexOf(q.toLowerCase()) !== -1) {
                        li.style.display = "";
                        if (titleEl) titleEl.innerHTML = text.replace(re, \'<mark class="spc-highlight">$1</mark>\');
                        found++;
                    } else {
                        li.style.display = "none";
                        if (titleEl) titleEl.innerHTML = text;
                    }
                });

                container.querySelectorAll(".spc-node").forEach(function(node) {
                    var vis = Array.from(node.querySelectorAll(".spc-article")).some(function(a){ return a.style.display !== "none"; });
                    node.style.display = vis ? "" : "none";
                });

                searchCount.textContent = found > 0 ? found + " résultat" + (found > 1 ? "s" : "") : "";
                noResults.classList.toggle("visible", found === 0);
            });

            searchClear.addEventListener("click", function() {
                searchInput.value = "";
                searchInput.dispatchEvent(new Event("input"));
                searchInput.focus();
            });

        });
        ');
    }


    /* ════════════════════════════════════════════════════════════
       SHORTCODE
    ════════════════════════════════════════════════════════════ */
    public function render_sitemap() {
        $excluded_ids = (array) get_option($this->option_name, []);
        $output       = '';

        $categories_niv1 = get_categories([
            'parent'     => 0,
            'hide_empty' => false,
            'exclude'    => $excluded_ids,
        ]);

        if (!empty($categories_niv1)) {
            $output .= '<div class="sitemap-summary"><ul>';
            $output .= '<li><a href="#" data-cat="all">Afficher tout</a></li>';
            foreach ($categories_niv1 as $cat) {
                $slug    = sanitize_title($cat->slug);
                $output .= '<li><a href="#" data-cat="' . esc_attr($slug) . '">' . esc_html($cat->name) . '</a></li>';
            }
            $output .= '</ul></div>';
        }

        $output .= '<div class="sitemap-categories">';
        foreach ($categories_niv1 as $cat_niv1) {
            $slug    = sanitize_title($cat_niv1->slug);
            $output .= '<div class="cat-parent" data-cat="' . esc_attr($slug) . '" id="cat-' . esc_attr($slug) . '">';
            $output .= '<h2>' . esc_html($cat_niv1->name) . '</h2>';
            $output .= $this->render_level($cat_niv1->term_id, 1);
            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
    }

    /* ════════════════════════════════════════════════════════════
       RENDU RÉCURSIF
    ════════════════════════════════════════════════════════════ */
    private function render_level($parent_id, $depth) {
        $output   = '';
        $depth    = min($depth, 4);
        $subcats  = get_categories(['parent' => $parent_id, 'hide_empty' => false]);

        $all_posts = get_posts([
            'category'       => $parent_id,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $subcat_ids   = array_column($subcats, 'term_id');
        $direct_posts = array_filter($all_posts, function($post) use ($subcat_ids) {
            if (empty($subcat_ids)) return true;
            $post_cats = wp_get_post_categories($post->ID);
            return empty(array_intersect($post_cats, $subcat_ids));
        });

        if (empty($subcats)) {
            $output .= '<ul class="spc-articles">';
            $output .= !empty($direct_posts)
                ? $this->render_articles(array_values($direct_posts))
                : '<li class="spc-article"><span class="spc-article-title" style="color:#aaa;font-style:italic">Aucun article</span></li>';
            $output .= '</ul>';
            return $output;
        }

        foreach ($subcats as $subcat) {
            $output .= '<div class="spc-node spc-depth-' . $depth . '">';
            $output .= '<div class="spc-node-header">';
            $output .= '<span>' . ($depth === 1 ? '📁' : '📂') . '</span>';
            $output .= '<span>' . esc_html($subcat->name) . '</span>';
            $output .= '<i class="spc-chevron">▶</i>';
            $output .= '</div>';
            $output .= '<div class="spc-node-body">';
            $output .= $this->render_level($subcat->term_id, $depth + 1);
            $output .= '</div>';
            $output .= '</div>';
        }

        if (!empty($direct_posts)) {
            $count   = count($direct_posts);
            $output .= '<div class="spc-node spc-depth-' . $depth . ' spc-node-misc">';
            $output .= '<div class="spc-node-header spc-node-header-misc">';
            $output .= '<span>📋</span>';
            $output .= '<span>Articles non classés (' . $count . ')</span>';
            $output .= '<i class="spc-chevron">▶</i>';
            $output .= '</div>';
            $output .= '<div class="spc-node-body"><ul class="spc-articles">';
            $output .= $this->render_articles(array_values($direct_posts));
            $output .= '</ul></div></div>';
        }

        return $output;
    }

    /* ════════════════════════════════════════════════════════════
       RENDU ARTICLES — avec miniatures, date, badge Nouveau, tooltip
    ════════════════════════════════════════════════════════════ */
    private function render_articles($posts) {
        $new_days = (int) get_option($this->new_days_option, 30);
        $cutoff   = strtotime("-{$new_days} days");
        $output   = '';

        foreach ($posts as $post) {
            $url   = get_permalink($post);
            $title = esc_html(get_the_title($post));
            $tid   = get_post_thumbnail_id($post->ID);
            $date  = get_the_date('d/m/Y', $post);
            $ts    = strtotime($post->post_date);
            $is_new = ($ts >= $cutoff);

            /* Extrait pour tooltip */
            $excerpt = '';
            if (has_excerpt($post->ID)) {
                $excerpt = wp_trim_words(get_the_excerpt($post), 20, '…');
            } else {
                $excerpt = wp_trim_words(strip_tags($post->post_content), 20, '…');
            }
            $excerpt = esc_html($excerpt);

            /* Miniatures */
            $thumb_list_url = $tid ? wp_get_attachment_image_url($tid, 'thumbnail') : '';
            $thumb_grid_url = $tid ? wp_get_attachment_image_url($tid, 'medium')    : '';

            $img_list = $thumb_list_url
                ? '<img class="spc-thumb-list" src="' . esc_url($thumb_list_url) . '" alt="' . $title . '" loading="lazy">'
                : '<span class="spc-thumb-ph-list">📄</span>';

            $img_grid = $thumb_grid_url
                ? '<img class="spc-thumb-grid" src="' . esc_url($thumb_grid_url) . '" alt="' . $title . '" loading="lazy">'
                : '<div class="spc-thumb-ph-grid">📄</div>';

            $output .= '<li class="spc-article">';
            $output .= '<span class="spc-img-list">' . $img_list . '</span>';
            $output .= '<span class="spc-img-grid">' . $img_grid . '</span>';

            $output .= '<span class="spc-article-info">';

            /* Titre + badge Nouveau */
            $output .= '<a class="spc-article-title" href="' . esc_url($url) . '">' . $title . '</a>';
            if ($is_new) {
                $output .= '<span class="spc-badge-new">Nouveau</span>';
            }

            /* Date */
            $output .= '<span class="spc-article-date">' . $date . '</span>';

            $output .= '</span>';

            /* Tooltip résumé */
            if ($excerpt) {
                $output .= '<span class="spc-tooltip-wrap">';
                $output .= '<button class="spc-tooltip-btn" title="Aperçu" aria-label="Voir le résumé">ⓘ</button>';
                $output .= '<div class="spc-tooltip-box">' . $excerpt . '</div>';
                $output .= '</span>';
            }

            $output .= '</li>';
        }
        return $output;
    }
}


/* ════════════════════════════════════════════════════════════════
   CLASSE XML — inchangée
════════════════════════════════════════════════════════════════ */
class Sitemap_Par_Categorie_XML {
    private $elements_option = 'sitemap_par_categorie_xml_elements';

    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('template_redirect', [$this, 'maybe_render_sitemap']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_footer', [$this, 'add_admin_script']);
        $this->hook_into_robots_txt();
    }

    public function hook_into_robots_txt() {
        add_filter('robots_txt', [$this, 'ajout_ligne_robots_txt'], 10, 2);
    }
    public function ajout_ligne_robots_txt($output, $public) {
        if ($public === '0') return $output;
        $output .= "\nSitemap: " . home_url('/?sitemap_par_categorie_xml=1') . "\n";
        return $output;
    }
    public function add_rewrite_rule() {
        add_rewrite_rule('^sitemap-par-categorie\\.xml$', 'index.php?sitemap_par_categorie_xml=1', 'top');
    }
    public function add_query_var($vars) {
        $vars[] = 'sitemap_par_categorie_xml';
        return $vars;
    }

    public function maybe_render_sitemap() {
        if (intval(get_query_var('sitemap_par_categorie_xml')) !== 1) return;
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/xml; charset=UTF-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        $elements            = get_option($this->elements_option, ['posts', 'categories']);
        $excluded_ids        = (array) get_option('sitemap_par_categorie_exclusions', []);
        $freq_posts          = get_option('sitemap_par_categorie_xml_freq_posts', 'weekly');
        $freq_pages          = get_option('sitemap_par_categorie_xml_freq_pages', 'monthly');
        $freq_categories     = get_option('sitemap_par_categorie_xml_freq_categories', 'weekly');
        $prio_posts          = get_option('sitemap_par_categorie_xml_prio_posts', '0.8');
        $prio_pages          = get_option('sitemap_par_categorie_xml_prio_pages', '0.5');
        $prio_categories     = get_option('sitemap_par_categorie_xml_prio_categories', '0.6');
        $excluded_pages      = get_option('sitemap_par_categorie_xml_exclude_page_ids', []);
        $excluded_categories = get_option('sitemap_par_categorie_xml_exclude_category_ids', []);
        $all_excluded        = array_merge($excluded_ids, $excluded_categories);

        if (in_array('categories', $elements)) {
            foreach (get_categories(['hide_empty' => false, 'exclude' => $all_excluded]) as $cat) {
                $posts = get_posts(['category' => $cat->term_id, 'posts_per_page' => 1]);
                if (empty($posts)) continue;
                echo "  <url>\n    <loc>" . esc_url(get_category_link($cat)) . "</loc>\n";
                echo "    <lastmod>" . get_the_modified_date('c', $posts[0]) . "</lastmod>\n";
                echo "    <changefreq>{$freq_categories}</changefreq>\n    <priority>{$prio_categories}</priority>\n  </url>\n";
            }
        }
        if (in_array('posts', $elements)) {
            foreach (get_posts(['posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'modified', 'category__not_in' => $all_excluded]) as $post) {
                echo "  <url>\n    <loc>" . esc_url(get_permalink($post)) . "</loc>\n";
                echo "    <lastmod>" . get_the_modified_date('c', $post) . "</lastmod>\n";
                echo "    <changefreq>{$freq_posts}</changefreq>\n    <priority>{$prio_posts}</priority>\n  </url>\n";
            }
        }
        if (in_array('pages', $elements)) {
            foreach (get_pages(['post_status' => 'publish']) as $page) {
                if (in_array($page->ID, $excluded_pages)) continue;
                echo "  <url>\n    <loc>" . esc_url(get_permalink($page)) . "</loc>\n";
                echo "    <lastmod>" . get_the_modified_date('c', $page) . "</lastmod>\n";
                echo "    <changefreq>{$freq_pages}</changefreq>\n    <priority>{$prio_pages}</priority>\n  </url>\n";
            }
        }
        echo "</urlset>";
        exit;
    }

    public function register_settings() {
        register_setting('sitemap_par_categorie_settings', $this->elements_option);
        foreach (['freq_posts','freq_pages','freq_categories','prio_posts','prio_pages','prio_categories'] as $k)
            register_setting('sitemap_par_categorie_settings', 'sitemap_par_categorie_xml_' . $k);
        register_setting('sitemap_par_categorie_settings', 'sitemap_par_categorie_xml_exclude_page_ids');
        register_setting('sitemap_par_categorie_settings', 'sitemap_par_categorie_xml_exclude_category_ids');

        add_settings_section('sitemap_par_categorie_xml', 'Options du Sitemap XML',
            function(){ echo '<p>Fréquences, priorités et exclusions.</p>'; }, 'sitemap-par-categorie-xml');
        add_settings_field('xml_include_elements', 'Éléments', [$this,'render_checkboxes'], 'sitemap-par-categorie-xml', 'sitemap_par_categorie_xml');
        foreach (['posts'=>'Articles','pages'=>'Pages','categories'=>'Catégories'] as $t => $l) {
            $this->add_frequency_field($t, $l);
            $this->add_priority_field($t, $l);
        }
        add_settings_field('xml_exclude_pages', 'Pages à exclure', [$this,'render_exclude_pages_checkboxes'], 'sitemap-par-categorie-xml', 'sitemap_par_categorie_xml');
        add_settings_field('xml_exclude_cats', 'Catégories à exclure', [$this,'render_exclude_categories_checkboxes'], 'sitemap-par-categorie-xml', 'sitemap_par_categorie_xml');
    }

    public function render_checkboxes() {
        $opts = get_option($this->elements_option, ['posts','categories']);
        foreach (['posts'=>'Articles','pages'=>'Pages','categories'=>'Catégories'] as $k => $l) {
            $c = in_array($k, $opts) ? 'checked' : '';
            echo "<label style='display:block;margin-bottom:5px'><input type='checkbox' name='{$this->elements_option}[]' value='{$k}' {$c}> {$l}</label>";
        }
    }
    private function add_frequency_field($t,$l) {
        add_settings_field("xml_freq_{$t}", "Fréquence – {$l}", function() use($t){ $this->render_frequency_dropdown("sitemap_par_categorie_xml_freq_{$t}"); }, 'sitemap-par-categorie-xml', 'sitemap_par_categorie_xml');
    }
    private function add_priority_field($t,$l) {
        add_settings_field("xml_prio_{$t}", "Priorité – {$l}", function() use($t){ $this->render_priority_input("sitemap_par_categorie_xml_prio_{$t}"); }, 'sitemap-par-categorie-xml', 'sitemap_par_categorie_xml');
    }
    public function render_frequency_dropdown($name) {
        $val = get_option($name, 'weekly');
        echo "<select name='{$name}'>";
        foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $c)
            echo "<option value='{$c}'" . selected($val,$c,false) . ">{$c}</option>";
        echo "</select>";
    }
    public function render_priority_input($name) {
        echo "<input type='number' name='{$name}' value='" . get_option($name,'0.6') . "' step='0.1' min='0.0' max='1.0'>";
    }
    public function render_exclude_pages_checkboxes() {
        $excl = get_option('sitemap_par_categorie_xml_exclude_page_ids', []);
        echo '<button type="button" onclick="toggleAllCheckboxes(\'exclude-pages\',true)">Tout cocher</button> ';
        echo '<button type="button" onclick="toggleAllCheckboxes(\'exclude-pages\',false)">Tout décocher</button>';
        echo '<div id="exclude-pages" style="border:1px solid #ccc;padding:10px;margin-top:8px;max-height:200px;overflow:auto">';
        foreach (get_pages(['post_status'=>'publish']) as $p) {
            $c = in_array($p->ID, $excl) ? 'checked' : '';
            echo "<label style='display:block;margin-bottom:4px'><input type='checkbox' name='sitemap_par_categorie_xml_exclude_page_ids[]' value='{$p->ID}' {$c}> {$p->post_title} <code>({$p->post_name})</code></label>";
        }
        echo '</div>';
    }
    public function render_exclude_categories_checkboxes() {
        $excl = get_option('sitemap_par_categorie_xml_exclude_category_ids', []);
        echo '<button type="button" onclick="toggleAllCheckboxes(\'exclude-categories\',true)">Tout cocher</button> ';
        echo '<button type="button" onclick="toggleAllCheckboxes(\'exclude-categories\',false)">Tout décocher</button>';
        echo '<div id="exclude-categories" style="border:1px solid #ccc;padding:10px;margin-top:8px;max-height:200px;overflow:auto">';
        foreach (get_categories(['hide_empty'=>false]) as $cat) {
            $c = in_array($cat->term_id, $excl) ? 'checked' : '';
            echo "<label style='display:block;margin-bottom:4px'><input type='checkbox' name='sitemap_par_categorie_xml_exclude_category_ids[]' value='{$cat->term_id}' {$c}> {$cat->name} <code>({$cat->slug})</code></label>";
        }
        echo '</div>';
    }
    public function add_admin_script() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'sitemap-par-categorie') return;
        echo '<script>function toggleAllCheckboxes(id,state){document.getElementById(id).querySelectorAll("input[type=checkbox]").forEach(function(cb){cb.checked=state;});}</script>';
    }
}

new Sitemap_Par_Categorie();
new Sitemap_Par_Categorie_XML();
