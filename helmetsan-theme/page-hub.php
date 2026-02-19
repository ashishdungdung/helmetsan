<?php
/**
 * Template Name: Discovery Hub
 *
 * A unified template for premium discovery hub pages.
 * Handles both Taxonomies (Terms) and Post Types (Posts).
 *
 * @package HelmetsanTheme
 */

get_header();

// 1. Determine Hub Context
$hub_type = get_post_meta(get_the_ID(), 'hs_hub_type', true) ?: 'taxonomy'; // 'taxonomy' or 'post_type'
$hub_source = get_post_meta(get_the_ID(), 'hs_hub_source', true); // e.g., 'accessory_category' or 'brand'
$hub_hero = get_post_meta(get_the_ID(), 'hs_hub_hero', true); // Hero image URL

if (!$hub_source) {
    echo '<p>Error: No hub source defined.</p>';
    get_footer();
    return;
}

$items = [];
if ($hub_type === 'taxonomy') {
    $items = get_terms([
        'taxonomy' => $hub_source,
        'hide_empty' => false,
    ]);
} else {
    $items = get_posts([
        'post_type' => $hub_source,
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
}

$themeDir = get_stylesheet_directory_uri();
?>

<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php the_title(); ?></h1>
        <?php the_content(); ?>
    </header>

    <?php if (!empty($items)) : ?>
        <div class="helmet-grid">
            <?php foreach ($items as $item) : 
                $title = '';
                $desc = '';
                $link = '';
                $img = '';
                $count_label = '';

                if ($item instanceof WP_Term) {
                    $title = $item->name;
                    $desc = $item->description ?: "Explore our " . $item->name . " collection.";
                    $link = get_term_link($item);
                    $img = $themeDir . '/assets/images/hubs/' . $hub_source . '/' . $item->slug . '.png';
                    $count_label = $item->count . ' Items';
                } else {
                    $title = $item->post_title;
                    $desc = $item->post_excerpt ?: get_the_excerpt($item);
                    $link = get_permalink($item);
                    $img = get_the_post_thumbnail_url($item, 'large') ?: ($themeDir . '/assets/images/hubs/' . $hub_source . '/' . $item->post_name . '.png');
                    $count_label = 'Details';
                }

                // Fallback for image if specific ones don't exist
                if (!file_exists(get_stylesheet_directory() . '/assets/images/hubs/' . $hub_source . '/' . ( ($item instanceof WP_Term) ? $item->slug : $item->post_name ) . '.png')) {
                    $img = $hub_hero ?: ($themeDir . '/assets/images/placeholder-hub.png');
                }
            ?>
                <a href="<?php echo esc_url($link); ?>" class="hs-discovery-card">
                    <article class="hs-discovery-card__inner">
                        <div class="hs-discovery-card__image-container">
                            <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" class="hs-discovery-card__image">
                            <span class="hs-discovery-card__badge"><?php echo esc_html($count_label); ?></span>
                        </div>
                        <div class="hs-discovery-card__content">
                            <h3 class="hs-discovery-card__title"><?php echo esc_html($title); ?></h3>
                            <p class="hs-discovery-card__description"><?php echo esc_html($desc); ?></p>
                            <span class="hs-discovery-card__cta">View Catalog <svg viewBox="0 0 24 24"><path d="M5 12h14m-7-7 7 7-7 7"/></svg></span>
                        </div>
                    </article>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p>No items found in this collection yet.</p>
    <?php endif; ?>
</section>

<?php
get_footer();
