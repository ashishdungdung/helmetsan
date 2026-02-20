<?php
/**
 * AJAX Instant Filtering for Archive
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_helmetsan_filter', 'helmetsan_ajax_filter_handler');
add_action('wp_ajax_nopriv_helmetsan_filter', 'helmetsan_ajax_filter_handler');

function helmetsan_ajax_filter_handler(): void
{
    global $wpdb;
    
    $indexTable = $wpdb->prefix . 'helmetsan_helmet_index';
    $postTable = $wpdb->posts;
    $metaTable = $wpdb->postmeta;

    // Check if table exists (fallback to standard WP_Query if not)
    $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $indexTable)) === $indexTable;

    $selectedTypes = isset($_GET['helmet_type']) ? array_map('sanitize_text_field', (array)$_GET['helmet_type']) : [];
    $selectedCerts = isset($_GET['certification']) ? array_map('sanitize_text_field', (array)$_GET['certification']) : [];
    $selectedFeatures = isset($_GET['feature']) ? array_map('sanitize_text_field', (array)$_GET['feature']) : [];
    $selectedSize = isset($_GET['size']) ? array_map('sanitize_text_field', (array)$_GET['size']) : [];
    
    $brandSlug = isset($_GET['brand_slug']) ? sanitize_title($_GET['brand_slug']) : '';
    $helmetFamily = isset($_GET['helmet_family']) ? sanitize_text_field($_GET['helmet_family']) : '';
    $priceMin = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
    $priceMax = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 0;
    $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest';
    $page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
    $perPage = 18;

    $args = [
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'posts_per_page' => $perPage,
        'paged' => $page,
    ];

    $taxQuery = [];
    if (!empty($selectedTypes)) {
        $taxQuery[] = ['taxonomy' => 'helmet_type', 'field' => 'slug', 'terms' => $selectedTypes];
    }
    if (!empty($selectedCerts)) {
        $taxQuery[] = ['taxonomy' => 'certification', 'field' => 'slug', 'terms' => $selectedCerts];
    }
    if (!empty($selectedFeatures)) {
        $taxQuery[] = ['taxonomy' => 'feature_tag', 'field' => 'slug', 'terms' => $selectedFeatures];
    }
    if (!empty($taxQuery)) {
        $taxQuery['relation'] = 'AND';
        $args['tax_query'] = $taxQuery;
    }

    $metaQuery = [];
    if ($brandSlug !== '') {
        $brandPost = get_page_by_path($brandSlug, OBJECT, 'brand');
        if (!$brandPost) {
            $altSlug = str_ends_with($brandSlug, '-helmets') ? substr($brandSlug, 0, -8) : $brandSlug . '-helmets';
            $brandPost = get_page_by_path($altSlug, OBJECT, 'brand');
        }
        if ($brandPost) {
            $metaQuery[] = ['key' => 'rel_brand', 'value' => $brandPost->ID];
        } else {
            $args['post__in'] = [0];
        }
    }
    if ($helmetFamily !== '') {
        $metaQuery[] = ['key' => 'helmet_family', 'value' => $helmetFamily, 'compare' => 'LIKE'];
    }
    if (!empty($selectedSize)) {
        $sizeQuery = ['relation' => 'OR'];
        foreach ($selectedSize as $sizeValue) {
            $sizeQuery[] = ['key' => 'size_chart_json', 'value' => $sizeValue, 'compare' => 'LIKE'];
            $sizeQuery[] = ['key' => 'variant_matrix_json', 'value' => $sizeValue, 'compare' => 'LIKE'];
        }
        $metaQuery[] = $sizeQuery;
    }
    if ($priceMin > 0) {
        $metaQuery[] = ['key' => 'price_retail_usd', 'value' => $priceMin, 'type' => 'NUMERIC', 'compare' => '>='];
    }
    if ($priceMax > 0) {
        $metaQuery[] = ['key' => 'price_retail_usd', 'value' => $priceMax, 'type' => 'NUMERIC', 'compare' => '<='];
    }
    if (!empty($metaQuery)) {
        $metaQuery['relation'] = 'AND';
        $args['meta_query'] = $metaQuery;
    }

    switch ($sort) {
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

    if ($tableExists && (empty($selectedSize) && empty($helmetFamily) && $sort !== 'top_rated')) {
        // Use wp_helmetsan_helmet_index for faster query
        $where = ["1=1"];
        $order = "post_id DESC";
        
        if (!empty($selectedTypes)) {
            $typesList = implode("','", array_map('esc_sql', $selectedTypes));
            $typeNames = [];
            foreach ($selectedTypes as $slug) {
                $term = get_term_by('slug', $slug, 'helmet_type');
                if ($term) $typeNames[] = $term->name;
            }
            if (!empty($typeNames)) {
                $namesList = implode("','", array_map('esc_sql', $typeNames));
                $where[] = "helmet_type IN ('$namesList')";
            }
        }

        if ($brandPost) {
            $where[] = "brand_id = " . intval($brandPost->ID);
        }

        if ($priceMin > 0) $where[] = "price_usd >= " . floatval($priceMin);
        if ($priceMax > 0) $where[] = "price_usd <= " . floatval($priceMax);

        if (!empty($selectedCerts)) {
            $certNames = [];
            foreach ($selectedCerts as $slug) {
                $term = get_term_by('slug', $slug, 'certification');
                if ($term) $certNames[] = $term->name;
            }
            if (!empty($certNames)) {
                $certConds = [];
                foreach ($certNames as $name) {
                    $certConds[] = "certifications LIKE '%" . esc_sql($name) . "%'";
                }
                $where[] = "(" . implode(' OR ', $certConds) . ")";
            }
        }

        if (!empty($selectedFeatures)) {
            $featNames = [];
            foreach ($selectedFeatures as $slug) {
                $term = get_term_by('slug', $slug, 'feature_tag');
                if ($term) $featNames[] = $term->name;
            }
            if (!empty($featNames)) {
                $featConds = [];
                foreach ($featNames as $name) {
                    $featConds[] = "features LIKE '%" . esc_sql($name) . "%'";
                }
                $where[] = "(" . implode(' OR ', $featConds) . ")";
            }
        }

        if ($sort === 'price_asc') $order = "price_usd ASC";
        if ($sort === 'price_desc') $order = "price_usd DESC";

        $whereStr = implode(' AND ', $where);
        
        $countQuery = "SELECT COUNT(*) FROM $indexTable WHERE $whereStr";
        $totalResults = (int) $wpdb->get_var($countQuery);

        $offset = ($page - 1) * $perPage;
        $dataQuery = "SELECT post_id FROM $indexTable WHERE $whereStr ORDER BY $order LIMIT $perPage OFFSET $offset";
        $results = $wpdb->get_col($dataQuery);

        if (!empty($results)) {
            $args = [
                'post_type' => 'helmet',
                'post__in' => $results,
                'orderby' => 'post__in',
                'posts_per_page' => $perPage,
                'paged' => 1 // Paging already handled by SQL
            ];
            $query = new WP_Query($args);
            $query->found_posts = $totalResults;
            $query->max_num_pages = ceil($totalResults / $perPage);
        } else {
            $query = new WP_Query(['post__in' => [0]]);
            $query->found_posts = 0;
            $query->max_num_pages = 0;
        }
    } else {
        // Fallback to WP_Query if needing meta joins (Size / Family / Top Rated)
        $query = new WP_Query($args);
    }

    ob_start();
    if ($query->have_posts()) {
        echo '<div class="helmet-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            get_template_part('template-parts/helmet-card');
        }
        echo '</div>';

        echo '<div class="hs-pagination-wrap">';
        // Unset any ajax params before generating pagination links
        unset($_GET['action']);
        
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'current' => $page,
            'total' => $query->max_num_pages,
            'mid_size' => 2,
            'prev_text' => '&larr; Prev',
            'next_text' => 'Next &rarr;',
        ]);
        echo '</div>';
    } else {
        echo '<p>No helmets found for the selected filters.</p>';
    }
    $html = ob_start() ? ob_get_clean() : '';

    wp_send_json_success([
        'html' => $html,
        'count' => $query->found_posts
    ]);
}
