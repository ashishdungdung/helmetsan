<?php
/**
 * Front page template.
 *
 * @package HelmetsanTheme
 */

get_header();

// Use direct DB counts so homepage stats are always current (avoids object-cache staleness vs wp_count_posts).
global $wpdb;
$helmetCount    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'helmet'));
$brandCount     = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'brand'));
$accessoryCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'accessory'));
$motorcycleCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'motorcycle'));
$dealerCount    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'dealer'));

$helmetsUrl    = get_post_type_archive_link('helmet') ?: home_url('/helmets/');
$brandsUrl     = get_post_type_archive_link('brand') ?: home_url('/brands/');
$accessoriesUrl = get_post_type_archive_link('accessory') ?: home_url('/accessories/');
$motorcyclesUrl = get_post_type_archive_link('motorcycle') ?: home_url('/motorcycles/');
$safetyUrl     = home_url('/safety/');
?>
<section class="hs-hero">
    <div class="hs-hero__gradient"></div>
    <div class="hs-hero__content">
        <p class="hs-eyebrow">Global Helmet Intelligence Platform</p>
        <h1>Find the Right Helmet. Compare Specs. Stay Compliant.</h1>
        <p>Search helmets by brand, type, and certification. Explore accessories, compatibility, and safety standards in one place.</p>
        <div class="hs-hero__actions">
            <a class="hs-btn hs-btn--primary" href="<?php echo esc_url($helmetsUrl); ?>">Explore Helmets</a>
            <a class="hs-btn hs-btn--ghost" href="<?php echo esc_url($accessoriesUrl); ?>">Browse Accessories</a>
            <?php if (current_user_can('manage_options')) : ?>
                <a class="hs-btn hs-btn--ghost" href="<?php echo esc_url(admin_url('admin.php?page=helmetsan-go-live')); ?>">Readiness Gate</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="hs-stat-grid">
    <a class="hs-stat-card hs-stat-card--link" href="<?php echo esc_url($helmetsUrl); ?>"><span>Helmets</span><strong><?php echo esc_html((string) $helmetCount); ?></strong></a>
    <a class="hs-stat-card hs-stat-card--link" href="<?php echo esc_url($brandsUrl); ?>"><span>Brands</span><strong><?php echo esc_html((string) $brandCount); ?></strong></a>
    <a class="hs-stat-card hs-stat-card--link" href="<?php echo esc_url($accessoriesUrl); ?>"><span>Accessories</span><strong><?php echo esc_html((string) $accessoryCount); ?></strong></a>
    <a class="hs-stat-card hs-stat-card--link" href="<?php echo esc_url($motorcyclesUrl); ?>"><span>Motorcycles</span><strong><?php echo esc_html((string) $motorcycleCount); ?></strong></a>
    <?php if ($dealerCount > 0) : ?>
        <a class="hs-stat-card hs-stat-card--link" href="<?php echo esc_url(get_post_type_archive_link('dealer') ?: home_url('/dealers/')); ?>"><span>Dealers</span><strong><?php echo esc_html((string) $dealerCount); ?></strong></a>
    <?php endif; ?>
</section>

<nav class="hs-home-quick-links" aria-label="Quick explore">
    <span class="hs-home-quick-links__label">Explore:</span>
    <a href="<?php echo esc_url($helmetsUrl); ?>">Helmets</a>
    <a href="<?php echo esc_url($brandsUrl); ?>">Brands</a>
    <a href="<?php echo esc_url($accessoriesUrl); ?>">Accessories</a>
    <a href="<?php echo esc_url($motorcyclesUrl); ?>">Motorcycles</a>
    <a href="<?php echo esc_url($safetyUrl); ?>">Safety Standards</a>
</nav>

<?php
$helmetQuery = new WP_Query([
    'post_type'      => 'helmet',
    'posts_per_page' => 12,
    'post_status'    => 'publish',
    'orderby'        => 'date',
]);
if ($helmetQuery->have_posts()) :
?>
<section class="hs-section">
    <div class="hs-section__head">
        <h2>Featured Helmets</h2>
        <a class="hs-section__view-all" href="<?php echo esc_url($helmetsUrl); ?>">View all &rarr;</a>
    </div>
    <div class="helmet-grid">
        <?php
        while ($helmetQuery->have_posts()) {
            $helmetQuery->the_post();
            get_template_part('template-parts/helmet', 'card');
        }
        wp_reset_postdata();
        ?>
    </div>
</section>
<?php endif; ?>

<?php
$brandQuery = new WP_Query([
    'post_type'      => 'brand',
    'posts_per_page' => 8,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
]);
if ($brandQuery->have_posts()) :
?>
<section class="hs-section">
    <div class="hs-section__head">
        <h2>Brands</h2>
        <a class="hs-section__view-all" href="<?php echo esc_url($brandsUrl); ?>">View all &rarr;</a>
    </div>
    <div class="hs-home-brand-grid">
        <?php
        while ($brandQuery->have_posts()) {
            $brandQuery->the_post();
            get_template_part('template-parts/entity', 'card');
        }
        wp_reset_postdata();
        ?>
    </div>
</section>
<?php endif; ?>

<section class="hs-section hs-home-cta">
    <div class="hs-home-cta__inner">
        <div class="hs-home-cta__content">
            <h2>Accessories &amp; Compatibility</h2>
            <p>Visors, communication systems, Pinlock inserts, and more—with compatibility metadata so you can match gear to your helmet.</p>
            <a class="hs-btn hs-btn--primary" href="<?php echo esc_url($accessoriesUrl); ?>">Explore Accessories</a>
        </div>
    </div>
</section>

<?php
$safetyPage = get_page_by_path('safety') ?: get_page_by_path('safety-standards');
if ($safetyPage || get_post_type_archive_link('safety_standard')) :
    $safetyLink = $safetyPage ? get_permalink($safetyPage) : home_url('/safety/');
?>
<section class="hs-section hs-home-safety-teaser">
    <div class="hs-home-safety-teaser__inner hs-panel">
        <h2>Safety &amp; Certifications</h2>
        <p>Understand ECE, DOT, SHARP, and other standards. See which helmets meet your region&rsquo;s requirements.</p>
        <a class="hs-btn hs-btn--ghost" href="<?php echo esc_url($safetyLink); ?>">Safety Standards</a>
    </div>
</section>
<?php endif; ?>

<?php
get_footer();
