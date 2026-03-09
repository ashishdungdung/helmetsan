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
foreach (['s', 'brand_slug', 'helmet_family', 'price_min', 'price_max', 'sort'] as $key) {
    if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
        $allEmpty = false;
        break;
    }
}
if ($allEmpty) {
    foreach (['helmet_type', 'certification', 'feature', 'size', 'price_range', 'region', 'use_case'] as $key) {
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

// Use plugin SearchService for faceted search (single source of truth for params and query).
$searchService = function_exists('helmetsan_core') ? helmetsan_core()->getSearchService() : null;
$parsed = $searchService !== null ? $searchService->parseParams($_GET) : [];
if ($parsed === []) {
    $getString = static function (string $key): string {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash((string) $_GET[$key])) : '';
    };
    $getArray = static function (string $key): array {
        if (! isset($_GET[$key])) { return []; }
        $raw = is_array($_GET[$key]) ? $_GET[$key] : [$_GET[$key]];
        $out = array_values(array_unique(array_filter(array_map(static fn($v) => sanitize_text_field(wp_unslash((string) $v)), $raw))));
        return $out;
    };
    $parsed = [
        's' => $getString('s'),
        'helmet_type' => $getArray('helmet_type'),
        'certification' => $getArray('certification'),
        'feature' => $getArray('feature'),
        'size' => $getArray('size'),
        'price_range' => $getArray('price_range'),
        'region' => $getArray('region'),
        'use_case' => $getArray('use_case'),
        'brand_slug' => sanitize_title($getString('brand_slug')),
        'helmet_family' => $getString('helmet_family'),
        'price_min' => $getString('price_min'),
        'price_max' => $getString('price_max'),
        'sort' => $getString('sort') ?: 'newest',
        'paged' => max(1, (int) ($getString('paged') !== '' ? $getString('paged') : get_query_var('paged', 1))),
    ];
}
if (($parsed['sort'] ?? '') === '') {
    $parsed['sort'] = 'newest';
}
$selectedTypes    = is_array($parsed['helmet_type'] ?? null) ? $parsed['helmet_type'] : [];
$selectedCerts    = is_array($parsed['certification'] ?? null) ? $parsed['certification'] : [];
$selectedFeatures = is_array($parsed['feature'] ?? null) ? $parsed['feature'] : [];
$selectedSize     = is_array($parsed['size'] ?? null) ? $parsed['size'] : [];
$selectedPriceRanges = is_array($parsed['price_range'] ?? null) ? $parsed['price_range'] : [];
$selectedRegions    = is_array($parsed['region'] ?? null) ? $parsed['region'] : [];
$selectedUseCases   = is_array($parsed['use_case'] ?? null) ? $parsed['use_case'] : [];
$brandSlug          = sanitize_title((string) ($parsed['brand_slug'] ?? ''));
$helmetFamily    = (string) ($parsed['helmet_family'] ?? '');
$priceMin        = (string) ($parsed['price_min'] ?? '');
$priceMax        = (string) ($parsed['price_max'] ?? '');
$searchTerm      = (string) ($parsed['s'] ?? '');
$sort            = (string) ($parsed['sort'] ?? 'newest');

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
$priceRangeTerms = get_terms([
    'taxonomy' => 'price_range',
    'hide_empty' => true,
]);
$regionTerms = get_terms([
    'taxonomy' => 'region',
    'hide_empty' => true,
]);
$useCaseTerms = get_terms([
    'taxonomy' => 'use_case',
    'hide_empty' => true,
]);

$brandPosts = get_posts([
    'post_type' => 'brand',
    'post_status' => 'publish',
    'posts_per_page' => 250,
    'orderby' => 'title',
    'order' => 'ASC',
]);

$paged = max(1, (int) ($parsed['paged'] ?? get_query_var('paged', 1)));

if ($searchService !== null) {
    $args = $searchService->buildQueryArgs($parsed);
    $args['posts_per_page'] = 40;
    $args['paged'] = $paged;
} else {
    $args = [
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'posts_per_page' => 40,
        'paged' => $paged,
    ];
    if ($searchTerm !== '') {
        $args['s'] = $searchTerm;
    }
}

// Build current query args (for pagination base and redirects) before running query.
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

$query = new WP_Query($args);

$max_num_pages = (int) $query->max_num_pages;
if ($max_num_pages < 1) {
    $max_num_pages = 1;
}
// Redirect to last valid page if requested page is beyond results (clean URL, avoid empty page)
if ($paged > $max_num_pages && $max_num_pages > 0) {
    $redirect_args = $currentQuery;
    if ($max_num_pages > 1) {
        $redirect_args['paged'] = $max_num_pages;
    }
    wp_safe_redirect((string) add_query_arg($redirect_args, $archiveUrl), 302);
    exit;
}
$paged = max(1, min($paged, $max_num_pages));

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
foreach ($selectedPriceRanges as $slug) {
    $term = get_term_by('slug', $slug, 'price_range');
    $activeChips[] = ['label' => ($term instanceof WP_Term ? $term->name : $slug), 'url' => $removeFilterUrl('price_range', $slug)];
}
foreach ($selectedRegions as $slug) {
    $term = get_term_by('slug', $slug, 'region');
    $activeChips[] = ['label' => ($term instanceof WP_Term ? $term->name : $slug), 'url' => $removeFilterUrl('region', $slug)];
}
foreach ($selectedUseCases as $slug) {
    $term = get_term_by('slug', $slug, 'use_case');
    $activeChips[] = ['label' => ($term instanceof WP_Term ? $term->name : $slug), 'url' => $removeFilterUrl('use_case', $slug)];
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
        <div class="hs-archive-hero__content">
            <p class="hs-archive-hero__lead">Browse our catalog of motorcycle helmets: compare specs, certifications, and prices. Use the filters to narrow by helmet type (full face, modular, adventure), brand, safety standard (DOT, ECE, Snell, SHARP), and price range. Each helmet page includes technical analysis and where to buy.</p>
            <p class="hs-archive-hero__sub">Add helmets to the <a href="<?php echo esc_url(home_url('/comparison/')); ?>">comparison tool</a> to see them side by side, or jump to <a href="<?php echo esc_url(get_post_type_archive_link('brand')); ?>">brands</a> and <a href="<?php echo esc_url(home_url('/helmet-types/')); ?>">helmet types</a> for curated lists.</p>
        </div>
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
                    <summary>Price range</summary>
                    <div class="hs-filter-checks">
                        <?php if (is_array($priceRangeTerms)) : foreach ($priceRangeTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                            <label><input type="checkbox" name="price_range[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedPriceRanges, true)); ?> /> <?php echo esc_html($term->name); ?></label>
                        <?php endforeach; endif; ?>
                    </div>
                </details>
                <details class="hs-filter-group">
                    <summary>Price (min–max)</summary>
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
                    <summary>Region</summary>
                    <div class="hs-filter-checks hs-filter-scroll">
                        <?php if (is_array($regionTerms)) : foreach ($regionTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                            <label><input type="checkbox" name="region[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedRegions, true)); ?> /> <?php echo esc_html($term->name); ?></label>
                        <?php endforeach; endif; ?>
                    </div>
                </details>

                <details class="hs-filter-group">
                    <summary>Use case</summary>
                    <div class="hs-filter-checks hs-filter-scroll">
                        <?php if (is_array($useCaseTerms)) : foreach ($useCaseTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                            <label><input type="checkbox" name="use_case[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedUseCases, true)); ?> /> <?php echo esc_html($term->name); ?></label>
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

                <nav class="hs-pagination-wrap" aria-label="<?php esc_attr_e( 'Helmet catalog pages', 'helmetsan-theme' ); ?>">
                    <?php
                    // Explicit base so all filter params are preserved on every page link (query-string and pretty permalinks).
                    $pagination_base = (string) add_query_arg(array_merge($currentQuery, [ 'paged' => '%#%' ]), $archiveUrl);
                    echo wp_kses_post(paginate_links([
                        'base'      => $pagination_base,
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $max_num_pages,
                        'mid_size'  => 2,
                        'prev_text' => __( '&larr; Prev', 'helmetsan-theme' ),
                        'next_text' => __( 'Next &rarr;', 'helmetsan-theme' ),
                        'type'      => 'plain',
                    ]));
                    ?>
                </nav>
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
