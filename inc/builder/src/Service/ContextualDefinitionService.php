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
                ? $override['terms']
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

        $withoutArticle = preg_replace('/^(?:le|la|les|l[’\']|un|une|des)\s+/iu', '', $label);
        $mainTerm = trim((string)$withoutArticle);

        return $mainTerm !== '' ? array($mainTerm) : array($label);
    }

    public function extractCode(string $title): string
    {
        return preg_match('/^([A-Z]{2,10}\s*\d+)/u', trim($title), $match)
            ? preg_replace('/\s+/', '', $match[1])
            : 'INFO';
    }

    private function sanitizeTerms(string $terms): array
    {
        $items = preg_split('/[,;\r\n]+/u', $terms);
        $items = array_map(static fn($term) => sanitize_text_field(trim($term)), $items ?: array());
        return array_values(array_unique(array_filter($items, static fn($term) => mb_strlen($term) >= 3)));
    }
}
