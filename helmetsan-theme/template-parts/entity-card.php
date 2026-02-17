<?php
/**
 * Generic entity card partial.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$postId = get_the_ID();
$type   = get_post_type($postId);
$logoUrl = helmetsan_get_logo_url($postId);
$metaA  = '';
$metaB  = '';

if ($type === 'brand') {
    $metaA = (string) get_post_meta($postId, 'brand_origin_country', true);
    $metaB = (string) get_post_meta($postId, 'brand_helmet_types', true);
} elseif ($type === 'accessory') {
    $metaA = (string) get_post_meta($postId, 'accessory_type', true);
    $metaB = (string) get_post_meta($postId, 'price_json', true);
} elseif ($type === 'motorcycle') {
    $metaA = (string) get_post_meta($postId, 'motorcycle_make', true);
    $metaB = (string) get_post_meta($postId, 'bike_segment', true);
} elseif ($type === 'safety_standard') {
    $metaA = (string) get_post_meta($postId, 'official_reference_url', true);
    $metaB = (string) get_post_meta($postId, 'mandatory_markets_json', true);
}
?>
<article <?php post_class('hs-panel entity-card'); ?>>
    <?php if ($logoUrl !== '') : ?>
        <div class="entity-card__logo-wrap">
            <img class="entity-card__logo" src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" />
        </div>
    <?php endif; ?>
    <h3 class="entity-card__title"><a class="hs-link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    <?php if (has_excerpt()) : ?>
        <p class="entity-card__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
    <?php endif; ?>
    <div class="entity-card__meta">
        <?php if ($metaA !== '') : ?>
            <code><?php echo esc_html(wp_trim_words($metaA, 12, '...')); ?></code>
        <?php endif; ?>
        <?php if ($metaB !== '') : ?>
            <code><?php echo esc_html(wp_trim_words($metaB, 12, '...')); ?></code>
        <?php endif; ?>
    </div>
</article>
