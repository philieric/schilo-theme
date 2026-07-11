<?php
/**
 * Template : Article individuel (single.php)
 */
get_header();

if ( ! have_posts() ) { get_footer(); return; }
the_post();

$post_id = get_the_ID();

// ── Code PER + titre propre ───────────────────────────────────────────
$raw_title      = get_post_field( 'post_title', $post_id );
$per_code       = '';
$article_prefix = '';
$clean_title    = $raw_title;
if ( preg_match( '/^([A-Z]+)\d+\s*[\x{2013}\x{2014}\-]+\s*/u', $raw_title, $m ) ) {
    $article_prefix = $m[1];
}
if ( preg_match( '/^([A-Z]+\d+)\s*[\x{2013}\x{2014}\-]+\s*/u', $raw_title, $m ) ) {
    $per_code    = $m[1];
    $clean_title = preg_replace( '/^[A-Z]+\d+\s*[\x{2013}\x{2014}\-]+\s*/u', '', $raw_title );
}

// ── Catégories + image ────────────────────────────────────────────────
$cats        = get_the_category();
$primary_cat = ! empty( $cats ) ? $cats[0] : null;
$thumb_id    = get_post_thumbnail_id();
$thumb_src   = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'full' ) : null;

// ── Tags : mots-cles de l'indexation Schilo si l'article est valide, sinon tags WP ──
$tags         = get_the_tags();
$display_tags = [];
$indexed_row  = null;

if ( class_exists( '\Schilo\Builder\Service\IndexationService' ) ) {
    $service     = new \Schilo\Builder\Service\IndexationService();
    $indexed_row = $service->getByPostId( $post_id );
    if ( ! $indexed_row || ( $indexed_row['statut_indexation'] ?? '' ) !== 'valide' ) {
        $indexed_row = null;
    }
}

if ( $indexed_row && ! empty( $indexed_row['mots_cles'] ) ) {
    $mots_cles = json_decode( $indexed_row['mots_cles'], true );
    if ( is_array( $mots_cles ) ) {
        foreach ( $mots_cles as $mot ) {
            if ( ! is_string( $mot ) || $mot === '' ) continue;
            $display_tags[] = [ 'name' => $mot, 'url' => get_search_link( $mot ) ];
        }
    }
}

if ( empty( $display_tags ) && $tags ) {
    foreach ( $tags as $tag ) {
        $display_tags[] = [ 'name' => $tag->name, 'url' => get_tag_link( $tag ) ];
    }
}

// ── Sections brutes (pour nav + sidebar) ─────────────────────────────
$sections_raw = get_post_meta( $post_id, '_schilo_builder_sections', true );
$sections_raw = is_array( $sections_raw ) ? $sections_raw : [];

// Ordre d'affichage : suit le template du préfixe, exactement comme le rendu
// du contenu (ContentRenderer). Garantit que les onglets et les statistiques
// reflètent le même ordre que les sections affichées, même si l'ordre stocké
// en base diffère (ex : bloc « Détails » enregistré en fin d'article).
if ( ! empty( $sections_raw ) && class_exists( '\Schilo\Builder\Service\TemplateService' ) ) {
    $tpl_prefix = $article_prefix;
    if ( class_exists( '\Schilo\Builder\Service\ArticleTypeService' ) ) {
        $tpl_prefix = ( new \Schilo\Builder\Service\ArticleTypeService() )->resolveType( $post_id );
    }
    $sec_types = [];
    foreach ( $sections_raw as $s ) {
        $sec_types[] = isset( $s['type'] ) ? $s['type'] : '';
    }
    $order = ( new \Schilo\Builder\Service\TemplateService() )->orderIndexesByTemplate( $sec_types, $tpl_prefix );
    $ordered = [];
    foreach ( $order as $oi ) {
        if ( isset( $sections_raw[ $oi ] ) ) {
            $ordered[] = $sections_raw[ $oi ];
        }
    }
    if ( ! empty( $ordered ) ) {
        $sections_raw = $ordered;
    }
}

// ── Helpers : livre → classe CSS et label lisible ────────────────────
if ( ! function_exists( 'schilo_book_class' ) ) :
function schilo_book_class( string $abbr ): string {
    static $map = [
        // Matthieu — abréviations et nom complet
        'matt' => 'citation-matthieu', 'mt'   => 'citation-matthieu', 'mat'      => 'citation-matthieu',
        'matthieu' => 'citation-matthieu', 'matthew' => 'citation-matthieu',
        // Marc
        'mc'   => 'citation-marc',     'mr'   => 'citation-marc',     'mrk'      => 'citation-marc',
        'marc' => 'citation-marc',     'mark' => 'citation-marc',
        // Luc
        'lc'   => 'citation-luc',      'luc'  => 'citation-luc',      'lu'       => 'citation-luc',
        'luk'  => 'citation-luc',      'luke' => 'citation-luc',
        // Jean
        'jn'   => 'citation-jean',     'jean' => 'citation-jean',     'jo'       => 'citation-jean',
        'jhn'  => 'citation-jean',     'john' => 'citation-jean',
    ];
    return $map[ strtolower( $abbr ) ] ?? 'citation-bible';
}

function schilo_book_label( string $abbr, string $class ): string {
    static $names = [
        'citation-matthieu' => 'Matthieu',
        'citation-marc'     => 'Marc',
        'citation-luc'      => 'Luc',
        'citation-jean'     => 'Jean',
    ];
    return $names[ $class ] ?? ucfirst( $abbr );
}
endif;

// ── Statistiques extraites des sections ───────────────────────────────
$verset_count    = 0;
$question_count  = 0;
$evangile_items  = []; // label => ['label','class','count']

foreach ( $sections_raw as $sec ) {
    $type = isset( $sec['type'] ) ? $sec['type'] : '';

    // Versets explicites de la section évangiles
    if ( $type === 'evangiles' && isset( $sec['data']['versets'] ) ) {
        foreach ( $sec['data']['versets'] as $v ) {
            // Ignorer les entrées sans référence (= "non cité dans le livre")
            if ( empty( $v['reference'] ) ) continue;
            $verset_count++;
            $vlabel = isset( $v['label'] ) ? $v['label'] : '';
            $vclass = isset( $v['class'] ) ? $v['class'] : 'citation-bible';
            if ( $vlabel ) {
                if ( ! isset( $evangile_items[ $vlabel ] ) ) {
                    $evangile_items[ $vlabel ] = [ 'label' => $vlabel, 'class' => $vclass, 'count' => 0 ];
                }
                $evangile_items[ $vlabel ]['count']++;
            }
        }
    }

    // Références [bib]/[b] et [brc] dans les sections à contenu libre
    if ( ! empty( $sec['content'] ) ) {
        preg_match_all( '/\[b(?:ib)?\](.*?)\[\/b(?:ib)?\]/is', $sec['content'], $bib_matches );
        foreach ( $bib_matches[1] as $ref_str ) {
            $ref_str = trim( $ref_str );
            if ( $ref_str === '' ) continue;
            $parts    = preg_split( '/\s+/', $ref_str );
            $abbr     = $parts[0];
            $bclass   = schilo_book_class( $abbr );
            $blabel   = schilo_book_label( $abbr, $bclass );
            $verset_count++;
            if ( ! isset( $evangile_items[ $blabel ] ) ) {
                $evangile_items[ $blabel ] = [ 'label' => $blabel, 'class' => $bclass, 'count' => 0 ];
            }
            $evangile_items[ $blabel ]['count']++;
        }
        preg_match_all( '/\[brc\](.*?)\[\/brc\]/is', $sec['content'], $brc_matches );
        foreach ( $brc_matches[1] as $ref_str ) {
            $ref_str = trim( $ref_str );
            if ( $ref_str === '' ) continue;
            $parts = preg_split( '/\s+/', $ref_str );
            // "2 Timothée 3.16" → abbr = "2 Timothée", pas juste "2"
            $abbr = ( isset( $parts[1] ) && ctype_digit( $parts[0] ) )
                ? $parts[0] . ' ' . $parts[1]
                : $parts[0];
            $bclass   = schilo_book_class( $abbr );
            $blabel   = schilo_book_label( $abbr, $bclass );
            $verset_count++;
            if ( ! isset( $evangile_items[ $blabel ] ) ) {
                $evangile_items[ $blabel ] = [ 'label' => $blabel, 'class' => $bclass, 'count' => 0 ];
            }
            $evangile_items[ $blabel ]['count']++;
        }
    }

    if ( $type === 'questions' && isset( $sec['data']['questions'] ) ) {
        $question_count += count( $sec['data']['questions'] );
    }
}

// ── Onglets de navigation (dédupliqués, ordonnés) ─────────────────────
$tab_map = [
    'intro'                       => [ 'label' => 'Résumé',         'anchor' => 'resume',       'icon' => 'ti-list-details' ],
    'liens-articles'              => [ 'label' => 'Consultation',    'anchor' => 'consultation', 'icon' => 'ti-books' ],
    'details-techniques'          => [ 'label' => 'Détails',         'anchor' => 'details',      'icon' => 'ti-tool' ],
    'detail-technique-img-droite' => [ 'label' => 'Détails',         'anchor' => 'detail-img',   'icon' => 'ti-tool' ],
    'details-colonnes'            => [ 'label' => 'Détails',         'anchor' => 'details-col',  'icon' => 'ti-tool' ],
    'image-textes'                => [ 'label' => 'Détails',         'anchor' => 'image-textes', 'icon' => 'ti-tool' ],
    'contexte'                    => [ 'label' => 'Contexte',        'anchor' => 'contexte',     'icon' => 'ti-file-text' ],
    'conclusion'                  => [ 'label' => 'Conclusion',      'anchor' => 'conclusion',   'icon' => 'ti-flag' ],
    'evangiles'                   => [ 'label' => 'Textes bibliques','anchor' => 'versets',      'icon' => 'ti-book' ],
    'paragraphe'                  => [ 'label' => 'Commentaires',    'anchor' => 'commentaires', 'icon' => 'ti-message' ],
    'questions'                   => [ 'label' => 'Questions',       'anchor' => 'questions',    'icon' => 'ti-help' ],
    'references'                  => [ 'label' => 'Articles liés',   'anchor' => 'articles',     'icon' => 'ti-link' ],
];

// Overrides par famille de préfixe : ajuste les labels selon le type d'article
$tab_label_overrides = [
    // Articles thématiques longs (annexes, apostolat, bible, daniel, doc, fds, lgh, par, pda, ctd)
    'ANN' => [ 'intro' => 'Introduction', 'paragraphe' => 'Contenu', 'image-textes' => 'Illustration' ],
    'INF' => [ 'intro' => 'Note',         'paragraphe' => 'Détails',  'liens-articles' => 'Consultation' ],
    'MIR' => [ 'intro' => 'Miracle',      'liens-articles' => 'Consultation' ],
    'PRB' => [ 'intro' => 'Parabole',     'liens-articles' => 'Consultation' ],
    'APO' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
    'BIB' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
    'CTD' => [ 'intro' => 'Introduction', 'paragraphe' => 'Contenu', 'image-textes' => 'Illustration' ],
    'DAN' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
    'DOC' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
    'FDS' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
    'LGH' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
    'PAR' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
    'PDA' => [ 'intro' => 'Introduction', 'contexte' => 'Contexte',  'conclusion' => 'Conclusion'     ],
];
if ( $article_prefix !== '' && isset( $tab_label_overrides[ $article_prefix ] ) ) {
    foreach ( $tab_label_overrides[ $article_prefix ] as $sec_type => $new_label ) {
        if ( isset( $tab_map[ $sec_type ] ) ) {
            $tab_map[ $sec_type ]['label'] = $new_label;
        }
    }
}

// Détecte si une section brute est vide (même logique que ContentRenderer::isSectionEmpty)
$schilo_is_sec_empty = function ( array $sec ): bool {
    if ( trim( isset( $sec['content'] ) ? (string) $sec['content'] : '' ) !== '' ) {
        return false;
    }
    $data = isset( $sec['data'] ) && is_array( $sec['data'] ) ? $sec['data'] : [];
    if ( empty( $data ) ) {
        return true;
    }
    $has = false;
    array_walk_recursive( $data, function ( $v ) use ( &$has ) {
        if ( trim( (string) $v ) !== '' ) { $has = true; }
    } );
    return ! $has;
};

$tabs = [];
foreach ( $sections_raw as $sec ) {
    if ( $schilo_is_sec_empty( $sec ) ) {
        continue;
    }
    $type  = isset( $sec['type'] ) ? $sec['type'] : '';
    $title = isset( $sec['title'] ) && trim( $sec['title'] ) !== '' ? trim( $sec['title'] ) : '';
    if ( isset( $tab_map[ $type ] ) ) {
        $a = $tab_map[ $type ]['anchor'];
        if ( ! isset( $tabs[ $a ] ) ) {
            $tab = $tab_map[ $type ];
            // N'utilise le titre de section comme label que pour les types "lien/navigation"
            $title_as_label_types = [ 'liens-articles', 'references', 'titre-simple' ];
            if ( $title !== '' && in_array( $type, $title_as_label_types, true ) ) {
                $tab['label'] = $title;
            }
            $tabs[ $a ] = $tab;
        }
    }
}

// ── Navigation précédent / suivant (même catégorie) ───────────────────
$prev_post = get_previous_post( ! empty( $cats ), '', 'category' );
$next_post = get_next_post( ! empty( $cats ), '', 'category' );

// ── Temps de lecture approximatif ─────────────────────────────────────
$text_content = get_the_excerpt() . ' ';
foreach ( $sections_raw as $sec ) {
    $text_content .= isset( $sec['content'] ) ? strip_tags( $sec['content'] ) . ' ' : '';
    if ( isset( $sec['data']['versets'] ) ) {
        $text_content .= implode( ' ', array_column( $sec['data']['versets'] ?? [], 'reference' ) ) . ' ';
    }
}
$word_count   = str_word_count( $text_content );
// +30 s par référence biblique (versets à lire non inclus dans le texte brut)
$reading_time = max( 1, ceil( $word_count / 200 + $verset_count * 0.5 ) );

// ── Texte pour le lecteur à haute voix ───────────────────────────────
$lhv_parts = [];
if ( $clean_title ) { $lhv_parts[] = $clean_title . '.'; }
$lhv_excerpt = get_the_excerpt();
if ( $lhv_excerpt ) { $lhv_parts[] = $lhv_excerpt; }
foreach ( $sections_raw as $sec ) {
    $sec_type = isset( $sec['type'] ) ? $sec['type'] : '';
    if ( ! empty( $sec['content'] ) ) {
        $raw = $sec['content'];
        // [brc]Genèse 1.3[/brc] → garder uniquement la référence pour le TTS
        $raw = preg_replace( '/\[brc\](.*?)\[\/brc\]/is', '$1', $raw );
        // Supprimer les autres shortcodes ([bib], [bvc], [bnv]…)
        $raw = strip_shortcodes( $raw );
        $lhv_parts[] = wp_strip_all_tags( $raw );
    }
    if ( $sec_type === 'questions' && ! empty( $sec['data']['questions'] ) ) {
        foreach ( $sec['data']['questions'] as $q ) {
            if ( ! empty( $q['text'] ) )   $lhv_parts[] = $q['text'];
            if ( ! empty( $q['answer'] ) ) $lhv_parts[] = $q['answer'];
        }
    }
    if ( $sec_type === 'evangiles' && ! empty( $sec['data']['versets'] ) ) {
        foreach ( $sec['data']['versets'] as $v ) {
            if ( ! empty( $v['reference'] ) && ! empty( $v['text'] ) ) {
                $lhv_parts[] = $v['reference'] . '. ' . $v['text'];
            }
        }
    }
}
$lhv_text = implode( "\n\n", array_filter( array_map( 'trim', $lhv_parts ) ) );
?>

<main id="schilo-main" role="main">

  <!-- ══ HERO ══════════════════════════════════════════════════════════ -->
  <div class="schilo-single-hero">
    <?php if ( $thumb_src ) : ?>
      <div class="schilo-single-hero__bg" aria-hidden="true">
        <img src="<?php echo esc_url( $thumb_src[0] ); ?>" alt="" loading="eager">
      </div>
    <?php endif; ?>
    <div class="schilo-single-hero__overlay" aria-hidden="true"></div>

    <div class="schilo-container schilo-single-hero__inner">

      <!-- Fil d'Ariane -->
      <nav class="schilo-single-hero__breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
        <?php if ( $primary_cat ) : ?>
          <span aria-hidden="true">›</span>
          <a href="<?php echo esc_url( get_category_link( $primary_cat ) ); ?>"><?php echo esc_html( schilo_strip_category_number( $primary_cat->name ) ); ?></a>
        <?php endif; ?>
        <?php if ( $per_code ) : ?>
          <span aria-hidden="true">›</span>
          <span><?php echo esc_html( $per_code ); ?></span>
        <?php endif; ?>
      </nav>

      <!-- Badge + titre -->
      <?php if ( $per_code ) : ?>
        <span class="schilo-single-hero__per"><?php echo esc_html( $per_code ); ?></span>
      <?php endif; ?>

      <h1 class="schilo-single-hero__title"><?php echo esc_html( $clean_title ); ?></h1>

      <?php
      $excerpt = ( $indexed_row['resume_court'] ?? '' ) ?: get_the_excerpt();
      if ( $excerpt ) :
      ?>
        <p class="schilo-single-hero__excerpt"><?php echo esc_html( $excerpt ); ?></p>
      <?php endif; ?>

      <!-- Badges meta -->
      <div class="schilo-single-hero__badges">
        <span class="schilo-hero-badge schilo-hero-badge--time">
          <i class="ti ti-clock" aria-hidden="true"></i> <?php echo esc_html( $reading_time ); ?> min
        </span>
        <?php foreach ( array_slice( $display_tags, 0, 5 ) as $dtag ) : ?>
          <span class="schilo-hero-badge"><?php echo esc_html( $dtag['name'] ); ?></span>
        <?php endforeach; ?>
        <?php if ( $verset_count ) : ?>
          <span class="schilo-hero-badge">
            <i class="ti ti-book" aria-hidden="true"></i> <?php echo esc_html( $verset_count ); ?> verset<?php echo $verset_count > 1 ? 's' : ''; ?>
          </span>
        <?php endif; ?>
        <?php if ( ! empty( $evangile_items ) ) : ?>
          <span class="schilo-hero-badge">
            <i class="ti ti-bible" aria-hidden="true"></i> <?php echo esc_html( count( $evangile_items ) ); ?> livre<?php echo count( $evangile_items ) > 1 ? 's' : ''; ?>
          </span>
        <?php endif; ?>
      </div>

    </div>
  </div><!-- /hero -->

  <!-- ══ BARRE D'ONGLETS ═══════════════════════════════════════════════ -->
  <?php if ( ! empty( $tabs ) ) : ?>
  <nav class="schilo-single-tabnav" id="schilo-tabnav" aria-label="Sections de l'article">
    <div class="schilo-container schilo-single-tabnav__inner">
      <ul class="schilo-tabnav-list" role="list">
        <?php foreach ( $tabs as $anchor => $tab ) : ?>
          <li>
            <a class="schilo-tabnav-link"
               href="#sec-<?php echo esc_attr( $anchor ); ?>"
               data-anchor="sec-<?php echo esc_attr( $anchor ); ?>">
              <i class="ti <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></i>
              <?php echo esc_html( $tab['label'] ); ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>
  <?php endif; ?>

  <!-- ══ LAYOUT PRINCIPAL ══════════════════════════════════════════════ -->
  <div class="schilo-container schilo-single-layout">

    <!-- Colonne contenu -->
    <div class="schilo-single-main" id="schilo-single-main">
      <?php the_content(); ?>
    </div>

    <!-- Sidebar -->
    <aside class="schilo-single-sidebar" aria-label="Informations complémentaires">

      <!-- Export PDF -->
      <div class="schilo-sidebar-card schilo-sidebar-pdf">
        <a class="schilo-pdf-btn"
           href="<?php echo esc_url( add_query_arg( 'schilo_pdf', '1', get_permalink() ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
            <polyline points="10 9 9 9 8 9"/>
          </svg>
          Exporter en PDF
        </a>
      </div>

      <!-- Lecteur à haute voix -->
      <?php if ( $lhv_text ) : ?>
      <div class="schilo-sidebar-card schilo-sidebar-lhv">
        <div class="lhv-lecture">
          <div class="lhv-controls"></div>
          <div class="lvh-txtlecture"><?php echo esc_html( $lhv_text ); ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Navigation prev/next dans la sidebar -->
      <?php if ( $prev_post || $next_post ) : ?>
      <div class="schilo-sidebar-card schilo-sidebar-nav">
        <div class="schilo-sidebar-card__title">Navigation</div>
        <?php if ( $next_post ) : // Next = fiche suivante dans le parcours ?>
          <a class="schilo-sidebar-navlink schilo-sidebar-navlink--next"
             href="<?php echo esc_url( get_permalink( $next_post ) ); ?>">
            <span class="schilo-sidebar-navlink__dir">Fiche suivante</span>
            <span class="schilo-sidebar-navlink__label">
              <?php echo esc_html( get_the_title( $next_post ) ); ?>
            </span>
            <i class="ti ti-chevron-right" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
        <?php if ( $prev_post ) : ?>
          <a class="schilo-sidebar-navlink schilo-sidebar-navlink--prev"
             href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>">
            <i class="ti ti-chevron-left" aria-hidden="true"></i>
            <span>
              <span class="schilo-sidebar-navlink__dir">Fiche précédente</span>
              <span class="schilo-sidebar-navlink__label">
                <?php echo esc_html( get_the_title( $prev_post ) ); ?>
              </span>
            </span>
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Références bibliques -->
      <?php if ( ! empty( $evangile_items ) ) : ?>
      <div class="schilo-sidebar-card">
        <div class="schilo-sidebar-card__title">Références bibliques</div>
        <ul class="schilo-sidebar-evangiles" role="list">
          <?php foreach ( $evangile_items as $item ) : ?>
            <li class="schilo-sidebar-evangile <?php echo esc_attr( $item['class'] ); ?>">
              <span class="schilo-sidebar-evangile__dot"></span>
              <span class="schilo-sidebar-evangile__name"><?php echo esc_html( $item['label'] ); ?></span>
              <span class="schilo-sidebar-evangile__count"><?php echo esc_html( $item['count'] ); ?> référence<?php echo $item['count'] > 1 ? 's' : ''; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Statistiques -->
      <div class="schilo-sidebar-card">
        <div class="schilo-sidebar-card__title">Statistiques</div>
        <ul class="schilo-sidebar-stats" role="list">
          <li>
            <i class="ti ti-clock" aria-hidden="true"></i>
            <span>Temps de lecture</span>
            <strong><?php echo esc_html( $reading_time ); ?> min</strong>
          </li>
          <?php if ( $verset_count ) : ?>
          <li>
            <i class="ti ti-book-2" aria-hidden="true"></i>
            <span>Références bibliques</span>
            <strong><?php echo esc_html( $verset_count ); ?></strong>
          </li>
          <?php endif; ?>
          <?php if ( $question_count ) : ?>
          <li>
            <i class="ti ti-help" aria-hidden="true"></i>
            <span>Questions</span>
            <strong><?php echo esc_html( $question_count ); ?></strong>
          </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Thèmes / catégories -->
      <?php if ( $cats ) : ?>
      <div class="schilo-sidebar-card">
        <div class="schilo-sidebar-card__title">Thèmes abordés</div>
        <div class="schilo-sidebar-themes">
          <?php foreach ( $cats as $cat ) : ?>
            <a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"
               class="schilo-sidebar-theme-tag">
              <?php echo esc_html( schilo_strip_category_number( $cat->name ) ); ?>
            </a>
          <?php endforeach; ?>
          <?php foreach ( $display_tags as $dtag ) : ?>
            <a href="<?php echo esc_url( $dtag['url'] ); ?>"
               class="schilo-sidebar-theme-tag">
              <?php echo esc_html( $dtag['name'] ); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </aside>
  </div><!-- /layout -->

  <!-- ══ NAVIGATION BAS DE PAGE ════════════════════════════════════════ -->
  <?php if ( $prev_post || $next_post ) : ?>
  <nav class="schilo-single-bootnav" aria-label="Navigation entre fiches">
    <div class="schilo-container schilo-single-bootnav__inner">

      <div class="schilo-bootnav-prev">
        <?php if ( $prev_post ) : ?>
          <a href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>" class="schilo-bootnav-link">
            <i class="ti ti-chevron-left" aria-hidden="true"></i>
            <span>
              <small>Fiche précédente</small>
              <?php echo esc_html( get_the_title( $prev_post ) ); ?>
            </span>
          </a>
        <?php endif; ?>
      </div>

      <div class="schilo-bootnav-next">
        <?php if ( $next_post ) : ?>
          <a href="<?php echo esc_url( get_permalink( $next_post ) ); ?>" class="schilo-bootnav-link schilo-bootnav-link--next">
            <span>
              <small>Fiche suivante</small>
              <?php echo esc_html( get_the_title( $next_post ) ); ?>
            </span>
            <i class="ti ti-chevron-right" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
      </div>

    </div>
  </nav>
  <?php endif; ?>

</main>

<?php get_footer(); ?>
