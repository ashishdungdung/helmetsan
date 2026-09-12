<?php

declare(strict_types=1);

namespace Helmetsan\Core\Search;

use WP_Query;
use WP_Post;
use WP_Term;
use Helmetsan\Core\Cache\ObjectCacheService;

final class SearchService
{
    /**
     * Parsing and Sanitizing input parameters
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function parseParams(array $params): array
    {
        $getString = fn($key) => isset($params[$key]) ? sanitize_text_field(wp_unslash((string) $params[$key])) : '';
        $getArray = function($key) use ($params) {
            if (! isset($params[$key])) return [];
            $raw = $params[$key];
            if (! is_array($raw)) $raw = [$raw];
            return array_values(array_unique(array_filter(array_map('sanitize_text_field', $raw))));
        };

        return [
            's'              => $getString('s'),
            'post_type'      => $getArray('post_type') ?: ['helmet'],
            'category'       => $getArray('category'), // Unified category filter (helmet_type or accessory_type)
            'helmet_type'    => $getArray('helmet_type'),
            'certification'  => $getArray('certification'),
            'feature'        => $getArray('feature'),
            'size'           => $getArray('size'),
            'price_range'    => $getArray('price_range'),
            'region'         => $getArray('region'),
            'use_case'       => $getArray('use_case'),
            'brand_slug'     => sanitize_title($getString('brand_slug')),
            'helmet_family'  => $getString('helmet_family'),
            'price_min'      => $getString('price_min'),
            'price_max'      => $getString('price_max'),
            'sharp_rating'   => $getArray('sharp_rating'),
            'strap_type'     => $getArray('strap_type'),
            'comms_ready'    => $getArray('comms_ready'),
            'sort'           => $getString('sort') ?: 'newest',
            'paged'          => max(1, (int) (get_query_var('paged') ?: ($params['paged'] ?? 1))),
        ];
    }

    /**
     * Build WP_Query args from parsed params
     *
     * @param array<string,mixed> $parsed
     * @return array<string,mixed>
     */
    public function buildQueryArgs(array $parsed): array
    {
        $args = [
            'post_type'      => $parsed['post_type'],
            'post_status'    => 'publish',
            'posts_per_page' => 18,
            'paged'          => (int) ($parsed['paged'] ?? 1),
        ];

        // Text Search
        if ($parsed['s'] !== '') {
            $args['s'] = $parsed['s'];
        }

        // Taxonomies
        $taxQuery = [];
        if ($parsed['helmet_type'] !== []) {
            $taxQuery[] = ['taxonomy' => 'helmet_type', 'field' => 'slug', 'terms' => $parsed['helmet_type']];
        }
        if ($parsed['certification'] !== []) {
            $taxQuery[] = ['taxonomy' => 'certification', 'field' => 'slug', 'terms' => $parsed['certification']];
        }
        if ($parsed['feature'] !== []) {
            $taxQuery[] = ['taxonomy' => 'feature_tag', 'field' => 'slug', 'terms' => $parsed['feature']];
        }
        if (isset($parsed['price_range']) && is_array($parsed['price_range']) && $parsed['price_range'] !== []) {
            $taxQuery[] = ['taxonomy' => 'price_range', 'field' => 'slug', 'terms' => $parsed['price_range']];
        }
        if (isset($parsed['region']) && is_array($parsed['region']) && $parsed['region'] !== []) {
            $taxQuery[] = ['taxonomy' => 'region', 'field' => 'slug', 'terms' => $parsed['region']];
        }
        if (isset($parsed['use_case']) && is_array($parsed['use_case']) && $parsed['use_case'] !== []) {
            $taxQuery[] = ['taxonomy' => 'use_case', 'field' => 'slug', 'terms' => $parsed['use_case']];
        }
        if ($parsed['size'] !== []) {
            $taxQuery[] = ['taxonomy' => 'size', 'field' => 'slug', 'terms' => $parsed['size']];
        }
        if ($taxQuery !== []) {
            if (count($taxQuery) > 1) $taxQuery['relation'] = 'AND';
            $args['tax_query'] = $taxQuery;
        }

        // Meta Query
        $metaQuery = [];
        
        // We now offload these to our custom product_index table via posts_clauses filter.
        // We just need to pass the parsed params to the query object so the filter can use them.
        $args['hs_filters'] = $parsed;

        return $args;
    }

    /**
     * Intercept Main Query to apply search filters and avoid dual query performance hit.
     */
    public function interceptMainQuery(\WP_Query $query): void
    {
        if (is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive('helmet')) {
            return;
        }

        $parsed = $this->parseParams($_GET);
        $args = $this->buildQueryArgs($parsed);
        
        // Ensure posts_per_page matches our design
        $args['posts_per_page'] = 40;

        foreach ($args as $key => $value) {
            $query->set($key, $value);
        }
    }

    /**
     * Run Query
     */
    public function query(array $params): WP_Query
    {
        $parsed = $this->parseParams($params);
        $args = $this->buildQueryArgs($parsed);
        return new WP_Query($args);
    }

    /** Meta keys used for identifier search (helmet and accessory). */
    private const IDENTIFIER_META_KEYS = ['ean', 'gtin', 'upc', 'affiliate_asin', 'sku', 'mpn', 'fsn'];

    /**
     * Register hook to improve search
     */
    public function register(): void
    {
        add_filter('posts_clauses', [$this, 'modifySearchClauses'], 10, 2);
        add_filter('posts_distinct', [$this, 'distinct']);

        add_action('wp_ajax_helmetsan_filter', [$this, 'handleAjax']);
        add_action('wp_ajax_nopriv_helmetsan_filter', [$this, 'handleAjax']);
        add_action('wp_head', [$this, 'noindexEmptyResults'], 1);

        // Clear AJAX search caches on catalog changes
        add_action('save_post_helmet', [$this, 'clearSearchCache']);
        add_action('save_post_accessory', [$this, 'clearSearchCache']);
    }

    /**
     * Prevent Soft 404s by noindexing empty filter/search results.
     */
    public function noindexEmptyResults(): void
    {
        if (is_admin()) {
            return;
        }

        global $wp_query;
        if (! isset($wp_query) || ! $wp_query->is_main_query()) {
            return;
        }

        $isSearch = !empty($_GET['s']) || !empty($_GET['brand_slug']) || !empty($_GET['helmet_type']) || !empty($_GET['category']);
        
        if ($isSearch && (int) $wp_query->found_posts === 0) {
            // Avoid duplicate or conflicting tag if Yoast SEO or core wp_robots has already handled it
            if (! did_action('wp_robots') && ! defined('WPSEO_VERSION')) {
                echo '<meta name="robots" content="noindex, nofollow">' . "\n";
            }
        }
    }

    /**
     * AJAX Handler
     */
    public function handleAjax(): void
    {
        // Security check? For public read-only, nonce is good but optional for caching. Let's add nonce check if present.
        
        $params = $_GET; // or $_POST
        
        $cacheKey = 'ajax_' . md5(wp_json_encode($params));
        $cached = ObjectCacheService::get($cacheKey, ObjectCacheService::GROUP_SEARCH);
        
        if ($cached !== false && !is_user_logged_in()) {
            wp_send_json_success($cached);
            exit;
        }

        $query = $this->query($params);

        ob_start();
        if ($query->have_posts()) {
            $view = sanitize_key($params['view'] ?? 'grid');
            $cols = (int) ($params['cols'] ?? 4);
            
            echo '<div class="hs-catalog-grid hs-catalog-grid--' . esc_attr($view) . ' hs-catalog-grid--cols-' . esc_attr((string) $cols) . '" id="helmet-results">';
            while ($query->have_posts()) {
                $query->the_post();
                get_template_part('template-parts/helmet-card');
            }
            echo '</div>';
            
            echo '<div class="hs-pagination-footer">';
            $paged = max(1, $query->get('paged'));
            $max_pages = (int) $query->max_num_pages;
            $ppp = $query->get('posts_per_page');
            $start = (($paged - 1) * $ppp) + 1;
            $end = min($paged * $ppp, $query->found_posts);
            $count_text = sprintf(__('Showing %d–%d of %d', 'helmetsan-theme'), $start, $end, $query->found_posts);
            
            get_template_part('template-parts/pagination-modern', null, [
                'paged' => $paged,
                'total' => $max_pages,
                'count_text' => $count_text
            ]);
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('No helmets found for the selected filters.', 'helmetsan-theme') . '</p>';
        }
        $html = ob_get_clean();
        wp_reset_postdata();

        $nextPage = (int) $query->get('paged') + 1;
        $hasMore = $nextPage <= $query->max_num_pages;

        $chips = $this->renderActiveChips($params);

        $responseData = [
            'html' => $html,
            'count' => (int) $query->found_posts,
            'max_pages' => (int) $query->max_num_pages,
            'current_page' => (int) $query->get('paged'),
            'next_page' => $hasMore ? $nextPage : null,
            'chips' => $chips,
        ];

        if (!is_user_logged_in()) {
            ObjectCacheService::set($cacheKey, $responseData, ObjectCacheService::GROUP_SEARCH, HOUR_IN_SECONDS);
        }

        wp_send_json_success($responseData);
    }

    /**
     * Render Active Chips HTML
     */
    public function renderActiveChips(array $params): string
    {
        $parsed = $this->parseParams($params);
        $chips = [];
        $removeUrl = '#'; // JS handles removal

        foreach ($parsed['helmet_type'] as $slug) {
            $term = get_term_by('slug', $slug, 'helmet_type');
            $label = ($term instanceof WP_Term) ? $term->name : $slug;
            $chips[] = ['label' => $label, 'key' => 'helmet_type', 'value' => $slug];
        }
        foreach ($parsed['certification'] as $slug) {
            $term = get_term_by('slug', $slug, 'certification');
            $label = ($term instanceof WP_Term) ? $term->name : $slug;
            $chips[] = ['label' => $label, 'key' => 'certification', 'value' => $slug];
        }
        foreach ($parsed['feature'] as $slug) {
            $term = get_term_by('slug', $slug, 'feature_tag');
            $label = ($term instanceof WP_Term) ? $term->name : $slug;
            $chips[] = ['label' => $label, 'key' => 'feature', 'value' => $slug];
        }
        foreach ($parsed['size'] as $size) {
            $chips[] = ['label' => 'Size ' . strtoupper($size), 'key' => 'size', 'value' => $size];
        }
        if ($parsed['brand_slug'] !== '') {
            $chips[] = ['label' => ucfirst(str_replace('-', ' ', $parsed['brand_slug'])), 'key' => 'brand_slug', 'value' => ''];
        }
        if ($parsed['helmet_family'] !== '') {
            $chips[] = ['label' => $parsed['helmet_family'], 'key' => 'helmet_family', 'value' => ''];
        }
        if ($parsed['price_min'] !== '') {
            $chips[] = ['label' => 'Min $' . $parsed['price_min'], 'key' => 'price_min', 'value' => ''];
        }
        if ($parsed['price_max'] !== '') {
            $chips[] = ['label' => 'Max $' . $parsed['price_max'], 'key' => 'price_max', 'value' => ''];
        }
        foreach ($parsed['sharp_rating'] as $rating) {
            $chips[] = ['label' => $rating . ' Star Safety', 'key' => 'sharp_rating', 'value' => $rating];
        }
        foreach ($parsed['strap_type'] as $strap) {
            $chips[] = ['label' => $strap, 'key' => 'strap_type', 'value' => $strap];
        }
        foreach ($parsed['comms_ready'] as $comms) {
            $chips[] = ['label' => 'Comms: ' . $comms, 'key' => 'comms_ready', 'value' => $comms];
        }

        if ($chips === []) {
            return '';
        }

        $html = '';
        foreach ($chips as $chip) {
            $html .= sprintf(
                '<button type="button" class="hs-chip" data-filter-key="%s" data-filter-value="%s">%s <span aria-hidden="true">×</span></button>',
                esc_attr($chip['key']),
                esc_attr($chip['value']),
                esc_html($chip['label'])
            );
        }
        return $html;
    }

    /**
     * The magic happens here: use the product_index table for filtering and sorting.
     */
    public function modifySearchClauses(array $clauses, \WP_Query $query): array
    {
        global $wpdb;

        // Apply keyword search extension (EAN/GTIN/SKU matching) if search query is present
        $s = $query->get('s');
        if (is_string($s) && trim($s) !== '') {
            $clauses['where'] = $this->applyKeywordSearchExtension($clauses['where'], $query);
        }

        $filters = $query->get('hs_filters');
        if (!is_array($filters)) {
            return $clauses;
        }

        $table = $wpdb->prefix . 'helmetsan_product_index';
        $clauses['join'] .= " LEFT JOIN {$table} hs_idx ON {$wpdb->posts}.ID = hs_idx.post_id ";

        $where = [];

        // Brand
        if ($filters['brand_slug'] !== '') {
            $brandPost = get_page_by_path($filters['brand_slug'], OBJECT, 'brand');
            if (!$brandPost instanceof \WP_Post) {
                 $altSlug = (str_ends_with($filters['brand_slug'], '-helmets')) ? substr($filters['brand_slug'], 0, -8) : $filters['brand_slug'] . '-helmets';
                 $brandPost = get_page_by_path($altSlug, OBJECT, 'brand');
            }
            if ($brandPost instanceof \WP_Post) {
                $where[] = $wpdb->prepare("hs_idx.brand_id = %d", (int) $brandPost->ID);
            } else {
                $where[] = "1=0"; // Force empty
            }
        }

        // Categories (index-based)
        if ($filters['category'] !== []) {
            $cats = array_map('esc_sql', $filters['category']);
            $where[] = "hs_idx.category IN ('" . implode("','", $cats) . "')";
        }

        // Helmet Family
        if ($filters['helmet_family'] !== '') {
            $where[] = $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like($filters['helmet_family']) . '%');
        }

        // Price
        if (is_numeric($filters['price_min'])) {
            $where[] = $wpdb->prepare("hs_idx.price_usd >= %f", (float) $filters['price_min']);
        }
        if (is_numeric($filters['price_max'])) {
            $where[] = $wpdb->prepare("hs_idx.price_usd <= %f", (float) $filters['price_max']);
        }

        // Ratings
        if ($filters['sharp_rating'] !== []) {
            $ratings = array_map('intval', $filters['sharp_rating']);
            $where[] = "hs_idx.sharp_rating IN (" . implode(',', $ratings) . ")";
        }

        // Enums
        if ($filters['strap_type'] !== []) {
            $straps = array_map('esc_sql', $filters['strap_type']);
            $where[] = "hs_idx.strap_type IN ('" . implode("','", $straps) . "')";
        }
        if ($filters['comms_ready'] !== []) {
            $comms = array_map('esc_sql', $filters['comms_ready']);
            $where[] = "hs_idx.comms_ready IN ('" . implode("','", $comms) . "')";
        }

        if ($where !== []) {
            $clauses['where'] .= " AND (" . implode(" AND ", $where) . ") ";
        }

        // Sorting
        switch ($filters['sort']) {
            case 'price_low':
            case 'price_asc':
                $clauses['orderby'] = "CASE WHEN hs_idx.price_usd IS NULL OR hs_idx.price_usd <= 0 THEN 1 ELSE 0 END ASC, hs_idx.price_usd ASC, {$wpdb->posts}.post_date DESC";
                break;
            case 'price_high':
            case 'price_desc':
                $clauses['orderby'] = "CASE WHEN hs_idx.price_usd IS NULL THEN 1 ELSE 0 END ASC, hs_idx.price_usd DESC, {$wpdb->posts}.post_date DESC";
                break;
            case 'rating':
            case 'top_rated':
            case 'user_rating':
                $clauses['orderby'] = "hs_idx.sharp_rating DESC, hs_idx.user_rating DESC, {$wpdb->posts}.post_date DESC";
                break;
            case 'weight':
                $clauses['orderby'] = "CASE WHEN hs_idx.weight_g IS NULL OR hs_idx.weight_g <= 0 THEN 1 ELSE 0 END ASC, hs_idx.weight_g ASC, {$wpdb->posts}.post_date DESC";
                break;
            case 'relevance':
                $s = $query->get('s');
                if (is_string($s) && trim($s) !== '') {
                    break;
                }
                $clauses['orderby'] = "hs_idx.sharp_rating DESC, {$wpdb->posts}.post_date DESC";
                break;
            case 'newest':
            default:
                $clauses['orderby'] = "{$wpdb->posts}.post_date DESC";
                break;
        }

        return $clauses;
    }

    private function applyKeywordSearchExtension(string $where, \WP_Query $query): string
    {
        $postType = $query->get('post_type');
        $types = is_array($postType) ? $postType : [$postType];
        $allowed = array_intersect($types, ['helmet', 'accessory']);
        if ($allowed === []) {
            return $where;
        }

        $s = $query->get('s');
        if (! is_string($s) || trim($s) === '') {
            return $where;
        }

        $s = trim($s);
        global $wpdb;
        $keys = implode("','", array_map('esc_sql', self::IDENTIFIER_META_KEYS));
        $subquery = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('{$keys}') AND meta_value = %s",
            $s
        );
        $extra = " OR ({$wpdb->posts}.ID IN ({$subquery}))";
        return $where . $extra;
    }

    public function distinct($distinct) {
        if (is_admin() || !is_search()) return $distinct;
        return "DISTINCT";
    }

    /**
     * Flush all cached AJAX search filters and invalidate search cache group on product saves.
     * Uses targeted group invalidation without destroying unrelated site caches.
     */
    public function clearSearchCache(): void
    {
        ObjectCacheService::invalidateGroup(ObjectCacheService::GROUP_SEARCH);
    }
}
