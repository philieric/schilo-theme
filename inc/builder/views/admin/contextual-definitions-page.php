<?php defined('ABSPATH') || exit; ?>
<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Définitions contextuelles</h1>
    <p>Transforme automatiquement les termes rencontrés dans les articles en définitions modales issues des fiches d’information.</p>
    <?php if ($saved) : ?><div class="notice notice-success is-dismissible"><p>Configuration enregistrée.</p></div><?php endif; ?>
    <form method="post">
        <?php wp_nonce_field('schilo_builder_save_definitions'); ?>
        <div class="schilo-settings-card">
            <h2>Réglages généraux</h2>
            <p><label><input type="checkbox" name="schilo_definitions[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> Activer les définitions contextuelles</label></p>
            <p><label><strong>Préfixes des fiches sources</strong><br><input class="regular-text" name="schilo_definitions[prefixes]" value="<?php echo esc_attr($settings['prefixes']); ?>" placeholder="INF"><br><span class="description">Séparer plusieurs préfixes par des virgules, par exemple : INF, GLO.</span></label></p>
        </div>
        <div class="schilo-settings-card">
            <h2>Fiches détectées <span class="count">(<?php echo count($sources); ?>)</span></h2>
            <p class="description">Le terme est déduit du titre. Saisissez des variantes séparées par des virgules pour corriger ou enrichir la détection.</p>
            <table class="widefat striped">
                <thead><tr><th style="width:70px">Actif</th><th style="width:90px">Code</th><th>Fiche source</th><th>Termes déclencheurs</th></tr></thead>
                <tbody>
                <?php foreach ($sources as $source) :
                    $row = $settings['definitions'][$source->ID] ?? array();
                    $enabled = !is_array($row) || !array_key_exists('enabled', $row) || !empty($row['enabled']);
                    $terms = !empty($row['terms']) ? $row['terms'] : $this->service->deriveTerms($source->post_title);
                ?>
                    <tr>
                        <td><input type="checkbox" name="schilo_definitions[definitions][<?php echo (int)$source->ID; ?>][enabled]" value="1" <?php checked($enabled); ?>></td>
                        <td><strong><?php echo esc_html($this->service->extractCode($source->post_title)); ?></strong></td>
                        <td><a href="<?php echo esc_url(get_edit_post_link($source->ID)); ?>"><?php echo esc_html($source->post_title); ?></a></td>
                        <td><input class="large-text" name="schilo_definitions[definitions][<?php echo (int)$source->ID; ?>][terms]" value="<?php echo esc_attr(implode(', ', $terms)); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php submit_button('Enregistrer les définitions'); ?>
    </form>
</div>

