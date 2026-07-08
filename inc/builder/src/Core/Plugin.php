<?php

namespace Schilo\Builder\Core;

use Schilo\Builder\Admin\BuilderMetabox;
use Schilo\Builder\Admin\SettingsPage;
use Schilo\Builder\Front\ContentRenderer;
use Schilo\Builder\Front\RelatedArticlesShortcode;
use Schilo\Builder\Front\ContextualDefinitionRenderer;
use Schilo\Builder\Admin\ContextualDefinitionsPage;
use Schilo\Builder\Service\ArticleTitleNumberer;
use Schilo\Builder\Service\CategoryAssigner;

class Plugin
{
    public function run()
    {
        // Taxonomies de classement (parcours/theme/serie) : doivent exister sur
        // TOUTE requete (front compris, pour les archives et tax_query), donc
        // hookees en dehors du bloc is_admin() ci-dessous.
        add_action('init', function (): void {
            (new \Schilo\Builder\Service\ClassementService())->registerTaxonomies();
        });

        // Pages d'index par personnage/lieu/mot-cle/reference biblique (texte
        // libre indexe par IA, pas une taxonomie) : rewrite rules + template
        // virtuel, doivent aussi exister sur le front, en dehors de is_admin().
        add_action('init', function (): void {
            (new \Schilo\Builder\Service\ClassementService())->registerIndexRewrites();
        });

        add_filter('template_include', function (string $template): string {
            if (get_query_var('schilo_index_field') !== '') {
                $custom = SCHILO_DIR . '/template-schilo-index.php';
                if (file_exists($custom)) {
                    return $custom;
                }
            }
            return $template;
        });

        add_filter('document_title_parts', function (array $parts): array {
            $field = get_query_var('schilo_index_field');
            $value = get_query_var('schilo_index_value');
            if ($field !== '' && $value !== '') {
                $parts['title'] = rawurldecode((string) $value);
            }
            return $parts;
        });

        if (is_admin()) {
            $admin = new BuilderMetabox();
            $admin->register();

            $settingsPage = new SettingsPage();
            $settingsPage->register();
            (new ContextualDefinitionsPage())->register();

            $titleNumberer = new ArticleTitleNumberer();
            add_filter('wp_insert_post_data', array($titleNumberer, 'filterPostData'), 20, 2);
            add_action('save_post', array($titleNumberer, 'normalizeAfterSave'), 999, 3);

            $categoryAssigner = new CategoryAssigner();
            add_action('save_post', array($categoryAssigner, 'assignCategoryOnSave'), 1000, 3);

            // Colonne + meta box indexation — enregistrees via add_meta_boxes pour etre sur du timing
            add_filter('manage_post_posts_columns', function (array $cols): array {
                $cols['schilo_indexation'] = '<span class="dashicons dashicons-search" style="font-size:14px;height:14px;width:14px;vertical-align:middle;" title="Indexation Schilo"></span> Indexation';
                return $cols;
            });

            add_action('manage_post_posts_custom_column', function (string $col, int $post_id): void {
                if ($col !== 'schilo_indexation') return;
                $service = new \Schilo\Builder\Service\IndexationService();
                $row     = $service->getByPostId($post_id);
                if (!$row) { echo '<span style="color:#cbd5e1;">—</span>'; return; }
                $statut  = $row['statut_indexation'] ?? '';
                $badges  = [
                    'valide'     => '<span style="background:#dcfce7;color:#166534;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:700;">Valide</span>',
                    'en_attente' => '<span style="background:#fef3c7;color:#92400e;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:700;">En attente</span>',
                    'brouillon'  => '<span style="background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:20px;font-size:11px;">Brouillon</span>',
                    'rejete'     => '<span style="background:#fee2e2;color:#991b1b;padding:2px 7px;border-radius:20px;font-size:11px;">Rejete</span>',
                ];
                echo $badges[$statut] ?? '<span style="color:#cbd5e1;">—</span>';
                if (!empty($row['date_validation'])) {
                    echo '<br><span style="font-size:11px;color:#94a3b8;">' . date('d/m/Y', strtotime($row['date_validation'])) . '</span>';
                }
            }, 10, 2);

            add_action('add_meta_boxes', function (): void {
                add_meta_box(
                    'schilo_indexation_status',
                    'Indexation Schilo',
                    function (\WP_Post $post): void {
                        try {
                            $service = new \Schilo\Builder\Service\IndexationService();
                            $row     = $service->getByPostId($post->ID);
                        } catch (\Throwable $e) {
                            echo '<p style="color:red;">Erreur : ' . esc_html($e->getMessage()) . '</p>';
                            return;
                        }
                        $row      = $row ?: null;
                        $list_url = admin_url('admin.php?page=schilo-builder-indexation');

                        if (!$row) {
                            echo '<p style="color:#94a3b8;font-size:13px;margin:4px 0 10px;">Non indexe.</p>';
                            echo '<div style="display:flex;flex-direction:column;gap:5px;">';
                            echo '<a href="' . esc_url($list_url) . '" class="button button-small">Ouvrir l\'indexation</a>';
                            echo '</div>';
                            return;
                        }

                        $statut  = $row['statut_indexation'] ?? '';
                        $source  = $row['source_indexation'] ?? '';
                        $sources = ['manuel' => 'Manuel', 'claude' => 'Claude AI', 'openai' => 'ChatGPT', 'xml_import' => 'Import XML'];
                        $styles  = [
                            'valide'     => 'background:#dcfce7;color:#166534;',
                            'en_attente' => 'background:#fef3c7;color:#92400e;',
                            'brouillon'  => 'background:#f1f5f9;color:#475569;',
                            'rejete'     => 'background:#fee2e2;color:#991b1b;',
                        ];
                        $labels  = ['valide' => 'Valide', 'en_attente' => 'En attente', 'brouillon' => 'Brouillon', 'rejete' => 'Rejete'];
                        echo '<p style="margin:4px 0 10px;"><span style="' . ($styles[$statut] ?? '') . 'padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">' . esc_html($labels[$statut] ?? $statut) . '</span></p>';

                        $info = [
                            'Theme'     => $row['theme_principal'] ?? '',
                            'SEO titre' => $row['seo_titre']       ?? '',
                            'Source'    => $sources[$source]       ?? $source,
                        ];
                        if (!empty($row['date_validation'])) {
                            $info['Valide le'] = date('d/m/Y', strtotime($row['date_validation']));
                        }

                        echo '<table style="width:100%;font-size:12px;border-collapse:collapse;">';
                        foreach ($info as $lbl => $val) {
                            if ($val === '') continue;
                            echo '<tr><td style="color:#64748b;white-space:nowrap;vertical-align:top;padding:2px 0;">' . esc_html($lbl) . ' :</td>';
                            echo '<td style="padding:2px 0 2px 6px;">' . esc_html(mb_substr((string)$val, 0, 60)) . '</td></tr>';
                        }
                        echo '</table>';
                        echo '<div style="margin-top:10px;display:flex;flex-direction:column;gap:5px;">';
                        echo '<a href="' . esc_url($list_url) . '" class="button button-small" style="font-size:11px;">Modifier l\'indexation</a>';
                        echo '</div>';
                    },
                    'post', 'side', 'high'
                );
            });

            // Colonne + meta box classement (parcours/theme/serie)
            add_filter('manage_post_posts_columns', function (array $cols): array {
                $cols['schilo_classement'] = '<span class="dashicons dashicons-networking" style="font-size:14px;height:14px;width:14px;vertical-align:middle;" title="Classement Schilo"></span> Classement';
                return $cols;
            });

            add_action('manage_post_posts_custom_column', function (string $col, int $post_id): void {
                if ($col !== 'schilo_classement') return;
                $service = new \Schilo\Builder\Service\ClassementService();
                $row     = $service->getByPostId($post_id);
                if (!$row || ($row['statut_indexation'] ?? '') !== 'valide') { echo '<span style="color:#cbd5e1;">—</span>'; return; }
                $statut  = $row['statut_classement'] ?? 'non_classe';
                $badges  = [
                    'classe'     => '<span style="background:#dcfce7;color:#166534;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:700;">Classe</span>',
                    'non_classe' => '<span style="background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:20px;font-size:11px;">Non classe</span>',
                ];
                echo $badges[$statut] ?? $badges['non_classe'];
                if (!empty($row['date_classement'])) {
                    echo '<br><span style="font-size:11px;color:#94a3b8;">' . date('d/m/Y', strtotime($row['date_classement'])) . '</span>';
                }
            }, 10, 2);

            add_action('add_meta_boxes', function (): void {
                add_meta_box(
                    'schilo_classement_status',
                    'Classement Schilo',
                    function (\WP_Post $post): void {
                        try {
                            $service = new \Schilo\Builder\Service\ClassementService();
                            $row     = $service->getByPostId($post->ID);
                        } catch (\Throwable $e) {
                            echo '<p style="color:red;">Erreur : ' . esc_html($e->getMessage()) . '</p>';
                            return;
                        }
                        $list_url = admin_url('admin.php?page=schilo-builder-classement');
                        $val_url  = admin_url('admin.php?page=schilo-builder-classement&tab=validation&post_id=' . $post->ID);

                        if (!$row || ($row['statut_indexation'] ?? '') !== 'valide') {
                            echo '<p style="color:#94a3b8;font-size:13px;margin:4px 0 10px;">Non indexe — le classement necessite une indexation validee au prealable.</p>';
                            echo '<a href="' . esc_url(admin_url('admin.php?page=schilo-builder-indexation')) . '" class="button button-small">Ouvrir l\'indexation</a>';
                            return;
                        }

                        $statut  = $row['statut_classement'] ?? 'non_classe';
                        $styles  = [
                            'classe'     => 'background:#dcfce7;color:#166534;',
                            'non_classe' => 'background:#f1f5f9;color:#475569;',
                        ];
                        $labels  = ['classe' => 'Classe', 'non_classe' => 'Non classe'];
                        echo '<p style="margin:4px 0 10px;"><span style="' . ($styles[$statut] ?? '') . 'padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">' . esc_html($labels[$statut] ?? $statut) . '</span></p>';

                        $terms = $service->getIndexedTermsForPost($post->ID);
                        $names = fn(array $terms) => implode(', ', array_map(fn($t) => $t->name, $terms));

                        $info = [
                            'Parcours' => $names($terms['schilo_parcours'] ?? []),
                            'Theme'    => $names($terms['schilo_theme'] ?? []),
                            'Serie'    => $names($terms['schilo_serie'] ?? []),
                        ];
                        if (!empty($row['date_classement'])) {
                            $info['Classe le'] = date('d/m/Y', strtotime($row['date_classement']));
                        }

                        echo '<table style="width:100%;font-size:12px;border-collapse:collapse;">';
                        foreach ($info as $lbl => $val) {
                            if ($val === '') continue;
                            echo '<tr><td style="color:#64748b;white-space:nowrap;vertical-align:top;padding:2px 0;">' . esc_html($lbl) . ' :</td>';
                            echo '<td style="padding:2px 0 2px 6px;">' . esc_html(mb_substr((string) $val, 0, 60)) . '</td></tr>';
                        }
                        echo '</table>';
                        echo '<div style="margin-top:10px;display:flex;flex-direction:column;gap:5px;">';
                        echo '<a href="' . esc_url($val_url) . '" class="button button-small" style="font-size:11px;">Classer cet article</a>';
                        echo '<a href="' . esc_url($list_url) . '" class="button button-small" style="font-size:11px;">Voir tous les classements</a>';
                        echo '</div>';
                    },
                    'post', 'side', 'high'
                );
            });
        }

        $front = new ContentRenderer();
        $front->register();

        (new ContextualDefinitionRenderer())->register();

        $relatedArticlesShortcode = new RelatedArticlesShortcode();
        $relatedArticlesShortcode->register();

        // Lecteur à haute voix — intégré au thème
        add_action( 'wp_enqueue_scripts', function () {
            if ( is_singular( 'post' ) ) {
                wp_enqueue_style( 'schilo-lhv', SCHILO_BUILDER_URL . 'lhv/css/lecteur-haute-voix.css', [], SCHILO_BUILDER_VERSION );
                wp_enqueue_script( 'schilo-lhv', SCHILO_BUILDER_URL . 'lhv/js/lecteur-haute-voix.js', [], SCHILO_BUILDER_VERSION, true );
            }
        } );

        // Modern Category Grid — intégré au thème
        if ( ! defined( 'MCG_PATH' ) ) {
            define( 'MCG_PATH',    SCHILO_BUILDER_PATH . 'mcg/' );
            define( 'MCG_URL',     SCHILO_BUILDER_URL  . 'mcg/' );
            define( 'MCG_VERSION', '1.0.0' );
        }
        if ( ! class_exists( '\\MCG\\Plugin' ) ) {
            require_once MCG_PATH . 'includes/class-mcg-plugin.php';
        }
        \MCG\Plugin::instance();
    }
}
