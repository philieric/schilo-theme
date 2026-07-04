---
name: feedback-js-errors
description: "Erreurs JS connues sur le site schilo, leurs causes diagnostiquées et les fixes à appliquer"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ec125d47-0880-4756-a67c-71be9028a52b
---

## Erreur 1 : SyntaxError "Unexpected token '<'" — WPBakery (résolu lors désinstall)

**Source** : `plan-du-site/:977 Uncaught SyntaxError: Unexpected token '<'`

**Cause** : WPBakery Page Builder stockait dans l'option DB `wpb_js_custom_js_header` le tag HTML complet :
```html
<script src="https://analytics.ahrefs.com/analytics.js" data-key="1CD0Ct4I2mvo40F8PwmKxA" async></script>
```
WPBakery enveloppait ce contenu dans un autre `<script>` en output, causant `<script><script src="..."...>`. Le navigateur essaie d'exécuter le tag HTML `<script src="...">` comme du JS.

**Fix** : Désinstallation de WPBakery (prévu). Si script Ahrefs nécessaire, l'ajouter via hook `wp_head` dans functions.php :
```php
add_action('wp_head', function() {
    echo '<script src="https://analytics.ahrefs.com/analytics.js" data-key="1CD0Ct4I2mvo40F8PwmKxA" async></script>' . "\n";
});
```

**Why:** Le champ "Custom JS Header" de WPBakery attend du code JS pur, pas des tags HTML. Stocker `<script src="">` dans ce champ = double enveloppement.

## Erreur 2 : TypeError null.addEventListener — popup-shortcode (à corriger)

**Source** : `script.js:38 Uncaught TypeError: Cannot read properties of null (reading 'addEventListener')`

**Cause** : `popup-shortcode/script.js` charge sur toutes les pages et accède à `#popup-close` qui n'existe que si `[popup_post]` est utilisé.

**Fix à faire** : Modifier `C:\Apache24\htdocs\schilo\wp-content\plugins\popup-shortcode\script.js` :
```js
// Avant :
popupClose.addEventListener('click', function () { ... });
popupOverlay.addEventListener('click', function () { ... });

// Après :
if (popupClose) popupClose.addEventListener('click', function () { ... });
if (popupOverlay) popupOverlay.addEventListener('click', function () { ... });
```

## Erreur 3 : TypeError addEventListener — plugin sitemap (résolue lors désinstall WPBakery)

**Source** : `plan-du-site/:1364 Uncaught TypeError: Cannot read properties of null (reading 'addEventListener')`

**Cause** : La SyntaxError WPBakery à la ligne 977 bloquait l'exécution du JS du plugin sitemap. Après désinstallation WPBakery, cette erreur disparaît.

## Règle générale

Pour déboguer une SyntaxError "Unexpected token '<'" dans une page WordPress :
1. Fetcher `http://schilo.local/PAGE/` et chercher la ligne du numéro d'erreur
2. Vérifier si un `<script>` est imbriqué dans un autre `<script>`
3. Chercher quel plugin/option DB produit cet output avec `grep -rn "pattern" wp-content/`
4. Chercher dans la DB : `SELECT option_name FROM wp_options WHERE option_value LIKE '%pattern%'`
