<?php
namespace MCG;

if (!defined('ABSPATH')) exit;

final class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        require_once MCG_PATH . 'includes/class-mcg-assets.php';
        require_once MCG_PATH . 'includes/class-mcg-repository.php';
        require_once MCG_PATH . 'includes/class-mcg-shortcode.php';
        require_once MCG_PATH . 'includes/class-mcg-ajax.php';
        require_once MCG_PATH . 'includes/class-mcg-templates.php';
        Templates::init();

        Assets::init();
        Shortcode::init();
        Ajax::init();
    }
}
