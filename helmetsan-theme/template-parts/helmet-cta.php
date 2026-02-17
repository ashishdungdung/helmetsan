<?php
/**
 * Helmet CTA partial.
 *
 * @package HelmetsanTheme
 */

$asin = get_post_meta(get_the_ID(), 'affiliate_asin', true);
if ($asin !== '') :
    $slug = get_post_field('post_name', get_the_ID());
    $goUrl = home_url('/go/' . $slug . '/?source=single_page');
    ?>
    <p><a class="hs-btn hs-btn--primary" href="<?php echo esc_url($goUrl); ?>" rel="nofollow sponsored">Check Price</a></p>
    <?php
endif;
