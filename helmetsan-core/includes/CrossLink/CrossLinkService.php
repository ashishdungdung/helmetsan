<?php

declare(strict_types=1);

namespace Helmetsan\Core\CrossLink;

use WP_Post;
use WP_Term;
use Helmetsan\Core\Cache\ObjectCacheService;

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

        if (! $dryRun && $updated > 0 && class_exists(ObjectCacheService::class)) {
            ObjectCacheService::invalidateGroup(ObjectCacheService::GROUP_CROSSLINK);
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
        return is_array($posts) ? array_map(static fn($p) => $p instanceof WP_Post ? (int) $p->ID : (int) $p, $posts) : [];
    }

    /**
     * Resolve localized permalinks for an array of cross links based on current language.
     *
     * @param list<array{post_id?: int, url?: string, title?: string, reason?: string}> $links
     * @param string|null $lang
     * @return list<array{post_id?: int, url: string, title?: string, reason?: string}>
     */
    public static function resolveLocalizedLinks(array $links, ?string $lang = null): array
    {
        if ($lang === null && function_exists('pll_current_language')) {
            $lang = pll_current_language();
        }

        $resolved = [];
        foreach ($links as $link) {
            $postId = (int) ($link['post_id'] ?? 0);
            if ($postId > 0) {
                if (function_exists('helmetsan_permalink')) {
                    $link['url'] = helmetsan_permalink($postId, $lang);
                } elseif (function_exists('pll_get_post') && ! empty($lang)) {
                    $transId = (int) pll_get_post($postId, $lang);
                    if ($transId > 0 && get_post_status($transId) === 'publish') {
                        $link['url'] = (string) get_permalink($transId);
                    } else {
                        $link['url'] = (string) get_permalink($postId);
                    }
                } else {
                    $link['url'] = (string) get_permalink($postId);
                }
            }
            $resolved[] = $link;
        }

        return $resolved;
    }

    /**
     * Get stored outgoing links from post meta.
     *
     * @return list<array{post_id?: int, url?: string, reason?: string}>
     */
    public function getStoredOutgoingLinks(int $postId): array
    {
        $raw = get_post_meta($postId, self::META_OUTGOING_LINKS, true);
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Identify orphan pages (published posts with zero incoming or outgoing internal links in outgoing_internal_links_json).
     *
     * @param string $postType 'helmet'|'brand'|'accessory'
     * @param int $limit Max number of orphan pages to return (0 for all)
     * @return list<array{
     *     post_id: int,
     *     title: string,
     *     url: string,
     *     post_type: string,
     *     outgoing_count: int,
     *     incoming_count: int,
     *     suggested_links: list<array{post_id: int, url: string, reason: string}>
     * }>
     */
    public function findOrphanPages(string $postType = 'helmet', int $limit = 50): array
    {
        global $wpdb;

        $cacheKey = 'orphan_pages_' . $postType . '_' . $limit;
        if (class_exists(ObjectCacheService::class)) {
            $cached = ObjectCacheService::get($cacheKey, ObjectCacheService::GROUP_CROSSLINK);
            if (is_array($cached)) {
                return $cached;
            }
        }

        // 1. Build incoming reference counts from all posts with outgoing_internal_links_json
        $incomingCounts = [];
        $outgoingCounts = [];

        if (isset($wpdb) && is_object($wpdb) && ! empty($wpdb->postmeta) && method_exists($wpdb, 'prepare') && method_exists($wpdb, 'get_results')) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    self::META_OUTGOING_LINKS
                ),
                \ARRAY_A
            );

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $srcId = (int) ($row['post_id'] ?? 0);
                    $val = (string) ($row['meta_value'] ?? '');
                    if ($val === '') {
                        continue;
                    }
                    $decoded = json_decode($val, true);
                    if (! is_array($decoded)) {
                        continue;
                    }
                    $outgoingCounts[$srcId] = count($decoded);
                    foreach ($decoded as $link) {
                        $targetId = (int) ($link['post_id'] ?? 0);
                        if ($targetId > 0) {
                            $incomingCounts[$targetId] = ($incomingCounts[$targetId] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        // 2. Fetch published post IDs of $postType
        $publishedIds = $this->getPostIds($postType, 0, 0);
        $orphans = [];

        foreach ($publishedIds as $pid) {
            $incoming = $incomingCounts[$pid] ?? 0;
            $outgoing = $outgoingCounts[$pid] ?? count($this->getStoredOutgoingLinks($pid));

            // An orphan has zero outgoing links OR zero incoming links
            if ($outgoing === 0 || $incoming === 0) {
                $post = get_post($pid);
                if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
                    continue;
                }

                $orphans[] = [
                    'post_id'         => $pid,
                    'title'           => (string) $post->post_title,
                    'url'             => (string) get_permalink($pid),
                    'post_type'       => $postType,
                    'outgoing_count'  => $outgoing,
                    'incoming_count'  => $incoming,
                    'suggested_links' => $this->suggestForPost($pid),
                ];

                if ($limit > 0 && count($orphans) >= $limit) {
                    break;
                }
            }
        }

        if (class_exists(ObjectCacheService::class)) {
            ObjectCacheService::set($cacheKey, $orphans, ObjectCacheService::GROUP_CROSSLINK, 86400);
        }

        return $orphans;
    }

    /**
     * Analyze outgoing links of a post and identify reciprocal link opportunities.
     * Checks whether target posts link back to this post, and provides suggestions to close the loop.
     *
     * @return array{
     *     post_id: int,
     *     outgoing_count: int,
     *     unreciprocated_outgoing: list<array{target_post_id: int, target_title: string, target_url: string, reason: string}>,
     *     suggested_reciprocals: list<array{post_id: int, url: string, title: string, reason: string}>
     * }
     */
    public function suggestBidirectionalLinks(int $postId): array
    {
        $post = get_post($postId);
        if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
            return [
                'post_id'                 => $postId,
                'outgoing_count'          => 0,
                'unreciprocated_outgoing' => [],
                'suggested_reciprocals'   => [],
            ];
        }

        $cacheKey = 'bidirectional_links_' . $postId;
        if (class_exists(ObjectCacheService::class)) {
            $cached = ObjectCacheService::get($cacheKey, ObjectCacheService::GROUP_CROSSLINK);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $outgoingLinks = $this->getStoredOutgoingLinks($postId);
        if ($outgoingLinks === []) {
            // Fallback to computed suggestions if meta not yet stored
            $outgoingLinks = $this->suggestForPost($postId);
        }

        $unreciprocated = [];
        $suggestedReciprocals = [];

        foreach ($outgoingLinks as $link) {
            $targetId = (int) ($link['post_id'] ?? 0);
            if ($targetId <= 0 || $targetId === $postId) {
                continue;
            }

            $targetPost = get_post($targetId);
            if (! $targetPost instanceof WP_Post || $targetPost->post_status !== 'publish') {
                continue;
            }

            $targetLinks = $this->getStoredOutgoingLinks($targetId);
            $hasReciprocal = false;
            foreach ($targetLinks as $tLink) {
                if ((int) ($tLink['post_id'] ?? 0) === $postId) {
                    $hasReciprocal = true;
                    break;
                }
            }

            if (! $hasReciprocal) {
                $targetUrl = (string) get_permalink($targetId);
                $unreciprocated[] = [
                    'target_post_id' => $targetId,
                    'target_title'   => (string) $targetPost->post_title,
                    'target_url'     => $targetUrl,
                    'reason'         => 'missing_reciprocal_from_target',
                ];

                $suggestedReciprocals[] = [
                    'post_id' => $targetId,
                    'url'     => $targetUrl,
                    'title'   => (string) $targetPost->post_title,
                    'reason'  => 'reciprocal_cluster',
                ];
            }
        }

        $result = [
            'post_id'                 => $postId,
            'outgoing_count'          => count($outgoingLinks),
            'unreciprocated_outgoing' => $unreciprocated,
            'suggested_reciprocals'   => $suggestedReciprocals,
        ];

        if (class_exists(ObjectCacheService::class)) {
            ObjectCacheService::set($cacheKey, $result, ObjectCacheService::GROUP_CROSSLINK, 86400);
        }

        return $result;
    }
}
