<?php
if (!defined('ABSPATH')) exit;

class Sitemap_Par_Categorie_Admin {
    private static $sections = [];

    public function __construct() {
        // admin_menu géré par Schilo Builder (SettingsPage)
        add_action('admin_init', [$this, 'register_all_sections']);
    }

    public function register_admin_menu() {
        add_menu_page(
            'Sitemap Catégorie',
            'Sitemap Catégorie',
            'manage_options',
            'sitemap-par-categorie',
            [$this, 'render_admin_page'],
            'dashicons-list-view',
            26
        );
    }

    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'html';
        $tabs = [
            'html' => 'Catégories HTML',
            'xml'  => 'Sitemap XML',
            'preview' => 'Aperçu XML'
        ];
        ?>
        <div class="wrap">
            <h1>Sitemap par Catégorie – Paramètres</h1>
            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab => $label): ?>
                    <a href="?page=sitemap-par-categorie&tab=<?php echo esc_attr($tab); ?>" class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php if ($current_tab === 'preview') : ?>
                <div style="background: #fff; border: 1px solid #ccc; padding: 1rem; margin-top: 1rem;">
                    <p><strong>URL du sitemap XML :</strong> <a href="<?php echo esc_url(home_url('/sitemap-par-categorie.xml')); ?>" target="_blank">
                        <?php echo esc_url(home_url('/sitemap-par-categorie.xml')); ?>
                    </a></p>
                    <iframe src="<?php echo esc_url(home_url('/sitemap-par-categorie.xml')); ?>" width="100%" height="400" style="border:1px solid #ccc;"></iframe>
                </div>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('sitemap_par_categorie_settings');
                    if ($current_tab === 'html') {
                        do_settings_sections('sitemap-par-categorie');
                    } elseif ($current_tab === 'xml') {
                        do_settings_sections('sitemap-par-categorie-xml');
                    }
                    submit_button();
                    ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function register_section($callback) {
        self::$sections[] = $callback;
    }

    public function register_all_sections() {
        register_setting('sitemap_par_categorie_settings', 'sitemap_par_categorie_exclusions');
        foreach (self::$sections as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback);
            }
        }
    }
}
