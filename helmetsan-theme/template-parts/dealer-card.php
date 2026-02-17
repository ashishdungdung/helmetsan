<?php
/**
 * Dealer card partial.
 *
 * @package HelmetsanTheme
 */
?>
<article <?php post_class('dealer-card hs-panel'); ?>>
    <?php $logoUrl = helmetsan_get_logo_url(get_the_ID()); ?>
    <?php if ($logoUrl !== '') : ?>
        <div class="entity-card__logo-wrap">
            <img class="entity-card__logo" src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
        </div>
    <?php endif; ?>
    <h3><?php the_title(); ?></h3>
    <div><?php the_excerpt(); ?></div>
    <a class="hs-link" href="<?php the_permalink(); ?>">View details</a>
</article>
