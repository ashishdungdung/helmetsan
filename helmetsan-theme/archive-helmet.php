<?php
/**
 * Helmet archive template.
 *
 * @package HelmetsanTheme
 */

get_header();

echo '<header><h1>' . esc_html(post_type_archive_title('', false)) . '</h1></header>';

if (have_posts()) {
    echo '<section class="helmet-grid">';
    while (have_posts()) {
        the_post();
        get_template_part('template-parts/helmet', 'card');
    }
    echo '</section>';

    the_posts_pagination();
} else {
    echo '<p>' . esc_html__('No helmets found.', 'helmetsan-theme') . '</p>';
}

get_footer();
