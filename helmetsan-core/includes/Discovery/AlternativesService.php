<?php

declare(strict_types=1);

namespace Helmetsan\Core\Discovery;

use WP_Post;

/**
 * Discovery Engine: Identifies "Technical Twins" and similar products
 * to provide alternative suggestions for out-of-stock items.
 */
final class AlternativesService
{
    /**
     * Find similar helmets based on technical DNA.
     * 
     * @return array<int> List of post IDs
     */
    public function findAlternatives(int $helmetId, int $limit = 5): array
    {
        $types = wp_get_post_terms($helmetId, 'helmet_type', ['fields' => 'ids']);
        $certs = wp_get_post_terms($helmetId, 'certification', ['fields' => 'ids']);
        $price = wp_get_post_terms($helmetId, 'price_range', ['fields' => 'ids']);

        $args = [
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $limit + 1, // +1 to exclude current
            'post__not_in'   => [$helmetId],
            'tax_query'      => [
                'relation' => 'OR',
            ]
        ];

        if (! empty($types)) {
            $args['tax_query'][] = [
                'taxonomy' => 'helmet_type',
                'field'    => 'term_id',
                'terms'    => $types,
            ];
        }

        if (! empty($certs)) {
             $args['tax_query'][] = [
                'taxonomy' => 'certification',
                'field'    => 'term_id',
                'terms'    => $certs,
                'boost'    => 2, // Prefer same safety level
            ];
        }

        if (! empty($price)) {
             $args['tax_query'][] = [
                'taxonomy' => 'price_range',
                'field'    => 'term_id',
                'terms'    => $price,
            ];
        }

        $query = new \WP_Query($args);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];

        // Simple ranking logic: count how many metadata matches we have
        $weighted = [];
        foreach ($ids as $id) {
            $weight = 0;
            $idTypes = wp_get_post_terms($id, 'helmet_type', ['fields' => 'ids']);
            $idCerts = wp_get_post_terms($id, 'certification', ['fields' => 'ids']);
            
            if (! empty($types) && array_intersect($types, $idTypes)) $weight += 3;
            if (! empty($certs) && array_intersect($certs, $idCerts)) $weight += 5; // Safety is key
            
            $weighted[$id] = $weight;
        }

        arsort($weighted);
        return array_slice(array_keys($weighted), 0, $limit);
    }

    /**
     * Sync alternatives to post meta for fast frontend retrieval.
     */
    public function syncAlternatives(int $helmetId): void
    {
        $ids = $this->findAlternatives($helmetId);
        update_post_meta($helmetId, '_hs_ai_alternatives', $ids);
    }

    /**
     * Get cached alternatives.
     */
    public function getRecommended(int $helmetId): array
    {
        $ids = get_post_meta($helmetId, '_hs_ai_alternatives', true);
        if (! is_array($ids)) {
            $ids = $this->findAlternatives($helmetId);
            update_post_meta($helmetId, '_hs_ai_alternatives', $ids);
        }
        return $ids;
    }
}

