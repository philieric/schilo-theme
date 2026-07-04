<?php

namespace Schilo\Builder\Service;

class IndexationService
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'schilo_indexation';
    }

    /* =========================================================
       INSTALLATION DE LA TABLE
    ========================================================= */

    public function maybeCreateTable(): void
    {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") === $this->table) {
            return;
        }

        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id             INT UNSIGNED NOT NULL,
            titre               VARCHAR(500)  NOT NULL DEFAULT '',
            slug                VARCHAR(255)  NOT NULL DEFAULT '',
            url                 VARCHAR(1000) NOT NULL DEFAULT '',
            prefix              VARCHAR(20)   NOT NULL DEFAULT '',
            type_article        VARCHAR(100)  NOT NULL DEFAULT '',
            statut_wp           VARCHAR(50)   NOT NULL DEFAULT 'publish',
            langue              VARCHAR(10)   NOT NULL DEFAULT 'fr',
            date_article        DATETIME      DEFAULT NULL,
            date_modification   DATETIME      DEFAULT NULL,
            auteur              VARCHAR(200)  NOT NULL DEFAULT '',
            theme_principal     VARCHAR(255)  NOT NULL DEFAULT '',
            sous_theme          VARCHAR(255)  NOT NULL DEFAULT '',
            parcours            VARCHAR(255)  NOT NULL DEFAULT '',
            serie               VARCHAR(255)  NOT NULL DEFAULT '',
            ordre_serie         INT           NOT NULL DEFAULT 0,
            categories          JSON          DEFAULT NULL,
            tags_wp             JSON          DEFAULT NULL,
            resume              TEXT          DEFAULT NULL,
            resume_court        VARCHAR(500)  NOT NULL DEFAULT '',
            mots_cles           JSON          DEFAULT NULL,
            concepts            JSON          DEFAULT NULL,
            personnages         JSON          DEFAULT NULL,
            lieux               JSON          DEFAULT NULL,
            periodes            JSON          DEFAULT NULL,
            references_bibliques JSON         DEFAULT NULL,
            citations_cles      JSON          DEFAULT NULL,
            public_cible        VARCHAR(100)  NOT NULL DEFAULT '',
            niveau_lecture      VARCHAR(50)   NOT NULL DEFAULT '',
            temps_lecture_min   INT           NOT NULL DEFAULT 0,
            nb_mots             INT           NOT NULL DEFAULT 0,
            nb_sections         INT           NOT NULL DEFAULT 0,
            seo_titre           VARCHAR(70)   NOT NULL DEFAULT '',
            seo_description     VARCHAR(160)  NOT NULL DEFAULT '',
            seo_mots_cles       JSON          DEFAULT NULL,
            og_titre            VARCHAR(200)  NOT NULL DEFAULT '',
            og_description      VARCHAR(300)  NOT NULL DEFAULT '',
            og_image_url        VARCHAR(1000) NOT NULL DEFAULT '',
            schema_type         VARCHAR(100)  NOT NULL DEFAULT 'Article',
            schema_json         JSON          DEFAULT NULL,
            canonical_url       VARCHAR(1000) NOT NULL DEFAULT '',
            robots              VARCHAR(100)  NOT NULL DEFAULT 'index,follow',
            articles_lies       JSON          DEFAULT NULL,
            articles_prerequis  JSON          DEFAULT NULL,
            articles_suite      JSON          DEFAULT NULL,
            sources_externes    JSON          DEFAULT NULL,
            champs_custom       JSON          DEFAULT NULL,
            statut_indexation   ENUM('brouillon','en_attente','valide','rejete') NOT NULL DEFAULT 'brouillon',
            source_indexation   ENUM('manuel','claude','openai','xml_import')    NOT NULL DEFAULT 'manuel',
            donnees_ia_brutes   LONGTEXT      DEFAULT NULL,
            notes_validation    TEXT          DEFAULT NULL,
            indexe_par          INT UNSIGNED  DEFAULT NULL,
            valide_par          INT UNSIGNED  DEFAULT NULL,
            date_indexation     DATETIME      DEFAULT NULL,
            date_validation     DATETIME      DEFAULT NULL,
            version             INT           NOT NULL DEFAULT 1,
            UNIQUE KEY idx_post_id (post_id),
            KEY idx_statut   (statut_indexation),
            KEY idx_theme    (theme_principal(100)),
            KEY idx_prefix   (prefix),
            KEY idx_date_val (date_validation)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* =========================================================
       LECTURE
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

    public function getList(int $per_page = 20, int $paged = 1, string $statut = ''): array
    {
        global $wpdb;
        $offset = ($paged - 1) * $per_page;

        $where = $statut ? $wpdb->prepare(" AND i.statut_indexation = %s", $statut) : '';

        $rows = $wpdb->get_results(
            "SELECT i.*, p.post_title
             FROM {$this->table} i
             JOIN {$wpdb->posts} p ON p.ID = i.post_id
             WHERE 1=1 {$where}
             ORDER BY i.date_indexation DESC
             LIMIT {$per_page} OFFSET {$offset}",
            ARRAY_A
        );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} i WHERE 1=1 {$where}"
        );

        return ['rows' => $rows ?: [], 'total' => $total];
    }

    public function getUnindexedPosts(int $limit = 50): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_status
             FROM {$wpdb->posts} p
             LEFT JOIN {$this->table} i ON i.post_id = p.ID
             WHERE p.post_type = 'post'
               AND p.post_status IN ('publish','draft')
               AND i.id IS NULL
             ORDER BY p.post_title ASC
             LIMIT {$limit}",
            ARRAY_A
        ) ?: [];
    }

    /* =========================================================
       ECRITURE / VALIDATION
    ========================================================= */

    private const JSON_FIELDS = [
        'categories', 'tags_wp', 'mots_cles', 'concepts', 'personnages',
        'lieux', 'periodes', 'references_bibliques', 'citations_cles',
        'seo_mots_cles', 'schema_json', 'articles_lies', 'articles_prerequis',
        'articles_suite', 'sources_externes', 'champs_custom',
    ];

    // Toutes les colonnes connues de la table — les champs hors de cette liste vont dans champs_custom
    private const DB_COLUMNS = [
        'titre', 'slug', 'url', 'prefix', 'type_article', 'statut_wp', 'langue',
        'date_article', 'date_modification', 'auteur',
        'theme_principal', 'sous_theme', 'parcours', 'serie', 'ordre_serie',
        'categories', 'tags_wp',
        'resume', 'resume_court', 'mots_cles', 'concepts', 'personnages', 'lieux',
        'periodes', 'references_bibliques', 'citations_cles', 'public_cible',
        'niveau_lecture', 'temps_lecture_min', 'nb_mots', 'nb_sections',
        'seo_titre', 'seo_description', 'seo_mots_cles', 'og_titre', 'og_description',
        'og_image_url', 'schema_type', 'schema_json', 'canonical_url', 'robots',
        'articles_lies', 'articles_prerequis', 'articles_suite', 'sources_externes',
        'champs_custom',
        'statut_indexation', 'source_indexation', 'donnees_ia_brutes', 'notes_validation',
        'indexe_par', 'valide_par', 'date_indexation', 'date_validation', 'version',
    ];

    public function saveValidated(int $post_id, array $data, int $user_id, string $statut = 'valide'): bool
    {
        global $wpdb;

        $row          = [];
        $extra_fields = [];

        foreach ($data as $k => $v) {
            if ($k === 'post_id') continue;

            if (!in_array($k, self::DB_COLUMNS, true)) {
                // Champ inconnu (ajouté par l'IA) → champs_custom
                $extra_fields[$k] = is_array($v) ? $v : sanitize_text_field((string) $v);
                continue;
            }

            if (in_array($k, self::JSON_FIELDS, true)) {
                $row[$k] = is_array($v) ? wp_json_encode($v) : $v;
            } else {
                $row[$k] = sanitize_text_field((string) $v);
            }
        }

        // Fusionner les champs inconnus dans champs_custom
        if (!empty($extra_fields)) {
            $existing_custom = [];
            if (!empty($row['champs_custom'])) {
                $decoded = json_decode($row['champs_custom'], true);
                if (is_array($decoded)) $existing_custom = $decoded;
            }
            $row['champs_custom'] = wp_json_encode(array_merge($existing_custom, $extra_fields));
        }

        // Champs identite auto si absents
        $post = get_post($post_id);
        if ($post) {
            $row['post_id']           = $post_id;
            $row['titre']             = ($row['titre']    ?? '') ?: $post->post_title;
            $row['slug']              = ($row['slug']     ?? '') ?: $post->post_name;
            $row['url']               = ($row['url']      ?? '') ?: get_permalink($post_id);
            $row['statut_wp']         = $post->post_status;
            $row['date_modification'] = $post->post_modified;
        }

        // Troncature de securite pour les champs avec limite stricte
        $truncate = ['seo_titre' => 70, 'seo_description' => 160, 'og_titre' => 200, 'slug' => 255];
        foreach ($truncate as $field => $max) {
            if (isset($row[$field]) && mb_strlen($row[$field]) > $max) {
                $row[$field] = mb_substr($row[$field], 0, $max);
            }
        }

        $row['statut_indexation'] = $statut;
        if ($statut === 'valide') {
            $row['valide_par']      = $user_id;
            $row['date_validation'] = current_time('mysql');
        }

        $existing = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE post_id = %d", $post_id)
        );

        if ($existing) {
            $current_version = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT version FROM {$this->table} WHERE post_id = %d", $post_id)
            );
            $row['version'] = $current_version + 1;
            return (bool) $wpdb->update($this->table, $row, ['post_id' => $post_id]);
        }

        $row['date_indexation'] = current_time('mysql');
        $row['indexe_par']      = $user_id;
        $row['version']         = 1;
        return (bool) $wpdb->insert($this->table, $row);
    }

    public function updateStatus(int $post_id, string $statut, string $notes = ''): bool
    {
        global $wpdb;
        $allowed = ['brouillon', 'en_attente', 'valide', 'rejete'];
        if (!in_array($statut, $allowed, true)) return false;

        $data = ['statut_indexation' => $statut];
        if ($notes) $data['notes_validation'] = $notes;

        return (bool) $wpdb->update($this->table, $data, ['post_id' => $post_id]);
    }

    public function storeRawResponse(int $post_id, string $raw, string $provider): void
    {
        global $wpdb;
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE post_id = %d", $post_id)
        );
        if ($existing) {
            $wpdb->update($this->table, [
                'donnees_ia_brutes' => $raw,
                'source_indexation' => $provider,
                'statut_indexation' => 'en_attente',
                'date_indexation'   => current_time('mysql'),
            ], ['post_id' => $post_id]);
        } else {
            $post = get_post($post_id);
            $wpdb->insert($this->table, [
                'post_id'           => $post_id,
                'titre'             => $post ? $post->post_title : '',
                'slug'              => $post ? $post->post_name  : '',
                'url'               => get_permalink($post_id) ?: '',
                'statut_wp'         => $post ? $post->post_status : '',
                'donnees_ia_brutes' => $raw,
                'source_indexation' => $provider,
                'statut_indexation' => 'en_attente',
                'date_indexation'   => current_time('mysql'),
                'indexe_par'        => get_current_user_id(),
                'version'           => 1,
            ]);
        }
    }

    /* =========================================================
       CONNEXION IA
    ========================================================= */

    public function indexArticleViaIA(int $post_id, string $provider): array|\WP_Error
    {
        $config = get_option('schilo_ia_config', []);
        $prompt = $this->buildPrompt($post_id);

        if ($provider === 'claude') {
            $key = $config['claude']['api_key'] ?? '';
            if (!$key) return new \WP_Error('no_key', 'Clé API Claude manquante dans la configuration IA.');

            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 90,
                'headers' => [
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model'      => $config['claude']['model'] ?? 'claude-sonnet-4-6',
                    'max_tokens' => 4096,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]),
            ]);

        } elseif ($provider === 'openai') {
            $key = $config['openai']['api_key'] ?? '';
            if (!$key) return new \WP_Error('no_key', 'Clé API OpenAI manquante dans la configuration IA.');

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'timeout' => 90,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model'      => $config['openai']['model'] ?? 'gpt-4o',
                    'max_tokens' => 4096,
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
            $msg = ($provider === 'claude')
                ? ($body['error']['message'] ?? 'Erreur HTTP ' . $code)
                : ($body['error']['message'] ?? 'Erreur HTTP ' . $code);
            return new \WP_Error('api_error', $msg);
        }

        $raw = ($provider === 'claude')
            ? ($body['content'][0]['text'] ?? '')
            : ($body['choices'][0]['message']['content'] ?? '');

        // Stocker la reponse brute avant parsing
        $this->storeRawResponse($post_id, $raw, $provider);

        // Nettoyer le JSON (l'IA peut inclure des backticks)
        $raw_clean = trim($raw);
        if (str_starts_with($raw_clean, '```')) {
            $raw_clean = preg_replace('/^```(?:json)?\s*/i', '', $raw_clean);
            $raw_clean = rtrim($raw_clean, '` ');
        }

        $parsed = json_decode($raw_clean, true);
        if (!is_array($parsed)) {
            return new \WP_Error('parse_error', 'La reponse IA n\'est pas un JSON valide.');
        }

        // Le prefixe n'est pas demande a l'IA : deduit du titre de l'article (fait, pas jugement)
        if (empty($parsed['prefix'])) {
            $parsed['prefix'] = $this->derivePrefixFromTitle($post_id);
        }

        return $parsed;
    }

    /**
     * Deduit le prefixe de reference (ex: "PER") depuis le titre de l'article
     * (ex: "PER013 - La naissance...") sans dependre de l'IA ni de la meta
     * _schilo_prefix, qui n'est pas renseignee sur ce site.
     */
    public function derivePrefixFromTitle(int $post_id): string
    {
        $title = get_the_title($post_id);
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        if (preg_match('/^([A-Z]{2,5})\d{2,4}/u', $title, $m)) {
            return $m[1];
        }
        return '';
    }

    private function buildPrompt(int $post_id): string
    {
        $post    = get_post($post_id);
        $titre   = $post ? $post->post_title : '';
        $content = $post ? wp_strip_all_tags($post->post_content) : '';
        $prefix  = get_post_meta($post_id, '_schilo_prefix', true) ?: '';

        $cats = get_the_category($post_id);
        $cat_names = array_map(fn($c) => $c->name, $cats);

        return "Tu es un expert en analyse de contenu biblique et theologique.\n"
             . "Analyse cet article WordPress et retourne un JSON valide avec exactement ces champs :\n\n"
             . "{\n"
             . "  \"resume\": \"texte 500-800 mots\",\n"
             . "  \"resume_court\": \"texte max 150 mots\",\n"
             . "  \"mots_cles\": [\"mot1\", \"mot2\"],\n"
             . "  \"concepts\": [\"concept1\"],\n"
             . "  \"personnages\": [\"nom1\"],\n"
             . "  \"lieux\": [\"lieu1\"],\n"
             . "  \"periodes\": [\"periode1\"],\n"
             . "  \"references_bibliques\": [\"Jn 3,16\"],\n"
             . "  \"citations_cles\": [\"citation importante\"],\n"
             . "  \"public_cible\": \"Debutant|Intermediaire|Expert\",\n"
             . "  \"niveau_lecture\": \"Simple|Moyen|Avance\",\n"
             . "  \"theme_principal\": \"ex: Evangiles\",\n"
             . "  \"sous_theme\": \"ex: Ministere de Jesus\",\n"
             . "  \"parcours\": \"nom du parcours si applicable\",\n"
             . "  \"serie\": \"nom de serie si applicable\",\n"
             . "  \"ordre_serie\": 0,\n"
             . "  \"seo_titre\": \"max 70 caracteres\",\n"
             . "  \"seo_description\": \"max 160 caracteres\",\n"
             . "  \"seo_mots_cles\": [\"kw1\", \"kw2\"],\n"
             . "  \"og_titre\": \"titre reseaux sociaux\",\n"
             . "  \"og_description\": \"description reseaux\",\n"
             . "  \"schema_type\": \"Article\",\n"
             . "  \"robots\": \"index,follow\",\n"
             . "  \"articles_lies\": [],\n"
             . "  \"articles_prerequis\": [],\n"
             . "  \"articles_suite\": [],\n"
             . "  \"sources_externes\": []\n"
             . "}\n\n"
             . "Informations sur l'article :\n"
             . "- Titre : " . $titre . "\n"
             . "- Prefix Schilo : " . ($prefix ?: 'non defini') . "\n"
             . "- Categories WP : " . implode(', ', $cat_names) . "\n"
             . "- Contenu (8000 premiers caracteres) :\n"
             . mb_substr($content, 0, 8000) . "\n\n"
             . "Contraintes STRICTES :\n"
             . "- resume : entre 500 et 800 mots\n"
             . "- resume_court : maximum 150 mots\n"
             . "- seo_titre : maximum 70 caracteres\n"
             . "- seo_description : maximum 160 caracteres\n"
             . "- Tous les tableaux doivent etre des tableaux JSON meme si vides\n"
             . "Retourne UNIQUEMENT le JSON, sans aucun texte avant ou apres, sans backticks.";
    }

    /* =========================================================
       EXPORT XML
    ========================================================= */

    public function generateXmlTemplate(int $post_id): string
    {
        $post     = get_post($post_id);
        $existing = $this->getByPostId($post_id);
        $prefix   = get_post_meta($post_id, '_schilo_prefix', true) ?: '';
        $date     = date('Y-m-d H:i:s');

        $titre = htmlspecialchars($existing['titre'] ?? ($post ? $post->post_title : ''), ENT_XML1);
        $slug  = htmlspecialchars($existing['slug']  ?? ($post ? $post->post_name  : ''), ENT_XML1);
        $url   = htmlspecialchars($existing['url']   ?? get_permalink($post_id), ENT_XML1);
        $pfx   = htmlspecialchars($prefix, ENT_XML1);
        $type  = htmlspecialchars($existing['type_article'] ?? '', ENT_XML1);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<schilo_indexation post_id=\"{$post_id}\" version=\"1\" generated=\"{$date}\">\n";
        $xml .= "  <identite>\n";
        $xml .= "    <titre><![CDATA[{$titre}]]></titre>\n";
        $xml .= "    <slug>{$slug}</slug>\n";
        $xml .= "    <url>{$url}</url>\n";
        $xml .= "    <prefix>{$pfx}</prefix>\n";
        $xml .= "    <type_article>{$type}</type_article>\n";
        $xml .= "    <langue>fr</langue>\n";
        $xml .= "  </identite>\n";
        $xml .= "  <classification>\n";
        $xml .= "    <theme_principal><![CDATA[" . ($existing['theme_principal'] ?? '') . "]]></theme_principal>\n";
        $xml .= "    <sous_theme><![CDATA[" . ($existing['sous_theme'] ?? '') . "]]></sous_theme>\n";
        $xml .= "    <parcours><![CDATA[" . ($existing['parcours'] ?? '') . "]]></parcours>\n";
        $xml .= "    <serie><![CDATA[" . ($existing['serie'] ?? '') . "]]></serie>\n";
        $xml .= "    <ordre_serie>" . ($existing['ordre_serie'] ?? 0) . "</ordre_serie>\n";
        $xml .= "  </classification>\n";
        $xml .= "  <contenu>\n";
        $xml .= "    <resume><![CDATA[" . ($existing['resume'] ?? 'REMPLIR : resume 500-800 mots') . "]]></resume>\n";
        $xml .= "    <resume_court><![CDATA[" . ($existing['resume_court'] ?? 'REMPLIR : resume court max 150 mots') . "]]></resume_court>\n";
        $xml .= "    <mots_cles>" . $this->arrayToXmlItems($existing['mots_cles'] ?? '[]') . "</mots_cles>\n";
        $xml .= "    <concepts>" . $this->arrayToXmlItems($existing['concepts'] ?? '[]') . "</concepts>\n";
        $xml .= "    <personnages>" . $this->arrayToXmlItems($existing['personnages'] ?? '[]') . "</personnages>\n";
        $xml .= "    <lieux>" . $this->arrayToXmlItems($existing['lieux'] ?? '[]') . "</lieux>\n";
        $xml .= "    <periodes>" . $this->arrayToXmlItems($existing['periodes'] ?? '[]') . "</periodes>\n";
        $xml .= "    <references_bibliques>" . $this->arrayToXmlItems($existing['references_bibliques'] ?? '[]') . "</references_bibliques>\n";
        $xml .= "    <citations_cles>" . $this->arrayToXmlItems($existing['citations_cles'] ?? '[]') . "</citations_cles>\n";
        $xml .= "    <public_cible>" . ($existing['public_cible'] ?? 'Intermediaire') . "</public_cible>\n";
        $xml .= "    <niveau_lecture>" . ($existing['niveau_lecture'] ?? 'Moyen') . "</niveau_lecture>\n";
        $xml .= "  </contenu>\n";
        $xml .= "  <seo>\n";
        $xml .= "    <seo_titre><![CDATA[" . ($existing['seo_titre'] ?? 'REMPLIR max 70 cars') . "]]></seo_titre>\n";
        $xml .= "    <seo_description><![CDATA[" . ($existing['seo_description'] ?? 'REMPLIR max 160 cars') . "]]></seo_description>\n";
        $xml .= "    <seo_mots_cles>" . $this->arrayToXmlItems($existing['seo_mots_cles'] ?? '[]') . "</seo_mots_cles>\n";
        $xml .= "    <og_titre><![CDATA[" . ($existing['og_titre'] ?? '') . "]]></og_titre>\n";
        $xml .= "    <og_description><![CDATA[" . ($existing['og_description'] ?? '') . "]]></og_description>\n";
        $xml .= "    <schema_type>" . ($existing['schema_type'] ?? 'Article') . "</schema_type>\n";
        $xml .= "    <robots>" . ($existing['robots'] ?? 'index,follow') . "</robots>\n";
        $xml .= "  </seo>\n";
        $xml .= "  <relations>\n";
        $xml .= "    <articles_lies>" . $this->arrayToXmlItems($existing['articles_lies'] ?? '[]') . "</articles_lies>\n";
        $xml .= "    <articles_prerequis>" . $this->arrayToXmlItems($existing['articles_prerequis'] ?? '[]') . "</articles_prerequis>\n";
        $xml .= "    <articles_suite>" . $this->arrayToXmlItems($existing['articles_suite'] ?? '[]') . "</articles_suite>\n";
        $xml .= "    <sources_externes>" . $this->arrayToXmlItems($existing['sources_externes'] ?? '[]') . "</sources_externes>\n";
        $xml .= "  </relations>\n";
        $xml .= "</schilo_indexation>";

        return $xml;
    }

    private function arrayToXmlItems(string $json): string
    {
        $arr = json_decode($json, true);
        if (!is_array($arr) || empty($arr)) return '';
        $out = '';
        foreach ($arr as $item) {
            $out .= '<item>' . htmlspecialchars((string) $item, ENT_XML1) . '</item>';
        }
        return $out;
    }

    /* =========================================================
       IMPORT XML / JSON
    ========================================================= */

    private function stripMarkdownFences(string $raw): string
    {
        // Retire les balises markdown ```xml ... ``` ou ``` ... ``` que les IA ajoutent souvent
        $raw = preg_replace('/^```[a-z]*\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/', '', $raw);
        return trim($raw);
    }

    public function parseXml(string $raw): array|\WP_Error
    {
        $raw = $this->stripMarkdownFences($raw);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if ($xml === false) {
            $err = libxml_get_last_error();
            return new \WP_Error('xml_parse', 'XML invalide : ' . ($err ? trim($err->message) : 'erreur inconnue') . ' — verifiez que vous avez colle le XML complet.');
        }

        $data = [];

        // Identite
        if (isset($xml->identite)) {
            $data['titre']        = (string) $xml->identite->titre;
            $data['slug']         = (string) $xml->identite->slug;
            // Nettoyer URL : supprimer format markdown [url](url) que les IA ajoutent parfois
            $raw_url = (string) $xml->identite->url;
            $data['url'] = preg_match('/\[([^\]]+)\]\([^\)]+\)/', $raw_url, $um) ? $um[1] : $raw_url;
            // Prefix : extraire les lettres uniquement (PER001 -> PER)
            $raw_pfx = (string) $xml->identite->prefix;
            $data['prefix'] = preg_match('/^([A-Z]{2,4})/', $raw_pfx, $pm) ? $pm[1] : $raw_pfx;
            $data['type_article'] = (string) $xml->identite->type_article;
            $data['langue']       = (string) $xml->identite->langue;
        }

        // Classification
        if (isset($xml->classification)) {
            $data['theme_principal'] = (string) $xml->classification->theme_principal;
            $data['sous_theme']      = (string) $xml->classification->sous_theme;
            $data['parcours']        = (string) $xml->classification->parcours;
            $data['serie']           = (string) $xml->classification->serie;
            $data['ordre_serie']     = (int)    $xml->classification->ordre_serie;
        }

        // Contenu
        if (isset($xml->contenu)) {
            $data['resume']       = (string) $xml->contenu->resume;
            $data['resume_court'] = (string) $xml->contenu->resume_court;
            $data['public_cible'] = (string) $xml->contenu->public_cible;
            $data['niveau_lecture'] = (string) $xml->contenu->niveau_lecture;

            foreach (['mots_cles','concepts','personnages','lieux','periodes','references_bibliques','citations_cles'] as $f) {
                $data[$f] = $this->xmlItemsToArray($xml->contenu->$f ?? null);
            }
        }

        // SEO
        if (isset($xml->seo)) {
            $data['seo_titre']       = (string) $xml->seo->seo_titre;
            $data['seo_description'] = (string) $xml->seo->seo_description;
            $data['og_titre']        = (string) $xml->seo->og_titre;
            $data['og_description']  = (string) $xml->seo->og_description;
            $data['schema_type']     = (string) $xml->seo->schema_type;
            $data['robots']          = (string) $xml->seo->robots;
            $data['seo_mots_cles']   = $this->xmlItemsToArray($xml->seo->seo_mots_cles ?? null);
        }

        // Relations
        if (isset($xml->relations)) {
            foreach (['articles_lies','articles_prerequis','articles_suite','sources_externes'] as $f) {
                $data[$f] = $this->xmlItemsToArray($xml->relations->$f ?? null);
            }
        }

        // Champs adaptatifs : capturer tout nœud XML non encore extrait
        $extra = [];
        foreach ($xml->children() as $section) {
            foreach ($section->children() as $key => $child) {
                if (array_key_exists($key, $data)) continue; // déjà extrait
                $arr = $this->xmlItemsToArray($child);
                if (!empty($arr)) {
                    $extra[$key] = $arr;
                } else {
                    $val = trim((string) $child);
                    if ($val !== '') $extra[$key] = $val;
                }
            }
        }
        if (!empty($extra)) {
            // Si le champ est une colonne DB connue, l'ajouter directement
            foreach ($extra as $k => $v) {
                if (in_array($k, self::DB_COLUMNS, true)) {
                    $data[$k] = $v;
                    unset($extra[$k]);
                }
            }
            // Le reste va dans champs_custom
            if (!empty($extra)) {
                $data['champs_custom'] = $extra;
            }
        }

        return $data;
    }

    private function xmlItemsToArray(?\SimpleXMLElement $node): array
    {
        if (!$node) return [];

        // Format attendu : <item>valeur</item> (template)
        $arr = [];
        foreach ($node->item as $item) {
            $v = trim((string) $item);
            if ($v !== '') $arr[] = $v;
        }
        if (!empty($arr)) return $arr;

        // Format IA : texte brut separé par ; ou ,
        $text = trim((string) $node);
        if ($text === '') return [];
        $sep  = strpos($text, ';') !== false ? ';' : ',';
        foreach (explode($sep, $text) as $part) {
            $v = trim($part);
            if ($v !== '') $arr[] = $v;
        }
        return $arr;
    }

    public function parseJson(string $raw): array|\WP_Error
    {
        $raw  = $this->stripMarkdownFences($raw);
        $data = json_decode(trim($raw), true);
        if (!is_array($data)) {
            return new \WP_Error('json_parse', 'JSON invalide ou non parseable. Verifiez que vous avez colle le JSON complet sans texte autour.');
        }
        return $data;
    }
}