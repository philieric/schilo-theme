<?php
namespace MCG;

if (!defined('ABSPATH')) exit;

final class Templates {

  public static function init(): void {
    add_filter('template_include', [__CLASS__, 'maybe_override_category_template'], 99);
  }

  public static function maybe_override_category_template(string $template): string {
    if (!is_category()) return $template;

    $pluginTpl = MCG_PATH . 'templates/category-archive.php';
    if (file_exists($pluginTpl)) return $pluginTpl;

    return $template;
  }
}
?>