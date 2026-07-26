<?php
/**
 * Template Name: Déclaration d'accessibilité
 * Description: Déclaration d'accessibilité (RGAA / WCAG 2.1) de Schilo.org.
 *
 * Toutes les chaînes sont traduisibles (domaine « schilo ») : la page est
 * prête pour la future version multilingue du site.
 *
 * NOTE ÉDITORIALE : le statut de conformité ci-dessous reflète une
 * auto-évaluation et une démarche d'amélioration continue, PAS un audit
 * formel. Après un audit RGAA/WCAG officiel, mettre à jour le statut et,
 * le cas échéant, la liste des non-conformités connues.
 */
defined( 'ABSPATH' ) || exit;

// Date de dernière mise à jour de la déclaration (modifiable).
$schilo_a11y_updated = get_the_modified_date( 'j F Y' );

// URL de la page de contact (auto-détection, même schéma que header/footer).
$schilo_a11y_contact_url = home_url( '/contactez-nous/' );
$schilo_a11y_by_tpl      = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'page-contact.php' ] );
if ( ! empty( $schilo_a11y_by_tpl ) ) {
	$schilo_a11y_contact_url = get_permalink( $schilo_a11y_by_tpl[0]->ID );
} else {
	foreach ( [ 'contactez-nous', 'contact', 'nous-contacter' ] as $schilo_a11y_slug ) {
		$schilo_a11y_p = get_page_by_path( $schilo_a11y_slug );
		if ( $schilo_a11y_p ) { $schilo_a11y_contact_url = get_permalink( $schilo_a11y_p->ID ); break; }
	}
}

get_header();
?>

<!-- ── HERO ── -->
<div class="schilo-hero">
  <div class="schilo-hero__inner">
    <div class="schilo-hero__eyebrow">
      <i class="ti ti-accessible" aria-hidden="true"></i>
      <?php esc_html_e( 'Accessibilité', 'schilo' ); ?>
    </div>
    <h1 class="schilo-hero__title schilo-serif">
      <?php esc_html_e( "Déclaration d'accessibilité", 'schilo' ); ?>
    </h1>
    <p class="schilo-hero__desc">
      <?php esc_html_e( "Schilo.org veut rendre l'étude biblique accessible à toutes et à tous, y compris aux personnes utilisant un lecteur d'écran, une plage braille ou une navigation au clavier.", 'schilo' ); ?>
    </p>
  </div>
</div>

<main id="schilo-main" role="main">
  <div class="schilo-container" style="padding-top:2rem;padding-bottom:4rem">

    <!-- ── SECTION : ENGAGEMENT ── -->
    <div class="schilo-card" style="margin-bottom:1.25rem">
      <div class="schilo-card__head">
        <div class="schilo-card__head-left">
          <div class="schilo-card__icon schilo-card__icon--dark">
            <i class="ti ti-heart-handshake" aria-hidden="true"></i>
          </div>
          <h2 class="schilo-card__title"><?php esc_html_e( 'Notre engagement', 'schilo' ); ?></h2>
        </div>
      </div>
      <div class="schilo-card__body">
        <p style="margin:0 0 .75rem;line-height:1.75">
          <?php esc_html_e( "L'accessibilité numérique fait partie des valeurs de Schilo.org : le savoir biblique doit être ouvert à tous, sans barrière technique. Nous nous efforçons de rendre l'ensemble du site conforme aux critères du RGAA (Référentiel général d'amélioration de l'accessibilité) et aux règles internationales WCAG 2.1 niveau AA.", 'schilo' ); ?>
        </p>
        <p style="margin:0;line-height:1.75">
          <?php esc_html_e( "L'accessibilité est une démarche continue : nous corrigeons les défauts au fil des versions et accueillons volontiers vos signalements.", 'schilo' ); ?>
        </p>
      </div>
    </div>

    <!-- ── SECTION : ÉTAT DE CONFORMITÉ ── -->
    <div class="schilo-card" style="margin-bottom:1.25rem">
      <div class="schilo-card__head">
        <div class="schilo-card__head-left">
          <div class="schilo-card__icon schilo-card__icon--dark">
            <i class="ti ti-clipboard-check" aria-hidden="true"></i>
          </div>
          <h2 class="schilo-card__title"><?php esc_html_e( 'État de conformité', 'schilo' ); ?></h2>
        </div>
      </div>
      <div class="schilo-card__body">
        <p style="margin:0 0 .75rem;line-height:1.75">
          <?php esc_html_e( "À ce jour, Schilo.org n'a pas encore fait l'objet d'un audit d'accessibilité formel par un tiers. Le site fait l'objet d'une auto-évaluation et d'une démarche d'amélioration continue visant le niveau WCAG 2.1 AA. Le statut de conformité sera précisé ici à l'issue d'un audit.", 'schilo' ); ?>
        </p>
        <p style="margin:0;line-height:1.75">
          <?php esc_html_e( "Certains contenus hérités, en cours de reprise éditoriale, peuvent ne pas encore respecter tous les critères (par exemple des textes alternatifs d'images à compléter).", 'schilo' ); ?>
        </p>
      </div>
    </div>

    <!-- ── SECTION : FONCTIONNALITÉS D'ACCESSIBILITÉ ── -->
    <div class="schilo-card" style="margin-bottom:1.25rem">
      <div class="schilo-card__head">
        <div class="schilo-card__head-left">
          <div class="schilo-card__icon schilo-card__icon--dark">
            <i class="ti ti-adjustments-check" aria-hidden="true"></i>
          </div>
          <h2 class="schilo-card__title"><?php esc_html_e( "Fonctionnalités d'accessibilité", 'schilo' ); ?></h2>
        </div>
      </div>
      <div class="schilo-card__body">
        <ul style="margin:0;padding-left:1.25rem;line-height:1.85">
          <li><?php esc_html_e( "Lien « Aller au contenu » en début de page pour sauter la navigation.", 'schilo' ); ?></li>
          <li><?php esc_html_e( "Navigation entièrement utilisable au clavier, avec un focus visible sur chaque élément interactif.", 'schilo' ); ?></li>
          <li><?php esc_html_e( "Structure sémantique (repères, titres hiérarchisés, listes) lisible par les lecteurs d'écran et les plages braille.", 'schilo' ); ?></li>
          <li><?php esc_html_e( "Fenêtres modales (recherche, définitions, résumés) gérées au clavier : ouverture, fermeture par la touche Échap et retour du focus.", 'schilo' ); ?></li>
          <li><?php esc_html_e( "Réglage de la taille du texte via le widget de zoom présent sur les pages de contenu.", 'schilo' ); ?></li>
          <li><?php esc_html_e( "Respect de la préférence système « réduire les animations ».", 'schilo' ); ?></li>
        </ul>
      </div>
    </div>

    <!-- ── SECTION : TECHNOLOGIES ── -->
    <div class="schilo-card" style="margin-bottom:1.25rem">
      <div class="schilo-card__head">
        <div class="schilo-card__head-left">
          <div class="schilo-card__icon schilo-card__icon--dark">
            <i class="ti ti-code" aria-hidden="true"></i>
          </div>
          <h2 class="schilo-card__title"><?php esc_html_e( "Technologies utilisées", 'schilo' ); ?></h2>
        </div>
      </div>
      <div class="schilo-card__body">
        <p style="margin:0;line-height:1.75">
          <?php esc_html_e( "L'accessibilité de Schilo.org s'appuie sur HTML5, les attributs WAI-ARIA, CSS et JavaScript. Le site est conçu pour rester utilisable même lorsque JavaScript est partiellement pris en charge.", 'schilo' ); ?>
        </p>
      </div>
    </div>

    <!-- ── SECTION : SIGNALER UN PROBLÈME ── -->
    <div class="schilo-card">
      <div class="schilo-card__head">
        <div class="schilo-card__head-left">
          <div class="schilo-card__icon schilo-card__icon--dark">
            <i class="ti ti-message-report" aria-hidden="true"></i>
          </div>
          <h2 class="schilo-card__title"><?php esc_html_e( "Signaler un problème d'accessibilité", 'schilo' ); ?></h2>
        </div>
      </div>
      <div class="schilo-card__body">
        <p style="margin:0 0 1rem;line-height:1.75">
          <?php esc_html_e( "Vous rencontrez une difficulté pour accéder à un contenu ou une fonctionnalité ? Signalez-le-nous : nous nous engageons à vous répondre et à corriger le problème dans la mesure du possible.", 'schilo' ); ?>
        </p>
        <a href="<?php echo esc_url( $schilo_a11y_contact_url ); ?>" class="schilo-btn schilo-btn--dark" style="display:inline-flex;align-items:center;gap:7px">
          <i class="ti ti-mail" aria-hidden="true"></i>
          <?php esc_html_e( "Nous signaler un problème", 'schilo' ); ?>
        </a>
      </div>
    </div>

    <p style="margin-top:1.5rem;font-size:13px;color:var(--schilo-text-secondary)">
      <?php
      /* translators: %s : date de dernière mise à jour de la déclaration. */
      printf( esc_html__( 'Déclaration mise à jour le %s.', 'schilo' ), esc_html( $schilo_a11y_updated ) );
      ?>
    </p>

  </div>
</main>

<?php get_footer(); ?>
