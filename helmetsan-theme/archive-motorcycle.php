<?php
/**
 * Motorcycle archive template.
 *
 * @package HelmetsanTheme
 */

get_header();

$themeDir = get_stylesheet_directory_uri();
$hero_img = $themeDir . '/assets/images/hubs/motorcycles_hub_hero.png';
?>

<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p>Motorcycle index with segment-aware helmet recommendations.</p>
    </header>

    <?php if (have_posts()) : ?>
        <div class="helmet-grid">
            <?php while (have_posts()) : the_post(); 
                $title = get_the_title();
                $desc = get_the_excerpt() ?: "Explore compatible helmets for the " . $title . ".";
                $link = get_permalink();
                $img = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: ($themeDir . '/assets/images/hubs/motorcycle/' . get_post_field('post_name') . '.png');
                
                // Fallback for image
                if (!file_exists(get_stylesheet_directory() . '/assets/images/hubs/motorcycle/' . get_post_field('post_name') . '.png') && !has_post_thumbnail()) {
                    $img = $hero_img;
                }
            ?>
                <a href="<?php echo esc_url($link); ?>" class="hs-discovery-card">
                    <article class="hs-discovery-card__inner">
                        <div class="hs-discovery-card__image-container">
                            <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" class="hs-discovery-card__image">
                            <span class="hs-discovery-card__badge">Details</span>
                        </div>
                        <div class="hs-discovery-card__content">
                            <h3 class="hs-discovery-card__title"><?php echo esc_html($title); ?></h3>
                            <p class="hs-discovery-card__description"><?php echo esc_html($desc); ?></p>
                            <span class="hs-discovery-card__cta">View Recommendations <svg viewBox="0 0 24 24"><path d="M5 12h14m-7-7 7 7-7 7"/></svg></span>
                        </div>
                    </article>
                </a>
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>No motorcycles found.</p>
    <?php endif; ?>
</section>

<?php
get_footer();

