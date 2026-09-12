<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cache;

/**
 * High-performance Object Cache abstraction and group governance service.
 * Supports native WordPress Object Cache (Redis/Memcached) with versioned group invalidation
 * and automatic transient fallback for persistent storage on standard environments.
 */
final class ObjectCacheService
{
    public const GROUP_SEARCH       = 'hs_search';
    public const GROUP_TAXONOMY     = 'hs_taxonomy';
    public const GROUP_SCHEMA       = 'hs_schema';
    public const GROUP_CROSSLINK    = 'hs_crosslink';
    public const GROUP_ALTERNATIVES = 'hs_alternatives';
    public const GROUP_PRICE        = 'hs_price';
    public const GROUP_MD_PAYLOAD   = 'hs_md_payload';

    /**
     * Default TTL configurations per group (in seconds).
     */
    public const DEFAULT_TTLS = [
        self::GROUP_SEARCH       => 3600,             // 1 hour
        self::GROUP_TAXONOMY     => 21600,            // 6 hours
        self::GROUP_SCHEMA       => 86400,            // 24 hours
        self::GROUP_CROSSLINK    => 86400,            // 24 hours
        self::GROUP_ALTERNATIVES => 86400,            // 24 hours
        self::GROUP_PRICE        => 3600,             // 1 hour
        self::GROUP_MD_PAYLOAD   => 3600,             // 1 hour
    ];

    /**
     * In-memory local runtime cache for identical requests within a single PHP lifecycle.
     *
     * @var array<string, mixed>
     */
    private static array $runtimeCache = [];

    /**
     * In-memory cache of group version indexes.
     *
     * @var array<string, int>
     */
    private static array $groupVersions = [];

    /**
     * Reset in-memory runtime cache and version state (useful for tests and long-running daemons).
     */
    public static function resetRuntimeState(): void
    {
        self::$runtimeCache = [];
        self::$groupVersions = [];
    }

    /**
     * Register core invalidation hooks.
     */
    public static function register(): void
    {
        // Product content changes
        add_action('save_post_helmet', [self::class, 'onProductSaved'], 10, 1);
        add_action('save_post_accessory', [self::class, 'onProductSaved'], 10, 1);
        add_action('save_post_motorcycle', [self::class, 'onProductSaved'], 10, 1);
        add_action('wp_trash_post', [self::class, 'onProductDeleted'], 10, 1);
        add_action('before_delete_post', [self::class, 'onProductDeleted'], 10, 1);

        // Taxonomy changes
        add_action('created_term', [self::class, 'onTaxonomyChanged'], 10, 3);
        add_action('edited_term', [self::class, 'onTaxonomyChanged'], 10, 3);
        add_action('delete_term', [self::class, 'onTaxonomyChanged'], 10, 4);

        // Brand post changes
        add_action('save_post_brand', [self::class, 'onBrandSaved'], 10, 1);

        // Price updates
        add_action('helmetsan_price_updated', [self::class, 'onPriceUpdated']);
    }

    /**
     * Cache-aside retrieval: returns cached value or computes, caches, and returns it.
     *
     * @template T
     * @param string $key
     * @param string $group
     * @param callable(): T $compute
     * @param int|null $ttl
     * @return T
     */
    public static function remember(string $key, string $group, callable $compute, ?int $ttl = null)
    {
        $cached = self::get($key, $group);
        if ($cached !== false && $cached !== null) {
            return $cached;
        }

        $computed = $compute();
        $actualTtl = $ttl ?? (self::DEFAULT_TTLS[$group] ?? 3600);
        self::set($key, $computed, $group, $actualTtl);

        return $computed;
    }

    /**
     * Retrieve an item from the object cache with group versioning.
     *
     * @param string $key
     * @param string $group
     * @return mixed False or null if not found
     */
    public static function get(string $key, string $group = 'default'): mixed
    {
        $versionedKey = self::buildKey($key, $group);

        // 1. Runtime memory cache check
        if (array_key_exists($versionedKey, self::$runtimeCache)) {
            return self::$runtimeCache[$versionedKey];
        }

        // 2. Persistent object cache check (Redis / Memcached)
        $found = false;
        $value = wp_cache_get($versionedKey, $group, false, $found);
        if ($found && $value !== false) {
            self::$runtimeCache[$versionedKey] = $value;
            return $value;
        }

        // 3. Fallback to transient when external object cache is not available
        if (function_exists('wp_using_ext_object_cache') && !wp_using_ext_object_cache()) {
            $transientKey = self::buildTransientKey($key, $group);
            $transientVal = get_transient($transientKey);
            if ($transientVal !== false) {
                self::$runtimeCache[$versionedKey] = $transientVal;
                return $transientVal;
            }
        }

        return false;
    }

    /**
     * Store an item in the cache with group versioning.
     *
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @param int|null $ttl
     * @return bool
     */
    public static function set(string $key, mixed $value, string $group = 'default', ?int $ttl = null): bool
    {
        $actualTtl = $ttl ?? (self::DEFAULT_TTLS[$group] ?? 3600);
        $versionedKey = self::buildKey($key, $group);

        self::$runtimeCache[$versionedKey] = $value;

        $stored = wp_cache_set($versionedKey, $value, $group, $actualTtl);

        // Transient fallback for persistent storage on standard hosting
        if (function_exists('wp_using_ext_object_cache') && !wp_using_ext_object_cache()) {
            $transientKey = self::buildTransientKey($key, $group);
            set_transient($transientKey, $value, $actualTtl);
        }

        return $stored;
    }

    /**
     * Delete a specific key from cache.
     */
    public static function delete(string $key, string $group = 'default'): bool
    {
        $versionedKey = self::buildKey($key, $group);
        unset(self::$runtimeCache[$versionedKey]);

        $deleted = wp_cache_delete($versionedKey, $group);

        if (function_exists('wp_using_ext_object_cache') && !wp_using_ext_object_cache()) {
            $transientKey = self::buildTransientKey($key, $group);
            delete_transient($transientKey);
        }

        return $deleted;
    }

    /**
     * Invalidate an entire cache group instantaneously via version bumping.
     * Prevents expensive table-wide truncates and avoids nuclear wp_cache_flush().
     */
    public static function invalidateGroup(string $group): void
    {
        // 1. Increment the group version key
        $versionKey = 'hs_group_ver_' . $group;
        $currentVer = (int) get_option($versionKey, 1);
        $nextVer = $currentVer + 1;
        update_option($versionKey, $nextVer, false);
        self::$groupVersions[$group] = $nextVer;

        // 2. Clear from local runtime cache
        self::$runtimeCache = [];

        // 3. Call native group deletion if object cache provider supports it
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group($group);
        }

        // 4. Clean up group transients if fallback was used
        if (function_exists('wp_using_ext_object_cache') && !wp_using_ext_object_cache()) {
            global $wpdb;
            if (isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'query')) {
                $prefix = '_transient_hs_' . $group . '_';
                $timeoutPrefix = '_transient_timeout_hs_' . $group . '_';
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like($prefix) . '%',
                    $wpdb->esc_like($timeoutPrefix) . '%'
                ));
            }
        }
    }

    /**
     * Pre-warm a cache key.
     */
    public static function warmKey(string $key, string $group, callable $compute, ?int $ttl = null): mixed
    {
        $value = $compute();
        self::set($key, $value, $group, $ttl);
        return $value;
    }

    /**
     * Build versioned key for cache isolation.
     */
    private static function buildKey(string $key, string $group): string
    {
        $version = self::getGroupVersion($group);
        return $group . '_' . $key . '_v' . $version;
    }

    /**
     * Build sanitized transient key (must be <= 172 characters).
     */
    private static function buildTransientKey(string $key, string $group): string
    {
        $version = self::getGroupVersion($group);
        $raw = 'hs_' . $group . '_' . $key . '_v' . $version;
        if (strlen($raw) > 165) {
            return 'hs_' . $group . '_' . md5($raw);
        }
        return $raw;
    }

    /**
     * Retrieve the current version index for a given group.
     */
    public static function getGroupVersion(string $group): int
    {
        if (isset(self::$groupVersions[$group])) {
            return self::$groupVersions[$group];
        }

        $versionKey = 'hs_group_ver_' . $group;
        $ver = (int) get_option($versionKey, 1);
        if ($ver <= 0) {
            $ver = 1;
        }

        self::$groupVersions[$group] = $ver;
        return $ver;
    }

    /**
     * Invalidation handler for product updates.
     */
    public static function onProductSaved(int $postId): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        self::invalidateGroup(self::GROUP_SEARCH);
        self::invalidateGroup(self::GROUP_SCHEMA);
        self::invalidateGroup(self::GROUP_ALTERNATIVES);
        self::invalidateGroup(self::GROUP_CROSSLINK);
        self::invalidateGroup(self::GROUP_MD_PAYLOAD);
    }

    /**
     * Invalidation handler for deleted products.
     */
    public static function onProductDeleted(int $postId): void
    {
        self::invalidateGroup(self::GROUP_SEARCH);
        self::invalidateGroup(self::GROUP_SCHEMA);
        self::invalidateGroup(self::GROUP_ALTERNATIVES);
        self::invalidateGroup(self::GROUP_CROSSLINK);
        self::invalidateGroup(self::GROUP_MD_PAYLOAD);
    }

    /**
     * Invalidation handler for taxonomy changes.
     */
    public static function onTaxonomyChanged(): void
    {
        self::invalidateGroup(self::GROUP_TAXONOMY);
        self::invalidateGroup(self::GROUP_SEARCH);
    }

    /**
     * Invalidation handler for brand post saves.
     */
    public static function onBrandSaved(): void
    {
        self::invalidateGroup(self::GROUP_TAXONOMY);
        self::invalidateGroup(self::GROUP_SEARCH);
    }

    /**
     * Invalidation handler for price updates.
     */
    public static function onPriceUpdated(): void
    {
        self::invalidateGroup(self::GROUP_PRICE);
        self::invalidateGroup(self::GROUP_SEARCH);
    }

    /**
     * Get cached taxonomy terms for catalog filters.
     *
     * @param string $taxonomy
     * @return array<\WP_Term>
     */
    public static function getTaxonomyTerms(string $taxonomy): array
    {
        return self::remember('terms_' . $taxonomy, self::GROUP_TAXONOMY, static function () use ($taxonomy): array {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
            ]);
            return is_array($terms) ? $terms : [];
        }, 6 * HOUR_IN_SECONDS);
    }

    /**
     * Get cached list of published brands.
     *
     * @return array<\WP_Post>
     */
    public static function getBrandList(): array
    {
        return self::remember('catalog_brand_list_250', self::GROUP_TAXONOMY, static function (): array {
            return get_posts([
                'post_type'      => 'brand',
                'post_status'    => 'publish',
                'posts_per_page' => 250,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
        }, 6 * HOUR_IN_SECONDS);
    }
}
