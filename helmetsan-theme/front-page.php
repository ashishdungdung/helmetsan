<?php
/**
 * Front page template.
 *
 * @package HelmetsanTheme
 */

get_header();

$helmetCount = (int) wp_count_posts('helmet')->publish;
$brandCount = (int) wp_count_posts('brand')->publish;
$dealerCount = (int) wp_count_posts('dealer')->publish;
?>
<section class="hs-hero">
    <div class="hs-hero__gradient"></div>
    <div class="hs-hero__content">
        <p class="hs-eyebrow">Helmetsan Directory</p>
        <h1>Safety Gear Intelligence, Optimized for Speed</h1>
        <p>Explore helmet specifications, compliance signals, brand relationships, and market links in one connected repository.</p>
        <div class="hs-hero__actions">
            <a class="hs-btn hs-btn--primary" href="<?php echo esc_url(get_post_type_archive_link('helmet')); ?>">Explore Helmets</a>
            <a class="hs-btn hs-btn--ghost" href="<?php echo esc_url(admin_url('admin.php?page=helmetsan-go-live')); ?>">Readiness Gate</a>
        </div>
    </div>
</section>

<section class="hs-stat-grid">
    <article class="hs-stat-card"><span>Helmets</span><strong><?php echo esc_html((string) $helmetCount); ?></strong></article>
    <article class="hs-stat-card"><span>Brands</span><strong><?php echo esc_html((string) $brandCount); ?></strong></article>
    <article class="hs-stat-card"><span>Dealers</span><strong><?php echo esc_html((string) $dealerCount); ?></strong></article>
</section>

<?php
$query = new WP_Query([
    'post_type'      => 'helmet',
    'posts_per_page' => 12,
    'post_status'    => 'publish',
]);

if ($query->have_posts()) {
    echo '<section class="hs-section"><div class="hs-section__head"><h2>Featured Helmets</h2></div><div class="helmet-grid">';
    while ($query->have_posts()) {
        $query->the_post();
        get_template_part('template-parts/helmet', 'card');
    }
    echo '</div></section>';
    wp_reset_postdata();
}

get_footer();
