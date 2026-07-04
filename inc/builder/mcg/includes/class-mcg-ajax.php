<?php
namespace MCG;

if (!defined('ABSPATH')) exit;

final class Ajax {

  public static function init(): void {
    add_action('wp_ajax_mcg_fetch', [__CLASS__, 'fetch']);
    add_action('wp_ajax_nopriv_mcg_fetch', [__CLASS__, 'fetch']);
  }

  public static function fetch(): void {
    check_ajax_referer('mcg_nonce', 'nonce');

    $page     = max(1, (int)($_POST['page'] ?? 1));
    $cat      = (int)($_POST['cat'] ?? 0);
    $per_page = max(1, min(48, (int)($_POST['per_page'] ?? 9)));
    $orderby  = sanitize_key($_POST['orderby'] ?? 'date');

    // ✅ Offset = (page-1) * per_page
    $offset = ($page - 1) * $per_page;

    $queryArgs = [
      'post_type'           => 'post',
      'post_status'         => 'publish',
      'posts_per_page'      => $per_page,
      'offset'              => $offset,
      'ignore_sticky_posts' => true,
    ];

    if ($cat > 0) {
      $queryArgs['tax_query'] = [[
        'taxonomy' => 'category',
        'field'    => 'term_id',
        'terms'    => [$cat],
      ]];
    }

    // tri (cohérent avec le shortcode)
    switch ($orderby) {
      case 'title':
        $queryArgs['orderby'] = 'title';
        $queryArgs['order']   = 'ASC';
        break;

      case 'rand':
        $queryArgs['orderby'] = 'rand';
        break;

      case 'popular':
        $queryArgs['meta_key'] = 'mcg_views';
        $queryArgs['orderby']  = 'meta_value_num';
        $queryArgs['order']    = 'DESC';
        break;

      case 'date':
      default:
        $queryArgs['orderby'] = 'date';
        $queryArgs['order']   = 'DESC';
        break;
    }

    $q = new \WP_Query($queryArgs);

    ob_start();
    if ($q->have_posts()) :
      while ($q->have_posts()) : $q->the_post(); ?>
        <article class="mcg-card">
          <a href="<?php the_permalink(); ?>" class="mcg-thumb">
            <?php the_post_thumbnail('medium_large'); ?>
          </a>
          <div class="mcg-body">
            <h3 class="mcg-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <p class="mcg-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 25)); ?></p>
          </div>
        </article>
      <?php endwhile;
    endif;
    wp_reset_postdata();

    // ✅ Calcul du “max pages” basé sur le total (FOUND_POSTS)
    $total = (int)$q->found_posts;
    $max   = (int)ceil($total / $per_page);

    wp_send_json_success([
      'html' => ob_get_clean(),
      'page' => $page,
      'max'  => $max,
    ]);
  }
}
