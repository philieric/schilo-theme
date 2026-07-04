<?php
if (!defined('ABSPATH')) exit;

class Sitemap_Par_Categorie_HTML {
    private $option_name = 'sitemap_par_categorie_exclusions';

    public function __construct() {
        add_shortcode('sitemap_par_categorie', [$this, 'render_sitemap']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        Sitemap_Par_Categorie_Admin::register_section([$this, 'register_settings']);
    }

    public function enqueue_styles() {
        wp_register_style('sitemap-par-categorie-style', false);
        wp_enqueue_style('sitemap-par-categorie-style');
        wp_add_inline_style("sitemap-par-categorie-style', '/* CSS compacté pour l'exemple */");
        wp_register_script('sitemap-par-categorie-script', false);
        wp_enqueue_script('sitemap-par-categorie-script');
        wp_add_inline_script("sitemap-par-categorie-script', '/* JS compacté pour l'exemple */");
    }

    public function render_sitemap() {
        $excluded_ids = get_option($this->option_name, []);
        $output = '';
        $categories_niv1 = get_categories([
            'parent' => 0,
            'hide_empty' => false,
            'exclude' => $excluded_ids,
        ]);
        if (!empty($categories_niv1)) {
            $output .= '<div class="sitemap-summary"><ul>';
            $output .= '<li><a href="#" data-cat="all">Afficher tout</a></li>';
            foreach ($categories_niv1 as $cat) {
                $slug = sanitize_title($cat->slug);
                $output .= '<li><a href="#" data-cat="' . esc_attr($slug) . '">' . esc_html($cat->name) . '</a></li>';
            }
            $output .= '</ul></div>';
        }
        $output .= '<div class="sitemap-categories">';
        foreach ($categories_niv1 as $cat_niv1) {
            $slug = sanitize_title($cat_niv1->slug);
            $output .= '<div class="cat-parent" data-cat="' . esc_attr($slug) . '" id="cat-' . esc_attr($slug) . '">';
            $output .= '<h2>' . esc_html($cat_niv1->name) . '</h2>';
            $subcategories = get_categories(['parent' => $cat_niv1->term_id, 'hide_empty' => false]);
            if (!empty($subcategories)) {
                foreach ($subcategories as $subcat) {
                    $output .= '<h3>' . esc_html($subcat->name) . '</h3><ul>';
                    $output .= $this->get_articles_list($subcat->term_id);
                    $output .= '</ul>';
                }
            } else {
                $output .= '<ul>' . $this->get_articles_list($cat_niv1->term_id) . '</ul>';
            }
            $output .= '</div>';
        }
        $output .= '</div>';
        return $output;
    }

    private function get_articles_list($category_id) {
        $posts = get_posts([
            'category' => $category_id,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if (empty($posts)) return '<li><em>Aucun article</em></li>';
        $list = '';
        foreach ($posts as $post) {
            $url = get_permalink($post);
            $title = esc_html(get_the_title($post));
            $list .= "<li><a href='{$url}'>{$title}</a></li>";
        }
        return $list;
    }

    public function register_settings() {
        add_settings_section(
            'sitemap_par_categorie_main',
            'Catégories à exclure',
            function () {
                echo '<p>Sélectionnez les catégories à exclure du sitemap HTML.</p>';
            },
            'sitemap-par-categorie'
        );
        add_settings_field(
            'sitemap_par_categorie_field',
            'Catégories',
            [$this, 'render_category_checklist'],
            'sitemap-par-categorie',
            'sitemap_par_categorie_main'
        );
    }

    public function render_category_checklist() {
        $excluded_ids = get_option($this->option_name, []);
        $all_categories = get_categories(['hide_empty' => false]);
        echo '<div style="border: 1px solid #ccc; padding: 10px;">';
        foreach ($all_categories as $category) {
            $checked = in_array($category->term_id, $excluded_ids) ? 'checked' : '';
            echo '<label style="display:block;margin-bottom:5px;">';
            echo "<input type='checkbox' name='{$this->option_name}[]' value='{$category->term_id}' {$checked}> ";
            echo esc_html($category->name);
            echo '</label>';
        }
        echo '</div>';
    }
}
