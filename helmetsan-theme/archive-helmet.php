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
$selectedSharp      = is_array($parsed['sharp_rating'] ?? null) ? array_map('intval', $parsed['sharp_rating']) : [];
$selectedStrap      = is_array($parsed['strap_type'] ?? null) ? $parsed['strap_type'] : [];
$selectedComms      = is_array($parsed['comms_ready'] ?? null) ? $parsed['comms_ready'] : [];
$brandSlug          = sanitize_title((string) ($parsed['brand_slug'] ?? ''));
$helmetFamily    = (string) ($parsed['helmet_family'] ?? '');
$priceMin        = (string) ($parsed['price_min'] ?? '');
$priceMax        = (string) ($parsed['price_max'] ?? '');
$searchTerm      = (string) ($parsed['s'] ?? '');
$sort            = (string) ($parsed['sort'] ?? 'newest');

if (class_exists(\Helmetsan\Core\Cache\ObjectCacheService::class)) {
    $helmetTypeTerms = \Helmetsan\Core\Cache\ObjectCacheService::getTaxonomyTerms('helmet_type');
    $certTerms       = \Helmetsan\Core\Cache\ObjectCacheService::getTaxonomyTerms('certification');
    $featureTerms    = \Helmetsan\Core\Cache\ObjectCacheService::getTaxonomyTerms('feature_tag');
    $priceRangeTerms = \Helmetsan\Core\Cache\ObjectCacheService::getTaxonomyTerms('price_range');
    $regionTerms     = \Helmetsan\Core\Cache\ObjectCacheService::getTaxonomyTerms('region');
    $useCaseTerms    = \Helmetsan\Core\Cache\ObjectCacheService::getTaxonomyTerms('use_case');
    $brandPosts      = \Helmetsan\Core\Cache\ObjectCacheService::getBrandList();
} else {
    $helmetTypeTerms = get_terms(['taxonomy' => 'helmet_type', 'hide_empty' => true]);
    $certTerms       = get_terms(['taxonomy' => 'certification', 'hide_empty' => true]);
    $featureTerms    = get_terms(['taxonomy' => 'feature_tag', 'hide_empty' => true]);
    $priceRangeTerms = get_terms(['taxonomy' => 'price_range', 'hide_empty' => true]);
    $regionTerms     = get_terms(['taxonomy' => 'region', 'hide_empty' => true]);
    $useCaseTerms    = get_terms(['taxonomy' => 'use_case', 'hide_empty' => true]);
    $brandPosts      = get_posts([
        'post_type'      => 'brand',
        'post_status'    => 'publish',
        'posts_per_page' => 250,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
}

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
        'post_parent' => 0,
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

$query = $GLOBALS['wp_query'];

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
foreach ($selectedSharp as $rating) {
    $activeChips[] = ['label' => $rating . ' Star Safety', 'url' => $removeFilterUrl('sharp_rating', (string)$rating)];
}
foreach ($selectedStrap as $strap) {
    $activeChips[] = ['label' => $strap, 'url' => $removeFilterUrl('strap_type', $strap)];
}
foreach ($selectedComms as $comms) {
    $activeChips[] = ['label' => 'Comms: ' . $comms, 'url' => $removeFilterUrl('comms_ready', $comms)];
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
<section class="hs-section hs-section--archive hs-section--archive-wide hs-reveal">
    <!-- §1 COMPACT PUNCHY HEADER & SEARCH -->
    <header class="hs-archive-hero--v3">
        <div class="hs-archive-hero__header-text">
            <h1 class="hs-archive-hero__v3-title">Motorcycle Helmets</h1>
            <p class="hs-archive-hero__v3-subtitle"><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?>+ helmets · 100+ brands · compare safety, fit and price</p>
        </div>

        <div class="hs-smart-search-wrap">
            <form role="search" method="get" class="hs-smart-search-form" action="<?php echo esc_url($archiveUrl); ?>">
                <?php foreach ($_GET as $gk => $gv) : if ($gk === 's' || $gk === 'paged') { continue; } if (is_array($gv)) { foreach ($gv as $gitem) { echo '<input type="hidden" name="' . esc_attr($gk) . '[]" value="' . esc_attr($gitem) . '" />'; } } else { echo '<input type="hidden" name="' . esc_attr($gk) . '" value="' . esc_attr($gv) . '" />'; } endforeach; ?>
                <div class="hs-smart-search-input-wrap">
                    <svg class="hs-smart-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" id="hs-smart-search-input" name="s" class="hs-smart-search-input" value="<?php echo esc_attr($searchTerm); ?>" placeholder="Search helmets, brands, motorcycles or requirements..." autocomplete="off" aria-label="Search helmets catalog" />
                    <button type="submit" class="hs-smart-search-btn">Search</button>
                </div>
            </form>

            <div class="hs-smart-search-prompts">
                <span class="hs-prompts-label">Popular:</span>
                <a href="<?php echo esc_url(add_query_arg(['certification' => ['ece-22-06']], $archiveUrl)); ?>">ECE 22.06</a> ·
                <a href="<?php echo esc_url(add_query_arg(['helmet_type' => ['full-face']], $archiveUrl)); ?>">Full Face</a> ·
                <a href="<?php echo esc_url(add_query_arg(['helmet_type' => ['adventure-dual-sport']], $archiveUrl)); ?>">Adventure</a> ·
                <a href="<?php echo esc_url(add_query_arg(['brand_slug' => 'arai'], $archiveUrl)); ?>">Arai</a> ·
                <a href="<?php echo esc_url(add_query_arg(['price_max' => '500'], $archiveUrl)); ?>">Under $500</a>
            </div>
        </div>
    </header>

    <!-- §2 COMPACT HORIZONTAL FINDER ("FIND YOUR HELMET") -->
    <div class="hs-horizontal-finder">
        <div class="hs-horizontal-finder__head">
            <h3 class="hs-horizontal-finder__title">FIND YOUR HELMET</h3>
            <span class="hs-horizontal-finder__sub">Tell us what you're looking for</span>
        </div>
        <form method="get" action="<?php echo esc_url($archiveUrl); ?>" class="hs-horizontal-finder__form">
            <div class="hs-horizontal-field">
                <label for="hs-hfield-moto">Motorcycle</label>
                <input type="text" id="hs-hfield-moto" name="s" placeholder="e.g. Himalayan 450" value="<?php echo esc_attr($searchTerm); ?>" />
            </div>
            <div class="hs-horizontal-field">
                <label for="hs-hfield-riding">Riding style</label>
                <select id="hs-hfield-riding" name="use_case[]">
                    <option value="">Any Riding Style</option>
                    <option value="highway" <?php selected(in_array('highway', $selectedUseCases, true)); ?>>Highway / Touring</option>
                    <option value="commuter" <?php selected(in_array('commuter', $selectedUseCases, true)); ?>>City Commuter</option>
                    <option value="track" <?php selected(in_array('track', $selectedUseCases, true)); ?>>Track / Racing</option>
                    <option value="adventure" <?php selected(in_array('adventure', $selectedUseCases, true)); ?>>Adventure / Off-Road</option>
                </select>
            </div>
            <div class="hs-horizontal-field">
                <label for="hs-hfield-budget">Budget</label>
                <select id="hs-hfield-budget" name="price_range[]">
                    <option value="">Any Budget</option>
                    <option value="under-300">Under $300</option>
                    <option value="300-600">$300 – $600</option>
                    <option value="over-600">$600+</option>
                </select>
            </div>
            <div class="hs-horizontal-field">
                <label for="hs-hfield-safety">Safety</label>
                <select id="hs-hfield-safety" name="certification[]">
                    <option value="">Any Safety</option>
                    <option value="ece-22-06" <?php selected(in_array('ece-22-06', $selectedCerts, true) || empty($selectedCerts)); ?>>ECE 22.06</option>
                    <option value="dot" <?php selected(in_array('dot', $selectedCerts, true)); ?>>DOT</option>
                    <option value="snell" <?php selected(in_array('snell', $selectedCerts, true)); ?>>Snell M2020</option>
                </select>
            </div>
            <button type="submit" class="hs-btn hs-btn--primary hs-horizontal-submit">Show Matching Helmets →</button>
        </form>
    </div>

    <!-- §3 SEGMENTED BROWSE CONTROL -->
    <div class="hs-segmented-bar">
        <div class="hs-segmented-control">
            <button type="button" class="hs-seg-btn is-active" data-mode="recommended">🧠 Best Matches</button>
            <button type="button" class="hs-seg-btn" data-mode="browse">All Helmets</button>
        </div>
    </div>

    <!-- §4 RESULTS CONTEXT BAR -->
    <div class="hs-results-context-bar">
        <div class="hs-results-context-count">
            <strong><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?> helmets match your requirements</strong>
        </div>
        <?php if (!empty($activeChips) || $searchTerm !== '') : ?>
            <div class="hs-results-context-chips">
                <?php if ($searchTerm !== '') : ?>
                    <a href="<?php echo esc_url(remove_query_arg('s', $archiveUrl)); ?>" class="hs-context-chip"><?php echo esc_html($searchTerm); ?> ×</a>
                <?php endif; ?>
                <?php foreach ($activeChips as $chip) : ?>
                    <a href="<?php echo esc_url((string) $chip['url']); ?>" class="hs-context-chip"><?php echo esc_html((string) $chip['label']); ?> ×</a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url($archiveUrl); ?>" class="hs-context-clear-all">Clear all</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- §5 CATALOG WORKSPACE GRID -->
    <div class="hs-catalog hs-catalog--wide">
        <!-- DECISION FILTER SIDEBAR (270px STICKY) -->
        <aside id="hsFilterPanel" class="hs-catalog__filters hs-catalog__filters--sticky hs-panel" aria-label="Helmet filters">
            <div class="hs-catalog__filters-head">
                <div class="hs-filter-head-left">
                    <strong>Filter Helmets</strong>
                    <span class="hs-filter-head-count">12 filters available</span>
                </div>
            </div>

            <form id="hsHelmetFilterForm" method="get" action="<?php echo esc_url($archiveUrl); ?>">
                <!-- GROUP 1: QUICK FILTERS -->
                <details class="hs-filter-group" open>
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Quick filters</span>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Quick Filters</legend>
                        <div class="hs-filter-checks">
                            <label class="hs-filter-check-label"><input type="checkbox" name="certification[]" value="ece-22-06" <?php checked(in_array('ece-22-06', $selectedCerts, true)); ?> /><span class="hs-filter-check-text">ECE 22.06</span></label>
                            <label class="hs-filter-check-label"><input type="checkbox" name="helmet_type[]" value="full-face" <?php checked(in_array('full-face', $selectedTypes, true)); ?> /><span class="hs-filter-check-text">Full Face</span></label>
                            <label class="hs-filter-check-label"><input type="checkbox" name="helmet_type[]" value="adventure-dual-sport" <?php checked(in_array('adventure-dual-sport', $selectedTypes, true)); ?> /><span class="hs-filter-check-text">Adventure</span></label>
                            <label class="hs-filter-check-label"><input type="checkbox" name="price_max" value="500" <?php checked($priceMax, '500'); ?> /><span class="hs-filter-check-text">Under $500</span></label>
                            <label class="hs-filter-check-label"><input type="checkbox" name="sharp_rating[]" value="5" <?php checked(in_array(5, $selectedSharp, true)); ?> /><span class="hs-filter-check-text">Top Rated</span></label>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 2: HELMET TYPE -->
                <details class="hs-filter-group" open>
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Helmet Type</span>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Helmet Type</legend>
                        <div class="hs-filter-checks">
                            <?php if (is_array($helmetTypeTerms)) : foreach ($helmetTypeTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                                <label class="hs-filter-check-label"><input type="checkbox" name="helmet_type[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedTypes, true)); ?> /><span class="hs-filter-check-text"><?php echo esc_html($term->name); ?></span></label>
                            <?php endforeach; endif; ?>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 3: SAFETY -->
                <details class="hs-filter-group" open>
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Safety</span>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Safety Standards</legend>
                        <div class="hs-filter-checks">
                            <?php if (is_array($certTerms)) : foreach ($certTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                                <label class="hs-filter-check-label"><input type="checkbox" name="certification[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedCerts, true)); ?> /><span class="hs-filter-check-text"><?php echo esc_html($term->name); ?></span></label>
                            <?php endforeach; endif; ?>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 4: RIDING -->
                <details class="hs-filter-group" open>
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Riding</span>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Riding Style</legend>
                        <div class="hs-filter-checks hs-filter-scroll">
                            <?php if (is_array($useCaseTerms)) : foreach ($useCaseTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                                <label class="hs-filter-check-label"><input type="checkbox" name="use_case[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedUseCases, true)); ?> /><span class="hs-filter-check-text"><?php echo esc_html($term->name); ?></span></label>
                            <?php endforeach; endif; ?>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 5: PRICE -->
                <details class="hs-filter-group" open>
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Price</span>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Price</legend>
                        <div class="hs-filter-checks">
                            <?php if (is_array($priceRangeTerms)) : foreach ($priceRangeTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                                <label class="hs-filter-check-label"><input type="checkbox" name="price_range[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedPriceRanges, true)); ?> /><span class="hs-filter-check-text"><?php echo esc_html($term->name); ?></span></label>
                            <?php endforeach; endif; ?>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 6: BRAND -->
                <details class="hs-filter-group">
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Brand</span>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Brand</legend>
                        <input type="text" id="hs-brand-filter-search" class="hs-filter-brand-search" placeholder="Search brands..." aria-label="Filter brands" />
                        <div class="hs-filter-checks hs-filter-scroll" id="hs-brand-filter-list">
                            <label class="hs-filter-check-label"><input type="radio" name="brand_slug" value="" <?php checked($brandSlug, ''); ?> /><span class="hs-filter-check-text">All Brands</span></label>
                            <?php foreach ($brandPosts as $brand) : if (! ($brand instanceof WP_Post)) { continue; } $slug = sanitize_title($brand->post_name); ?>
                                <label class="hs-filter-check-label" data-brand-name="<?php echo esc_attr(strtolower($brand->post_title)); ?>"><input type="radio" name="brand_slug" value="<?php echo esc_attr($slug); ?>" <?php checked($brandSlug, $slug); ?> /><span class="hs-filter-check-text"><?php echo esc_html($brand->post_title); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 7: FEATURES -->
                <details class="hs-filter-group">
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Features</span>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Features</legend>
                        <div class="hs-filter-checks hs-filter-scroll">
                            <?php if (is_array($featureTerms)) : foreach ($featureTerms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                                <label class="hs-filter-check-label"><input type="checkbox" name="feature[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selectedFeatures, true)); ?> /><span class="hs-filter-check-text"><?php echo esc_html($term->name); ?></span></label>
                            <?php endforeach; endif; ?>
                        </div>
                    </fieldset>
                </details>

                <div class="hs-filter-actions">
                    <a class="hs-btn hs-btn--outline" href="<?php echo esc_url($archiveUrl); ?>">Clear All</a>
                    <button class="hs-btn hs-btn--primary" type="submit">Apply Filters</button>
                </div>
            </form>
        </aside>

        <!-- RESULTS CONTENT AREA -->
        <div class="hs-catalog__results">
            <!-- CATALOG TOOLBAR -->
            <div class="hs-catalog__topbar hs-panel">
                <div class="hs-catalog__topbar-left">
                    <span class="hs-catalog__count-bold"><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?> helmets</span>
                    <a href="<?php echo esc_url(home_url('/#hs-home-intent')); ?>" class="hs-btn hs-btn--sm hs-btn--outline hs-help-me-choose-btn">
                        🧠 Help Me Choose
                    </a>
                </div>

                <div class="hs-catalog__topbar-right">
                    <div class="hs-catalog__sort">
                        <label for="hs-catalog-sort" class="hs-sort-label">Sort by:</label>
                        <select id="hs-catalog-sort" name="sort" form="hsHelmetFilterForm" aria-label="Sort helmets" onchange="if(this.form){this.form.submit();}else{var u=new URL(window.location.href);u.searchParams.set('sort',this.value);u.searchParams.delete('paged');window.location.href=u.toString();}">
                            <option value="relevance" <?php selected($sort, 'relevance'); ?>>Relevance</option>
                            <option value="rating" <?php selected($sort, 'rating'); ?>>Helmetsan Score</option>
                            <option value="price_low" <?php selected($sort, 'price_low'); ?>>Price: Low → High</option>
                            <option value="price_high" <?php selected($sort, 'price_high'); ?>>Price: High → Low</option>
                            <option value="weight" <?php selected($sort, 'weight'); ?>>Weight: Lightest</option>
                            <option value="newest" <?php selected($sort, 'newest'); ?>>Newest</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- PRODUCT GRID / 4 COLS DESKTOP -->
            <?php if ($query->have_posts()) : ?>
                <section class="hs-catalog__results-content">

                    <!-- PARTITION 1: BEST MATCHES FOR YOU (FIRST 4-6 CARDS) -->
                    <div class="hs-results-partition" id="partition-best-matches">
                        <div class="hs-partition-head">
                            <h3 class="hs-results-partition-title">Best matches for you</h3>
                            <p class="hs-results-partition-sub">Based on your motorcycle, riding style, budget and safety requirements.</p>
                        </div>
                        <div class="hs-catalog-grid hs-catalog-grid--4cols" id="helmet-results-recommended">
                            <?php
                            $cardCount = 0;
                            while ($query->have_posts() && $cardCount < 4) : $query->the_post();
                                $cardCount++;
                                get_template_part('template-parts/helmet-card');
                            endwhile;
                            ?>
                        </div>
                    </div>

                    <!-- PARTITION 2: ALL MATCHING HELMETS (REMAINING CARDS) -->
                    <?php if ($query->have_posts()) : ?>
                        <div class="hs-results-partition" id="partition-all-helmets" style="margin-top: 3rem;">
                            <h3 class="hs-results-partition-title">All matching helmets</h3>
                            <div class="hs-catalog-grid hs-catalog-grid--4cols" id="helmet-results-remaining">
                                <?php
                                while ($query->have_posts()) : $query->the_post();
                                    get_template_part('template-parts/helmet-card');
                                endwhile;
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="hs-pagination-footer">
                        <?php
                        $ppp = $query->get('posts_per_page');
                        $start = (($paged - 1) * $ppp) + 1;
                        $end = min($paged * $ppp, $query->found_posts);
                        $count_text = sprintf(__('Showing %d–%d of %d', 'helmetsan-theme'), $start, $end, $query->found_posts);

                        get_template_part('template-parts/pagination-modern', null, [
                            'paged' => $paged,
                            'total' => $max_num_pages,
                            'count_text' => $count_text
                        ]);
                        ?>
                    </div>
                </section>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <!-- INTELLIGENT EMPTY STATE & SMART RECOVERY V2 -->
                <div class="hs-catalog-empty-v2" data-empty-state="true">
                    <div class="hs-catalog-empty-v2__icon">🔍</div>
                    <h3 class="hs-catalog-empty-v2__title"><?php esc_html_e('No exact helmet matches found', 'helmetsan-theme'); ?></h3>
                    <p class="hs-catalog-empty-v2__desc"><?php esc_html_e('We couldn\'t find a helmet matching all your active filters. Try relaxing one or more parameters or explore our top-rated recommendations below.', 'helmetsan-theme'); ?></p>

                    <div class="hs-catalog-empty-v2__suggestions">
                        <a href="<?php echo esc_url($archiveUrl); ?>" class="hs-catalog-empty-v2__reset-btn">
                            <?php esc_html_e('Clear All Filters →', 'helmetsan-theme'); ?>
                        </a>
                    </div>

                    <?php
                    // Smart Recovery fallback recommendations
                    $fallbackArgs = [
                        'post_type'      => 'helmet',
                        'post_status'    => 'publish',
                        'post_parent'    => 0,
                        'posts_per_page' => 4,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                    ];

                    // If a brand was filtered, prioritize helmets from that brand
                    if ($brandSlug !== '') {
                        $fallbackBrand = get_page_by_path($brandSlug, OBJECT, 'brand');
                        if ($fallbackBrand instanceof WP_Post) {
                            $fallbackArgs['meta_query'] = [
                                [
                                    'key'     => 'brand',
                                    'value'   => $fallbackBrand->ID,
                                    'compare' => '=',
                                ],
                            ];
                        }
                    } elseif (!empty($selectedTypes)) {
                        $fallbackArgs['tax_query'] = [
                            [
                                'taxonomy' => 'helmet_type',
                                'field'    => 'slug',
                                'terms'    => $selectedTypes,
                            ],
                        ];
                    }

                    $recoveryCacheKey = 'recovery_ids_' . md5($brandSlug . '_' . implode('-', $selectedTypes));
                    $fallbackPostIds = class_exists(\Helmetsan\Core\Cache\ObjectCacheService::class)
                        ? \Helmetsan\Core\Cache\ObjectCacheService::remember($recoveryCacheKey, \Helmetsan\Core\Cache\ObjectCacheService::GROUP_SEARCH, static function () use ($fallbackArgs, $brandSlug, $selectedTypes): array {
                            $q = new WP_Query(array_merge($fallbackArgs, ['fields' => 'ids']));
                            if (! $q->have_posts() && ($brandSlug !== '' || !empty($selectedTypes))) {
                                $q = new WP_Query([
                                    'post_type'      => 'helmet',
                                    'post_status'    => 'publish',
                                    'post_parent'    => 0,
                                    'posts_per_page' => 4,
                                    'orderby'        => 'date',
                                    'order'          => 'DESC',
                                    'fields'         => 'ids',
                                ]);
                            }
                            return is_array($q->posts) ? array_map('intval', $q->posts) : [];
                        }, 3600)
                        : null;

                    if (is_array($fallbackPostIds) && !empty($fallbackPostIds)) {
                        $fallbackQuery = new WP_Query([
                            'post_type'      => 'helmet',
                            'post__in'       => $fallbackPostIds,
                            'orderby'        => 'post__in',
                            'posts_per_page' => count($fallbackPostIds),
                        ]);
                    } else {
                        $fallbackQuery = new WP_Query($fallbackArgs);
                        if (! $fallbackQuery->have_posts() && ($brandSlug !== '' || !empty($selectedTypes))) {
                            $fallbackQuery = new WP_Query([
                                'post_type'      => 'helmet',
                                'post_status'    => 'publish',
                                'post_parent'    => 0,
                                'posts_per_page' => 4,
                                'orderby'        => 'date',
                                'order'          => 'DESC',
                            ]);
                        }
                    }

                    if ($fallbackQuery->have_posts()) :
                    ?>
                        <div class="hs-catalog-empty-v2__recovery" style="margin-top: 2.5rem; text-align: left;">
                            <div class="hs-partition-head" style="margin-bottom: 1rem;">
                                <h4 style="font-size: 1.15rem; font-weight: 800; color: var(--hs-text); margin: 0 0 0.25rem;">
                                    <?php esc_html_e('Popular Alternative Helmets', 'helmetsan-theme'); ?>
                                </h4>
                                <p style="font-size: 0.875rem; color: var(--hs-muted); margin: 0;">
                                    <?php esc_html_e('Riders frequently choose these verified models:', 'helmetsan-theme'); ?>
                                </p>
                            </div>
                            <div class="hs-catalog-grid hs-catalog-grid--4cols" id="helmet-results-recovery">
                                <?php
                                while ($fallbackQuery->have_posts()) : $fallbackQuery->the_post();
                                    get_template_part('template-parts/helmet-card');
                                endwhile;
                                wp_reset_postdata();
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- §6 FOOTER SEO & NAVIGATION GRID (§18) -->
    <section class="hs-archive-seo-v3">
        <h2 class="hs-archive-seo-v3__title">About the Helmetsan Helmet Database</h2>
        <div class="hs-archive-seo-v3__desc-grid">
            <div>
                <p>Helmetsan tracks verified helmet safety data across 3,100+ certified motorcycle helmets from 100+ global manufacturers. Each helmet record aggregates official safety certifications (ECE 22.06, DOT FMVSS 218, Snell M2020, FIM FRHPhe), SHARP impact ratings, shell weights, visor compatibility, and comms readiness.</p>
            </div>
            <div>
                <p>Use our decision engine workspace to compare safety ratings, riding styles, price brackets, and shell materials. Select helmets to compare side-by-side or launch our guided finder to find gear engineered for your motorcycle.</p>
            </div>
        </div>

        <div class="hs-archive-seo-v3__nav-grid">
            <div class="hs-seo-nav-col">
                <h4>Safety Standards</h4>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/certification/ece-22-06/')); ?>">ECE 22.06 Certified →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/certification/dot/')); ?>">DOT FMVSS 218 →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/certification/snell/')); ?>">Snell M2020 →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/certification/fim/')); ?>">FIM Racing Certified →</a></li>
                </ul>
            </div>
            <div class="hs-seo-nav-col">
                <h4>Helmet Types</h4>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/helmet_type/full-face/')); ?>">Full Face Helmets →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/helmet_type/modular/')); ?>">Modular / Flip-up →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/helmet_type/adventure-dual-sport/')); ?>">Adventure / Dual-Sport →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/helmet_type/open-face/')); ?>">Open Face / 3/4 →</a></li>
                </ul>
            </div>
            <div class="hs-seo-nav-col">
                <h4>Top Brands</h4>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/brand/arai/')); ?>">Arai Helmets →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/brand/shoei/')); ?>">Shoei Helmets →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/brand/agv/')); ?>">AGV Helmets →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/brand/hjc/')); ?>">HJC Helmets →</a></li>
                </ul>
            </div>
            <div class="hs-seo-nav-col">
                <h4>Buying Guides</h4>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/buying-guide/')); ?>">Helmet Fit &amp; Sizing Guide →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/safety-standards/')); ?>">ECE 22.05 vs 22.06 Explained →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/comparison/')); ?>">Full Helmet Comparison Matrix →</a></li>
                    <li><a href="<?php echo esc_url(home_url('/accessories/')); ?>">Visors &amp; Comms Accessories →</a></li>
                </ul>
            </div>
        </div>
    </section>
</section>

<!-- STICKY COMPARISON TRAY -->
<div id="hsStickyCompareBar" class="hs-sticky-compare-bar is-hidden">
    <div class="hs-sticky-compare-inner">
        <div class="hs-sticky-compare-info">
            <span id="hsStickyCompareCount" class="hs-sticky-compare-count">0</span> helmets selected:
            <span id="hsStickyCompareNames" class="hs-sticky-compare-names"></span>
        </div>
        <a href="<?php echo esc_url(home_url('/comparison/')); ?>" class="hs-btn hs-btn--primary hs-sticky-compare-btn">
            Compare Now →
        </a>
    </div>
</div>


<div class="hs-mobile-tools" aria-label="Mobile catalog tools">
    <button type="button" data-open-filter aria-controls="hsFilterPanel" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M22 3H2l8 9v7l4 3v-10L22 3z"/></svg>
        <span>Filter</span>
    </button>
    <button type="button" data-open-sort aria-label="Sort options">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M11 5L6 9l5 4M13 19l5-4-5-4M6 9h12M18 15H6"/></svg>
        <span>Sort</span>
    </button>
    <button type="button" data-open-size aria-label="Quick size selection">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="7" y1="7" x2="17" y2="7"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="7" y1="17" x2="12" y2="17"/></svg>
        <span>Size</span>
    </button>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Smart Search Focus Dropdown
    var searchInput = document.getElementById('hs-smart-search-input');
    var searchDropdown = document.getElementById('hs-smart-search-dropdown');
    if (searchInput && searchDropdown) {
        searchInput.addEventListener('focus', function() {
            searchDropdown.classList.remove('is-hidden');
        });
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.classList.add('is-hidden');
            }
        });
    }

    // 2. Brand Filter Inline Search
    var brandSearch = document.getElementById('hs-brand-filter-search');
    var brandList = document.getElementById('hs-brand-filter-list');
    if (brandSearch && brandList) {
        brandSearch.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var labels = brandList.querySelectorAll('label[data-brand-name]');
            labels.forEach(function(lbl) {
                var bName = lbl.getAttribute('data-brand-name') || '';
                if (q === '' || bName.indexOf(q) !== -1) {
                    lbl.style.display = 'flex';
                } else {
                    lbl.style.display = 'none';
                }
            });
        });
    }

    // 3. Browse Mode Switcher
    var modeBtns = document.querySelectorAll('.hs-mode-btn');
    var recPartition = document.getElementById('helmet-results-recommended');
    var remPartition = document.getElementById('helmet-results-remaining');
    modeBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            modeBtns.forEach(function(b) { b.classList.remove('is-active'); });
            this.classList.add('is-active');
            var mode = this.getAttribute('data-mode');
            if (mode === 'browse') {
                if (recPartition) recPartition.closest('.hs-results-partition').style.display = 'none';
                if (remPartition) remPartition.closest('.hs-results-partition').style.marginTop = '0';
            } else {
                if (recPartition) recPartition.closest('.hs-results-partition').style.display = 'block';
                if (remPartition) remPartition.closest('.hs-results-partition').style.marginTop = '2.5rem';
            }
        });
    });

    // 3b. Mobile Sort Button Trigger
    var sortBtn = document.querySelector('[data-open-sort]');
    var sortSelect = document.getElementById('hs-catalog-sort');
    if (sortBtn && sortSelect) {
        sortBtn.addEventListener('click', function() {
            sortSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
            sortSelect.focus();
            try { if (typeof sortSelect.showPicker === 'function') sortSelect.showPicker(); } catch(e) {}
        });
    }

    // 4. Sticky Compare Tray
    var compareBar = document.getElementById('hsStickyCompareBar');
    var compareCountEl = document.getElementById('hsStickyCompareCount');
    var compareNamesEl = document.getElementById('hsStickyCompareNames');
    var selectedHelmets = [];

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.js-add-to-compare');
        if (!btn) return;

        var id = btn.getAttribute('data-id');
        var title = btn.getAttribute('data-title') || 'Helmet #' + id;

        var idx = selectedHelmets.findIndex(function(h) { return h.id === id; });
        if (idx !== -1) {
            selectedHelmets.splice(idx, 1);
            btn.classList.remove('is-selected');
            btn.setAttribute('aria-pressed', 'false');
        } else {
            selectedHelmets.push({ id: id, title: title });
            btn.classList.add('is-selected');
            btn.setAttribute('aria-pressed', 'true');
        }

        if (compareBar && compareCountEl && compareNamesEl) {
            if (selectedHelmets.length > 0) {
                compareBar.classList.remove('is-hidden');
                compareCountEl.textContent = selectedHelmets.length;
                compareNamesEl.textContent = selectedHelmets.map(function(h) { return h.title; }).join(' · ');
            } else {
                compareBar.classList.add('is-hidden');
            }
        }
    });
});
</script>
<?php
get_footer();

