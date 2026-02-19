<?php
/**
 * Helmet card.
 *
 * @package HelmetsanTheme
 */
$helmetId = get_the_ID();
$price = helmetsan_get_helmet_price($helmetId);
$certs = helmetsan_get_certifications($helmetId);
$logoUrl = helmetsan_get_logo_url($helmetId);
?>
<article <?php post_class('helmet-card hs-panel'); ?>>
    <?php if ($logoUrl !== '') : ?>
        <div class="entity-card__logo-wrap">
            <img class="entity-card__logo" src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
        </div>
    <?php endif; ?>
    <button type="button" 
            class="js-add-to-compare hs-btn hs-btn--icon" 
            data-id="<?php echo esc_attr((string) $helmetId); ?>" 
            title="<?php esc_attr_e('Compare', 'helmetsan-theme'); ?>"
            aria-pressed="false">
        <span>Compare</span>
    </button>
    <a href="/comparison/" 
       class="js-view-compare hs-btn hs-btn--sm hs-btn--primary is-hidden"
       title="<?php esc_attr_e('View Comparison', 'helmetsan-theme'); ?>"
       style="position:absolute; top:45px; right:10px; z-index:10; font-size:0.65rem; padding:0.2rem 0.6rem; min-width:auto; line-height:1.2;">
       View
    </a>

    <a href="<?php the_permalink(); ?>" class="helmet-card__link">
        <?php if (has_post_thumbnail()) : ?>
            <div class="helmet-card__image"><?php the_post_thumbnail('medium_large'); ?></div>
        <?php endif; ?>
        <h3 class="helmet-card__title"><?php the_title(); ?></h3>
    </a>
    
    <?php 
    $children = get_posts([
        'post_parent'    => $helmetId,
        'post_type'      => 'helmet',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $count = is_array($children) ? count($children) : 0;
    ?>

    <div class="helmet-card__meta">
        <span><?php echo esc_html($price); ?></span>
        <?php if ($count > 0) : ?>
            <span class="hs-badge hs-badge--variants"><?php echo esc_html($count); ?> Colors</span>
        <?php endif; ?>
        <span><?php echo esc_html($certs); ?></span>
    </div>
</article>
