<?php
/**
 * Plugin Name: Modern Category Grid (MCG)
 * Description: Grille moderne d’articles par catégorie avec filtres, tri et pagination AJAX.
 * Version: 1.0.0
 * Author: Eric Philippot
 */

if (!defined('ABSPATH')) exit;

define('MCG_VERSION', '1.0.0');
define('MCG_PATH', plugin_dir_path(__FILE__));
define('MCG_URL', plugin_dir_url(__FILE__));

require_once MCG_PATH . 'includes/class-mcg-plugin.php';

add_action('plugins_loaded', function () {
    \MCG\Plugin::instance();
});
