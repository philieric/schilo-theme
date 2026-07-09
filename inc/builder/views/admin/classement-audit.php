<?php
if (!defined('ABSPATH')) exit;

use Schilo\Builder\Service\ClassementService;

$service   = new ClassementService();
$counts    = $service->getCounts();
$violations = $service->auditPrefixRuleViolations();

$base_url  = admin_url('admin.php?page=schilo-builder-classement');
$back_url  = $base_url;

$taxonomy_labels = [
    'schilo_parcours' => 'Parcours',
    'schilo_theme'    => 'Thème',
    'schilo_serie'    => 'Série',
];

$has_any = !empty($violations['exclu']) || !empty($violations['limite']);
?>
<div class="wrap schilo-builder-settings">
    <h1 class="scl-page-title">
        <span class="dashicons dashicons-networking" style="color:#2872d4;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Parcours &amp; Thèmes — Audit des règles de préfixe
    </h1>

    <nav class="scl-tabs">
        <a href="<?php echo esc_url($back_url); ?>" class="scl-tab">Classement</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'termes', $base_url)); ?>" class="scl-tab">Termes</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'config', $base_url)); ?>" class="scl-tab">Configuration</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'audit', $base_url)); ?>" class="scl-tab scl-tab-active">Audit</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'doublons', $base_url)); ?>" class="scl-tab">Doublons</a>
    </nav>

    <div class="scl-val-bloc" style="display:block;">
        <p style="color:#64748b;font-size:13px;margin:0;">
            Projet encore en développement : <strong><?php echo esc_html($counts['total']); ?> articles indexés</strong>,
            dont <strong><?php echo esc_html($counts['classes']); ?> déjà classés</strong> en parcours/thème/série
            à ce jour. Les règles par préfixe
            (rôle, poids, limite) ne s'appliquent qu'aux <strong>nouveaux</strong> enregistrements — rien ne
            modifie automatiquement un classement déjà fait quand une règle change après coup. Cet audit sert
            à repérer une éventuelle dérive entre les règles actuelles et l'existant ; il est normal qu'il
            n'y ait rien à signaler tant que peu d'articles sont classés.
        </p>
    </div>

    <?php if (!$has_any) : ?>
    <div class="notice notice-success" style="padding:10px 12px;margin-top:16px;">
        <p style="margin:0;"><strong>Aucun conflit détecté</strong> entre les règles actuelles et le classement existant.</p>
    </div>
    <?php endif; ?>

    <div class="scl-val-bloc" style="display:block;margin-top:16px;">
        <div class="scl-val-bloc-title">Préfixes exclus mais encore classés</div>
        <p style="color:#64748b;font-size:13px;">
            Ces articles sont classés dans une taxonomie alors que leur préfixe est aujourd'hui réglé sur
            « Exclu » dans Configuration (la règle a probablement été ajoutée après leur classement).
        </p>
        <?php if (empty($violations['exclu'])) : ?>
            <p style="color:#94a3b8;">Rien à signaler.</p>
        <?php else : ?>
        <table class="widefat scl-table">
            <thead><tr>
                <th>Article</th>
                <th style="width:80px;">Préfixe</th>
                <th style="width:110px;">Taxonomie</th>
                <th>Terme</th>
                <th style="width:100px;">Action</th>
            </tr></thead>
            <tbody>
                <?php foreach ($violations['exclu'] as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row['titre']); ?></td>
                    <td><strong><?php echo esc_html($row['prefix']); ?></strong></td>
                    <td><?php echo esc_html($taxonomy_labels[$row['taxonomy']] ?? $row['taxonomy']); ?></td>
                    <td><?php echo esc_html($row['term_name']); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(['tab' => 'validation', 'post_id' => $row['post_id']], $base_url)); ?>" class="button button-small">Corriger</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="scl-val-bloc" style="display:block;margin-top:16px;">
        <div class="scl-val-bloc-title">Limites dépassées</div>
        <p style="color:#64748b;font-size:13px;">
            Un même préfixe apparaît plus souvent que la limite configurée dans un même terme (parcours,
            thème ou série) — probablement parce que des articles ont été classés avant que la limite ne
            soit réglée, ou que la limite a été resserrée après coup.
        </p>
        <?php if (empty($violations['limite'])) : ?>
            <p style="color:#94a3b8;">Rien à signaler.</p>
        <?php else : ?>
        <table class="widefat scl-table">
            <thead><tr>
                <th style="width:80px;">Préfixe</th>
                <th style="width:110px;">Taxonomie</th>
                <th>Terme</th>
                <th style="width:100px;">Compte actuel</th>
                <th style="width:80px;">Limite</th>
            </tr></thead>
            <tbody>
                <?php foreach ($violations['limite'] as $row) : ?>
                <tr>
                    <td><strong><?php echo esc_html($row['prefix']); ?></strong></td>
                    <td><?php echo esc_html($taxonomy_labels[$row['taxonomy']] ?? $row['taxonomy']); ?></td>
                    <td><?php echo esc_html($row['term_name']); ?></td>
                    <td><?php echo esc_html($row['count']); ?></td>
                    <td><?php echo esc_html($row['limite']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
