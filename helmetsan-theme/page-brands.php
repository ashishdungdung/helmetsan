<?php
/**
 * Brands hub page template.
 *
 * @package HelmetsanTheme
 */

get_header();

$query = new WP_Query([
    'post_type' => 'brand',
    'post_status' => 'publish',
    'posts_per_page' => 12,
    'orderby' => 'title',
    'order' => 'ASC',
]);
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php the_title(); ?></h1>
        <p>Featured brand profiles from the Helmetsan brand directory.</p>
    </header>
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="hs-panel"><?php the_content(); ?></div>
    <?php endwhile; endif; ?>

    <?php if ($query->have_posts()) : ?>
        <div class="helmet-grid">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php get_template_part('template-parts/entity', 'card'); ?>
            <?php endwhile; ?>
        </div>
        <p><a class="hs-btn hs-btn--ghost" href="<?php echo esc_url(get_post_type_archive_link('brand')); ?>">Browse Full Brand Archive</a></p>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p>No brand profiles published yet.</p>
    <?php endif; ?>
</section>
<?php
get_footer();

