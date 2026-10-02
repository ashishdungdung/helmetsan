<?php
/**
 * Motorcycle Catalogue Archive Template (V3 Hub — Enhanced).
 *
 * Architecture:
 * 1. Hero: Title, live motorcycle counter, search form with query persistence & sort control.
 * 2. Active Filters Bar: Removable chips with 1-click '✕' removal + 'Clear All'.
 * 3. Segment Filters: Interactive pills for segments with post counts and active toggles.
 * 4. Popular Manufacturers: Dynamic pills for top makes with counts.
 * 5. Results Bar: Search/facet summary, result counter, sort selector.
 * 6. Card Grid: 3-column responsive grid of rich motorcycle cards.
 *    - Segment-specific SVG silhouette well.
 *    - Segment badge & Make badge (WCAG AAA contrast).
 *    - Model Title.
 *    - Micro Spec indicators with semantic icons (CC, Seat Height, Top Speed).
 *    - Actionable Helmet Pairing tags linking directly to the Helmets catalog.
 * @package HelmetsanTheme
 */

get_header();

// Fetch segment terms with counts
$segment_terms = get_terms([
    'taxonomy'   => 'motorcycle_segment',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
]);

// Fetch top manufacturer terms with counts
$make_terms = get_terms([
    'taxonomy'   => 'motorcycle_make',
    'hide_empty' => true,
    'number'     => 14,
    'orderby'    => 'count',
    'order'      => 'DESC',
]);

$active_segment = isset($_GET['hs_segment']) ? sanitize_title(wp_unslash($_GET['hs_segment'])) : '';
$active_make    = isset($_GET['hs_make']) ? sanitize_title(wp_unslash($_GET['hs_make'])) : '';
$search_value   = isset($_GET['hs_q']) ? trim(sanitize_text_field(wp_unslash($_GET['hs_q']))) : '';
$active_sort    = isset($_GET['hs_sort']) ? sanitize_key((string) $_GET['hs_sort']) : '';

$filter_url = static function (array $changes = []) use ($active_segment, $active_make, $search_value, $active_sort): string {
    $args = array_filter(
        [
            'hs_q'       => $search_value,
            'hs_segment' => $active_segment,
            'hs_make'    => $active_make,
            'hs_sort'    => $active_sort,
        ],
        static fn($val) => $val !== '' && $val !== null
    );

    foreach ($changes as $key => $value) {
        if ($value === '' || $value === null) {
            unset($args[$key]);
        } else {
            $args[$key] = $value;
        }
    }

    $base = get_post_type_archive_link('motorcycle') ?: home_url('/motorcycles/');
    return esc_url(add_query_arg($args, $base));
};

// Map helmet type string to canonical archive filter URL
if (! function_exists('hs_moto_helmet_link')) {
    function hs_moto_helmet_link(string $type_name): string {
        $clean = strtolower(trim($type_name));
        $type_slug = 'full-face';
        if (str_contains($clean, 'dual sport') || str_contains($clean, 'adventure') || str_contains($clean, 'enduro')) {
            $type_slug = 'dual-sport';
        } elseif (str_contains($clean, 'modular') || str_contains($clean, 'flip-up')) {
            $type_slug = 'modular';
        } elseif (str_contains($clean, 'open face') || str_contains($clean, 'jet')) {
            $type_slug = 'open-face';
        } elseif (str_contains($clean, 'half') || str_contains($clean, 'cruiser')) {
            $type_slug = 'half-helmet';
        } elseif (str_contains($clean, 'motocross') || str_contains($clean, 'dirt')) {
            $type_slug = 'off-road';
        }
        return esc_url(add_query_arg(['helmet_type[]' => $type_slug], home_url('/helmets/')));
    }
}

// SVG Silhouette generator function based on segment
if (! function_exists('hs_render_moto_silhouette')) {
    function hs_render_moto_silhouette(string $segment_slug): string {
        $slug = strtolower($segment_slug);
        if (str_contains($slug, 'sport') || str_contains($slug, 'superbike')) {
            return '<svg viewBox="0 0 160 80" class="hs-moto-silhouette hs-moto-silhouette--sport" aria-hidden="true">
                <circle cx="35" cy="55" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="125" cy="55" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="35" cy="55" r="6" fill="currentColor" opacity="0.3"/>
                <circle cx="125" cy="55" r="6" fill="currentColor" opacity="0.3"/>
                <path d="M35 55 L58 36 L88 38 L115 22 L132 38 L125 55" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M58 36 L75 22 L98 22 L112 36 Z" fill="currentColor" opacity="0.25"/>
                <path d="M88 38 L72 55 L125 55" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <path d="M70 20 L60 20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>';
        } elseif (str_contains($slug, 'adventure') || str_contains($slug, 'dual-sport')) {
            return '<svg viewBox="0 0 160 80" class="hs-moto-silhouette hs-moto-silhouette--adventure" aria-hidden="true">
                <circle cx="32" cy="52" r="20" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="128" cy="54" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="32" cy="52" r="7" fill="currentColor" opacity="0.3"/>
                <circle cx="128" cy="54" r="7" fill="currentColor" opacity="0.3"/>
                <path d="M32 52 L55 24 L62 14 L75 26 L96 32 L116 28 L138 32 L128 54" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M55 24 L75 26 L90 38 L65 52 Z" fill="currentColor" opacity="0.25"/>
                <path d="M60 14 L50 20 L30 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
                <path d="M62 14 L68 8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <path d="M118 28 L138 28 L142 42 L124 42 Z" fill="none" stroke="currentColor" stroke-width="2.5"/>
            </svg>';
        } elseif (str_contains($slug, 'cruiser')) {
            return '<svg viewBox="0 0 160 80" class="hs-moto-silhouette hs-moto-silhouette--cruiser" aria-hidden="true">
                <circle cx="30" cy="54" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="130" cy="54" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="30" cy="54" r="6" fill="currentColor" opacity="0.3"/>
                <circle cx="130" cy="54" r="6" fill="currentColor" opacity="0.3"/>
                <path d="M30 54 L62 48 L80 32 L98 42 L120 48 L130 54" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M62 48 L80 32 L98 42 L95 54 L55 54 Z" fill="currentColor" opacity="0.25"/>
                <path d="M80 32 L68 18 L62 18" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
                <path d="M98 42 L112 36 L124 44" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>';
        } elseif (str_contains($slug, 'scooter')) {
            return '<svg viewBox="0 0 160 80" class="hs-moto-silhouette hs-moto-silhouette--scooter" aria-hidden="true">
                <circle cx="34" cy="58" r="14" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="126" cy="58" r="14" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="34" cy="58" r="5" fill="currentColor" opacity="0.3"/>
                <circle cx="126" cy="58" r="5" fill="currentColor" opacity="0.3"/>
                <path d="M34 58 L48 30 L58 18 L70 32 L88 52 L108 52 L118 36 L134 40 L126 58" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M58 18 L52 18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <path d="M100 36 L128 36 L124 50 L95 50 Z" fill="currentColor" opacity="0.25"/>
                <path d="M72 52 L100 52" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
            </svg>';
        } elseif (str_contains($slug, 'tourer') || str_contains($slug, 'touring')) {
            return '<svg viewBox="0 0 160 80" class="hs-moto-silhouette hs-moto-silhouette--touring" aria-hidden="true">
                <circle cx="34" cy="55" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="126" cy="55" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="34" cy="55" r="6" fill="currentColor" opacity="0.3"/>
                <circle cx="126" cy="55" r="6" fill="currentColor" opacity="0.3"/>
                <path d="M34 55 L52 30 L64 12 L84 28 L104 35 L120 28 L138 30 L136 50 L126 55" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M52 30 L64 12 L75 26 L65 52 Z" fill="currentColor" opacity="0.25"/>
                <path d="M115 28 L138 28 L136 48 L112 48 Z" fill="none" stroke="currentColor" stroke-width="3"/>
            </svg>';
        } else {
            return '<svg viewBox="0 0 160 80" class="hs-moto-silhouette hs-moto-silhouette--naked" aria-hidden="true">
                <circle cx="34" cy="54" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="126" cy="54" r="18" fill="none" stroke="currentColor" stroke-width="4"/>
                <circle cx="34" cy="54" r="6" fill="currentColor" opacity="0.3"/>
                <circle cx="126" cy="54" r="6" fill="currentColor" opacity="0.3"/>
                <path d="M34 54 L58 32 L78 26 L98 32 L116 26 L130 36 L126 54" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M58 32 L78 26 L94 36 L86 52 L62 52 Z" fill="currentColor" opacity="0.25"/>
                <path d="M58 32 L56 22 L48 20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <path d="M78 26 L94 48 M70 38 L88 38" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>';
        }
    }
}

global $wp_query;
$total_bikes = (int) ($wp_query->found_posts ?? 0);
$has_active_filters = ($active_segment !== '' || $active_make !== '' || $search_value !== '' || $active_sort !== '');
?>

<main class="hs-moto-hub">
    <!-- Hero Section -->
    <section class="hs-moto-hero" aria-labelledby="hs-moto-title">
        <div class="hs-moto-hero__copy">
            <span class="hs-moto-eyebrow">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <?php esc_html_e('Helmetsan Motorcycle Intelligence', 'helmetsan-theme'); ?>
            </span>
            <h1 id="hs-moto-title"><?php esc_html_e('Find your motorcycle. Discover matching helmets.', 'helmetsan-theme'); ?></h1>
            <p class="hs-moto-hero__intro">
                <?php esc_html_e('Explore motorcycles by riding segment, compare ergonomic specs, and get precision helmet pairings calibrated for your riding posture.', 'helmetsan-theme'); ?>
            </p>

            <!-- Search Form -->
            <form class="hs-moto-search" role="search" method="get" action="<?php echo esc_url(get_post_type_archive_link('motorcycle')); ?>">
                <label class="screen-reader-text" for="hs-moto-search-input">
                    <?php esc_html_e('Search motorcycles', 'helmetsan-theme'); ?>
                </label>
                <span class="hs-moto-search__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                    id="hs-moto-search-input"
                    type="search"
                    name="hs_q"
                    value="<?php echo esc_attr($search_value); ?>"
                    placeholder="<?php esc_attr_e('Search make or model (e.g. BMW GS, Himalayan, CBR)...', 'helmetsan-theme'); ?>"
                    autocomplete="off"
                >
                <?php if ($search_value !== '') : ?>
                    <a href="<?php echo $filter_url(['hs_q' => '']); ?>" class="hs-moto-search__clear" title="<?php esc_attr_e('Clear search', 'helmetsan-theme'); ?>" aria-label="<?php esc_attr_e('Clear search query', 'helmetsan-theme'); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($active_segment) : ?>
                    <input type="hidden" name="hs_segment" value="<?php echo esc_attr($active_segment); ?>">
                <?php endif; ?>
                <?php if ($active_make) : ?>
                    <input type="hidden" name="hs_make" value="<?php echo esc_attr($active_make); ?>">
                <?php endif; ?>
                <?php if ($active_sort) : ?>
                    <input type="hidden" name="hs_sort" value="<?php echo esc_attr($active_sort); ?>">
                <?php endif; ?>
                <button type="submit" class="hs-moto-search__btn">
                    <span><?php esc_html_e('Search', 'helmetsan-theme'); ?></span>
                </button>
            </form>

            <!-- Live Status Note -->
            <div class="hs-moto-hero__meta">
                <span class="hs-moto-counter">
                    <span class="hs-moto-counter__dot"></span>
                    <?php
                    printf(
                        /* translators: %s: number of bikes in catalogue */
                        esc_html__('%s motorcycles indexed', 'helmetsan-theme'),
                        '<strong>' . esc_html(number_format_i18n($total_bikes)) . '</strong>'
                    );
                    ?>
                </span>
            </div>
        </div>

        <!-- Technical Silhouette Hero Visual -->
        <div class="hs-moto-hero__art" aria-hidden="true">
            <div class="hs-moto-hero__art-card">
                <div class="hs-moto-hero__rings">
                    <span class="hs-moto-ring hs-moto-ring--1"></span>
                    <span class="hs-moto-ring hs-moto-ring--2"></span>
                </div>
                <svg viewBox="0 0 480 240" class="hs-moto-hero__schematic" role="presentation">
                    <circle cx="100" cy="170" r="42" fill="none" stroke="currentColor" stroke-width="5" stroke-dasharray="4 2"/>
                    <circle cx="100" cy="170" r="18" fill="none" stroke="currentColor" stroke-width="3"/>
                    <circle cx="380" cy="170" r="42" fill="none" stroke="currentColor" stroke-width="5" stroke-dasharray="4 2"/>
                    <circle cx="380" cy="170" r="18" fill="none" stroke="currentColor" stroke-width="3"/>
                    <path d="M100 170 L170 95 L245 95 L320 65 L370 110 L380 170" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M170 95 L225 170 L380 170" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                    <path d="M245 95 L290 140 L345 140" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                    <path d="M300 65 L275 60 L240 60" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                </svg>
                <div class="hs-moto-hero__art-badge">
                    <span class="hs-moto-hero__art-dot"></span>
                    <span><?php esc_html_e('Multi-Axial Ergonomic Calibrations', 'helmetsan-theme'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Segment Filter Pills -->
    <?php if (is_array($segment_terms) && ! is_wp_error($segment_terms) && ! empty($segment_terms)) : ?>
        <section class="hs-moto-browse" aria-label="<?php esc_attr_e('Filter by motorcycle segment', 'helmetsan-theme'); ?>">
            <div class="hs-moto-pills-wrap">
                <span class="hs-moto-pills-label"><?php esc_html_e('Segment:', 'helmetsan-theme'); ?></span>
                <nav class="hs-moto-pills" aria-label="<?php esc_attr_e('Motorcycle segments', 'helmetsan-theme'); ?>">
                    <a class="hs-moto-pill <?php echo $active_segment === '' ? 'is-active' : ''; ?>"
                        href="<?php echo $filter_url(['hs_segment' => '']); ?>">
                        <?php esc_html_e('All Segments', 'helmetsan-theme'); ?>
                    </a>
                    <?php foreach ($segment_terms as $seg) : ?>
                        <?php
                        $is_seg_active = ($active_segment === $seg->slug);
                        $toggle_url = $is_seg_active ? $filter_url(['hs_segment' => '']) : $filter_url(['hs_segment' => $seg->slug]);
                        ?>
                        <a class="hs-moto-pill <?php echo $is_seg_active ? 'is-active' : ''; ?>"
                            href="<?php echo $toggle_url; ?>"
                            <?php echo $is_seg_active ? 'aria-current="page"' : ''; ?>>
                            <?php echo esc_html($seg->name); ?>
                            <span class="hs-moto-pill__count"><?php echo esc_html(number_format_i18n($seg->count)); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </section>
    <?php endif; ?>

    <!-- Manufacturer Quick Chips -->
    <?php if (is_array($make_terms) && ! is_wp_error($make_terms) && ! empty($make_terms)) : ?>
        <section class="hs-moto-makers" aria-label="<?php esc_attr_e('Top manufacturers', 'helmetsan-theme'); ?>">
            <div class="hs-moto-makers-wrap">
                <span class="hs-moto-makers-label"><?php esc_html_e('Make:', 'helmetsan-theme'); ?></span>
                <div class="hs-moto-maker-list">
                    <?php foreach ($make_terms as $mk) : ?>
                        <?php
                        $is_make_active = ($active_make === $mk->slug);
                        $toggle_make_url = $is_make_active ? $filter_url(['hs_make' => '']) : $filter_url(['hs_make' => $mk->slug]);
                        ?>
                        <a class="hs-moto-maker-chip <?php echo $is_make_active ? 'is-active' : ''; ?>"
                            href="<?php echo $toggle_make_url; ?>">
                            <?php echo esc_html($mk->name); ?>
                            <span class="hs-moto-maker-chip__count"><?php echo esc_html(number_format_i18n($mk->count)); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Active Filters Row (Removable Chips) -->
    <?php if ($has_active_filters) : ?>
        <section class="hs-moto-active-filters" aria-label="<?php esc_attr_e('Active filters', 'helmetsan-theme'); ?>">
            <span class="hs-moto-active-filters__label"><?php esc_html_e('Active Filters:', 'helmetsan-theme'); ?></span>
            <div class="hs-moto-active-filters__list">
                <?php if ($search_value !== '') : ?>
                    <a href="<?php echo $filter_url(['hs_q' => '']); ?>" class="hs-moto-filter-chip">
                        <span><?php printf(esc_html__('Search: “%s”', 'helmetsan-theme'), esc_html($search_value)); ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </a>
                <?php endif; ?>

                <?php if ($active_segment !== '') : ?>
                    <?php $seg_obj = get_term_by('slug', $active_segment, 'motorcycle_segment'); ?>
                    <a href="<?php echo $filter_url(['hs_segment' => '']); ?>" class="hs-moto-filter-chip">
                        <span><?php printf(esc_html__('Segment: %s', 'helmetsan-theme'), esc_html($seg_obj ? $seg_obj->name : ucwords($active_segment))); ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </a>
                <?php endif; ?>

                <?php if ($active_make !== '') : ?>
                    <?php $make_obj = get_term_by('slug', $active_make, 'motorcycle_make'); ?>
                    <a href="<?php echo $filter_url(['hs_make' => '']); ?>" class="hs-moto-filter-chip">
                        <span><?php printf(esc_html__('Make: %s', 'helmetsan-theme'), esc_html($make_obj ? $make_obj->name : ucwords($active_make))); ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </a>
                <?php endif; ?>

                <?php if ($active_sort !== '') : ?>
                    <a href="<?php echo $filter_url(['hs_sort' => '']); ?>" class="hs-moto-filter-chip">
                        <span><?php esc_html_e('Custom Sort', 'helmetsan-theme'); ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </a>
                <?php endif; ?>

                <a href="<?php echo esc_url(get_post_type_archive_link('motorcycle')); ?>" class="hs-moto-filter-clear-all">
                    <?php esc_html_e('Reset All', 'helmetsan-theme'); ?>
                </a>
            </div>
        </section>
    <?php endif; ?>

    <!-- Results Section -->
    <section class="hs-moto-results" aria-labelledby="hs-moto-results-heading">
        <div class="hs-moto-results-bar">
            <div>
                <h2 id="hs-moto-results-heading" class="hs-moto-results-title">
                    <?php
                    if ($search_value !== '') {
                        printf(
                            esc_html__('Results for “%s”', 'helmetsan-theme'),
                            esc_html($search_value)
                        );
                    } elseif ($active_segment) {
                        $term_obj = get_term_by('slug', $active_segment, 'motorcycle_segment');
                        echo esc_html($term_obj ? $term_obj->name : ucwords(str_replace('-', ' ', $active_segment)));
                    } else {
                        esc_html_e('Motorcycles Catalogue', 'helmetsan-theme');
                    }
                    ?>
                </h2>
                <p class="hs-moto-results-subtitle">
                    <?php
                    if ($active_make) {
                        $make_obj = get_term_by('slug', $active_make, 'motorcycle_make');
                        printf(
                            esc_html__('Filtered by %s', 'helmetsan-theme'),
                            '<strong>' . esc_html($make_obj ? $make_obj->name : ucwords($active_make)) . '</strong>'
                        );
                    } else {
                        esc_html_e('Showing verified ergonomic profiles and compatible helmet pairings', 'helmetsan-theme');
                    }
                    ?>
                </p>
            </div>

            <!-- Sort Controls -->
            <div class="hs-moto-results-controls">
                <form method="get" class="hs-moto-sort-form" action="<?php echo esc_url(get_post_type_archive_link('motorcycle')); ?>">
                    <?php if ($search_value !== '') : ?>
                        <input type="hidden" name="hs_q" value="<?php echo esc_attr($search_value); ?>">
                    <?php endif; ?>
                    <?php if ($active_segment) : ?>
                        <input type="hidden" name="hs_segment" value="<?php echo esc_attr($active_segment); ?>">
                    <?php endif; ?>
                    <?php if ($active_make) : ?>
                        <input type="hidden" name="hs_make" value="<?php echo esc_attr($active_make); ?>">
                    <?php endif; ?>
                    <label for="hs-moto-sort" class="hs-moto-sort-label"><?php esc_html_e('Sort by:', 'helmetsan-theme'); ?></label>
                    <select id="hs-moto-sort" name="hs_sort" class="hs-moto-sort-select" onchange="this.form.submit()">
                        <option value="" <?php selected($active_sort, ''); ?>><?php esc_html_e('Alphabetical (A–Z)', 'helmetsan-theme'); ?></option>
                        <option value="alpha_desc" <?php selected($active_sort, 'alpha_desc'); ?>><?php esc_html_e('Alphabetical (Z–A)', 'helmetsan-theme'); ?></option>
                        <option value="displacement_desc" <?php selected($active_sort, 'displacement_desc'); ?>><?php esc_html_e('Engine (Highest CC)', 'helmetsan-theme'); ?></option>
                        <option value="displacement_asc" <?php selected($active_sort, 'displacement_asc'); ?>><?php esc_html_e('Engine (Lowest CC)', 'helmetsan-theme'); ?></option>
                    </select>
                </form>
            </div>
        </div>

        <!-- India Authorized Dealers Discovery Strip -->
        <div class="hs-moto-hub-dealer-strip" style="background:linear-gradient(135deg,rgba(255,59,48,0.08),rgba(13,21,39,0.7)); border:1px solid rgba(255,59,48,0.25); border-radius:14px; padding:1.2rem 1.75rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div style="display:flex; align-items:center; gap:1rem;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:50%; background:var(--hs-accent, #ff3b30); color:#fff; flex-shrink:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <div>
                    <h3 style="margin:0 0 0.2rem 0; font-size:1.05rem; color:var(--hs-text-primary, #0f172a); font-weight:700;"><?php esc_html_e('Explore Authorized Two-Wheeler Dealerships in India', 'helmetsan-theme'); ?></h3>
                    <p style="margin:0; font-size:0.85rem; color:var(--hs-text-muted, #64748b);"><?php esc_html_e('Connect with verified showrooms in Delhi NCR, Mumbai, Bengaluru, Pune, Chennai, Hyderabad & Kolkata for doorstep test rides.', 'helmetsan-theme'); ?></p>
                </div>
            </div>
            <a href="<?php echo esc_url(home_url('/dealers/')); ?>" class="hs-moto-btn hs-moto-btn--primary" style="padding:0.55rem 1rem; font-size:0.85rem; border-radius:8px;">
                <?php esc_html_e('Dealer Directory ↗', 'helmetsan-theme'); ?>
            </a>
        </div>

        <?php if (have_posts()) : ?>
            <div class="hs-moto-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <?php
                    $post_id = get_the_ID();
                    $title   = get_the_title();
                    $link    = get_permalink();

                    // Retrieve metadata
                    $make_meta    = (string) get_post_meta($post_id, 'motorcycle_make', true);
                    $model_meta   = (string) get_post_meta($post_id, 'motorcycle_model', true);
                    $segment_meta = (string) get_post_meta($post_id, 'bike_segment', true);
                    $engine_cc    = get_post_meta($post_id, 'engine_cc', true);
                    $riding_pos   = (string) get_post_meta($post_id, 'riding_position', true);
                    $seat_height  = get_post_meta($post_id, 'seat_height_mm', true);
                    $top_speed    = get_post_meta($post_id, 'top_speed_kmh', true);
                    $price_inr    = (int) get_post_meta($post_id, 'price_inr', true);
                    if ($price_inr <= 0) {
                        $cc_int = (int) $engine_cc;
                        $price_inr = ($cc_int >= 400) ? 295000 : (($cc_int >= 200) ? 175000 : 115000);
                    }
                    $price_fmt = $price_inr >= 100000 ? sprintf('From ₹%.2fL', $price_inr / 100000) : ('₹' . number_format($price_inr));

                    // Taxonomy fallbacks
                    if ($segment_meta === '') {
                        $seg_terms = get_the_terms($post_id, 'motorcycle_segment');
                        if (! empty($seg_terms) && ! is_wp_error($seg_terms)) {
                            $segment_meta = $seg_terms[0]->name;
                        }
                    }
                    if ($make_meta === '') {
                        $mk_terms = get_the_terms($post_id, 'motorcycle_make');
                        if (! empty($mk_terms) && ! is_wp_error($mk_terms)) {
                            $make_meta = $mk_terms[0]->name;
                        }
                    }

                    // Recommended helmet types
                    $rec_helmets_raw = get_post_meta($post_id, 'recommended_helmet_types_json', true);
                    $rec_helmets = [];
                    if (is_array($rec_helmets_raw)) {
                        $rec_helmets = $rec_helmets_raw;
                    } elseif (is_string($rec_helmets_raw) && $rec_helmets_raw !== '') {
                        $decoded = json_decode($rec_helmets_raw, true);
                        if (is_array($decoded)) {
                            $rec_helmets = $decoded;
                        }
                    }

                    $segment_slug = sanitize_title($segment_meta ?: 'roadster');
                    ?>

                    <article class="hs-moto-card">
                        <!-- Media / Silhouette Well -->
                        <div class="hs-moto-card__media">
                            <div class="hs-moto-card__canvas">
                                <?php if (has_post_thumbnail($post_id)) : ?>
                                    <?php echo get_the_post_thumbnail($post_id, 'medium', ['class' => 'hs-moto-card__img', 'loading' => 'lazy']); ?>
                                <?php else : ?>
                                    <div class="hs-moto-card__silhouette-wrap">
                                        <?php echo hs_render_moto_silhouette($segment_slug); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Floating Badges -->
                            <div class="hs-moto-card__tags">
                                <?php if ($segment_meta) : ?>
                                    <span class="hs-moto-badge hs-moto-badge--segment"><?php echo esc_html($segment_meta); ?></span>
                                <?php endif; ?>
                                <?php if ($make_meta) : ?>
                                    <span class="hs-moto-badge hs-moto-badge--make"><?php echo esc_html($make_meta); ?></span>
                                <?php endif; ?>
                                <span class="hs-moto-badge" style="background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); font-weight:700;">
                                    <?php echo esc_html($price_fmt); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="hs-moto-card__body">
                            <h3 class="hs-moto-card__title">
                                <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a>
                            </h3>

                            <?php if ($riding_pos) : ?>
                                <p class="hs-moto-card__posture">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
                                    <span><?php echo esc_html($riding_pos); ?></span>
                                </p>
                            <?php endif; ?>

                            <!-- Specs Micro Grid with Icons -->
                            <div class="hs-moto-specs">
                                <div class="hs-moto-spec-item">
                                    <span class="hs-moto-spec-item__label">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                                        <?php esc_html_e('Displacement', 'helmetsan-theme'); ?>
                                    </span>
                                    <span class="hs-moto-spec-item__value">
                                        <?php echo ($engine_cc && (float)$engine_cc > 0) ? esc_html(number_format((float)$engine_cc) . ' cc') : esc_html__('Electric / N/A', 'helmetsan-theme'); ?>
                                    </span>
                                </div>
                                <div class="hs-moto-spec-item">
                                    <span class="hs-moto-spec-item__label">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                        <?php esc_html_e('Seat Height', 'helmetsan-theme'); ?>
                                    </span>
                                    <span class="hs-moto-spec-item__value">
                                        <?php echo ($seat_height && (int)$seat_height > 0) ? esc_html($seat_height . ' mm') : esc_html__('Standard', 'helmetsan-theme'); ?>
                                    </span>
                                </div>
                                <div class="hs-moto-spec-item">
                                    <span class="hs-moto-spec-item__label">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <?php esc_html_e('Top Speed', 'helmetsan-theme'); ?>
                                    </span>
                                    <span class="hs-moto-spec-item__value">
                                        <?php echo ($top_speed && (int)$top_speed > 0) ? esc_html($top_speed . ' km/h') : esc_html__('Highway Capable', 'helmetsan-theme'); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Actionable Helmet Recommendation -->
                            <?php if (! empty($rec_helmets)) : ?>
                                <div class="hs-moto-rec">
                                    <span class="hs-moto-rec__label">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a8 8 0 0 0-8 8c0 3.2 1.8 6 4.5 7.3L7 21l3.5-1.5 3.5 1.5-1.5-3.7A8 8 0 0 0 20 10a8 8 0 0 0-8-8z"/></svg>
                                        <?php esc_html_e('Pairing:', 'helmetsan-theme'); ?>
                                    </span>
                                    <div class="hs-moto-rec__types">
                                        <?php foreach (array_slice($rec_helmets, 0, 2) as $rType) : ?>
                                            <a href="<?php echo hs_moto_helmet_link($rType); ?>" class="hs-moto-rec__link" title="<?php printf(esc_attr__('Find %s helmets', 'helmetsan-theme'), esc_attr($rType)); ?>">
                                                <?php echo esc_html($rType); ?>
                                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M7 7h10v10"/></svg>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Footer -->
                        <div class="hs-moto-card__footer">
                            <a href="<?php echo esc_url($link); ?>" class="hs-moto-card__cta">
                                <span><?php esc_html_e('View Bike & Helmet Match', 'helmetsan-theme'); ?></span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <!-- Modern Pagination -->
            <div class="hs-moto-pagination">
                <?php
                $paged = max(1, (int) get_query_var('paged'));
                $total_pages = (int) ($wp_query->max_num_pages ?? 1);
                $ppp = (int) ($wp_query->get('posts_per_page') ?: 24);
                $start = (($paged - 1) * $ppp) + 1;
                $end = min($paged * $ppp, $total_bikes);
                $count_text = sprintf(__('Showing %1$d–%2$d of %3$d motorcycles', 'helmetsan-theme'), $start, $end, $total_bikes);

                if (locate_template('template-parts/pagination-modern.php')) {
                    get_template_part('template-parts/pagination-modern', null, [
                        'paged'      => $paged,
                        'total'      => $total_pages,
                        'count_text' => $count_text,
                    ]);
                } else {
                    echo paginate_links([
                        'total'     => $total_pages,
                        'current'   => $paged,
                        'prev_text' => __('« Previous', 'helmetsan-theme'),
                        'next_text' => __('Next »', 'helmetsan-theme'),
                    ]);
                }
                ?>
            </div>
        <?php else : ?>
            <div class="hs-moto-empty">
                <div class="hs-moto-empty__icon" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                </div>
                <h3><?php esc_html_e('No motorcycles matched your search criteria.', 'helmetsan-theme'); ?></h3>
                <p><?php esc_html_e('Try clearing some of your active segment or manufacturer filters, or check spelling.', 'helmetsan-theme'); ?></p>
                <a href="<?php echo esc_url(get_post_type_archive_link('motorcycle')); ?>" class="hs-btn hs-btn-primary">
                    <?php esc_html_e('Browse All Motorcycles', 'helmetsan-theme'); ?>
                </a>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php
get_footer();
