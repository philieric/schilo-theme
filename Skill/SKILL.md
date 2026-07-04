---
name: schilo-wordpress-theme
description: >
  Skill complet pour développer et maintenir le thème WordPress Schilo.org —
  site d'étude biblique francophone organisé autour des quatre Évangiles
  (Matthieu/rouge #e05a2b, Marc/vert #2e9e4f, Luc/bleu #2872d4, Jean/violet #7c4db8).

  TOUJOURS utiliser ce skill pour :
  - Modifier ou créer un fichier du thème schilo-theme (PHP, CSS, JS)
  - Créer un nouveau template de page WordPress (page-*.php)
  - Ajouter un composant CSS ou un module JS dans le namespace Schilo
  - Intégrer un plugin (GTranslate, Schilo Builder, etc.) dans le thème
  - Déboguer une erreur sur Schilo.org

  Couvre intégralement :
  - Architecture PHP POO (classes Schilo_*, Schilo_Security, Schilo_Nav_Walker)
  - Design system CSS (variables --schilo-*, code couleur Évangiles immuable)
  - JavaScript ES5 POO (namespace Schilo, modules IIFE, Schilo.Polyfills)
  - Responsive 6 breakpoints mobiles (360px→1280px) + 5 grands écrans (1400px→4K)
  - Compatibilité tous navigateurs (IE11, Safari, Firefox, Chrome, Edge, Android)
  - Sécurité WordPress complète (headers HTTP, XML-RPC, nonces, honeypot)
  - Intégration GTranslate (clic simulé sur liens natifs, widget caché)
  - Templates Contact (formulaire PHP sécurisé + guide dynamique JS)
  - Template À propos (grille valeurs, timeline 4 cartes, signification Schilo)
  - Gestion des erreurs PHP/JS/CSS + plan de tests complet
  - Relation avec Schilo Builder (plugin interne, délégation CPT et rendu fiches)
---

# Skill — Thème WordPress Schilo.org

## Vue d'ensemble

Schilo.org est un site d'étude biblique francophone dédié à la découverte de
Jésus à travers les quatre Évangiles. Le thème `schilo-theme` est un thème
WordPress 100 % sur-mesure, sans Gutenberg, sans builder externe — conçu pour
fonctionner en tandem avec le plugin **Schilo Builder** (plugin interne qui gère
les articles, les parcours et la migration depuis Wikilogy).

### Contexte éditorial

Le contenu est organisé autour de **quatre Évangiles**, chacun identifié par une
couleur stricte et immuable :

| Évangile | Couleur | Code hex | Lettre badge |
|----------|---------|----------|--------------|
| Matthieu | Rouge   | `#e05a2b` | M |
| Marc     | Vert    | `#2e9e4f` | M |
| Luc      | Bleu    | `#2872d4` | L |
| Jean     | Violet  | `#7c4db8` | J |

Le site publie trois types de contenus principaux :
- **Fiches** (*fiches d'étude*) — articles courts sur un passage, un personnage
  ou un thème biblique, identifiés par un code (ex. `PER001`)
- **Parcours** — séquences ordonnées de fiches autour d'un thème ou d'un Évangile
- **Annexes** — ressources complémentaires (synopse, chronologie, glossaire…)

Le site est **gratuit, sans publicité et sans inscription**.
Langue principale : **français**. Traduction via GTranslate (10 langues).

---

### Philosophie du projet

#### Design
- Ton **moderne, épuré, professionnel, agréable à lire**
- Fond général gris clair `#eef0f4` — jamais blanc pur, jamais fond foncé hors hero
- Hero sombre `#1e2a3a` pour les en-têtes de page et le footer
- Accent principal **bleu Luc** `#2872d4` pour les éléments d'interface (boutons,
  focus, guides, liens actifs) — sauf sur les versets ou badges liés à un autre Évangile
- Typographie : **Inter** (sans-serif, lisibilité interface) + **Lora** (serif,
  titres hero et citations bibliques)
- Icônes : **Tabler Icons** exclusivement (`ti ti-*`)
- Pas d'emojis, pas d'images décoratives — design pur texte + icônes

#### Code
- **PHP** : orienté objet, classes nommées `Schilo_*`, méthodes statiques pour
  les utilitaires globaux (`Schilo_Security::init()`, `Schilo_Nav_Walker`)
- **JavaScript** : POO stricte avec namespace `Schilo`, pattern IIFE
  (`(function() { ... })()`) pour chaque module, méthodes privées préfixées `_`
- **CSS** : 100 % variables custom properties `--schilo-*`, aucune valeur
  hardcodée dans les règles de composants
- **Architecture** : un fichier = une responsabilité (pas de god-file)

#### Compatibilité
- **ES5 uniquement** — zéro `const`, zéro `let`, zéro arrow function `=>`
- Préfixes vendeurs CSS complets : `-webkit-`, `-moz-`, `-ms-`, `-o-`
- `Schilo.Polyfills` couvre IE11, anciens Safari, Android WebView, Firefox ESR
- Meta `X-UA-Compatible: IE=edge` dans le `<head>`
- `viewport-fit=cover` pour les encoches iPhone / Dynamic Island

#### Sécurité
- Classe `Schilo_Security` centralise toute la protection
- Nonces WordPress sur tous les formulaires
- Honeypot anti-spam sur le formulaire contact
- Headers HTTP : X-Frame-Options, X-Content-Type-Options, XSS-Protection,
  Referrer-Policy, Permissions-Policy
- XML-RPC désactivé
- Messages de connexion génériques (anti-énumération)
- API REST `/wp/v2/users` bloquée pour les non-authentifiés
- Uploads filtrés (`.php`, `.js`, `.html` interdits)
- `DISALLOW_FILE_EDIT` activé

#### Ce que le thème ne fait PAS
- ❌ Pas de gestion des types de contenus (CPT) — délégué à Schilo Builder
- ❌ Pas de rendu des fiches d'étude — délégué à Schilo Builder
- ❌ Pas de gestion des parcours — délégué à Schilo Builder
- ❌ Pas de Gutenberg (`use_block_editor_for_post → false`)
- ❌ Pas de jQuery (vanilla JS uniquement)
- ❌ Pas de framework CSS (Bootstrap, Tailwind…)

---

### Principes de développement à respecter absolument

Ces règles s'appliquent à **chaque ligne de code** produite pour ce projet.
Ne jamais les contourner, même si cela semble plus simple.

#### PHP
```
✅ defined( 'ABSPATH' ) || exit;    — première ligne de chaque fichier PHP
✅ Constantes define() AVANT require_once
✅ Toutes les sorties via esc_html_e() / esc_url() / esc_attr() / esc_textarea()
✅ Apostrophes échappées : esc_html_e( 'C\'est', 'schilo' )
✅ Classes nommées Schilo_NomDeLaClasse
✅ Méthodes statiques pour les utilitaires : Schilo_Security::init()
✅ wp_verify_nonce() avant tout traitement POST
✅ sanitize_*() sur tous les $_POST avant utilisation
❌ Jamais echo sans esc_*()
❌ Jamais $_POST sans sanitize_*()
❌ Jamais get_permalink() sans vérifier que la page existe
```

#### JavaScript
```
✅ var uniquement (jamais const / let)
✅ function() {} uniquement (jamais les arrow functions =>)
✅ Namespace Schilo : var Schilo = Schilo || {};
✅ Pattern IIFE : Schilo.Module = (function() { 'use strict'; ... })();
✅ Méthodes privées préfixées _ : function _doSomething() {}
✅ Vérifier l'existence des éléments DOM avant de les utiliser
✅ Auto-init après DOMContentLoaded
❌ Jamais jQuery
❌ Jamais de variable globale hors namespace Schilo
❌ Jamais de code en dehors d'un module Schilo.*
```

#### CSS
```
✅ Variables --schilo-* pour toutes les valeurs de design
✅ Préfixes vendeurs dans compat.css pour les nouvelles propriétés
✅ clamp() pour les tailles de police des titres
✅ Styles inline en dernier recours (conflits WordPress/plugin)
❌ Jamais de valeur hardcodée (#1a2230) dans un composant CSS
❌ Jamais modifier les couleurs des Évangiles
❌ Jamais display:none sur les éléments GTranslate (casse les listeners)
```

---

## 1. Structure des fichiers

```
schilo-theme/
├── style.css                  ← Déclaration WP + design system CSS complet
├── functions.php              ← Setup, enqueues, sécurité, helpers PHP
├── header.php                 ← Nav sticky avec sélecteur langue + bouton Contact
├── footer.php                 ← Footer 4 colonnes + légende couleurs Évangiles
├── index.php                  ← Fallback de base WP (requis)
├── front-page.php             ← Hook pour Schilo Builder (page d'accueil)
├── single.php                 ← Hook pour Schilo Builder (article/fiche)
├── page.php                   ← Page statique générique
├── page-contact.php           ← Template Name: Contact
├── page-apropos.php           ← Template Name: À propos
├── 404.php                    ← Page d'erreur
├── template-parts/
│   └── nav-walker.php         ← Schilo_Nav_Walker (liens <a> sans <ul>/<li>)
└── assets/
    ├── css/
    │   ├── compat.css         ← Préfixes vendeurs + fallbacks IE11 (chargé EN PREMIER)
    │   └── responsive.css     ← Breakpoints 480px → 4K (chargé après style.css)
    └── js/
        ├── schilo-polyfills.js  ← Schilo.Polyfills — chargé dans <head>
        ├── schilo.js            ← Schilo.App + sous-modules
        ├── schilo-lang.js       ← Schilo.Lang — sélecteur GTranslate
        └── schilo-contact.js    ← Schilo.Contact — guide formulaire
```

### Ordre de chargement obligatoire

```
CSS : compat.css → style.css → responsive.css
JS  : schilo-polyfills.js (head) → schilo.js (footer) → schilo-lang.js → schilo-contact.js
```

---

## 2. Design System CSS

### Variables (`style.css` — section `:root`)

```css
:root {
  /* Fonds */
  --schilo-bg-page:    #eef0f4;   /* fond général gris clair */
  --schilo-bg-card:    #ffffff;
  --schilo-bg-dark:    #1e2a3a;   /* hero, footer, CTA */
  --schilo-bg-mid:     #273548;
  --schilo-bg-muted:   #f4f6f9;

  /* Bordures */
  --schilo-border:     #dde2ea;
  --schilo-border-mid: #c8d0dc;

  /* Texte */
  --schilo-text-primary:   #1a2230;
  --schilo-text-secondary: #556070;
  --schilo-text-muted:     #8a96a8;
  --schilo-text-hint:      #b0bac8;

  /* CODE COULEUR ÉVANGILES — JAMAIS MODIFIER */
  --schilo-mat:          #e05a2b;   /* Matthieu — rouge/orange */
  --schilo-mat-dark:     #7a2808;
  --schilo-mat-bg:       #f9ded4;   /* fond pale */
  --schilo-mat-bg2:      #fdeee8;   /* fond très pale */
  --schilo-mat-border:   #e8a080;

  --schilo-marc:         #2e9e4f;   /* Marc — vert */
  --schilo-marc-dark:    #0e5a28;
  --schilo-marc-bg:      #cdefd8;
  --schilo-marc-bg2:     #e6f7ec;
  --schilo-marc-border:  #80cc98;

  --schilo-luc:          #2872d4;   /* Luc — bleu */
  --schilo-luc-dark:     #0e3f88;
  --schilo-luc-bg:       #c8dff8;
  --schilo-luc-bg2:      #e2eefb;
  --schilo-luc-border:   #80b0e8;

  --schilo-jean:         #7c4db8;   /* Jean — violet */
  --schilo-jean-dark:    #3d1878;
  --schilo-jean-bg:      #dac8f0;
  --schilo-jean-bg2:     #ede4f8;
  --schilo-jean-border:  #b090d8;

  /* Typographie */
  --schilo-font-sans:  'Inter', -apple-system, sans-serif;
  --schilo-font-serif: 'Lora', Georgia, serif;

  /* Rayons */
  --schilo-radius-sm:  6px;
  --schilo-radius-md:  10px;
  --schilo-radius-lg:  14px;
  --schilo-radius-xl:  20px;

  /* Layout */
  --schilo-container:  1400px;
  --schilo-container-w: 90%;
  --schilo-nav-h:      56px;
}
```

### Règle absolue sur les couleurs Évangiles

Chaque Évangile a **toujours** la même couleur partout dans le thème et dans
Schilo Builder. Ne jamais déroger à ce code couleur :

| Évangile | Couleur principale | Utilisation |
|----------|-------------------|-------------|
| Matthieu | `#e05a2b` rouge   | Badge M, fond verset, bordure |
| Marc     | `#2e9e4f` vert    | Badge M, fond verset, bordure |
| Luc      | `#2872d4` bleu    | Badge L, fond verset, bordure, accent UI |
| Jean     | `#7c4db8` violet  | Badge J, fond verset, bordure |

### Composants CSS principaux

#### Container
```css
.schilo-container {
  width: var(--schilo-container-w);   /* 90% */
  max-width: var(--schilo-container); /* 1400px */
  margin: 0 auto;
}
```

#### Card
```css
.schilo-card { background: var(--schilo-bg-card); border: 1px solid var(--schilo-border); border-radius: var(--schilo-radius-lg); }
.schilo-card__head { padding: .85rem 1.3rem; border-bottom: 1px solid #eef1f5; display: flex; align-items: center; justify-content: space-between; }
.schilo-card__body { padding: 1.1rem 1.3rem; }
```

#### Verset biblique — structure HTML obligatoire
```html
<div class="schilo-verse schilo-verse--luc">
  <div class="schilo-verse__head schilo-verse__head--luc">
    <div class="schilo-verse__head-left">
      <div class="schilo-ev-badge schilo-ev-badge--luc">L</div>
      <span class="schilo-verse__ref schilo-verse__ref--luc">
        Luc 1.1–4 <span class="schilo-verse__sub">Prologue</span>
      </span>
    </div>
    <div class="schilo-verse__versions">
      <button class="schilo-vpill schilo-vpill--luc active" data-ev="luc" data-ver="LSG">LSG</button>
      <button class="schilo-vpill schilo-vpill--luc" data-ev="luc" data-ver="BDS">BDS</button>
      <button class="schilo-vpill schilo-vpill--luc" data-ev="luc" data-ver="NBS">NBS</button>
      <button class="schilo-vpill schilo-vpill--luc" data-ev="luc" data-ver="TOB">TOB</button>
    </div>
  </div>
  <div class="schilo-verse__body schilo-verse__body--luc">
    <p class="schilo-verse__text" id="luc-text">Texte du verset…</p>
  </div>
</div>
```

**Règle** : le fond du body du verset (`schilo-verse__body--luc`) est la variante
`bg2` de la couleur (très pale), le head utilise la variante `bg` (pale).

---

## 3. Architecture PHP

### functions.php — structure

```php
// 1. CONSTANTES (EN PREMIER — avant tout require)
define( 'SCHILO_VERSION', '1.0.0' );
define( 'SCHILO_DIR',     get_template_directory() );
define( 'SCHILO_URI',     get_template_directory_uri() );
define( 'SCHILO_ASSETS',  SCHILO_URI . '/assets' );

// 2. REQUIRE (après les constantes)
require_once SCHILO_DIR . '/template-parts/nav-walker.php';

// 3. SETUP THÈME
add_action( 'after_setup_theme', 'schilo_theme_setup' );

// 4. ENQUEUES
add_action( 'wp_enqueue_scripts', 'schilo_enqueue_assets' );

// 5. DÉSACTIVATION GUTENBERG
add_filter( 'use_block_editor_for_post', '__return_false', 99 );
add_filter( 'use_block_editor_for_post_type', '__return_false', 99 );

// 6. SÉCURITÉ
add_action( 'init', [ 'Schilo_Security', 'init' ] );
```

### Ordre d'enqueue CSS/JS

```php
function schilo_enqueue_assets() {
    // Fonts
    wp_enqueue_style( 'schilo-fonts', 'https://fonts.googleapis.com/...' );
    wp_enqueue_style( 'tabler-icons', 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/...' );

    // CSS — ordre strict
    wp_enqueue_style( 'schilo-compat',     SCHILO_ASSETS . '/css/compat.css',     ['schilo-fonts', 'tabler-icons'] );
    wp_enqueue_style( 'schilo-main',       get_stylesheet_uri(),                   ['schilo-fonts', 'tabler-icons', 'schilo-compat'] );
    wp_enqueue_style( 'schilo-responsive', SCHILO_ASSETS . '/css/responsive.css',  ['schilo-main'] );

    // JS — polyfills dans le HEAD (false = head, true = footer)
    wp_enqueue_script( 'schilo-polyfills', SCHILO_ASSETS . '/js/schilo-polyfills.js', [], SCHILO_VERSION, false );
    wp_enqueue_script( 'schilo-main',      SCHILO_ASSETS . '/js/schilo.js',            ['schilo-polyfills'], SCHILO_VERSION, true );
    wp_enqueue_script( 'schilo-lang',      SCHILO_ASSETS . '/js/schilo-lang.js',       [], SCHILO_VERSION, true );

    // Contact uniquement sur le template Contact
    if ( is_page_template( 'page-contact.php' ) ) {
        wp_enqueue_script( 'schilo-contact', SCHILO_ASSETS . '/js/schilo-contact.js', ['schilo-main'], SCHILO_VERSION, true );
    }

    // Variables PHP → JS
    wp_localize_script( 'schilo-main', 'schiloData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'schilo_nonce' ),
        'homeUrl' => home_url( '/' ),
        'version' => SCHILO_VERSION,
    ] );
}
```

### Classe Schilo_Security

```php
class Schilo_Security {
    public static function init() {
        self::remove_version_hints();   // supprimer ?ver= WP
        self::harden_headers();         // X-Frame-Options, XSS-Protection...
        self::disable_xml_rpc();        // bloquer XML-RPC
        self::protect_login();          // messages d'erreur génériques
        self::disable_file_edit();      // DISALLOW_FILE_EDIT
        self::remove_unnecessary_meta(); // nettoyer le <head>
        self::protect_rest_api();       // bloquer /wp/v2/users non authentifié
        self::sanitize_uploads();       // bloquer .php/.js uploadés
    }
}
```

### Helpers PHP (disponibles dans tous les templates)

```php
schilo_asset( 'css/responsive.css' )   // URL complète d'un asset
schilo_ev_color( 'luc' )               // '#2872d4'
schilo_ev_letter( 'luc' )             // 'L'
schilo_ev_name( 'luc' )               // 'Luc'
schilo_ev_badge( 'luc', '32px' )      // affiche <span class="schilo-ev-badge...">
```

### Détection de la page Contact

```php
// Toujours détecter par template — jamais par slug (peut changer)
$by_tpl = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
if ( ! empty( $by_tpl ) ) {
    $contact_url = get_permalink( $by_tpl[0]->ID );
} else {
    // Fallback par slugs courants
    foreach ( [ 'contactez-nous', 'contact', 'nous-contacter' ] as $slug ) {
        $p = get_page_by_path( $slug );
        if ( $p ) { $contact_url = get_permalink( $p->ID ); break; }
    }
}
```

---

## 4. Architecture JavaScript — Namespace Schilo

**Règle absolue** : tout le JS est en **ES5** (`var`, pas `const`/`let`/arrow
functions). Le namespace `Schilo` est déclaré une seule fois en haut de chaque
fichier avec `var Schilo = Schilo || {};`.

### Structure type d'un module

```javascript
var Schilo = Schilo || {};

Schilo.NomModule = (function () {
    'use strict';

    /* ── État interne ── */
    var _state = {
        element: null,
        isOpen : false
    };

    /* ── Méthodes privées (préfixe _) ── */
    function _doSomething() { /* ... */ }

    /* ── Méthode publique init() ── */
    function init() {
        _state.element = document.getElementById('mon-id');
        if (!_state.element) return;
        _state.element.addEventListener('click', _doSomething);
    }

    /* ── API publique ── */
    return {
        init: init,
        // exposer uniquement ce qui est nécessaire
    };

})();

/* ── Auto-init ── */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Schilo.NomModule.init(); });
} else {
    Schilo.NomModule.init();
}
```

### Modules existants

| Module | Fichier | Rôle |
|--------|---------|------|
| `Schilo.Polyfills` | `schilo-polyfills.js` | 18 polyfills (IE11, Safari, Android) |
| `Schilo.App` | `schilo.js` | Point d'entrée, initialise tous les modules |
| `Schilo.Nav` | `schilo.js` | Menu burger mobile |
| `Schilo.Search` | `schilo.js` | Ouverture recherche |
| `Schilo.Anchors` | `schilo.js` | Ancres de navigation au scroll |
| `Schilo.Verses` | `schilo.js` | Sélecteur versions bibliques (LSG/BDS/NBS/TOB) |
| `Schilo.MarkRead` | `schilo.js` | Bouton "Marquer comme lu" + AJAX |
| `Schilo.Date` | `schilo.js` | Date du verset du jour |
| `Schilo.Lang` | `schilo-lang.js` | Sélecteur de langue GTranslate |
| `Schilo.Contact` | `schilo-contact.js` | Guide dynamique formulaire contact |

### Schilo.Polyfills — méthodes disponibles

```javascript
Schilo.Polyfills.init()               // tout installer (appelé automatiquement)
Schilo.Polyfills.installClosest()     // Element.closest()
Schilo.Polyfills.installMatches()     // Element.matches()
Schilo.Polyfills.installCustomEvent() // CustomEvent
Schilo.Polyfills.installRAF()         // requestAnimationFrame
```

---

## 5. Intégration GTranslate

**Principe** : ne pas piloter GTranslate via `doGTranslate()` — peu fiable.
Au lieu, injecter le shortcode `[GTranslate]` caché dans le footer, puis
simuler un clic sur ses liens natifs `[data-gt-lang="xx"]`.

### Dans functions.php

```php
function schilo_gtranslate_hidden_widget() {
    echo '<div id="schilo-gt-hidden" style="position:absolute;top:-9999px;..." aria-hidden="true">';
    echo do_shortcode( '[GTranslate]' );
    echo '</div>';
}
add_action( 'wp_footer', 'schilo_gtranslate_hidden_widget', 5 );
```

### Dans schilo-lang.js — méthode de déclenchement

```javascript
function _triggerLang(code) {
    if (code === 'fr') {
        // Effacer les cookies googtrans + reload
        _clearCookies();
        location.reload();
        return;
    }
    // Clic sur le vrai lien GTranslate (listeners déjà attachés)
    var link = document.querySelector('a[data-gt-lang="' + code + '"]');
    if (link) {
        ['pointerover','pointerenter','mouseover','mouseenter'].forEach(function(ev) {
            link.dispatchEvent(new MouseEvent(ev, { bubbles: true, cancelable: true }));
        });
        setTimeout(function () {
            link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        }, 50);
    }
}
```

### CSS pour masquer le widget natif (sans casser les listeners)

```css
/* Masquer visuellement — PAS display:none (casserait les listeners) */
#gt_float_wrapper, .gt_switcher_wrapper {
    position: fixed !important;
    top: -9999px !important;
    left: -9999px !important;
    visibility: hidden !important;
    pointer-events: none !important;
    z-index: -1 !important;
}
body { top: 0 !important; }
```

---

## 6. Responsive — 5 breakpoints mobiles + 5 grands écrans

### Fichier `responsive.css` — structure

```
Section 1  : Variables CSS responsive
Section 2  : Navigation (burger à 768px)
Section 3  : Hero dark
Section 4  : Layout général
Section 5  : Cards
Section 6  : Versets bibliques
Section 7  : Sidebar (grille 2 col à 1024px)
Section 8  : Boutons
Section 9  : Footer
Section 10 : Typographie fluide avec clamp()
Section 11 : Sélecteur de langue
Section 12 : Utilitaires (.schilo-hide-mobile, etc.)
Section 13 : Touch & accessibilité (min 44px tap)
Section 14 : Impression
Section 15 : Safe area (encoches iPhone / Android)
Section 16 : Grands écrans 1400px → 4K
```

### Breakpoints mobiles

```css
@media (max-width: 1280px) { /* grand laptop */ }
@media (max-width: 1024px) { /* tablette paysage */ }
@media (max-width: 768px)  { /* tablette portrait — burger menu */ }
@media (max-width: 600px)  { /* grand téléphone */ }
@media (max-width: 480px)  { /* petit téléphone */ }
@media (max-width: 360px)  { /* très petit écran */ }
```

### Breakpoints grands écrans

```css
@media (min-width: 1400px) { --schilo-container-w: 88%; font-size: 15.5px; }
@media (min-width: 1600px) { --schilo-container-w: 85%; --schilo-nav-h: 60px; }
@media (min-width: 1920px) { --schilo-container-w: 80%; --schilo-nav-h: 64px; }
@media (min-width: 2560px) { --schilo-container-w: 72%; }
@media (min-width: 3840px) { --schilo-container-w: 65%; html { font-size: 20px; } }
```

### Règles typographiques fluides

```css
h1 { font-size: clamp(20px, 5vw, 38px); }
h2 { font-size: clamp(17px, 4vw, 26px); }
/* Jamais de taille fixe sur les titres — toujours clamp() */
```

---

## 7. Compatibilité navigateurs — `compat.css`

Préfixes vendeurs obligatoires sur ces propriétés :

```css
/* Flexbox */
display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
-webkit-flex-direction: column; -ms-flex-direction: column; flex-direction: column;
-webkit-align-items: center; -ms-flex-align: center; align-items: center;

/* Transform */
-webkit-transform: translateY(-4px);
-moz-transform: translateY(-4px);
-ms-transform: translateY(-4px);
-o-transform: translateY(-4px);
transform: translateY(-4px);

/* Transition */
-webkit-transition: all 0.15s ease;
-moz-transition: all 0.15s ease;
-o-transition: all 0.15s ease;
transition: all 0.15s ease;

/* Position sticky */
position: -webkit-sticky;
position: sticky;
```

### Polyfills JS installés par `Schilo.Polyfills`

- `Element.closest()` — IE11, anciens Android
- `Element.matches()` — IE11
- `NodeList.forEach()` — IE11, Edge ≤ 15
- `Array.find()`, `Array.from()` — IE11
- `Object.assign()`, `Object.keys()` — IE11/IE8
- `String.includes()`, `String.startsWith()`, `String.endsWith()` — IE11
- `CustomEvent` — IE11
- `PointerEvent` — anciens Safari/Firefox
- `requestAnimationFrame` — anciens navigateurs
- `IntersectionObserver` — Safari < 12.1
- `dataset` — IE10
- `classList.toggle` 2ème argument — IE11
- Smooth scroll — Safari < 15.4
- `focus-visible` léger — tous

---

## 8. Templates de pages

### Créer un nouveau template

```php
<?php
/**
 * Template Name: Mon Template
 * Description: Description courte
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<!-- HERO -->
<div class="schilo-hero">
  <div class="schilo-hero__inner">
    <div class="schilo-hero__eyebrow">
      <i class="ti ti-icon" aria-hidden="true"></i>
      <?php esc_html_e( 'Catégorie', 'schilo' ); ?>
    </div>
    <h1 class="schilo-hero__title schilo-serif">
      <?php esc_html_e( 'Titre de la page', 'schilo' ); ?>
    </h1>
    <p class="schilo-hero__desc">
      <?php esc_html_e( 'Description.', 'schilo' ); ?>
    </p>
  </div>
</div>

<main id="schilo-main" role="main">
  <div class="schilo-container" style="padding-top:1.5rem;padding-bottom:4rem">
    <div class="schilo-grid-main">

      <div>
        <!-- Contenu principal -->
        <div class="schilo-card">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-icon" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title">Section</span>
            </div>
          </div>
          <div class="schilo-card__body">
            <!-- contenu -->
          </div>
        </div>
      </div>

      <aside class="schilo-sidebar">
        <!-- Sidebar avec .schilo-sb-card -->
      </aside>

    </div>
  </div>
</main>

<?php get_footer(); ?>
```

### Règles de sécurité dans les templates

- Toujours `esc_html_e()` pour le texte traduit
- Toujours `esc_url()` pour les URLs
- Toujours `esc_attr()` pour les attributs HTML
- Toujours `esc_textarea()` pour les valeurs textarea
- Toujours `sanitize_text_field()` pour les `$_POST`
- Toujours `wp_verify_nonce()` avant traitement d'un formulaire
- **Apostrophes dans `esc_html_e()`** : toujours échapper avec `\'`
  - ❌ `esc_html_e( 'C'est un exemple', 'schilo' )`
  - ✅ `esc_html_e( 'C\'est un exemple', 'schilo' )`

---

## 9. Formulaire de contact — `page-contact.php`

### Traitement PHP sécurisé

```php
// Vérification nonce
if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['schilo_contact_nonce'] ) ), 'schilo_contact' ) ) { /* erreur */ }

// Honeypot anti-spam
if ( ! empty( $_POST['website'] ) ) { $form_sent = true; return; }

// Sanitisation
$prenom  = sanitize_text_field( wp_unslash( $_POST['schilo_prenom']  ?? '' ) );
$email   = sanitize_email( wp_unslash( $_POST['schilo_email'] ?? '' ) );
$message = sanitize_textarea_field( wp_unslash( $_POST['schilo_message'] ?? '' ) );

// Envoi avec Reply-To
$headers = [ 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $prenom . ' <' . $email . '>' ];
wp_mail( get_option('admin_email'), $subject, $body, $headers );
```

### Guide dynamique (Schilo.Contact)

Les guides sont définis en PHP (`$guides` array) et passés en JSON au JS :
```php
window.schiloContactGuides = <?php echo wp_json_encode( $guides ); ?>;
```

Format d'un guide :
```php
'question-biblique' => [
    'conseil'   => 'Précisez le livre, le chapitre et le verset...',
    'questions' => [
        'Que signifie exactement [TERME] dans son contexte ?',
        'Quelle est la différence entre [ÉVANGILE A] et [ÉVANGILE B] ?',
    ],
],
```

---

## 10. Relation avec Schilo Builder

Le thème détecte Schilo Builder via `function_exists()` et lui délègue le rendu :

```php
// front-page.php
if ( function_exists( 'schilo_builder_render_home' ) ) {
    schilo_builder_render_home();
} else {
    // fallback : contenu WP standard
}

// single.php
if ( function_exists( 'schilo_builder_render_single' ) ) {
    schilo_builder_render_single( get_the_ID() );
} else {
    // fallback : article WP standard
}
```

Le thème **ne doit pas** dupliquer la logique métier de Schilo Builder.

### Types de contenus Schilo Builder

Les fiches d'étude utilisent des codes préfixés :
- `PER001` — Parcours (fiches ordonnées dans un parcours)
- Les badges PER sont affichés en `schilo-ev-chip--luc` (couleur de l'Évangile)

---

## 11. Pièges courants et solutions

| Piège | Symptôme | Solution |
|-------|----------|----------|
| `SCHILO_DIR` avant `define()` | Fatal error: Undefined constant | Mettre les `define()` AVANT `require_once` |
| Apostrophe non échappée en PHP | Parse error: syntax error | Utiliser `\'` dans toutes les chaînes `esc_html_e()` |
| `const`/`let` en JS | Erreur IE11/anciens navigateurs | Utiliser `var` uniquement |
| Arrow functions `=>` en JS | Erreur IE11 | Utiliser `function()` |
| `display:none` sur les liens GTranslate | Traduction ne fonctionne pas | Masquer avec `visibility:hidden` + `top:-9999px` |
| CSS `.schilo-*` non appliqué | Conflit avec WP/plugin | Utiliser `style=""` inline en dernier recours |
| `get_page_by_path('contact')` échoue | Mauvais slug | Détecter par `_wp_page_template` en priorité |
| Grille CSS non affichée | Plugin CSS override | Forcer `display:grid` en inline style |
| Sidebar disparaît | CSS conflit `.schilo-grid-main` | Vérifier `grid-template-columns` en DevTools |
| Bouton contact 404 | Page sans template assigné | Assigner "Contact" dans Attributs de page |

---

## 12. Checklist avant livraison

### PHP
- [ ] `defined( 'ABSPATH' ) || exit;` en première ligne de chaque fichier PHP
- [ ] Toutes les sorties HTML passent par `esc_html_e()`, `esc_url()`, `esc_attr()`
- [ ] Apostrophes échappées dans toutes les chaînes PHP : `\'`
- [ ] Nonce vérifié avant tout traitement POST
- [ ] `SCHILO_DIR` défini avant les `require_once`

### CSS
- [ ] Variables `--schilo-*` utilisées (pas de valeurs hardcodées)
- [ ] Préfixes vendeurs dans `compat.css` pour les nouvelles propriétés
- [ ] Responsive testé à 360px, 768px, 1024px, 1920px
- [ ] Code couleur Évangiles non modifié

### JS
- [ ] Uniquement `var` (pas `const`/`let`)
- [ ] Pas d'arrow functions
- [ ] Nouveau module dans le namespace `Schilo`
- [ ] `init()` auto-déclenché après `DOMContentLoaded`
- [ ] Polyfills existants suffisants (sinon ajouter dans `Schilo.Polyfills`)

### Sécurité
- [ ] `Schilo_Security::init()` actif
- [ ] Headers HTTP présents (vérifier avec curl -I)
- [ ] XML-RPC désactivé
- [ ] Uploads filtrés (pas de .php/.js uploadables)

### Performance
- [ ] Google Fonts et Tabler Icons chargés depuis CDN
- [ ] `schilo-polyfills.js` dans le `<head>` (pas en footer)
- [ ] `schilo-contact.js` chargé uniquement sur `page-contact.php`

---

## 13. Gestion des erreurs

### 13.1 Erreurs PHP — catégories et traitements

#### Erreurs fatales WordPress

```php
// ❌ FATAL — constante utilisée avant definition
require_once SCHILO_DIR . '/template-parts/nav-walker.php'; // SCHILO_DIR pas encore défini
define( 'SCHILO_DIR', get_template_directory() );

// ✅ CORRECT — define() TOUJOURS avant require_once
define( 'SCHILO_DIR', get_template_directory() );
require_once SCHILO_DIR . '/template-parts/nav-walker.php';
```

#### Parse errors — apostrophes non échappées

```php
// ❌ Parse error: syntax error, unexpected identifier "étude"
esc_html_e( 'Espace d'étude', 'schilo' );

// ✅ Toujours échapper avec \'
esc_html_e( 'Espace d\'étude', 'schilo' );

// ✅ Ou utiliser des guillemets doubles pour la chaîne externe
echo esc_html__( "Espace d'étude", 'schilo' );
```

**Règle** : avant tout livraison d'un template PHP, scanner tous les
`esc_html_e(`, `__()`, `_e()` à la recherche d'apostrophes non échappées.

#### Erreurs de formulaire — validation côté serveur

```php
// Pattern complet de gestion d'erreur formulaire
$form_sent  = false;
$form_error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['schilo_contact_nonce'] ) ) {

    // 1. Vérification nonce (TOUJOURS EN PREMIER)
    if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['schilo_contact_nonce'] ) ),
            'schilo_contact'
        ) ) {
        $form_error = __( 'Erreur de sécurité. Veuillez recharger la page.', 'schilo' );

    // 2. Honeypot anti-spam
    } elseif ( ! empty( $_POST['website'] ) ) {
        $form_sent = true; // Simuler le succès sans rien faire

    // 3. Validation métier
    } elseif ( empty( sanitize_text_field( wp_unslash( $_POST['schilo_prenom'] ?? '' ) ) ) ) {
        $form_error = __( 'Veuillez indiquer votre prénom.', 'schilo' );

    } elseif ( ! is_email( wp_unslash( $_POST['schilo_email'] ?? '' ) ) ) {
        $form_error = __( "L'adresse e-mail est invalide.", 'schilo' );

    } elseif ( strlen( sanitize_textarea_field( wp_unslash( $_POST['schilo_message'] ?? '' ) ) ) < 10 ) {
        $form_error = __( 'Le message est trop court (10 caractères minimum).', 'schilo' );

    } else {
        // 4. Traitement — wp_mail() peut échouer
        if ( wp_mail( $to, $subject, $body, $headers ) ) {
            $form_sent = true;
        } else {
            $form_error = __( "Une erreur est survenue lors de l'envoi. Veuillez réessayer.", 'schilo' );
        }
    }
}

// Affichage de l'erreur en template
if ( $form_error ) : ?>
<div class="schilo-contact-error" role="alert">
    <i class="ti ti-alert-circle" aria-hidden="true"></i>
    <?php echo esc_html( $form_error ); ?>
</div>
<?php endif;
```

#### Erreurs 404 — page non trouvée

```php
// Vérifier l'existence d'une page avant get_permalink()
$contact_page = null;
$by_tpl = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );

if ( ! empty( $by_tpl ) ) {
    $contact_page = $by_tpl[0];
} else {
    // Fallback multi-slug
    foreach ( [ 'contactez-nous', 'contact', 'nous-contacter' ] as $slug ) {
        $p = get_page_by_path( $slug );
        if ( $p ) { $contact_page = $p; break; }
    }
}

// Ne jamais appeler get_permalink() sur null
$contact_url = $contact_page
    ? get_permalink( $contact_page->ID )
    : home_url( '/contactez-nous/' ); // fallback URL codée en dur
```

#### Erreurs d'upload fichiers

```php
// Dans Schilo_Security::sanitize_uploads()
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
    // Si extension non reconnue — retourner false pour bloquer
    if ( ! empty( $data['ext'] ) ) return $data;

    // Vérification MIME réel (pas seulement l'extension)
    $filetype = wp_check_filetype( $filename );
    if ( ! $filetype['type'] ) {
        // Bloquer le fichier
        return [ 'ext' => false, 'type' => false, 'proper_filename' => false ];
    }
    return $data;
}, 10, 3 );
```

---

### 13.2 Erreurs JavaScript — détection et récupération

#### Pattern de sécurité dans les modules Schilo

```javascript
// Toujours vérifier l'existence des éléments avant de les utiliser
function init() {
    var wrapper = document.getElementById('schilo-lang-selector');
    if (!wrapper) return; // sortie silencieuse si absent de la page

    var btn = document.getElementById('schilo-lang-toggle');
    if (!btn || !wrapper.contains(btn)) {
        // Re-construire le sélecteur si le DOM est incomplet
        _buildSelector();
        return;
    }
    _bindEvents();
}
```

#### Gestion des erreurs AJAX (Schilo.MarkRead)

```javascript
// Dans Schilo.MarkRead — AJAX avec récupération d'erreur
if (window.schiloData && window.schiloData.ajaxUrl) {
    fetch(window.schiloData.ajaxUrl, {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : 'action=schilo_mark_read&post_id=' + postId + '&nonce=' + window.schiloData.nonce
    })
    .then(function (response) {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            console.warn('[Schilo.MarkRead] Serveur a rejeté la requête :', data);
            // Annuler le changement visuel si le serveur échoue
            _revertMarkRead(btn, postId);
        }
    })
    .catch(function (err) {
        console.warn('[Schilo.MarkRead] Erreur AJAX :', err);
        // Toujours annuler l'état visuel en cas d'erreur réseau
        _revertMarkRead(btn, postId);
    });
}
```

#### Gestion des erreurs GTranslate (Schilo.Lang)

```javascript
// Fallback en cascade si le lien natif GTranslate est introuvable
function _triggerLang(code) {
    if (code === _config.defaultLang) {
        _clearCookies();
        location.reload();
        return;
    }

    // Tentative 1 : lien natif GTranslate
    var link = document.querySelector('a[data-gt-lang="' + code + '"]');
    if (link) {
        try {
            ['pointerover','pointerenter','mouseover','mouseenter'].forEach(function(ev) {
                link.dispatchEvent(new MouseEvent(ev, { bubbles: true, cancelable: true }));
            });
            setTimeout(function () {
                link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
            }, 50);
            return;
        } catch (e) {
            console.warn('[Schilo.Lang] Erreur clic natif :', e);
            // Continuer vers le fallback
        }
    }

    // Tentative 2 : cookie + reload (fallback absolu)
    console.warn('[Schilo.Lang] Lien data-gt-lang="' + code + '" non trouvé — fallback cookie');
    var val  = '/' + _config.defaultLang + '/' + code;
    var host = location.hostname;
    document.cookie = 'googtrans=' + val + '; path=/';
    document.cookie = 'googtrans=' + val + '; path=/; domain=.' + host;
    location.reload();
}
```

#### Erreurs de polyfills (Schilo.Polyfills)

```javascript
// Chaque installation est protégée par try/catch
function installDataset() {
    try {
        if ('dataset' in document.createElement('div')) return;
        Object.defineProperty(HTMLElement.prototype, 'dataset', {
            get: function () { /* ... */ }
        });
    } catch (e) {
        // IE8 — Object.defineProperty non supporté sur les éléments DOM
        // Ignorer silencieusement — dataset sera inaccessible
        console.warn('[Schilo.Polyfills] dataset non installable :', e);
    }
}
```

---

### 13.3 Erreurs CSS — diagnostics et solutions

#### Détection des conflits CSS

```css
/* Outil de diagnostic — ajouter temporairement dans style.css */
.debug-layout * {
    outline: 1px solid rgba(255, 0, 0, 0.3) !important;
}

/* Repérer un élément qui déborde */
.debug-overflow {
    overflow: visible !important;
    background: rgba(255, 200, 0, 0.2) !important;
}
```

#### Ordre de spécificité à respecter

```
1. compat.css          — préfixes, normalize, fallbacks IE
2. style.css           — design system, composants
3. responsive.css      — breakpoints
4. styles inline HTML  — dernier recours (forcer sur un élément)
```

**Règle** : si un style CSS ne s'applique pas malgré la classe correcte,
diagnostiquer dans DevTools → Styles → chercher la règle barrée.
Solutions dans l'ordre de préférence :

1. Augmenter la spécificité du sélecteur (`.schilo-card .schilo-verse__text`)
2. Ajouter `!important` dans `style.css`
3. Ajouter un style inline sur l'élément HTML (template PHP)

#### Grid non rendu — pièges fréquents

```css
/* ❌ Grid ignoré si le parent est flex sans flex-wrap */
.parent { display: flex; }
.child  { display: grid; grid-template-columns: repeat(3, 1fr); } /* ignoré */

/* ✅ Le parent doit être block ou le grid doit être direct enfant d'un block */
.parent { display: block; }
.child  { display: grid; grid-template-columns: repeat(3, 1fr); }

/* ✅ Ou forcer en inline style pour contourner les conflits WP */
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem">
```

---

### 13.4 Plan de tests

#### Tests PHP — à exécuter après chaque modification de template

```bash
# 1. Validation syntaxe PHP (si PHP disponible en local)
php -l wp-content/themes/schilo-theme/page-contact.php
php -l wp-content/themes/schilo-theme/page-apropos.php
php -l wp-content/themes/schilo-theme/functions.php

# 2. Si PHP non disponible — scanner les apostrophes manuellement
grep -n "esc_html_e\|__(\|_e(" fichier.php | grep "[^\\]'"
```

#### Tests JS — console navigateur

```javascript
// Ouvrir DevTools → Console, puis tester chaque module
Schilo.Nav.init();       // aucune erreur attendue
Schilo.Lang.getCurrentLang(); // doit retourner 'fr' ou la langue active

// Tester les polyfills
console.log(typeof Element.prototype.closest);   // 'function'
console.log(typeof NodeList.prototype.forEach);  // 'function'
console.log(typeof Array.prototype.find);        // 'function'

// Vérifier que le namespace est complet
console.log(Object.keys(Schilo));
// ['Polyfills', 'App', 'Nav', 'Search', 'Anchors', 'Verses', 'MarkRead', 'Date', 'Lang']
```

#### Tests CSS — DevTools responsive

| Taille | Vérifier |
|--------|----------|
| 360px  | Nav burger, textes non tronqués, pas de scroll horizontal |
| 480px  | Grilles en 1 col, footer en 1 col |
| 768px  | Sidebar sous le contenu, menu burger |
| 1024px | Sidebar en 2 col, nav complète |
| 1280px | Layout standard |
| 1920px | Container à 80%, typo 16.5px |
| 2560px | Container à 72%, lignes de texte ≤ 68ch |

#### Tests formulaire contact

```
✅ Envoi vide → erreur "Prénom requis"
✅ Email invalide → erreur "E-mail invalide"
✅ Message < 10 car → erreur "Message trop court"
✅ Honeypot rempli → succès simulé (anti-spam)
✅ Nonce expiré → erreur "Erreur de sécurité"
✅ Envoi valide → message de succès + email reçu
✅ Repopulation des champs après erreur
✅ Guide dynamique selon le sujet sélectionné
✅ Questions préétablies cliquables → textarea rempli
```

#### Tests GTranslate

```
✅ Sélecteur FR visible dans la nav
✅ Dropdown s'ouvre au clic
✅ Sélection EN → page traduite en anglais
✅ Retour FR → cookies effacés + page rechargée en français
✅ Drapeau actif correspond à la langue du cookie googtrans
✅ Widget natif GTranslate invisible (top:-9999px)
✅ Barre Google Translate masquée (pas de décalage du body)
```

#### Tests sécurité — headers HTTP

```bash
# Vérifier les headers de sécurité
curl -I https://schilo.org/ 2>/dev/null | grep -i "x-frame\|x-content\|x-xss\|referrer\|permissions"

# Résultat attendu :
# X-Frame-Options: SAMEORIGIN
# X-Content-Type-Options: nosniff
# X-XSS-Protection: 1; mode=block
# Referrer-Policy: strict-origin-when-cross-origin
# Permissions-Policy: geolocation=(), microphone=(), camera=()

# Vérifier que XML-RPC est désactivé
curl -s -o /dev/null -w "%{http_code}" https://schilo.org/xmlrpc.php
# Doit retourner 403 ou 405
```

#### Tests de régression — checklist après mise à jour thème

```
□ Page d'accueil charge sans erreur WP_DEBUG
□ Page Contact — formulaire fonctionnel
□ Page À propos — grille valeurs 3 col, timeline 4 col
□ Sélecteur de langue — drapeau et traduction OK
□ Bouton Contact dans nav — pointe vers /contactez-nous/
□ Footer — lien "Contactez-nous" avec icône
□ Responsive 768px — menu burger fonctionnel
□ Console JS — aucune erreur (0 erreurs, warnings acceptés)
□ WP_DEBUG — aucune notice PHP
```

---

### 13.5 Activation du mode debug

```php
// Dans wp-config.php (développement uniquement — jamais en production)
define( 'WP_DEBUG',         true  );
define( 'WP_DEBUG_LOG',     true  );  // log dans /wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );  // ne pas afficher dans le navigateur
define( 'SCRIPT_DEBUG',     true  );  // charge les versions non-minifiées

// Lire le log
tail -f wp-content/debug.log
```

```javascript
// Mode debug JS — ajouter dans schilo.js au besoin
var SCHILO_DEBUG = (window.schiloData && window.schiloData.debug) || false;

function _log() {
    if (SCHILO_DEBUG) {
        console.log.apply(console, ['[Schilo]'].concat(Array.prototype.slice.call(arguments)));
    }
}
// Usage : _log('Nav initialized', _toggle);
```

```php
// Passer le flag debug au JS via wp_localize_script
wp_localize_script( 'schilo-main', 'schiloData', [
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'schilo_nonce' ),
    'homeUrl' => home_url( '/' ),
    'version' => SCHILO_VERSION,
    'debug'   => defined('WP_DEBUG') && WP_DEBUG, // true/false
] );
```
