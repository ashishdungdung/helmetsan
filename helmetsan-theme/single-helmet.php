<?php
/**
 * Single helmet template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();

        $helmetId = get_the_ID();
        $brandId = helmetsan_get_brand_id($helmetId);
        $brandName = helmetsan_get_brand_name($helmetId);
        $related = helmetsan_get_related_helmets_by_brand($helmetId, 6);
        $weight = (string) get_post_meta($helmetId, 'spec_weight_g', true);
        $shell = (string) get_post_meta($helmetId, 'spec_shell_material', true);
        $price = helmetsan_get_helmet_price($helmetId);
        $certs = helmetsan_get_certifications($helmetId);
        ?>
        <article <?php post_class('helmet-single hs-section'); ?>>
            <header class="helmet-single__header hs-section__head">
                <p class="hs-eyebrow"><?php echo esc_html($brandName !== '' ? $brandName : 'Helmet'); ?></p>
                <h1><?php the_title(); ?></h1>
                <div class="helmet-single__meta-chip">
                    <span><?php echo esc_html($price); ?></span>
                    <span><?php echo esc_html($certs); ?></span>
                </div>
            </header>

            <div class="helmet-single__layout">
                <div class="helmet-single__media hs-panel">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="helmet-single__image"><?php the_post_thumbnail('large'); ?></div>
                    <?php else : ?>
                        <div class="helmet-single__placeholder">No image available</div>
                    <?php endif; ?>
                </div>

                <aside class="helmet-single__aside hs-panel">
                    <h2>Key Specs</h2>
                    <dl class="helmetsan-specs-grid">
                        <div class="helmetsan-specs-row"><dt>Weight</dt><dd><?php echo esc_html($weight !== '' ? $weight . ' g' : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Shell</dt><dd><?php echo esc_html($shell !== '' ? $shell : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Certifications</dt><dd><?php echo esc_html($certs); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Price</dt><dd><?php echo esc_html($price); ?></dd></div>
                    </dl>

                    <?php get_template_part('template-parts/helmet', 'cta'); ?>

                    <?php if ($brandId > 0) : ?>
                        <p><a class="hs-link" href="<?php echo esc_url(get_permalink($brandId)); ?>">View brand profile</a></p>
                    <?php endif; ?>
                </aside>
            </div>

            <?php get_template_part('template-parts/legal', 'warning'); ?>

            <div class="helmet-single__content hs-panel">
                <h2>Technical Analysis</h2>
                <?php the_content(); ?>
            </div>

            <?php if ($related !== []) : ?>
                <section class="hs-panel">
                    <h2>More from <?php echo esc_html($brandName); ?></h2>
                    <div class="helmet-grid">
                        <?php foreach ($related as $post) : setup_postdata($post); get_template_part('template-parts/helmet', 'card'); endforeach; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
        <?php
    }
}

get_footer();
