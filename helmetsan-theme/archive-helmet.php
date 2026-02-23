<?php
/**
 * Helmet archive template.
 *
 * @package HelmetsanTheme
 */

$archiveUrl = (string) get_post_type_archive_link('helmet');
if ($archiveUrl === '') {
    $archiveUrl = (string) home_url('/helmets/');
}
// Redirect to clean URL when all filter params are empty.
$cleanUrl = $archiveUrl;
$allEmpty = true;
foreach (['brand_slug', 'helmet_family', 'price_min', 'price_max', 'sort'] as $key) {
    if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
        $allEmpty = false;
        break;
    }
}
if ($allEmpty) {
    foreach (['helmet_type', 'certification', 'feature', 'size'] as $key) {
        if (isset($_GET[$key])) {
            $v = $_GET[$key];
            if (is_array($v) && array_filter(array_map('trim', $v)) !== []) {
                $allEmpty = false;
                break;
            }
            if (! is_array($v) && trim((string) $v) !== '') {
                $allEmpty = false;
                break;
            }
        }
    }
}
// Only redirect when we would actually change the URL (strip empty query params). Avoid redirecting
// when already on the clean URL to prevent redirect loops (ERR_TOO_MANY_REDIRECTS).
if ($allEmpty && (! isset($_GET['paged']) || (int) $_GET['paged'] <= 1) && ! empty($_GET)) {
    wp_safe_redirect($cleanUrl, 302);
    exit;
}

get_header();
echo '<script>window.helmetsanListContext={list_id:"helmet_archive",list_name:"Helmet catalog"};</script>' . "\n";

$getString = static function (string $key): string {
    if (! isset($_GET[$key])) {
        return '';
    }
    return sanitize_text_field(wp_unslash((string) $_GET[$key]));
};

$getArray = static function (string $key): array {
    if (! isset($_GET[$key])) {
        return [];
    }
    $raw = $_GET[$key];
    if (! is_array($raw)) {
        $raw = [$raw];
    }
    $out = [];
    foreach ($raw as $item) {
        $value = sanitize_text_field(wp_unslash((string) $item));
        if ($value !== '') {
            $out[] = $value;
        }
    }
    return array_values(array_unique($out));
};

$selectedTypes = $getArray('helmet_type');
$selectedCerts = $getArray('certification');
$selectedFeatures = $getArray('feature');
$selectedSize = $getArray('size');
$brandSlug = sanitize_title($getString('brand_slug'));
$helmetFamily = $getString('helmet_family');
$priceMin = $getString('price_min');
$priceMax = $getString('price_max');
$sort = $getString('sort');
if ($sort === '') {
    $sort = 'newest';
}

$helmetTypeTerms = get_terms([
    'taxonomy' => 'helmet_type',
    'hide_empty' => true,
]);
$certTerms = get_terms([
    'taxonomy' => 'certification',
    'hide_empty' => true,
]);
$featureTerms = get_terms([
    'taxonomy' => 'feature_tag',
    'hide_empty' => true,
]);

$brandPosts = get_posts([
    'post_type' => 'brand',
    'post_status' => 'publish',
    'posts_per_page' => 250,
    'orderby' => 'title',
    'order' => 'ASC',
]);

$paged = max(1, (int) ($getString('paged') !== '' ? $getString('paged') : get_query_var('paged', 1)));
$args = [
    'post_type' => 'helmet',
    'post_status' => 'publish',
    'posts_per_page' => 40,
    'paged' => $paged,
];

$taxQuery = [];
if ($selectedTypes !== []) {
    $taxQuery[] = [
        'taxonomy' => 'helmet_type',
        'field' => 'slug',
        'terms' => $selectedTypes,
    ];
}
if ($selectedCerts !== []) {
    $taxQuery[] = [
        'taxonomy' => 'certification',
        'field' => 'slug',
        'terms' => $selectedCerts,
    ];
}
if ($selectedFeatures !== []) {
    $taxQuery[] = [
        'taxonomy' => 'feature_tag',
        'field' => 'slug',
        'terms' => $selectedFeatures,
    ];
}
if ($taxQuery !== []) {
    if (count($taxQuery) > 1) {
        $taxQuery['relation'] = 'AND';
    }
    $args['tax_query'] = $taxQuery;
}

$metaQuery = [];
if ($brandSlug !== '') {
    $brandPost = get_page_by_path($brandSlug, OBJECT, 'brand');
    // Fallback: Try with '-helmets' suffix if it was omitted, or vice-versa
    if (! ($brandPost instanceof WP_Post)) {
        $altSlug = (str_ends_with($brandSlug, '-helmets')) 
            ? substr($brandSlug, 0, -8) 
            : $brandSlug . '-helmets';
        $brandPost = get_page_by_path($altSlug, OBJECT, 'brand');
    }

    if ($brandPost instanceof WP_Post) {
        $metaQuery[] = [
            'key' => 'rel_brand',
            'value' => (int) $brandPost->ID,
        ];
    } else {
        // If brand slug is invalid, force 0 results instead of showing everything
        $args['post__in'] = [0];
    }
}
if ($helmetFamily !== '') {
    $metaQuery[] = [
        'key' => 'helmet_family',
        'value' => $helmetFamily,
        'compare' => 'LIKE',
    ];
}
if ($selectedSize !== []) {
    $sizeQuery = ['relation' => 'OR'];
    foreach ($selectedSize as $sizeValue) {
        $sizeQuery[] = [
            'key' => 'size_chart_json',
            'value' => $sizeValue,
            'compare' => 'LIKE',
        ];
        $sizeQuery[] = [
            'key' => 'variant_matrix_json',
            'value' => $sizeValue,
            'compare' => 'LIKE',
        ];
    }
    $metaQuery[] = $sizeQuery;
}
if ($priceMin !== '' && is_numeric($priceMin)) {
    $metaQuery[] = [
        'key' => 'price_retail_usd',
        'value' => (float) $priceMin,
        'type' => 'NUMERIC',
        'compare' => '>=',
    ];
}
if ($priceMax !== '' && is_numeric($priceMax)) {
    $metaQuery[] = [
        'key' => 'price_retail_usd',
        'value' => (float) $priceMax,
        'type' => 'NUMERIC',
        'compare' => '<=',
    ];
}
if ($metaQuery !== []) {
    if (count($metaQuery) > 1) {
        $metaQuery['relation'] = 'AND';
    }
    $args['meta_query'] = $metaQuery;
}

switch ($sort) {
    case 'price_asc':
        $args['meta_key'] = 'price_retail_usd';
        $args['orderby'] = 'meta_value_num';
        $args['order'] = 'ASC';
        break;
    case 'price_desc':
        $args['meta_key'] = 'price_retail_usd';
        $args['orderby'] = 'meta_value_num';
        $args['order'] = 'DESC';
        break;
    case 'top_rated':
        $args['meta_key'] = 'safety_sharp_rating';
        $args['orderby'] = 'meta_value_num';
        $args['order'] = 'DESC';
        break;
    case 'newest':
    default:
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
        break;
}

$query = new WP_Query($args);

$currentQuery = [];
foreach ($_GET as $k => $v) {
    if ($k === 'paged') {
        continue;
    }
    if (is_array($v)) {
        $vals = array_map(static fn($it): string => sanitize_text_field(wp_unslash((string) $it)), $v);
        $vals = array_filter($vals, static fn($it): bool => $it !== '');
        if ($vals !== []) {
            $currentQuery[$k] = array_values($vals);
        }
    } else {
        $sv = sanitize_text_field(wp_unslash((string) $v));
        if ($sv !== '') {
            $currentQuery[$k] = $sv;
        }
    }
}

// Canonical redirect: strip empty query params so pagination and layout stay consistent (no ?brand_slug=&price_min=...).
$hasEmptyParam = false;
foreach ($_GET as $k => $v) {
    if ($k === 'paged') {
        continue;
    }
    if (is_array($v)) {
        if (array_filter(array_map('trim', array_map('strval', $v))) === []) {
            $hasEmptyParam = true;
            break;
        }
    } elseif (trim((string) $v) === '') {
        $hasEmptyParam = true;
        break;
    }
}
if ($hasEmptyParam) {
    $canonicalArgs = $currentQuery;
    if ($paged > 1) {
        $canonicalArgs['paged'] = $paged;
    }
    if (isset($canonicalArgs['sort']) && $canonicalArgs['sort'] === 'newest') {
        unset($canonicalArgs['sort']);
    }
    $canonicalUrl = (string) add_query_arg($canonicalArgs, $archiveUrl);
    wp_safe_redirect($canonicalUrl, 302);
    exit;
}

$removeFilterUrl = static function (string $key, string $value = '') use ($archiveUrl, $currentQuery): string {
    $queryArgs = $currentQuery;
    if (! isset($queryArgs[$key])) {
        return $archiveUrl;
    }
    if ($value === '' || ! is_array($queryArgs[$key])) {
        unset($queryArgs[$key]);
    } else {
        $queryArgs[$key] = array_values(array_filter(
            $queryArgs[$key],
            static fn($item): bool => (string) $item !== $value
        ));
        if ($queryArgs[$key] === []) {
            unset($queryArgs[$key]);
        }
    }

    return (string) add_query_arg($queryArgs, $archiveUrl);
};

$activeChips = [];
foreach ($selectedTypes as $slug) {
    $term = get_term_by('slug', $slug, 'helmet_type');
    $activeChips[] = ['label' => ($term instanceof WP_Term ? $term->name : $slug), 'url' => $removeFilterUrl('helmet_type', $slug)];
}
foreach ($selectedCerts as $slug) {
    $term = get_term_by('slug', $slug, 'certification');
    $activeChips[] = ['label' => ($term instanceof WP_Term ? $term->name : $slug), 'url' => $removeFilterUrl('certification', $slug)];
}
foreach ($selectedFeatures as $slug) {
    $term = get_term_by('slug', $slug, 'feature_tag');
    $activeChips[] = ['label' => ($term instanceof WP_Term ? $term->name : $slug), 'url' => $removeFilterUrl('feature', $slug)];
}
foreach ($selectedSize as $size) {
    $activeChips[] = ['label' => 'Size ' . strtoupper($size), 'url' => $removeFilterUrl('size', $size)];
}
if ($brandSlug !== '') {
    $activeChips[] = ['label' => ucfirst(str_replace('-', ' ', $brandSlug)), 'url' => $removeFilterUrl('brand_slug')];
}
if ($helmetFamily !== '') {
    $activeChips[] = ['label' => $helmetFamily, 'url' => $removeFilterUrl('helmet_family')];
}
if ($priceMin !== '') {
    $activeChips[] = ['label' => 'Min $' . $priceMin, 'url' => $removeFilterUrl('price_min')];
}
if ($priceMax !== '') {
    $activeChips[] = ['label' => 'Max $' . $priceMax, 'url' => $removeFilterUrl('price_max')];
}

$sizeOptions = ['xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'];
?>
<section class="hs-section hs-section--archive">
    <header class="hs-archive-hero">
        <h1 class="hs-archive-hero__title"><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p class="hs-archive-hero__subtitle">Size-first, safety-aware catalog with faceted filters by type, brand, certification, and more.</p>
    </header>

    <div class="hs-catalog">
        <aside id="hsFilterPanel" class="hs-catalog__filters hs-panel" aria-label="Helmet filters">
            <div class="hs-catalog__filters-head">
                <strong>Filters</strong>
                <button type="button" class="hs-mobile-filter-close" data-close-filter>Close</button>
            </div>
            <form id="hsHelmetFilterForm" method="get" action="<?php echo esc_url($archiveUrl); ?>">
                <details class="hs-filter-group" open id="hs-filter-size">
                    <summary>Size</summary>
                    <div class="hs-pill-grid">
                        <?php foreach ($sizeOptions as $sizeOption) : ?>
                            <label class="hs-pill-input">
                                <input type="checkbox" name="size[]" value="<?php echo esc_attr($sizeOption); ?>" <?php checked(in_array($sizeOption, $selectedSize, true)); ?> />
                                <span><?php echo esc_html(strtoupper($sizeOption)); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="hs-filter-group" open>
                    <summary>Helmet Type</summary>
                    <div class="hs-filter-checks">
                        <?php if (is_array($helmetTypeTerms)) : foreach ($helmetTypeTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                            <label><input type="checkbox" name="helmet_type[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedTypes, true)); ?> /> <?php echo esc_html($term->name); ?></label>
                        <?php endforeach; endif; ?>
                    </div>
                </details>

                <details class="hs-filter-group">
                    <summary>Brand</summary>
                    <div class="hs-filter-checks hs-filter-scroll">
                        <?php foreach ($brandPosts as $brand) : if (! ($brand instanceof WP_Post)) { continue; } $slug = sanitize_title($brand->post_name); ?>
                            <label><input type="radio" name="brand_slug" value="<?php echo esc_attr($slug); ?>" <?php checked($brandSlug, $slug); ?> /> <?php echo esc_html($brand->post_title); ?></label>
                        <?php endforeach; ?>
                        <label><input type="radio" name="brand_slug" value="" <?php checked($brandSlug, ''); ?> /> All Brands</label>
                    </div>
                </details>

                <details class="hs-filter-group">
                    <summary>Price</summary>
                    <div class="hs-price-range">
                        <input type="number" name="price_min" value="<?php echo esc_attr($priceMin); ?>" min="0" step="1" placeholder="Min" />
                        <input type="number" name="price_max" value="<?php echo esc_attr($priceMax); ?>" min="0" step="1" placeholder="Max" />
                    </div>
                </details>

                <details class="hs-filter-group">
                    <summary>Safety & Certification Marks</summary>
                    <div class="hs-filter-checks">
                        <?php if (is_array($certTerms)) : foreach ($certTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                            <label><input type="checkbox" name="certification[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedCerts, true)); ?> /> <?php echo esc_html($term->name); ?></label>
                        <?php endforeach; endif; ?>
                    </div>
                </details>

                <details class="hs-filter-group">
                    <summary>Features</summary>
                    <div class="hs-filter-checks hs-filter-scroll">
                        <?php if (is_array($featureTerms)) : foreach ($featureTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                            <label><input type="checkbox" name="feature[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedFeatures, true)); ?> /> <?php echo esc_html($term->name); ?></label>
                        <?php endforeach; endif; ?>
                    </div>
                </details>

                <details class="hs-filter-group">
                    <summary>Helmet Model</summary>
                    <input type="text" name="helmet_family" value="<?php echo esc_attr($helmetFamily); ?>" placeholder="e.g. Shoei RF1400" />
                </details>

                <div class="hs-filter-actions">
                    <a class="hs-btn hs-btn--text" href="<?php echo esc_url($archiveUrl); ?>">Clear All</a>
                    <button class="hs-btn hs-btn--primary" type="submit">Show <?php echo esc_html((string) max(0, (int) $query->found_posts)); ?> Helmets</button>
                </div>
                <script>
                document.getElementById('hsHelmetFilterForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    var form = this;
                    var params = new URLSearchParams();
                    var data = new FormData(form);
                    data.forEach(function(value, key) {
                        if (value !== '' && value !== null) {
                            params.append(key, value);
                        }
                    });
                    var qs = params.toString();
                    window.location.href = form.action + (qs ? '?' + qs : '');
                });
                </script>
            </form>
        </aside>

        <div class="hs-catalog__results">
            <div class="hs-catalog__topbar hs-panel">
                <div class="hs-catalog__count"><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?> Helmets</div>
                <div class="hs-catalog__chips">
                    <?php foreach ($activeChips as $chip) : ?>
                        <a class="hs-chip" href="<?php echo esc_url((string) $chip['url']); ?>"><?php echo esc_html((string) $chip['label']); ?> <span aria-hidden="true">×</span></a>
                    <?php endforeach; ?>
                </div>
                <form class="hs-catalog__sort" method="get" action="<?php echo esc_url($archiveUrl); ?>">
                    <?php
                    foreach ($currentQuery as $k => $v) {
                        if ($k === 'sort') {
                            continue;
                        }
                        if (is_array($v)) {
                            foreach ($v as $item) {
                                echo '<input type="hidden" name="' . esc_attr($k) . '[]" value="' . esc_attr((string) $item) . '">';
                            }
                        } else {
                            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) $v) . '">';
                        }
                    }
                    ?>
                    <label for="hsSort" class="screen-reader-text">Sort</label>
                    <select id="hsSort" name="sort" onchange="this.form.submit()">
                        <option value="newest" <?php selected($sort, 'newest'); ?>>Newest</option>
                        <option value="price_asc" <?php selected($sort, 'price_asc'); ?>>Price Low → High</option>
                        <option value="price_desc" <?php selected($sort, 'price_desc'); ?>>Price High → Low</option>
                        <option value="top_rated" <?php selected($sort, 'top_rated'); ?>>Top Rated</option>
                    </select>
                </form>
            </div>

            <?php if ($query->have_posts()) : ?>
                <section class="hs-catalog__results-content">
                <div class="helmet-grid">
                    <?php
                    while ($query->have_posts()) : $query->the_post();
                        get_template_part('template-parts/helmet-card');
                    endwhile;
                    ?>
                </div>

                <div class="hs-pagination-wrap">
                    <?php
                    // Temporarily replace the global query with our custom query
                    // so the_posts_pagination reads the correct page/totals.
                    global $wp_query;
                    $original_query = $wp_query;
                    $wp_query = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

                    the_posts_pagination([
                        'total' => $query->max_num_pages,
                        'current' => $paged,
                        'add_args' => $currentQuery,
                        'mid_size'  => 2,
                        'prev_text' => __( '&larr; Prev', 'helmetsan-theme' ),
                        'next_text' => __( 'Next &rarr;', 'helmetsan-theme' ),
                        'screen_reader_text' => __( 'Helmet Navigation', 'helmetsan-theme' ),
                    ]);

                    $wp_query = $original_query; // Restore original query

                    ?>
                </div>
            </section>
                <?php
                wp_reset_postdata();
                ?>
            <?php else : ?>
                <p><?php esc_html_e('No helmets found for the selected filters.', 'helmetsan-theme'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="hs-mobile-tools" aria-label="Mobile catalog tools">
    <button type="button" data-open-filter>Filter</button>
    <button type="button" data-open-sort>Sort</button>
    <button type="button" data-open-size>Size</button>
</div>
<?php
get_footer();
