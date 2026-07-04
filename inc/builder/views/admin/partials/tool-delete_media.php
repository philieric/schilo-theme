<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-images-alt2" style="vertical-align:middle;margin-right:6px;"></span>
        Supprimer les médias non attachés
    </h2>
    <p>
        Supprime tous les médias qui ne sont <strong>pas attachés à un article ou une page</strong>
        et qui ne sont pas utilisés dans le contenu d'aucun article publié.
        <strong style="color:#b32d2e;">Action irréversible — faire une sauvegarde avant.</strong>
    </p>

    <?php if (!empty($result_orphan_media)) : ?>
    <div class="notice notice-success inline" style="margin:0 0 16px;">
        <p><?php echo esc_html($result_orphan_media['message']); ?></p>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_delete_orphan_media', 'schilo_delete_media_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="delete_orphan_media">
        <table class="form-table" style="max-width:600px;">
            <tr>
                <th scope="row">Mode</th>
                <td>
                    <label><input type="radio" name="schilo_delete_media_dry" value="1" checked>
                        <strong>Simulation</strong> — liste les médias qui seraient supprimés</label><br><br>
                    <label><input type="radio" name="schilo_delete_media_dry" value="0">
                        <strong>Réel</strong> — supprime définitivement les fichiers et entrées</label>
                </td>
            </tr>
            <tr>
                <th scope="row">Limite</th>
                <td>
                    <input type="number" name="schilo_delete_media_limit" value="200" min="1" max="2000" style="width:80px;">
                    <span class="description">médias traités par exécution</span>
                </td>
            </tr>
        </table>
        <?php submit_button('Lancer', 'primary', 'schilo_delete_media_submit', false); ?>
    </form>

    <?php if (!empty($result_orphan_media['items'])) : ?>
    <div class="schilo-tool-result">
        <h3>Résultat</h3>
        <table class="widefat fixed striped" style="max-width:700px;">
            <thead><tr><th>Fichier</th><th>Type</th><th>Date upload</th><th>Taille</th></tr></thead>
            <tbody>
                <?php foreach ($result_orphan_media['items'] as $item) : ?>
                <tr>
                    <td style="word-break:break-all;"><?php echo esc_html($item['filename']); ?></td>
                    <td><?php echo esc_html($item['mime']); ?></td>
                    <td><?php echo esc_html($item['date']); ?></td>
                    <td><?php echo esc_html($item['size']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
