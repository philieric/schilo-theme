<?php

namespace Schilo\Builder\Front;

class RelatedArticlesShortcode
{
    public function register()
    {
        add_shortcode('schilo_articles_lies', array($this, 'render'));
    }

    public function render($atts)
    {
        $atts = shortcode_atts(array(
            'category' => 0,
            'count' => 6,
        ), $atts, 'schilo_articles_lies');

        $category = (int) $atts['category'];
        $count = max(1, min(20, (int) $atts['count']));

        if ($category <= 0) {
            return '';
        }

        $query = new \WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'cat' => $category,
            'posts_per_page' => $count,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (!$query->have_posts()) {
            return '';
        }

        ob_start();

        echo '<div class="schilo-related-articles">';
        echo '<ul>';

        while ($query->have_posts()) {
            $query->the_post();

            echo '<li><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></li>';
        }

        echo '</ul>';
        echo '</div>';

        wp_reset_postdata();

        return (string) ob_get_clean();
    }
}
