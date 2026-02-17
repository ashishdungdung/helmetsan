<?php
/**
 * Accessory archive template.
 *
 * @package HelmetsanTheme
 */

get_header();
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p>Accessory catalog with compatibility metadata and feature tags.</p>
    </header>
    <?php if (have_posts()) : ?>
        <div class="helmet-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php get_template_part('template-parts/entity', 'card'); ?>
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>No accessories found.</p>
    <?php endif; ?>
</section>
<?php
get_footer();

