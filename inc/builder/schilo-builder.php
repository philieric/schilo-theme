<?php
/**
 * Plugin Name: Schilo Builder
 * Plugin URI: https://schilo.org
 * Description: Builder maison multi-sections pour remplacer progressivement WPBakery.
 * Version: 0.8.4
 * Author: Ã‰ric Philippot
 * Text Domain: schilo-builder
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCHILO_BUILDER_VERSION', '0.52.4');
define('SCHILO_BUILDER_FILE', __FILE__);
define('SCHILO_BUILDER_PATH', plugin_dir_path(__FILE__));
define('SCHILO_BUILDER_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'Schilo\\Builder\\';
    $baseDir = SCHILO_BUILDER_PATH . 'src/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', function () {
    if (class_exists('\\Schilo\\Builder\\Core\\Plugin')) {
        $plugin = new \Schilo\Builder\Core\Plugin();
        $plugin->run();
    }
});



