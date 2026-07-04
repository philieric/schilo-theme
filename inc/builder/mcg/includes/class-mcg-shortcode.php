<?php
namespace MCG;

if (!defined('ABSPATH')) exit;

final class Shortcode {

    public static function init(): void {
        add_shortcode('mcg_grid', [__CLASS__, 'render']);
    }

    public static function render($atts): string {
        $atts = shortcode_atts([
            'cat' => 0,
            'per_page' => 9,
            'orderby' => 'date',
            'show_filters' => 1,
            'show_sort' => 1,
            'mode' => 'loadmore',
        ], $atts);

        wp_enqueue_style('mcg');
        wp_enqueue_script('mcg');

        $q = Repository::get_posts([
            'cat' => (int)$atts['cat'],
            'per_page' => (int)$atts['per_page'],
            'orderby' => $atts['orderby'],
        ]);

        ob_start(); ?>
            <div class="mcg"
                data-cat="<?php echo esc_attr($atts['cat']); ?>"
                data-per-page="<?php echo esc_attr($atts['per_page']); ?>"
                data-orderby="<?php echo esc_attr($atts['orderby']); ?>"
                data-mode="<?php echo esc_attr($atts['mode']); ?>"
                data-page="1">

            <div class="mcg-grid">
                <?php
                if ($q->have_posts()) :
                    while ($q->have_posts()) : $q->the_post(); ?>
                       <article class="mcg-card">
                        <a href="<?php the_permalink(); ?>" class="mcg-thumb">
                            <?php the_post_thumbnail('medium_large'); ?>
                        </a>
                        <div class="mcg-body">
                            <h3 class="mcg-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p class="mcg-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 25)); ?></p>
                            
                        </article>
                    <?php endwhile;
                else :
                    echo '<p>Aucun article.</p>';
                endif;
                wp_reset_postdata();
                ?>
            </div>
            <button class="mcg-loadmore" type="button">Voir plus</button>
        </div>
        <?php
        return ob_get_clean();
    }
}
