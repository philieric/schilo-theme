# Skill : Système d'Indexation Schilo

Implémente ou maintient le module d'indexation IA des articles Schilo Builder.
Couvre : table SQL, pages admin WordPress, validation humaine, connexion IA (Claude/ChatGPT), export/import XML-JSON.

Invoquer avec `/indexation` pour installer le module depuis zéro, reprendre une installation partielle, ou ajouter une fonctionnalité spécifique.

---

> **Prérequis obligatoire — branche dédiée** : toute action de ce skill (installation,
> ajout de fonctionnalité, correctif, config) doit commencer par la création d'une
> sous-branche dédiée depuis `develop` à jour (`feature/*`, `fix/*` ou `chore/*` selon
> le cas). Jamais de commit direct sur `develop` ou `master`. Voir [[git-workflow]]
> section 2 pour la procédure exacte (`git checkout develop && git pull && git checkout -b ...`).

---

## 0. Avant tout : vérifier l'état actuel

Commence TOUJOURS par auditer ce qui existe déjà pour ne pas écraser du travail en cours :

```powershell
# Table SQL
Set-Location "C:\Apache24\htdocs\schilo"
php -r "require 'wp-load.php'; global $wpdb; echo $wpdb->get_var('SHOW TABLES LIKE \"' . $wpdb->prefix . 'schilo_indexation\"') ? 'TABLE OK' : 'TABLE ABSENTE';"

# Fichiers PHP
ls "wp-content\themes\schilo-theme\inc\builder\src\Admin\IndexationPage.php" 2>$null
ls "wp-content\themes\schilo-theme\inc\builder\src\Service\IndexationService.php" 2>$null
ls "wp-content\themes\schilo-theme\views\admin\indexation-page.php" 2>$null
```

Affiche un tableau récapitulatif de ce qui est présent / manquant, puis demande à l'utilisateur de confirmer ce qu'il veut implémenter si ce n'est pas évident.

---

## 1. Règles de code absolues (lire avant d'écrire la moindre ligne)

| Règle | Raison |
|-------|--------|
| **PowerShell WriteAllText UTF-8 sans BOM** pour TOUS les fichiers PHP | L'outil Edit corrompt les apostrophes (0x27 → 0x3F) |
| **`php -l fichier.php`** après chaque fichier créé | Détecter les erreurs de syntaxe immédiatement |
| **Nonce WordPress** sur chaque action AJAX et formulaire POST | Sécurité CSRF |
| **`current_user_can('manage_options')`** sur chaque méthode admin | Contrôle d'accès |
| **Validation humaine avant tout INSERT/UPDATE** en base | Règle métier fondamentale |
| Ne jamais exposer les clés API IA | Elles sont dans `get_option('schilo_ia_config')` |
| Bumper `SCHILO_BUILDER_VERSION` dans `functions.php` après modifications | Cache busting CSS/JS |

```powershell
# Pattern d'écriture PHP — à utiliser systématiquement
$path = "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme\inc\builder\src\Admin\IndexationPage.php"
$content = @'
<?php
// ... contenu PHP ...
'@
[System.IO.File]::WriteAllText($path, $content, [System.Text.UTF8Encoding]::new($false))
php -l $path
```

---

## 2. Architecture des fichiers

```
inc/builder/
  src/
    Admin/
      IndexationPage.php        ← classe principale (menus, AJAX, routing)
    Service/
      IndexationService.php     ← logique métier (DB, IA, XML)
  views/admin/
    indexation-page.php         ← liste articles + statuts
    indexation-validation.php   ← formulaire validation humaine champ par champ
    indexation-config.php       ← paramétrage blocs, champs custom, prompts IA
  assets/admin/
    indexation-admin.css        ← styles (charger via SettingsPage::enqueueAssets)
    indexation-admin.js         ← AJAX calls, UI dynamique
```

Enregistrement du sous-menu dans `inc/builder/src/Admin/SettingsPage.php` :
- Ajouter `add_action('wp_ajax_schilo_ia_index_article', ...)` dans `register()`
- Ajouter `add_action('wp_ajax_schilo_ia_index_batch', ...)`
- Ajouter `add_action('wp_ajax_schilo_import_indexation', ...)`
- Ajouter `add_action('wp_ajax_schilo_save_indexation_validated', ...)`
- Ajouter le submenu `schilo-builder-indexation` dans `addMenu()`
- Inclure `IndexationPage` dans le chargement via `require_once`

---

## 3. Table SQL — `wp_schilo_indexation`

Créer via `dbDelta()` dans la méthode d'activation ou au premier chargement de la page.

### SQL complet (57 champs, 7 blocs)

```sql
CREATE TABLE {prefix}schilo_indexation (
  -- BLOC 1 : Identité article
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id         INT UNSIGNED NOT NULL,
  titre           VARCHAR(500)  NOT NULL DEFAULT '',
  slug            VARCHAR(255)  NOT NULL DEFAULT '',
  url             VARCHAR(1000) NOT NULL DEFAULT '',
  prefix          VARCHAR(20)   NOT NULL DEFAULT '',
  type_article    VARCHAR(100)  NOT NULL DEFAULT '',
  statut_wp       VARCHAR(50)   NOT NULL DEFAULT 'publish',
  langue          VARCHAR(10)   NOT NULL DEFAULT 'fr',
  date_article    DATETIME      DEFAULT NULL,
  date_modification DATETIME    DEFAULT NULL,
  auteur          VARCHAR(200)  NOT NULL DEFAULT '',

  -- BLOC 2 : Classification thématique
  theme_principal VARCHAR(255)  NOT NULL DEFAULT '',
  sous_theme      VARCHAR(255)  NOT NULL DEFAULT '',
  parcours        VARCHAR(255)  NOT NULL DEFAULT '',
  serie           VARCHAR(255)  NOT NULL DEFAULT '',
  ordre_serie     INT           NOT NULL DEFAULT 0,
  categories      JSON          DEFAULT NULL,
  tags_wp         JSON          DEFAULT NULL,

  -- BLOC 3 : Analyse de contenu
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

  -- BLOC 4 : SEO
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

  -- BLOC 5 : Relations
  articles_lies       JSON          DEFAULT NULL,
  articles_prerequis  JSON          DEFAULT NULL,
  articles_suite      JSON          DEFAULT NULL,
  sources_externes    JSON          DEFAULT NULL,

  -- BLOC 6 : Champs paramétrables
  champs_custom       JSON          DEFAULT NULL,

  -- BLOC 7 : Workflow & validation
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
  KEY idx_statut    (statut_indexation),
  KEY idx_theme     (theme_principal(100)),
  KEY idx_prefix    (prefix),
  KEY idx_date_val  (date_validation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Utiliser `$wpdb->prefix` pour le préfixe de table. Ne jamais appeler `$wpdb->query(CREATE TABLE...)` directement — passer par `dbDelta()` de `wp-admin/includes/upgrade.php`.

---

## 4. Classe IndexationPage.php — squelette

```php
<?php
namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\IndexationService;

class IndexationPage {

    private IndexationService $service;

    public function __construct() {
        $this->service = new IndexationService();
    }

    public function register(): void {
        add_action('wp_ajax_schilo_ia_index_article',       [$this, 'ajaxIndexArticle']);
        add_action('wp_ajax_schilo_ia_index_batch',         [$this, 'ajaxIndexBatch']);
        add_action('wp_ajax_schilo_import_indexation',      [$this, 'ajaxImport']);
        add_action('wp_ajax_schilo_save_indexation_validated', [$this, 'ajaxSaveValidated']);
        add_action('wp_ajax_schilo_export_indexation_xml',  [$this, 'ajaxExportXml']);
    }

    public function renderPage(): void {
        // Router selon ?tab=validation|config|list
        $tab = sanitize_key($_GET['tab'] ?? 'list');
        switch ($tab) {
            case 'validation': include SCHILO_BUILDER_DIR . 'views/admin/indexation-validation.php'; break;
            case 'config':     include SCHILO_BUILDER_DIR . 'views/admin/indexation-config.php';    break;
            default:           include SCHILO_BUILDER_DIR . 'views/admin/indexation-page.php';
        }
    }

    public function ajaxIndexArticle(): void {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Accès refusé', 403);

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) wp_send_json_error('post_id manquant');

        $provider = sanitize_key($_POST['provider'] ?? 'claude');
        $result   = $this->service->indexArticleViaIA($post_id, $provider);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result); // retourne les champs pour validation humaine
    }

    public function ajaxSaveValidated(): void {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Accès refusé', 403);

        $data    = $_POST['data'] ?? [];
        $post_id = absint($data['post_id'] ?? 0);
        if (!$post_id) wp_send_json_error('post_id manquant');

        // La validation humaine a eu lieu côté client — on enregistre
        $saved = $this->service->saveValidated($post_id, $data, get_current_user_id());
        $saved ? wp_send_json_success() : wp_send_json_error('Erreur enregistrement');
    }

    public function ajaxExportXml(): void {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Accès refusé', 403);

        $post_id = absint($_POST['post_id'] ?? 0);
        $xml     = $this->service->generateXmlTemplate($post_id);
        wp_send_json_success(['xml' => $xml]);
    }

    public function ajaxImport(): void {
        check_ajax_referer('schilo_indexation', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Accès refusé', 403);

        $raw     = stripslashes($_POST['content'] ?? '');
        $format  = sanitize_key($_POST['format'] ?? 'json');
        $post_id = absint($_POST['post_id'] ?? 0);

        $parsed = ($format === 'xml')
            ? $this->service->parseXml($raw)
            : $this->service->parseJson($raw);

        if (is_wp_error($parsed)) {
            wp_send_json_error($parsed->get_error_message());
        }
        // Retourne les champs parsés pour validation humaine avant enregistrement
        wp_send_json_success(['fields' => $parsed, 'post_id' => $post_id]);
    }
}
```

---

## 5. Service IndexationService.php — méthodes clés

### Prompt IA structuré

```php
private function buildPrompt(int $post_id): string {
    $post     = get_post($post_id);
    $content  = wp_strip_all_tags($post->post_content);
    $titre    = $post->post_title;
    $prefix   = get_post_meta($post_id, '_schilo_prefix', true);

    return "Tu es un expert en analyse de contenu biblique et théologique.\n"
         . "Analyse cet article WordPress et retourne un JSON valide avec exactement ces champs :\n"
         . "[resume, resume_court, mots_cles (tableau), concepts (tableau), personnages (tableau), "
         . "lieux (tableau), periodes (tableau), references_bibliques (tableau), citations_cles (tableau), "
         . "public_cible, niveau_lecture, theme_principal, sous_theme, parcours, serie, ordre_serie, "
         . "seo_titre, seo_description, seo_mots_cles (tableau), og_titre, og_description, "
         . "schema_type, robots, articles_lies (tableau vide si inconnu), "
         . "articles_prerequis (tableau vide si inconnu), articles_suite (tableau vide si inconnu)]\n\n"
         . "Article : titre=\"{$titre}\", prefix=\"{$prefix}\"\n"
         . "Contenu :\n" . mb_substr($content, 0, 8000) . "\n\n"
         . "Contraintes : resume=500-800 mots, resume_court=max 150 mots, "
         . "seo_titre=max 70 caractères, seo_description=max 160 caractères.\n"
         . "Retourne UNIQUEMENT le JSON, sans texte avant ou après.";
}
```

### Appel API IA

```php
public function indexArticleViaIA(int $post_id, string $provider): array|\WP_Error {
    $config  = get_option('schilo_ia_config', []);
    $prompt  = $this->buildPrompt($post_id);

    if ($provider === 'claude') {
        $key = $config['claude']['api_key'] ?? '';
        if (!$key) return new \WP_Error('no_key', 'Clé Claude manquante');
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => [
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => $config['claude']['model'] ?? 'claude-opus-4-8',
                'max_tokens' => 4096,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);
    } else {
        $key = $config['openai']['api_key'] ?? '';
        if (!$key) return new \WP_Error('no_key', 'Clé OpenAI manquante');
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'    => $config['openai']['model'] ?? 'gpt-4o',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);
    }

    if (is_wp_error($response)) return $response;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $raw  = ($provider === 'claude')
        ? ($body['content'][0]['text'] ?? '')
        : ($body['choices'][0]['message']['content'] ?? '');

    // Stocker la réponse brute AVANT de la parser (pour audit)
    $this->storeRawResponse($post_id, $raw, $provider);

    $parsed = json_decode($raw, true);
    if (!$parsed) return new \WP_Error('parse_error', 'Réponse IA non-JSON');

    return $parsed; // sera présenté à l'humain pour validation avant INSERT
}
```

### Enregistrement après validation humaine

```php
public function saveValidated(int $post_id, array $data, int $user_id): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'schilo_indexation';

    // Champs JSON : encoder les tableaux
    $json_fields = ['categories','tags_wp','mots_cles','concepts','personnages','lieux',
                    'periodes','references_bibliques','citations_cles','seo_mots_cles',
                    'schema_json','articles_lies','articles_prerequis','articles_suite',
                    'sources_externes','champs_custom'];

    $row = [];
    foreach ($data as $k => $v) {
        $row[$k] = in_array($k, $json_fields) ? wp_json_encode($v) : sanitize_text_field((string)$v);
    }

    $row['post_id']           = $post_id;
    $row['valide_par']        = $user_id;
    $row['date_validation']   = current_time('mysql');
    $row['statut_indexation'] = 'valide';

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE post_id = %d", $post_id
    ));

    if ($existing) {
        $row['version'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT version FROM $table WHERE post_id = %d", $post_id
        )) + 1;
        return (bool)$wpdb->update($table, $row, ['post_id' => $post_id]);
    }

    $row['date_indexation'] = current_time('mysql');
    $row['indexe_par']      = $user_id;
    return (bool)$wpdb->insert($table, $row);
}
```

---

## 6. Format XML export/import

```xml
<?xml version="1.0" encoding="UTF-8"?>
<schilo_indexation post_id="{post_id}" version="1" generated="{date}">
  <identite>
    <titre><![CDATA[{titre}]]></titre>
    <slug>{slug}</slug>
    <url>{url}</url>
    <prefix>{prefix}</prefix>
    <type_article>{type_article}</type_article>
    <langue>{langue}</langue>
  </identite>
  <classification>
    <theme_principal><![CDATA[]]></theme_principal>
    <sous_theme><![CDATA[]]></sous_theme>
    <parcours><![CDATA[]]></parcours>
    <serie><![CDATA[]]></serie>
    <ordre_serie>0</ordre_serie>
  </classification>
  <contenu>
    <resume><![CDATA[REMPLIR : résumé 500-800 mots]]></resume>
    <resume_court><![CDATA[REMPLIR : résumé court max 150 mots]]></resume_court>
    <mots_cles><item>mot1</item><item>mot2</item></mots_cles>
    <concepts><item></item></concepts>
    <personnages><item></item></personnages>
    <lieux><item></item></lieux>
    <periodes><item></item></periodes>
    <references_bibliques><item></item></references_bibliques>
    <citations_cles><item></item></citations_cles>
    <public_cible>Intermédiaire</public_cible>
    <niveau_lecture>Moyen</niveau_lecture>
  </contenu>
  <seo>
    <seo_titre><![CDATA[REMPLIR max 70 cars]]></seo_titre>
    <seo_description><![CDATA[REMPLIR max 160 cars]]></seo_description>
    <seo_mots_cles><item></item></seo_mots_cles>
    <og_titre><![CDATA[]]></og_titre>
    <og_description><![CDATA[]]></og_description>
    <schema_type>Article</schema_type>
    <robots>index,follow</robots>
  </seo>
  <relations>
    <articles_lies></articles_lies>
    <articles_prerequis></articles_prerequis>
    <articles_suite></articles_suite>
    <sources_externes></sources_externes>
  </relations>
</schilo_indexation>
```

Pour générer le template, pré-remplir `identite` depuis `get_post()` et laisser les champs d'analyse vides avec des commentaires `REMPLIR`.

---

## 7. Vue liste (indexation-page.php) — éléments essentiels

- Tableau des articles (`WP_Query`) avec colonnes : Titre / Préfixe / Statut indexation / Source / Date validation / Actions
- Badges colorés : `brouillon`=gris, `en_attente`=orange, `valide`=vert, `rejete`=rouge
- Boutons par ligne : "Indexer via IA" / "Valider" / "Exporter XML" / "Importer"
- Filtre par statut d'indexation (dropdown)
- Bouton "Indexation en lot" (sélection multiple + batch AJAX)

---

## 8. Vue validation (indexation-validation.php) — éléments essentiels

- Afficher les données IA brutes côte à côte avec les champs éditables
- Un champ de saisie par propriété (textarea pour les longs, input pour les courts)
- Champs JSON (mots_cles, concepts…) : interface tag-input ou textarea JSON
- Section "Données brutes IA" repliable (pour audit)
- Boutons : "Valider et enregistrer" (AJAX `schilo_save_indexation_validated`) / "Rejeter" / "Modifier le prompt et relancer"
- Le bouton Valider n'est actif qu'après review complète (JS dirty-check sur les champs vides)

---

## 9. Vue config (indexation-config.php) — éléments essentiels

- Toggle activer/désactiver chaque bloc (sauvegardé dans `wp_options('schilo_indexation_config')`)
- Champs custom : formulaire d'ajout (nom, type, description) + liste avec supression
- Prompts IA : textarea éditable par type d'article (stocké en option), avec restauration par défaut
- Provider IA par défaut : radio Claude / OpenAI (lit `schilo_ia_config`)

---

## 10. Ordre d'implémentation recommandé

1. **Table SQL** — dbDelta + vérification avec WP-CLI : `wp db query "DESCRIBE wp_schilo_indexation"`
2. **IndexationService.php** — méthodes DB (saveValidated, getByPostId, storeRawResponse, generateXmlTemplate, parseXml, parseJson)
3. **IndexationPage.php** — register() + renderPage() + méthodes AJAX
4. **Enregistrement dans SettingsPage.php** — require_once + new IndexationPage + submenu + AJAX hooks
5. **indexation-page.php** — vue liste (la plus utile pour tester)
6. **indexation-validation.php** — formulaire de validation
7. **indexation-admin.css + indexation-admin.js** — styles et JS AJAX
8. **indexation-config.php** — paramétrage (peut attendre une deuxième session)

Après chaque étape, tester dans WordPress avant de passer à la suivante.

---

## 11. Commandes de vérification utiles

```powershell
# Vérifier la table après création
Set-Location "C:\Apache24\htdocs\schilo"
wp db query "DESCRIBE wp_schilo_indexation" --allow-root

# Compter les articles sans index
wp db query "SELECT COUNT(*) FROM wp_posts p LEFT JOIN wp_schilo_indexation i ON p.ID=i.post_id WHERE p.post_type='post' AND p.post_status='publish' AND i.id IS NULL" --allow-root

# Syntaxe PHP de tous les fichiers du module
php -l "wp-content\themes\schilo-theme\inc\builder\src\Admin\IndexationPage.php"
php -l "wp-content\themes\schilo-theme\inc\builder\src\Service\IndexationService.php"

# Bumper la version après modifications
# Modifier SCHILO_BUILDER_VERSION dans functions.php
```
