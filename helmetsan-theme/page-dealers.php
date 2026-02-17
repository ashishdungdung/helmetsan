<?php
/**
 * Dealers page template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        ?>
        <article <?php post_class('dealers-page'); ?>>
            <h1><?php the_title(); ?></h1>
            <div><?php the_content(); ?></div>
        </article>
        <?php
    }
}

get_footer();
