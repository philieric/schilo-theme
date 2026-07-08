<?php

namespace Schilo\Builder\Service;

class ContextualDefinitionService
{
    public const OPTION_NAME = 'schilo_builder_contextual_definitions';

    public function getSettings(): array
    {
        $saved = get_option(self::OPTION_NAME, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), array(
            'enabled' => 1,
            'include_biblical_references' => 1,
            'prefixes' => 'INF',
            'definitions' => array(),
        ));
    }

    public function saveSettings(array $input): void
    {
        $definitions = array();
        foreach ((array)($input['definitions'] ?? array()) as $postId => $row) {
            $postId = absint($postId);
            if (!$postId) continue;
            $definitions[$postId] = array(
                'enabled' => empty($row['enabled']) ? 0 : 1,
                'terms' => $this->sanitizeTerms((string)($row['terms'] ?? '')),
            );
        }

        update_option(self::OPTION_NAME, array(
            'enabled' => empty($input['enabled']) ? 0 : 1,
            'include_biblical_references' => empty($input['include_biblical_references']) ? 0 : 1,
            'prefixes' => strtoupper(sanitize_text_field((string)($input['prefixes'] ?? 'INF'))),
            'definitions' => $definitions,
        ), false);
    }

    public function getSourcePosts(): array
    {
        $settings = $this->getSettings();
        $prefixes = array_filter(array_map('trim', explode(',', $settings['prefixes'])));
        if (!$prefixes) return array();

        global $wpdb;
        $conditions = array();
        $params = array();
        foreach ($prefixes as $prefix) {
            $conditions[] = 'post_title LIKE %s';
            $params[] = $wpdb->esc_like($prefix) . '%';
        }
        $sql = "SELECT ID, post_title, post_name FROM {$wpdb->posts}
                WHERE post_type = 'post' AND post_status = 'publish'
                  AND (" . implode(' OR ', $conditions) . ")
                ORDER BY post_title ASC";
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public function getDefinitions(): array
    {
        $settings = $this->getSettings();
        if (empty($settings['enabled'])) return array();

        $definitions = array();
        foreach ($this->getSourcePosts() as $post) {
            $override = $settings['definitions'][$post->ID] ?? null;
            if (is_array($override) && empty($override['enabled'])) continue;
            $terms = is_array($override) && !empty($override['terms'])
                ? $this->sanitizeTerms(implode("\n", (array)$override['terms']))
                : $this->deriveTerms($post->post_title);
            if (!$terms) continue;
            $definitions[] = array(
                'source_id' => (int)$post->ID,
                'code' => $this->extractCode($post->post_title),
                'title' => $post->post_title,
                'terms' => $terms,
            );
        }

        usort($definitions, static function (array $a, array $b): int {
            return mb_strlen($b['terms'][0] ?? '') <=> mb_strlen($a['terms'][0] ?? '');
        });
        return $definitions;
    }

    public function deriveTerms(string $title): array
    {
        $label = preg_replace('/^[A-Z]{2,10}\s*\d+\s*[-–—:._]*\s*/u', '', trim($title));
        $label = trim(wp_strip_all_tags((string)$label), " \t\n\r\0\x0B-–—:._");
        if ($label === '') return array();

        $withoutArticle = preg_replace('/^(?:le|la|les|un|une|des)\s+/iu', '', $label);
        $mainTerm = $this->normalizeTrigger((string)$withoutArticle);
        if ($mainTerm === '') return array();

        $suggestions = array($mainTerm);
        if (strpos($mainTerm, ' ') === false && strpos($mainTerm, "'") === false) {
            $variant = mb_substr($mainTerm, -1, 1, 'UTF-8') === 's'
                ? mb_substr($mainTerm, 0, -1, 'UTF-8')
                : $mainTerm . 's';
            if (mb_strlen($variant, 'UTF-8') >= 3) $suggestions[] = $variant;
        }

        return array_values(array_unique($suggestions));
    }

    public function extractCode(string $title): string
    {
        return preg_match('/^([A-Z]{2,10}\s*\d+)/u', trim($title), $match)
            ? preg_replace('/\s+/', '', $match[1])
            : 'INFO';
    }

    public function normalizeTrigger(string $term): string
    {
        $term = wp_strip_all_tags($term);
        $term = str_replace('’', "'", $term);
        $isBiblicalReference = (bool)preg_match('/\p{L}[\p{L}\s\'’.:-]*\s\d+(?:[.:]\d+)(?:\s*[-–]\s*\d+)?/u', $term);
        $term = str_replace("'", 'SCHILOAPOSTROPHEPLACEHOLDER', $term);
        if ($isBiblicalReference) {
            $term = str_replace(
                array('.', ':', '-', '–', ','),
                array('SCHILODOTPLACEHOLDER', 'SCHILOCOLONPLACEHOLDER', 'SCHILODASHPLACEHOLDER', 'SCHILODASHPLACEHOLDER', 'SCHILOCOMMAPLACEHOLDER'),
                $term
            );
        }
        $term = preg_replace('/[\p{P}\p{S}]+/u', ' ', $term);
        $term = str_replace('SCHILOAPOSTROPHEPLACEHOLDER', "'", (string)$term);
        if ($isBiblicalReference) {
            $term = str_replace(
                array('SCHILODOTPLACEHOLDER', 'SCHILOCOLONPLACEHOLDER', 'SCHILODASHPLACEHOLDER', 'SCHILOCOMMAPLACEHOLDER'),
                array('.', ':', '-', ','),
                $term
            );
        }
        $term = preg_replace('/\s+/u', ' ', (string)$term);
        $term = mb_strtolower(trim((string)$term), 'UTF-8');
        $term = preg_replace('/^(?:le|la|les|un|une|des)\s+/u', '', $term);

        return trim((string)$term);
    }

    public function suggestTermsViaIA(int $postId, string $provider, string $existingTerms = ''): array|\WP_Error
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'post') {
            return new \WP_Error('invalid_definition', 'Fiche de définition introuvable.');
        }

        $content = wp_strip_all_tags((string)$post->post_content);
        $sections = get_post_meta($postId, '_schilo_builder_sections', true);
        if (is_array($sections)) {
            foreach ($sections as $section) {
                $content .= ' ' . wp_strip_all_tags((string)($section['title'] ?? ''));
                $content .= ' ' . wp_strip_all_tags((string)($section['content'] ?? ''));
            }
        }
        $content = wp_trim_words($content, 900, '');
        $code = $this->extractCode($post->post_title);
        $currentTerms = $this->sanitizeTerms($existingTerms);

        $prompt = "Tu configures les DECLENCHEURS de la fiche de définition {$code} affichée sur cette ligne du tableau Schilo Builder.\n"
            . "Un déclencheur est un mot ou une courte expression susceptible d'apparaître dans un autre article. Lorsqu'il est rencontré, il ouvre la fenêtre modale de cette fiche {$code}.\n"
            . "Tu ne dois ni rédiger la définition, ni résumer la fiche, ni proposer des thèmes voisins.\n\n"
            . "Fiche INF de la ligne : " . $post->post_title . "\n"
            . "Déclencheurs actuellement présents sur la ligne : " . ($currentTerms ? implode(', ', $currentTerms) : '(aucun)') . "\n"
            . "Contenu permettant d'identifier précisément le terme défini : " . $content . "\n\n"
            . "Retourne entre 3 et 8 déclencheurs strictement équivalents au terme défini : nom principal, singulier, pluriel, variantes orthographiques et synonymes non ambigus.\n"
            . "Chaque proposition doit pouvoir remplacer le terme principal dans une phrase sans changer le sujet de la définition.\n"
            . "Contraintes : minuscules uniquement, aucun article séparé en début, aucune ponctuation sauf l'apostrophe et la ponctuation indispensable d'une référence biblique, aucun doublon, aucun terme générique, aucun mot seulement associé au sujet. Conserve les formes élidées comme l'Égypte avec leur apostrophe et les références comme Matthieu 9.11-13.\n"
            . "Retourne UNIQUEMENT ce JSON, sans markdown : {\"triggers\":[\"terme 1\",\"terme 2\"]}";

        $raw = (new ClassementService())->callIaRaw($provider, $prompt, 700);
        if (is_wp_error($raw)) return $raw;

        $clean = trim($raw);
        if (strpos($clean, '```') === 0) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
            $clean = rtrim((string)$clean, "` \t\n\r\0\x0B");
        }
        $parsed = json_decode($clean, true);
        if (!is_array($parsed) || !is_array($parsed['triggers'] ?? null)) {
            return new \WP_Error('invalid_ai_response', 'La réponse IA ne contient pas une liste de déclencheurs valide.');
        }

        $terms = $this->sanitizeTerms(implode("\n", $parsed['triggers']));
        return array_slice($terms, 0, 8);
    }

    private function sanitizeTerms(string $terms): array
    {
        $items = preg_split('/[,;\r\n]+/u', $terms);
        $normalized = array();
        foreach ($items ?: array() as $item) {
            $term = $this->normalizeTrigger(sanitize_text_field((string)$item));
            if (mb_strlen($term, 'UTF-8') < 3) continue;
            $normalized[$term] = $term;
        }

        return array_values($normalized);
    }
}
