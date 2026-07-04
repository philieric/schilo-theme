---
name: project-overview
description: "Architecture du thème schilo, URL locale, structure des fichiers clés et configuration Apache"
metadata: 
  node_type: memory
  type: project
  originSessionId: ec125d47-0880-4756-a67c-71be9028a52b
---

## Site Schilo

Site d'étude biblique (4 Évangiles). Thème WordPress OOP custom nommé **schilo-theme**.

**URL locale** : `http://schilo.local/` (virtual host Apache)
**Fichiers** : `C:\Apache24\htdocs\schilo\`
**Config Apache** : `C:\Apache24\conf\extra\httpd-vhosts.conf`
- `localhost` → `C:\Apache24\htdocs` (DocumentRoot racine)
- `schilo.local` → `C:\Apache24\htdocs\schilo` (le site WordPress)

**Why:** Ne pas fetcher `http://localhost/schilo/...` — ça retourne une page phpinfo(). Toujours utiliser `http://schilo.local/`.

**How to apply:** Pour déboguer des pages, utiliser `Invoke-WebRequest -Uri "http://schilo.local/PAGE/"`.

## Fichiers clés du thème

```
schilo-theme/
├── functions.php          — Enqueue assets, hooks, classes WP
├── style.css              — CSS principal (handle: schilo-main)
├── header.php             — Nav avec boutons auto-détectés
├── footer.php             — Footer avec liens auto-détectés
├── page-apropos.php       — Template "À propos"
├── page-avancements.php   — Template "Derniers contenus"
├── page-contact.php       — Template "Contact" + CF7
├── page-sitemap.php       — Template "Plan du site" (shortcode plugin)
├── assets/
│   ├── css/compat.css, responsive.css
│   └── js/schilo.js, schilo-polyfills.js, schilo-lang.js, schilo-contact.js
```

## Design tokens principaux

- Accent bleu : `#2872d4`
- Évangiles : Matthieu `#e05a2b`, Marc `#2e9e4f`, Luc `#2872d4`, Jean `#7c4db8`
- Variables CSS : `--schilo-accent`, `--schilo-bg-dark`, `--schilo-text-primary`, etc.
- Classes UI : `.schilo-hero`, `.schilo-card`, `.schilo-card__head`, `.schilo-card__body`, `.schilo-container`
