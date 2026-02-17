<?php
/**
 * Front page template.
 *
 * @package HelmetsanTheme
 */

get_header();
?>
<section class="home-hero">
    <h1><?php bloginfo('name'); ?></h1>
    <p><?php bloginfo('description'); ?></p>
</section>

<?php
$query = new WP_Query([
    'post_type'      => 'helmet',
    'posts_per_page' => 12,
]);

if ($query->have_posts()) {
    echo '<section class="helmet-grid">';
    while ($query->have_posts()) {
        $query->the_post();
        get_template_part('template-parts/helmet', 'card');
    }
    echo '</section>';
    wp_reset_postdata();
}

get_footer();
