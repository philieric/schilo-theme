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
            $number = (int) $matches[2];
            $afterNumber = trim($matches[3]);

            $cleanTitle = $this->extractRealTitle($afterNumber);

            if ($cleanTitle === '') {
                return null;
            }

            $conflictPostId = $this->numberExistsForAnotherPost($prefix, $number, (int) $currentPostId);

            if ($conflictPostId) {
                $this->markDuplicateWarning($prefix, $number, $conflictPostId);
                return null;
            }

            $normalizedTitle = sprintf('%s%03d - %s', $prefix, $number, $cleanTitle);

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

            $number = $this->getNextAvailableNumberForPrefix($prefix, (int) $currentPostId);

            return sprintf('%s%03d - %s', $prefix, $number, $cleanTitle);
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
        $numberFormatted = sprintf('%s%03d', $data['prefix'], (int) $data['number']);

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

    private function getNextAvailableNumberForPrefix($prefix, $currentPostId)
    {
        $usedNumbers = $this->getUsedNumbersForPrefix($prefix, $currentPostId);

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
