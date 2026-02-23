<?php
/**
 * Brand archive template.
 *
 * @package HelmetsanTheme
 */

$brandArchiveUrl = get_post_type_archive_link('brand');
if (! is_string($brandArchiveUrl) || $brandArchiveUrl === '') {
    $brandArchiveUrl = home_url('/brands/');
}
// Redirect to clean URL when all query params are empty.
$allEmpty = true;
foreach (['s', 'country', 'helmet_type', 'cert'] as $key) {
    if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
        $allEmpty = false;
        break;
    }
}
// Only redirect when stripping empty query params; avoid loop when already on clean URL.
if ($allEmpty && (! isset($_GET['paged']) || (int) $_GET['paged'] <= 1) && ! empty($_GET)) {
    wp_safe_redirect($brandArchiveUrl, 302);
    exit;
}

get_header();

$search = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
$country = isset($_GET['country']) ? sanitize_text_field((string) $_GET['country']) : '';
$helmetType = isset($_GET['helmet_type']) ? sanitize_text_field((string) $_GET['helmet_type']) : '';
$cert = isset($_GET['cert']) ? sanitize_text_field((string) $_GET['cert']) : '';

$filterSource = get_posts([
    'post_type' => 'brand',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
]);
$countries = [];
$helmetTypes = get_terms([
    'taxonomy' => 'helmet_type',
    'hide_empty' => false,
]);
$certs = [];
foreach ($filterSource as $brandPost) {
    if (! ($brandPost instanceof WP_Post)) {
        continue;
    }
    $origin = trim((string) get_post_meta($brandPost->ID, 'brand_origin_country', true));
    if ($origin !== '') {
        $countries[$origin] = $origin;
    }
    foreach (explode(',', (string) get_post_meta($brandPost->ID, 'brand_certification_coverage', true)) as $item) {
        $item = trim($item);
        if ($item !== '') {
            $certs[$item] = $item;
        }
    }
}
ksort($countries, SORT_NATURAL | SORT_FLAG_CASE);
ksort($certs, SORT_NATURAL | SORT_FLAG_CASE);

$themeDir = get_stylesheet_directory_uri();
?>

<section class="hs-section hs-section--archive">
    <header class="hs-archive-hero">
        <h1 class="hs-archive-hero__title"><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p class="hs-archive-hero__subtitle">Explore helmet brands with profile metadata, origin, and model coverage.</p>
    </header>

    <div class="hs-catalog hs-catalog--brands">
        <aside class="hs-catalog__filters hs-panel" aria-label="Brand filters">
            <div class="hs-catalog__filters-head">
                <strong>Filters</strong>
            </div>
            <form class="hs-filter-bar hs-filter-bar--stacked" method="get" action="<?php echo esc_url(get_post_type_archive_link('brand')); ?>">
                <label class="hs-filter-bar__label">Search</label>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search brands" />
                <label class="hs-filter-bar__label">Country</label>
                <select name="country">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($country, $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="hs-filter-bar__label">Helmet type</label>
                <select name="helmet_type">
                    <option value="">All Helmet Types</option>
                    <?php
                    if (is_array($helmetTypes)) :
                        foreach ($helmetTypes as $term) :
                            if (! ($term instanceof WP_Term)) {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($helmetType, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php
                        endforeach;
                    endif;
                    ?>
                </select>
                <label class="hs-filter-bar__label">Certification</label>
                <select name="cert">
                    <option value="">All Certification Marks</option>
                    <?php foreach ($certs as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($cert, $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="hs-filter-actions">
                    <a class="hs-btn hs-btn--text" href="<?php echo esc_url($brandArchiveUrl); ?>">Clear all</a>
                    <button class="hs-btn hs-btn--primary" type="submit">Apply</button>
                </div>
            </form>
        </aside>
        <div class="hs-catalog__results">
    <?php
    $args = [
        'post_type' => 'brand',
        'post_status' => 'publish',
        'posts_per_page' => 18,
        'paged' => max(1, get_query_var('paged')),
        'orderby' => 'title',
        'order' => 'ASC',
    ];
    if ($search !== '') {
        $args['s'] = $search;
    }
    $metaQuery = [];
    if ($country !== '') {
        $metaQuery[] = ['key' => 'brand_origin_country', 'value' => $country];
    }
    if ($helmetType !== '') {
        $args['tax_query'] = [[
            'taxonomy' => 'helmet_type',
            'field' => 'slug',
            'terms' => $helmetType,
        ]];
    }
    if ($cert !== '') {
        $metaQuery[] = ['key' => 'brand_certification_coverage', 'value' => $cert, 'compare' => 'LIKE'];
    }
    if ($metaQuery !== []) {
        $args['meta_query'] = $metaQuery;
    }
    $query = new WP_Query($args);
    if ($query->have_posts()) : ?>
            <div class="hs-catalog__topbar hs-panel">
                <div class="hs-catalog__count"><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?> Brands</div>
            </div>
            <section class="hs-catalog__results-content">
            <div class="helmet-grid hs-brand-grid">
                <?php
                while ($query->have_posts()) : $query->the_post();
                    $brandId = get_the_ID();
                    $logoUrl = helmetsan_get_logo_url($brandId);
                    $helmetCount = helmetsan_get_brand_helmet_count($brandId);
                    ?>
                    <article <?php post_class('entity-card hs-panel'); ?>>
                        <?php if ($logoUrl !== '') : ?>
                            <div class="entity-card__logo-wrap">
                                <img class="entity-card__logo" src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                            </div>
                        <?php endif; ?>
                        <h3 class="entity-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <div class="entity-card__excerpt"><?php echo wp_trim_words(get_the_excerpt(), 20); ?></div>
                        <div class="entity-card__meta">
                            <code><?php printf(esc_html__('%d Helmets', 'helmetsan-theme'), $helmetCount); ?></code>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            <div class="hs-pagination-wrap">
                <?php
                $addArgs = [];
                if (isset($_GET['s'])) {
                    $addArgs['s'] = sanitize_text_field(wp_unslash((string) $_GET['s']));
                }
                if (isset($_GET['country'])) {
                    $addArgs['country'] = sanitize_text_field(wp_unslash((string) $_GET['country']));
                }
                if (isset($_GET['helmet_type'])) {
                    $addArgs['helmet_type'] = sanitize_text_field(wp_unslash((string) $_GET['helmet_type']));
                }
                if (isset($_GET['cert'])) {
                    $addArgs['cert'] = sanitize_text_field(wp_unslash((string) $_GET['cert']));
                }
                the_posts_pagination([
                    'total' => $query->max_num_pages,
                    'add_args' => array_filter($addArgs, static fn(string $v): bool => $v !== ''),
                ]);
                ?>
            </div>
            </section>
            <?php wp_reset_postdata(); ?>
    <?php else : ?>
            <p class="hs-catalog__empty">No brands match the filters. <a href="<?php echo esc_url($brandArchiveUrl); ?>">Clear filters</a>.</p>
    <?php endif; ?>
        </div>
    </div>
</section>

<?php
get_footer();
