# Schilo Builder 0.3.2

## Nouveauté
- TinyMCE dynamique pour les nouvelles sections ajoutées en JavaScript.
- Chargement de `wp_enqueue_editor()`.
- Sauvegarde automatique du contenu TinyMCE lors de l'enregistrement.
- Maintien de la médiathèque WordPress pour les images.


## Version 0.3.3
- Masquage des boutons médias dans TinyMCE du builder.
- Masquage ciblé des boutons Instant Images / Font Awesome / TOC dans l'interface Schilo Builder.
- Barre TinyMCE dynamique simplifiée.


## Version 0.3.4
- Schilo Builder est maintenant affiché directement sous le titre de l'article.
- L'éditeur WordPress standard reste affiché en dessous.
- Suppression de l'affichage en metabox classique.


## Version 0.3.5
- Ajout d'un sélecteur de type de template dans l'article.
- Stockage du type choisi dans `_schilo_builder_type`.
- Mode AUTO : détection depuis le préfixe du titre.
- Types par défaut : PER, ANN, REF, DEFAULT.
- Préparation pour une future page `Schilo Builder > Types`.


## Version 0.3.6
- Nouvelle présentation de l'en-tête en deux colonnes.
- Colonne gauche 1/3 : présentation Schilo Builder.
- Colonne droite 2/3 : configuration du template et zone prévue pour futures options.


## Version 0.3.7
- Correction du chevauchement visuel.
- Zone builder séparée sur fond gris très clair.
- Séparation nette entre la colonne d’ajout et la colonne des sections.
- Toolbar mieux espacée.


## Version 0.3.8
- Ajout de la numérotation automatique des titres.
- Exemple : `PER - La vie de Jésus` devient automatiquement `PER542 - La vie de Jésus`.
- Le plugin cherche le dernier numéro existant pour le préfixe et ajoute +1.
- Si un numéro existe déjà dans le titre, le titre n'est pas modifié.


## Version 0.3.9
- Correction de la numérotation automatique.
- Utilisation de `wp_insert_post_data` au lieu de `save_post`.
- Le titre est corrigé avant l'enregistrement en base, ce qui est plus fiable.


## Version 0.4.0
- Correction robuste de la numérotation automatique.
- Double sécurité : `wp_insert_post_data` + `save_post`.
- Formats acceptés :
  - `PER - La vie de Jésus`
  - `PER: La vie de Jésus`
  - `PER La vie de Jésus`
- Si le titre est déjà numéroté, il n'est pas modifié.


## Version 0.4.1
- Numérotation toujours sur 3 chiffres.
- `PER - Titre` devient `PER001 - Titre`, `PER008 - Titre`, etc.
- Correction automatique des titres déjà numérotés mais mal formatés :
  - `CDT77 - Titre` devient `CDT077 - Titre`
  - `CDT8 - Titre` devient `CDT008 - Titre`
  - `CDT100 - Titre` reste `CDT100 - Titre`


## Version 0.4.2
- Détection des doublons de numéros par préfixe.
- Si `PER542 - Titre` existe déjà sur un autre article, le nouvel article est renommé avec le prochain numéro disponible.
- Les numéros restent normalisés sur 3 chiffres minimum.
- Exemple :
  - `CDT77 - Titre` -> `CDT077 - Titre`
  - si `CDT077` existe déjà -> `CDT078 - Titre` ou prochain numéro libre après le maximum existant.


## Version 0.4.3
- Normalisation systématique du séparateur après le numéro : ` espace + tiret + espace`.
- Ajout automatique du tiret s’il manque.
- Correction des espaces irréguliers autour du tiret.
- Première lettre du titre mise en majuscule.
- Exemples :
  - `ctd77 la vie` -> `CTD077 - La vie`
  - `CTD077-La vie` -> `CTD077 - La vie`
  - `CTD077  -  la vie` -> `CTD077 - La vie`


## Version 0.4.4
- Correction des entités HTML dans les titres, ex: `&#8211;`.
- Suppression des tirets parasites au début et à la fin du titre.
- Exemple :
  - `CTD078 - &#8211; Le miracle &#8211;` devient `CTD078 - Le miracle`
  - `CTD078 - – Le miracle –` devient `CTD078 - Le miracle`


## Version 0.4.5
- Nouvelle normalisation stricte du titre.
- Le plugin extrait le préfixe + numéro, supprime tout ce qui se trouve entre le dernier chiffre et le vrai titre, puis reconstruit avec ` - `.
- Exemples :
  - `CTD077 &#8211; Le miracle &#8211;` -> `CTD077 - Le miracle`
  - `CTD077   --   Le miracle` -> `CTD077 - Le miracle`
  - `ctd77 le miracle` -> `CTD077 - Le miracle`


## Version 0.4.6
- Ajout d'une page d'administration `Schilo Builder > Réglages`.
- Correspondance préfixe -> catégorie WordPress.
- Attribution automatique de la catégorie à l'enregistrement de l'article.
- Exemple : `CTD` -> catégorie `Les contradictions`.


## Version 0.4.7
- Correction de l'enregistrement des réglages préfixe -> catégorie.
- La page sauvegarde directement avec `update_option`.
- Ajout d'un message de confirmation après sauvegarde.


## Version 0.4.8
- Lorsqu'une catégorie automatique est appliquée, la catégorie WordPress par défaut `Non classé` est retirée uniquement elle.
- Ajout d'un bouton `Ouvrir les réglages` dans le bloc Schilo Builder, avec ouverture dans un nouvel onglet.


## Version 0.4.9
- Création d'un tableau de bord `Schilo Builder`.
- Déplacement des réglages actuels dans `Schilo Builder > Préfixes & catégories`.
- Ajout de pages prévues pour :
  - Types & templates
  - Migration WPBakery
  - Sections
  - Indexation
  - Outils
- Le bouton dans l'édition d'article pointe maintenant vers `Préfixes & catégories`.


## Version 0.5.0
- Développement de la partie `Schilo Builder > Sections`.
- Gestion des sections disponibles :
  - clé technique
  - libellé
  - description
  - activation / désactivation
  - vue PHP associée
- Les boutons d’ajout de section dans l’article sont maintenant générés depuis cette configuration.
- Ajout d’un bouton `Configurer les sections` dans la colonne d’ajout du builder.


## Version 0.5.1
- Correction de l'affichage de la page `Schilo Builder > Sections`.
- Réécriture sécurisée de la classe `SettingsPage`.
- Déclaration explicite du sous-menu Sections.


## Version 0.5.2
- Correction de la numérotation trop élevée.
- Exemple : si le dernier article est `PER458`, un titre saisi `PER700 - Titre` devient `PER459 - Titre`.
- Les doublons restent corrigés automatiquement.


## Version 0.5.3
- Ajout d’un bouton `Retour au tableau de bord` sur les sous-pages d’administration.
- Différenciation couleur des boutons de l’éditeur d’article qui pointent vers l’administration.
- Bouton `Préfixes & catégories` en violet.
- Bouton `Configurer les sections` en vert.


## Version 0.5.4
- Le tableau de bord affiche maintenant le nombre de sections configurées.
- La carte `Sections` affiche le format `actives / total`.
- La carte `Sections` n’est plus marquée comme `Prévu`.


## Version 0.5.5
- Correction forcée de la carte `Sections` du tableau de bord.
- Suppression du badge `Prévu` sur la carte Sections.
- Affichage du compteur `sections actives / sections totales`.


## Version 0.5.6
- Ajout de la page `Schilo Builder > Migration WPBakery`.
- Scan des articles contenant des shortcodes WPBakery.
- Prévisualisation du contenu nettoyé.
- Migration vers une section Schilo Builder `Contenu migré`.
- Sauvegarde de l’ancien `post_content` dans `_schilo_backup_post_content`.
- Restauration possible depuis la sauvegarde.


## Version 0.5.7
- Correction de la page `Migration WPBakery` : le sous-menu pointe bien vers la vraie page de migration.
- Scan WPBakery fiabilisé avec recherche SQL directe sur `[vc_`.
- Actions `Prévisualiser`, `Migrer`, `Restaurer` sécurisées et fonctionnelles.


## Version 0.5.8
- La migration WPBakery supprime aussi les shortcodes Wikilogy.
- Shortcodes ciblés :
  - `[wikilogy ...]`
  - `[wikilogy_xxx]`
  - `[wiki_xxx]`
  - `[wl_xxx]`
- Le contenu texte entre les shortcodes reste conservé.


## Version 0.5.9
- Migration intelligente Wikilogy.
- `[wikilogy_title ...]` est transformé en section `Introduction`.
- `[wikilogy_blog_list ...]` est transformé en section `Références / Articles liés`.
- Ajout du shortcode `[schilo_articles_lies category="" count=""]` pour remplacer les anciennes listes Wikilogy.
- Les autres shortcodes `[wikilogy_*]` sont supprimés du contenu migré.


## Version 0.6.0
- Sécurisation de la migration WPBakery / Wikilogy.
- Le `post_content` original n’est jamais modifié pendant la migration.
- Ajout d’une sauvegarde originale immuable : `_schilo_original_wpbakery_content`.
- La restauration utilise prioritairement cette sauvegarde originale.
- Les shortcodes Wikilogy sont nettoyés uniquement dans les sections Schilo générées, pas dans le contenu original.


## Version 0.6.1
- Création de l’interface `Types & templates`.
- Gestion des templates par préfixe : DEFAULT, PER, CTD, ANN.
- Choix des sections prévues pour chaque template.
- La migration détecte le préfixe et conserve le template utilisé dans les métadonnées des sections.


## Version 0.6.2
- Correction du tableau de bord : la carte `Types & templates` ouvre bien la page des templates.
- Correction du sous-menu `Types & templates` qui pointait encore vers le module en préparation.


## Version 0.6.4
- Le sélecteur de template dans l’article liste maintenant les templates actifs de `Types & templates`.
- Ajout d’une option dans l’article : compléter les sections manquantes du template à l’enregistrement.
- En cas de changement de template, les sections existantes ne sont jamais écrasées.
- Le dernier template appliqué est mémorisé dans `_schilo_builder_last_template`.


## Version 0.6.5
- Correction du sélecteur `Type de template` dans l’article.
- Le sélecteur lit directement les templates actifs depuis `Schilo Builder > Types & templates`.
- Ajout d’un compteur de templates actifs sous le sélecteur.


## Version 0.6.6
- Correction du fatal error `$availableTypes` non défini dans la vue d’édition article.
- `BuilderMetabox` transmet maintenant toujours `$availableTypes`, `$sectionTypes` et `$lastAppliedTemplate`.
- La vue possède aussi un fallback de sécurité.


## Version 0.6.7
- Correction du fatal error `TemplateService::getActiveTemplates()` absent.
- Réécriture propre de `TemplateService` avec `getActiveTemplates()`.
- Sécurisation de `ArticleTypeService` avec fallback `method_exists`.


## Version 0.6.8
- Correction de l’application des templates dans l’article.
- La case `Compléter les sections manquantes` ajoute maintenant réellement les sections du template choisi.
- Les sections existantes ne sont jamais supprimées ni écrasées.
- Réécriture propre de `BuilderMetabox` pour stabiliser le flux sauvegarde/template.


## Version 0.6.9
- Ajout d’un vrai bouton `Appliquer le template maintenant`.
- Le bouton ajoute immédiatement les sections manquantes du template choisi.
- L’action passe par `admin-post.php`.
- La case d’application à l’enregistrement reste disponible.

## Version 0.7.0
- Repart depuis la base v0.6.9.
- Ajout d'une configuration serveur pour définir la structure interne des sections.
- Fichier principal : `config/section-structures.php`.
- Fichier de surcharge recommandé : `config/section-structures.custom.php`.
- La section `evangiles` n'utilise plus l'éditeur TinyMCE par défaut.
- La section `evangiles` affiche au minimum 4 champs texte simples pour les versets.
- Possibilité d'ajouter des champs versets supplémentaires dans l'administration de l'article.
- La vue front `views/sections/evangiles.php` affiche les versets dans l'ordre.


## Version 0.7.1
- Section `Évangiles` structurée pour les références bibliques.
- 5 lignes par défaut :
  - Matthieu → `citation-matthieu`
  - Marc → `citation-marc`
  - Luc → `citation-luc`
  - Jean → `citation-jean`
  - Bible → `citation-bible`
- Chaque ligne possède :
  - un libellé
  - une classe CSS sélectionnable
  - un champ référence simple
- Le shortcode `[bnv]...[/bnv]` est ajouté automatiquement côté front.
- Possibilité d’ajouter des références supplémentaires.


## Version 0.7.2
- Correction de l’affichage admin de la section `Évangiles`.
- Les champs Matthieu, Marc, Luc, Jean et Bible apparaissent maintenant par défaut.
- Ajout du menu déroulant de classe CSS pour chaque ligne.
- Le champ de référence reste un champ texte simple.


## Version 0.7.3
- Correction forcée de l’affichage admin de la section `Évangiles`.
- Les 5 lignes Matthieu, Marc, Luc, Jean, Bible s’affichent par défaut.
- Chaque ligne affiche sa classe par défaut dans la liste déroulante.
- Le front ne filtre plus Matthieu/Marc/Luc/Jean/Bible : seul un champ vide est ignoré.


## Version 0.7.4
- Raccordement du nouveau champ `repeatable_bible_refs` dans `views/admin/partials/section-item.php`.
- La section Évangiles affiche maintenant les champs Matthieu, Marc, Luc, Jean et Bible via le système de structure.
- Chaque ligne affiche la bonne classe par défaut dans la liste déroulante.
- Le front ne filtre plus les valeurs Matthieu/Marc/Luc/Jean/Bible.


## Version 0.7.5
- Ajout d’une colonne `Vue admin PHP` dans `Schilo Builder > Sections`.
- Chaque type de section peut maintenant définir :
  - une vue front PHP
  - une vue admin PHP
- Ajout du service `AdminSectionRenderer`.
- `section-item.php` délègue maintenant l’affichage du formulaire à la vue admin configurée.
- La section `evangiles` utilise `views/admin/sections/evangiles.php`.
- Les sections classiques utilisent `views/admin/sections/default.php`.


## Version 0.7.6
- Correction du raccordement entre templates, sections et vues admin.
- `section-item.php` utilise maintenant obligatoirement `AdminSectionRenderer`.
- La page `Types & templates` affiche la vue front et la vue admin associées à chaque section.
- La section `evangiles` est raccordée à `views/admin/sections/evangiles.php`.

## Version 0.7.7
- Correction forcée de `metabox-builder.php` : le rendu des sections passe maintenant uniquement par `partials/section-item.php`.
- `section-item.php` délègue à `AdminSectionRenderer`, donc à la vue admin configurée.


## Version 0.7.8
- Retrait de l’ancienne présentation inline dans `metabox-builder.php`.
- Rendu unique via le connecteur `partials/section-item.php`.
- `section-item.php` appelle `AdminSectionRenderer`.
- `AdminSectionRenderer` charge la vue admin configurée dans `Schilo Builder > Sections`.


## Version 0.7.9
- Correction du bouton `Ajouter` : la section Évangiles créée manuellement utilise maintenant le bon formulaire.
- Les lignes Matthieu, Marc, Luc, Jean, Bible sont générées aussi côté JavaScript.
- Restauration du visuel des cartes `Schilo Builder` et `Configuration du template`.
- Restauration du rendu `default.php` avec grille Titre / Classe CSS et éditeur visuel WordPress.


## Version 0.8.0
- Correction du bouton `Replier / Ouvrir`.
- Le clic ne fait plus remonter en haut de page.
- La position de scroll est conservée.
- Les liens `#` ont été remplacés par des boutons pour éviter le saut navigateur.

## Version 0.8.1
- Remise des boutons `Tout replier` et `Tout ouvrir` alignés à droite.

## Version 0.8.2
- Correction des boutons `Ouvrir/Replier` qui ne fonctionnaient plus sur certaines sections.
- Suppression des anciens handlers JavaScript conflictuels.
- Conservation de la position de scroll.
