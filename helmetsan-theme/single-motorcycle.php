<?php
/**
 * Single motorcycle template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        $id = get_the_ID();
        $make = (string) get_post_meta($id, 'motorcycle_make', true);
        $model = (string) get_post_meta($id, 'motorcycle_model', true);
        $segment = (string) get_post_meta($id, 'bike_segment', true);
        $engine = (string) get_post_meta($id, 'engine_cc', true);
        $recommendedJson = (string) get_post_meta($id, 'recommended_helmet_types_json', true);
        $recommended = json_decode($recommendedJson, true);

        $relatedHelmets = [];
        if (is_array($recommended) && $recommended !== []) {
            $relatedHelmets = get_posts([
                'post_type' => 'helmet',
                'post_status' => 'publish',
                'posts_per_page' => 8,
                'tax_query' => [
                    [
                        'taxonomy' => 'helmet_type',
                        'field' => 'slug',
                        'terms' => array_map('sanitize_title', array_map('strval', $recommended)),
                    ],
                ],
            ]);
        }
        ?>
        <article <?php post_class('hs-section'); ?>>
            <header class="hs-section__head">
                <p class="hs-eyebrow">Motorcycle Compatibility</p>
                <h1><?php the_title(); ?></h1>
            </header>

            <section class="hs-stat-grid">
                <article class="hs-stat-card"><span>Make</span><strong><?php echo esc_html($make !== '' ? $make : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Segment</span><strong><?php echo esc_html($segment !== '' ? $segment : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Engine</span><strong><?php echo esc_html($engine !== '' ? $engine . ' cc' : 'N/A'); ?></strong></article>
            </section>

            <div class="hs-panel"><?php the_content(); ?></div>

            <section class="hs-panel">
                <h2>Recommended Helmet Types</h2>
                <ul class="hs-list">
                    <?php if (is_array($recommended)) : foreach ($recommended as $item) : ?>
                        <li><?php echo esc_html((string) $item); ?></li>
                    <?php endforeach; else : ?>
                        <li>No recommendation data yet.</li>
                    <?php endif; ?>
                </ul>
            </section>

            <?php if ($relatedHelmets !== []) : ?>
                <section class="hs-panel">
                    <h2>Suggested Helmets for This Segment</h2>
                    <div class="helmet-grid">
                        <?php foreach ($relatedHelmets as $post) : setup_postdata($post); get_template_part('template-parts/helmet', 'card'); endforeach; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
        <?php
    }
}

get_footer();

