<?php
/**
 * Accessory archive: category cards + full catalog with filters and pagination.
 * Used for /accessories/ (post type archive).
 *
 * @package HelmetsanTheme
 */

// Redirect to clean URL when all query params are empty (avoids "Search results for" and ugly URLs).
$cleanUrl = home_url('/accessories/');
$allEmpty = true;
foreach (['s', 'accessory_category', 'helmet_type', 'pinlock_ready', 'electric', 'snow', 'sort'] as $key) {
    if (! isset($_GET[$key])) {
        continue;
    }
    $v = $_GET[$key];
    if (is_array($v)) {
        if (array_filter(array_map('trim', $v)) !== []) {
            $allEmpty = false;
            break;
        }
    } elseif (trim((string) $v) !== '') {
        $allEmpty = false;
        break;
    }
}
// Only redirect when stripping empty query params; avoid loop when already on clean URL.
if ($allEmpty && (! isset($_GET['paged']) || (int) $_GET['paged'] <= 1) && ! empty($_GET)) {
    wp_safe_redirect($cleanUrl, 302);
    exit;
}

get_header();

$pageUrl = get_post_type_archive_link('accessory');
if (! is_string($pageUrl) || $pageUrl === '') {
    $pageUrl = home_url('/accessories/');
}

$getString = static function (string $key): string {
    if (! isset($_GET[$key])) {
        return '';
    }
    $v = $_GET[$key];
    return is_array($v) ? '' : sanitize_text_field(wp_unslash((string) $v));
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

$search       = $getString('s');
$categories   = $getArray('accessory_category');
$helmetTypes  = $getArray('helmet_type');
$pinlock      = $getString('pinlock_ready');
$electric     = $getString('electric');
$snow         = $getString('snow');
$sort         = $getString('sort') !== '' ? $getString('sort') : 'title_asc';
$paged        = max(1, (int) ($getString('paged') !== '' ? $getString('paged') : get_query_var('paged', 1)));

$categoryTerms = get_terms([
    'taxonomy'   => 'accessory_category',
    'hide_empty' => false,
]);
$helmetTypeTerms = get_terms([
    'taxonomy'   => 'helmet_type',
    'hide_empty' => true,
]);

$themeDir = get_stylesheet_directory_uri();
$heroImg  = $themeDir . '/assets/images/hubs/accessories_hub_hero.png';
if (! file_exists(get_stylesheet_directory() . '/assets/images/hubs/accessories_hub_hero.png')) {
    $heroImg = $themeDir . '/assets/images/placeholder-hub.png';
}

// Query for accessories
$queryArgs = [
    'post_type'      => 'accessory',
    'post_status'    => 'publish',
    'posts_per_page' => 24,
    'paged'          => $paged,
];
if ($search !== '') {
    $queryArgs['s'] = $search;
}
$taxQuery = [];
if ($categories !== []) {
    $taxQuery[] = [
        'taxonomy' => 'accessory_category',
        'field'    => 'slug',
        'terms'    => $categories,
    ];
}
if ($helmetTypes !== []) {
    $taxQuery[] = [
        'taxonomy' => 'helmet_type',
        'field'    => 'slug',
        'terms'    => $helmetTypes,
    ];
}
if ($taxQuery !== []) {
    $queryArgs['tax_query'] = $taxQuery;
}
$metaQuery = [];
if ($pinlock !== '') {
    $metaQuery[] = [
        'key'   => 'accessory_pinlock_ready',
        'value' => $pinlock === '1' ? '1' : '0',
    ];
}
if ($electric !== '') {
    $metaQuery[] = [
        'key'   => 'accessory_electric_compatible',
        'value' => $electric === '1' ? '1' : '0',
    ];
}
if ($snow !== '') {
    $metaQuery[] = [
        'key'   => 'accessory_snow_compatible',
        'value' => $snow === '1' ? '1' : '0',
    ];
}
if ($metaQuery !== []) {
    $queryArgs['meta_query'] = $metaQuery;
}
switch ($sort) {
    case 'title_desc':
        $queryArgs['orderby'] = 'title';
        $queryArgs['order']   = 'DESC';
        break;
    case 'newest':
        $queryArgs['orderby'] = 'date';
        $queryArgs['order']   = 'DESC';
        break;
    case 'title_asc':
    default:
        $queryArgs['orderby'] = 'title';
        $queryArgs['order']   = 'ASC';
        break;
}

$accessoryQuery = new WP_Query($queryArgs);

// Current query for chips and hidden inputs
$currentQuery = [];
if ($search !== '') {
    $currentQuery['s'] = $search;
}
foreach ($categories as $c) {
    $currentQuery['accessory_category'][] = $c;
}
foreach ($helmetTypes as $ht) {
    $currentQuery['helmet_type'][] = $ht;
}
if ($pinlock !== '') {
    $currentQuery['pinlock_ready'] = $pinlock;
}
if ($electric !== '') {
    $currentQuery['electric'] = $electric;
}
if ($snow !== '') {
    $currentQuery['snow'] = $snow;
}
if ($sort !== 'title_asc') {
    $currentQuery['sort'] = $sort;
}

$removeFilterUrl = static function (string $key, string $value = '') use ($pageUrl, &$currentQuery): string {
    $queryArgs = $currentQuery;
    if (! isset($queryArgs[$key])) {
        return $pageUrl;
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
    return (string) add_query_arg($queryArgs, $pageUrl);
};

$activeChips = [];
if ($search !== '') {
    $activeChips[] = ['label' => 'Search: ' . $search, 'url' => $removeFilterUrl('s')];
}
foreach ($categories as $slug) {
    $term = get_term_by('slug', $slug, 'accessory_category');
    $activeChips[] = ['label' => $term instanceof WP_Term ? $term->name : $slug, 'url' => $removeFilterUrl('accessory_category', $slug)];
}
foreach ($helmetTypes as $slug) {
    $term = get_term_by('slug', $slug, 'helmet_type');
    $activeChips[] = ['label' => $term instanceof WP_Term ? $term->name : $slug, 'url' => $removeFilterUrl('helmet_type', $slug)];
}
if ($pinlock !== '') {
    $activeChips[] = ['label' => $pinlock === '1' ? 'Pinlock Ready' : 'Not Pinlock', 'url' => $removeFilterUrl('pinlock_ready')];
}
if ($electric !== '') {
    $activeChips[] = ['label' => $electric === '1' ? 'Electric' : 'Non-Electric', 'url' => $removeFilterUrl('electric')];
}
if ($snow !== '') {
    $activeChips[] = ['label' => $snow === '1' ? 'Snow' : 'Non-Snow', 'url' => $removeFilterUrl('snow')];
}
?>

<section class="hs-section hs-section--archive">
    <header class="hs-archive-hero">
        <h1 class="hs-archive-hero__title"><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p class="hs-archive-hero__subtitle">Accessory catalog with compatibility metadata and feature tags. Browse by category or filter the full list below.</p>
    </header>

    <!-- Section 1: Category cards -->
    <div class="hs-accessories-categories">
        <h2 class="hs-accessories-categories__title">Browse by category</h2>
        <div class="hs-accessories-categories__grid">
            <?php
            if (is_array($categoryTerms)) :
                foreach ($categoryTerms as $term) :
                    if (! ($term instanceof WP_Term)) {
                        continue;
                    }
                    $link  = get_term_link($term);
                    $title = $term->name;
                    $desc  = $term->description ?: 'Explore ' . $term->name . '.';
                    $img   = $themeDir . '/assets/images/hubs/accessory_category/' . $term->slug . '.png';
                    if (! file_exists(get_stylesheet_directory() . '/assets/images/hubs/accessory_category/' . $term->slug . '.png')) {
                        $img = $heroImg;
                    }
                    ?>
                    <a href="<?php echo esc_url($link); ?>" class="hs-discovery-card">
                        <article class="hs-discovery-card__inner">
                            <div class="hs-discovery-card__image-container">
                                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" class="hs-discovery-card__image" loading="lazy">
                                <span class="hs-discovery-card__badge"><?php echo esc_html((string) $term->count); ?> Items</span>
                            </div>
                            <div class="hs-discovery-card__content">
                                <h3 class="hs-discovery-card__title"><?php echo esc_html($title); ?></h3>
                                <p class="hs-discovery-card__description"><?php echo esc_html($desc); ?></p>
                                <span class="hs-discovery-card__cta">View catalog <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M5 12h14m-7-7 7 7-7 7"/></svg></span>
                            </div>
                        </article>
                    </a>
                <?php endforeach;
            endif;
            ?>
        </div>
    </div>

    <!-- Section 2: All accessories with filters -->
    <div class="hs-accessories-catalog">
        <h2 class="hs-accessories-catalog__title">All accessories</h2>
        <div class="hs-catalog">
            <aside id="hsFilterPanel" class="hs-catalog__filters hs-panel" aria-label="Accessory filters">
                <div class="hs-catalog__filters-head">
                    <strong>Filters</strong>
                    <button type="button" class="hs-mobile-filter-close" data-close-filter aria-label="Close filters">Close</button>
                </div>
                <form id="hsAccessoryFilterForm" method="get" action="<?php echo esc_url($pageUrl); ?>">
                    <input type="hidden" name="paged" value="1" />
                    <details class="hs-filter-group" open>
                        <summary>Search</summary>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search accessories" />
                    </details>
                    <details class="hs-filter-group" open>
                        <summary>Accessory category</summary>
                        <div class="hs-filter-checks hs-filter-scroll">
                            <?php
                            if (is_array($categoryTerms)) :
                                foreach ($categoryTerms as $term) :
                                    if (! ($term instanceof WP_Term)) {
                                        continue;
                                    }
                                    ?>
                                    <label><input type="checkbox" name="accessory_category[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $categories, true)); ?> /> <?php echo esc_html($term->name); ?></label>
                                <?php endforeach;
                            endif;
                            ?>
                        </div>
                    </details>
                    <details class="hs-filter-group">
                        <summary>Helmet type compatibility</summary>
                        <div class="hs-filter-checks hs-filter-scroll">
                            <?php
                            if (is_array($helmetTypeTerms)) :
                                foreach ($helmetTypeTerms as $term) :
                                    if (! ($term instanceof WP_Term)) {
                                        continue;
                                    }
                                    ?>
                                    <label><input type="checkbox" name="helmet_type[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $helmetTypes, true)); ?> /> <?php echo esc_html($term->name); ?></label>
                                <?php endforeach;
                            endif;
                            ?>
                        </div>
                    </details>
                    <details class="hs-filter-group">
                        <summary>Pinlock ready</summary>
                        <div class="hs-filter-checks">
                            <label><input type="radio" name="pinlock_ready" value="" <?php checked($pinlock, ''); ?> /> Any</label>
                            <label><input type="radio" name="pinlock_ready" value="1" <?php checked($pinlock, '1'); ?> /> Yes</label>
                            <label><input type="radio" name="pinlock_ready" value="0" <?php checked($pinlock, '0'); ?> /> No</label>
                        </div>
                    </details>
                    <details class="hs-filter-group">
                        <summary>Electric compatible</summary>
                        <div class="hs-filter-checks">
                            <label><input type="radio" name="electric" value="" <?php checked($electric, ''); ?> /> Any</label>
                            <label><input type="radio" name="electric" value="1" <?php checked($electric, '1'); ?> /> Yes</label>
                            <label><input type="radio" name="electric" value="0" <?php checked($electric, '0'); ?> /> No</label>
                        </div>
                    </details>
                    <details class="hs-filter-group">
                        <summary>Snow compatible</summary>
                        <div class="hs-filter-checks">
                            <label><input type="radio" name="snow" value="" <?php checked($snow, ''); ?> /> Any</label>
                            <label><input type="radio" name="snow" value="1" <?php checked($snow, '1'); ?> /> Yes</label>
                            <label><input type="radio" name="snow" value="0" <?php checked($snow, '0'); ?> /> No</label>
                        </div>
                    </details>
                    <div class="hs-filter-actions">
                        <a class="hs-btn hs-btn--text" href="<?php echo esc_url($pageUrl); ?>">Clear all</a>
                        <button class="hs-btn hs-btn--primary" type="submit">Apply filters</button>
                    </div>
                </form>
            </aside>

            <div class="hs-catalog__results">
                <div class="hs-catalog__topbar hs-panel">
                    <div class="hs-catalog__count"><?php echo esc_html(number_format_i18n((int) $accessoryQuery->found_posts)); ?> Accessories</div>
                    <div class="hs-catalog__chips">
                        <?php foreach ($activeChips as $chip) : ?>
                            <a class="hs-chip" href="<?php echo esc_url((string) $chip['url']); ?>"><?php echo esc_html((string) $chip['label']); ?> <span aria-hidden="true">×</span></a>
                        <?php endforeach; ?>
                    </div>
                    <form class="hs-catalog__sort" method="get" action="<?php echo esc_url($pageUrl); ?>">
                        <?php
                        foreach ($currentQuery as $k => $v) {
                            if ($k === 'sort' || $k === 'paged') {
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
                        <label for="hsAccessorySort" class="screen-reader-text">Sort</label>
                        <select id="hsAccessorySort" name="sort" onchange="this.form.submit()">
                            <option value="title_asc" <?php selected($sort, 'title_asc'); ?>>Name A–Z</option>
                            <option value="title_desc" <?php selected($sort, 'title_desc'); ?>>Name Z–A</option>
                            <option value="newest" <?php selected($sort, 'newest'); ?>>Newest</option>
                        </select>
                    </form>
                </div>

                <?php if ($accessoryQuery->have_posts()) : ?>
                    <section class="hs-catalog__results-content">
                        <div class="helmet-grid">
                            <?php
                            while ($accessoryQuery->have_posts()) {
                                $accessoryQuery->the_post();
                                get_template_part('template-parts/entity', 'card');
                            }
                            ?>
                        </div>
                        <div class="hs-pagination-wrap">
                            <?php
                            global $wp_query;
                            $original_query = $wp_query;
                            $wp_query = $accessoryQuery; // phpcs:ignore

                            the_posts_pagination([
                                'total'     => $accessoryQuery->max_num_pages,
                                'current'   => $paged,
                                'add_args'  => $currentQuery,
                                'mid_size'  => 2,
                                'prev_text' => __('&larr; Prev', 'helmetsan-theme'),
                                'next_text' => __('Next &rarr;', 'helmetsan-theme'),
                                'screen_reader_text' => __('Accessories navigation', 'helmetsan-theme'),
                            ]);

                            $wp_query = $original_query;
                            ?>
                        </div>
                    </section>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <p class="hs-catalog__empty">No accessories match the selected filters. <a href="<?php echo esc_url($pageUrl); ?>">Clear filters</a> to see all.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="hs-mobile-tools" aria-label="Mobile catalog tools">
    <button type="button" data-open-filter aria-label="Open filters">Filter</button>
</div>

<?php
get_footer();
