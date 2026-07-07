<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-admin-links" style="vertical-align:middle;margin-right:6px;"></span>
        Liens par ID
    </h2>
    <p>
        Les liens internes (section « Liens vers annexes » d'un article) enregistraient
        jusqu'ici une <strong>URL figée</strong> : si l'article ciblé change de slug (ex:
        renumérotation), le lien casse. Cet outil résout un <strong>ID d'article stable</strong>
        pour chaque lien qui n'en a pas encore — une fois l'ID enregistré, le lien est
        reconstruit à chaque affichage et ne peut plus casser. Les liens dont l'URL ne
        correspond déjà plus à aucun article (cassés par un renommage passé) sont listés
        pour correction manuelle : leur article d'origine n'est plus déterminable automatiquement.
    </p>

    <?php if (!empty($result_liens_ids)) : ?>
    <div class="notice notice-success inline" style="margin:0 0 16px;">
        <p><?php echo esc_html($result_liens_ids['message']); ?></p>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_migrate_liens_ids', 'schilo_liens_ids_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="migrate_liens_ids">
        <table class="form-table" style="max-width:600px;">
            <tr>
                <th scope="row">Mode</th>
                <td>
                    <label><input type="radio" name="schilo_liens_ids_dry" value="1" checked>
                        <strong>Simulation</strong> — liste les liens résolubles et déjà cassés</label><br><br>
                    <label><input type="radio" name="schilo_liens_ids_dry" value="0">
                        <strong>Réel</strong> — enregistre l'ID pour les liens résolubles</label>
                </td>
            </tr>
        </table>
        <?php submit_button('Lancer', 'primary', 'schilo_liens_ids_submit', false); ?>
    </form>

    <?php if (!empty($result_liens_ids)) : ?>
    <div class="schilo-tool-result">
        <h3>Résultat</h3>

        <?php if (empty($result_liens_ids['migrated']) && empty($result_liens_ids['broken'])) : ?>
            <p style="color:#16a34a;">✓ Tous les liens ont déjà un ID enregistré.</p>
        <?php else : ?>

            <?php if (!empty($result_liens_ids['migrated'])) : ?>
                <h4><?php echo $result_liens_ids['dry'] ? 'Liens résolubles' : 'Liens migrés'; ?> (<?php echo count($result_liens_ids['migrated']); ?>)</h4>
                <table class="widefat fixed striped" style="max-width:1000px;margin-bottom:20px;">
                    <thead>
                        <tr>
                            <th>Article contenant le lien</th>
                            <th>Libellé du lien</th>
                            <th>Article cible résolu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result_liens_ids['migrated'] as $m) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $m['source_id'] . '&action=edit')); ?>" target="_blank">
                                    <?php echo esc_html($m['source_title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($m['label']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $m['target_id'] . '&action=edit')); ?>" target="_blank">
                                    <?php echo esc_html($m['target_title']); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($result_liens_ids['broken'])) : ?>
                <h4 style="color:#92400e;">Liens déjà cassés — à corriger manuellement (<?php echo count($result_liens_ids['broken']); ?>)</h4>
                <table class="widefat fixed striped" style="max-width:1000px;">
                    <thead>
                        <tr>
                            <th>Article contenant le lien</th>
                            <th>Libellé du lien</th>
                            <th>URL enregistrée (introuvable)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result_liens_ids['broken'] as $b) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $b['source_id'] . '&action=edit')); ?>" target="_blank">
                                    <?php echo esc_html($b['source_title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($b['label']); ?></td>
                            <td><code><?php echo esc_html($b['url']); ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
