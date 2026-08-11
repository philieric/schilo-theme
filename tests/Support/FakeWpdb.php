<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Support;

/**
 * Double de test pour $wpdb : simule, sur un jeu de "posts" en mémoire, les
 * deux formes de requête utilisées par ArticleTitleNumberer (colonnes
 * ID/post_title, filtrées par post_type='post', ID != %d, post_status hors
 * trash/auto-draft, post_title LIKE %s) — sans base de données réelle.
 */
final class FakeWpdb
{
    public string $posts = 'wp_posts';

    /** @var array<int, array{ID:int, post_title:string, post_type:string, post_status:string}> */
    private array $rows;

    /**
     * @param array<int, array{ID:int, post_title:string, post_type?:string, post_status?:string}> $rows
     */
    public function __construct(array $rows = [])
    {
        $this->rows = array_map(static function (array $row): array {
            $row['post_type'] = $row['post_type'] ?? 'post';
            $row['post_status'] = $row['post_status'] ?? 'publish';

            return $row;
        }, $rows);
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[ds]/', $replacement, $query, 1);
        }

        return $query;
    }

    /** @return string[] */
    public function get_col(string $query): array
    {
        $column = $this->firstSelectedColumn($query);

        return array_map(
            static fn (array $row): string => (string) $row[$column],
            $this->executeQuery($query)
        );
    }

    /** @return \stdClass[] */
    public function get_results(string $query): array
    {
        return array_map(static function (array $row): \stdClass {
            $object = new \stdClass();
            foreach ($row as $key => $value) {
                $object->$key = $value;
            }

            return $object;
        }, $this->executeQuery($query));
    }

    /** @return array<int, array{ID:int, post_title:string, post_type:string, post_status:string}> */
    private function executeQuery(string $query): array
    {
        $excludeId = null;
        if (preg_match('/ID\s*!=\s*(\d+)/', $query, $matches)) {
            $excludeId = (int) $matches[1];
        }

        $likePrefix = '';
        if (preg_match("/LIKE\\s+'([^%']*)%'/", $query, $matches)) {
            $likePrefix = stripslashes($matches[1]);
        }

        return array_values(array_filter($this->rows, static function (array $row) use ($excludeId, $likePrefix): bool {
            if ($row['post_type'] !== 'post') {
                return false;
            }

            if (in_array($row['post_status'], ['trash', 'auto-draft'], true)) {
                return false;
            }

            if ($excludeId !== null && $row['ID'] === $excludeId) {
                return false;
            }

            if ($likePrefix !== '' && stripos($row['post_title'], $likePrefix) !== 0) {
                return false;
            }

            return true;
        }));
    }

    private function firstSelectedColumn(string $query): string
    {
        if (preg_match('/SELECT\s+(.+?)\s+FROM/i', $query, $matches)) {
            $columns = array_map('trim', explode(',', $matches[1]));

            return $columns[0];
        }

        return 'post_title';
    }
}
