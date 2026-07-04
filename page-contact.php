<?php
/**
 * Template Name: Contact
 * Description: Page de contact Schilo
 */

defined( 'ABSPATH' ) || exit;

/* ── Traitement du formulaire ── */
$form_sent   = false;
$form_error  = '';
$form_values = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['schilo_contact_nonce'] ) ) {

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['schilo_contact_nonce'] ) ), 'schilo_contact' ) ) {
        $form_error = __( 'Erreur de sécurité. Veuillez recharger la page.', 'schilo' );

    } elseif ( ! empty( $_POST['website'] ) ) {
        $form_sent = true; // Honeypot

    } else {
        $prenom     = sanitize_text_field( wp_unslash( $_POST['schilo_prenom']   ?? '' ) );
        $nom        = sanitize_text_field( wp_unslash( $_POST['schilo_nom']      ?? '' ) );
        $email      = sanitize_email(      wp_unslash( $_POST['schilo_email']    ?? '' ) );
        $sujet      = sanitize_text_field( wp_unslash( $_POST['schilo_sujet']    ?? '' ) );
        $message    = sanitize_textarea_field( wp_unslash( $_POST['schilo_message'] ?? '' ) );
        $newsletter = isset( $_POST['schilo_newsletter'] );

        $form_values = compact( 'prenom', 'nom', 'email', 'sujet', 'message', 'newsletter' );

        if ( empty( $prenom ) ) {
            $form_error = __( 'Veuillez indiquer votre prénom.', 'schilo' );
        } elseif ( ! is_email( $email ) ) {
            $form_error = __( "L'adresse e-mail est invalide.", 'schilo' );
        } elseif ( empty( $sujet ) ) {
            $form_error = __( 'Veuillez choisir un sujet.', 'schilo' );
        } elseif ( strlen( $message ) < 10 ) {
            $form_error = __( 'Le message est trop court (10 caractères minimum).', 'schilo' );
        } else {
            $to           = get_option( 'admin_email' );
            $mail_subject = sprintf( '[Schilo Contact] %s — %s %s', $sujet, $prenom, $nom );
            $mail_body    = sprintf(
                "Nouveau message reçu via le formulaire de contact de Schilo.org\n\n" .
                "Prénom : %s\nNom : %s\nE-mail : %s\nSujet : %s\nNewsletter : %s\n\n" .
                "Message :\n%s\n\n---\nEnvoyé le %s",
                $prenom, $nom, $email, $sujet,
                $newsletter ? 'Oui' : 'Non',
                $message,
                wp_date( 'd/m/Y à H:i' )
            );
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'Reply-To: ' . $prenom . ' ' . $nom . ' <' . $email . '>',
            ];

            if ( wp_mail( $to, $mail_subject, $mail_body, $headers ) ) {
                $form_sent = true;
            } else {
                $form_error = __( "Une erreur est survenue lors de l'envoi. Veuillez réessayer.", 'schilo' );
            }
        }
    }
}

/* ── Sujets ── */
$sujets = [
    ''                   => __( 'Choisir un sujet…', 'schilo' ),
    'question-article'   => __( 'Question sur un article ou une fiche', 'schilo' ),
    'question-biblique'  => __( 'Question biblique ou théologique', 'schilo' ),
    'signaler-erreur'    => __( 'Signaler une erreur ou une imprécision', 'schilo' ),
    'suggestion-contenu' => __( 'Suggestion de contenu ou de parcours', 'schilo' ),
    'probleme-technique' => __( 'Problème technique', 'schilo' ),
    'partenariat'        => __( 'Partenariat / collaboration', 'schilo' ),
    'autre'              => __( 'Autre', 'schilo' ),
];

/*
 * ── Guides par sujet ──
 * Chaque sujet dispose de :
 *   - conseil : texte d'aide à la rédaction
 *   - questions : liste de questions préétablies (clic → remplit le textarea)
 *     (uniquement pour les sujets liés à l'étude)
 */
$guides = [
    'question-article' => [
        'conseil'   => __( "Indiquez le code de la fiche (ex. PER001) ou le titre de l'article, puis posez votre question aussi précisément que possible.", 'schilo' ),
        'questions' => [
            __( 'Je n\'ai pas bien compris le commentaire de la fiche [CODE]. Pourriez-vous m\'expliquer… ?', 'schilo' ),
            __( 'Le verset [RÉFÉRENCE] cité dans la fiche [CODE] me pose question : que signifie… ?', 'schilo' ),
            __( 'Y a-t-il d\'autres fiches sur le thème abordé dans [CODE] ?', 'schilo' ),
        ],
    ],
    'question-biblique' => [
        'conseil'   => __( "Précisez si possible le livre, le chapitre et le verset concernés. Plus votre question est précise, plus notre réponse sera utile.", 'schilo' ),
        'questions' => [
            __( 'Que signifie exactement [TERME / PASSAGE] dans son contexte original ?', 'schilo' ),
            __( 'Quelle est la différence entre ce que dit [ÉVANGILE A] et [ÉVANGILE B] sur ce sujet ?', 'schilo' ),
            __( 'Comment interpréter [PASSAGE] à la lumière de l\'Ancien Testament ?', 'schilo' ),
            __( 'Existe-t-il des parallèles entre [PASSAGE] et d\'autres textes bibliques ?', 'schilo' ),
        ],
    ],
    'signaler-erreur' => [
        'conseil'   => __( "Merci pour votre vigilance ! Indiquez le code de la fiche (ex. PER001), la nature de l'erreur (texte, référence, traduction…) et, si possible, la correction proposée.", 'schilo' ),
        'questions' => [],
    ],
    'suggestion-contenu' => [
        'conseil'   => __( "Toutes les suggestions sont lues attentivement. Décrivez le thème, le passage ou le parcours que vous aimeriez voir développé sur Schilo.", 'schilo' ),
        'questions' => [
            __( 'Je souhaiterais un parcours dédié à [THÈME / LIVRE BIBLIQUE].', 'schilo' ),
            __( 'Pourriez-vous ajouter une fiche sur [SUJET] dans l\'Évangile de [MATTHIEU / MARC / LUC / JEAN] ?', 'schilo' ),
            __( 'Il manque selon moi une étude sur [PASSAGE OU THÈME]. Voici pourquoi…', 'schilo' ),
        ],
    ],
    'probleme-technique' => [
        'conseil'   => __( "Décrivez le problème rencontré : page concernée, navigateur utilisé, message d'erreur affiché. Une capture d'écran peut être jointe en répondant à notre e-mail de confirmation.", 'schilo' ),
        'questions' => [],
    ],
    'partenariat' => [
        'conseil'   => __( "Présentez brièvement votre projet, votre organisation et la nature de la collaboration envisagée.", 'schilo' ),
        'questions' => [],
    ],
    'autre' => [
        'conseil'   => __( "N'hésitez pas à détailler votre demande. Nous lisons chaque message.", 'schilo' ),
        'questions' => [],
    ],
];

get_header();
?>

<!-- ── HERO ── -->
<div class="schilo-hero">
  <div class="schilo-hero__inner">
    <div class="schilo-hero__eyebrow">
      <i class="ti ti-mail" aria-hidden="true"></i>
      <?php esc_html_e( 'Contact', 'schilo' ); ?>
    </div>
    <h1 class="schilo-hero__title schilo-serif">
      <?php esc_html_e( "Écrire à l'équipe Schilo", 'schilo' ); ?>
    </h1>
    <p class="schilo-hero__desc">
      <?php esc_html_e( "Une question biblique, une erreur à signaler, une suggestion d'étude ? Nous lisons chaque message avec attention.", 'schilo' ); ?>
    </p>
  </div>
</div>

<!-- ── BODY ── -->
<main id="schilo-main" role="main">
  <div class="schilo-container" style="padding-top:1.5rem;padding-bottom:4rem">
    <div class="schilo-grid-main">

      <!-- COLONNE PRINCIPALE -->
      <div>

        <!-- Texte introductif -->
        <div class="schilo-card" style="margin-bottom:1.25rem">
          <div class="schilo-card__body">
            <p style="margin:0 0 .75rem;font-size:14px;color:var(--schilo-text-secondary);line-height:1.75">
              <?php esc_html_e( 'Que vous ayez une question sur un passage biblique, une remarque sur une fiche, ou simplement envie d\'entamer une conversation — nous serons heureux de vous lire.', 'schilo' ); ?>
            </p>
            <p style="margin:0;font-size:14px;color:var(--schilo-text-secondary);line-height:1.75">
              <?php esc_html_e( 'Choisissez un sujet dans le formulaire ci-dessous pour obtenir des conseils de rédaction adaptés. Nous répondons en général sous 48 h.', 'schilo' ); ?>
            </p>
          </div>
        </div>

        <?php if ( $form_sent ) : ?>
        <div class="schilo-contact-success" role="alert">
          <div class="schilo-contact-success__icon">
            <i class="ti ti-check" aria-hidden="true"></i>
          </div>
          <div>
            <div class="schilo-contact-success__title">
              <?php esc_html_e( 'Message envoyé avec succès', 'schilo' ); ?>
            </div>
            <p class="schilo-contact-success__desc">
              <?php
              printf(
                esc_html__( 'Merci %s ! Nous avons bien reçu votre message et vous répondrons à l\'adresse %s.', 'schilo' ),
                esc_html( $form_values['prenom'] ?? '' ),
                '<strong>' . esc_html( $form_values['email'] ?? '' ) . '</strong>'
              );
              ?>
            </p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="schilo-btn schilo-btn--outline" style="margin-top:1rem;display:inline-flex">
              <i class="ti ti-home" aria-hidden="true"></i>
              <?php esc_html_e( "Retour à l'accueil", 'schilo' ); ?>
            </a>
          </div>
        </div>

        <?php else : ?>

        <div class="schilo-card">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-send" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title">
                <?php esc_html_e( 'Envoyer un message', 'schilo' ); ?>
              </span>
            </div>
          </div>
          <div class="schilo-card__body">

            <?php if ( $form_error ) : ?>
            <div class="schilo-contact-error" role="alert">
              <i class="ti ti-alert-circle" aria-hidden="true"></i>
              <?php echo esc_html( $form_error ); ?>
            </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( get_permalink() ); ?>"
                  class="schilo-contact-form" novalidate id="schilo-contact-form">

              <?php wp_nonce_field( 'schilo_contact', 'schilo_contact_nonce' ); ?>

              <!-- Honeypot -->
              <div class="schilo-contact-honeypot" aria-hidden="true">
                <label for="website">Site web</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
              </div>

              <!-- Identité -->
              <div class="schilo-contact-row">
                <div class="schilo-contact-group">
                  <label for="schilo_prenom">
                    <?php esc_html_e( 'Prénom', 'schilo' ); ?>
                    <span class="schilo-contact-req" aria-hidden="true">*</span>
                  </label>
                  <input type="text" id="schilo_prenom" name="schilo_prenom"
                         placeholder="<?php esc_attr_e( 'Jean', 'schilo' ); ?>"
                         value="<?php echo esc_attr( $form_values['prenom'] ?? '' ); ?>"
                         required autocomplete="given-name">
                </div>
                <div class="schilo-contact-group">
                  <label for="schilo_nom"><?php esc_html_e( 'Nom', 'schilo' ); ?></label>
                  <input type="text" id="schilo_nom" name="schilo_nom"
                         placeholder="<?php esc_attr_e( 'Dupont', 'schilo' ); ?>"
                         value="<?php echo esc_attr( $form_values['nom'] ?? '' ); ?>"
                         autocomplete="family-name">
                </div>
              </div>

              <!-- Email + Sujet -->
              <div class="schilo-contact-row">
                <div class="schilo-contact-group">
                  <label for="schilo_email">
                    <?php esc_html_e( 'Adresse e-mail', 'schilo' ); ?>
                    <span class="schilo-contact-req" aria-hidden="true">*</span>
                  </label>
                  <input type="email" id="schilo_email" name="schilo_email"
                         placeholder="<?php esc_attr_e( 'jean@exemple.fr', 'schilo' ); ?>"
                         value="<?php echo esc_attr( $form_values['email'] ?? '' ); ?>"
                         required autocomplete="email">
                </div>
                <div class="schilo-contact-group">
                  <label for="schilo_sujet">
                    <?php esc_html_e( 'Sujet', 'schilo' ); ?>
                    <span class="schilo-contact-req" aria-hidden="true">*</span>
                  </label>
                  <select id="schilo_sujet" name="schilo_sujet" required>
                    <?php foreach ( $sujets as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"
                      <?php selected( $form_values['sujet'] ?? '', $val ); ?>>
                      <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Zone de guide dynamique (JS la remplit selon le sujet) -->
              <div id="schilo-contact-guide" class="schilo-contact-guide" aria-live="polite" style="display:none">

                <!-- Conseil de rédaction -->
                <div class="schilo-contact-guide__conseil" id="schilo-guide-conseil" style="display:none">
                  <div class="schilo-contact-guide__conseil-inner">
                    <i class="ti ti-bulb" aria-hidden="true"></i>
                    <span id="schilo-guide-conseil-text"></span>
                  </div>
                </div>

                <!-- Questions préétablies -->
                <div class="schilo-contact-guide__questions" id="schilo-guide-questions" style="display:none">
                  <div class="schilo-contact-guide__questions-label">
                    <i class="ti ti-list-check" aria-hidden="true"></i>
                    <?php esc_html_e( 'Cliquez sur une question pour l\'utiliser comme point de départ :', 'schilo' ); ?>
                  </div>
                  <div class="schilo-contact-guide__questions-list" id="schilo-guide-questions-list"></div>
                </div>

              </div>

              <!-- Message -->
              <div class="schilo-contact-group schilo-contact-group--full">
                <label for="schilo_message">
                  <?php esc_html_e( 'Message', 'schilo' ); ?>
                  <span class="schilo-contact-req" aria-hidden="true">*</span>
                </label>
                <textarea id="schilo_message" name="schilo_message"
                          placeholder="<?php esc_attr_e( 'Votre message… Choisissez d\'abord un sujet pour obtenir de l\'aide à la rédaction.', 'schilo' ); ?>"
                          rows="6" required><?php echo esc_textarea( $form_values['message'] ?? '' ); ?></textarea>
              </div>

              <!-- Newsletter -->
              <div class="schilo-contact-group schilo-contact-group--full schilo-contact-group--checkbox">
                <label for="schilo_newsletter">
                  <input type="checkbox" id="schilo_newsletter" name="schilo_newsletter"
                         <?php checked( $form_values['newsletter'] ?? false ); ?>>
                  <?php esc_html_e( 'Je souhaite recevoir les nouvelles fiches et parcours par e-mail (optionnel)', 'schilo' ); ?>
                </label>
              </div>

              <!-- Submit -->
              <div class="schilo-contact-submit">
                <span class="schilo-contact-privacy">
                  <i class="ti ti-lock" aria-hidden="true"></i>
                  <?php esc_html_e( 'Données protégées — jamais partagées', 'schilo' ); ?>
                </span>
                <button type="submit" class="schilo-btn schilo-btn--dark">
                  <i class="ti ti-send" aria-hidden="true"></i>
                  <?php esc_html_e( 'Envoyer le message', 'schilo' ); ?>
                </button>
              </div>

            </form>
          </div>
        </div>

        <!-- Texte de bas de page -->
        <?php if ( ! $form_sent ) : ?>
        <div style="margin-top:1.25rem;padding:1.25rem;background:var(--schilo-bg-muted);border-radius:12px;border:1px solid var(--schilo-border)">
          <div style="display:flex;align-items:flex-start;gap:.75rem">
            <i class="ti ti-shield-check" style="font-size:18px;color:var(--schilo-luc-dark);margin-top:2px;flex-shrink:0" aria-hidden="true"></i>
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.35rem">
                <?php esc_html_e( 'Confidentialité & respect', 'schilo' ); ?>
              </div>
              <p style="margin:0;font-size:12px;color:var(--schilo-text-secondary);line-height:1.7">
                <?php esc_html_e( 'Vos données personnelles ne sont jamais partagées ni revendues. Elles sont utilisées uniquement pour répondre à votre message. Vous pouvez nous demander leur suppression à tout moment.', 'schilo' ); ?>
              </p>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>

      <!-- ── SIDEBAR ── -->
      <aside class="schilo-sidebar">

        <!-- Bloc : Pourquoi nous écrire -->
        <div class="schilo-card" style="margin-bottom:1rem">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-help" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Pourquoi nous écrire ?', 'schilo' ); ?></span>
            </div>
          </div>
          <div class="schilo-card__body" style="padding-top:.75rem">
            <div class="schilo-contact-info">
              <div class="schilo-contact-info__item">
                <div class="schilo-contact-info__icon"><i class="ti ti-book-2" aria-hidden="true"></i></div>
                <div>
                  <div class="schilo-contact-info__label"><?php esc_html_e( 'Question biblique', 'schilo' ); ?></div>
                  <div class="schilo-contact-info__sub"><?php esc_html_e( 'Un verset, un passage, un thème — posez votre question.', 'schilo' ); ?></div>
                </div>
              </div>
              <div class="schilo-contact-info__item">
                <div class="schilo-contact-info__icon"><i class="ti ti-alert-triangle" aria-hidden="true"></i></div>
                <div>
                  <div class="schilo-contact-info__label"><?php esc_html_e( 'Signaler une erreur', 'schilo' ); ?></div>
                  <div class="schilo-contact-info__sub"><?php esc_html_e( 'Vous avez repéré une imprécision ? Aidez-nous à améliorer.', 'schilo' ); ?></div>
                </div>
              </div>
              <div class="schilo-contact-info__item">
                <div class="schilo-contact-info__icon"><i class="ti ti-bulb" aria-hidden="true"></i></div>
                <div>
                  <div class="schilo-contact-info__label"><?php esc_html_e( 'Suggérer un contenu', 'schilo' ); ?></div>
                  <div class="schilo-contact-info__sub"><?php esc_html_e( 'Un sujet manquant ? Une idée de parcours ? Partagez-la.', 'schilo' ); ?></div>
                </div>
              </div>
              <div class="schilo-contact-info__item" style="border:none">
                <div class="schilo-contact-info__icon"><i class="ti ti-clock" aria-hidden="true"></i></div>
                <div>
                  <div class="schilo-contact-info__label"><?php esc_html_e( 'Délai de réponse', 'schilo' ); ?></div>
                  <div class="schilo-contact-info__sub"><?php esc_html_e( 'En général sous 48 h, jours ouvrés.', 'schilo' ); ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bloc : À propos du site -->
        <div class="schilo-card" style="margin-bottom:1rem">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-flame" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'À propos de Schilo', 'schilo' ); ?></span>
            </div>
          </div>
          <div class="schilo-card__body" style="padding-top:.75rem">
            <p class="schilo-sb-desc" style="margin:0 0 1rem;font-size:12px;color:var(--schilo-text-secondary);line-height:1.7">
              <?php esc_html_e( 'Schilo.org est un site d\'étude biblique indépendant, gratuit et sans publicité, consacré à la découverte de Jésus à travers les quatre Évangiles.', 'schilo' ); ?>
            </p>
            <a href="<?php echo esc_url( home_url( '/parcours/' ) ); ?>"
               style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--schilo-luc-dark);text-decoration:none">
              <i class="ti ti-route" style="font-size:13px" aria-hidden="true"></i>
              <?php esc_html_e( 'Découvrir les parcours', 'schilo' ); ?>
            </a>
          </div>
        </div>

        <!-- Bloc : FAQ rapide -->
        <div class="schilo-card">
          <div class="schilo-card__head">
            <div class="schilo-card__head-left">
              <div class="schilo-card__icon schilo-card__icon--dark">
                <i class="ti ti-message-question" aria-hidden="true"></i>
              </div>
              <span class="schilo-card__title"><?php esc_html_e( 'Questions fréquentes', 'schilo' ); ?></span>
            </div>
          </div>
          <div class="schilo-card__body" style="padding-top:.75rem">
            <div style="display:flex;flex-direction:column;gap:.85rem">
              <div>
                <div style="font-size:12px;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.25rem"><?php esc_html_e( 'Le site est-il gratuit ?', 'schilo' ); ?></div>
                <p style="margin:0;font-size:12px;color:var(--schilo-text-secondary);line-height:1.65"><?php esc_html_e( 'Oui, entièrement. Aucune inscription, aucune publicité, aucun abonnement.', 'schilo' ); ?></p>
              </div>
              <div style="border-top:1px solid var(--schilo-border);padding-top:.85rem">
                <div style="font-size:12px;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.25rem"><?php esc_html_e( 'Puis-je citer vos fiches ?', 'schilo' ); ?></div>
                <p style="margin:0;font-size:12px;color:var(--schilo-text-secondary);line-height:1.65"><?php esc_html_e( 'Oui, avec mention de la source (schilo.org). Contactez-nous pour toute utilisation commerciale.', 'schilo' ); ?></p>
              </div>
              <div style="border-top:1px solid var(--schilo-border);padding-top:.85rem">
                <div style="font-size:12px;font-weight:600;color:var(--schilo-text-primary);margin-bottom:.25rem"><?php esc_html_e( 'Êtes-vous affiliés à une église ?', 'schilo' ); ?></div>
                <p style="margin:0;font-size:12px;color:var(--schilo-text-secondary);line-height:1.65"><?php esc_html_e( 'Non. Schilo.org est indépendant de toute église, dénomination ou organisation.', 'schilo' ); ?></p>
              </div>
            </div>
          </div>
        </div>

      </aside>

    </div>
  </div>
</main>

<?php wp_localize_script( 'schilo-contact', 'schiloContactGuides', $guides ); ?>
<?php get_footer(); ?>
