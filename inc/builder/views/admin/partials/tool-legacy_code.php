<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-shield" style="vertical-align:middle;margin-right:6px;"></span>
        Résidus WPBakery / Divi / Wikilogy
    </h2>
    <p>
        Scanne le contenu des sections (<code>_schilo_builder_sections</code>) de tous les articles
        publiés à la recherche de traces des anciens systèmes : shortcodes non nettoyés
        (<code>[vc_...]</code>, <code>[et_pb_...]</code>, <code>[wikilogy_...]</code>) ou HTML déjà
        rendu resté dans le texte (ex : <code>&lt;div class="wpb_text_column"&gt;</code>). Outil de
        lecture seule — ne modifie rien, sert de vérification avant de désinstaller ces
        plugins/thème.
    </p>

    <?php if (!empty($result_legacy_code)) : ?>
    <div class="notice <?php echo empty($result_legacy_code['items']) ? 'notice-success' : 'notice-warning'; ?> inline" style="margin:0 0 16px;">
        <p><?php echo esc_html($result_legacy_code['message']); ?></p>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_scan_legacy_code', 'schilo_legacy_code_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="scan_legacy_code">
        <?php submit_button('Lancer le scan', 'primary', 'schilo_legacy_code_submit', false); ?>
    </form>

    <?php if (!empty($result_legacy_code) && !empty($result_legacy_code['items'])) : ?>
    <div class="schilo-tool-result">
        <h3>Résidus trouvés</h3>
        <table class="widefat fixed striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th style="width:80px;">Origine</th>
                    <th>Article</th>
                    <th>Section</th>
                    <th>Extrait</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result_legacy_code['items'] as $it) : ?>
                <tr>
                    <td><strong><?php echo esc_html($it['source']); ?></strong></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $it['post_id'] . '&action=edit')); ?>" target="_blank">
                            <?php echo esc_html($it['title']); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($it['section']); ?></td>
                    <td style="font-size:11px;color:#64748b;font-family:monospace;">…<?php echo esc_html($it['extract']); ?>…</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
