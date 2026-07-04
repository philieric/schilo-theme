---
name: project-plugins
description: "Plugins installés, leurs rôles, et ceux à désinstaller (WPBakery, Divi, Wikilogy)"
metadata: 
  node_type: memory
  type: project
  originSessionId: ec125d47-0880-4756-a67c-71be9028a52b
---

## Plugins à DÉSINSTALLER (décision 2026-06-27)

Ces plugins seront désactivés et désinstallés. Ne pas écrire de code qui en dépend :

- **WPBakery Page Builder** (`js_composer`, `divi-builder`) — page builder, causa un SyntaxError via `wpb_js_custom_js_header` contenant un tag `<script>` HTML qui était double-enveloppé
- **Wikilogy child theme** (`wikilogy-child`) — ancien thème, contient le script Ahrefs analytics dans `header.php`
- **Divi Builder** (`divi-builder`) — page builder alternatif

**Why:** Migration vers thème OOP custom `schilo-theme`. Ces outils créaient des conflits JS et des dépendances non souhaitées.

**How to apply:** Si une erreur JS ou CSS semble inexpliquée, vérifier si elle vient d'un de ces plugins via l'inspection de `wp_head()` output. Après désinstallation, les erreurs WPBakery/Divi disparaîtront.

## Plugins actifs principaux

| Plugin | Rôle |
|---|---|
| `sitemap-par-categorie` | Plan du site HTML (shortcode `[sitemap_par_categorie]`) — intégré dans `page-sitemap.php` |
| `popup-shortcode` | Popups via shortcode `[popup_post id="X"]` — script.js doit avoir des null checks |
| `contact-form-7` | Formulaire de contact |
| `gtranslate` | Sélecteur de langue |
| `easy-table-of-contents` | Tables des matières |
| `https-redirection` | Redirige HTTP → HTTPS |
| `wp-super-cache` | Cache WordPress |
| `sitemap-par-categorie` | Sitemap HTML par catégorie |
| `google-sitemap-generator` | Sitemap XML |

## Bug connu : popup-shortcode/script.js

`script.js` est chargé sur TOUTES les pages mais accède à `#popup-close`, `#popup-overlay`, `#popup-content` qui n'existent que si le shortcode `[popup_post]` est utilisé sur la page.

**Erreur** : `script.js:38 Uncaught TypeError: Cannot read properties of null (reading 'addEventListener')`

**Fix à faire** : Ajouter des null checks dans `popup-shortcode/script.js` :
```js
if (popupClose) popupClose.addEventListener('click', ...);
if (popupOverlay) popupOverlay.addEventListener('click', ...);
```
