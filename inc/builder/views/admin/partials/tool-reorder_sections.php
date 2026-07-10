<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-sort" style="vertical-align:middle;margin-right:6px;"></span>
        Réordonner les sections
    </h2>
    <p>
        Corrige l'ordre interne des sections (<code>_schilo_builder_sections</code>) des articles
        migrés pour qu'il suive l'ordre du template attendu (Schilo Builder &gt; Types &amp; templates)
        selon le préfixe de l'article. Le contenu de chaque section n'est jamais modifié — seule sa
        position change, ce qui corrige l'ordre des onglets affichés sur la page publique (ex :
        « Détails » qui apparaissait après « Commentaires » au lieu d'avant).
    </p>

    <?php if (!empty($result_reorder_sections)) : ?>
    <div class="notice notice-success inline" style="margin:0 0 16px;">
        <p><?php echo esc_html($result_reorder_sections['message']); ?></p>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_reorder_sections', 'schilo_reorder_sections_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="reorder_sections">
        <table class="form-table" style="max-width:600px;">
            <tr>
                <th scope="row">Mode</th>
                <td>
                    <label><input type="radio" name="schilo_reorder_sections_dry" value="1" checked>
                        <strong>Simulation</strong> — liste les articles à réordonner et le nouvel ordre proposé</label><br><br>
                    <label><input type="radio" name="schilo_reorder_sections_dry" value="0">
                        <strong>Réel</strong> — réordonne les sections dans la base</label>
                </td>
            </tr>
        </table>
        <?php submit_button('Lancer', 'primary', 'schilo_reorder_sections_submit', false); ?>
    </form>

    <?php if (!empty($result_reorder_sections)) : ?>
    <div class="schilo-tool-result">
        <h3>Résultat</h3>
        <?php if (empty($result_reorder_sections['items'])) : ?>
            <p style="color:#16a34a;">✓ Aucun article à réordonner.</p>
        <?php else : ?>
        <table class="widefat fixed striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th style="width:70px;">Préfixe</th>
                    <th>Article</th>
                    <th>Ordre actuel</th>
                    <th>Nouvel ordre</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result_reorder_sections['items'] as $it) : ?>
                <tr>
                    <td><code><?php echo esc_html($it['prefix']); ?></code></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $it['post_id'] . '&action=edit')); ?>" target="_blank">
                            <?php echo esc_html($it['title']); ?>
                        </a>
                    </td>
                    <td style="font-size:11px;color:#94a3b8;"><?php echo esc_html(implode(' → ', $it['before'])); ?></td>
                    <td style="font-size:11px;color:#166534;font-weight:600;"><?php echo esc_html(implode(' → ', $it['after'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
