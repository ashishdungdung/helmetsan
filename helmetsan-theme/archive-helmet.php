<?php
/**
 * Helmet archive template.
 *
 * @package HelmetsanTheme
 */

get_header();

$certTerms = get_terms([
    'taxonomy' => 'certification',
    'hide_empty' => true,
]);

$brandPosts = get_posts([
    'post_type' => 'brand',
    'post_status' => 'publish',
    'posts_per_page' => 100,
    'orderby' => 'title',
    'order' => 'ASC',
]);
?>
<section class="hs-section hs-section--archive">
    <div class="hs-section__head">
        <h1><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p>Compare technical specifications and certification standards.</p>
    </div>

    <form class="hs-filter-bar" method="get">
        <select name="certification">
            <option value=""><?php esc_html_e('All Certifications', 'helmetsan-theme'); ?></option>
            <?php
            if (is_array($certTerms)) {
                foreach ($certTerms as $term) {
                    if (! ($term instanceof WP_Term)) {
                        continue;
                    }
                    $selected = isset($_GET['certification']) ? sanitize_text_field((string) $_GET['certification']) : '';
                    echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
                }
            }
            ?>
        </select>
        <select name="brand">
            <option value=""><?php esc_html_e('All Brands', 'helmetsan-theme'); ?></option>
            <?php
            foreach ($brandPosts as $brand) {
                if (! ($brand instanceof WP_Post)) {
                    continue;
                }
                $selected = isset($_GET['brand']) ? (int) $_GET['brand'] : 0;
                echo '<option value="' . esc_attr((string) $brand->ID) . '" ' . selected($selected, (int) $brand->ID, false) . '>' . esc_html($brand->post_title) . '</option>';
            }
            ?>
        </select>
        <button class="hs-btn hs-btn--primary" type="submit"><?php esc_html_e('Apply', 'helmetsan-theme'); ?></button>
    </form>

    <?php
    $args = [
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'posts_per_page' => 18,
        'paged' => max(1, get_query_var('paged')),
    ];

    $taxQuery = [];
    if (! empty($_GET['certification'])) {
        $taxQuery[] = [
            'taxonomy' => 'certification',
            'field' => 'slug',
            'terms' => sanitize_text_field((string) $_GET['certification']),
        ];
    }
    if ($taxQuery !== []) {
        $args['tax_query'] = $taxQuery;
    }

    if (! empty($_GET['brand']) && (int) $_GET['brand'] > 0) {
        $args['meta_query'] = [
            [
                'key' => 'rel_brand',
                'value' => (int) $_GET['brand'],
            ],
        ];
    }

    $q = new WP_Query($args);

    if ($q->have_posts()) {
        echo '<div class="helmet-grid">';
        while ($q->have_posts()) {
            $q->the_post();
            get_template_part('template-parts/helmet', 'card');
        }
        echo '</div>';

        the_posts_pagination([
            'total' => $q->max_num_pages,
        ]);
        wp_reset_postdata();
    } else {
        echo '<p>' . esc_html__('No helmets found.', 'helmetsan-theme') . '</p>';
    }
    ?>
</section>
<?php
get_footer();
