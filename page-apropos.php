<?php
/**
 * Template Name: À propos
 * Description: Page À propos de Schilo.org
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<!-- ── HERO ── -->
<div class="schilo-hero">
  <div class="schilo-hero__inner">
    <div class="schilo-hero__eyebrow">
      <i class="ti ti-info-circle" aria-hidden="true"></i>
      <?php esc_html_e( 'À propos', 'schilo' ); ?>
    </div>
    <h1 class="schilo-hero__title schilo-serif">
      <?php esc_html_e( 'Découvrir Jésus, au-delà du grand homme', 'schilo' ); ?>
    </h1>
    <p class="schilo-hero__desc">
      <?php esc_html_e( 'Schilo.org est un site d\'étude biblique indépendant, gratuit et sans publicité, consacré à la découverte de Jésus à travers les quatre Évangiles.', 'schilo' ); ?>
    </p>
  </div>
</div>

<main id="schilo-main" role="main">
  <div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">

    <!-- ── SECTION : NOTRE DÉMARCHE ── -->
    <div class="schilo-card" style="margin-bottom:1.25rem">
      <div class="schilo-card__head">
        <div class="schilo-card__head-left">
          <div class="schilo-card__icon schilo-card__icon--dark">
            <i class="ti ti-book-2" aria-hidden="true"></i>
          </div>
          <span class="schilo-card__title"><?php esc_html_e( 'Notre démarche', 'schilo' ); ?></span>
        </div>
      </div>
      <div class="schilo-card__body">
        <div class="schilo-apropos-intro">
          <p><?php esc_html_e( 'Schilo.org est né d\'une conviction simple : la Bible, et en particulier les quatre Évangiles, demeure une référence indéniable pour comprendre la vie, le message et l\'identité de Jésus. Notre objectif n\'est pas de promouvoir une idéologie, un courant de pensée particulier ou un mouvement religieux — mais de mettre le texte biblique au centre, et de l\'étudier avec rigueur et honnêteté.', 'schilo' ); ?></p>
          <p><?php esc_html_e( 'Bien que nous soyons chrétiens, nous accueillons tout chercheur sincère, quelle que soit sa position de départ. Nous affirmons l\'inerrance de la Bible et nous répondons aux critiques qui la remettent en cause — non par dogmatisme, mais parce que nous sommes convaincus que ces textes, lus attentivement, s\'harmonisent pour délivrer un message cohérent et crucial pour l\'avenir de nos vies.', 'schilo' ); ?></p>
        </div>
      </div>
    </div>

    <!-- ── SECTION : VALEURS — grille 3 cartes ── -->
    <div class="schilo-apropos-section-title">
      <i class="ti ti-heart" aria-hidden="true"></i>
      <?php esc_html_e( 'Nos valeurs', 'schilo' ); ?>
    </div>
    <div class="schilo-apropos-values" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:0">

      <div class="schilo-apropos-value-card" style="background:var(--schilo-bg-card);border:1px solid var(--schilo-border);border-radius:14px;padding:1.25rem">
        <div class="schilo-apropos-value-icon" style="background:var(--schilo-luc-bg)">
          <i class="ti ti-zoom-in" style="color:var(--schilo-luc-dark)" aria-hidden="true"></i>
        </div>
        <div class="schilo-apropos-value-title"><?php esc_html_e( 'Rigueur', 'schilo' ); ?></div>
        <p class="schilo-apropos-value-desc"><?php esc_html_e( 'Chaque fiche est rédigée après étude approfondie des textes, de leur contexte historique et des différentes traductions disponibles.', 'schilo' ); ?></p>
      </div>

      <div class="schilo-apropos-value-card" style="background:var(--schilo-bg-card);border:1px solid var(--schilo-border);border-radius:14px;padding:1.25rem">
        <div class="schilo-apropos-value-icon" style="background:var(--schilo-marc-bg)">
          <i class="ti ti-lock-open" style="color:var(--schilo-marc-dark)" aria-hidden="true"></i>
        </div>
        <div class="schilo-apropos-value-title"><?php esc_html_e( 'Accessibilité', 'schilo' ); ?></div>
        <p class="schilo-apropos-value-desc"><?php esc_html_e( 'Le site est entièrement gratuit, sans publicité et sans inscription. Le savoir biblique doit être accessible à tous, partout dans le monde.', 'schilo' ); ?></p>
      </div>

      <div class="schilo-apropos-value-card" style="background:var(--schilo-bg-card);border:1px solid var(--schilo-border);border-radius:14px;padding:1.25rem">
        <div class="schilo-apropos-value-icon" style="background:var(--schilo-mat-bg)">
          <i class="ti ti-flame" style="color:var(--schilo-mat-dark)" aria-hidden="true"></i>
        </div>
        <div class="schilo-apropos-value-title"><?php esc_html_e( 'Honnêteté', 'schilo' ); ?></div>
        <p class="schilo-apropos-value-desc"><?php esc_html_e( 'Nous n\'évitons pas les questions difficiles. Les passages complexes, les contradictions apparentes et les critiques sont abordés frontalement.', 'schilo' ); ?></p>
      </div>

      <div class="schilo-apropos-value-card" style="background:var(--schilo-bg-card);border:1px solid var(--schilo-border);border-radius:14px;padding:1.25rem">
        <div class="schilo-apropos-value-icon" style="background:var(--schilo-jean-bg)">
          <i class="ti ti-world" style="color:var(--schilo-jean-dark)" aria-hidden="true"></i>
        </div>
        <div class="schilo-apropos-value-title"><?php esc_html_e( 'Universalité', 'schilo' ); ?></div>
        <p class="schilo-apropos-value-desc"><?php esc_html_e( 'Le message de Jésus s\'adresse à toute l\'humanité. Le site est traduit en plusieurs langues pour toucher le plus grand nombre.', 'schilo' ); ?></p>
      </div>

      <div class="schilo-apropos-value-card" style="background:var(--schilo-bg-card);border:1px solid var(--schilo-border);border-radius:14px;padding:1.25rem">
        <div class="schilo-apropos-value-icon" style="background:#fef6e4">
          <i class="ti ti-route" style="color:#8a5c00" aria-hidden="true"></i>
        </div>
        <div class="schilo-apropos-value-title"><?php esc_html_e( 'Progressivité', 'schilo' ); ?></div>
        <p class="schilo-apropos-value-desc"><?php esc_html_e( 'Les parcours sont structurés pour accompagner le lecteur pas à pas, du débutant curieux au lecteur confirmé souhaitant approfondir.', 'schilo' ); ?></p>
      </div>

      <div class="schilo-apropos-value-card" style="background:var(--schilo-bg-card);border:1px solid var(--schilo-border);border-radius:14px;padding:1.25rem">
        <div class="schilo-apropos-value-icon" style="background:#e0f5f0">
          <i class="ti ti-shield-check" style="color:#0e6a52" aria-hidden="true"></i>
        </div>
        <div class="schilo-apropos-value-title"><?php esc_html_e( 'Indépendance', 'schilo' ); ?></div>
        <p class="schilo-apropos-value-desc"><?php esc_html_e( 'Schilo.org n\'est affilié à aucune église, denomination ou organisation. Notre seule autorité de référence est le texte biblique lui-même.', 'schilo' ); ?></p>
      </div>

      <div class="schilo-apropos-value-card" style="background:var(--schilo-bg-card);border:1px solid var(--schilo-border);border-radius:14px;padding:1.25rem">
        <div class="schilo-apropos-value-icon" style="background:#fff3c4">
          <i class="ti ti-layers-subtract" style="color:#7a5800" aria-hidden="true"></i>
        </div>
        <div class="schilo-apropos-value-title"><?php esc_html_e( 'Profondeur', 'schilo' ); ?></div>
        <p class="schilo-apropos-value-desc"><?php esc_html_e( 'Chaque sujet est traité avec le contexte historique, linguistique et culturel nécessaire pour en saisir toute la portée.', 'schilo' ); ?></p>
      </div>

    </div>

    <!-- ── SECTION : TIMELINE ── -->
    <div class="schilo-apropos-section-title" style="margin-top:2.5rem">
      <i class="ti ti-timeline" aria-hidden="true"></i>
      <?php esc_html_e( 'L\'histoire du projet', 'schilo' ); ?>
    </div>

    <div class="schilo-card" style="margin-bottom:1.25rem">
      <div class="schilo-card__body">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem">

          <div style="border:1px solid var(--schilo-border);border-radius:12px;overflow:hidden">
            <div style="background:var(--schilo-luc);padding:.85rem 1rem;display:flex;align-items:center;gap:8px">
              <span style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">1</span>
              <span style="font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.8)"><?php esc_html_e( 'Les origines', 'schilo' ); ?></span>
            </div>
            <div style="padding:1rem;background:var(--schilo-bg-card)">
              <div style="font-size:13px;font-weight:500;color:var(--schilo-text-primary);margin-bottom:.4rem"><?php esc_html_e( 'Une conviction personnelle', 'schilo' ); ?></div>
              <p style="font-size:12px;color:var(--schilo-text-secondary);line-height:1.65;margin:0"><?php esc_html_e( 'Face aux nombreuses critiques adressées à la Bible, il manquait un espace d\'étude structuré, rigoureux et accessible au grand public francophone.', 'schilo' ); ?></p>
            </div>
          </div>

          <div style="border:1px solid var(--schilo-border);border-radius:12px;overflow:hidden">
            <div style="background:var(--schilo-mat);padding:.85rem 1rem;display:flex;align-items:center;gap:8px">
              <span style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">2</span>
              <span style="font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.8)"><?php esc_html_e( 'Wikilogy', 'schilo' ); ?></span>
            </div>
            <div style="padding:1rem;background:var(--schilo-bg-card)">
              <div style="font-size:13px;font-weight:500;color:var(--schilo-text-primary);margin-bottom:.4rem"><?php esc_html_e( 'La première version', 'schilo' ); ?></div>
              <p style="font-size:12px;color:var(--schilo-text-secondary);line-height:1.65;margin:0"><?php esc_html_e( 'Des centaines de fiches d\'étude rédigées sur Wikilogy, couvrant les quatre Évangiles et leurs grands thèmes bibliques.', 'schilo' ); ?></p>
            </div>
          </div>

          <div style="border:1px solid var(--schilo-border);border-radius:12px;overflow:hidden">
            <div style="background:var(--schilo-marc);padding:.85rem 1rem;display:flex;align-items:center;gap:8px">
              <span style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">3</span>
              <span style="font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.8)"><?php esc_html_e( 'Migration', 'schilo' ); ?></span>
            </div>
            <div style="padding:1rem;background:var(--schilo-bg-card)">
              <div style="font-size:13px;font-weight:500;color:var(--schilo-text-primary);margin-bottom:.4rem"><?php esc_html_e( 'Naissance de Schilo.org', 'schilo' ); ?></div>
              <p style="font-size:12px;color:var(--schilo-text-secondary);line-height:1.65;margin:0"><?php esc_html_e( 'Le contenu migré vers Schilo.org — un site dédié, repensé autour d\'un design épuré et d\'une navigation intuitive.', 'schilo' ); ?></p>
            </div>
          </div>

          <div style="border:1px solid var(--schilo-border);border-radius:12px;overflow:hidden">
            <div style="background:var(--schilo-jean);padding:.85rem 1rem;display:flex;align-items:center;gap:8px">
              <span style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">4</span>
              <span style="font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.8)"><?php esc_html_e( 'Aujourd\'hui', 'schilo' ); ?></span>
            </div>
            <div style="padding:1rem;background:var(--schilo-bg-card)">
              <div style="font-size:13px;font-weight:500;color:var(--schilo-text-primary);margin-bottom:.4rem"><?php esc_html_e( 'Un site en constante évolution', 'schilo' ); ?></div>
              <p style="font-size:12px;color:var(--schilo-text-secondary);line-height:1.65;margin:0"><?php esc_html_e( 'Schilo.org continue de s\'enrichir de nouvelles fiches et parcours. L\'objectif : accompagner chaque lecteur dans sa découverte de Jésus.', 'schilo' ); ?></p>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ── SECTION : SIGNIFICATION ── -->
    <div class="schilo-apropos-section-title" style="margin-top:2.5rem">
      <i class="ti ti-book" aria-hidden="true"></i>
      <?php esc_html_e( 'La signification de « Schilo »', 'schilo' ); ?>
    </div>

    <div class="schilo-card schilo-apropos-schilo-card" style="margin-bottom:1.25rem">
      <div class="schilo-card__body">
        <div class="schilo-apropos-schilo">
          <div class="schilo-apropos-schilo__verse">
            <div class="schilo-apropos-schilo__ref">Genèse 49.10</div>
            <blockquote class="schilo-apropos-schilo__text">
              <?php esc_html_e( '« Le sceptre ne s\'éloignera pas de Juda, ni le bâton de commandement d\'entre ses pieds, jusqu\'à ce que vienne Schilo, et que les peuples lui obéissent. »', 'schilo' ); ?>
            </blockquote>
          </div>
          <div class="schilo-apropos-schilo__expl">
            <p><?php esc_html_e( 'Le terme « Schilo » apparaît dans la Genèse, au sein de la bénédiction de Jacob sur ses fils. Il est généralement traduit par « celui à qui cela appartient » ou « celui qui doit venir » — une prophétie messianique qui annonce un personnage issu de la tribu de Juda, à qui les peuples du monde obéiront.', 'schilo' ); ?></p>
            <p><?php esc_html_e( 'Pour nous, ce nom symbolise notre conviction centrale : Jésus est ce Schilo — le Messie annoncé, fils de David, fils d\'Abraham, vers qui pointent toutes les Écritures. Notre site porte ce nom en hommage à cette prophétie et à Celui qu\'elle désigne.', 'schilo' ); ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── SECTION : ARTICLES LIÉS (categorie "A propos du site", retiree de l'accueil
         via Schilo Builder > Prefixes & categories, mais gardee accessible ici) ── -->
    <?php
    $apropos_category = get_category_by_slug( 'a-propos-du-site' );
    $apropos_articles = $apropos_category ? get_posts( [
        'category'       => $apropos_category->term_id,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ] ) : [];
    ?>
    <?php if ( ! empty( $apropos_articles ) ) : ?>
    <div class="schilo-apropos-section-title" style="margin-top:2.5rem">
      <i class="ti ti-notes" aria-hidden="true"></i>
      <?php esc_html_e( 'Pour aller plus loin', 'schilo' ); ?>
    </div>
    <div class="schilo-apropos-articles">
      <?php foreach ( $apropos_articles as $apropos_post ) :
        // Certains de ces articles n'ont jamais ete migres vers Schilo Builder et
        // contiennent encore des shortcodes WPBakery/Wikilogy desactives — non
        // reconnus par strip_shortcodes() (plugin desactive). Part du contenu brut
        // (pas de get_the_excerpt(), qui tronque a 55 mots AVANT le nettoyage et
        // coupe donc souvent en plein milieu d'un shortcode non ferme) : on retire
        // les blocs [entre crochets] sur le texte complet, puis seulement on tronque.
        $apropos_excerpt = wp_strip_all_tags( get_the_content( '', false, $apropos_post ) );
        $apropos_excerpt = preg_replace( '/\[[^\]]*\]/s', '', $apropos_excerpt );
        $apropos_excerpt = wp_trim_words( trim( $apropos_excerpt ), 20, '…' );
      ?>
        <a href="<?php echo esc_url( get_permalink( $apropos_post ) ); ?>" class="schilo-apropos-article-card">
          <h3><?php echo esc_html( get_the_title( $apropos_post ) ); ?></h3>
          <?php if ( $apropos_excerpt !== '' ) : ?>
            <p><?php echo esc_html( $apropos_excerpt ); ?></p>
          <?php endif; ?>
          <span class="schilo-apropos-article-link">
            <?php esc_html_e( 'Lire l\'article', 'schilo' ); ?>
            <i class="ti ti-arrow-right" aria-hidden="true"></i>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── SECTION : CTA ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:2rem;background:var(--schilo-bg-dark);border-radius:14px;padding:2rem 2.5rem;margin-top:2rem;flex-wrap:wrap">
      <div style="flex:1">
        <div style="font-size:18px;font-weight:500;color:#fff;margin-bottom:.4rem">
          <?php esc_html_e( 'Prêt à commencer votre étude ?', 'schilo' ); ?>
        </div>
        <p style="font-size:13px;color:rgba(255,255,255,.55);margin:0">
          <?php esc_html_e( 'Parcourez les Évangiles fiche par fiche, explorez les thèmes ou posez-nous une question.', 'schilo' ); ?>
        </p>
      </div>
      <div style="display:flex;gap:10px;flex-shrink:0;flex-wrap:wrap">
        <a href="<?php echo esc_url( home_url( '/parcours/' ) ); ?>"
           style="display:inline-flex;align-items:center;gap:7px;background:var(--schilo-luc);color:#fff!important;border:none;border-radius:99px;padding:10px 20px;font-size:13px;font-weight:500;text-decoration:none">
          <i class="ti ti-route" aria-hidden="true"></i>
          <?php esc_html_e( 'Commencer un parcours', 'schilo' ); ?>
        </a>
        <?php
        $fc_url  = home_url( '/contactez-nous/' );
        $by_tpl  = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
        if ( ! empty( $by_tpl ) ) $fc_url = get_permalink( $by_tpl[0]->ID );
        ?>
        <a href="<?php echo esc_url( $fc_url ); ?>"
           style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.1);color:#fff!important;border:1px solid rgba(255,255,255,.3);border-radius:99px;padding:10px 20px;font-size:13px;font-weight:500;text-decoration:none">
          <i class="ti ti-mail" aria-hidden="true"></i>
          <?php esc_html_e( 'Nous écrire', 'schilo' ); ?>
        </a>
      </div>
    </div>

  </div>
</main>

<?php get_footer(); ?>
