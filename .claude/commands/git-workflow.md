# Skill : Workflow Git branches (develop / feature / master)

Gère le cycle de vie des branches du dépôt `schilo-theme`
(https://github.com/philieric/schilo-theme) : création de sous-branches pour
toute nouvelle modification, fusion vers `develop`, puis fusion `develop` → `master`
avec tag de version (déclenche Git Updater).

Invoquer avec `/git-workflow` pour créer une nouvelle branche de travail, fusionner
une branche existante, ou préparer une release vers `master`.

---

## 0. Principe général

| Branche | Rôle | Règle |
|---------|------|-------|
| `master` | Production/stable — ce que Git Updater et Composer (site Infomaniak) installent | Toujours déployable. Chaque commit sur `master` est **tagué** (`vX.Y.Z`). Jamais de commit direct dessus. |
| `develop` | Intégration | Toujours fonctionnel sur `schilo.local`. Reçoit uniquement des merges de sous-branches. Jamais de commit direct dessus. |
| `feature/*`, `fix/*`, `chore/*` | Travail en cours | Une branche = un seul chantier. Créée à partir de `develop` à jour, supprimée après fusion. |
| `hotfix/*` | Correctif urgent en prod | Créée à partir de `master`, fusionnée dans `master` **et** `develop`. |

**Règle absolue** : toute nouvelle modification (feature, correctif, chantier du roadmap)
passe par une sous-branche dédiée, jamais directement sur `develop` ou `master`.

Cette règle s'applique à **tous les skills du dépôt**, pas seulement à celui-ci : les
skills [[indexation]] et [[migration]] rappellent chacun ce prérequis en tête de fichier
et renvoient ici (section 2) pour la procédure de création de branche.

---

## 1. Convention de nommage des sous-branches

```
feature/<description-courte-en-kebab-case>   → nouvelle fonctionnalité
fix/<description-courte>                     → correction de bug
chore/<description-courte>                   → tâches techniques (config, deps, cleanup)
hotfix/<description-courte>                  → correctif urgent sur master
```

Exemples cohérents avec le [[project-roadmap]] :
- `feature/migration-infomaniak`
- `feature/liaison-articles`
- `fix/popup-shortcode-null`
- `chore/bump-composer-installers`

---

## 2. Créer une nouvelle sous-branche

Toujours partir de `develop` à jour :

```powershell
Set-Location "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
git checkout develop
git pull origin develop
git checkout -b feature/nom-du-chantier
```

Travailler, committer normalement sur cette branche (petits commits atomiques,
messages en français décrivant le "pourquoi").

---

## 3. Règles de test — AVANT toute fusion vers `develop`

Ne jamais fusionner une sous-branche sans avoir validé ces points, dans l'ordre :

### 3.1 Lint PHP (bloquant)

```powershell
Set-Location "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
git diff --name-only develop... -- "*.php" | ForEach-Object {
    php -l $_
}
```
Aucune erreur `Parse error` tolérée. Corriger avant de continuer.

### 3.2 Site fonctionnel (bloquant)

```powershell
# Vider le debug.log pour isoler les nouvelles erreurs
Clear-Content "C:\Apache24\htdocs\schilo\wp-content\debug.log" -ErrorAction SilentlyContinue

# Charger les pages clés
Invoke-WebRequest -Uri "http://schilo.local/" -UseBasicParsing | Select-Object StatusCode
Invoke-WebRequest -Uri "http://schilo.local/wp-admin/" -UseBasicParsing | Select-Object StatusCode

# Vérifier qu'aucune erreur Fatal/Warning n'est apparue
Get-Content "C:\Apache24\htdocs\schilo\wp-content\debug.log" -ErrorAction SilentlyContinue | Select-String "Fatal|Parse error"
```
Toute `Fatal error` ou `Parse error` bloque la fusion. Les `Notice`/`Deprecated`
préexistants ne bloquent pas sauf s'ils sont nouveaux (comparer avant/après).

### 3.3 Test manuel du parcours concerné

Ouvrir dans le navigateur (ou via le tool preview) la ou les pages réellement
impactées par le changement — pas seulement la page d'accueil. Vérifier visuellement
et dans la console JS (pas de nouvelle erreur).

### 3.4 Checklist finale avant merge

- [ ] `php -l` propre sur tous les fichiers modifiés
- [ ] Aucune nouvelle Fatal/Parse error dans `debug.log`
- [ ] Parcours fonctionnel testé manuellement
- [ ] `SCHILO_BUILDER_VERSION` bumpée dans `functions.php` si CSS/JS modifié (cache busting)
- [ ] Pas de clé API, mot de passe ou identifiant en dur dans le diff

---

## 4. Fusionner une sous-branche vers `develop`

```powershell
Set-Location "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
git checkout develop
git pull origin develop
git merge --no-ff feature/nom-du-chantier -m "Merge feature/nom-du-chantier dans develop"
git push origin develop

# Nettoyage : supprimer la branche locale et distante une fois fusionnée
git branch -d feature/nom-du-chantier
git push origin --delete feature/nom-du-chantier
```

`--no-ff` est obligatoire : conserve la trace de chaque chantier dans l'historique
même en cas de fast-forward possible.

**En cas de conflit** : résoudre manuellement fichier par fichier, ne jamais utiliser
`--theirs`/`--ours` en aveugle. Relancer intégralement la section 3 (tests) après
résolution, avant de finaliser le merge.

---

## 5. Règles supplémentaires — AVANT fusion `develop` → `master` (release)

En plus de la section 3 (déjà validée à chaque merge vers `develop`, donc normalement
acquise), vérifier spécifiquement sur `develop` avant de passer en `master` :

- [ ] Tous les chantiers prévus pour cette release sont fusionnés dans `develop`
- [ ] Le site tourne sur `develop` depuis au moins une session de travail sans régression
- [ ] Le header `Version:` dans `style.css` est incrémenté (semver : patch/minor/major
      selon l'ampleur des changements) et cohérent avec le tag qui sera créé
- [ ] Pas de TODO/FIXME bloquant introduit dans cette release
- [ ] `composer.json` du thème toujours valide : `composer validate` (voir §7)

---

## 6. Fusionner `develop` → `master` et publier la release

```powershell
Set-Location "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
git checkout master
git pull origin master
git merge --no-ff develop -m "Release vX.Y.Z"
git push origin master

# Tag = déclencheur de mise à jour pour Git Updater
git tag -a vX.Y.Z -m "Description courte de la release"
git push origin vX.Y.Z

# Revenir sur develop pour la suite du travail
git checkout develop
```

Remplacer `vX.Y.Z` par la version réellement présente dans le header `Version:` de
`style.css` (les deux doivent toujours correspondre, sinon Git Updater affiche une
version incohérente dans wp-admin).

---

## 7. Validation composer (avant chaque release)

```powershell
Set-Location "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
composer validate --no-check-publish
```
Doit retourner `./composer.json is valid`. Corriger avant de taguer si erreur.

---

## 8. Hotfix (correctif urgent en production)

Si un bug bloquant est découvert sur `master` alors que `develop` contient déjà
d'autres travaux non finalisés :

```powershell
git checkout master
git pull origin master
git checkout -b hotfix/nom-du-bug

# ... corriger, tester (section 3) ...

git checkout master
git merge --no-ff hotfix/nom-du-bug -m "Hotfix : nom-du-bug"
git tag -a vX.Y.Z+1 -m "Hotfix nom-du-bug"
git push origin master --tags

# Reporter le correctif dans develop pour ne pas le perdre
git checkout develop
git merge --no-ff hotfix/nom-du-bug -m "Report hotfix nom-du-bug dans develop"
git push origin develop

git branch -d hotfix/nom-du-bug
git push origin --delete hotfix/nom-du-bug
```

---

## 9. Audit rapide de l'état des branches

```powershell
Set-Location "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
git fetch origin --prune
git branch -a
git log --oneline --graph --all -20
```

À lancer en début de session pour savoir où reprendre le travail.

---

## 10. Travailler sur plusieurs branches en parallèle (git worktree)

**Contrainte** : un seul dossier de travail = une seule branche extraite à la fois.
Le dossier principal (`wp-content\themes\schilo-theme`) est celui servi en live par
Apache sur `schilo.local` — on ne peut donc pas y `checkout` une deuxième branche
sans arrêter de prévisualiser la première.

**Solution** : `git worktree` crée un dossier supplémentaire relié au même dépôt
(même historique, même remote), chacun sur sa propre branche, sans dupliquer le `.git`.

```powershell
Set-Location "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
git fetch origin

# Nouvelle branche de travail dans un dossier séparé, partant de develop à jour
git worktree add "C:\Apache24\worktrees\schilo-theme-feature-y" -b feature/nom-chantier-y develop

# Ou pour continuer une branche existante dans un 2e dossier (remplacer par le nom réel)
git worktree add "C:\Apache24\worktrees\schilo-theme-<branche>" feature/nom-de-la-branche-existante
```

Chaque worktree suit exactement les mêmes règles que le reste de ce document
(nommage §1, tests avant fusion §3, fusion vers `develop` §4).

### Limite importante : aperçu live

Seul le dossier principal (`wp-content\themes\schilo-theme`) est reconnu par
WordPress/Apache comme le thème actif sur `schilo.local`. Un worktree secondaire
permet d'éditer, committer, lancer `php -l` et faire les revues de code — mais
**pas** de voir le rendu dans le navigateur, tant qu'il n'a pas son propre vhost
Apache + une installation WordPress séparée (base de données dédiée). Si un
aperçu live simultané des deux branches est nécessaire, il faut créer ce
second environnement (site miroir local) — à faire sur demande explicite,
c'est un chantier à part entière.

### Nettoyage après fusion

```powershell
git worktree remove "C:\Apache24\worktrees\schilo-theme-feature-y"
git worktree prune
```
