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
        ?>
        <article <?php post_class('helmet-single'); ?>>
            <header class="helmet-single__header">
                <h1><?php the_title(); ?></h1>
            </header>

            <?php if (has_post_thumbnail()) : ?>
                <div class="helmet-single__image"><?php the_post_thumbnail('large'); ?></div>
            <?php endif; ?>

            <?php get_template_part('template-parts/legal', 'warning'); ?>
            <?php get_template_part('template-parts/helmet', 'specs'); ?>
            <?php get_template_part('template-parts/helmet', 'cta'); ?>

            <div class="helmet-single__content"><?php the_content(); ?></div>
        </article>
        <?php
    }
}

get_footer();
