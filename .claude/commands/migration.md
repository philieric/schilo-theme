# Skill : Migration complète Schilo

Effectue ou pilote la migration complète d'un site Schilo :
1. Transfert des CPT `content` + `reflexions` → `post` avec catégories
2. Configuration des modèles et templates Schilo Builder
3. Migration batch WPBakery → Schilo Builder pour tous les préfixes

---

> **Prérequis obligatoire — branche dédiée** : toute action de ce skill (transfert de
> CPT, configuration de modèles, migration batch d'un préfixe, correctif d'un script)
> doit commencer par la création d'une sous-branche dédiée depuis `develop` à jour
> (`feature/*`, `fix/*` ou `chore/*` selon le cas). Jamais de commit direct sur
> `develop` ou `master`. Voir [[git-workflow]] section 2 pour la procédure exacte
> (`git checkout develop && git pull && git checkout -b ...`).

---

## Contexte

Les scripts PHP réutilisables sont dans :
`wp-content/themes/schilo-theme/inc/builder/migration-scripts/`

- `01-transfer-cpt.php` — Transfère les CPT, migre les catégories
- `02-setup-models.php` — Enregistre templates + modèles de migration
- `03-batch-migrate.php` — Migration batch WPBakery → Schilo Builder

**Important** : ces scripts utilisent `define('WP_ROOT', dirname(__DIR__, 4))` prévu pour
une structure plugin. Depuis le thème (5 niveaux), les exécuter via `wp eval-file` (WP-CLI)
plutôt que par HTTP. WP-CLI est disponible à `C:\wp-cli\wp.bat`.

## Méthode d'exécution (WP-CLI)

```powershell
Set-Location "C:\Apache24\htdocs\schilo"
wp eval-file "wp-content/themes/schilo-theme/inc/builder/migration-scripts/02-setup-models.php"
wp eval-file "wp-content/themes/schilo-theme/inc/builder/migration-scripts/03-batch-migrate.php"
```

Pour un préfixe spécifique ou avec reset, écrire un script dans le répertoire scratchpad
puis l'exécuter avec `wp eval-file CHEMIN`.

## Préfixes connus et état actuel

| Préfixe | Description                  | Modèle         | Template                                                                      | Statut           |
|---------|------------------------------|----------------|-------------------------------------------------------------------------------|------------------|
| ANN     | Annexes                      | ann_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (122)   |
| APO     | Apocalypse                   | apo_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (16)    |
| BIB     | Bible / Transmission         | bib_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (9)     |
| CTD     | Contradictions               | ctd_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (65)    |
| DAN     | Daniel                       | dan_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (5)     |
| DOC     | Doctrines                    | doc_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (4)     |
| FDS     | Faits de société             | fds_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (5)     |
| INF     | Notes d'information          | inf_standard   | paragraphe, conclusion                                                        | ✅ migré (288)   |
| LGH     | Lignes historiques           | lgh_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (8)     |
| MIR     | Miracles                     | mir_standard   | paragraphe, conclusion                                                        | ✅ migré (42)    |
| PAR     | Paraboles (PER)              | par_standard   | liens-articles, paragraphe, conclusion                                        | ✅ migré (23)    |
| PDA     | Points d'approfondissement   | pda_standard   | paragraphe, conclusion                                                        | ✅ migré (19)    |
| PER     | Personnages bibliques        | per_standard   | liens-articles, evangiles, details-techniques, image-textes, paragraphe, conclusion | ✅ migré (360)   |
| PRB     | Paraboles synoptiques        | prb_standard   | paragraphe, conclusion                                                        | ✅ migré (80)    |

## Assignments par préfixe (configurés et validés)

### BIB, DAN, FDS, LGH, PAR (structure consultation + section_texte)
```php
'consultation_heading'  => ['section_type' => 'liens-articles', 'field' => 'section_title'],
'consultation_link'     => ['section_type' => 'liens-articles', 'field' => 'links_auto'],
'consultation_text'     => ['section_type' => 'liens-articles', 'field' => 'intro'],
'section_texte_heading' => ['section_type' => 'paragraphe',     'field' => 'section_title'],
'section_texte_content' => ['section_type' => 'paragraphe',     'field' => 'content'],
```

### DOC (idem + id dynamique doc_02fd93c6)
Même structure. Modèle supplémentaire `doc_02fd93c6` en DB (id dynamique généré lors de la config manuelle de DOC002).

### PDA (structure simple sans consultation)
```php
'section_texte_heading' => ['section_type' => 'paragraphe', 'field' => 'section_title'],
'section_texte_content' => ['section_type' => 'paragraphe', 'field' => 'content'],
'plain_content'         => ['section_type' => 'paragraphe', 'field' => 'content'],
```

### INF (plain_content uniquement)
```php
'section_texte_heading' => ['section_type' => 'paragraphe', 'field' => 'section_title'],
'plain_content'         => ['section_type' => 'paragraphe', 'field' => 'content'],
```

### MIR, PRB (section_texte avec shortcodes [b])
```php
'section_texte_heading' => ['section_type' => 'paragraphe', 'field' => 'section_title'],
'section_texte_content' => ['section_type' => 'paragraphe', 'field' => 'content'],
```
Note : ces articles utilisent le shortcode `[b]...[/b]` (référence inline sans suffixe).
ContentFilter.php le protège désormais via la regex `b(?:ib|vc|nv|rc)?`.

## Bug corrigé dans 03-batch-migrate.php

Le script original ne faisait pas `expandModelForElements()` — les éléments suffixés
(`section_texte_content_61`, `consultation_link_3`, etc.) n'étaient pas assignés.
**Corrigé** : la ligne 134 appelle maintenant `$ms->expandModelForElements($model, $elements)`.

## Workflow reset + re-migration

Pour un préfixe déjà marqué `migrated` (avec mauvais modèle) :
```php
// Écrire un script scratchpad avec :
delete_post_meta($postId, '_schilo_migration_status');
delete_post_meta($postId, '_schilo_builder_sections');
// puis apply() + update_post_meta()
```
Exemple complet dans le scratchpad de session : `reset-and-migrate.php`.

## Éléments extracteurs connus

| Extracteur              | Éléments générés                                         |
|-------------------------|----------------------------------------------------------|
| ConsultationExtractor   | `consultation_heading`, `consultation_link`, `consultation_link_N`, `consultation_text`, `consultation_text_N` |
| SectionTextesExtractor  | `section_texte_heading`, `section_texte_heading_N`, `section_texte_content`, `section_texte_content_N` |
| PlainContentExtractor   | `plain_content`                                          |
| TitleExtractor          | `title`                                                  |

**expandModelForElements()** est obligatoire pour mapper les éléments suffixés (`_N`).

## Shortcodes bibliques

Le plugin `Usx-import` enregistre : `[b]`, `[bib]`, `[bvc]`, `[brc]`, `[bnv]`.
`ContentFilter.php` protège ces shortcodes de `wpautop` avant de les exécuter via `do_shortcode()`.
Pattern actuel : `/\[b(?:ib|vc|nv|rc)?\b[^\]]*\].*?\[\/b(?:ib|vc|nv|rc)?\]/is`
(le `?` rend le suffixe optionnel, couvrant ainsi `[b]...[/b]` utilisé dans MIR et PRB)

## Assignation des catégories après migration

### Fonctionnement automatique
`CategoryAssigner::assignCategoryOnSave()` se déclenche sur le hook `save_post`.
Il lit le mapping `schilo_builder_prefix_categories` (option WP) et assigne la catégorie correspondante.

**Automatique uniquement** si l'article est sauvegardé via WordPress (éditeur, `wp_update_post()`).
**Pas déclenché** par les scripts de migration (qui écrivent directement en DB via `update_post_meta()`).

### Mapping préfixe → catégorie (option `schilo_builder_prefix_categories`)
```json
{
  "CTD": 377,
  "PER": 366,
  "ANN": 367,
  "INF": 452,
  "MIR": 449,
  "PRB": 445
}
```
Les préfixes APO, DOC, BIB, DAN, FDS, LGH, PAR, PDA ont leurs catégories grâce au script `01-transfer-cpt.php` (qui utilise `wp_insert_post()`).

### Catégories correspondantes par préfixe
| Préfixe | Catégorie WP                      | term_id | Parent       |
|---------|-----------------------------------|---------|--------------|
| ANN     | Annexes                           | 367     | —            |
| APO     | Apocalypse                        | 440     | Séries (443) |
| BIB     | Histoire de la Bible              | 389     | Thém. (444)  |
| CTD     | Les contradictions                | 377     | Thém. (444)  |
| DAN     | La révélation de Daniel           | 439     | Séries (443) |
| DOC     | Doctrines de la Bible             | 385     | Thém. (444)  |
| FDS     | Faits de société                  | 434     | Thém. (444)  |
| INF     | Notes historiques                 | 452     | —            |
| LGH     | Histoire de la Bible              | 389     | Thém. (444)  |
| MIR     | Miracles                          | 449     | —            |
| PAR     | Les paraboles                     | 435     | Séries (443) |
| PDA     | Parole d'un Ami                   | 378     | Thém. (444)  |
| PER     | Synopse (sous-catégories 1-8)     | 366     | —            |
| PRB     | Analyse comparative des textes    | 445     | —            |

### Assigner les catégories manuellement après migration batch
```php
// Script scratchpad — à adapter selon les préfixes
$new_mappings = ['INF' => 452, 'MIR' => 449, 'PRB' => 445];
$default_cat  = (int) get_option('default_category');
foreach ($new_mappings as $prefix => $cat_id) {
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status IN ('publish','draft') AND post_title LIKE %s",
        $prefix . '%'
    ));
    foreach ($ids as $post_id) {
        $current = wp_get_post_categories((int)$post_id) ?: [];
        if (!in_array($cat_id, $current, true)) $current[] = $cat_id;
        $current = array_diff($current, [$default_cat]);
        wp_set_post_categories((int)$post_id, array_values(array_unique(array_map('intval', $current))), false);
    }
}
wp_cache_flush();
wp term recount category  // via WP-CLI séparément
```
Après l'assignation, toujours relancer `wp term recount category` pour mettre à jour les compteurs.

## Notes importantes

- WPBakery, Divi et Wikilogy doivent rester **actifs pendant la migration**.
  Ne les désinstaller qu'après validation complète de tous les préfixes.
- Toujours inspecter un article représentatif avant de configurer les assignments :
  utiliser `ExtractorRegistry::extractAll()` sur le `post_content` brut.
- Après migration, vérifier le rendu sur `https://schilo.local/[slug]/`.
- `_schilo_migration_status = 'migrated'` + `_schilo_builder_sections` = métas clés.
- Les catégories **ne sont pas assignées automatiquement** par les scripts de migration batch.
  Il faut soit utiliser le script d'assignation manuelle ci-dessus, soit re-sauvegarder
  les articles via l'éditeur WordPress (déclenche `save_post` → `CategoryAssigner`).
