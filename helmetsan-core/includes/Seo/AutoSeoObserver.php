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

        // Dynamic archive titles & descriptions for filtered/paginated catalog
        add_filter('wpseo_title', [$this, 'filterArchiveTitle'], 90);
        add_filter('document_title_parts', [$this, 'filterDocumentTitleParts'], 90);
        add_filter('wpseo_metadesc', [$this, 'filterArchiveMetaDescription'], 90);

        // Pagination rel="prev" / rel="next" link tags
        add_action('wp_head', [$this, 'printPaginationLinkTags'], 5);

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
        global $wp_query;

        // 1. Canonical consolidation for zero-result archive/filter queries
        if (isset($wp_query) && $wp_query->is_main_query() && (int) $wp_query->found_posts === 0) {
            $isBrandFilter = isset($_GET['brand_slug']) || isset($_GET['sort']);
            $isArchiveOrSearch = is_archive() || is_search() || is_tax() || is_category() || is_tag() || is_post_type_archive();
            if ($isArchiveOrSearch || $isBrandFilter) {
                $cleanArchive = get_post_type_archive_link('helmet');
                return is_string($cleanArchive) && $cleanArchive !== '' ? $cleanArchive : home_url('/helmets/');
            }
        }

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
        if (($isArchiveOrSearch || $isBrandFilter) && $wp_query->is_main_query() && (int) $wp_query->found_posts === 0) {
            $shouldNoindex = true;
            $robotsDirective = 'noindex, nofollow';
            $wp_query->is_404 = false;

            if (! headers_sent()) {
                status_header(200);
            }

            $this->logEmptyFilterCombo();
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

    /**
     * Log zero-result filter queries to a transient ring buffer for administrative review.
     */
    private function logEmptyFilterCombo(): void
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $params = array_filter(
            $_GET,
            static fn($k) => !in_array($k, ['hs_warm_bypass', '_wpnonce'], true),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($params)) {
            return;
        }

        $logKey = 'hs_empty_filter_log';
        $entries = get_transient($logKey);
        if (!is_array($entries)) {
            $entries = [];
        }

        $comboKey = md5(wp_json_encode($params));
        $now = current_time('mysql');

        if (isset($entries[$comboKey])) {
            $entries[$comboKey]['hits'] = ($entries[$comboKey]['hits'] ?? 1) + 1;
            $entries[$comboKey]['last_seen'] = $now;
        } else {
            if (count($entries) >= 50) {
                array_shift($entries);
            }
            $entries[$comboKey] = [
                'params'    => $params,
                'hits'      => 1,
                'last_seen' => $now,
            ];
        }

        set_transient($logKey, $entries, 7 * DAY_IN_SECONDS);
    }

    /**
     * Generate dynamic title for helmet archives, brand filters, and pagination.
     */
    public function filterArchiveTitle(mixed $title): mixed
    {
        if (! $this->isHelmetCatalogArchive()) {
            return $title;
        }

        $dynamicTitle = $this->buildDynamicArchiveTitle();
        return $dynamicTitle !== '' ? $dynamicTitle : $title;
    }

    /**
     * Filter core WordPress document_title_parts.
     *
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function filterDocumentTitleParts(array $parts): array
    {
        if (! $this->isHelmetCatalogArchive()) {
            return $parts;
        }

        $dynamicTitle = $this->buildDynamicArchiveTitle(false);
        if ($dynamicTitle !== '') {
            $parts['title'] = $dynamicTitle;
            unset($parts['page']);
        }

        return $parts;
    }

    /**
     * Filter Yoast meta description for helmet catalog archives.
     */
    public function filterArchiveMetaDescription(mixed $desc): mixed
    {
        if (! $this->isHelmetCatalogArchive()) {
            return $desc;
        }

        $dynamicDesc = $this->buildDynamicArchiveDescription();
        return $dynamicDesc !== '' ? $dynamicDesc : $desc;
    }

    /**
     * Print rel="prev" and rel="next" links on paginated archives.
     */
    public function printPaginationLinkTags(): void
    {
        global $wp_query;

        if (! isset($wp_query) || ! $wp_query->is_main_query() || ! $this->isHelmetCatalogArchive()) {
            return;
        }

        $paged = max(1, (int) (get_query_var('paged') ?: (get_query_var('page') ?: 1)));
        $maxPages = (int) $wp_query->max_num_pages;

        if ($maxPages <= 1) {
            return;
        }

        if ($paged > 1) {
            $prevUrl = $paged === 2 ? remove_query_arg('paged') : add_query_arg('paged', $paged - 1);
            echo '<link rel="prev" href="' . esc_url($prevUrl) . '">' . "\n";
        }

        if ($paged < $maxPages) {
            $nextUrl = add_query_arg('paged', $paged + 1);
            echo '<link rel="next" href="' . esc_url($nextUrl) . '">' . "\n";
        }
    }

    /**
     * Determine if current query is a helmet catalog archive view.
     */
    private function isHelmetCatalogArchive(): bool
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if (is_post_type_archive('helmet')) {
            return true;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (!is_string($requestUri) || $requestUri === '') {
            return false;
        }

        $path = trim((string) wp_parse_url($requestUri, PHP_URL_PATH), '/');
        $segments = explode('/', $path);
        return in_array('helmets', $segments, true);
    }

    /**
     * Build dynamic archive title string.
     */
    private function buildDynamicArchiveTitle(bool $includeSiteName = true): string
    {
        global $wp_query;

        $paged = max(1, (int) (get_query_var('paged') ?: (get_query_var('page') ?: 1)));
        $maxPages = isset($wp_query) && (int) $wp_query->max_num_pages > 0 ? (int) $wp_query->max_num_pages : 1;

        $brandSlug = sanitize_title($_GET['brand_slug'] ?? '');
        $rawType = $_GET['helmet_type'] ?? '';
        $helmetType = sanitize_title(is_array($rawType) ? ($rawType[0] ?? '') : $rawType);
        $rawCert = $_GET['certification'] ?? '';
        $cert = sanitize_title(is_array($rawCert) ? ($rawCert[0] ?? '') : $rawCert);

        $baseTitle = 'Helmets Catalog';

        if ($brandSlug !== '') {
            $brandPost = get_page_by_path($brandSlug, OBJECT, 'brand');
            $brandName = ($brandPost instanceof \WP_Post) ? $brandPost->post_title : ucfirst(str_replace('-', ' ', $brandSlug));
            $baseTitle = sprintf('%s Helmets — All Models, Specs & Prices', $brandName);
        } elseif ($helmetType !== '') {
            $term = get_term_by('slug', $helmetType, 'helmet_type');
            $typeName = ($term instanceof \WP_Term) ? $term->name : ucfirst(str_replace('-', ' ', $helmetType));
            $baseTitle = sprintf('%s Motorcycle Helmets — Specs, Noise & Safety', $typeName);
        } elseif ($cert !== '') {
            $term = get_term_by('slug', $cert, 'certification');
            $certName = ($term instanceof \WP_Term) ? $term->name : strtoupper(str_replace('-', ' ', $cert));
            $baseTitle = sprintf('%s Certified Motorcycle Helmets', $certName);
        }

        if ($paged > 1) {
            $baseTitle .= sprintf(' — Page %d of %d', $paged, max($paged, $maxPages));
        }

        if ($includeSiteName) {
            $baseTitle .= ' | Helmetsan';
        }

        return $baseTitle;
    }

    /**
     * Build dynamic archive meta description string.
     */
    private function buildDynamicArchiveDescription(): string
    {
        $paged = max(1, (int) (get_query_var('paged') ?: (get_query_var('page') ?: 1)));
        $brandSlug = sanitize_title($_GET['brand_slug'] ?? '');
        $rawType = $_GET['helmet_type'] ?? '';
        $helmetType = sanitize_title(is_array($rawType) ? ($rawType[0] ?? '') : $rawType);

        if ($brandSlug !== '') {
            $brandPost = get_page_by_path($brandSlug, OBJECT, 'brand');
            $brandName = ($brandPost instanceof \WP_Post) ? $brandPost->post_title : ucfirst(str_replace('-', ' ', $brandSlug));
            $desc = sprintf('Browse verified %s motorcycle helmets. Compare certified weights, acoustic noise levels (dB @ 100km/h), and safety ratings (ECE 22.06, DOT, Snell).', $brandName);
        } elseif ($helmetType !== '') {
            $term = get_term_by('slug', $helmetType, 'helmet_type');
            $typeName = ($term instanceof \WP_Term) ? $term->name : ucfirst(str_replace('-', ' ', $helmetType));
            $desc = sprintf('Compare verified %s motorcycle helmets. Side-by-side technical specs, laboratory noise tests, safety standards, and verified rider ratings.', $typeName);
        } else {
            $desc = 'Explore 3,100+ verified motorcycle helmets. Compare laboratory noise test metrics (dB), shell weights, safety homologations (ECE 22.06, DOT, FIM), and live retailer pricing.';
        }

        if ($paged > 1) {
            $desc .= sprintf(' (Page %d)', $paged);
        }

        return $desc;
    }
}
