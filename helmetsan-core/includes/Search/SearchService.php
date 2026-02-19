<?php

declare(strict_types=1);

namespace Helmetsan\Core\Search;

use WP_Query;
use WP_Post;
use WP_Term;

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
            's'             => $getString('s'),
            'helmet_type'   => $getArray('helmet_type'),
            'certification' => $getArray('certification'),
            'feature'       => $getArray('feature'),
            'size'          => $getArray('size'),
            'brand_slug'    => sanitize_title($getString('brand_slug')),
            'helmet_family' => $getString('helmet_family'),
            'price_min'     => $getString('price_min'),
            'price_max'     => $getString('price_max'),
            'sort'          => $getString('sort') ?: 'newest',
            'paged'         => max(1, (int) ($params['paged'] ?? 1)),
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
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => 18,
            'paged'          => $parsed['paged'],
            'post_parent'    => 0, // Only show Parent Models
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
        if ($taxQuery !== []) {
            if (count($taxQuery) > 1) $taxQuery['relation'] = 'AND';
            $args['tax_query'] = $taxQuery;
        }

        // Meta Query
        $metaQuery = [];
        
        // Brand logic
        if ($parsed['brand_slug'] !== '') {
            $brandPost = get_page_by_path($parsed['brand_slug'], OBJECT, 'brand');
            if (!$brandPost instanceof WP_Post) {
                 // Try suffix fallback
                 $altSlug = (str_ends_with($parsed['brand_slug'], '-helmets')) 
                    ? substr($parsed['brand_slug'], 0, -8) 
                    : $parsed['brand_slug'] . '-helmets';
                 $brandPost = get_page_by_path($altSlug, OBJECT, 'brand');
            }

            if ($brandPost instanceof WP_Post) {
                $metaQuery[] = ['key' => 'rel_brand', 'value' => (int) $brandPost->ID];
            } else {
                $args['post__in'] = [0]; // Force empty
            }
        }

        if ($parsed['helmet_family'] !== '') {
            $metaQuery[] = ['key' => 'helmet_family', 'value' => $parsed['helmet_family'], 'compare' => 'LIKE'];
        }

        if ($parsed['size'] !== []) {
            $sizeQuery = ['relation' => 'OR'];
            foreach ($parsed['size'] as $sizeValue) {
                $sizeQuery[] = ['key' => 'size_chart_json', 'value' => $sizeValue, 'compare' => 'LIKE'];
                $sizeQuery[] = ['key' => 'variant_matrix_json', 'value' => $sizeValue, 'compare' => 'LIKE'];
            }
            $metaQuery[] = $sizeQuery;
        }

        if (is_numeric($parsed['price_min'])) {
            $metaQuery[] = ['key' => 'price_retail_usd', 'value' => (float) $parsed['price_min'], 'type' => 'NUMERIC', 'compare' => '>='];
        }
        if (is_numeric($parsed['price_max'])) {
            $metaQuery[] = ['key' => 'price_retail_usd', 'value' => (float) $parsed['price_max'], 'type' => 'NUMERIC', 'compare' => '<='];
        }

        if ($metaQuery !== []) {
            if (count($metaQuery) > 1) $metaQuery['relation'] = 'AND';
            $args['meta_query'] = $metaQuery;
        }

        // Sorting
        switch ($parsed['sort']) {
            case 'price_asc':
                $args['meta_key'] = 'price_retail_usd'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'ASC'; break;
            case 'price_desc':
                $args['meta_key'] = 'price_retail_usd'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
            case 'top_rated':
                $args['meta_key'] = 'safety_sharp_rating'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
            case 'newest':
            default:
                $args['orderby'] = 'date'; $args['order'] = 'DESC'; break;
        }

        return $args;
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

    /**
     * Register hook to improve search
     */
    public function register(): void
    {
        add_filter('posts_join', [$this, 'joinPostMeta']);
        add_filter('posts_where', [$this, 'wherePostMeta']);
        add_filter('posts_distinct', [$this, 'distinct']);
        
        // Register AJAX endpoint
        add_action('wp_ajax_helmetsan_filter', [$this, 'handleAjax']);
        add_action('wp_ajax_nopriv_helmetsan_filter', [$this, 'handleAjax']);
    }

    /**
     * AJAX Handler
     */
    public function handleAjax(): void
    {
        // Security check? For public read-only, nonce is good but optional for caching. Let's add nonce check if present.
        
        $params = $_GET; // or $_POST
        $query = $this->query($params);

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                get_template_part('template-parts/helmet', 'card');
            }
        } else {
            echo '<p>No helmets found.</p>';
        }
        $html = ob_get_clean();

        // Pagination: If ajax_load_more is true, we don't need the list pagination as much, 
        // but we DO need to know next page number.
        $nextPage = (int) $query->get('paged') + 1;
        $hasMore = $nextPage <= $query->max_num_pages;

        // Keep standard pagination for SEO / fallback
        $pagination = paginate_links([
            'base' => '%_%',
            'format' => '?paged=%#%',
            'current' => max(1, $query->get('paged')),
            'total' => $query->max_num_pages,
            'type' => 'list',
        ]);

        $chips = $this->renderActiveChips($params);

        wp_send_json_success([
            'html' => $html,
            'pagination' => $pagination,
            'count' => (int) $query->found_posts,
            'max_pages' => (int) $query->max_num_pages,
            'current_page' => (int) $query->get('paged'),
            'next_page' => $hasMore ? $nextPage : null,
            'chips' => $chips,
        ]);
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

    /* 
     * Enhanced Search Logic (Join Meta & Taxonomies)
     * Limit this to main search query or specifically requested via a flag
     */ 
    public function joinPostMeta($join) {
        if (is_admin() || !is_search()) return $join;
        
        global $wpdb;
        // Example: Join postmeta for searching custom fields
        // This can be heavy. Only do if necessary.
        // For now, let's keep it simple and focus on the FACETED filtering first.
        
        return $join;
    }
    
    public function wherePostMeta($where) {
        return $where;
    }

    public function distinct($distinct) {
        if (is_admin() || !is_search()) return $distinct;
        return "DISTINCT";
    }
}
