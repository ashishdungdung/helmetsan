<?php
/**
 * Dealer card partial.
 *
 * @package HelmetsanTheme
 */
?>
<article <?php post_class('dealer-card hs-panel'); ?>>
    <h3><?php the_title(); ?></h3>
    <div><?php the_excerpt(); ?></div>
    <a class="hs-link" href="<?php the_permalink(); ?>">View details</a>
</article>
