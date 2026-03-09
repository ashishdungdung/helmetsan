<?php
/**
 * Sticky Comparison Bar.
 *
 * @package HelmetsanTheme
 */
$compare_url = home_url('/comparison/');
?>
<div id="hs-comparison-bar" class="hs-comparison-bar is-hidden" aria-live="polite" aria-label="<?php esc_attr_e('Comparison bar', 'helmetsan-theme'); ?>">
    <div id="hs-comparison-list" class="hs-comparison-bar__thumbs"></div>
    <span class="hs-comparison-bar__label">
        <span id="hs-comparison-prefix"></span><span id="hs-comparison-count">0</span><span id="hs-comparison-label"> <?php esc_html_e('selected', 'helmetsan-theme'); ?></span>
    </span>
    <button type="button" id="hs-comparison-clear" class="hs-btn hs-btn--sm hs-btn--ghost"><?php esc_html_e('Clear', 'helmetsan-theme'); ?></button>
    <a id="hs-comparison-view" href="<?php echo esc_url($compare_url); ?>" class="hs-btn hs-btn--sm hs-btn--primary">
        <?php esc_html_e('Compare now', 'helmetsan-theme'); ?> &rarr;
    </a>
</div>
