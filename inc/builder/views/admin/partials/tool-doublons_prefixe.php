<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-search" style="vertical-align:middle;margin-right:6px;"></span>
        Doublons de préfixe
    </h2>
    <p>
        Détecte les articles qui partagent le même numéro de préfixe (ex: deux articles
        <code>INF144</code>). L'article <strong>publié</strong> garde toujours son numéro en
        priorité (un brouillon ne fera jamais changer l'URL d'un article publié) ; à défaut
        de publié dans le groupe, le plus ancien (ID le plus bas) le conserve. Les autres sont
        renumérotés vers le prochain numéro disponible pour ce préfixe, <strong>en cascade</strong>
        (chaque renumérotation réserve immédiatement son numéro pour éviter de recréer un
        doublon avec le suivant du même lot).
    </p>

    <?php if (!empty($result_doublons_prefixe)) : ?>
    <div class="notice notice-success inline" style="margin:0 0 16px;">
        <p><?php echo esc_html($result_doublons_prefixe['message']); ?></p>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_fix_duplicate_prefixes', 'schilo_doublons_prefixe_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="fix_duplicate_prefixes">
        <table class="form-table" style="max-width:600px;">
            <tr>
                <th scope="row">Mode</th>
                <td>
                    <label><input type="radio" name="schilo_doublons_prefixe_dry" value="1" checked>
                        <strong>Simulation</strong> — liste les doublons et le nouveau numéro proposé</label><br><br>
                    <label><input type="radio" name="schilo_doublons_prefixe_dry" value="0">
                        <strong>Réel</strong> — renumérote les articles en doublon dans la base</label>
                </td>
            </tr>
        </table>
        <?php submit_button('Lancer', 'primary', 'schilo_doublons_prefixe_submit', false); ?>
    </form>

    <?php if (!empty($result_doublons_prefixe)) : ?>
    <div class="schilo-tool-result">
        <h3>Résultat</h3>
        <?php if (empty($result_doublons_prefixe['duplicates'])) : ?>
            <p style="color:#16a34a;">✓ Aucun doublon de préfixe trouvé.</p>
        <?php else : ?>
            <?php
            $statusLabels = array(
                'publish' => array('Publié', '#166534', '#dcfce7'),
                'draft'   => array('Brouillon', '#92400e', '#fef3c7'),
                'pending' => array('En attente', '#92400e', '#fef3c7'),
                'private' => array('Privé', '#475569', '#f1f5f9'),
            );
            $renderStatus = function ($status) use ($statusLabels) {
                $s = $statusLabels[$status] ?? array(ucfirst($status), '#475569', '#f1f5f9');
                printf(
                    '<span style="background:%s;color:%s;padding:1px 7px;border-radius:20px;font-size:11px;font-weight:700;margin-left:6px;">%s</span>',
                    esc_attr($s[2]),
                    esc_attr($s[1]),
                    esc_html($s[0])
                );
            };
            ?>
            <table class="widefat fixed striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th>Préfixe</th>
                        <th>Conservé (numéro d'origine)</th>
                        <th>Doublon renuméroté</th>
                        <th>Nouveau titre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result_doublons_prefixe['duplicates'] as $d) : ?>
                    <tr>
                        <td><code><?php echo esc_html($d['prefix'] . sprintf('%03d', $d['number'])); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $d['kept_id'] . '&action=edit')); ?>" target="_blank">
                                <?php echo esc_html($d['kept_title']); ?>
                            </a>
                            <?php $renderStatus($d['kept_status']); ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $d['dup_id'] . '&action=edit')); ?>" target="_blank">
                                <?php echo esc_html($d['old_title']); ?>
                            </a>
                            <?php $renderStatus($d['dup_status']); ?>
                        </td>
                        <td><?php echo esc_html($d['new_title']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
