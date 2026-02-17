<?php
/**
 * Dealers page template.
 *
 * @package HelmetsanTheme
 */

get_header();

$query = new WP_Query([
    'post_type' => 'dealer',
    'post_status' => 'publish',
    'posts_per_page' => 60,
]);
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php the_title(); ?></h1>
        <p>Dealer and retail listing index.</p>
    </header>

    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="hs-panel"><?php the_content(); ?></div>
    <?php endwhile; endif; ?>

    <?php
    if ($query->have_posts()) {
        echo '<div class="helmet-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            get_template_part('template-parts/dealer', 'card');
        }
        echo '</div>';
        wp_reset_postdata();
    }
    ?>
</section>
<?php
get_footer();
