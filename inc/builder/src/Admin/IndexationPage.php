<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\IndexationService;

class IndexationPage
{
    private IndexationService $service;

    public function __construct()
    {
        $this->service = new IndexationService();
        $this->service->maybeCreateTable();
    }

    public function register(): void
    {
        add_action('wp_ajax_schilo_ia_index_article',          [$this, 'ajaxIndexArticle']);
        add_action('wp_ajax_schilo_ia_index_batch',            [$this, 'ajaxIndexBatch']);
        add_action('wp_ajax_schilo_import_indexation',         [$this, 'ajaxImport']);
        add_action('wp_ajax_schilo_save_indexation_validated', [$this, 'ajaxSaveValidated']);
        add_action('wp_ajax_schilo_export_indexation_xml',     [$this, 'ajaxExportXml']);
        add_action('wp_ajax_schilo_indexation_update_status',  [$this, 'ajaxUpdateStatus']);
        add_action('wp_ajax_schilo_indexation_get_articles',   [$this, 'ajaxGetArticles']);
        add_action('wp_ajax_schilo_indexation_get_record',    [$this, 'ajaxGetRecord']);

    }

    public function renderPage(): void
    {
        $tab = sanitize_key($_GET['tab'] ?? 'list');
        switch ($tab) {
            case 'validation':
                include SCHILO_BUILDER_PATH . 'views/admin/indexation-validation.php';
                break;
            case 'config':
                include SCHILO_BUILDER_PATH . 'views/admin/indexation-config.php';
                break;
            default:
                include SCHILO_BUILDER_PATH . 'views/admin/indexation-page.php';
        }
    }

    /* =========================================================
       Verifie si une erreur WP_Error est due a un quota depasse
    ========================================================= */

    private function isQuotaError(\WP_Error $error): bool
    {
        $msg = strtolower($error->get_error_message());
        return strpos($msg, 'quota') !== false
            || strpos($msg, 'exceeded') !== false
            || strpos($msg, 'billing') !== false
            || strpos($msg, 'insufficient') !== false
            || strpos($msg, 'rate limit') !== false
            || strpos($msg, 'too many') !== false;
    }

    /* =========================================================
       Retourne le provider de secours
    ========================================================= */

    private function fallbackProvider(string $provider): string
    {
        return $provider === 'claude' ? 'openai' : 'claude';
    }

    /* =========================================================
       AJAX - Indexer via IA (avec fallback automatique)
    ========================================================= */

    public function ajaxIndexArticle(): void
    {
        @set_time_limit(120);

        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_id  = absint($_POST['post_id'] ?? 0);
        $provider = sanitize_key($_POST['provider'] ?? 'claude');

        if (!$post_id) {
            wp_send_json_error(['message' => 'post_id manquant.']);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Article introuvable (ID: ' . $post_id . ').']);
        }

        $ia_config = get_option('schilo_ia_config', []);

        // Verifier la cle du provider demande
        $key = $ia_config[$provider]['api_key'] ?? '';
        if (empty($key)) {
            wp_send_json_error(['message' => 'Cle API ' . $provider . ' non configuree. Allez dans Schilo Builder > IA.']);
        }

        // Tentative avec le provider choisi
        $result      = $this->service->indexArticleViaIA($post_id, $provider);
        $used        = $provider;
        $fallback_msg = '';

        // Si quota depasse, tentative avec l'autre provider
        if (is_wp_error($result) && $this->isQuotaError($result)) {
            $fallback = $this->fallbackProvider($provider);
            $fb_key   = $ia_config[$fallback]['api_key'] ?? '';

            if (!empty($fb_key)) {
                $fallback_msg = 'Quota ' . $provider . ' depasse — bascule automatique sur ' . $fallback . '.';
                $result       = $this->service->indexArticleViaIA($post_id, $fallback);
                $used         = $fallback;
            }
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Mode automatique : enregistrer directement en "valide", sans passer par la modale de relecture.
        $auto_mode  = get_option('schilo_indexation_validation_mode', 'manuel') === 'auto';
        $auto_saved = false;
        if ($auto_mode) {
            $auto_saved = $this->service->saveValidated($post_id, $result, get_current_user_id(), 'valide');
        }

        wp_send_json_success([
            'fields'        => $result,
            'post_id'       => $post_id,
            'provider_used' => $used,
            'fallback_msg'  => $fallback_msg,
            'auto_mode'     => $auto_mode,
            'auto_saved'    => $auto_saved,
        ]);
    }

    /* =========================================================
       AJAX - Indexation en lot
    ========================================================= */

    public function ajaxIndexBatch(): void
    {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_ids = array_map('absint', (array) ($_POST['post_ids'] ?? []));
        $provider = sanitize_key($_POST['provider'] ?? 'claude');

        if (empty($post_ids)) {
            wp_send_json_error(['message' => 'Aucun article selectionne.']);
        }

        $results   = ['ok' => [], 'error' => []];
        $user_id   = get_current_user_id();
        $auto_mode = get_option('schilo_indexation_validation_mode', 'manuel') === 'auto';
        $statut    = $auto_mode ? 'valide' : 'en_attente';

        foreach ($post_ids as $post_id) {
            if (!$post_id) continue;
            $r = $this->service->indexArticleViaIA($post_id, $provider);
            if (is_wp_error($r)) {
                $results['error'][] = ['id' => $post_id, 'msg' => $r->get_error_message()];
                continue;
            }

            // Pre-remplit les champs proposes par l'IA. Statut selon le mode choisi
            // dans la configuration : "en_attente" (validation humaine requise) ou
            // "valide" directement si le mode automatique est active.
            $saved = $this->service->saveValidated($post_id, $r, $user_id, $statut);
            if ($saved) {
                $results['ok'][] = $post_id;
            } else {
                $results['error'][] = ['id' => $post_id, 'msg' => 'Echec enregistrement DB.'];
            }
        }

        $results['auto_mode'] = $auto_mode;
        wp_send_json_success($results);
    }

    /* =========================================================
       AJAX - Sauvegarder apres validation humaine
    ========================================================= */

    public function ajaxSaveValidated(): void
    {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        // wp_unslash obligatoire : WordPress ajoute des antislashs devant apostrophes/guillemets
        // sur tout $_POST (emulation historique des magic quotes), meme pour un tableau imbrique.
        $data    = wp_unslash((array) ($_POST['data'] ?? []));
        $post_id = absint($data['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(['message' => 'post_id manquant.']);
        }

        global $wpdb;
        $saved = $this->service->saveValidated($post_id, $data, get_current_user_id());

        if ($saved) {
            wp_send_json_success(['message' => 'Index valide et enregistre.', 'post_id' => $post_id]);
        } else {
            $db_err = $wpdb->last_error ?: 'Erreur inconnue (wpdb->insert/update a retourne false).';
            wp_send_json_error(['message' => 'Echec enregistrement DB : ' . $db_err]);
        }
    }

    /* =========================================================
       AJAX - Export XML template
    ========================================================= */

    public function ajaxExportXml(): void
    {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'post_id manquant.']);
        }

        $post    = get_post($post_id);
        $xml     = $this->service->generateXmlTemplate($post_id);
        $titre   = $post ? $post->post_title : '';
        $contenu = $post ? wp_strip_all_tags($post->post_content) : '';
        $contenu = mb_substr($contenu, 0, 8000);

        wp_send_json_success([
            'xml'     => $xml,
            'post_id' => $post_id,
            'titre'   => $titre,
            'contenu' => $contenu,
        ]);
    }

    /* =========================================================
       AJAX - Import XML ou JSON
    ========================================================= */

    public function ajaxImport(): void
    {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $raw     = stripslashes($_POST['content'] ?? '');
        $format  = sanitize_key($_POST['format'] ?? 'json');
        $post_id = absint($_POST['post_id'] ?? 0);

        if (empty($raw)) {
            wp_send_json_error(['message' => 'Contenu vide.']);
        }

        $parsed = ($format === 'xml')
            ? $this->service->parseXml($raw)
            : $this->service->parseJson($raw);

        if (is_wp_error($parsed)) {
            wp_send_json_error(['message' => $parsed->get_error_message()]);
        }

        wp_send_json_success(['fields' => $parsed, 'post_id' => $post_id]);
    }

    /* =========================================================
       AJAX - Mise a jour du statut
    ========================================================= */

    public function ajaxUpdateStatus(): void
    {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $statut  = sanitize_key($_POST['statut'] ?? '');
        $notes   = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if (!$post_id || !$statut) {
            wp_send_json_error(['message' => 'Parametres manquants.']);
        }

        $ok = $this->service->updateStatus($post_id, $statut, $notes);
        $ok ? wp_send_json_success() : wp_send_json_error(['message' => 'Mise a jour echouee.']);
    }

    /* =========================================================
       AJAX - Recuperer une fiche indexation existante
    ========================================================= */

    public function ajaxGetRecord(): void
    {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'post_id manquant.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'schilo_indexation';
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d", $post_id), ARRAY_A);

        if (!$row) {
            wp_send_json_error(['message' => 'Aucune fiche indexation pour cet article.']);
        }

        // Decoder les champs JSON
        $json_fields = ['categories','tags_wp','mots_cles','concepts','personnages','lieux',
                        'periodes','references_bibliques','citations_cles','seo_mots_cles',
                        'schema_json','articles_lies','articles_prerequis','articles_suite',
                        'sources_externes','champs_custom'];
        foreach ($json_fields as $f) {
            if (!empty($row[$f])) {
                $decoded = json_decode($row[$f], true);
                if (is_array($decoded)) $row[$f] = $decoded;
            }
        }

        wp_send_json_success(['fields' => $row, 'post_id' => $post_id]);
    }

    /* =========================================================
       Colonne "Indexation" dans la liste des articles WP
    ========================================================= */

    public function addIndexationColumn(array $columns): array
    {
        $columns['schilo_indexation'] = '<span title="Indexation Schilo" style="display:inline-flex;align-items:center;gap:4px;"><span class="dashicons dashicons-search" style="font-size:14px;height:14px;width:14px;"></span> Indexation</span>';
        return $columns;
    }

    public function renderIndexationColumn(string $column, int $post_id): void
    {
        if ($column !== 'schilo_indexation') return;

        $row = $this->service->getByPostId($post_id);
        if (!$row) {
            echo '<span style="color:#cbd5e1;">—</span>';
            return;
        }

        $statut  = $row['statut_indexation'] ?? '';
        $badges  = [
            'valide'     => '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">Valide</span>',
            'en_attente' => '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">En attente</span>',
            'brouillon'  => '<span style="background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:20px;font-size:11px;">Brouillon</span>',
            'rejete'     => '<span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:20px;font-size:11px;">Rejete</span>',
        ];
        echo $badges[$statut] ?? '<span style="color:#cbd5e1;">—</span>';

        if (!empty($row['date_validation'])) {
            $date = date_create($row['date_validation']);
            if ($date) {
                echo '<br><span style="font-size:11px;color:#94a3b8;">' . date_format($date, 'd/m/Y') . '</span>';
            }
        }
    }

    /* =========================================================
       Meta box "Indexation Schilo" dans l'editeur d'article
    ========================================================= */

    public function addIndexationMetaBox(): void
    {
        add_meta_box(
            'schilo_indexation_status',
            'Indexation Schilo',
            [$this, 'renderIndexationMetaBox'],
            'post',
            'side',
            'high'
        );
    }

    public function renderIndexationMetaBox(\WP_Post $post): void
    {
        $row      = $this->service->getByPostId($post->ID);
        $list_url = admin_url('admin.php?page=schilo-builder-indexation');

        if (!$row) {
            echo '<p style="color:#94a3b8;font-size:13px;margin:4px 0 10px;">Non indexe.</p>';
            echo '<div style="display:flex;flex-direction:column;gap:5px;">';
            echo '<a href="' . esc_url($list_url) . '" class="button button-small">Ouvrir l\'indexation</a>';
            echo '<a href="#revisionsdiv" class="button button-small" style="font-size:11px;" onclick="event.preventDefault();var el=document.getElementById(\'revisionsdiv\');if(el){el.scrollIntoView({behavior:\'smooth\'});}">Voir les revisions ↓</a>';
            echo '</div>';
            return;
        }

        $statut  = $row['statut_indexation'] ?? '';
        $source  = $row['source_indexation'] ?? '';
        $sources = ['manuel' => 'Manuel', 'claude' => 'Claude AI', 'openai' => 'ChatGPT', 'xml_import' => 'Import XML'];
        $badges  = [
            'valide'     => 'background:#dcfce7;color:#166534;',
            'en_attente' => 'background:#fef3c7;color:#92400e;',
            'brouillon'  => 'background:#f1f5f9;color:#475569;',
            'rejete'     => 'background:#fee2e2;color:#991b1b;',
        ];
        $badge_style = $badges[$statut] ?? 'background:#f1f5f9;color:#64748b;';
        $labels = ['valide' => 'Valide', 'en_attente' => 'En attente', 'brouillon' => 'Brouillon', 'rejete' => 'Rejete'];

        echo '<p style="margin:4px 0 10px;">';
        echo '<span style="' . $badge_style . 'padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">';
        echo esc_html($labels[$statut] ?? $statut);
        echo '</span></p>';

        $fields = [
            'Theme'       => $row['theme_principal'] ?? '',
            'SEO titre'   => $row['seo_titre']       ?? '',
            'Source'      => $sources[$source]        ?? $source,
        ];
        if (!empty($row['date_validation'])) {
            $d = date_create($row['date_validation']);
            $fields['Valide le'] = $d ? date_format($d, 'd/m/Y') : '';
        }

        echo '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        foreach ($fields as $label => $val) {
            if ($val === '') continue;
            echo '<tr>';
            echo '<td style="color:#64748b;padding:3px 0;white-space:nowrap;vertical-align:top;">' . esc_html($label) . ' :</td>';
            echo '<td style="padding:3px 0 3px 6px;color:#1e293b;">' . esc_html(mb_substr($val, 0, 60)) . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '<div style="margin-top:10px;display:flex;flex-direction:column;gap:5px;">';
        echo '<a href="' . esc_url($list_url) . '" class="button button-small" style="font-size:11px;">Modifier l\'indexation</a>';
        echo '<a href="#revisionsdiv" class="button button-small" style="font-size:11px;" onclick="event.preventDefault();var el=document.getElementById(\'revisionsdiv\');if(el){el.scrollIntoView({behavior:\'smooth\'});}">Voir les revisions ↓</a>';
        echo '</div>';
    }

    /* =========================================================
       AJAX - Liste articles avec statut indexation (AJAX)
    ========================================================= */

    public function ajaxGetArticles(): void
    {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'schilo_indexation';
        $prefix      = sanitize_text_field($_POST['prefix'] ?? '');
        $search      = sanitize_text_field($_POST['search'] ?? '');
        $statut      = sanitize_key($_POST['statut'] ?? '');
        $post_status = sanitize_key($_POST['post_status'] ?? 'publish');
        $paged       = max(1, absint($_POST['paged'] ?? 1));
        $per_page    = 40;
        $offset      = ($paged - 1) * $per_page;

        $where  = ["p.post_type = 'post'"];
        $params = [];

        $allowed_wp_statuses = ['publish', 'draft', 'pending'];
        if (in_array($post_status, $allowed_wp_statuses, true)) {
            $where[] = 'p.post_status = %s';
            $params[] = $post_status;
        } else {
            $where[] = "p.post_status IN ('" . implode("','", $allowed_wp_statuses) . "')";
        }

        if ($prefix !== '') {
            $where[]  = 'p.post_title LIKE %s';
            $params[] = $wpdb->esc_like($prefix) . '%';
        }
        if ($search !== '') {
            $where[]  = 'p.post_title LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        if ($statut === 'non_indexe') {
            $join_sql  = "LEFT JOIN {$table} i ON i.post_id = p.ID";
            $where_sql .= ' AND i.id IS NULL';
        } elseif ($statut === 'indexe') {
            $join_sql = "INNER JOIN {$table} i ON i.post_id = p.ID";
        } elseif ($statut !== '') {
            $join_sql = "INNER JOIN {$table} i ON i.post_id = p.ID AND i.statut_indexation = '" . esc_sql($statut) . "'";
        } else {
            $join_sql = "LEFT JOIN {$table} i ON i.post_id = p.ID";
        }

        $count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} p {$join_sql} {$where_sql}";
        $total     = $params
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
            : (int) $wpdb->get_var($count_sql);

        $data_sql = "SELECT p.ID, p.post_title, p.post_status, p.post_modified,
                            i.statut_indexation, i.source_indexation, i.date_validation
                     FROM {$wpdb->posts} p
                     {$join_sql}
                     {$where_sql}
                     ORDER BY p.post_title ASC
                     LIMIT {$per_page} OFFSET {$offset}";

        $rows = $params
            ? $wpdb->get_results($wpdb->prepare($data_sql, $params), ARRAY_A)
            : $wpdb->get_results($data_sql, ARRAY_A);

        // Compteurs globaux (respectent le filtre de statut WP courant) pour mettre a jour les stats sans recharger la page
        $status_where_sql = in_array($post_status, $allowed_wp_statuses, true)
            ? $wpdb->prepare("post_status = %s", $post_status)
            : "post_status IN ('" . implode("','", $allowed_wp_statuses) . "')";

        $total_posts   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND {$status_where_sql}");
        $total_indexed = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} i INNER JOIN {$wpdb->posts} p ON p.ID = i.post_id WHERE p.post_type='post' AND {$status_where_sql}"
        );
        $total_valides = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} i INNER JOIN {$wpdb->posts} p ON p.ID = i.post_id WHERE p.post_type='post' AND {$status_where_sql} AND i.statut_indexation='valide'"
        );
        $total_attente = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} i INNER JOIN {$wpdb->posts} p ON p.ID = i.post_id WHERE p.post_type='post' AND {$status_where_sql} AND i.statut_indexation='en_attente'"
        );

        wp_send_json_success([
            'rows'          => $rows ?: [],
            'total'         => $total,
            'per_page'      => $per_page,
            'paged'         => $paged,
            'pages'         => (int) ceil($total / $per_page),
            'stats'         => [
                'total'    => $total_posts,
                'valides'  => $total_valides,
                'attente'  => $total_attente,
                'non_indexes' => $total_posts - $total_indexed,
            ],
        ]);
    }
}