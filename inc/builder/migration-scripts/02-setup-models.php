<?php
/**
 * SCRIPT 2 : Enregistre les templates et modèles de migration Schilo Builder
 * pour tous les préfixes connus (PER, ANN, APO, BIB, CTD, DAN, DOC, FDS,
 * LGH, PAR, PDA, INF, MIR, PRB).
 *
 * Idempotent : peut être relancé sans effet de bord.
 * Usage : http://site/wp-content/plugins/schilo-builder/migration-scripts/02-setup-models.php
 */

if (php_sapi_name() !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'], true) && !isset($_GET['token'])) {
        http_response_code(403); exit('Accès refusé.');
    }
}

define('WP_ROOT', dirname(__DIR__, 4));
require_once WP_ROOT . '/wp-load.php';

use Schilo\Builder\Service\TemplateService;
use Schilo\Builder\Service\Migration\MigrationModelService;

header('Content-Type: text/plain; charset=utf-8');

// ── Templates ─────────────────────────────────────────────────────────────────
$ts        = new TemplateService();
$templates = $ts->getAllTemplates();

$template_defs = [
    'PER' => ['label' => 'Personnage',        'sections' => ['intro', 'liens-articles', 'evangiles', 'details-techniques', 'paragraphe', 'conclusion']],
    'ANN' => ['label' => 'Annexe',            'sections' => ['liens-articles', 'intro', 'paragraphe', 'image-textes', 'conclusion']],
    'APO' => ['label' => 'Apocalypse',        'sections' => ['intro', 'contexte', 'conclusion']],
    'BIB' => ['label' => 'Bible',             'sections' => ['intro', 'contexte', 'conclusion']],
    'CTD' => ['label' => 'Contradiction',     'sections' => ['liens-articles', 'intro', 'contexte', 'paragraphe', 'conclusion']],
    'DAN' => ['label' => 'Daniel',            'sections' => ['intro', 'contexte', 'conclusion']],
    'DOC' => ['label' => 'Doctrine',          'sections' => ['intro', 'contexte', 'conclusion']],
    'FDS' => ['label' => 'Fait de société',   'sections' => ['intro', 'contexte', 'conclusion']],
    'LGH' => ['label' => 'Ligne historique',  'sections' => ['intro', 'contexte', 'conclusion']],
    'PAR' => ['label' => 'Parabole (PER)',    'sections' => ['intro', 'contexte', 'conclusion']],
    'PDA' => ['label' => 'Point approfond.',  'sections' => ['intro', 'contexte', 'conclusion']],
    'INF' => ['label' => 'Note d\'info',      'sections' => ['intro', 'paragraphe', 'liens-articles']],
    'MIR' => ['label' => 'Miracle',           'sections' => ['intro', 'liens-articles']],
    'PRB' => ['label' => 'Parabole (synopt)', 'sections' => ['intro', 'liens-articles']],
];

foreach ($template_defs as $key => $def) {
    $templates[$key] = [
        'key'         => $key,
        'label'       => $def['label'],
        'description' => "Modèle pour les articles {$key}.",
        'active'      => 1,
        'sections'    => $def['sections'],
    ];
    echo "  ✓ Template {$key}: " . implode(', ', $def['sections']) . "\n";
}

update_option(TemplateService::OPTION_TEMPLATES, $templates);
echo "\n✓ " . count($template_defs) . " templates enregistrés\n";

// ── Modèles de migration ──────────────────────────────────────────────────────
$ms     = new MigrationModelService();
$models = $ms->getAllModels();

// Assignments communs aux articles avec structure section_texte + consultation
$std_assignments = [
    'consultation_link'      => ['section_type' => 'liens-articles', 'field' => 'links'],
    'consultation_intro'     => ['section_type' => 'liens-articles', 'field' => 'intro'],
    'section_texte_heading'  => ['section_type' => 'contexte',       'field' => 'section_title'],
    'section_texte_content'  => ['section_type' => 'contexte',       'field' => 'content'],
    'image_textes'           => ['section_type' => 'image-textes',   'field' => 'items'],
];

$model_defs = [
    // Préfixes PER : consultation → intro, section_texte → paragraphe
    'per_standard' => [
        'name' => 'PER standard', 'prefix' => 'PER',
        'assignments' => array_merge($std_assignments, [
            'consultation_resume'   => ['section_type' => 'intro',      'field' => 'content'],
            'section_texte_heading' => ['section_type' => 'paragraphe', 'field' => 'section_title'],
            'section_texte_content' => ['section_type' => 'paragraphe', 'field' => 'content'],
        ]),
    ],
    // Préfixes thématiques : consultation → intro, section_texte → contexte
    'ann_standard' => ['name' => 'ANN standard', 'prefix' => 'ANN', 'assignments' => array_merge($std_assignments, [
        'consultation_resume' => ['section_type' => 'intro',    'field' => 'content'],
    ])],
    'apo_standard' => ['name' => 'APO standard', 'prefix' => 'APO', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'bib_standard' => ['name' => 'BIB standard', 'prefix' => 'BIB', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'ctd_standard' => ['name' => 'CTD standard', 'prefix' => 'CTD', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'dan_standard' => ['name' => 'DAN standard', 'prefix' => 'DAN', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'doc_standard' => ['name' => 'DOC standard', 'prefix' => 'DOC', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'fds_standard' => ['name' => 'FDS standard', 'prefix' => 'FDS', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'lgh_standard' => ['name' => 'LGH standard', 'prefix' => 'LGH', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'par_standard' => ['name' => 'PAR standard', 'prefix' => 'PAR', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    'pda_standard' => ['name' => 'PDA standard', 'prefix' => 'PDA', 'assignments' => $std_assignments + [
        'consultation_resume' => ['section_type' => 'intro', 'field' => 'content'],
    ]],
    // Préfixes plain HTML (INF, MIR, PRB)
    'inf_standard' => ['name' => 'INF standard', 'prefix' => 'INF', 'assignments' => [
        'plain_content'      => ['section_type' => 'intro',         'field' => 'content'],
        'consultation_link'  => ['section_type' => 'liens-articles', 'field' => 'links'],
        'consultation_intro' => ['section_type' => 'liens-articles', 'field' => 'intro'],
    ]],
    'mir_standard' => ['name' => 'MIR standard', 'prefix' => 'MIR', 'assignments' => [
        'plain_content'      => ['section_type' => 'intro',         'field' => 'content'],
        'consultation_link'  => ['section_type' => 'liens-articles', 'field' => 'links'],
        'consultation_intro' => ['section_type' => 'liens-articles', 'field' => 'intro'],
    ]],
    'prb_standard' => ['name' => 'PRB standard', 'prefix' => 'PRB', 'assignments' => [
        'plain_content'      => ['section_type' => 'intro',         'field' => 'content'],
        'consultation_link'  => ['section_type' => 'liens-articles', 'field' => 'links'],
        'consultation_intro' => ['section_type' => 'liens-articles', 'field' => 'intro'],
    ]],
];

$now = date('Y-m-d H:i:s');
foreach ($model_defs as $id => $def) {
    // Ne pas écraser un modèle existant s'il a été personnalisé (updated_at récent)
    if (isset($models[$id]) && !isset($_GET['force'])) {
        echo "  [skip] Modèle {$id} déjà présent (ajoutez ?force=1 pour écraser)\n";
        continue;
    }
    $models[$id] = [
        'id'          => $id,
        'name'        => $def['name'],
        'prefix'      => $def['prefix'],
        'assignments' => $def['assignments'],
        'created_at'  => $models[$id]['created_at'] ?? $now,
        'updated_at'  => $now,
    ];
    echo "  ✓ Modèle {$id} ({$def['prefix']})\n";
}

update_option(MigrationModelService::OPTION_KEY, $models);
echo "\n✓ Modèles enregistrés\n=== Terminé ===\n";
