---
name: project-pages
description: "Pages spéciales du thème avec leurs templates PHP, auto-détection dans header/footer, et structure HTML attendue"
metadata: 
  node_type: memory
  type: project
  originSessionId: ec125d47-0880-4756-a67c-71be9028a52b
---

## Pages spéciales (templates)

Chaque page utilise un template PHP avec `Template Name:` en header. Assigné via `_wp_page_template` dans WP Admin.

| Template | Nom WP | Slugs fallback |
|---|---|---|
| `page-apropos.php` | "À propos" | `a-propos`, `apropos`, `about` |
| `page-avancements.php` | "Avancements" | `avancements`, `derniers-contenus`, `nouveautes` |
| `page-contact.php` | "Contact" | `contactez-nous`, `contact`, `nous-contacter` |
| `page-sitemap.php` | "Plan du site" | `plan-du-site`, `sitemap`, `plan-site` |

## Auto-détection dans header.php et footer.php

Pattern utilisé partout (à reproduire pour toute nouvelle page) :

```php
$url    = home_url( '/slug-fallback/' );
$active = false;
$by_tpl = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-XXX.php' ] );
if ( ! empty( $by_tpl ) ) {
    $url    = get_permalink( $by_tpl[0]->ID );
    $active = is_page( $by_tpl[0]->ID );
} else {
    foreach ( [ 'slug1', 'slug2' ] as $slug ) {
        $p = get_page_by_path( $slug );
        if ( $p ) { $url = get_permalink( $p->ID ); $active = is_page( $p->ID ); break; }
    }
}
```

Bouton dans la nav (header.php) :
```php
<a href="<?php echo esc_url( $url ); ?>"
   class="schilo-btn-contact<?php echo $active ? ' active' : ''; ?>"
   aria-label="<?php esc_attr_e( 'Label', 'schilo' ); ?>">
  <i class="ti ti-ICON" aria-hidden="true"></i>
</a>
```

Lien dans le footer (footer.php, colonne "Site") :
```php
<a href="<?php echo esc_url( $url ); ?>">
  <?php esc_html_e( 'Label', 'schilo' ); ?>
</a>
```

## Structure HTML d'un template de page

```php
<?php /* Template Name: NOM */ defined('ABSPATH') || exit; get_header(); ?>

<div class="schilo-hero">
  <div class="schilo-hero__inner">
    <div class="schilo-hero__eyebrow"><i class="ti ti-ICON"></i> LABEL</div>
    <h1 class="schilo-hero__title schilo-serif">TITRE</h1>
    <p class="schilo-hero__desc">DESCRIPTION</p>
  </div>
</div>

<main id="schilo-main" role="main">
<div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">
  <div class="schilo-card" style="margin-bottom:1.25rem">
    <div class="schilo-card__head">
      <div class="schilo-card__head-left">
        <div class="schilo-card__icon schilo-card__icon--dark"><i class="ti ti-ICON"></i></div>
        <span class="schilo-card__title">TITRE CARTE</span>
      </div>
    </div>
    <div class="schilo-card__body">CONTENU</div>
  </div>
</div>
</main>

<?php get_footer(); ?>
```

## page-sitemap.php — Intégration plugin

Intègre le plugin `sitemap-par-categorie` via shortcode, sans toucher la logique du plugin.

```php
<?php echo do_shortcode( '[sitemap_par_categorie]' ); ?>
```

Le CSS/JS du plugin est remappé dans `functions.php` via `wp_add_inline_style`/`wp_add_inline_script` conditionnels sur `is_page_template('page-sitemap.php')` — jamais en inline dans le template.

**Why:** Respecte le principe OOP du thème et évite les SyntaxError JS causés par les balises inline dans le body.
