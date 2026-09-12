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

// Fetch all published brand posts for metadata counts & deduplication
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

foreach ($rawBrandPosts as $brandPost) {
    if (! ($brandPost instanceof WP_Post)) {
        continue;
    }
    $normTitle = strtolower(trim($brandPost->post_title));
    if (! isset($uniqueBrands[$normTitle])) {
        $uniqueBrands[$normTitle] = $brandPost;
    }

    $cMeta = trim((string) get_post_meta($brandPost->ID, 'brand_origin_country', true));
    if (stripos($cMeta, 'unknown') === false && stripos($cMeta, 'unverified') === false) {
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

arsort($countryCounts);
ksort($certsList, SORT_NATURAL | SORT_FLAG_CASE);

$total_brands = count($uniqueBrands);
$active_countries = count($countryCounts);
$total_helmets = wp_count_posts('helmet')->publish;
?>

<section class="hs-section hs-section--archive-wide hs-brands-hub">
    <!-- 1. MANUFACTURE INTELLIGENCE HERO -->
    <header class="hs-brands-hero--v3">
        <div class="hs-brands-hero__top">
            <span class="hs-eyebrow">Manufacturer Registry & Intelligence</span>
            <h1 class="hs-brands-hero__title">Global Helmet Brands</h1>
            <p class="hs-brands-hero__subtitle">
                Deciphering origins, safety engineering, and manufacturing philosophies across leading helmet makers.
            </p>
        </div>

        <div class="hs-brands-hero__stats-row">
            <div class="hs-brand-stat-box">
                <span class="hs-brand-stat-num"><?php echo esc_html($total_brands); ?></span>
                <span class="hs-brand-stat-lbl">Manufacturers</span>
            </div>
            <div class="hs-brand-stat-box">
                <span class="hs-brand-stat-num"><?php echo esc_html($active_countries); ?></span>
                <span class="hs-brand-stat-lbl">Origin Markets</span>
            </div>
            <div class="hs-brand-stat-box">
                <span class="hs-brand-stat-num"><?php echo number_format($total_helmets); ?></span>
                <span class="hs-brand-stat-lbl">Helmets Tracked</span>
            </div>
        </div>
    </header>

    <!-- 2. PROMINENT SEARCH BAR -->
    <div class="hs-brand-search-box hs-panel">
        <form method="get" action="<?php echo esc_url(get_post_type_archive_link('brand')); ?>" class="hs-smart-search-form">
            <div class="hs-smart-search-input-wrap">
                <svg class="hs-smart-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" name="s" class="hs-smart-search-input" value="<?php echo esc_attr($search); ?>" placeholder="Search manufacturers, brands or helmet makers..." />
                <button type="submit" class="hs-smart-search-btn">Search</button>
            </div>
        </form>
    </div>

    <!-- 3. COUNTRY EXPLORER BAR (CANONICAL ORIGIN MARKETS) -->
    <div class="hs-country-explorer hs-panel">
        <span class="hs-country-explorer__label">EXPLORE BY ORIGIN MARKET:</span>
        <div class="hs-country-chips-wrap">
            <?php foreach (array_slice($countryCounts, 0, 8, true) as $cName => $cCount) :
                $cNorm = helmetsan_normalize_country($cName);
                ?>
                <a href="<?php echo esc_url(add_query_arg('country', $cName, $brandArchiveUrl)); ?>"
                   class="hs-country-chip <?php echo strcasecmp($country, $cName) === 0 ? 'is-active' : ''; ?>">
                    <?php echo $cNorm['flag']; ?> <?php echo esc_html($cName); ?> · <?php echo (int) $cCount; ?>
                </a>
            <?php endforeach; ?>
            <?php if ($country !== '') : ?>
                <a href="<?php echo esc_url($brandArchiveUrl); ?>" class="hs-context-clear-all">Clear Filter</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. A-Z BRAND INDEX NAVIGATION -->
    <div class="hs-az-directory hs-panel">
        <span class="hs-az-label">Browse brands:</span>
        <div class="hs-az-letters">
            <a href="<?php echo esc_url($brandArchiveUrl); ?>" class="hs-az-letter <?php echo ($letter === '') ? 'is-active' : ''; ?>">ALL</a>
            <?php foreach (range('A', 'Z') as $char) : ?>
                <a href="<?php echo esc_url(add_query_arg('letter', $char, $brandArchiveUrl)); ?>"
                   class="hs-az-letter <?php echo ($letter === $char) ? 'is-active' : ''; ?>">
                    <?php echo $char; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 5. MAIN CATALOG GRID & SIDEBAR -->
    <div class="hs-catalog hs-catalog--brands hs-catalog--wide" id="brands-catalog">
        <!-- Sidebar Filters -->
        <aside class="hs-catalog__filters hs-catalog__filters--sticky" aria-label="Brand filters">
            <div class="hs-catalog__filters-head">
                <strong>Filter Brands</strong>
            </div>
            <form class="hs-filter-bar hs-filter-bar--stacked" method="get" action="<?php echo esc_url(get_post_type_archive_link('brand')); ?>">
                <?php if ($letter !== '') : ?>
                    <input type="hidden" name="letter" value="<?php echo esc_attr($letter); ?>" />
                <?php endif; ?>
                <label class="hs-filter-bar__label">Country Market</label>
                <select name="country">
                    <option value="">All Countries</option>
                    <?php foreach ($countryCounts as $cVal => $cnt) : ?>
                        <option value="<?php echo esc_attr($cVal); ?>" <?php selected($country, $cVal); ?>><?php echo esc_html($cVal); ?> (<?php echo (int) $cnt; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <label class="hs-filter-bar__label">Helmet Type</label>
                <select name="helmet_type">
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
                <label class="hs-filter-bar__label">Certification Mark</label>
                <select name="cert">
                    <option value="">All Certifications</option>
                    <?php foreach ($certsList as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($cert, $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="hs-filter-actions" style="margin-top: 1rem;">
                    <a class="hs-btn hs-btn--text" href="<?php echo esc_url($brandArchiveUrl); ?>">Clear all</a>
                    <button class="hs-btn hs-btn--primary" type="submit">Apply</button>
                </div>
            </form>
        </aside>

        <!-- Main Directory Content -->
        <div class="hs-catalog__results">
            <?php
            $paged = max(1, (int) get_query_var('paged', 1));
            $queryArgs = [
                'post_type' => 'brand',
                'post_status' => 'publish',
                'posts_per_page' => 24,
                'paged' => $paged,
                'orderby' => 'title',
                'order' => ($sort === 'za') ? 'DESC' : 'ASC',
            ];

            if ($search !== '') {
                $queryArgs['s'] = $search;
            }
            if ($letter !== '') {
                $queryArgs['search_title'] = $letter;
                add_filter('posts_where', function($where, $query) use ($letter) {
                    global $wpdb;
                    if ($query->get('search_title')) {
                        $where .= " AND {$wpdb->posts}.post_title LIKE '" . esc_sql($wpdb->esc_like($letter)) . "%'";
                    }
                    return $where;
                }, 10, 2);
            }

            $metaQuery = [];
            if ($country !== '') {
                $metaQuery[] = ['key' => 'brand_origin_country', 'value' => $country, 'compare' => 'LIKE'];
            }
            if ($cert !== '') {
                $metaQuery[] = ['key' => 'brand_certification_coverage', 'value' => $cert, 'compare' => 'LIKE'];
            }
            if ($metaQuery !== []) {
                $queryArgs['meta_query'] = $metaQuery;
            }

            $brandQuery = new WP_Query($queryArgs);
            if (! $brandQuery->have_posts() && empty($search) && empty($country) && empty($cert) && empty($letter)) {
                $fallbackArgs = $queryArgs;
                $fallbackArgs['lang'] = '';
                $brandQuery = new WP_Query($fallbackArgs);
            }
            ?>

            <!-- DIRECTORY TOOLBAR (Grid | List Switcher + Sort Selector + Range Count) -->
            <div class="hs-catalog__topbar hs-panel" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <div class="hs-catalog__topbar-left" style="display: flex; align-items: baseline; gap: 0.5rem;">
                    <span class="hs-catalog__count" style="font-weight: 800; font-size: 1.05rem; color: #0f172a;"><?php echo esc_html(number_format_i18n((int) $brandQuery->found_posts)); ?> manufacturers</span>
                    <?php if ($brandQuery->found_posts > 0) :
                        $ppp = $brandQuery->get('posts_per_page');
                        $start = (($paged - 1) * $ppp) + 1;
                        $end = min($paged * $ppp, $brandQuery->found_posts);
                        ?>
                        <span class="hs-results-context-count" style="font-size: 0.84rem; color: #64748b;">
                            · Showing <?php echo (int) $start; ?>–<?php echo (int) $end; ?> of <?php echo (int) $brandQuery->found_posts; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="hs-catalog__topbar-right" style="display: flex; align-items: center; gap: 0.75rem;">
                    <!-- Sort Selection inside Results Toolbar -->
                    <form method="get" action="<?php echo esc_url(helmetsan_url('/brands/')); ?>" class="hs-toolbar-sort-form">
                        <?php if ($search !== '') : ?><input type="hidden" name="s" value="<?php echo esc_attr($search); ?>" /><?php endif; ?>
                        <?php if ($country !== '') : ?><input type="hidden" name="country" value="<?php echo esc_attr($country); ?>" /><?php endif; ?>
                        <?php if ($letter !== '') : ?><input type="hidden" name="letter" value="<?php echo esc_attr($letter); ?>" /><?php endif; ?>
                        <select name="sort" onchange="this.form.submit()" class="hs-toolbar-sort-select" style="padding: 0.4rem 0.75rem; border: 1px solid var(--hs-border); border-radius: 8px; font-size: 0.8125rem; font-weight: 700; color: #0f172a; background: #ffffff;">
                            <option value="az" <?php selected($sort, 'az'); ?>>Sort: A–Z ▾</option>
                            <option value="za" <?php selected($sort, 'za'); ?>>Sort: Z–A ▾</option>
                        </select>
                    </form>

                    <div class="hs-catalog__view-switcher">
                        <button type="button" class="hs-view-btn is-active" data-view="grid" title="Grid View">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            <span>Grid</span>
                        </button>
                        <button type="button" class="hs-view-btn" data-view="list" title="List View">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
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
                    $ppp = $brandQuery->get('posts_per_page');
                    $start = (($paged - 1) * $ppp) + 1;
                    $end = min($paged * $ppp, $brandQuery->found_posts);
                    $count_text = sprintf(__('Showing %d–%d of %d', 'helmetsan-theme'), $start, $end, $brandQuery->found_posts);

                    get_template_part('template-parts/pagination-modern', null, [
                        'paged' => $paged,
                        'total' => (int) $brandQuery->max_num_pages,
                        'count_text' => $count_text
                    ]);
                    ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <p class="hs-catalog__empty">No manufacturer profiles found matching criteria. <a href="<?php echo esc_url($brandArchiveUrl); ?>">Clear all filters</a>.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<?php
get_footer();



