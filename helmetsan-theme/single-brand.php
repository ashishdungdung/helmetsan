<?php
/**
 * Single brand template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();

        $brandId = get_the_ID();
        $origin = (string) get_post_meta($brandId, 'brand_origin_country', true);
        $warranty = (string) get_post_meta($brandId, 'brand_warranty_terms', true);
        $supportUrl = (string) get_post_meta($brandId, 'brand_support_url', true);
        $supportEmail = (string) get_post_meta($brandId, 'brand_support_email', true);
        $totalModels = (string) get_post_meta($brandId, 'brand_total_models', true);
        $helmetTypes = (string) get_post_meta($brandId, 'brand_helmet_types', true);
        $certCoverage = (string) get_post_meta($brandId, 'brand_certification_coverage', true);

        $helmets = new WP_Query([
            'post_type' => 'helmet',
            'post_status' => 'publish',
            'posts_per_page' => 24,
            'meta_query' => [
                [
                    'key' => 'rel_brand',
                    'value' => $brandId,
                ],
            ],
        ]);
        ?>
        <article <?php post_class('brand-single hs-section'); ?>>
            <header class="hs-section__head">
                <p class="hs-eyebrow">Brand Profile</p>
                <h1><?php the_title(); ?></h1>
            </header>

            <section class="hs-stat-grid">
                <article class="hs-stat-card"><span>Origin</span><strong><?php echo esc_html($origin !== '' ? $origin : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Warranty</span><strong><?php echo esc_html($warranty !== '' ? $warranty : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Support</span><strong><?php echo esc_html($supportEmail !== '' ? $supportEmail : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Total Models</span><strong><?php echo esc_html($totalModels !== '' ? $totalModels : 'N/A'); ?></strong></article>
            </section>

            <div class="hs-panel">
                <?php the_content(); ?>
                <?php if ($supportUrl !== '') : ?>
                    <p><a class="hs-link" href="<?php echo esc_url($supportUrl); ?>" target="_blank" rel="noopener noreferrer">Official support</a></p>
                <?php endif; ?>
                <?php if ($helmetTypes !== '') : ?>
                    <p><strong>Helmet Types:</strong> <?php echo esc_html($helmetTypes); ?></p>
                <?php endif; ?>
                <?php if ($certCoverage !== '') : ?>
                    <p><strong>Certification Coverage:</strong> <?php echo esc_html($certCoverage); ?></p>
                <?php endif; ?>
            </div>

            <section class="hs-section">
                <div class="hs-section__head"><h2>Helmets by <?php the_title(); ?></h2></div>
                <?php
                if ($helmets->have_posts()) {
                    echo '<div class="helmet-grid">';
                    while ($helmets->have_posts()) {
                        $helmets->the_post();
                        get_template_part('template-parts/helmet', 'card');
                    }
                    echo '</div>';
                    wp_reset_postdata();
                } else {
                    echo '<p>No linked helmets yet.</p>';
                }
                ?>
            </section>
        </article>
        <?php
    }
}

get_footer();
