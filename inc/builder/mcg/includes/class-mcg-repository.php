<?php
namespace MCG;

if (!defined('ABSPATH')) exit;

final class Repository {

    public static function get_posts(array $args): \WP_Query {
        $paged   = max(1, (int)($args['paged'] ?? 1));
        $perPage = max(1, min(48, (int)($args['per_page'] ?? 9)));
        $cat     = (int)($args['cat'] ?? 0);
        $term    = (int)($args['term'] ?? 0);
        $orderBy = sanitize_key($args['orderby'] ?? 'date');

        $taxQuery = [];
        if ($term > 0) {
            $taxQuery[] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => [$term],
            ];
        } elseif ($cat > 0) {
            $taxQuery[] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => [$cat],
            ];
        }

        $queryArgs = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $perPage,
            'paged'               => $paged,
            'ignore_sticky_posts' => true,
            'tax_query'           => $taxQuery ?: null,
        ];

        switch ($orderBy) {
            case 'title':
                $queryArgs['orderby'] = 'title';
                $queryArgs['order'] = 'ASC';
                break;
            case 'rand':
                $queryArgs['orderby'] = 'rand';
                break;
            case 'popular':
                $queryArgs['meta_key'] = 'mcg_views';
                $queryArgs['orderby']  = 'meta_value_num';
                $queryArgs['order']    = 'DESC';
                break;
            default:
                $queryArgs['orderby'] = 'date';
                $queryArgs['order'] = 'DESC';
        }

        return new \WP_Query($queryArgs);
    }
}
