<?php
/**
 * Geo pricing hub page template.
 *
 * @package HelmetsanTheme
 */

get_header();

$query = new WP_Query([
    'post_type' => 'helmet',
    'post_status' => 'publish',
    'posts_per_page' => 12,
    'meta_query' => [
        [
            'key' => 'geo_pricing_json',
            'compare' => 'EXISTS',
        ],
    ],
]);
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php the_title(); ?></h1>
        <p>Helmets with country-linked pricing and availability metadata.</p>
    </header>
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="hs-panel"><?php the_content(); ?></div>
    <?php endwhile; endif; ?>

    <?php if ($query->have_posts()) : ?>
        <div class="helmet-grid">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php get_template_part('template-parts/helmet', 'card'); ?>
            <?php endwhile; ?>
        </div>
        <p><a class="hs-btn hs-btn--ghost" href="<?php echo esc_url(get_post_type_archive_link('helmet')); ?>">Browse Helmet Archive</a></p>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p>No helmets with geo pricing metadata yet.</p>
    <?php endif; ?>
</section>
<?php
get_footer();

