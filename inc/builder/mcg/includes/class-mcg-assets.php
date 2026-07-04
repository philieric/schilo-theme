<?php
namespace MCG;

if (!defined('ABSPATH')) exit;

final class Assets {
    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register']);
    }

    public static function register(): void {
        wp_register_style('mcg', MCG_URL . 'assets/mcg.css', [], MCG_VERSION);
        wp_register_script('mcg', MCG_URL . 'assets/mcg.js', ['jquery'], MCG_VERSION, true);

        wp_localize_script('mcg', 'MCG', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mcg_nonce'),
        ]);
    }
}
