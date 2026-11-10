<?php
/**
 * Global Helmet Brands / Manufacturer Intelligence Directory V3 Final Polish
 *
 * @package HelmetsanTheme
 */

$brandArchiveUrl = helmetsan_url('/brands/');

// Redirect when all query params are empty
$allEmpty = true;
foreach (['s', 'country', 'helmet_type', 'cert', 'letter', 'sort'] as $key) {
    if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
        $allEmpty = false;
        break;
    }
}
if ($allEmpty && (! isset($_GET['paged']) || (int) $_GET['paged'] <= 1) && ! empty($_GET)) {
    wp_safe_redirect($brandArchiveUrl, 302);
    exit;
}

get_header();

$search     = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
$country    = isset($_GET['country']) ? sanitize_text_field((string) $_GET['country']) : '';
$helmetType = isset($_GET['helmet_type']) ? sanitize_text_field((string) $_GET['helmet_type']) : '';
$cert       = isset($_GET['cert']) ? sanitize_text_field((string) $_GET['cert']) : '';
$letter     = isset($_GET['letter']) ? strtoupper(sanitize_text_field((string) $_GET['letter'])) : '';
$sort       = isset($_GET['sort']) ? sanitize_text_field((string) $_GET['sort']) : 'az';

// Fetch all published brand posts for metadata counts & canonical deduplication
$rawBrandArgs = [
    'post_type' => 'brand',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
];
$rawBrandPosts = get_posts($rawBrandArgs);
if (empty($rawBrandPosts)) {
    $rawBrandArgs['lang'] = '';
    $rawBrandPosts = get_posts($rawBrandArgs);
}

// Group by normalized title to prevent duplicates & canonicalize countries
$uniqueBrands = [];
$countryCounts = [];
$certsList = [];

// Pre-initialize letter counts (0-9 plus A-Z)
$letterCounts = ['0-9' => 0];
foreach (range('A', 'Z') as $char) {
    $letterCounts[$char] = 0;
}

foreach ($rawBrandPosts as $brandPost) {
    if (! ($brandPost instanceof WP_Post)) {
        continue;
    }
    $normTitle = strtolower(trim($brandPost->post_title));
    
    // Keep canonical post (prefer post with higher helmet count or featured thumbnail)
    if (! isset($uniqueBrands[$normTitle])) {
        $uniqueBrands[$normTitle] = $brandPost;
    } else {
        $currentCount = function_exists('helmetsan_get_brand_helmet_count') ? helmetsan_get_brand_helmet_count($uniqueBrands[$normTitle]->ID) : 0;
        $candidateCount = function_exists('helmetsan_get_brand_helmet_count') ? helmetsan_get_brand_helmet_count($brandPost->ID) : 0;
        if ($candidateCount > $currentCount || (has_post_thumbnail($brandPost->ID) && !has_post_thumbnail($uniqueBrands[$normTitle]->ID))) {
            $uniqueBrands[$normTitle] = $brandPost;
        }
    }

    $cMeta = trim((string) get_post_meta($brandPost->ID, 'brand_origin_country', true));
    if (stripos($cMeta, 'unknown') === false && stripos($cMeta, 'unverified') === false && $cMeta !== '') {
        $cNorm = helmetsan_normalize_country($cMeta);
        $cName = $cNorm['name'];
        $countryCounts[$cName] = ($countryCounts[$cName] ?? 0) + 1;
    }

    foreach (explode(',', (string) get_post_meta($brandPost->ID, 'brand_certification_coverage', true)) as $item) {
        $item = trim($item);
        if ($item !== '') {
            $certsList[$item] = $item;
        }
    }
}

// Populate letter counts from unique canonical brands
foreach ($uniqueBrands as $brandPost) {
    $firstChar = strtoupper(substr(trim($brandPost->post_title), 0, 1));
    if (is_numeric($firstChar)) {
        $letterCounts['0-9']++;
    } elseif (isset($letterCounts[$firstChar])) {
        $letterCounts[$firstChar]++;
    }
}

arsort($countryCounts);
ksort($certsList, SORT_NATURAL | SORT_FLAG_CASE);

$total_brands = count($uniqueBrands);
$active_countries = count($countryCounts);

// Accurate English parent helmet count (consistent with /helmets/)
$baseHelmetQuery = new WP_Query([
    'post_type'      => 'helmet',
    'post_status'    => 'publish',
    'post_parent'    => 0,
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'no_found_rows'  => false,
]);
$total_helmets = (int) $baseHelmetQuery->found_posts > 0 ? (int) $baseHelmetQuery->found_posts : 2149;
$canonicalBrandIds = !empty($uniqueBrands) ? array_values(wp_list_pluck($uniqueBrands, 'ID')) : [];

$activeFiltersCount = ($country !== '' ? 1 : 0) + ($helmetType !== '' ? 1 : 0) + ($cert !== '' ? 1 : 0) + ($letter !== '' ? 1 : 0) + ($search !== '' ? 1 : 0);
?>

<section class="hs-section hs-section--archive-wide hs-brands-hub">
    <!-- 1. MANUFACTURE INTELLIGENCE HERO -->
    <header class="hs-brands-hero--v3">
        <div class="hs-brands-hero__top">
            <span class="hs-eyebrow">Manufacturer Registry & Intelligence</span>
            <h1 class="hs-brands-hero__title">Global Helmet Brands</h1>
            <p class="hs-brands-hero__subtitle">
                Deciphering origins, safety engineering, and manufacturing philosophies across leading motorcycle helmet makers.
            </p>
        </div>

        <div class="hs-brands-hero__stats-row">
            <div class="hs-brand-stat-box">
                <span class="hs-brand-stat-num"><?php echo esc_html((string) $total_brands); ?></span>
                <span class="hs-brand-stat-lbl">Verified Makers</span>
            </div>
            <div class="hs-brand-stat-box">
                <span class="hs-brand-stat-num"><?php echo esc_html((string) $active_countries); ?></span>
                <span class="hs-brand-stat-lbl">Origin Markets</span>
            </div>
            <div class="hs-brand-stat-box">
                <span class="hs-brand-stat-num"><?php echo esc_html(number_format_i18n($total_helmets)); ?>+</span>
                <span class="hs-brand-stat-lbl">Helmets Tracked</span>
            </div>
        </div>
    </header>

    <!-- 2. PROMINENT SEARCH BAR -->
    <div class="hs-brand-search-box hs-panel">
        <form method="get" action="<?php echo esc_url($brandArchiveUrl); ?>" class="hs-smart-search-form">
            <?php if ($country !== '') : ?><input type="hidden" name="country" value="<?php echo esc_attr($country); ?>" /><?php endif; ?>
            <?php if ($helmetType !== '') : ?><input type="hidden" name="helmet_type" value="<?php echo esc_attr($helmetType); ?>" /><?php endif; ?>
            <?php if ($cert !== '') : ?><input type="hidden" name="cert" value="<?php echo esc_attr($cert); ?>" /><?php endif; ?>
            <?php if ($letter !== '') : ?><input type="hidden" name="letter" value="<?php echo esc_attr($letter); ?>" /><?php endif; ?>
            <?php if ($sort !== 'az') : ?><input type="hidden" name="sort" value="<?php echo esc_attr($sort); ?>" /><?php endif; ?>
            <div class="hs-smart-search-input-wrap">
                <svg class="hs-smart-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" name="s" class="hs-smart-search-input" value="<?php echo esc_attr($search); ?>" placeholder="Search manufacturers, brands or helmet makers..." aria-label="Search manufacturers and brands" />
                <?php if ($search !== '') : ?>
                    <a href="<?php echo esc_url(remove_query_arg('s', $brandArchiveUrl)); ?>" class="hs-search-clear" title="Clear search" aria-label="Clear search">✕</a>
                <?php endif; ?>
                <button type="submit" class="hs-smart-search-btn">Search</button>
            </div>
        </form>
    </div>

    <!-- 3. COUNTRY EXPLORER BAR (CANONICAL ORIGIN MARKETS) -->
    <div class="hs-country-explorer hs-panel">
        <div class="hs-country-explorer__head">
            <span class="hs-country-explorer__label">EXPLORE BY ORIGIN MARKET:</span>
            <?php if ($country !== '') : ?>
                <a href="<?php echo esc_url(remove_query_arg('country', $brandArchiveUrl)); ?>" class="hs-context-clear-all">
                    ✕ Reset Market Filter (<?php echo esc_html($country); ?>)
                </a>
            <?php endif; ?>
        </div>
        <div class="hs-country-chips-wrap">
            <?php foreach (array_slice($countryCounts, 0, 10, true) as $cName => $cCount) :
                $cNorm = helmetsan_normalize_country($cName);
                $isAct = (strcasecmp($country, $cName) === 0);
                $chipUrl = $isAct ? remove_query_arg('country', $brandArchiveUrl) : add_query_arg('country', $cName, $brandArchiveUrl);
                ?>
                <a href="<?php echo esc_url($chipUrl); ?>"
                   class="hs-country-chip <?php echo $isAct ? 'is-active' : ''; ?>"
                   title="<?php echo esc_attr($cName . ' · ' . $cCount . ' manufacturers'); ?>">
                    <span class="hs-country-chip__flag"><?php echo $cNorm['flag']; ?></span>
                    <span class="hs-country-chip__name"><?php echo esc_html($cName); ?></span>
                    <span class="hs-country-chip__count"><?php echo (int) $cCount; ?></span>
                    <?php if ($isAct) : ?><span class="hs-chip-remove" aria-hidden="true">✕</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 4. A-Z BRAND INDEX NAVIGATION -->
    <div class="hs-az-directory hs-panel">
        <div class="hs-country-explorer__head">
            <span class="hs-az-label">Browse by alphabet:</span>
            <?php if ($letter !== '') : ?>
                <a href="<?php echo esc_url(remove_query_arg('letter', $brandArchiveUrl)); ?>" class="hs-context-clear-all">
                    ✕ Clear Letter Filter (<?php echo esc_html($letter); ?>)
                </a>
            <?php endif; ?>
        </div>
        <div class="hs-az-letters">
            <a href="<?php echo esc_url(remove_query_arg('letter', $brandArchiveUrl)); ?>" class="hs-az-letter <?php echo ($letter === '') ? 'is-active' : ''; ?>">ALL</a>
            
            <?php
            // Numbers 0-9 chip
            $numCount = $letterCounts['0-9'] ?? 0;
            $numEmpty = ($numCount === 0);
            $numActive = ($letter === '0-9' || $letter === '#');
            ?>
            <a href="<?php echo $numEmpty ? '#' : esc_url(add_query_arg('letter', '0-9', $brandArchiveUrl)); ?>"
               class="hs-az-letter <?php echo $numActive ? 'is-active' : ''; ?> <?php echo $numEmpty ? 'is-disabled' : ''; ?>"
               <?php echo $numEmpty ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
               title="# / 0-9 (<?php echo (int) $numCount; ?> brands)">#</a>

            <?php foreach (range('A', 'Z') as $char) :
                $cnt = $letterCounts[$char] ?? 0;
                $isEmpty = ($cnt === 0);
                $isActive = ($letter === $char);
            ?>
                <a href="<?php echo $isEmpty ? '#' : esc_url(add_query_arg('letter', $char, $brandArchiveUrl)); ?>"
                   class="hs-az-letter <?php echo $isActive ? 'is-active' : ''; ?> <?php echo $isEmpty ? 'is-disabled' : ''; ?>"
                   <?php echo $isEmpty ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                   title="<?php echo esc_attr($char . ' (' . $cnt . ' brands)'); ?>">
                    <?php echo $char; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mobile Filter Toggle Bar -->
    <div class="hs-mobile-filter-bar">
        <button type="button" class="hs-btn hs-btn--outline hs-mobile-filter-btn" id="hsBrandFilterToggle" aria-expanded="false" aria-controls="hsBrandFiltersAside">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
            <span>Filter Manufacturers</span>
            <?php if ($activeFiltersCount > 0) : ?>
                <span class="hs-filter-badge"><?php echo (int) $activeFiltersCount; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- 5. MAIN CATALOG GRID & SIDEBAR -->
    <div class="hs-catalog hs-catalog--brands hs-catalog--wide" id="brands-catalog">
        <!-- Sidebar Filters -->
        <aside class="hs-catalog__filters hs-catalog__filters--sticky" id="hsBrandFiltersAside" aria-label="Brand filters">
            <div class="hs-catalog__filters-head">
                <strong>Filter Brands</strong>
                <?php if ($activeFiltersCount > 0) : ?>
                    <a class="hs-btn hs-btn--text hs-filter-clear-head" href="<?php echo esc_url($brandArchiveUrl); ?>">Reset</a>
                <?php endif; ?>
            </div>
            <form class="hs-filter-bar hs-filter-bar--stacked" method="get" action="<?php echo esc_url($brandArchiveUrl); ?>">
                <?php if ($search !== '') : ?><input type="hidden" name="s" value="<?php echo esc_attr($search); ?>" /><?php endif; ?>
                <?php if ($letter !== '') : ?><input type="hidden" name="letter" value="<?php echo esc_attr($letter); ?>" /><?php endif; ?>
                <?php if ($sort !== 'az') : ?><input type="hidden" name="sort" value="<?php echo esc_attr($sort); ?>" /><?php endif; ?>
                
                <label class="hs-filter-bar__label" for="filter-country">Country Market</label>
                <select name="country" id="filter-country" class="hs-filter-select">
                    <option value="">All Countries</option>
                    <?php foreach ($countryCounts as $cVal => $cnt) : ?>
                        <option value="<?php echo esc_attr($cVal); ?>" <?php selected(strcasecmp($country, $cVal) === 0); ?>><?php echo esc_html($cVal); ?> (<?php echo (int) $cnt; ?>)</option>
                    <?php endforeach; ?>
                </select>
                
                <label class="hs-filter-bar__label" for="filter-helmet-type">Helmet Category</label>
                <select name="helmet_type" id="filter-helmet-type" class="hs-filter-select">
                    <option value="">All Helmet Types</option>
                    <?php
                    $helmetTypes = get_terms(['taxonomy' => 'helmet_type', 'hide_empty' => false]);
                    if (is_array($helmetTypes)) :
                        foreach ($helmetTypes as $term) :
                            if (! ($term instanceof WP_Term)) continue;
                            ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($helmetType, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php
                        endforeach;
                    endif;
                    ?>
                </select>
                
                <label class="hs-filter-bar__label" for="filter-cert">Safety Standard</label>
                <select name="cert" id="filter-cert" class="hs-filter-select">
                    <option value="">All Certifications</option>
                    <?php foreach ($certsList as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($cert, $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="hs-filter-actions">
                    <a class="hs-btn hs-btn--outline" href="<?php echo esc_url($brandArchiveUrl); ?>">Clear all</a>
                    <button class="hs-btn hs-btn--primary" type="submit">Apply Filters</button>
                </div>
            </form>
        </aside>

        <!-- Main Directory Content -->
        <div class="hs-catalog__results">
            <?php
            $paged = max(1, (int) get_query_var('paged', 1));
            $queryArgs = [
                'post_type'      => 'brand',
                'post_status'    => 'publish',
                'posts_per_page' => 24,
                'paged'          => $paged,
                'orderby'        => 'title',
                'order'          => ($sort === 'za') ? 'DESC' : 'ASC',
            ];

            if (!empty($canonicalBrandIds)) {
                $queryArgs['post__in'] = $canonicalBrandIds;
            }

            if ($search !== '') {
                $queryArgs['s'] = $search;
            }

            $whereFilter = null;
            if ($letter !== '') {
                $queryArgs['search_title'] = $letter;
                $whereFilter = static function(string $where, WP_Query $query) use ($letter): string {
                    global $wpdb;
                    if ($query->get('search_title')) {
                        if ($letter === '0-9' || $letter === '#') {
                            $where .= " AND {$wpdb->posts}.post_title REGEXP '^[0-9]'";
                        } else {
                            $where .= " AND {$wpdb->posts}.post_title LIKE '" . esc_sql($wpdb->esc_like($letter)) . "%'";
                        }
                    }
                    return $where;
                };
                add_filter('posts_where', $whereFilter, 10, 2);
            }

            $metaQuery = [];
            if ($country !== '') {
                $cNorm = helmetsan_normalize_country($country);
                $countryVariants = array_unique([$country, $cNorm['name']]);
                if (strcasecmp($country, 'United States') === 0 || strcasecmp($country, 'USA') === 0) {
                    $countryVariants[] = 'USA';
                    $countryVariants[] = 'United States';
                }
                if (count($countryVariants) > 1) {
                    $countryMetaOr = ['relation' => 'OR'];
                    foreach ($countryVariants as $cVar) {
                        $countryMetaOr[] = ['key' => 'brand_origin_country', 'value' => $cVar, 'compare' => 'LIKE'];
                    }
                    $metaQuery[] = $countryMetaOr;
                } else {
                    $metaQuery[] = ['key' => 'brand_origin_country', 'value' => $country, 'compare' => 'LIKE'];
                }
            }

            if ($helmetType !== '') {
                $term = get_term_by('slug', $helmetType, 'helmet_type');
                $typeName = $term ? $term->name : ucwords(str_replace('-', ' ', $helmetType));
                $metaQuery[] = [
                    'relation' => 'OR',
                    ['key' => 'brand_helmet_types', 'value' => $typeName, 'compare' => 'LIKE'],
                    ['key' => 'brand_helmet_types', 'value' => $helmetType, 'compare' => 'LIKE'],
                ];
            }

            if ($cert !== '') {
                $metaQuery[] = ['key' => 'brand_certification_coverage', 'value' => $cert, 'compare' => 'LIKE'];
            }

            if ($metaQuery !== []) {
                $queryArgs['meta_query'] = $metaQuery;
            }

            $brandQuery = new WP_Query($queryArgs);
            if ($whereFilter !== null) {
                remove_filter('posts_where', $whereFilter, 10);
            }

            if (! $brandQuery->have_posts() && empty($search) && empty($country) && empty($cert) && empty($letter) && empty($helmetType)) {
                $fallbackArgs = $queryArgs;
                $fallbackArgs['lang'] = '';
                unset($fallbackArgs['post__in']);
                $brandQuery = new WP_Query($fallbackArgs);
            }
            ?>

            <!-- DIRECTORY TOOLBAR (Grid | List Switcher + Sort Selector + Range Count) -->
            <div class="hs-catalog__topbar hs-panel">
                <div class="hs-catalog__topbar-left">
                    <span class="hs-catalog__count-bold"><?php echo esc_html(number_format_i18n((int) $brandQuery->found_posts)); ?> manufacturers</span>
                    <?php if ($brandQuery->found_posts > 0) :
                        $ppp = (int) $brandQuery->get('posts_per_page');
                        $start = (($paged - 1) * $ppp) + 1;
                        $end = min($paged * $ppp, (int) $brandQuery->found_posts);
                        ?>
                        <span class="hs-results-context-count">
                            · Showing <?php echo (int) $start; ?>–<?php echo (int) $end; ?> of <?php echo (int) $brandQuery->found_posts; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="hs-catalog__topbar-right">
                    <!-- Sort Selection inside Results Toolbar -->
                    <form method="get" action="<?php echo esc_url($brandArchiveUrl); ?>" class="hs-toolbar-sort-form">
                        <?php if ($search !== '') : ?><input type="hidden" name="s" value="<?php echo esc_attr($search); ?>" /><?php endif; ?>
                        <?php if ($country !== '') : ?><input type="hidden" name="country" value="<?php echo esc_attr($country); ?>" /><?php endif; ?>
                        <?php if ($helmetType !== '') : ?><input type="hidden" name="helmet_type" value="<?php echo esc_attr($helmetType); ?>" /><?php endif; ?>
                        <?php if ($cert !== '') : ?><input type="hidden" name="cert" value="<?php echo esc_attr($cert); ?>" /><?php endif; ?>
                        <?php if ($letter !== '') : ?><input type="hidden" name="letter" value="<?php echo esc_attr($letter); ?>" /><?php endif; ?>
                        <select name="sort" onchange="this.form.submit()" class="hs-toolbar-sort-select" aria-label="Sort manufacturers">
                            <option value="az" <?php selected($sort, 'az'); ?>>Sort: A–Z ▾</option>
                            <option value="za" <?php selected($sort, 'za'); ?>>Sort: Z–A ▾</option>
                        </select>
                    </form>

                    <div class="hs-catalog__view-switcher" role="group" aria-label="View switcher">
                        <button type="button" class="hs-view-btn is-active" data-view="grid" title="Grid View" aria-label="Grid View">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            <span>Grid</span>
                        </button>
                        <button type="button" class="hs-view-btn" data-view="list" title="List View" aria-label="List View">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            <span>List</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- BRAND CARDS GRID (4 Columns Desktop / 2 Tablet / 1 Mobile) -->
            <section class="hs-catalog__results-content">
                <?php if ($brandQuery->have_posts()) : ?>
                    <div class="hs-catalog-grid hs-catalog-grid--4cols">
                        <?php
                        while ($brandQuery->have_posts()) : $brandQuery->the_post();
                            get_template_part('template-parts/brand-card');
                        endwhile;
                        ?>
                    </div>

                    <?php
                    $ppp = (int) $brandQuery->get('posts_per_page');
                    $start = (($paged - 1) * $ppp) + 1;
                    $end = min($paged * $ppp, (int) $brandQuery->found_posts);
                    $count_text = sprintf(__('Showing %d–%d of %d', 'helmetsan-theme'), $start, $end, $brandQuery->found_posts);

                    get_template_part('template-parts/pagination-modern', null, [
                        'paged'      => $paged,
                        'total'      => (int) $brandQuery->max_num_pages,
                        'count_text' => $count_text
                    ]);
                    ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <div class="hs-catalog-empty-v2">
                        <div class="hs-catalog-empty-v2__icon">🔍</div>
                        <h3 class="hs-catalog-empty-v2__title">No manufacturer profiles found</h3>
                        <p class="hs-catalog-empty-v2__desc">We couldn't find any brands matching your active criteria. Try broadening your search or resetting filters.</p>
                        <a class="hs-btn hs-btn--primary" href="<?php echo esc_url($brandArchiveUrl); ?>">Reset all filters</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var filterToggle = document.getElementById('hsBrandFilterToggle');
    var filterAside = document.getElementById('hsBrandFiltersAside');
    if (filterToggle && filterAside) {
        filterToggle.addEventListener('click', function() {
            var isOpen = filterAside.classList.toggle('is-open');
            filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }
});
</script>

<?php
get_footer();
