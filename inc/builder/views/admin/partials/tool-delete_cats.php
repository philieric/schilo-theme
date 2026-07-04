<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:6px;"></span>
        Supprimer les catégories sans articles
    </h2>
    <p>
        Supprime toutes les catégories qui ne contiennent <strong>aucun article publié ou brouillon</strong>.
        La catégorie "Non classé" (slug <code>uncategorized</code>) est toujours préservée.
    </p>

    <?php if (!empty($result_empty_cats)) : ?>
    <div class="notice notice-success inline" style="margin:0 0 16px;">
        <p><?php echo esc_html($result_empty_cats['message']); ?></p>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_delete_empty_cats', 'schilo_delete_cats_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="delete_empty_categories">
        <table class="form-table" style="max-width:600px;">
            <tr>
                <th scope="row">Mode</th>
                <td>
                    <label><input type="radio" name="schilo_delete_cats_dry" value="1" checked>
                        <strong>Simulation</strong> — liste les catégories qui seraient supprimées</label><br><br>
                    <label><input type="radio" name="schilo_delete_cats_dry" value="0">
                        <strong>Réel</strong> — supprime définitivement</label>
                </td>
            </tr>
        </table>
        <?php submit_button('Lancer', 'primary', 'schilo_delete_cats_submit', false); ?>
    </form>

    <?php if (!empty($result_empty_cats)) : ?>
    <div class="schilo-tool-result">
        <h3>Résultat</h3>
        <?php if (empty($result_empty_cats['cats'])) : ?>
            <p style="color:#16a34a;">✓ Aucune catégorie vide trouvée.</p>
        <?php else : ?>
            <table class="widefat fixed striped" style="max-width:500px;">
                <thead><tr><th>Catégorie</th><th>Slug</th><th>Parent</th></tr></thead>
                <tbody>
                    <?php foreach ($result_empty_cats['cats'] as $cat) : ?>
                    <tr>
                        <td><?php echo esc_html($cat['name']); ?></td>
                        <td><code><?php echo esc_html($cat['slug']); ?></code></td>
                        <td><?php echo esc_html($cat['parent']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
