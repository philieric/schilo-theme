<?php
if (!defined('ABSPATH')) exit;

class Sitemap_Par_Categorie_XML {
    private $option_name = 'sitemap_par_categorie_xml_options';

    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('template_redirect', [$this, 'maybe_render_sitemap']);
        Sitemap_Par_Categorie_Admin::register_section([$this, 'register_admin_section']);
    }

    public function add_rewrite_rule() {
        add_rewrite_rule('^sitemap-par-categorie\.xml$', 'index.php?sitemap_par_categorie_xml=1', 'top');
    }

    public function add_query_var($vars) {
        $vars[] = 'sitemap_par_categorie_xml';
        return $vars;
    }

    public function maybe_render_sitemap() {
        if (intval(get_query_var('sitemap_par_categorie_xml')) !== 1) return;

        $opts = get_option($this->option_name, []);
        $excluded_ids = get_option('sitemap_par_categorie_exclusions', []);
        $elements = $opts['elements'] ?? ['posts', 'categories'];
        $changefreq = $opts['changefreq'] ?? 'weekly';
        $priority = $opts['priority'] ?? '0.8';

        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "
";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "
";

        if (in_array('categories', $elements)) {
            $categories = get_categories([
                'hide_empty' => false,
                'exclude' => $excluded_ids,
            ]);

            foreach ($categories as $cat) {
                if (!$this->has_articles($cat->term_id)) continue;
                echo "  <url>
";
                echo "    <loc>" . esc_url(get_category_link($cat)) . "</loc>
";
                echo "    <lastmod>" . esc_html($this->get_lastmod_category($cat->term_id)) . "</lastmod>
";
                echo "    <changefreq>{$changefreq}</changefreq>
";
                echo "    <priority>0.6</priority>
";
                echo "  </url>
";
            }
        }

        if (in_array('posts', $elements)) {
            $posts = get_posts([
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'modified',
                'category__not_in' => $excluded_ids,
            ]);

            foreach ($posts as $post) {
                echo "  <url>
";
                echo "    <loc>" . esc_url(get_permalink($post)) . "</loc>
";
                echo "    <lastmod>" . get_the_modified_date('c', $post) . "</lastmod>
";
                echo "    <changefreq>{$changefreq}</changefreq>
";
                echo "    <priority>{$priority}</priority>
";
                echo "  </url>
";
            }
        }

        if (in_array('pages', $elements)) {
            $pages = get_pages(['post_status' => 'publish']);
            foreach ($pages as $page) {
                echo "  <url>
";
                echo "    <loc>" . esc_url(get_permalink($page)) . "</loc>
";
                echo "    <lastmod>" . get_the_modified_date('c', $page) . "</lastmod>
";
                echo "    <changefreq>{$changefreq}</changefreq>
";
                echo "    <priority>{$priority}</priority>
";
                echo "  </url>
";
            }
        }

        echo "</urlset>";
        exit;
    }

    private function has_articles($cat_id) {
        return count(get_posts(['posts_per_page' => 1, 'category' => $cat_id, 'post_status' => 'publish'])) > 0;
    }

    private function get_lastmod_category($cat_id) {
        $posts = get_posts([
            'category' => $cat_id,
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'post_status' => 'publish',
        ]);
        return $posts ? get_the_modified_date('c', $posts[0]) : current_time('c');
    }

    public function register_admin_section() {
        register_setting('sitemap_par_categorie_settings', $this->option_name);

        add_settings_section(
            'sitemap_par_categorie_xml',
            'Options du Sitemap XML',
            function () {
                echo '<p>Configurer les options SEO du sitemap XML généré automatiquement.</p>';
            },
            'sitemap-par-categorie'
        );

        add_settings_field(
            'xml_include_elements',
            'Éléments à inclure',
            [$this, 'render_include_elements'],
            'sitemap-par-categorie',
            'sitemap_par_categorie_xml'
        );

        add_settings_field(
            'xml_changefreq',
            'Fréquence de mise à jour',
            [$this, 'render_changefreq'],
            'sitemap-par-categorie',
            'sitemap_par_categorie_xml'
        );

        add_settings_field(
            'xml_priority',
            'Priorité des éléments',
            [$this, 'render_priority'],
            'sitemap-par-categorie',
            'sitemap_par_categorie_xml'
        );
    }

    public function render_include_elements() {
        $opts = get_option($this->option_name, []);
        $elements = $opts['elements'] ?? ['posts', 'categories'];
        $items = [
            'posts' => 'Articles',
            'pages' => 'Pages',
            'categories' => 'Catégories'
        ];
        foreach ($items as $key => $label) {
            $checked = in_array($key, $elements) ? 'checked' : '';
            echo "<label style='display:block; margin-bottom:5px;'>
                    <input type='checkbox' name='{$this->option_name}[elements][]' value='{$key}' {$checked}>
                    {$label}
                  </label>";
        }
    }

    public function render_changefreq() {
        $opts = get_option($this->option_name, []);
        $val = $opts['changefreq'] ?? 'weekly';
        $options = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        echo "<select name='{$this->option_name}[changefreq]'>";
        foreach ($options as $o) {
            $selected = ($o === $val) ? 'selected' : '';
            echo "<option value='{$o}' {$selected}>{$o}</option>";
        }
        echo "</select>";
    }

    public function render_priority() {
        $opts = get_option($this->option_name, []);
        $priority = $opts['priority'] ?? '0.8';
        echo "<input type='number' name='{$this->option_name}[priority]' min='0.1' max='1.0' step='0.1' value='{$priority}'> (de 0.1 à 1.0)";
    }
}
