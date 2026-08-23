<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use Helmetsan\Core\Support\Config;

/**
 * Observes post saves and triggers asynchronous SEO generation via WP-Cron
 * if the setting is enabled and no meta description exists.
 */
final class AutoSeoObserver
{
    private const ALLOWED_POST_TYPES = ['helmet', 'brand', 'accessory'];

    public function __construct(
        private readonly YoastSeoSeeder $seeder
    ) {
    }

    public function init(): void
    {
        add_action('save_post', [$this, 'onSavePost'], 20, 3);
        add_action('helmetsan_generate_seo_for_post', [$this, 'generateSeoForPost']);
        
        // Native WordPress sitemap exclusions
        add_filter('wp_sitemaps_taxonomies', [$this, 'filterCoreSitemapTaxonomies']);
        add_filter('wp_sitemaps_post_types', [$this, 'filterCoreSitemapPostTypes']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'filterCoreSitemapHelmetQuery'], 10, 2);

        // Yoast SEO sitemap exclusions
        add_filter('wpseo_sitemap_exclude_post_type', [$this, 'excludeYoastSitemapPostType'], 10, 2);
        add_filter('wpseo_sitemap_exclude_taxonomy', [$this, 'excludeYoastSitemapTaxonomy'], 10, 2);
        add_filter('wpseo_sitemap_entry', [$this, 'excludeYoastSitemapEntries'], 10, 3);

        // Canonical consolidation for child helmet variants -> parent model
        add_filter('wpseo_canonical', [$this, 'filterHelmetCanonicalUrl']);
        add_filter('get_canonical_url', [$this, 'filterHelmetCanonicalUrl']);

        // Handle quality governance & noindex tags for thin/placeholder pages
        add_action('template_redirect', [$this, 'handleQualityAndNoindexGovernance']);
    }

    /**
     * Exclude redundant / thin taxonomies from core WP sitemaps.
     */
    public function filterCoreSitemapTaxonomies(array $taxonomies): array
    {
        $thinTaxonomies = [
            'helmet_brand',
            'price_range',
            'use_case',
            'feature_tag',
            'region',
            'accessory_category',
        ];

        foreach ($thinTaxonomies as $tax) {
            unset($taxonomies[$tax]);
        }

        return $taxonomies;
    }

    /**
     * Exclude thin custom post types from core WP sitemaps.
     */
    public function filterCoreSitemapPostTypes(array $postTypes): array
    {
        $thinPostTypes = [
            'dealer',
            'distributor',
            'comparison',
            'recommendation',
            'motorcycle',
            'asset',
        ];

        foreach ($thinPostTypes as $pt) {
            unset($postTypes[$pt]);
        }

        return $postTypes;
    }

    /**
     * Ensure core WP helmet sitemap only indexes parent helmet models (post_parent = 0).
     */
    public function filterCoreSitemapHelmetQuery(array $args, string $postType): array
    {
        if ($postType === 'helmet') {
            $args['post_parent'] = 0;
        }
        return $args;
    }

    /**
     * Exclude thin CPTs from Yoast SEO XML sitemaps.
     */
    public function excludeYoastSitemapPostType(bool $exclude, string $postType): bool
    {
        $thinPostTypes = [
            'dealer',
            'distributor',
            'comparison',
            'recommendation',
            'motorcycle',
            'asset',
        ];

        if (in_array($postType, $thinPostTypes, true)) {
            return true;
        }

        return $exclude;
    }

    /**
     * Exclude thin taxonomies from Yoast SEO XML sitemaps.
     */
    public function excludeYoastSitemapTaxonomy(bool $exclude, string $taxonomy): bool
    {
        $thinTaxonomies = [
            'helmet_brand',
            'price_range',
            'use_case',
            'feature_tag',
            'region',
            'accessory_category',
        ];

        if (in_array($taxonomy, $thinTaxonomies, true)) {
            return true;
        }

        return $exclude;
    }

    /**
     * Exclude child helmet variant URLs from Yoast SEO sitemap entries so only parent models are indexed.
     */
    public function excludeYoastSitemapEntries(array|bool $url, string $type, ?\WP_Post $post = null): array|bool
    {
        if ($url === false || ! $post instanceof \WP_Post) {
            return $url;
        }

        // If this is a helmet post and it has a parent (i.e. child SKU variant), exclude from sitemap
        if ($post->post_type === 'helmet' && (int) $post->post_parent > 0) {
            return false;
        }

        return $url;
    }

    /**
     * Canonicalize child helmet variants to their parent helmet model.
     */
    public function filterHelmetCanonicalUrl(mixed $canonical): mixed
    {
        if (! is_singular('helmet')) {
            return $canonical;
        }

        $postId = get_queried_object_id();
        if (! $postId) {
            return $canonical;
        }

        $post = get_post($postId);
        if ($post instanceof \WP_Post && (int) $post->post_parent > 0) {
            $parentUrl = get_permalink((int) $post->post_parent);
            if ($parentUrl) {
                return $parentUrl;
            }
        }

        return $canonical;
    }

    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        if (! in_array($post->post_type, self::ALLOWED_POST_TYPES, true)) {
            return;
        }

        // Check if auto SEO is enabled
        $options = get_option(Config::OPTION_AI, []);
        $enabled = (bool) ($options['phase1_seo_enabled'] ?? false);
        if (! $enabled) {
            return;
        }

        // Check if Yoast meta description is already set
        $existingMetaDesc = get_post_meta($postId, '_yoast_wpseo_metadesc', true);
        if ($existingMetaDesc !== '') {
            return;
        }

        // Schedule async event to generate SEO
        if (! wp_next_scheduled('helmetsan_generate_seo_for_post', [$postId])) {
            wp_schedule_single_event(time(), 'helmetsan_generate_seo_for_post', [$postId]);
        }
    }

    public function generateSeoForPost(int $postId): void
    {
        // Re-check conditions inside worker
        $existingMetaDesc = get_post_meta($postId, '_yoast_wpseo_metadesc', true);
        if ($existingMetaDesc !== '') {
            return;
        }

        $this->seeder->seedSinglePost($postId);
    }

    /**
     * Dynamic quality and indexation governance.
     * Prevents search engines and AdSense crawlers from evaluating thin/placeholder/duplicate pages.
     */
    public function handleQualityAndNoindexGovernance(): void
    {
        global $wp_query;

        if (! isset($wp_query)) {
            return;
        }

        $isBrandFilter = isset($_GET['brand_slug']) || isset($_GET['sort']);
        $isArchiveOrSearch = is_archive() || is_search() || is_tax() || is_category() || is_tag() || is_post_type_archive();
        $paged = get_query_var('paged') ? (int) get_query_var('paged') : (get_query_var('page') ? (int) get_query_var('page') : 1);

        $shouldNoindex = false;
        $robotsDirective = 'noindex, follow';

        // 1. Thin Custom Post Types (single or archive)
        $thinPostTypes = ['dealer', 'distributor', 'comparison', 'recommendation', 'motorcycle', 'asset'];
        if (is_singular($thinPostTypes) || is_post_type_archive($thinPostTypes)) {
            $shouldNoindex = true;
            $robotsDirective = 'noindex, follow';
        }

        // 2. Granular / thin taxonomy archives
        $thinTaxonomies = ['helmet_brand', 'price_range', 'use_case', 'feature_tag', 'region', 'accessory_category'];
        if (is_tax($thinTaxonomies)) {
            $shouldNoindex = true;
            $robotsDirective = 'noindex, follow';
        }

        // 3. Child helmet variants (SKU pages)
        if (is_singular('helmet')) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post && (int) $post->post_parent > 0) {
                $shouldNoindex = true;
                $robotsDirective = 'noindex, follow';
            }
        }

        // 4. Dummy WooCommerce pages if WooCommerce is active but not used as direct store
        if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) {
            $shouldNoindex = true;
            $robotsDirective = 'noindex, follow';
        }

        // 5. Main archive/search query has 0 found posts
        if (($isArchiveOrSearch || $isBrandFilter) && $wp_query->is_main_query() && $wp_query->found_posts === 0) {
            $shouldNoindex = true;
            $robotsDirective = 'noindex, nofollow';
        }

        // 6. Pagination page exceeds total pages for archives
        if ($isArchiveOrSearch && $wp_query->is_main_query() && $paged > 1 && $paged > $wp_query->max_num_pages) {
            $shouldNoindex = true;
            $robotsDirective = 'noindex, nofollow';
        }

        // 7. Single post/page has pagination page > 1
        if (is_singular() && $wp_query->is_main_query() && $paged > 1) {
            $shouldNoindex = true;
            $robotsDirective = 'noindex, nofollow';
        }

        if ($shouldNoindex) {
            if (! headers_sent()) {
                header('X-Robots-Tag: ' . $robotsDirective);
            }

            $isNoFollow = str_contains($robotsDirective, 'nofollow');

            add_filter('wp_robots', function (array $robots) use ($isNoFollow): array {
                $robots['noindex'] = true;
                if ($isNoFollow) {
                    $robots['nofollow'] = true;
                } else {
                    $robots['follow'] = true;
                }
                return $robots;
            }, 99);

            add_filter('wpseo_robots', function () use ($robotsDirective): string {
                return $robotsDirective;
            }, 99);
        }
    }
}
