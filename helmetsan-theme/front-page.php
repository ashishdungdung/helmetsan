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
    <div class="hs-hero__content hs-hero__content--centered">
        <p class="hs-eyebrow"><?php esc_html_e('Global Helmet Intelligence Platform', 'helmetsan-theme'); ?></p>
        <h1 class="hs-hero__title"><?php esc_html_e('Find the Right Helmet. Compare Specs. Stay Compliant.', 'helmetsan-theme'); ?></h1>
        <p class="hs-hero__desc"><?php esc_html_e('Search helmets by brand, type, and certification. Explore accessories, compatibility, and safety standards in one place.', 'helmetsan-theme'); ?></p>
        <div class="hs-hero__actions hs-hero__actions--centered">
            <a class="hs-btn hs-btn--primary" href="<?php echo esc_url($helmetsUrl); ?>"><?php esc_html_e('Explore Helmets', 'helmetsan-theme'); ?></a>
            <a class="hs-btn hs-btn--ghost" href="<?php echo esc_url($accessoriesUrl); ?>"><?php esc_html_e('Browse Accessories', 'helmetsan-theme'); ?></a>
            <?php if (current_user_can('manage_options')) : ?>
                <a class="hs-btn hs-btn--ghost hs-btn--readiness" href="<?php echo esc_url(admin_url('admin.php?page=helmetsan-go-live')); ?>"><?php esc_html_e('Readiness Gate', 'helmetsan-theme'); ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
$featuredHelmetQuery = new WP_Query([
    'post_type'      => 'helmet',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
    'orderby'        => 'rand',
    'meta_query'     => [
        [
            'key'     => '_thumbnail_id',
            'compare' => 'EXISTS',
        ],
    ],
]);
if ($featuredHelmetQuery->have_posts()) :
    $featuredHelmetQuery->the_post();
    get_template_part('template-parts/featured-helmet', 'hero');
    wp_reset_postdata();
else :
    $fallbackHelmet = new WP_Query([
        'post_type'      => 'helmet',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
    ]);
    if ($fallbackHelmet->have_posts()) {
        $fallbackHelmet->the_post();
        get_template_part('template-parts/featured-helmet', 'hero');
        wp_reset_postdata();
    }
endif;
?>

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
        <h2><?php esc_html_e('Featured Helmets', 'helmetsan-theme'); ?></h2>
        <a class="hs-section__view-all" href="<?php echo esc_url($helmetsUrl); ?>"><?php esc_html_e('View all', 'helmetsan-theme'); ?> &rarr;</a>
    </div>
    <p class="hs-section__intro"><?php esc_html_e('Hand-picked models to compare specs, certifications, and prices. Add any helmet to the comparison tool to see them side by side.', 'helmetsan-theme'); ?></p>
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
<section class="hs-section" aria-labelledby="home-brands-heading">
    <div class="hs-section__head">
        <h2 id="home-brands-heading"><?php esc_html_e('Brands', 'helmetsan-theme'); ?></h2>
        <a class="hs-section__view-all" href="<?php echo esc_url($brandsUrl); ?>"><?php esc_html_e('View all', 'helmetsan-theme'); ?> &rarr;</a>
    </div>
    <p class="hs-section__intro"><?php esc_html_e('Shop by manufacturer. From premium heritage brands to value leaders—explore origins, helmet types, and full catalogs.', 'helmetsan-theme'); ?></p>
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

<section class="hs-section hs-home-cta" aria-labelledby="home-accessories-heading">
    <div class="hs-home-cta__inner">
        <div class="hs-home-cta__content">
            <p class="hs-home-cta__eyebrow"><?php esc_html_e('Gear & fit', 'helmetsan-theme'); ?></p>
            <h2 id="home-accessories-heading"><?php esc_html_e('Accessories &amp; Compatibility', 'helmetsan-theme'); ?></h2>
            <p><?php esc_html_e('Visors, communication systems, Pinlock inserts, and more—with compatibility metadata so you can match gear to your helmet.', 'helmetsan-theme'); ?></p>
            <a class="hs-btn hs-btn--primary" href="<?php echo esc_url($accessoriesUrl); ?>"><?php esc_html_e('Explore Accessories', 'helmetsan-theme'); ?></a>
        </div>
    </div>
</section>

<?php
$safetyPage = get_page_by_path('safety') ?: get_page_by_path('safety-standards');
if ($safetyPage || get_post_type_archive_link('safety_standard')) :
    $safetyLink = $safetyPage ? get_permalink($safetyPage) : home_url('/safety/');
?>
<section class="hs-section hs-home-safety-teaser" aria-labelledby="home-safety-heading">
    <div class="hs-home-safety-teaser__inner hs-panel">
        <p class="hs-home-safety-teaser__eyebrow"><?php esc_html_e('Peace of mind', 'helmetsan-theme'); ?></p>
        <h2 id="home-safety-heading"><?php esc_html_e('Safety &amp; Certifications', 'helmetsan-theme'); ?></h2>
        <p><?php esc_html_e('Understand ECE, DOT, SHARP, and other standards. See which helmets meet your region&rsquo;s requirements.', 'helmetsan-theme'); ?></p>
        <a class="hs-btn hs-btn--ghost" href="<?php echo esc_url($safetyLink); ?>"><?php esc_html_e('Safety Standards', 'helmetsan-theme'); ?></a>
    </div>
</section>
<?php endif; ?>

<?php
get_footer();
