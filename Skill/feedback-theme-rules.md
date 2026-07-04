---
name: feedback-theme-rules
description: "Règles de développement du thème schilo — CSS/JS, intégration plugins, cache busting, structure OOP"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ec125d47-0880-4756-a67c-71be9028a52b
---

## Règle 1 : CSS/JS toujours via functions.php, jamais inline dans les templates

Ne jamais mettre `<style>` ou `<script>` directement dans le body d'un template PHP.

Utiliser dans `functions.php` :
```php
wp_add_inline_style( 'schilo-main', '/* CSS */' );
wp_add_inline_script( 'schilo-main', '/* JS */', 'after' );
```

Pour conditionner à un template :
```php
if ( is_page_template( 'page-XXX.php' ) ) {
    wp_add_inline_style( ... );
    wp_add_inline_script( ... );
}
```

**Why:** Les tags inline dans le body causent des `Unexpected token '<'` SyntaxError en JS. WordPress a des hooks dédiés pour ça.

## Règle 2 : Ne pas toucher la logique des plugins

Intégrer les plugins via shortcode (`do_shortcode()`), pas en copiant leur code. Remappe uniquement leurs CSS variables pour correspondre à la charte schilo.

**Why:** Maintenabilité — les mises à jour de plugins ne cassent pas les customisations.

## Règle 3 : Cache busting avec clearstatcache()

Au début de `schilo_enqueue_assets()`, appeler `clearstatcache()` pour que `filemtime()` retourne des valeurs fraîches.

**Why:** PHP cache les résultats de `stat()` (realpath_cache), ce qui peut faire retourner des versions obsolètes des assets.

## Règle 4 : Remappage des variables CSS du plugin sitemap

Le plugin `sitemap-par-categorie` utilise des variables `--spc-*` avec une couleur violette par défaut (`#56548C`). Les remappe dans `functions.php` sur les tokens schilo (bleu `#2872d4`).

```css
:root {
    --spc-accent: #2872d4;
    --spc-accent-dark: #0e3f88;
    /* ... voir functions.php pour la liste complète */
}
```

## Règle 5 : Accordéon sitemap — CSS !important

Le plugin sitemap utilise la classe `.open` pour afficher/masquer les nœuds. La carte `.schilo-card` a `transition: all` et `overflow: hidden` qui bloquent la fermeture. Désactiver ces propriétés pour la page sitemap :

```css
.schilo-sitemap-page .schilo-card {
    transition: none !important;
    overflow: visible;
}
.sitemap-categories .spc-node > .spc-node-body { display: none !important; }
.sitemap-categories .spc-node.open > .spc-node-body { display: block !important; }
```

## Règle 6 : Handles WordPress

- CSS handle : `schilo-main` → `style.css`
- JS handle : `schilo-main` → `schilo.js` (footer)
- Polyfills : `schilo-polyfills` → head
- Lang : `schilo-lang` → footer
- Contact : `schilo-contact` → footer, conditionnel `page-contact.php`
