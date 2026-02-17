<?php
/**
 * Main template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        ?>
        <article <?php post_class(); ?>>
            <h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
            <div><?php the_excerpt(); ?></div>
        </article>
        <?php
    }

    the_posts_pagination();
} else {
    echo '<p>' . esc_html__('No content found.', 'helmetsan-theme') . '</p>';
}

get_footer();
