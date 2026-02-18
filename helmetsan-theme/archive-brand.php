<?php
/**
 * Brand archive template.
 *
 * @package HelmetsanTheme
 */

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
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p>Explore helmet brands with profile metadata and model coverage.</p>
    </header>
    <form class="hs-filter-bar" method="get" action="<?php echo esc_url(get_post_type_archive_link('brand')); ?>">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search brands" />
        <select name="country">
            <option value="">All Countries</option>
            <?php foreach ($countries as $value) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($country, $value); ?>><?php echo esc_html($value); ?></option>
            <?php endforeach; ?>
        </select>
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
        <select name="cert">
            <option value="">All Certification Marks</option>
            <?php foreach ($certs as $value) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($cert, $value); ?>><?php echo esc_html($value); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="hs-btn hs-btn--primary" type="submit">Apply</button>
    </form>
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
        <div class="helmet-grid">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php get_template_part('template-parts/entity', 'card'); ?>
            <?php endwhile; ?>
        </div>
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
        wp_reset_postdata();
        ?>
    <?php else : ?>
        <p>No brands found.</p>
    <?php endif; ?>
</section>
<?php
get_footer();
