<?php
/**
 * Geo pricing hub page template.
 *
 * Displays helmets with geo-resolved pricing data from the price engine.
 *
 * @package HelmetsanTheme
 */

get_header();

$query = new WP_Query([
    'post_type'      => 'helmet',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'meta_query'     => [
        [
            'key'     => 'geo_pricing_json',
            'compare' => 'EXISTS',
        ],
    ],
]);
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php the_title(); ?></h1>
        <p>Compare helmet prices across countries and marketplaces.</p>
    </header>
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="hs-panel"><?php the_content(); ?></div>
    <?php endwhile; endif; ?>

    <?php if ($query->have_posts()) : ?>
        <div class="helmet-grid">
            <?php while ($query->have_posts()) : $query->the_post();
                $postId       = get_the_ID();
                $geoPricing   = get_post_meta($postId, 'geo_pricing_json', true);
                $prices       = is_string($geoPricing) ? json_decode($geoPricing, true) : [];
                $thumbnailUrl = get_the_post_thumbnail_url($postId, 'medium');
            ?>
                <article class="hs-card">
                    <?php if ($thumbnailUrl) : ?>
                        <img class="hs-card__img" src="<?php echo esc_url($thumbnailUrl); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                    <?php endif; ?>

                    <div class="hs-card__body">
                        <h2 class="hs-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>

                        <?php if (is_array($prices) && !empty($prices)) : ?>
                            <table class="hs-geo-table">
                                <thead>
                                    <tr><th>Country</th><th>Price</th><th>Marketplace</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach (array_slice($prices, 0, 5) as $row) :
                                    $cc       = $row['country_code'] ?? '—';
                                    $currency = $row['currency'] ?? 'USD';
                                    $price    = $row['current_price'] ?? null;
                                    $mpId     = $row['marketplace_id'] ?? '—';
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($cc); ?></td>
                                        <td>
                                            <?php
                                            if ($price !== null) {
                                                echo esc_html($currency . ' ' . number_format((float) $price, 2));
                                            } else {
                                                echo '<em>N/A</em>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($mpId); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p class="hs-card__note">No geo pricing data yet.</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        <p><a class="hs-btn hs-btn--ghost" href="<?php echo esc_url(get_post_type_archive_link('helmet')); ?>">Browse Helmet Archive</a></p>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p>No helmets with geo pricing metadata yet.</p>
    <?php endif; ?>
</section>
<?php
get_footer();
