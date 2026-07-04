# Schilo Builder — Migration WPBakery : tâches restantes

Ce document liste les tâches à réaliser pour étendre le module de migration
(`src/Service/Migration/`) à tous les blocs de contenu restants, en suivant
**exactement** le principe déjà posé par `TitleExtractor` et
`ConsultationExtractor`. Chaque tâche peut être traitée indépendamment et
dans l'ordre proposé.

---

## 0. Principe à respecter pour chaque extracteur (rappel)

Avant de coder un nouvel extracteur, relire `TitleExtractor.php` et
`ConsultationExtractor.php` comme référence. Règles non négociables :

1. **Une classe = un type d'élément**, dans `src/Service/Migration/Extractors/`,
   implémentant `ExtractorInterface` (`getKey()`, `getLabel()`, `extract(MigrationSourceContent $source)`).
2. **Choisir la bonne source** :
   - `$source->getRenderedHtml()` (DOM, via `MigrationDomHelper`) si le bloc est
     généré par un shortcode qui s'exécute correctement (ex: Wikilogy).
   - `$source->getRawContent()` (regex sur `post_content` brut) si le bloc
     repose sur un shortcode WPBakery (`[vc_message]`, `[vc_column_text]`, etc.)
     dont les attributs peuvent être corrompus par `wptexturize` au rendu.
   - **En cas de doute, tester les deux sur un vrai article** avant de choisir.
3. **Identifiants stables et "à motif"** : pour les éléments répétables (0 à n
   occurrences), utiliser le format `{cle}_{index}` où l'index 0 est implicite
   (`consultation_link`, `consultation_link_1`, `consultation_link_2`...). Ne
   jamais coder en dur un nombre maximum d'occurrences.
4. **Toujours retourner un tableau d'éléments**, même vide, jamais `null`.
   Chaque élément : `['id' => ..., 'label' => ..., 'content' => ..., 'meta' => []]`.
5. **Texte par défaut intelligent** : si un texte répétitif (ex: "Vous pouvez
   consulter l'annexe") doit accompagner chaque occurrence, utiliser le premier
   trouvé sur l'article comme valeur par défaut pour les occurrences qui ne
   l'ont pas, et marquer l'élément `'meta' => ['editable' => true]` pour qu'il
   soit éditable dans l'assistant.
6. **Enregistrer l'extracteur** dans `ExtractorRegistry::registerDefaults()`.
7. **Déclarer les champs de destination** du ou des types de section concernés
   dans `MigrationDestinationFields::getTypeSpecificFields()` s'ils n'y sont pas
   déjà (vérifier l'existant avant d'ajouter en double).
8. **Vérifier `MigrationApplier::buildSection()`** : si le nouveau champ de
   destination (ex: `versets_auto`, `image_auto`) nécessite une logique de
   construction de données spécifique (au-delà de `section_title`, `content`,
   `intro`, `texte_libre`, `links_auto`), ajouter un `case` dans le `switch`
   de cette méthode.
9. **Tester sur au moins 2 articles réels** du même préfixe ayant des nombres
   d'occurrences différents (ex: 1 verset vs 4 versets), pour confirmer que la
   généralisation par motif fonctionne avant de passer à la tâche suivante.
10. **Vérifier l'équilibre des accolades/parenthèses** après chaque édition
    (les sessions précédentes ont buté plusieurs fois sur des `str_replace`
    ayant supprimé une ligne de signature de méthode — toujours `view` le
    fichier après édition pour confirmer qu'aucune signature n'a disparu).

---

## 1. `EvangilesExtractor` (priorité haute — section déjà très utilisée)

**Objectif** : extraire le bloc "Textes bibliques" (Matthieu / Marc / Luc /
Jean, avec les références `[bvc]...[/bvc]` ou `[bnv]...[/bnv]`) vers la
section `evangiles`.

- Étudier le HTML rendu réel d'un article PER (ex: PER002, capture déjà
  fournie dans l'historique) pour repérer la structure exacte : titre
  "Textes bibliques", puis un bloc par évangéliste avec mention "non cité dans
  le livre" ou les versets affichés.
- Décider HTML rendu vs raw : les shortcodes `[bvc]`/`[bnv]` semblent
  s'afficher correctement dans le rendu (cf. capture PER002) — partir du
  rendu, mais vérifier sur un article où le shortcode pourrait être cassé par
  wptexturize (citer la jurisprudence ConsultationExtractor) avant de valider
  ce choix.
- Le champ de destination existant `versets_auto` (déjà déclaré dans
  `MigrationDestinationFields`) doit recevoir une liste structurée compatible
  avec `data.versets = [['reference' => ..., 'label' => ..., 'class' => ...]]`
  (voir `views/sections/evangiles.php` pour le format exact attendu).
- Ajouter le `case 'versets_auto':` dans `MigrationApplier::buildSection()`.
- Gérer le cas "non cité dans le livre" : ne pas l'inclure comme verset, mais
  potentiellement comme information à part (à clarifier avec l'utilisateur si
  ambigu).

---

## 2. `DetailsTechniquesExtractor`

**Objectif** : extraire le bloc "Détails techniques" (Lieu / Date / Mode
opératoire / Note sur le mode opératoire + image + texte sous l'image) vers
la section `details-techniques` ou `detail-technique-img-droite`.

- Repérer dans le rendu ou le raw content les lignes "Lieu :", "Date :",
  "Mode opératoire :", "Note sur le mode opératoire :" (cf. capture PER002
  dans l'historique de conversation).
- Chaque ligne = un élément séparé assignable (`details_lieu`, `details_date`,
  `details_mode_operatoire`, `details_note`), avec un champ de destination
  correspondant déjà existant dans `MigrationDestinationFields` pour
  `detail-technique-img-droite` (`lieu`, `date`, `mode_operatoire`,
  `note_mode_operatoire`).
- Extraire aussi l'image associée (probablement un `[vc_single_image]` ou
  `<img>` dans le rendu) → élément `details_image` avec `meta.image_url`,
  destiné au champ `image_id` (NB : nécessitera de retrouver l'attachment
  WordPress correspondant à l'URL, via `attachment_url_to_postid()` — ajouter
  cette résolution dans `MigrationApplier` au moment de construire la section,
  pas dans l'extracteur qui ne doit retourner que l'URL brute).
- Extraire le texte qui suit l'image ("Arrivé au mont Golgotha...") → élément
  `details_texte_dessous`, vers le champ `texte_dessous`.

---

## 3. `ImageExtractor` (générique, transverse)

**Objectif** : extraire toutes les images "libres" d'un article (hors celles
déjà capturées par un extracteur plus spécifique comme Détails techniques),
pour permettre leur affectation à n'importe quelle section ayant un champ
`image_id` (`image-textes`, `details-colonnes`, etc.).

- Travailler sur le HTML rendu (`<img>` ou `.vc_single_image`), car les
  shortcodes d'image WPBakery s'exécutent généralement sans souci avec
  wptexturize (pas d'attributs textuels complexes à corrompre) — à vérifier
  quand même sur un article test.
- Un élément par image trouvée (`image_1`, `image_2`...), avec
  `meta.image_url` et `meta.alt_text`.
- Dans `MigrationApplier`, ajouter la résolution URL → attachment ID
  (`attachment_url_to_postid()`) commune à cet extracteur et à
  `DetailsTechniquesExtractor` — factoriser dans une méthode privée partagée
  plutôt que dupliquer le code.

---

## 4. `ReferencesExtractor` (alias "Articles liés" automatiques type Wikilogy)

**Objectif** : extraire les listes Wikilogy "Voici quelques titres qui
peuvent vous intéresser" (visibles en bas de PER002 dans la capture) — à ne
pas confondre avec `ConsultationExtractor`, qui couvre les liens "Vous pouvez
consulter l'annexe..." en haut de l'article.

- Ce bloc semble généré par un shortcode Wikilogy (`[wikilogy_blog_list]` ou
  équivalent) qui s'exécute correctement → utiliser le rendu HTML.
- Repérer la grille de vignettes (image + titre cliquable) en bas de la page,
  juste avant la pagination "précédent/suivant".
- Retourne une liste de liens (titre + URL), à fusionner via le champ
  `links_auto` de la section `liens-articles` — même format de sortie que
  `ConsultationExtractor` pour permettre la réutilisation du `case
  'links_auto':` déjà existant dans `MigrationApplier`.
- Si le nombre d'articles suggérés est variable (l'attribut `count=` du
  shortcode), s'assurer que l'identifiant de chaque élément reste à motif
  (`references_link`, `references_link_1`...) comme pour Consultation.

---

## 5. `CommentaireExtractor`

**Objectif** : extraire le bloc "Commentaire" (paragraphes de texte, avec le
bouton "Écouter ce texte" à ignorer) vers une section `paragraphe` ou
`contexte` selon le template.

- Probablement le plus simple : un titre "Commentaire" suivi de plusieurs
  `<p>` ou `[vc_column_text]`, sans structure répétable complexe (pas de
  motif à n occurrences ici, juste un bloc de texte).
- Extraire le HTML interne du bloc de texte (en conservant les liens internes
  type `[Jean 3.25-29]` s'il y en a, à étudier sur un article réel).
- Un seul élément `commentaire_content`, destiné au champ `content` (éditeur).
- Exclure explicitement le bouton/widget "Écouter ce texte" (TTS) du contenu
  extrait — vérifier sa structure HTML pour l'exclure proprement plutôt que de
  le laisser polluer le texte migré.

---

## 6. Page "Bon à savoir" / sections globales hors article (à clarifier)

**Objectif** : les blocs "Derniers ajouts", "Bon à savoir", "Signification du
mot SCHILO" visibles en bas de PER002 semblent être des widgets globaux de
sidebar/footer, **pas du contenu propre à l'article**.

- Avant de coder quoi que ce soit : confirmer avec l'utilisateur si ces blocs
  doivent être migrés (peu probable, ce sont vraisemblablement des widgets de
  thème communs à toutes les pages) ou ignorés. Ne pas créer d'extracteur pour
  ce point sans validation explicite au préalable.

---

## 7. Une fois 2-3 extracteurs validés : industrialiser le "Modèle de migration"

Une fois les extracteurs ci-dessus posés et testés individuellement, revenir
sur l'orchestration globale :

- **Détection automatique du préfixe → suggestion du modèle** : à l'ouverture
  d'un article dans l'assistant, si un seul modèle existe pour son préfixe,
  le proposer automatiquement (actuellement il faut cliquer "Charger sur cet
  article" manuellement à chaque fois).
- **Application en masse** : une fois qu'un modèle PER est validé sur
  plusieurs articles individuellement, ajouter une action groupée ("appliquer
  ce modèle à tous les articles PER non encore migrés") avec aperçu avant
  validation et rapport d'erreurs par article (cf. schéma initial validé en
  début de conversation : "Construire le mapping" puis "Application en
  masse").
- **Page de gestion des modèles** dédiée (actuellement les modèles ne sont
  visibles que depuis la page de test, filtrés par préfixe) : lister tous les
  modèles tous préfixes confondus, avec aperçu du contenu de chaque règle.

---

## Notes générales pour Claude Code

- Le plugin est zippé/dézippé manuellement à chaque itération (pas de build
  process) : après chaque modification, incrémenter `SCHILO_BUILDER_VERSION`
  dans `schilo-builder.php` pour forcer le rechargement des assets en cache
  navigateur/WordPress.
- Aucun outil PHP CLI n'est disponible dans cet environnement de
  développement — la vérification de syntaxe se fait par relecture manuelle
  attentive (`view`) après chaque édition, en particulier autour des
  signatures de méthodes lors de `str_replace`.
- Le format de stockage des modèles de migration (`MigrationModelService`)
  utilise déjà la logique "par motif" — la réutiliser telle quelle pour tous
  les nouveaux extracteurs répétables, ne pas réinventer un mécanisme parallèle.
