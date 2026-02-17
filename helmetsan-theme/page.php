<?php
/**
 * Generic page template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        ?>
        <article <?php post_class('hs-section'); ?>>
            <header class="hs-section__head">
                <h1><?php the_title(); ?></h1>
            </header>
            <div class="hs-panel page-content">
                <?php the_content(); ?>
            </div>
        </article>
        <?php
    }
}

get_footer();

