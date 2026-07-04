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
     * - PER700 Titre, si le dernier est PER458 => PER459 - Titre.
     * - Si le numéro existe déjà => prochain numéro disponible.
     * - Si le numéro est supérieur au prochain numéro réel => prochain numéro réel.
     */
    private function normalizeTitle($title, $currentPostId)
    {
        $originalTitle = trim((string) $title);
        $title = html_entity_decode($originalTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim($title);

        if ($title === '') {
            return null;
        }

        // Cas 1 : préfixe + numéro existant.
        if (preg_match('/^([A-Za-z]{3})(\d+)(.*)$/u', $title, $matches)) {
            $prefix = strtoupper($matches[1]);
            $number = (int) $matches[2];
            $afterNumber = trim($matches[3]);

            $cleanTitle = $this->extractRealTitle($afterNumber);

            if ($cleanTitle === '') {
                return null;
            }

            $nextAvailable = $this->getNextAvailableNumberForPrefix($prefix, (int) $currentPostId);

            /*
             * Si le numéro existe déjà OU s'il est supérieur au prochain numéro logique,
             * on force le prochain numéro disponible.
             *
             * Exemple : dernier PER458, saisie PER700 => PER459.
             */
            if (
                $this->numberExistsForAnotherPost($prefix, $number, (int) $currentPostId)
                || $number > $nextAvailable
            ) {
                $number = $nextAvailable;
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
                    return true;
                }
            }
        }

        return false;
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
