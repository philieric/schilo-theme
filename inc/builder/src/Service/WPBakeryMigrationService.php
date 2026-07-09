<?php

namespace Schilo\Builder\Service;

class WPBakeryMigrationService
{
    const BACKUP_META_KEY = '_schilo_backup_post_content';
        const ORIGINAL_BACKUP_META_KEY = '_schilo_original_wpbakery_content';
    const STATUS_META_KEY = '_schilo_migration_status';
    const DATE_META_KEY = '_schilo_migration_date';
    const BUILDER_META_KEY = '_schilo_builder_sections';
    const BUILDER_ENABLED_KEY = '_schilo_builder_enabled';

    public function postContainsBakery($postContent)
    {
        $content = (string) $postContent;

        return (bool) (
            preg_match('/\[(\/)?vc_[a-z0-9_\-]+/i', $content)
            || preg_match('/\[(\/)?wikilogy_[a-z0-9_\-]+/i', $content)
        );
    }

    public function getCandidatePosts($limit = 0)
    {
        global $wpdb;

        $limit = (int) $limit;

        $sql = "SELECT ID
                 FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                 AND post_status NOT IN ('trash', 'auto-draft')
                 AND (post_content LIKE %s OR post_content LIKE %s)
                 ORDER BY ID DESC";

        $params = array('%[vc_%', '%[wikilogy_%');

        if ($limit > 0) {
            $sql .= ' LIMIT %d';
            $params[] = $limit;
        }

        $ids = $wpdb->get_col($wpdb->prepare($sql, $params));

        $posts = array();

        foreach ($ids as $id) {
            $post = get_post((int) $id);

            if ($post && $this->postContainsBakery($post->post_content)) {
                $posts[] = $post;
            }
        }

        return $posts;
    }

    public function getMigrationStatus($postId)
    {
        $status = get_post_meta((int) $postId, self::STATUS_META_KEY, true);
        return $status ? (string) $status : 'not_migrated';
    }

    /**
     * Condition SQL commune : articles "candidats" a la migration, identifies
     * par un prefixe a 3 lettres majuscules en tete de titre (ex: "PER355 - ...").
     * Coherent avec PrefixDetector::detectFromTitle() (le detail des chiffres
     * apres le prefixe n'a pas besoin d'etre reproduit ici, seul le prefixe
     * lettre compte pour le filtre SQL).
     */
    private function candidateWhereClause()
    {
        return "p.post_type = 'post' AND p.post_status IN ('publish','draft') AND p.post_title REGEXP '^[A-Z]{3}'";
    }

    /**
     * Compteurs globaux (total / migres / non migres) pour la barre de stats
     * de l'ecran Migration > Liste.
     */
    public function getCounts()
    {
        global $wpdb;

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE " . $this->candidateWhereClause()
        );

        $migrated = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = 'migrated'
             WHERE " . $this->candidateWhereClause(),
            self::STATUS_META_KEY
        ));

        return array(
            'total'        => $total,
            'migrated'     => $migrated,
            'not_migrated' => $total - $migrated,
        );
    }

    /**
     * Nombre d'articles candidats par prefixe (pour les pastilles de filtre).
     */
    public function getPrefixCounts()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT LEFT(post_title, 3) as pfx, COUNT(*) as n
             FROM {$wpdb->posts} p
             WHERE " . $this->candidateWhereClause() . "
             GROUP BY pfx ORDER BY pfx ASC",
            ARRAY_A
        );

        $out = array();
        foreach ($rows as $row) {
            $out[$row['pfx']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Liste paginee/filtrable des articles candidats a la migration, pour
     * l'ecran Migration > Liste (equivalent de ClassementService::getList()
     * pour Parcours & Themes).
     */
    public function getMigrationList($perPage, $paged, $prefix = '', $status = '')
    {
        global $wpdb;

        $perPage = max(1, (int) $perPage);
        $paged   = max(1, (int) $paged);
        $offset  = ($paged - 1) * $perPage;

        $where  = $this->candidateWhereClause();
        $params = array(self::STATUS_META_KEY);

        if ($prefix !== '') {
            $where   .= ' AND p.post_title LIKE %s';
            $params[] = $wpdb->esc_like($prefix) . '%';
        }

        if ($status === 'migrated') {
            $where .= " AND pm.meta_value = 'migrated'";
        } elseif ($status === 'not_migrated') {
            $where .= " AND (pm.meta_value IS NULL OR pm.meta_value != 'migrated')";
        }

        $joinSql = "LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s";

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p {$joinSql} WHERE {$where}",
            $params
        ));

        $listParams   = $params;
        $listParams[] = $perPage;
        $listParams[] = $offset;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p {$joinSql} WHERE {$where}
             ORDER BY p.post_title ASC LIMIT %d OFFSET %d",
            $listParams
        ));

        $prefixDetector = new PrefixDetector();
        $rows = array();

        foreach ($ids as $id) {
            $id    = (int) $id;
            $title = get_the_title($id);
            $rows[] = array(
                'post_id' => $id,
                'title'   => $title,
                'prefix'  => $prefixDetector->detectFromTitle($title),
                'status'  => $this->getMigrationStatus($id),
                'date'    => (string) get_post_meta($id, self::DATE_META_KEY, true),
            );
        }

        return array('rows' => $rows, 'total' => $total);
    }

    public function previewMigration($postId)
    {
        $post = get_post((int) $postId);

        if (!$post) {
            return '';
        }

        $sections = $this->buildSectionsFromContent($post->post_content, (int) $postId);

        $html = '';

        foreach ($sections as $section) {
            $html .= '<h3>' . esc_html($section['title']) . '</h3>';
            $html .= wpautop(wp_kses_post($section['content']));
            $html .= '<hr>';
        }

        return $html;
    }

    public function migratePost($postId)
    {
        $post = get_post((int) $postId);

        if (!$post || $post->post_type !== 'post') {
            return new \WP_Error('invalid_post', 'Article invalide.');
        }

        if (!$this->postContainsBakery($post->post_content)) {
            return new \WP_Error('no_bakery', 'Aucun contenu WPBakery ou Wikilogy détecté.');
        }

        if (!get_post_meta((int) $postId, self::BACKUP_META_KEY, true)) {
            update_post_meta((int) $postId, self::BACKUP_META_KEY, $post->post_content);
        }

        $sections = $this->buildSectionsFromContent($post->post_content, (int) $postId);

        update_post_meta((int) $postId, self::BUILDER_META_KEY, $sections);
        update_post_meta((int) $postId, self::BUILDER_ENABLED_KEY, 1);
        update_post_meta((int) $postId, self::STATUS_META_KEY, 'migrated');
        update_post_meta((int) $postId, self::DATE_META_KEY, current_time('mysql'));

        return true;
    }

    public function restorePost($postId)
    {
        $backup = get_post_meta((int) $postId, self::BACKUP_META_KEY, true);

        if (!$backup) {
            return new \WP_Error('no_backup', 'Aucune sauvegarde disponible.');
        }

        wp_update_post(array(
            'ID' => (int) $postId,
            'post_content' => $backup,
        ));

        delete_post_meta((int) $postId, self::BUILDER_META_KEY);
        delete_post_meta((int) $postId, self::BUILDER_ENABLED_KEY);
        update_post_meta((int) $postId, self::STATUS_META_KEY, 'restored');

        return true;
    }

    /**
     * Liste les éléments de contenu détectables dans un article WPBakery / Wikilogy,
     * pour permettre à l'administrateur de choisir manuellement vers quelle section
     * (et quel emplacement du template) chacun doit être migré.
     */
    public function extractMigrationElements($content)
    {
        $content = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $wikilogyData = $this->extractWikilogyData($content);

        $elements = array();

        if (!empty($wikilogyData['title'])) {
            $elements[] = array(
                'id' => 'wikilogy_title',
                'label' => 'Titre Wikilogy',
                'default_type' => 'intro',
                'title' => $wikilogyData['title_text'] ? $wikilogyData['title_text'] : 'Introduction',
                'content' => $wikilogyData['title'],
                'custom_class' => 'schilo-migrated-wikilogy-title',
                'data' => array(
                    'source' => 'wikilogy_title',
                    'shadow_text' => $wikilogyData['shadow_text'],
                ),
            );
        }

        $contentBlocks = $this->splitContentBlocks($content);
        $blockNumber = 0;

        foreach ($contentBlocks as $block) {
            $cleanBlock = $this->cleanBakeryContent($block);

            if ($cleanBlock === '') {
                continue;
            }

            $blockNumber++;

            $defaultType = 'paragraphe';

            if (preg_match('/\[wikilogy_blog_list/i', $block)) {
                $defaultType = 'references';
            } elseif (preg_match('/consulter l.{1,2}annexe/iu', $cleanBlock)) {
                $defaultType = 'liens-articles';
            } elseif (preg_match('/\[vc_single_image|<img\b/i', $block)) {
                $defaultType = 'image-textes';
            }

            $elements[] = array(
                'id' => 'content_block_' . $blockNumber,
                'label' => 'Bloc de contenu #' . $blockNumber,
                'default_type' => $defaultType,
                'title' => 'Contenu migré #' . $blockNumber,
                'content' => $cleanBlock,
                'custom_class' => 'schilo-migrated-from-wpbakery',
                'data' => array(
                    'source' => 'wpbakery_block',
                    'block_index' => $blockNumber,
                ),
            );
        }

        foreach ($wikilogyData['blog_lists'] as $i => $blogList) {
            $elements[] = array(
                'id' => 'blog_list_' . $i,
                'label' => 'Liste Wikilogy d’articles liés #' . ($i + 1),
                'default_type' => 'references',
                'title' => 'Articles liés',
                'content' => $this->renderBlogListMigrationContent($blogList),
                'custom_class' => 'schilo-migrated-wikilogy-blog-list',
                'data' => array(
                    'source' => 'wikilogy_blog_list',
                    'category' => isset($blogList['category']) ? $blogList['category'] : '',
                    'count' => isset($blogList['count']) ? $blogList['count'] : '',
                    'style' => isset($blogList['style']) ? $blogList['style'] : '',
                ),
            );
        }

        return $elements;
    }

    /**
     * Migre un article en appliquant un template choisi et une correspondance
     * "élément détecté -> type de section" + "élément détecté -> champ de la section"
     * définie par l'administrateur.
     *
     * @param int    $postId
     * @param string $templateKey
     * @param array  $mapping      Tableau [element_id => section_type|'ignore']
     * @param array  $fieldMapping Tableau [element_id => field_key] (ex: 'content', 'section_title', 'intro', 'texte_libre', 'links_auto')
     */
    public function migrateWithMapping($postId, $templateKey, $mapping, $fieldMapping = array())
    {
        $post = get_post((int) $postId);

        if (!$post || $post->post_type !== 'post') {
            return new \WP_Error('invalid_post', 'Article invalide.');
        }

        if (!get_post_meta((int) $postId, self::BACKUP_META_KEY, true)) {
            update_post_meta((int) $postId, self::BACKUP_META_KEY, $post->post_content);
        }

        $elements = $this->extractMigrationElements($post->post_content);

        $elementsById = array();
        foreach ($elements as $element) {
            $elementsById[$element['id']] = $element;
        }

        $templateService = new TemplateService();
        $templates = $templateService->getAllTemplates();
        $templateKey = strtoupper(sanitize_key((string) $templateKey));
        $template = isset($templates[$templateKey]) ? $templates[$templateKey] : $templateService->getTemplateForPrefix($templateKey);

        $templateSections = (!empty($template['sections']) && is_array($template['sections']))
            ? $template['sections']
            : array('intro', 'paragraphe', 'references', 'conclusion');

        $defaultTitles = array(
            'intro' => 'Introduction',
            'paragraphe' => 'Contenu',
            'contexte' => 'Contexte',
            'references' => 'Articles liés',
            'liens-articles' => 'Articles liés',
            'conclusion' => 'Conclusion',
        );

        // Regroupe les éléments assignés par type de section cible, avec le champ choisi pour chacun.
        $assignments = array();

        if (is_array($mapping)) {
            foreach ($mapping as $elementId => $target) {
                $elementId = sanitize_key((string) $elementId);
                $target = sanitize_key((string) $target);

                if ($target === '' || $target === 'ignore' || !isset($elementsById[$elementId])) {
                    continue;
                }

                $fieldKey = isset($fieldMapping[$elementId]) ? sanitize_key((string) $fieldMapping[$elementId]) : 'content';

                if ($fieldKey === '') {
                    $fieldKey = 'content';
                }

                $assignments[$target][] = array(
                    'element_id' => $elementId,
                    'field' => $fieldKey,
                );
            }
        }

        $buildSectionFromAssignedElements = function ($sectionType, array $items) use ($elementsById, $template) {
            $contentParts = array();
            $title = '';
            $introParts = array();
            $texteLibreParts = array();
            $linksAutoContent = array();
            $customClass = '';
            $data = array();

            foreach ($items as $item) {
                $element = $elementsById[$item['element_id']];
                $field = $item['field'];

                if ($customClass === '' && $element['custom_class'] !== '') {
                    $customClass = $element['custom_class'];
                }

                if (empty($data) && is_array($element['data'])) {
                    $data = $element['data'];
                }

                switch ($field) {
                    case 'section_title':
                        if ($title === '') {
                            $title = trim(wp_strip_all_tags($element['content']));
                        }
                        break;

                    case 'intro':
                        $introParts[] = trim(wp_strip_all_tags($element['content']));
                        break;

                    case 'texte_libre':
                        $texteLibreParts[] = trim(wp_strip_all_tags($element['content']));
                        break;

                    case 'links_auto':
                        $linksAutoContent[] = $element['content'];
                        break;

                    default:
                        $contentParts[] = $element['content'];
                        break;
                }
            }

            if ($title === '') {
                $title = $elementsById[$items[0]['element_id']]['title'];
            }

            $data['template'] = isset($template['key']) ? $template['key'] : '';

            $section = array(
                'type' => $sectionType,
                'title' => $title,
                'content' => implode("\n\n", $contentParts),
                'custom_class' => $customClass,
                'data' => $data,
            );

            if ($sectionType === 'liens-articles' && (!empty($introParts) || !empty($texteLibreParts) || !empty($linksAutoContent))) {
                $links = array();

                foreach ($linksAutoContent as $linksContent) {
                    $links = array_merge($links, $this->extractAnnexeLinks($linksContent));
                }

                $section['data'] = array(
                    'intro' => implode(' ', $introParts),
                    'texte_libre' => implode("\n\n", $texteLibreParts),
                    'links' => $links,
                );
            }

            return $section;
        };

        $sections = array();
        $order = 0;

        // 1. Sections du template, dans l'ordre défini, avec les éléments assignés ou vides.
        foreach ($templateSections as $sectionType) {
            $sectionType = sanitize_key($sectionType);

            if ($sectionType === '') {
                continue;
            }

            if (!empty($assignments[$sectionType])) {
                $section = $buildSectionFromAssignedElements($sectionType, $assignments[$sectionType]);
                unset($assignments[$sectionType]);
            } else {
                $section = array(
                    'type' => $sectionType,
                    'title' => isset($defaultTitles[$sectionType]) ? $defaultTitles[$sectionType] : '',
                    'content' => '',
                    'custom_class' => '',
                    'data' => array(
                        'source' => 'template_schema',
                        'template' => isset($template['key']) ? $template['key'] : '',
                    ),
                );
            }

            $section['order'] = $order++;
            $sections[] = $section;
        }

        // 2. Éléments assignés vers des types de section absents du template : ajoutés à la suite.
        foreach ($assignments as $sectionType => $elementIds) {
            $sectionType = sanitize_key($sectionType);

            if ($sectionType === '') {
                continue;
            }

            $section = $buildSectionFromAssignedElements($sectionType, $elementIds);
            $section['order'] = $order++;
            $sections[] = $section;
        }

        if (empty($sections)) {
            $sections[] = array(
                'type' => 'paragraphe',
                'title' => 'Contenu migré',
                'content' => '',
                'custom_class' => 'schilo-migrated-empty',
                'order' => 0,
                'data' => array(
                    'source' => 'migration_empty',
                    'template' => isset($template['key']) ? $template['key'] : '',
                ),
            );
        }

        update_post_meta((int) $postId, self::BUILDER_META_KEY, $sections);
        update_post_meta((int) $postId, self::BUILDER_ENABLED_KEY, 1);
        update_post_meta((int) $postId, self::STATUS_META_KEY, 'migrated');
        update_post_meta((int) $postId, self::DATE_META_KEY, current_time('mysql'));

        return true;
    }

    private function buildSectionsFromContent($content, $postId = 0)
    {
        $content = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $wikilogyData = $this->extractWikilogyData($content);
        $cleanContent = $this->cleanBakeryContent($content);

        $prefixDetector = new PrefixDetector();
        $templateService = new TemplateService();

        $prefix = $postId > 0 ? $prefixDetector->detectFromPostId($postId) : 'DEFAULT';
        $template = $templateService->getTemplateForPrefix($prefix);

        $templateSections = (!empty($template['sections']) && is_array($template['sections']))
            ? $template['sections']
            : array('intro', 'paragraphe', 'references', 'conclusion');

        // Contenu migré disponible, classé par type de section du template.
        $migrated = array();

        if (!empty($wikilogyData['title'])) {
            $migrated['intro'] = array(
                'title' => $wikilogyData['title_text'] ? $wikilogyData['title_text'] : 'Introduction',
                'content' => $wikilogyData['title'],
                'custom_class' => 'schilo-migrated-wikilogy-title',
                'data' => array(
                    'source' => 'wikilogy_title',
                    'template' => isset($template['key']) ? $template['key'] : '',
                    'shadow_text' => $wikilogyData['shadow_text'],
                ),
            );
        }

        if ($cleanContent !== '') {
            $migrated['paragraphe'] = array(
                'title' => 'Contenu migré',
                'content' => $cleanContent,
                'custom_class' => 'schilo-migrated-from-wpbakery',
                'data' => array(
                    'source' => 'wpbakery_wikilogy',
                    'template' => isset($template['key']) ? $template['key'] : '',
                ),
            );
        }

        $migratedReferences = array();

        foreach ($wikilogyData['blog_lists'] as $blogList) {
            $migratedReferences[] = array(
                'title' => 'Articles liés',
                'content' => $this->renderBlogListMigrationContent($blogList),
                'custom_class' => 'schilo-migrated-wikilogy-blog-list',
                'data' => array(
                    'source' => 'wikilogy_blog_list',
                    'template' => isset($template['key']) ? $template['key'] : '',
                    'category' => isset($blogList['category']) ? $blogList['category'] : '',
                    'count' => isset($blogList['count']) ? $blogList['count'] : '',
                    'style' => isset($blogList['style']) ? $blogList['style'] : '',
                ),
            );
        }

        $defaultTitles = array(
            'intro' => 'Introduction',
            'paragraphe' => 'Contenu',
            'contexte' => 'Contexte',
            'references' => 'Articles liés',
            'liens-articles' => 'Articles liés',
            'conclusion' => 'Conclusion',
        );

        $sections = array();
        $order = 0;

        // 1. Suit l'ordre des sections défini dans le template (Types & templates).
        foreach ($templateSections as $sectionType) {
            $sectionType = sanitize_key($sectionType);

            if ($sectionType === '') {
                continue;
            }

            if (isset($migrated[$sectionType])) {
                $entry = $migrated[$sectionType];
                unset($migrated[$sectionType]);
            } elseif (in_array($sectionType, array('references', 'liens-articles'), true) && !empty($migratedReferences)) {
                $entry = array_shift($migratedReferences);
            } else {
                $entry = array(
                    'title' => isset($defaultTitles[$sectionType]) ? $defaultTitles[$sectionType] : '',
                    'content' => '',
                    'custom_class' => '',
                    'data' => array(
                        'source' => 'template_schema',
                        'template' => isset($template['key']) ? $template['key'] : '',
                    ),
                );
            }

            $sections[] = array(
                'type' => $sectionType,
                'title' => $entry['title'],
                'content' => $entry['content'],
                'custom_class' => $entry['custom_class'],
                'order' => $order++,
                'data' => $entry['data'],
            );
        }

        // 2. Contenu migré qui n'a pas trouvé de section correspondante dans le template
        //    (ex : type de section absent du template) : ajouté à la suite.
        foreach ($migrated as $sectionType => $entry) {
            $sections[] = array(
                'type' => $sectionType,
                'title' => $entry['title'],
                'content' => $entry['content'],
                'custom_class' => $entry['custom_class'],
                'order' => $order++,
                'data' => $entry['data'],
            );
        }

        foreach ($migratedReferences as $entry) {
            $sections[] = array(
                'type' => 'references',
                'title' => $entry['title'],
                'content' => $entry['content'],
                'custom_class' => $entry['custom_class'],
                'order' => $order++,
                'data' => $entry['data'],
            );
        }

        if (empty($sections)) {
            $sections[] = array(
                'type' => 'paragraphe',
                'title' => 'Contenu migré',
                'content' => '',
                'custom_class' => 'schilo-migrated-empty',
                'order' => 0,
                'data' => array(
                    'source' => 'migration_empty',
                    'template' => isset($template['key']) ? $template['key'] : '',
                ),
            );
        }

        return $sections;
    }

    /**
     * Découpe le contenu d'origine en blocs (un par ligne/rangée WPBakery [vc_row]...[/vc_row]),
     * afin que chaque bloc puisse être migré indépendamment vers la bonne section.
     */
    private function splitContentBlocks($content)
    {
        $content = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $blocks = array();

        if (preg_match_all('/\[vc_row\b[^\]]*\].*?\[\/vc_row\]/is', $content, $matches)) {
            $remaining = $content;

            foreach ($matches[0] as $rowContent) {
                $remaining = str_replace($rowContent, '', $remaining);
                $blocks[] = $rowContent;
            }

            $remaining = trim($remaining);

            if ($remaining !== '') {
                $blocks[] = $remaining;
            }
        } else {
            $blocks[] = $content;
        }

        return $blocks;
    }

    /**
     * Extrait les liens "Vous pouvez consulter l'annexe ANNXXX : Titre" d'un texte migré
     * et les transforme en liens [label => Titre, url => permalien de l'annexe].
     */
    public function extractAnnexeLinks($content)
    {
        $text = wp_strip_all_tags((string) $content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $links = array();

        $pattern = '/Vous pouvez consulter l.{1,2}annexe\s+([A-Z]{2,4}[0-9]+)\s*:\s*(.*?)(?=Vous pouvez consulter l.{1,2}annexe|$)/isu';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $code = trim($match[1]);
                $label = trim($match[2]);
                $label = trim($label, " \t\n\r\0\x0B.:-");

                if ($label === '') {
                    $label = $code;
                }

                $links[] = array(
                    'label' => sanitize_text_field($label),
                    'url' => $this->findPostUrlByTitlePrefix($code),
                );
            }
        }

        return $links;
    }

    /**
     * Recherche le permalien d'un article dont le titre commence par le préfixe donné
     * (ex : "ANN027" pour retrouver "ANN027 - Le dernier repas de Pâque").
     */
    private function findPostUrlByTitlePrefix($prefix)
    {
        global $wpdb;

        $prefix = trim((string) $prefix);

        if ($prefix === '') {
            return '';
        }

        $like = $wpdb->esc_like($prefix) . '%';

        $postId = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_title LIKE %s
             AND post_status = 'publish'
             AND post_type IN ('post', 'page')
             ORDER BY ID ASC
             LIMIT 1",
            $like
        ));

        return $postId ? (string) get_permalink((int) $postId) : '';
    }

    public function cleanBakeryContent($content)
    {
        $content = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Supprime WPBakery en conservant le contenu interne.
        $content = preg_replace('/\[(\/)?vc_[^\]]*\]/i', '', $content);

        // Nettoie les shortcodes Wikilogy uniquement dans la section Schilo migrée. Le post_content original reste intact.
        $content = preg_replace('/\[(\/)?wikilogy_[^\]]*\]/i', '', $content);

        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        return wp_kses_post($content);
    }

    private function extractWikilogyData($content)
    {
        $data = array(
            'title' => '',
            'title_text' => '',
            'shadow_text' => '',
            'blog_lists' => array(),
        );

        if (preg_match_all('/\[wikilogy_title\s+([^\]]*)\]/i', $content, $matches)) {
            foreach ($matches[1] as $attributesText) {
                $attributes = $this->parseShortcodeAttributes($attributesText);

                if (!empty($attributes['title'])) {
                    $data['title'] = sanitize_text_field($attributes['title']);
                }

                if (!empty($attributes['title-text'])) {
                    $data['title_text'] = sanitize_text_field($attributes['title-text']);
                }

                if (!empty($attributes['shadow-text'])) {
                    $data['shadow_text'] = sanitize_text_field($attributes['shadow-text']);
                }
            }
        }

        if (preg_match_all('/\[wikilogy_blog_list\s+([^\]]*)\]/i', $content, $matches)) {
            foreach ($matches[1] as $attributesText) {
                $data['blog_lists'][] = $this->parseShortcodeAttributes($attributesText);
            }
        }

        return $data;
    }

    private function parseShortcodeAttributes($attributesText)
    {
        $attributes = array();

        if (preg_match_all('/([a-zA-Z0-9_\-]+)\s*=\s*"([^"]*)"/', (string) $attributesText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[strtolower($match[1])] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $attributes;
    }

    private function renderBlogListMigrationContent($attributes)
    {
        $category = isset($attributes['category']) ? (int) $attributes['category'] : 0;
        $count = isset($attributes['count']) ? (int) $attributes['count'] : 0;
        $style = isset($attributes['style']) ? sanitize_text_field($attributes['style']) : '';

        $parts = array();

        $parts[] = '<p><strong>Ancienne liste Wikilogy migrée.</strong></p>';

        if ($category > 0) {
            $categoryTerm = get_category($category);
            $categoryName = $categoryTerm && !is_wp_error($categoryTerm) ? $categoryTerm->name : 'Catégorie #' . $category;
            $parts[] = '<p>Catégorie liée : <strong>' . esc_html($categoryName) . '</strong></p>';
        }

        if ($count > 0) {
            $parts[] = '<p>Nombre d’articles demandé : <strong>' . esc_html((string) $count) . '</strong></p>';
        }

        if ($style !== '') {
            $parts[] = '<p>Style d’origine : <code>' . esc_html($style) . '</code></p>';
        }

        if ($category > 0) {
            $parts[] = '[schilo_articles_lies category="' . esc_attr((string) $category) . '" count="' . esc_attr((string) ($count > 0 ? $count : 6)) . '"]';
        }

        return implode("\n\n", $parts);
    }
}
