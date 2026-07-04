<?php
if (!defined('ABSPATH')) exit;

get_header();

$term   = get_queried_object();
$cat_id = isset($term->term_id) ? (int)$term->term_id : 0;
$title  = single_cat_title('', false);
$desc   = !empty($term->description) ? wp_kses_post(wpautop($term->description)) : '';

?>

<div class="wikilogy-wrapper boxed-false" id="general-wrapper">
  <div class="site-content">

    <div class="site-sub-content">

      <div class="title-banner style-2">
        <div class="page-title-background"></div>
        <div class="content">
          <div class="wikilogy-title style-1">
            <div class="title-text">
              <div class="title"><?php echo esc_html($title); ?></div>
            </div>
          </div>

          
        </div>
      </div>
      <?php if ($desc): ?>
        <div class="mcg-container-description"><?php echo $desc; ?></div>
      <?php endif; ?>
      <div class="mcg-container-archive">
        <div class="mgc-container">
          <div class="row">

            <!-- CONTENU PRINCIPAL -->
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 fullwidthsidebar">
              <div class="archive-post-list-style-2 post-list">
                <div class="mgc-container-post">

                  <?php
                  // Ici on met TON rendu (liste style-2)
                 echo do_shortcode('[mcg_grid cat="' . esc_attr($cat_id) . '" per_page="12" orderby="title" show_filters="1" show_sort="1" mode="loadmore"]');
                  ?>

                </div>
              </div>
            </div>

            <!-- SIDEBAR (si ton thème l’affiche sur les archives) -->
            <?php /*
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 hide fixed-sidebar">
              <?php get_sidebar(); ?>
            </div>
            */ ?>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php
get_footer();
