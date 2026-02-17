<?php
/**
 * Helmet card.
 *
 * @package HelmetsanTheme
 */
?>
<article <?php post_class('helmet-card'); ?>>
    <a href="<?php the_permalink(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <div class="helmet-card__image"><?php the_post_thumbnail('medium'); ?></div>
        <?php endif; ?>
        <h2 class="helmet-card__title"><?php the_title(); ?></h2>
    </a>
</article>
