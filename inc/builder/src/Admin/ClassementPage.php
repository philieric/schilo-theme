<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\ClassementService;

class ClassementPage
{
    private ClassementService $service;

    public function __construct()
    {
        $this->service = new ClassementService();
        $this->service->maybeUpgradeTable();
    }

    public function register(): void
    {
        // Note : les taxonomies (schilo_parcours/theme/serie) sont enregistrees
        // depuis Plugin::run() en dehors du bloc is_admin(), pas ici, car cette
        // classe n'est instanciee que cote admin (voir SettingsPage::register()).

        add_action('wp_ajax_schilo_classement_classify',       [$this, 'ajaxClassifyArticle']);
        add_action('wp_ajax_schilo_classement_classify_batch', [$this, 'ajaxClassifyBatch']);
        add_action('wp_ajax_schilo_classement_save',       [$this, 'ajaxSaveClassement']);
        add_action('wp_ajax_schilo_classement_save_term',  [$this, 'ajaxSaveTerm']);
        add_action('wp_ajax_schilo_classement_delete_term', [$this, 'ajaxDeleteTerm']);
        add_action('wp_ajax_schilo_classement_save_term_order', [$this, 'ajaxSaveTermOrder']);
        add_action('wp_ajax_schilo_classement_save_term_description', [$this, 'ajaxSaveTermDescription']);
        add_action('wp_ajax_schilo_classement_propose_term_structure',    [$this, 'ajaxProposeTermStructure']);
        add_action('wp_ajax_schilo_classement_propose_term_descriptions', [$this, 'ajaxProposeTermDescriptions']);
        add_action('wp_ajax_schilo_classement_generate_term_description', [$this, 'ajaxGenerateTermDescription']);
        add_action('wp_ajax_schilo_classement_apply_terms',     [$this, 'ajaxApplyTermCuration']);
    }

    public function renderPage(): void
    {
        $tab = sanitize_key($_GET['tab'] ?? 'list');
        switch ($tab) {
            case 'validation':
                include SCHILO_BUILDER_PATH . 'views/admin/classement-validation.php';
                break;
            case 'termes':
                include SCHILO_BUILDER_PATH . 'views/admin/classement-termes.php';
                break;
            case 'config':
                include SCHILO_BUILDER_PATH . 'views/admin/classement-config.php';
                break;
            default:
                include SCHILO_BUILDER_PATH . 'views/admin/classement-page.php';
        }
    }

    /* =========================================================
       AJAX - Proposer un classement via IA (ne sauvegarde rien)
    ========================================================= */

    public function ajaxClassifyArticle(): void
    {
        @set_time_limit(120);

        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_id  = absint($_POST['post_id'] ?? 0);
        $provider = sanitize_key($_POST['provider'] ?? 'claude');

        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(['message' => 'Article introuvable.']);
        }

        $ia_config = get_option('schilo_ia_config', []);
        $key = $ia_config[$provider]['api_key'] ?? '';
        if (empty($key)) {
            wp_send_json_error(['message' => 'Cle API ' . $provider . ' non configuree. Allez dans Schilo Builder > IA.']);
        }

        $result = $this->service->classifyArticleViaIA($post_id, $provider);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['suggestion' => $result, 'post_id' => $post_id]);
    }

    /* =========================================================
       AJAX - Classer en lot via IA (suggestion stockee, revue differee
       sauf en mode "auto" ou l'enregistrement est immediat)
    ========================================================= */

    public function ajaxClassifyBatch(): void
    {
        @set_time_limit(300);

        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_ids = array_map('absint', (array) ($_POST['post_ids'] ?? []));
        $provider = sanitize_key($_POST['provider'] ?? 'claude');

        if (empty($post_ids)) {
            wp_send_json_error(['message' => 'Aucun article selectionne.']);
        }

        $ia_config = get_option('schilo_ia_config', []);
        if (empty($ia_config[$provider]['api_key'] ?? '')) {
            wp_send_json_error(['message' => 'Cle API ' . $provider . ' non configuree. Allez dans Schilo Builder > IA.']);
        }

        $auto_mode = get_option('schilo_classement_validation_mode', 'manuel') === 'auto';
        $user_id   = get_current_user_id();
        $results   = ['ok' => [], 'error' => []];

        foreach ($post_ids as $post_id) {
            if (!$post_id) continue;

            $suggestion = $this->service->classifyArticleViaIA($post_id, $provider);
            if (is_wp_error($suggestion)) {
                $results['error'][] = ['id' => $post_id, 'msg' => $suggestion->get_error_message()];
                continue;
            }

            $resolved = $this->service->resolveSuggestionTermIds($suggestion);
            $this->service->storeSuggestion($post_id, $resolved);

            if ($auto_mode) {
                $rule_issue = null;
                foreach (ClassementService::TAXONOMY_FIELD_MAP as $taxonomy => $field) {
                    $rule_issue = $this->service->checkPrefixRulesForTaxonomy($post_id, $resolved[$field] ?? [], $taxonomy);
                    if ($rule_issue !== null) break;
                }
                if ($rule_issue !== null) {
                    $results['error'][] = ['id' => $post_id, 'msg' => $rule_issue];
                    continue;
                }

                $saved = $this->service->saveClassement($post_id, $resolved, $user_id);
                if (!$saved) {
                    $results['error'][] = ['id' => $post_id, 'msg' => 'Echec enregistrement DB.'];
                    continue;
                }
            }

            $results['ok'][] = $post_id;
        }

        $results['auto_mode'] = $auto_mode;
        wp_send_json_success($results);
    }

    /* =========================================================
       AJAX - Enregistrer le classement apres validation humaine
    ========================================================= */

    public function ajaxSaveClassement(): void
    {
        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(['message' => 'Article introuvable.']);
        }

        $theme_ids    = array_map('absint', (array) ($_POST['theme_term_ids'] ?? []));
        $parcours_ids = array_map('absint', (array) ($_POST['parcours_term_ids'] ?? []));
        $serie_ids    = array_map('absint', (array) ($_POST['serie_term_ids'] ?? []));

        // Creation a la volee de nouveaux termes tapes en texte libre
        // wp_unslash obligatoire : WordPress ajoute des antislashs devant apostrophes/guillemets sur tout $_POST.
        $new_theme    = sanitize_text_field(wp_unslash($_POST['new_theme'] ?? ''));
        $new_theme_parent = absint($_POST['new_theme_parent'] ?? 0);
        $new_parcours = sanitize_text_field(wp_unslash($_POST['new_parcours'] ?? ''));
        $new_parcours_parent = absint($_POST['new_parcours_parent'] ?? 0);
        $new_serie    = sanitize_text_field(wp_unslash($_POST['new_serie'] ?? ''));

        if ($new_theme !== '') {
            $term = $this->service->findOrCreateTerm('schilo_theme', $new_theme, $new_theme_parent);
            if (!is_wp_error($term)) $theme_ids[] = (int) $term['term_id'];
        }
        if ($new_parcours !== '') {
            $term = $this->service->findOrCreateTerm('schilo_parcours', $new_parcours, $new_parcours_parent);
            if (!is_wp_error($term)) $parcours_ids[] = (int) $term['term_id'];
        }
        if ($new_serie !== '') {
            $term = $this->service->findOrCreateTerm('schilo_serie', $new_serie, 0);
            if (!is_wp_error($term)) $serie_ids[] = (int) $term['term_id'];
        }

        $ordres_raw = (array) ($_POST['ordres'] ?? []);
        $ordres = [];
        foreach ($ordres_raw as $term_id => $ordre) {
            $ordres[absint($term_id)] = absint($ordre);
        }

        $ids_by_taxonomy = ['schilo_theme' => $theme_ids, 'schilo_parcours' => $parcours_ids, 'schilo_serie' => $serie_ids];
        foreach (ClassementService::TAXONOMY_FIELD_MAP as $taxonomy => $field) {
            $rule_issue = $this->service->checkPrefixRulesForTaxonomy($post_id, $ids_by_taxonomy[$taxonomy], $taxonomy);
            if ($rule_issue !== null) {
                wp_send_json_error(['message' => $rule_issue]);
            }
        }

        $saved = $this->service->saveClassement($post_id, [
            'theme_term_ids'    => $theme_ids,
            'parcours_term_ids' => $parcours_ids,
            'serie_term_ids'    => $serie_ids,
            'ordres'            => $ordres,
        ], get_current_user_id());

        if ($saved) {
            wp_send_json_success(['message' => 'Classement enregistre.', 'post_id' => $post_id]);
        } else {
            wp_send_json_error(['message' => 'Echec enregistrement (article pas encore indexe ?).']);
        }
    }

    /* =========================================================
       AJAX - Gestion des termes (page "Termes")
    ========================================================= */

    public function ajaxSaveTerm(): void
    {
        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $taxonomy    = sanitize_key($_POST['taxonomy'] ?? '');
        $name        = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $parent      = absint($_POST['parent'] ?? 0);
        $ordre       = absint($_POST['ordre'] ?? 0);
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));

        if (!$this->service->isValidTaxonomy($taxonomy) || $name === '') {
            wp_send_json_error(['message' => 'Parametres invalides.']);
        }

        $result = $this->service->createTerm($taxonomy, $name, $parent, $ordre, $description);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['term_id' => $result['term_id']]);
    }

    public function ajaxDeleteTerm(): void
    {
        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
        $term_id  = absint($_POST['term_id'] ?? 0);

        if (!$term_id || !$this->service->deleteTerm($term_id, $taxonomy)) {
            wp_send_json_error(['message' => 'Suppression impossible.']);
        }

        wp_send_json_success();
    }

    public function ajaxSaveTermOrder(): void
    {
        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $term_id = absint($_POST['term_id'] ?? 0);
        $ordre   = absint($_POST['ordre'] ?? 0);

        if (!$term_id || !$this->service->updateTermOrder($term_id, $ordre)) {
            wp_send_json_error(['message' => 'Mise a jour impossible.']);
        }

        wp_send_json_success();
    }

    public function ajaxSaveTermDescription(): void
    {
        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $taxonomy    = sanitize_key($_POST['taxonomy'] ?? '');
        $term_id     = absint($_POST['term_id'] ?? 0);
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));

        if (!$term_id || !$this->service->updateTermDescription($term_id, $taxonomy, $description)) {
            wp_send_json_error(['message' => 'Mise a jour impossible.']);
        }

        wp_send_json_success();
    }

    /* =========================================================
       AJAX - Curation IA du vocabulaire (parcours/theme/serie)
       Propose une suggestion (aucune ecriture) puis, apres revue
       humaine, applique la selection retenue.
    ========================================================= */

    /**
     * Phase 1/2 : structure seule (noms + hierarchie, sans description),
     * un appel IA rapide par taxonomie. Les descriptions sont demandees
     * ensuite par petits lots via ajaxProposeTermDescriptions() — voir
     * ClassementService::proposeTermStructure() pour le pourquoi de la
     * separation (une reponse combinee avec descriptions saturait/timeoutait).
     */
    public function ajaxProposeTermStructure(): void
    {
        @set_time_limit(180);

        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $provider  = sanitize_key($_POST['provider'] ?? 'claude');
        $ia_config = get_option('schilo_ia_config', []);
        if (empty($ia_config[$provider]['api_key'] ?? '')) {
            wp_send_json_error(['message' => 'Cle API ' . $provider . ' non configuree. Allez dans Schilo Builder > IA.']);
        }

        $structure = $this->service->proposeTermStructure($provider);
        if (is_wp_error($structure)) {
            wp_send_json_error(['message' => $structure->get_error_message()]);
        }

        wp_send_json_success(['structure' => $structure]);
    }

    /**
     * Phase 2/2 : decrit un petit lot de termes deja nommes (voir
     * ajaxProposeTermStructure). Appelee plusieurs fois de suite par le JS,
     * un lot a la fois, avec affichage de la progression.
     */
    public function ajaxProposeTermDescriptions(): void
    {
        @set_time_limit(120);

        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $provider  = sanitize_key($_POST['provider'] ?? 'claude');
        $ia_config = get_option('schilo_ia_config', []);
        if (empty($ia_config[$provider]['api_key'] ?? '')) {
            wp_send_json_error(['message' => 'Cle API ' . $provider . ' non configuree. Allez dans Schilo Builder > IA.']);
        }

        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
        if (!$this->service->isValidTaxonomy($taxonomy)) {
            wp_send_json_error(['message' => 'Taxonomie invalide.']);
        }

        $names_raw = json_decode(stripslashes((string) ($_POST['names'] ?? '[]')), true);
        $names = array_values(array_filter(array_map(fn($n) => sanitize_text_field((string) $n), (array) $names_raw)));
        if (empty($names)) {
            wp_send_json_error(['message' => 'Aucun terme a decrire.']);
        }

        $descriptions = $this->service->proposeTermDescriptions($provider, $taxonomy, $names);
        if (is_wp_error($descriptions)) {
            wp_send_json_error(['message' => $descriptions->get_error_message()]);
        }

        wp_send_json_success(['descriptions' => $descriptions]);
    }

    /**
     * Genere/regenere la description d'UN seul terme (boutons individuels de
     * la vue Termes, sur le nom et sur la description).
     */
    public function ajaxGenerateTermDescription(): void
    {
        @set_time_limit(90);

        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $provider  = sanitize_key($_POST['provider'] ?? 'claude');
        $ia_config = get_option('schilo_ia_config', []);
        if (empty($ia_config[$provider]['api_key'] ?? '')) {
            wp_send_json_error(['message' => 'Cle API ' . $provider . ' non configuree. Allez dans Schilo Builder > IA.']);
        }

        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
        $term_id  = absint($_POST['term_id'] ?? 0);
        if (!$term_id) {
            wp_send_json_error(['message' => 'Terme invalide.']);
        }

        $result = $this->service->generateSingleTermDescription($provider, $taxonomy, $term_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public function ajaxApplyTermCuration(): void
    {
        check_ajax_referer('schilo_classement', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acces refuse.'], 403);
        }

        $raw = stripslashes((string) ($_POST['suggestion'] ?? ''));
        $suggestion = json_decode($raw, true);
        if (!is_array($suggestion)) {
            wp_send_json_error(['message' => 'Suggestion invalide ou vide.']);
        }

        $summary = $this->service->applyTermCuration($suggestion);
        wp_send_json_success($summary);
    }
}
