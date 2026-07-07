<?php

namespace Schilo\Builder\Service;

class ClassementService
{
    private string $table;

    public const TAXONOMIES = ['schilo_parcours', 'schilo_theme', 'schilo_serie'];

    /** Term meta : date/heure de la derniere generation de description via IA. */
    public const DESC_GENERATED_META = 'schilo_ia_desc_generated_at';

    /** Option : regles de classement par prefixe d'article (role/poids/limite). */
    public const PREFIX_RULES_OPTION = 'schilo_classement_prefix_rules';
    private const PREFIX_ROLES = ['principal', 'complement', 'exclu'];

    /** Mapping taxonomie -> cle de champ *_term_ids (resolveSuggestionTermIds/AJAX). */
    public const TAXONOMY_FIELD_MAP = [
        'schilo_theme'    => 'theme_term_ids',
        'schilo_parcours' => 'parcours_term_ids',
        'schilo_serie'    => 'serie_term_ids',
    ];

    /** Option : reglages de rotation periodique sur l'accueil (une entree par taxonomie). */
    public const ROTATION_OPTION = 'schilo_classement_rotation';

    /** Valeurs par defaut par taxonomie — reprennent le comportement historique (pas de rotation). */
    private const ROTATION_DEFAULTS = [
        'schilo_parcours' => ['enabled' => false, 'interval_days' => 7, 'count' => 3],
        'schilo_theme'    => ['enabled' => false, 'interval_days' => 7, 'count' => 4],
        'schilo_serie'    => ['enabled' => false, 'interval_days' => 7, 'count' => 8],
    ];

    /** Libelles pour les messages d'erreur de checkPrefixRulesForTaxonomy(). */
    private const TAXONOMY_ARTICLE_LABELS = [
        'schilo_parcours' => 'le parcours',
        'schilo_theme'    => 'le theme',
        'schilo_serie'    => 'la serie',
    ];

    private function taxonomyArticleLabel(string $taxonomy): string
    {
        return self::TAXONOMY_ARTICLE_LABELS[$taxonomy] ?? $taxonomy;
    }

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'schilo_indexation';
    }

    /**
     * Bornes (mots) configurables pour les descriptions de termes generees
     * via IA — reglables dans Schilo Builder > Parcours & Themes > Configuration.
     */
    public function getDescriptionWordRange(): array
    {
        $option = get_option('schilo_classement_desc_words', []);
        $min = isset($option['min']) ? max(20, (int) $option['min']) : 150;
        $max = isset($option['max']) ? max($min, (int) $option['max']) : 250;
        return ['min' => $min, 'max' => $max];
    }

    /**
     * Bornes (nombre de paragraphes courts) configurables pour les descriptions
     * de termes generees via IA — force un texte aere plutot qu'un seul bloc,
     * reglable au meme endroit que la longueur en mots.
     */
    public function getDescriptionParagraphRange(): array
    {
        $option = get_option('schilo_classement_desc_paragraphs', []);
        $min = isset($option['min']) ? max(1, (int) $option['min']) : 2;
        $max = isset($option['max']) ? max($min, (int) $option['max']) : 4;
        return ['min' => $min, 'max' => $max];
    }

    /* =========================================================
       ROTATION PERIODIQUE SUR L'ACCUEIL (parcours / themes / series)
       Meme principe que Schilo_Featured::get() (voir class-schilo-featured.php) :
       aucun cron, une simple fenetre deterministe basee sur le temps ecoule,
       identique pour tous les visiteurs simultanes et compatible avec le cache.
    ========================================================= */

    /**
     * Reglages de rotation pour une taxonomie ('schilo_parcours', 'schilo_theme'
     * ou 'schilo_serie'), regles dans Parcours & Themes > Configuration.
     */
    public function getRotationSettings(string $taxonomy): array
    {
        $defaults = self::ROTATION_DEFAULTS[$taxonomy] ?? ['enabled' => false, 'interval_days' => 7, 'count' => 4];
        $saved    = get_option(self::ROTATION_OPTION, []);
        $raw      = is_array($saved) ? ($saved[$taxonomy] ?? []) : [];

        return [
            'enabled'       => array_key_exists('enabled', $raw) ? !empty($raw['enabled']) : $defaults['enabled'],
            'interval_days' => isset($raw['interval_days']) ? max(1, (int) $raw['interval_days']) : $defaults['interval_days'],
            'count'         => isset($raw['count']) ? max(1, (int) $raw['count']) : $defaults['count'],
        ];
    }

    public function saveRotationSettings(array $settings): void
    {
        $clean = [];
        foreach (self::TAXONOMIES as $taxonomy) {
            $raw = (array) ($settings[$taxonomy] ?? []);
            $clean[$taxonomy] = [
                'enabled'       => !empty($raw['enabled']),
                'interval_days' => max(1, (int) ($raw['interval_days'] ?? 7)),
                'count'         => max(1, (int) ($raw['count'] ?? 4)),
            ];
        }
        update_option(self::ROTATION_OPTION, $clean, false);
    }

    /**
     * Selectionne, parmi un pool ordonne de term_id, la fenetre a afficher
     * "maintenant" sur l'accueil pour une taxonomie donnee. Si la rotation est
     * desactivee ou que le pool est trop petit pour tourner, renvoie simplement
     * les N premiers termes du pool (comportement historique, sans rotation).
     *
     * @param int[] $pool_term_ids Termes deja ordonnes (ordre manuel ou popularite).
     * @return int[] Sous-ensemble ordonne de term_id a afficher.
     */
    public function getRotatedTermIds(string $taxonomy, array $pool_term_ids): array
    {
        $settings = $this->getRotationSettings($taxonomy);
        $count    = $settings['count'];
        $total    = count($pool_term_ids);

        if ($total === 0) {
            return [];
        }

        $window_count = (int) floor($total / $count);
        if (!$settings['enabled'] || $count >= $total || $window_count < 1) {
            return array_slice($pool_term_ids, 0, $count);
        }

        $interval_seconds = $settings['interval_days'] * DAY_IN_SECONDS;
        $slot   = (int) floor(time() / $interval_seconds);
        $window = $slot % $window_count;

        return array_slice($pool_term_ids, $window * $count, $count);
    }

    /* =========================================================
       INSTALLATION / MISE A NIVEAU DE LA TABLE
    ========================================================= */

    public function maybeUpgradeTable(): void
    {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") !== $this->table) {
            return;
        }

        $columns = [
            'statut_classement' => "ALTER TABLE {$this->table} ADD COLUMN statut_classement ENUM('non_classe','classe') NOT NULL DEFAULT 'non_classe' AFTER statut_indexation",
            'date_classement'   => "ALTER TABLE {$this->table} ADD COLUMN date_classement DATETIME DEFAULT NULL AFTER statut_classement",
            'classe_par'        => "ALTER TABLE {$this->table} ADD COLUMN classe_par INT UNSIGNED DEFAULT NULL AFTER date_classement",
        ];

        foreach ($columns as $col => $sql) {
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$this->table} LIKE '{$col}'");
            if (!$exists) {
                $wpdb->query($sql);
            }
        }
    }

    /* =========================================================
       TAXONOMIES - enregistrees sur 'init', y compris cote front
    ========================================================= */

    public function registerTaxonomies(): void
    {
        register_taxonomy('schilo_parcours', 'post', [
            'labels' => [
                'name'          => 'Parcours',
                'singular_name' => 'Parcours',
                'menu_name'     => 'Parcours',
                'add_new_item'  => 'Ajouter un parcours',
                'edit_item'     => 'Modifier le parcours',
                'search_items'  => 'Rechercher un parcours',
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => false,
            'show_in_menu'      => false,
            'show_in_nav_menus' => false,
            'show_admin_column' => false,
            'show_in_rest'      => false,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'parcours', 'with_front' => false],
        ]);

        register_taxonomy('schilo_theme', 'post', [
            'labels' => [
                'name'          => 'Themes',
                'singular_name' => 'Theme',
                'menu_name'     => 'Themes',
                'add_new_item'  => 'Ajouter un theme',
                'edit_item'     => 'Modifier le theme',
                'search_items'  => 'Rechercher un theme',
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => false,
            'show_in_menu'      => false,
            'show_in_nav_menus' => false,
            'show_admin_column' => false,
            'show_in_rest'      => false,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'theme', 'with_front' => false],
        ]);

        register_taxonomy('schilo_serie', 'post', [
            'labels' => [
                'name'          => 'Series',
                'singular_name' => 'Serie',
                'menu_name'     => 'Series',
                'add_new_item'  => 'Ajouter une serie',
                'edit_item'     => 'Modifier la serie',
                'search_items'  => 'Rechercher une serie',
            ],
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => false,
            'show_in_menu'      => false,
            'show_in_nav_menus' => false,
            'show_admin_column' => false,
            'show_in_rest'      => false,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'serie', 'with_front' => false],
        ]);
    }

    public function isValidTaxonomy(string $taxonomy): bool
    {
        return in_array($taxonomy, self::TAXONOMIES, true);
    }

    /* =========================================================
       PAGES D'INDEX PAR PERSONNAGE / LIEU / MOT-CLE / REFERENCE
       Ces 4 champs sont du texte libre indexe par IA (pas des
       taxonomies WP) : on leur donne une page d'archive "virtuelle"
       via des rewrite rules + template_include, plutot qu'un
       register_taxonomy (qui imposerait un vocabulaire ferme).
    ========================================================= */

    public const INDEX_FIELDS = [
        'personnages'          => ['slug' => 'personnage',         'label' => 'Personnage',          'icon' => 'ti-users'],
        'lieux'                => ['slug' => 'lieu',                'label' => 'Lieu',                 'icon' => 'ti-map-pin'],
        'mots_cles'            => ['slug' => 'mot-cle',             'label' => 'Mot-clé',              'icon' => 'ti-tags'],
        'references_bibliques' => ['slug' => 'reference-biblique',  'label' => 'Référence biblique',   'icon' => 'ti-bible'],
    ];

    private const INDEX_REWRITES_VERSION = '1';

    /**
     * Construit l'URL de la page d'index pour une valeur donnee d'un des 4
     * champs libres ci-dessus (utilise par la sidebar pour rendre les tags
     * cliquables).
     */
    public static function getIndexUrl(string $field, string $value): string
    {
        if (!isset(self::INDEX_FIELDS[$field])) return '';
        return home_url('/' . self::INDEX_FIELDS[$field]['slug'] . '/' . rawurlencode($value) . '/');
    }

    /**
     * Rewrite rules + query vars pour les 4 pages d'index. Hookee sur
     * 'init' comme registerTaxonomies(), en dehors du bloc is_admin().
     */
    public function registerIndexRewrites(): void
    {
        add_filter('query_vars', function (array $vars): array {
            $vars[] = 'schilo_index_field';
            $vars[] = 'schilo_index_value';
            return $vars;
        });

        foreach (self::INDEX_FIELDS as $field => $meta) {
            add_rewrite_rule(
                '^' . $meta['slug'] . '/([^/]+)/?$',
                'index.php?schilo_index_field=' . $field . '&schilo_index_value=$matches[1]',
                'top'
            );
        }

        if (get_option('schilo_index_rewrites_version') !== self::INDEX_REWRITES_VERSION) {
            flush_rewrite_rules();
            update_option('schilo_index_rewrites_version', self::INDEX_REWRITES_VERSION);
        }
    }

    /**
     * Articles (post_id) dont le champ indexe $field contient exactement
     * $value. Prefiltre par une valeur non vide, puis comparaison exacte
     * apres decodage JSON (evite les faux positifs d'un LIKE brut sur la
     * chaine JSON).
     */
    public function getPostIdsByIndexedValue(string $field, string $value): array
    {
        if (!isset(self::INDEX_FIELDS[$field])) return [];

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT post_id, {$field} AS val FROM {$this->table} WHERE {$field} != '' AND statut_indexation = 'valide'",
            ARRAY_A
        );

        $post_ids = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['val'] ?? '[]', true);
            if (!is_array($decoded)) continue;
            foreach ($decoded as $v) {
                if (trim((string) $v) === $value) {
                    $post_ids[] = (int) $row['post_id'];
                    break;
                }
            }
        }
        return $post_ids;
    }

    /* =========================================================
       TERMES CONTROLES - CRUD + ordre
    ========================================================= */

    public function getTerms(string $taxonomy, int $parent = -1): array
    {
        if (!$this->isValidTaxonomy($taxonomy)) return [];

        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'meta_value_num',
            'meta_key'   => 'schilo_ordre',
            'order'      => 'ASC',
        ];
        if ($parent >= 0) $args['parent'] = $parent;

        $terms = get_terms($args);
        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Renvoie les termes d'une taxonomie sous forme d'arbre (parent > enfants),
     * tries par meta 'schilo_ordre'.
     */
    public function getTermsTree(string $taxonomy): array
    {
        $all = $this->getTerms($taxonomy);
        $tree = [];
        foreach ($all as $term) {
            if ((int) $term->parent === 0) {
                $term->children = array_values(array_filter($all, fn($t) => (int) $t->parent === (int) $term->term_id));
                $tree[] = $term;
            }
        }
        return $tree;
    }

    public function createTerm(string $taxonomy, string $name, int $parent = 0, int $ordre = 0, string $description = ''): array|\WP_Error
    {
        if (!$this->isValidTaxonomy($taxonomy)) {
            return new \WP_Error('bad_taxonomy', 'Taxonomie inconnue.');
        }

        $result = wp_insert_term($name, $taxonomy, ['parent' => $parent, 'description' => $description]);
        if (is_wp_error($result)) return $result;

        update_term_meta((int) $result['term_id'], 'schilo_ordre', $ordre);
        return $result;
    }

    public function updateTermOrder(int $term_id, int $ordre): bool
    {
        return (bool) update_term_meta($term_id, 'schilo_ordre', $ordre);
    }

    public function updateTermDescription(int $term_id, string $taxonomy, string $description): bool
    {
        if (!$this->isValidTaxonomy($taxonomy)) return false;
        $result = wp_update_term($term_id, $taxonomy, ['description' => $description]);
        return !is_wp_error($result);
    }

    /**
     * Marque un terme comme ayant une description generee via IA a l'instant
     * present — permet d'afficher un statut ("Genere le ...") et d'eviter de
     * regenerer inutilement lors des prochains passages de la curation en lot.
     */
    public function markDescriptionGenerated(int $term_id): void
    {
        update_term_meta($term_id, self::DESC_GENERATED_META, current_time('mysql'));
    }

    public function getDescriptionGeneratedAt(int $term_id): string
    {
        return (string) get_term_meta($term_id, self::DESC_GENERATED_META, true);
    }

    public function deleteTerm(int $term_id, string $taxonomy): bool
    {
        if (!$this->isValidTaxonomy($taxonomy)) return false;
        $result = wp_delete_term($term_id, $taxonomy);
        return $result === true;
    }

    /**
     * Cree un terme s'il n'existe pas deja (recherche par nom exact), sinon renvoie
     * l'existant — et met a jour sa description si une nouvelle est fournie (ne
     * renomme/supprime jamais un terme existant, seule la description est synchronisee).
     */
    public function findOrCreateTerm(string $taxonomy, string $name, int $parent = 0, string $description = ''): array|\WP_Error
    {
        $name = trim($name);
        if ($name === '') return new \WP_Error('empty_name', 'Nom de terme vide.');

        $existing = get_term_by('name', $name, $taxonomy);
        if ($existing) {
            if ($description !== '' && $description !== $existing->description) {
                $this->updateTermDescription((int) $existing->term_id, $taxonomy, $description);
            }
            return ['term_id' => (int) $existing->term_id, 'term_taxonomy_id' => (int) $existing->term_taxonomy_id];
        }

        return $this->createTerm($taxonomy, $name, $parent, 0, $description);
    }

    /* =========================================================
       ORDRE DES ARTICLES AU SEIN D'UN TERME (parcours / serie)
    ========================================================= */

    public function setPostOrderInTerm(int $post_id, int $term_id, int $ordre): void
    {
        update_post_meta($post_id, '_schilo_ordre_' . $term_id, $ordre);
    }

    public function getPostOrderInTerm(int $post_id, int $term_id): int
    {
        $val = get_post_meta($post_id, '_schilo_ordre_' . $term_id, true);
        return $val === '' ? 0 : (int) $val;
    }

    /* =========================================================
       LECTURE STATUT CLASSEMENT
    ========================================================= */

    public function getByPostId(int $post_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE post_id = %d", $post_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function getIndexedTermsForPost(int $post_id): array
    {
        $out = [];
        foreach (self::TAXONOMIES as $tax) {
            $terms = wp_get_object_terms($post_id, $tax);
            $out[$tax] = is_wp_error($terms) ? [] : $terms;
        }
        return $out;
    }

    public function getList(int $per_page = 20, int $paged = 1, string $statut_classement = '', string $prefix = ''): array
    {
        global $wpdb;
        $offset = ($paged - 1) * $per_page;

        $where = " AND i.statut_indexation = 'valide'";
        if ($statut_classement !== '') {
            $where .= $wpdb->prepare(" AND i.statut_classement = %s", $statut_classement);
        }
        if ($prefix !== '') {
            $where .= $wpdb->prepare(" AND i.titre LIKE %s", $wpdb->esc_like($prefix) . '%');
        }

        $rows = $wpdb->get_results(
            "SELECT i.post_id, i.titre, i.theme_principal, i.sous_theme, i.parcours, i.serie,
                    i.ordre_serie, i.statut_classement, i.date_classement,
                    (pm.meta_id IS NOT NULL) as has_suggestion
             FROM {$this->table} i
             JOIN {$wpdb->posts} p ON p.ID = i.post_id
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = i.post_id AND pm.meta_key = '_schilo_classement_suggestion'
             WHERE 1=1 {$where}
             ORDER BY i.titre ASC
             LIMIT {$per_page} OFFSET {$offset}",
            ARRAY_A
        );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} i JOIN {$wpdb->posts} p ON p.ID = i.post_id WHERE 1=1 {$where}"
        );

        return ['rows' => $rows ?: [], 'total' => $total];
    }

    /**
     * Prefixes distincts (3 lettres majuscules en tete de titre) parmi les
     * articles indexes valides, avec leur nombre d'articles.
     */
    public function getPrefixCounts(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT LEFT(titre, 3) as pfx, COUNT(*) as n
             FROM {$this->table}
             WHERE statut_indexation = 'valide' AND titre REGEXP '^[A-Z]{3}'
             GROUP BY pfx
             ORDER BY pfx ASC",
            ARRAY_A
        );

        $prefixes = [];
        foreach ($rows as $row) {
            $prefixes[$row['pfx']] = (int) $row['n'];
        }
        return $prefixes;
    }

    /* =========================================================
       REGLES DE CLASSEMENT PAR PREFIXE (role / poids / limite)
       Tous les articles n'ont pas la meme vocation editoriale (ex: une
       Annexe complete un PER, elle ne devrait jamais devenir une etape
       numerotee au meme titre). Regles configurables dans Parcours &
       Themes > Configuration, appliquees pour l'instant a schilo_parcours
       uniquement (voir plan feature/reglages-parcours-themes-series).
    ========================================================= */

    private function normalizePrefixRule($raw): array
    {
        $raw  = is_array($raw) ? $raw : [];
        $role = in_array($raw['role'] ?? '', self::PREFIX_ROLES, true) ? $raw['role'] : 'principal';
        return [
            'role'   => $role,
            'poids'  => isset($raw['poids']) ? max(0, min(100, (int) $raw['poids'])) : 50,
            'limite' => isset($raw['limite']) ? max(0, (int) $raw['limite']) : 0,
        ];
    }

    /**
     * Regles pour tous les prefixes reellement presents (indexes ou deja
     * mappes a une categorie WP dans Prefixes & categories), avec des
     * valeurs par defaut (principal, poids 50, illimite) pour tout prefixe
     * non configure — retrocompatible : rien ne change tant que l'utilisateur
     * n'a rien regle.
     */
    public function getPrefixRules(): array
    {
        $saved = get_option(self::PREFIX_RULES_OPTION, []);
        if (!is_array($saved)) $saved = [];

        $known = array_unique(array_merge(
            array_keys($this->getPrefixCounts()),
            array_keys((array) get_option(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES, []))
        ));
        sort($known);

        $rules = [];
        foreach ($known as $prefix) {
            $rules[$prefix] = $this->normalizePrefixRule($saved[$prefix] ?? []);
        }
        return $rules;
    }

    public function getPrefixRule(string $prefix): array
    {
        $saved = get_option(self::PREFIX_RULES_OPTION, []);
        return $this->normalizePrefixRule(is_array($saved) ? ($saved[$prefix] ?? []) : []);
    }

    public function savePrefixRules(array $rules): void
    {
        $clean = [];
        foreach ($rules as $prefix => $rule) {
            $prefix = strtoupper(sanitize_key($prefix));
            if ($prefix === '') continue;
            $clean[$prefix] = $this->normalizePrefixRule($rule);
        }
        update_option(self::PREFIX_RULES_OPTION, $clean, false);
    }

    /**
     * Nombre d'articles d'un prefixe donne deja classes dans un terme precis
     * d'une taxonomie donnee (pour l'enforcement de la limite, partagee entre
     * parcours/theme/serie). $exclude_post_id permet de ne pas se compter
     * soi-meme lors d'une re-sauvegarde.
     */
    public function countPrefixInTerm(string $prefix, int $term_id, string $taxonomy, int $exclude_post_id = 0): int
    {
        $post_ids = get_objects_in_term($term_id, $taxonomy);
        if (is_wp_error($post_ids) || empty($post_ids)) return 0;

        $post_ids = array_map('intval', $post_ids);
        if ($exclude_post_id) {
            $post_ids = array_values(array_diff($post_ids, [$exclude_post_id]));
        }
        if (empty($post_ids)) return 0;

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE prefix = %s AND post_id IN ({$placeholders})",
            $prefix,
            ...$post_ids
        ));
    }

    /**
     * Verifie les regles de prefixe (exclusion + limite) pour un post et une
     * liste de term_ids cibles d'une taxonomie donnee (schilo_parcours,
     * schilo_theme ou schilo_serie — la regle est partagee entre les 3).
     * Renvoie null si tout est OK, ou un message d'erreur explicite sinon.
     * A appeler AVANT saveClassement().
     */
    public function checkPrefixRulesForTaxonomy(int $post_id, array $term_ids, string $taxonomy): ?string
    {
        $term_ids = array_values(array_filter(array_map('absint', $term_ids)));
        if (empty($term_ids)) return null;

        $indexed = $this->getByPostId($post_id);
        $prefix  = $indexed['prefix'] ?? '';
        if ($prefix === '') return null;

        $rule = $this->getPrefixRule($prefix);

        if ($rule['role'] === 'exclu') {
            return "Le prefixe {$prefix} est exclu du classement (regle definie dans Parcours & Themes > Configuration).";
        }

        if ($rule['limite'] > 0) {
            foreach ($term_ids as $term_id) {
                $count = $this->countPrefixInTerm($prefix, $term_id, $taxonomy, $post_id);
                if ($count >= $rule['limite']) {
                    $term = get_term($term_id, $taxonomy);
                    $term_name = ($term && !is_wp_error($term)) ? $term->name : ('#' . $term_id);
                    return "Limite atteinte pour le prefixe {$prefix} dans {$this->taxonomyArticleLabel($taxonomy)} \"{$term_name}\" ({$rule['limite']} max, regle definie dans Parcours & Themes > Configuration).";
                }
            }
        }

        return null;
    }

    /**
     * Tente de rattacher un article "Complement" (ex: une Annexe) a l'article
     * "Principal" du meme terme de parcours qui le reference, via le champ
     * deja indexe articles_lies (texte libre, parfois bruite). Compare les
     * tokens PREFIXNNN extraits des entrees de articles_lies aux titres reels
     * des principaux candidats. Renvoie null si aucune correspondance fiable
     * (l'appelant bascule alors sur une section "Complements" generique).
     */
    public function resolveComplementPrincipal(int $complementPostId, array $principalPostIds): ?int
    {
        if (empty($principalPostIds)) return null;

        $indexed = $this->getByPostId($complementPostId);
        $liesRaw = json_decode($indexed['articles_lies'] ?? '[]', true);
        if (!is_array($liesRaw) || empty($liesRaw)) return null;

        $referencedTokens = [];
        foreach ($liesRaw as $entry) {
            if (preg_match('/^([A-Za-z]{2,5}\d{2,4})/u', trim((string) $entry), $m)) {
                $referencedTokens[] = strtoupper($m[1]);
            }
        }
        if (empty($referencedTokens)) return null;

        foreach ($principalPostIds as $pid) {
            $prow  = $this->getByPostId((int) $pid);
            $titre = $prow['titre'] ?? get_the_title((int) $pid);
            if (preg_match('/^([A-Za-z]{2,5}\d{2,4})/u', trim((string) $titre), $m)) {
                if (in_array(strtoupper($m[1]), $referencedTokens, true)) {
                    return (int) $pid;
                }
            }
        }
        return null;
    }

    public function getCounts(): array
    {
        global $wpdb;
        $total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE statut_indexation = 'valide'");
        $classes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE statut_indexation = 'valide' AND statut_classement = 'classe'");
        $suggestions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} i
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = i.post_id AND pm.meta_key = '_schilo_classement_suggestion'
             WHERE i.statut_indexation = 'valide' AND i.statut_classement != 'classe'"
        );
        return [
            'total'       => $total,
            'classes'     => $classes,
            'non_classes' => $total - $classes,
            'suggestions' => $suggestions,
        ];
    }

    /* =========================================================
       SUGGESTION IA STOCKEE (classement en lot, revue differee)
    ========================================================= */

    private const SUGGESTION_META_KEY = '_schilo_classement_suggestion';

    /**
     * Resout une suggestion IA (noms de termes) en term_id reels, en creant
     * les termes manquants (premier niveau) si aucun terme existant ne correspond.
     */
    public function resolveSuggestionTermIds(array $suggestion): array
    {
        $theme_ids = [];
        if (!empty($suggestion['theme'])) {
            $theme = $this->findOrCreateTerm('schilo_theme', (string) $suggestion['theme'], 0);
            if (!is_wp_error($theme)) {
                $theme_ids[] = (int) $theme['term_id'];
                if (!empty($suggestion['sous_theme'])) {
                    $sous_theme = $this->findOrCreateTerm('schilo_theme', (string) $suggestion['sous_theme'], (int) $theme['term_id']);
                    if (!is_wp_error($sous_theme)) $theme_ids[] = (int) $sous_theme['term_id'];
                }
            }
        }

        $parcours_ids = [];
        foreach ((array) ($suggestion['parcours'] ?? []) as $name) {
            if (!is_string($name) || trim($name) === '') continue;
            $term = $this->findOrCreateTerm('schilo_parcours', $name, 0);
            if (!is_wp_error($term)) $parcours_ids[] = (int) $term['term_id'];
        }

        $serie_ids = [];
        if (!empty($suggestion['serie'])) {
            $serie = $this->findOrCreateTerm('schilo_serie', (string) $suggestion['serie'], 0);
            if (!is_wp_error($serie)) $serie_ids[] = (int) $serie['term_id'];
        }

        $ordre  = absint($suggestion['ordre'] ?? 0);
        $ordres = [];
        foreach (array_merge($parcours_ids, $serie_ids) as $term_id) {
            $ordres[$term_id] = $ordre;
        }

        return [
            'theme_term_ids'    => $theme_ids,
            'parcours_term_ids' => $parcours_ids,
            'serie_term_ids'    => $serie_ids,
            'ordres'            => $ordres,
        ];
    }

    public function storeSuggestion(int $post_id, array $resolved): void
    {
        update_post_meta($post_id, self::SUGGESTION_META_KEY, wp_json_encode($resolved));
    }

    public function getSuggestion(int $post_id): ?array
    {
        $raw = get_post_meta($post_id, self::SUGGESTION_META_KEY, true);
        if (!$raw) return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function clearSuggestion(int $post_id): void
    {
        delete_post_meta($post_id, self::SUGGESTION_META_KEY);
    }

    /* =========================================================
       ENREGISTREMENT DU CLASSEMENT (validation humaine)
    ========================================================= */

    public function saveClassement(int $post_id, array $data, int $user_id): bool
    {
        global $wpdb;

        $theme_ids    = array_map('absint', (array) ($data['theme_term_ids'] ?? []));
        $parcours_ids = array_map('absint', (array) ($data['parcours_term_ids'] ?? []));
        $serie_ids    = array_map('absint', (array) ($data['serie_term_ids'] ?? []));
        $ordres       = (array) ($data['ordres'] ?? []); // [term_id => ordre]

        wp_set_object_terms($post_id, array_values(array_filter($theme_ids)), 'schilo_theme');
        wp_set_object_terms($post_id, array_values(array_filter($parcours_ids)), 'schilo_parcours');
        wp_set_object_terms($post_id, array_values(array_filter($serie_ids)), 'schilo_serie');

        foreach (array_merge($parcours_ids, $serie_ids) as $term_id) {
            if (!$term_id) continue;
            $ordre = isset($ordres[$term_id]) ? absint($ordres[$term_id]) : 0;
            $this->setPostOrderInTerm($post_id, $term_id, $ordre);
        }

        $existing = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE post_id = %d", $post_id)
        );
        if (!$existing) return false;

        $saved = (bool) $wpdb->update($this->table, [
            'statut_classement' => 'classe',
            'date_classement'   => current_time('mysql'),
            'classe_par'        => $user_id,
        ], ['post_id' => $post_id]);

        if ($saved) {
            $this->clearSuggestion($post_id);
        }

        return $saved;
    }

    /* =========================================================
       CONNEXION IA - classement sur liste fermee de termes
    ========================================================= */

    public function classifyArticleViaIA(int $post_id, string $provider): array|\WP_Error
    {
        $prompt = $this->buildClassementPrompt($post_id);
        $raw    = $this->callIaRaw($provider, $prompt, 1024);
        if (is_wp_error($raw)) return $raw;
        return $this->parseIaJson($raw);
    }

    /**
     * Appelle Claude ou OpenAI avec le prompt donne et renvoie le texte brut de la reponse.
     */
    private function callIaRaw(string $provider, string $prompt, int $max_tokens = 1024): string|\WP_Error
    {
        $config = get_option('schilo_ia_config', []);

        if ($provider === 'claude') {
            $key = $config['claude']['api_key'] ?? '';
            if (!$key) return new \WP_Error('no_key', "Cle API Claude manquante dans la configuration IA.");

            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 240,
                'headers' => [
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model'      => $config['claude']['model'] ?? 'claude-sonnet-4-6',
                    'max_tokens' => $max_tokens,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]),
            ]);
        } elseif ($provider === 'openai') {
            $key = $config['openai']['api_key'] ?? '';
            if (!$key) return new \WP_Error('no_key', "Cle API OpenAI manquante dans la configuration IA.");

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'timeout' => 240,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model'      => $config['openai']['model'] ?? 'gpt-4o',
                    'max_tokens' => $max_tokens,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]),
            ]);
        } else {
            return new \WP_Error('bad_provider', 'Provider inconnu : ' . $provider);
        }

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $body['error']['message'] ?? ('Erreur HTTP ' . $code);
            return new \WP_Error('api_error', $msg);
        }

        return ($provider === 'claude')
            ? ($body['content'][0]['text'] ?? '')
            : ($body['choices'][0]['message']['content'] ?? '');
    }

    private function parseIaJson(string $raw): array|\WP_Error
    {
        $raw_clean = trim($raw);
        if (str_starts_with($raw_clean, '```')) {
            $raw_clean = preg_replace('/^```(?:json)?\s*/i', '', $raw_clean);
            $raw_clean = rtrim($raw_clean, '` ');
        }

        $parsed = json_decode($raw_clean, true);
        if (!is_array($parsed)) {
            $looks_truncated = $raw_clean !== '' && !in_array(substr($raw_clean, -1), ['}', ']'], true);
            $hint = $looks_truncated
                ? " La reponse semble coupee avant la fin (limite de longueur atteinte) : reessayez, ou si cela persiste, reduisez le nombre de termes traites en une fois."
                : '';
            return new \WP_Error('parse_error', "La reponse IA n'est pas un JSON valide." . $hint);
        }

        return $parsed;
    }

    private function buildClassementPrompt(int $post_id): string
    {
        $indexed = $this->getByPostId($post_id);
        $post    = get_post($post_id);
        $titre   = $post ? $post->post_title : ($indexed['titre'] ?? '');

        $prefix      = $indexed['prefix'] ?? '';
        $prefix_rule = $prefix !== '' ? $this->getPrefixRule($prefix) : null;
        $role_note   = '';
        if ($prefix_rule) {
            $role_labels = ['principal' => 'Principal', 'complement' => 'Complement', 'exclu' => 'Exclu'];
            $role_label  = $role_labels[$prefix_rule['role']] ?? $prefix_rule['role'];
            $role_note   = "- Prefixe : {$prefix} — role editorial configure : {$role_label} (poids {$prefix_rule['poids']}/100)\n";
            if ($prefix_rule['role'] === 'complement') {
                $role_note .= "  Consigne : cet article est un COMPLEMENT (ex: une annexe qui apporte un supplement a "
                            . "un article principal), il ne doit pas etre traite comme une etape numerotee de "
                            . "premier plan d'un parcours. Mets \"ordre\": 0 pour lui.\n";
            } elseif ($prefix_rule['role'] === 'exclu') {
                $role_note .= "  Consigne : ce prefixe est EXCLU du classement en parcours. Retourne un tableau "
                            . "\"parcours\" vide.\n";
            }
        }

        $lister = function (string $taxonomy): string {
            $tree = $this->getTermsTree($taxonomy);
            $lines = [];
            foreach ($tree as $term) {
                $lines[] = '- ' . $term->name;
                foreach ($term->children as $child) {
                    $lines[] = '  - ' . $child->name;
                }
            }
            return $lines ? implode("\n", $lines) : '(aucun terme existant pour le moment)';
        };

        return "Tu dois classer un article biblique dans des listes FERMEES de termes deja existants.\n"
             . "N'invente un nouveau terme QUE si aucun terme existant ne convient vraiment.\n\n"
             . "Article a classer :\n"
             . "- Titre : {$titre}\n"
             . $role_note
             . "- Theme principal indexe (indice) : " . ($indexed['theme_principal'] ?? '') . "\n"
             . "- Sous-theme indexe (indice) : " . ($indexed['sous_theme'] ?? '') . "\n"
             . "- Parcours indexe (indice) : " . ($indexed['parcours'] ?? '') . "\n"
             . "- Serie indexee (indice) : " . ($indexed['serie'] ?? '') . "\n"
             . "- Ordre serie indexe (indice) : " . ($indexed['ordre_serie'] ?? 0) . "\n\n"
             . "Parcours existants (parent > etape) :\n" . $lister('schilo_parcours') . "\n\n"
             . "Themes existants (theme > sous-theme) :\n" . $lister('schilo_theme') . "\n\n"
             . "Series existantes :\n" . $lister('schilo_serie') . "\n\n"
             . "Retourne UNIQUEMENT un JSON avec exactement ces champs :\n"
             . "{\n"
             . "  \"theme\": \"nom exact d'un theme existant, ou nouveau nom si aucun ne convient, ou vide\",\n"
             . "  \"sous_theme\": \"nom exact d'un sous-theme existant sous ce theme, ou nouveau nom, ou vide\",\n"
             . "  \"parcours\": [\"nom exact d'un ou plusieurs parcours/etapes existants, ou nouveaux noms\"],\n"
             . "  \"serie\": \"nom exact d'une serie existante, ou nouveau nom, ou vide\",\n"
             . "  \"ordre\": 0\n"
             . "}\n"
             . "Retourne UNIQUEMENT le JSON, sans texte avant ou apres, sans backticks.";
    }

    /* =========================================================
       CURATION IA DU VOCABULAIRE CONTROLE (parcours/theme/serie)
       Analyse les valeurs indexees en texte libre et propose une
       hierarchie de termes propre. Ne modifie rien : la creation
       reelle passe par applyTermCuration(), apres validation humaine.
    ========================================================= */

    /**
     * Valeurs distinctes indexees (texte libre) avec leur nombre d'occurrences,
     * pour une colonne donnee de la table d'indexation.
     */
    private function getDistinctIndexedValues(string $column): array
    {
        global $wpdb;
        $allowed = ['theme_principal', 'sous_theme', 'parcours', 'serie'];
        if (!in_array($column, $allowed, true)) return [];

        $rows = $wpdb->get_results(
            "SELECT {$column} as val, COUNT(*) as n
             FROM {$this->table}
             WHERE {$column} != '' AND statut_indexation = 'valide'
             GROUP BY {$column}
             ORDER BY n DESC",
            ARRAY_A
        );

        $out = [];
        foreach ($rows as $row) {
            $out[$row['val']] = (int) $row['n'];
        }
        return $out;
    }

    private function formatIndexedValues(array $values): string
    {
        $lines = [];
        foreach ($values as $val => $n) {
            $lines[] = "- \"{$val}\" ({$n} article(s))";
        }
        return $lines ? implode("\n", $lines) : '(aucune valeur indexee)';
    }

    private function listExistingTerms(string $taxonomy): string
    {
        $tree  = $this->getTermsTree($taxonomy);
        $lines = [];
        foreach ($tree as $term) {
            $lines[] = '- ' . $term->name . ($term->description ? ' (description actuelle : "' . $term->description . '")' : ' (SANS DESCRIPTION)');
            foreach ($term->children as $child) {
                $lines[] = '  - ' . $child->name . ($child->description ? ' (description actuelle : "' . $child->description . '")' : ' (SANS DESCRIPTION)');
            }
        }
        return $lines ? implode("\n", $lines) : '(aucun terme existant pour le moment)';
    }

    private function curationIntro(): string
    {
        return "Tu es un bibliothecaire expert en analyse de contenu biblique. Ton objectif : construire un "
             . "vocabulaire controle propre (peu de termes, sans doublons ni quasi-doublons) a partir de "
             . "valeurs indexees en texte libre par une IA sur des articles individuels — ces valeurs sont "
             . "souvent tres proches les unes des autres (variantes de formulation d'un meme sujet).\n\n";
    }

    /**
     * Prompt de STRUCTURE seule (noms + hierarchie, sans description) pour
     * une taxonomie donnee. Reponse volontairement petite et rapide : les
     * descriptions (longueur configurable, voir getDescriptionWordRange())
     * sont demandees ensuite, par petits
     * lots sequentiels via buildTermDescriptionsPrompt() — les demander toutes
     * en un seul appel (60+ termes) saturait la reponse (JSON tronque) ou
     * depassait le temps d'attente d'un appel HTTP non-streame.
     */
    private function buildTermStructurePrompt(string $taxonomy): string
    {
        $intro = $this->curationIntro();
        $common = "Consignes :\n"
             . "- Fusionne les valeurs qui designent clairement la meme chose (ex: \"Vie de Jesus\" / \"Vies de Jesus\" / \"La vie de Jesus\").\n"
             . "- Les valeurs isolees (1 seule occurrence) tres specifiques peuvent etre ignorees si elles ne meritent pas un terme dedie.\n"
             . "- Conserve les termes existants listes ci-dessus s'ils restent pertinents (ne les recree pas, ils seront reutilises automatiquement si le nom correspond exactement).\n"
             . "- Ne fournis PAS de description ici, seulement les noms et la hierarchie : les descriptions seront demandees separement.\n\n";

        if ($taxonomy === 'schilo_theme') {
            return $intro
                 . "=== Valeurs de \"theme_principal\" deja indexees ===\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('theme_principal')) . "\n\n"
                 . "=== Valeurs de \"sous_theme\" deja indexees ===\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('sous_theme')) . "\n\n"
                 . "=== Themes deja crees (theme > sous-theme) ===\n" . $this->listExistingTerms('schilo_theme') . "\n\n"
                 . $common
                 . "- Les themes peuvent avoir des sous-themes enfants.\n\n"
                 . "Retourne UNIQUEMENT un JSON avec cette structure exacte (aucun texte avant/apres, sans backticks) :\n"
                 . "{ \"schilo_theme\": [ { \"name\": \"Nom du theme\", \"children\": [ { \"name\": \"Sous-theme\" } ] } ] }";
        }

        if ($taxonomy === 'schilo_parcours') {
            return $intro
                 . "=== Valeurs de \"parcours\" deja indexees (ignore \"non defini\"/\"non applicable\") ===\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('parcours')) . "\n\n"
                 . "=== Parcours deja crees (parcours > etape) ===\n" . $this->listExistingTerms('schilo_parcours') . "\n\n"
                 . $common
                 . "- Les parcours et etapes representent un ordre de lecture (un parcours peut avoir des etapes enfants).\n\n"
                 . "Retourne UNIQUEMENT un JSON avec cette structure exacte (aucun texte avant/apres, sans backticks) :\n"
                 . "{ \"schilo_parcours\": [ { \"name\": \"Nom du parcours\", \"children\": [ { \"name\": \"Etape\" } ] } ] }";
        }

        // schilo_serie
        return $intro
             . "=== Valeurs de \"serie\" deja indexees (ignore \"non defini\"/\"non applicable\") ===\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('serie')) . "\n\n"
             . "=== Series deja creees ===\n" . $this->listExistingTerms('schilo_serie') . "\n\n"
             . $common
             . "- Les series sont a plat (pas de hierarchie, pas de \"children\").\n\n"
             . "Retourne UNIQUEMENT un JSON avec cette structure exacte (aucun texte avant/apres, sans backticks) :\n"
             . "{ \"schilo_serie\": [ { \"name\": \"Nom de serie\" } ] }";
    }

    /**
     * Un appel IA rapide par taxonomie (noms + hierarchie seulement).
     */
    /**
     * Map nom => {description, generated_at} pour tous les termes existants
     * d'une taxonomie (parents + enfants), utilisee pour enrichir la
     * structure proposee par l'IA (qui ne connait que les noms) avec l'etat
     * deja en base — permet au JS de sauter les termes deja generes.
     */
    private function buildExistingTermMap(string $taxonomy): array
    {
        $map = [];
        foreach ($this->getTermsTree($taxonomy) as $term) {
            $map[$term->name] = [
                'description'  => $term->description,
                'generated_at' => $this->getDescriptionGeneratedAt((int) $term->term_id),
            ];
            foreach ($term->children as $child) {
                $map[$child->name] = [
                    'description'  => $child->description,
                    'generated_at' => $this->getDescriptionGeneratedAt((int) $child->term_id),
                ];
            }
        }
        return $map;
    }

    public function proposeTermStructure(string $provider): array|\WP_Error
    {
        $result = [];
        foreach (self::TAXONOMIES as $taxonomy) {
            $prompt = $this->buildTermStructurePrompt($taxonomy);
            $raw    = $this->callIaRaw($provider, $prompt, 3000);
            if (is_wp_error($raw)) return $raw;

            $parsed = $this->parseIaJson($raw);
            if (is_wp_error($parsed)) return $parsed;

            $existing = $this->buildExistingTermMap($taxonomy);
            $items    = (array) ($parsed[$taxonomy] ?? []);
            foreach ($items as &$item) {
                $name = trim((string) ($item['name'] ?? ''));
                $item['description']  = $existing[$name]['description'] ?? '';
                $item['generated_at'] = $existing[$name]['generated_at'] ?? '';
                if (!empty($item['children']) && is_array($item['children'])) {
                    foreach ($item['children'] as &$child) {
                        $cname = trim((string) ($child['name'] ?? ''));
                        $child['description']  = $existing[$cname]['description'] ?? '';
                        $child['generated_at'] = $existing[$cname]['generated_at'] ?? '';
                    }
                    unset($child);
                }
            }
            unset($item);

            $result[$taxonomy] = $items;
        }
        return $result;
    }

    /**
     * Prompt de DESCRIPTION pour un petit lot de termes deja nommes (voir
     * proposeTermStructure). Garder les lots petits (5-6 noms) est ce qui
     * rend chaque appel rapide et fiable (pas de reponse saturee ni
     * d'attente trop longue sur un appel HTTP non-streame).
     */
    private function buildTermDescriptionsPrompt(string $taxonomy, array $names): string
    {
        $taxContext = [
            'schilo_theme'    => "=== Valeurs de \"theme_principal\"/\"sous_theme\" indexees ===\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('theme_principal')) . "\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('sous_theme')) . "\n\n",
            'schilo_parcours' => "=== Valeurs de \"parcours\" indexees ===\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('parcours')) . "\n\n",
            'schilo_serie'    => "=== Valeurs de \"serie\" indexees ===\n" . $this->formatIndexedValues($this->getDistinctIndexedValues('serie')) . "\n\n",
        ];

        $namesList  = implode("\n", array_map(fn($n) => '- "' . $n . '"', $names));
        $words      = $this->getDescriptionWordRange();
        $paragraphs = $this->getDescriptionParagraphRange();

        return $this->curationIntro()
             . ($taxContext[$taxonomy] ?? '')
             . "Pour CHACUN des termes suivants, redige une description developpee (entre {$words['min']} et {$words['max']} mots, "
             . "destinee a etre affichee publiquement en haut de la page de ce terme). Donne au lecteur une vraie "
             . "mise en contexte : de quoi parle ce theme/parcours/etape/serie, quels evenements ou enseignements "
             . "bibliques il couvre, pourquoi il est interessant a lire, en t'appuyant sur les valeurs indexees "
             . "ci-dessus.\n\n"
             . "Format IMPORTANT : structure le texte en {$paragraphs['min']} a {$paragraphs['max']} paragraphes "
             . "COURTS (quelques phrases chacun, une idee par paragraphe) plutot qu'un seul bloc compact. Separe "
             . "chaque paragraphe par une ligne vide (\\n\\n) dans la valeur JSON — pas de markdown, pas de listes "
             . "a puces, juste du texte brut avec des paragraphes.\n\n"
             . "Termes a decrire :\n" . $namesList . "\n\n"
             . "Retourne UNIQUEMENT un JSON avec cette structure exacte (aucun texte avant/apres, sans backticks), "
             . "une entree par terme demande, avec le nom exact en cle :\n"
             . "{ \"descriptions\": { \"Nom exact du terme\": \"...\" } }";
    }

    /**
     * Decrit un petit lot de termes (voir buildTermDescriptionsPrompt).
     * Renvoie une map RE-CLEE sur les noms exacts demandes (pas les cles
     * brutes de l'IA) : le modele reformule parfois legerement une cle,
     * notamment sur des noms contenant des caracteres inhabituels comme
     * ">" — sans cette normalisation, la description existe mais reste
     * introuvable par une recherche exacte cote consommateur.
     */
    public function proposeTermDescriptions(string $provider, string $taxonomy, array $names): array|\WP_Error
    {
        if (empty($names)) return [];

        $prompt = $this->buildTermDescriptionsPrompt($taxonomy, $names);
        // max_tokens proportionnel au nombre de termes du lot x la longueur
        // maximale configuree (~2 tokens/mot en francais + marge JSON), pour
        // ne pas retomber dans la reponse tronquee corrigee precedemment si
        // la longueur configuree est augmentee.
        $words      = $this->getDescriptionWordRange();
        $max_tokens = max(4000, count($names) * $words['max'] * 2 + 500);
        $raw        = $this->callIaRaw($provider, $prompt, $max_tokens);
        if (is_wp_error($raw)) return $raw;

        $parsed = $this->parseIaJson($raw);
        if (is_wp_error($parsed)) return $parsed;

        $raw_descriptions = is_array($parsed['descriptions'] ?? null) ? $parsed['descriptions'] : [];

        $result = [];
        foreach ($names as $name) {
            $desc = $this->matchDescriptionByName($raw_descriptions, $name);
            if ($desc !== '') {
                $result[$name] = $desc;
            }
        }
        // Un seul terme demande et une seule description revenue, mais sous
        // une cle qui ne correspond ni exactement ni a la casse/espaces pres :
        // on la prend quand meme (cas observe avec des noms contenant ">").
        if (empty($result) && count($names) === 1 && count($raw_descriptions) === 1) {
            $result[$names[0]] = trim((string) reset($raw_descriptions));
        }
        return $result;
    }

    /**
     * Correspondance exacte puis insensible a la casse/aux espaces entre un
     * nom de terme demande et les cles renvoyees par l'IA.
     */
    private function matchDescriptionByName(array $descriptions, string $name): string
    {
        if (isset($descriptions[$name])) {
            return trim((string) $descriptions[$name]);
        }
        $needle = mb_strtolower(trim($name));
        foreach ($descriptions as $key => $value) {
            if (mb_strtolower(trim((string) $key)) === $needle) {
                return trim((string) $value);
            }
        }
        return '';
    }

    /**
     * Applique une suggestion de curation (structure + descriptions fusionnees
     * cote JS, potentiellement editee par l'humain) : cree les termes manquants
     * via findOrCreateTerm (les termes existants sont reutilises, jamais
     * renommes ni supprimes), assigne un ordre croissant.
     */
    /**
     * Extrait {name, description} d'un element de suggestion, qu'il soit un
     * simple nom (string, ancien format) ou un objet {name, description}.
     */
    private function extractNameDescription($item): array
    {
        if (is_array($item)) {
            return [trim((string) ($item['name'] ?? '')), trim((string) ($item['description'] ?? ''))];
        }
        return [trim((string) $item), ''];
    }

    public function applyTermCuration(array $suggestion): array
    {
        $summary = ['schilo_theme' => 0, 'schilo_parcours' => 0, 'schilo_serie' => 0, 'errors' => []];

        foreach (['schilo_theme', 'schilo_parcours'] as $taxonomy) {
            $order = 1;
            foreach ((array) ($suggestion[$taxonomy] ?? []) as $item) {
                [$name, $description] = $this->extractNameDescription($item);
                if ($name === '') continue;

                $parent = $this->findOrCreateTerm($taxonomy, $name, 0, $description);
                if (is_wp_error($parent)) {
                    $summary['errors'][] = "{$taxonomy} / {$name} : " . $parent->get_error_message();
                    continue;
                }
                $this->updateTermOrder((int) $parent['term_id'], $order++);
                if ($description !== '') $this->markDescriptionGenerated((int) $parent['term_id']);
                $summary[$taxonomy]++;

                $childOrder = 1;
                $children = is_array($item) ? (array) ($item['children'] ?? []) : [];
                foreach ($children as $childItem) {
                    [$childName, $childDescription] = $this->extractNameDescription($childItem);
                    if ($childName === '') continue;
                    $child = $this->findOrCreateTerm($taxonomy, $childName, (int) $parent['term_id'], $childDescription);
                    if (is_wp_error($child)) {
                        $summary['errors'][] = "{$taxonomy} / {$name} > {$childName} : " . $child->get_error_message();
                        continue;
                    }
                    $this->updateTermOrder((int) $child['term_id'], $childOrder++);
                    if ($childDescription !== '') $this->markDescriptionGenerated((int) $child['term_id']);
                    $summary[$taxonomy]++;
                }
            }
        }

        $order = 1;
        foreach ((array) ($suggestion['schilo_serie'] ?? []) as $item) {
            [$name, $description] = $this->extractNameDescription($item);
            if ($name === '') continue;
            $term = $this->findOrCreateTerm('schilo_serie', $name, 0, $description);
            if (is_wp_error($term)) {
                $summary['errors'][] = "schilo_serie / {$name} : " . $term->get_error_message();
                continue;
            }
            $this->updateTermOrder((int) $term['term_id'], $order++);
            if ($description !== '') $this->markDescriptionGenerated((int) $term['term_id']);
            $summary['schilo_serie']++;
        }

        return $summary;
    }

    /**
     * Genere (ou regenere) la description d'UN seul terme deja existant, via
     * le bouton individuel de la vue Termes — evite de relancer tout le
     * cycle structure+lots pour ajuster un seul terme.
     */
    public function generateSingleTermDescription(string $provider, string $taxonomy, int $term_id): array|\WP_Error
    {
        if (!$this->isValidTaxonomy($taxonomy)) {
            return new \WP_Error('bad_taxonomy', 'Taxonomie invalide.');
        }

        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return new \WP_Error('not_found', 'Terme introuvable.');
        }

        $descriptions = $this->proposeTermDescriptions($provider, $taxonomy, [$term->name]);
        if (is_wp_error($descriptions)) return $descriptions;

        $description = trim((string) ($descriptions[$term->name] ?? ''));
        if ($description === '') {
            return new \WP_Error('empty', "L'IA n'a pas renvoye de description pour ce terme.");
        }

        $this->updateTermDescription($term_id, $taxonomy, $description);
        $this->markDescriptionGenerated($term_id);

        return [
            'description'  => $description,
            'generated_at' => $this->getDescriptionGeneratedAt($term_id),
        ];
    }
}
