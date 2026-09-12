<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use WP_Post;

/**
 * Intercepts 404 requests on product, brand, and accessory URLs and executes
 * intelligent 301 redirects to preserve link equity, eliminate crawl waste,
 * and seamlessly guide users to the relevant successor or category hub.
 */
final class SlugRedirectService
{
    private const SUPPORTED_POST_TYPES = ['helmet', 'accessory', 'brand'];

    /**
     * Register hook listeners.
     */
    public function register(): void
    {
        add_action('template_redirect', [$this, 'handleRedirect'], 1);
        add_action('before_delete_post', [$this, 'onBeforeDeletePost'], 10, 1);
        add_action('wp_trash_post', [$this, 'onBeforeDeletePost'], 10, 1);
        add_action('post_updated', [$this, 'onPostUpdated'], 10, 3);
    }

    /**
     * Intercept 404 requests and check redirect rules.
     */
    public function handleRedirect(): void
    {
        if (!is_404()) {
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (!is_string($requestUri) || $requestUri === '') {
            return;
        }

        $path = trim((string) wp_parse_url($requestUri, PHP_URL_PATH), '/');
        if ($path === '') {
            return;
        }

        $segments = explode('/', $path);

        $postTypeMap = [
            'helmets'     => 'helmet',
            'accessories' => 'accessory',
            'brands'      => 'brand',
        ];

        // Locate segment matching post type (supports language prefixes e.g. /de/helmets/{slug}/)
        $keyIndex = -1;
        $postType = '';
        foreach ($segments as $idx => $segment) {
            if (isset($postTypeMap[$segment])) {
                $keyIndex = $idx;
                $postType = $postTypeMap[$segment];
                break;
            }
        }

        if ($keyIndex === -1) {
            return;
        }

        $rawSlug = $segments[$keyIndex + 1] ?? '';
        $targetSlug = sanitize_title($rawSlug);
        if ($targetSlug === '') {
            return;
        }

        $langPrefix = $keyIndex > 0 ? $segments[0] : '';

        // 1. Check custom redirect table for exact mapping
        $redirect = $this->findRedirect($targetSlug, $postType);
        if ($redirect !== null) {
            $this->recordHit((int) $redirect['id']);
            wp_safe_redirect($redirect['target_url'], (int) $redirect['redirect_status']);
            exit;
        }

        // 2. Intelligent recovery: Check if the slug had numerical variant suffixes (e.g., shoei-x-fifteen-2)
        if (preg_match('/^(.+)-\d+$/', $targetSlug, $matches)) {
            $baseSlug = $matches[1];
            $basePost = get_page_by_path($baseSlug, OBJECT, $postType);
            if ($basePost instanceof WP_Post && $basePost->post_status === 'publish') {
                $canonicalUrl = get_permalink($basePost);
                if (is_string($canonicalUrl) && $canonicalUrl !== '') {
                    $this->addRedirect($targetSlug, $canonicalUrl, $postType, 301);
                    wp_safe_redirect($canonicalUrl, 301);
                    exit;
                }
            }
        }

        // 3. If helmet 404, check if slug contains a known brand slug to redirect to brand hub
        if ($postType === 'helmet') {
            $brandRedirect = $this->findBrandHubForSlug($targetSlug, $langPrefix);
            if ($brandRedirect !== '') {
                $this->addRedirect($targetSlug, $brandRedirect, $postType, 301);
                wp_safe_redirect($brandRedirect, 301);
                exit;
            }

            // Fallback: consolidate product 404 to clean catalog archive (language-aware)
            $archiveUrl = $langPrefix !== ''
                ? home_url('/' . $langPrefix . '/helmets/')
                : (string) get_post_type_archive_link('helmet');

            if ($archiveUrl !== '') {
                wp_safe_redirect($archiveUrl, 301);
                exit;
            }
        }
    }

    /**
     * Find redirect entry by slug and post_type.
     *
     * @return array{id: int, target_url: string, redirect_status: int}|null
     */
    public function findRedirect(string $sourceSlug, string $postType): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_slug_redirects';

        // Check if table exists before querying
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, target_url, redirect_status FROM {$table} WHERE source_slug = %s AND (post_type = %s OR post_type = '') LIMIT 1",
                $sourceSlug,
                $postType
            ),
            ARRAY_A
        );

        if (!is_array($row) || empty($row['target_url'])) {
            return null;
        }

        return [
            'id'              => (int) $row['id'],
            'target_url'      => (string) $row['target_url'],
            'redirect_status' => (int) ($row['redirect_status'] ?? 301),
        ];
    }

    /**
     * Record a hit on a redirect entry.
     */
    private function recordHit(int $redirectId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_slug_redirects';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET hit_count = hit_count + 1, last_accessed = %s WHERE id = %d",
                current_time('mysql'),
                $redirectId
            )
        );
    }

    /**
     * Add or update a slug redirect rule.
     */
    public function addRedirect(string $sourceSlug, string $targetUrl, string $postType = 'helmet', int $status = 301): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_slug_redirects';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return false;
        }

        $cleanSlug = sanitize_title($sourceSlug);
        if ($cleanSlug === '' || $targetUrl === '') {
            return false;
        }

        $result = $wpdb->replace(
            $table,
            [
                'source_slug'     => $cleanSlug,
                'post_type'       => $postType,
                'target_url'      => esc_url_raw($targetUrl),
                'redirect_status' => in_array($status, [301, 302, 307, 308], true) ? $status : 301,
                'created_at'      => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Hook before deleting or trashing a product to preserve redirect mapping.
     */
    public function onBeforeDeletePost(int $postId): void
    {
        $post = get_post($postId);
        if (!$post instanceof WP_Post || !in_array($post->post_type, self::SUPPORTED_POST_TYPES, true)) {
            return;
        }

        $sourceSlug = $post->post_name;
        if ($sourceSlug === '') {
            return;
        }

        // Determine best target URL
        $targetUrl = '';

        if ($post->post_type === 'helmet') {
            // Check if helmet had a brand associated
            $brandId = (int) get_post_meta($postId, 'brand', true);
            if ($brandId > 0) {
                $brandUrl = get_permalink($brandId);
                if (is_string($brandUrl) && $brandUrl !== '') {
                    $targetUrl = $brandUrl;
                }
            }

            // Fallback to helmet catalog archive
            if ($targetUrl === '') {
                $targetUrl = (string) get_post_type_archive_link('helmet');
            }
        } elseif ($post->post_type === 'accessory') {
            $targetUrl = home_url('/accessories/');
        } elseif ($post->post_type === 'brand') {
            $targetUrl = (string) get_post_type_archive_link('helmet');
        }

        if ($targetUrl !== '') {
            $this->addRedirect($sourceSlug, $targetUrl, $post->post_type, 301);
        }
    }

    /**
     * Hook when a post is updated to track slug changes.
     */
    public function onPostUpdated(int $postId, WP_Post $postAfter, WP_Post $postBefore): void
    {
        if (!in_array($postAfter->post_type, self::SUPPORTED_POST_TYPES, true)) {
            return;
        }

        if ($postBefore->post_name !== '' && $postAfter->post_name !== '' && $postBefore->post_name !== $postAfter->post_name) {
            $newUrl = get_permalink($postId);
            if (is_string($newUrl) && $newUrl !== '') {
                $this->addRedirect($postBefore->post_name, $newUrl, $postAfter->post_type, 301);
            }
        }
    }

    /**
     * Attempt to find a brand hub URL if the slug starts with or contains a known brand.
     */
    private function findBrandHubForSlug(string $slug, string $langPrefix = ''): string
    {
        $brands = class_exists(\Helmetsan\Core\Cache\ObjectCacheService::class)
            ? \Helmetsan\Core\Cache\ObjectCacheService::getBrandList()
            : get_posts([
                'post_type'      => 'brand',
                'post_status'    => 'publish',
                'posts_per_page' => 250,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);

        if (!is_array($brands)) {
            return '';
        }

        foreach ($brands as $brandPost) {
            if (!$brandPost instanceof WP_Post) {
                continue;
            }

            $brandSlug = $brandPost->post_name;
            if ($brandSlug !== '' && (str_starts_with($slug, $brandSlug . '-') || $slug === $brandSlug)) {
                $brandUrl = get_permalink($brandPost);
                if (is_string($brandUrl) && $brandUrl !== '') {
                    return $brandUrl;
                }
            }
        }

        return '';
    }
}
