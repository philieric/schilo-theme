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
            <p>
                <label><input type="checkbox" name="schilo_definitions[include_biblical_references]" value="1" <?php checked(!empty($settings['include_biblical_references'])); ?>> Autoriser les déclencheurs dans le texte des références bibliques</label><br>
                <span class="description">Désactivez cette option pour limiter les déclencheurs au contenu rédactionnel des articles.</span>
            </p>
            <p><label><strong>Préfixes des fiches sources</strong><br><input class="regular-text" name="schilo_definitions[prefixes]" value="<?php echo esc_attr($settings['prefixes']); ?>" placeholder="INF"><br><span class="description">Séparer plusieurs préfixes par des virgules, par exemple : INF, GLO.</span></label></p>
            <p><?php submit_button('Enregistrer les réglages généraux', 'primary', 'submit', false); ?></p>
        </div>
        <div class="schilo-settings-card">
            <h2>Fiches détectées <span class="count">(<?php echo count($sources); ?>)</span></h2>
            <p class="description"><strong>Un déclencheur par ligne.</strong> Le terme principal et sa variante singulier/pluriel sont déjà proposés depuis le titre, sans article ni ponctuation. L’apostrophe et la ponctuation des références bibliques sont conservées. La détection ne tient pas compte des majuscules et minuscules.</p>
            <p>
                <label for="schilo-definition-provider"><strong>Service IA pour les suggestions</strong></label>
                <select id="schilo-definition-provider">
                    <option value="claude">Claude AI</option>
                    <option value="openai">ChatGPT</option>
                </select>
                <button type="button" id="schilo-definition-suggest-all" class="button button-primary">
                    <span class="dashicons dashicons-superhero" style="font-size:16px;height:16px;width:16px;line-height:16px;vertical-align:middle;margin-right:4px;"></span>
                    Suggérer tous les déclencheurs via IA
                </button>
                <span id="schilo-definition-global-feedback" style="margin-left:8px;font-weight:600;"></span>
            </p>
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
                        <td>
                            <textarea class="large-text" rows="3" name="schilo_definitions[definitions][<?php echo (int)$source->ID; ?>][terms]" placeholder="Un déclencheur par ligne"><?php echo esc_textarea(implode("\n", $terms)); ?></textarea>
                            <span class="description">Un déclencheur par ligne. Vous pouvez modifier ou supprimer les propositions.</span>
                            <div style="margin-top:6px;">
                                <button type="button" class="button button-small schilo-definition-suggest-ia" data-post-id="<?php echo (int)$source->ID; ?>">
                                    <span class="dashicons dashicons-superhero" style="font-size:15px;height:15px;width:15px;line-height:15px;vertical-align:middle;margin-right:3px;"></span>
                                    Suggérer via IA
                                </button>
                                <span class="schilo-definition-ia-feedback" style="margin-left:6px;font-weight:600;"></span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php submit_button('Enregistrer les définitions'); ?>
    </form>
</div>

