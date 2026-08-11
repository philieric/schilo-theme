<?php

namespace Schilo\Builder\Service;

class ArticleTitleNumberer
{
    public function filterPostData($data, $postarr)
    {
        if (!is_array($data)) {
            return $data;
        }

        if (!isset($data['post_type']) || $data['post_type'] !== 'post') {
            return $data;
        }

        if (isset($data['post_status']) && in_array($data['post_status'], array('auto-draft', 'trash'), true)) {
            return $data;
        }

        $postId = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        $title = isset($data['post_title']) ? (string) $data['post_title'] : '';

        $newTitle = $this->normalizeTitle($title, $postId);

        if ($newTitle !== null) {
            $data['post_title'] = $newTitle;
            $data['post_name'] = sanitize_title($newTitle);
        }

        return $data;
    }

    public function normalizeAfterSave($postId, $post, $update)
    {
        if (!$post || !isset($post->post_type) || $post->post_type !== 'post') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (!current_user_can('edit_post', (int) $postId)) {
            return;
        }

        $title = get_the_title((int) $postId);
        $newTitle = $this->normalizeTitle($title, (int) $postId);

        if ($newTitle === null || $newTitle === $title) {
            return;
        }

        remove_action('save_post', array($this, 'normalizeAfterSave'), 999);

        wp_update_post(array(
            'ID' => (int) $postId,
            'post_title' => $newTitle,
            'post_name' => sanitize_title($newTitle),
        ));

        add_action('save_post', array($this, 'normalizeAfterSave'), 999, 3);
    }

    /**
     * Normalisation stricte du titre.
     *
     * Règles :
     * - PER - Titre => prochain numéro disponible.
     * - PER77 Titre => PER077 - Titre.
     * - PER700 Titre, meme si le dernier existant est PER458 => PER700 - Titre
     *   (le numéro saisi fait foi, y compris "en avance" sur la séquence :
     *   convention volontaire, ex. +100 pour la version académique d'une
     *   parabole — PAR001 grand public / PAR101 académique).
     * - Si le numéro est déjà utilisé par un AUTRE article => refusé (titre
     *   inchangé, pas de renumérotation silencieuse), avec un message
     *   d'erreur affiché à l'utilisateur (voir markDuplicateWarning()).
     */
    private function normalizeTitle($title, $currentPostId)
    {
        $originalTitle = trim((string) $title);
        $title = html_entity_decode($originalTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim($title);

        if ($title === '') {
            return null;
        }

        // Cas 1 : préfixe + numéro explicite.
        if (preg_match('/^([A-Za-z]{3})(\d+)(.*)$/u', $title, $matches)) {
            $prefix = strtoupper($matches[1]);
            $typedNumber = (int) $matches[2];
            $afterNumber = trim($matches[3]);

            $cleanTitle = $this->extractRealTitle($afterNumber);

            if ($cleanTitle === '') {
                return null;
            }

            list($number, $digits) = $this->resolveNumberAndDigits($prefix, $typedNumber, (int) $currentPostId);

            $conflictPostId = $this->numberExistsForAnotherPost($prefix, $number, (int) $currentPostId);

            if ($conflictPostId) {
                $this->markDuplicateWarning($prefix, $number, $conflictPostId);
                return null;
            }

            $normalizedTitle = sprintf('%s%0' . $digits . 'd - %s', $prefix, $number, $cleanTitle);

            return $normalizedTitle !== $originalTitle ? $normalizedTitle : null;
        }

        // Cas 2 : préfixe sans numéro.
        if (preg_match('/^([A-Za-z]{3})(.*)$/u', $title, $matches)) {
            $prefix = strtoupper($matches[1]);
            $afterPrefix = trim($matches[2]);

            $cleanTitle = $this->extractRealTitle($afterPrefix);

            if ($cleanTitle === '') {
                return null;
            }

            $digits = $this->digitsForPrefix($prefix);
            $number = $this->getNextAvailableNumberForPrefix($prefix, (int) $currentPostId, $digits);

            return sprintf('%s%0' . $digits . 'd - %s', $prefix, $number, $cleanTitle);
        }

        return null;
    }

    private function extractRealTitle($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);

        $text = preg_replace('/^[\s\-\–\—\:\/\\\\|]+/u', '', $text);
        $text = preg_replace('/[\s\-\–\—\:\/\\\\|]+$/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return $this->ucfirstUnicode($text);
    }

    private function ucfirstUnicode($text)
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $first = mb_substr($text, 0, 1, 'UTF-8');
            $rest = mb_substr($text, 1, null, 'UTF-8');

            return mb_strtoupper($first, 'UTF-8') . $rest;
        }

        return strtoupper(substr($text, 0, 1)) . substr($text, 1);
    }

    /**
     * @return int ID de l'article en conflit, ou 0 si le numéro est libre.
     */
    private function numberExistsForAnotherPost($prefix, $number, $currentPostId)
    {
        global $wpdb;

        $like = $wpdb->esc_like($prefix) . '%';

        $titles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title
                 FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                 AND ID != %d
                 AND post_status NOT IN ('trash', 'auto-draft')
                 AND post_title LIKE %s",
                (int) $currentPostId,
                $like
            )
        );

        foreach ($titles as $row) {
            $existingTitle = html_entity_decode((string) $row->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)/i', $existingTitle, $matches)) {
                if ((int) $matches[1] === (int) $number) {
                    return (int) $row->ID;
                }
            }
        }

        return 0;
    }

    /**
     * Mémorise un conflit de numéro pour l'utilisateur courant (transient
     * de courte durée), affiché ensuite par renderDuplicateNotice() au
     * prochain chargement d'écran admin — voir Plugin.php pour l'action
     * 'admin_notices' correspondante.
     */
    private function markDuplicateWarning($prefix, $number, $conflictPostId)
    {
        $userId = get_current_user_id();

        if (!$userId) {
            return;
        }

        set_transient('schilo_dup_number_' . $userId, array(
            'prefix' => $prefix,
            'number' => (int) $number,
            'conflict_id' => (int) $conflictPostId,
        ), 60);
    }

    /**
     * Affiche, une seule fois, l'avertissement de doublon de numéro laissé
     * par markDuplicateWarning() lors de la sauvegarde précédente.
     *
     * Appelé directement par BuilderMetabox::renderAfterTitle() plutôt que
     * via le hook 'admin_notices' : functions.php nettoie agressivement
     * cette liste (retire tout callback dont le fichier contient
     * "wp-content/", donc aussi bien les nags de plugins tiers que nos
     * propres notices), et une règle CSS globale masque ".notice" partout
     * sauf ".schilo-keep". Rendu en style autonome pour éviter les deux.
     */
    public function renderDuplicateNotice()
    {
        $userId = get_current_user_id();

        if (!$userId) {
            return;
        }

        $key = 'schilo_dup_number_' . $userId;
        $data = get_transient($key);

        if (!$data || !is_array($data)) {
            return;
        }

        delete_transient($key);

        $conflictTitle = !empty($data['conflict_id']) ? get_the_title((int) $data['conflict_id']) : '';
        $numberFormatted = sprintf('%s%0' . $this->digitsForPrefix($data['prefix']) . 'd', $data['prefix'], (int) $data['number']);

        printf(
            '<div class="schilo-keep" style="margin:0 0 18px;padding:12px 16px;border:1px solid #f5c6cb;border-radius:8px;background:#fdecea;color:#7a1f22;font-size:13px;line-height:1.6;">' .
                '<strong>%s</strong><br>%s <strong>%s</strong> %s%s<br>%s' .
            '</div>',
            esc_html__('Numéro déjà utilisé', 'schilo'),
            esc_html__('Le numéro', 'schilo'),
            esc_html($numberFormatted),
            esc_html__('est déjà utilisé par un autre article', 'schilo'),
            $conflictTitle ? ' (« ' . esc_html($conflictTitle) . ' »).' : '.',
            esc_html__('Le titre n’a pas été renuméroté automatiquement : choisissez un autre numéro.', 'schilo')
        );
    }

    /**
     * Nombre de chiffres de numerotation configure pour ce prefixe (3 par
     * defaut, personnalisable par prefixe sur Schilo Builder > Types &
     * templates — ex. ANX0001 sur 4 chiffres si le volume d'annexes le
     * justifie un jour).
     */
    private function digitsForPrefix($prefix)
    {
        return (new TemplateService())->getDigitsForPrefix($prefix);
    }

    /**
     * Resout le couple [numero, nombre de chiffres] a utiliser pour CE
     * prefixe+numero precis.
     *
     * Un article deja existant en base avec ce meme prefixe+numero garde la
     * largeur qu'il a deja, meme s'il est resauvegarde apres un changement
     * du reglage global (ex. PER passe de 3 a 4 chiffres) : "PER373" reste
     * "PER373" et n'est jamais force en "PER0373". Objectif : ne jamais
     * changer silencieusement le slug/URL d'un article deja publie et
     * indexe.
     *
     * Pour un numero veritablement nouveau pour ce post (nouvel article, ou
     * numero explicitement change), le reglage actuellement configure pour
     * le prefixe s'applique — mais s'il existe deja des articles "hérités"
     * sur ce meme prefixe avec moins de chiffres (cas d'une augmentation du
     * reglage, ex. PER 3 -> 4), on evite le zero de tete qui preterait a
     * confusion avec ces anciens numeros (ex. "PER0402") : le numero est
     * decale dans le bloc de dizaine superieur correspondant a la nouvelle
     * largeur ("PER1402" plutot que "PER0402"), meme principe que la
     * convention +100 deja utilisee pour distinguer PAR001/PAR101.
     *
     * @return array{0:int,1:int} [numero final, nombre de chiffres]
     */
    private function resolveNumberAndDigits($prefix, $number, $currentPostId)
    {
        $number = (int) $number;

        if ($currentPostId > 0) {
            $currentTitle = get_the_title($currentPostId);
            $currentTitle = html_entity_decode((string) $currentTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)/i', $currentTitle, $matches)) {
                if ((int) $matches[1] === $number) {
                    return array($number, strlen($matches[1]));
                }
            }
        }

        $digits = $this->digitsForPrefix($prefix);

        if ($this->hasLegacyShorterWidth($prefix, $digits, $currentPostId)) {
            $threshold = (int) pow(10, $digits - 1);

            if ($number < $threshold) {
                $number += $threshold;
            }
        }

        return array($number, $digits);
    }

    /**
     * Vrai s'il existe, pour ce prefixe, au moins un autre article dont le
     * numero est ecrit sur moins de chiffres que $digits (numerotation
     * "heritee" d'avant une augmentation du reglage).
     */
    private function hasLegacyShorterWidth($prefix, $digits, $currentPostId)
    {
        global $wpdb;

        $like = $wpdb->esc_like($prefix) . '%';

        $titles = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_title
                 FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                 AND ID != %d
                 AND post_status NOT IN ('trash', 'auto-draft')
                 AND post_title LIKE %s",
                (int) $currentPostId,
                $like
            )
        );

        foreach ($titles as $existingTitle) {
            $existingTitle = html_entity_decode((string) $existingTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)/i', $existingTitle, $matches)) {
                if (strlen($matches[1]) < $digits) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Prochain numero disponible pour un nouvel article de ce prefixe.
     *
     * S'il existe une numerotation heritee sur moins de chiffres que
     * $digits (cas d'une augmentation du reglage), le prochain numero est
     * pris dans le bloc de dizaine correspondant a la nouvelle largeur
     * (ex. a partir de 1000 pour 4 chiffres) plutot que de continuer la
     * sequence heritee avec un zero de tete — voir resolveNumberAndDigits().
     */
    private function getNextAvailableNumberForPrefix($prefix, $currentPostId, $digits)
    {
        $usedNumbers = $this->getUsedNumbersForPrefix($prefix, $currentPostId);

        if ($this->hasLegacyShorterWidth($prefix, $digits, $currentPostId)) {
            $threshold = (int) pow(10, $digits - 1);
            $usedInBlock = array_filter($usedNumbers, function ($n) use ($threshold) {
                return $n >= $threshold;
            });

            return empty($usedInBlock) ? $threshold : max($usedInBlock) + 1;
        }

        if (empty($usedNumbers)) {
            return 1;
        }

        return max($usedNumbers) + 1;
    }

    private function getUsedNumbersForPrefix($prefix, $currentPostId)
    {
        global $wpdb;

        $like = $wpdb->esc_like($prefix) . '%';

        $titles = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_title
                 FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                 AND ID != %d
                 AND post_status NOT IN ('trash', 'auto-draft')
                 AND post_title LIKE %s",
                (int) $currentPostId,
                $like
            )
        );

        $used = array();

        foreach ($titles as $title) {
            $title = html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)/i', $title, $matches)) {
                $used[] = (int) $matches[1];
            }
        }

        return array_unique($used);
    }
}
