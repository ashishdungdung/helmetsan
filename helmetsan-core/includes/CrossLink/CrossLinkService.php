<?php

declare(strict_types=1);

namespace Helmetsan\Core\CrossLink;

use WP_Post;
use WP_Term;

/**
 * Suggests and optionally writes internal links (outgoing_internal_links_json) for helmets, brands, and accessories.
 * Used by CLI "wp helmetsan ai cross-link" and can be extended for admin "Suggest links".
 *
 * @see docs/ai-seeder-enrichment-roadmap.md Phase D
 */
final class CrossLinkService
{
    public const META_OUTGOING_LINKS = 'outgoing_internal_links_json';
    private const MAX_LINKS_PER_POST = 10;

    /**
     * Suggest related internal links for a post. Does not write meta.
     *
     * @return list<array{post_id: int, url: string, reason: string}>
     */
    public function suggestForPost(int $postId): array
    {
        $post = get_post($postId);
        if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
            return [];
        }
        if ($post->post_type === 'helmet') {
            return $this->suggestForHelmet($postId);
        }
        if ($post->post_type === 'brand') {
            return $this->suggestForBrand($postId);
        }
        if ($post->post_type === 'accessory') {
            return $this->suggestForAccessory($postId);
        }
        return [];
    }

    /**
     * Run cross-link suggestion for a batch of posts and optionally save to meta.
     *
     * @param 'helmet'|'brand'|'accessory'|'all' $postType
     * @return array{updated: int, skipped: int, total: int, dry_run: bool, by_reason: array<string, int>, total_links: int, posts_with_links: int}
     */
    public function run(string $postType, int $limit = 0, int $offset = 0, bool $dryRun = false): array
    {
        $types = $postType === 'all' ? ['helmet', 'brand', 'accessory'] : [$postType];
        $updated = 0;
        $skipped = 0;
        $total = 0;
        $byReason = [];
        $totalLinks = 0;

        foreach ($types as $type) {
            $postIds = $this->getPostIds($type, $limit, $offset);
            $total += count($postIds);
            foreach ($postIds as $pid) {
                $links = $this->suggestForPost($pid);
                if ($links === []) {
                    $skipped++;
                    continue;
                }
                $seenUrl = [];
                $deduped = [];
                foreach ($links as $link) {
                    $url = isset($link['url']) ? (string) $link['url'] : '';
                    if ($url !== '' && ! isset($seenUrl[$url])) {
                        $seenUrl[$url] = true;
                        $deduped[] = $link;
                    }
                }
                $links = $deduped;
                foreach ($links as $link) {
                    $reason = $link['reason'] ?? 'other';
                    $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
                }
                $totalLinks += count($links);
                if (! $dryRun) {
                    $json = wp_json_encode(array_values($links));
                    if (is_string($json)) {
                        update_post_meta($pid, self::META_OUTGOING_LINKS, $json);
                        $updated++;
                    }
                } else {
                    $updated++;
                }
            }
        }

        return [
            'updated'          => $updated,
            'skipped'          => $skipped,
            'total'            => $total,
            'dry_run'          => $dryRun,
            'by_reason'        => $byReason,
            'total_links'       => $totalLinks,
            'posts_with_links' => $updated,
        ];
    }

    /**
     * @return list<array{post_id: int, url: string, reason: string}>
     */
    private function suggestForHelmet(int $postId): array
    {
        $seen = [$postId => true];
        $out = [];
        $brandId = (int) get_post_meta($postId, 'rel_brand', true);
        $typeTermIds = $this->getTermIds($postId, 'helmet_type');
        $certTermIds = $this->getTermIds($postId, 'certification');
        $family = (string) get_post_meta($postId, 'helmet_family', true);

        if ($brandId > 0) {
            foreach ($this->queryHelmetsByBrand($brandId, self::MAX_LINKS_PER_POST, $postId) as $id) {
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $url = get_permalink($id);
                    if (is_string($url) && $url !== '') {
                        $out[] = ['post_id' => $id, 'url' => $url, 'reason' => 'same_brand'];
                        if (count($out) >= self::MAX_LINKS_PER_POST) {
                            return $out;
                        }
                    }
                }
            }
        }

        if ($typeTermIds !== []) {
            foreach ($this->queryHelmetsByTerms('helmet_type', $typeTermIds, self::MAX_LINKS_PER_POST, $postId) as $id) {
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $url = get_permalink($id);
                    if (is_string($url) && $url !== '') {
                        $out[] = ['post_id' => $id, 'url' => $url, 'reason' => 'same_type'];
                        if (count($out) >= self::MAX_LINKS_PER_POST) {
                            return $out;
                        }
                    }
                }
            }
        }

        if ($certTermIds !== []) {
            foreach ($this->queryHelmetsByTerms('certification', $certTermIds, self::MAX_LINKS_PER_POST, $postId) as $id) {
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $url = get_permalink($id);
                    if (is_string($url) && $url !== '') {
                        $out[] = ['post_id' => $id, 'url' => $url, 'reason' => 'same_cert'];
                        if (count($out) >= self::MAX_LINKS_PER_POST) {
                            return $out;
                        }
                    }
                }
            }
        }

        if ($family !== '') {
            foreach ($this->queryHelmetsByFamily($family, self::MAX_LINKS_PER_POST, $postId) as $id) {
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $url = get_permalink($id);
                    if (is_string($url) && $url !== '') {
                        $out[] = ['post_id' => $id, 'url' => $url, 'reason' => 'same_family'];
                        if (count($out) >= self::MAX_LINKS_PER_POST) {
                            return $out;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array{post_id: int, url: string, reason: string}>
     */
    private function suggestForBrand(int $postId): array
    {
        $postIds = $this->queryHelmetsByBrand($postId, self::MAX_LINKS_PER_POST, 0);
        $out = [];
        foreach ($postIds as $id) {
            $url = get_permalink($id);
            if (is_string($url) && $url !== '') {
                $out[] = ['post_id' => $id, 'url' => $url, 'reason' => 'brand_helmet'];
            }
        }
        return $out;
    }

    /**
     * @return list<array{post_id: int, url: string, reason: string}>
     */
    private function suggestForAccessory(int $postId): array
    {
        $termIds = $this->getTermIds($postId, 'accessory_category');
        if ($termIds === []) {
            return [];
        }
        $postIds = $this->queryAccessoriesByCategory($termIds, self::MAX_LINKS_PER_POST, $postId);
        $out = [];
        foreach ($postIds as $id) {
            $url = get_permalink($id);
            if (is_string($url) && $url !== '') {
                $out[] = ['post_id' => $id, 'url' => $url, 'reason' => 'same_category'];
            }
        }
        return $out;
    }

    /**
     * @return list<int>
     */
    private function getTermIds(int $postId, string $taxonomy): array
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (! is_array($terms) || $terms === []) {
            return [];
        }
        $ids = [];
        foreach ($terms as $t) {
            if ($t instanceof WP_Term) {
                $ids[] = $t->term_id;
            }
        }
        return $ids;
    }

    /**
     * @return list<int>
     */
    private function queryHelmetsByBrand(int $brandId, int $limit, int $excludePostId): array
    {
        $q = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'post__not_in'   => $excludePostId > 0 ? [$excludePostId] : [],
            'meta_query'     => [
                ['key' => 'rel_brand', 'value' => $brandId, 'compare' => '='],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $posts = $q->posts;
        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    /**
     * @param list<int> $termIds
     * @return list<int>
     */
    private function queryHelmetsByTerms(string $taxonomy, array $termIds, int $limit, int $excludePostId): array
    {
        $q = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'post__not_in'   => [$excludePostId],
            'tax_query'      => [
                ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $termIds],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $posts = $q->posts;
        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    /**
     * @return list<int>
     */
    private function queryHelmetsByFamily(string $family, int $limit, int $excludePostId): array
    {
        $q = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'post__not_in'   => [$excludePostId],
            'meta_query'     => [
                ['key' => 'helmet_family', 'value' => $family, 'compare' => '='],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $posts = $q->posts;
        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    /**
     * @param list<int> $termIds
     * @return list<int>
     */
    private function queryAccessoriesByCategory(array $termIds, int $limit, int $excludePostId): array
    {
        $q = new \WP_Query([
            'post_type'      => 'accessory',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'post__not_in'   => [$excludePostId],
            'tax_query'      => [
                ['taxonomy' => 'accessory_category', 'field' => 'term_id', 'terms' => $termIds],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $posts = $q->posts;
        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    /**
     * @return list<int>
     */
    private function getPostIds(string $postType, int $limit, int $offset): array
    {
        $q = new \WP_Query([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);
        $posts = $q->posts;
        return is_array($posts) ? array_map('intval', $posts) : [];
    }
}
