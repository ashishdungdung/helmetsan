<?php
/**
 * Helmet card.
 *
 * @package HelmetsanTheme
 */
$helmetId = get_the_ID();
$price = helmetsan_get_helmet_price($helmetId);
$certs = helmetsan_get_certifications($helmetId);
?>
<article <?php post_class('helmet-card hs-panel'); ?>>
    <a href="<?php the_permalink(); ?>" class="helmet-card__link">
        <?php if (has_post_thumbnail()) : ?>
            <div class="helmet-card__image"><?php the_post_thumbnail('medium_large'); ?></div>
        <?php endif; ?>
        <h3 class="helmet-card__title"><?php the_title(); ?></h3>
    </a>
    <p class="helmet-card__meta"><span><?php echo esc_html($price); ?></span><span><?php echo esc_html($certs); ?></span></p>
</article>
