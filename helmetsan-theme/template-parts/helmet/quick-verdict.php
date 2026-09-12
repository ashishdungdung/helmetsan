<?php
/**
 * Helmet Single: Quick Verdict & Decision Support Card
 *
 * @package HelmetsanTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

$helmetId        = (int) ($args['helmetId'] ?? get_the_ID());
$useCase         = $args['useCase'] ?? '';
$helmetTypeLabel = $args['helmetTypeLabel'] ?? '';
$weight          = (int) ($args['weight'] ?? 0);
$shell           = $args['shell'] ?? '';
$headShape       = $args['headShape'] ?? '';
$certs           = $args['certs'] ?? '';
?>

<aside class="helmet-single__aside hs-pdp-verdict-card" id="quick-verdict">
    <div class="hs-pdp-verdict-card__header">
        <span class="hs-pdp-verdict-card__badge"><?php esc_html_e('HELMETSAN QUICK VERDICT', 'helmetsan-theme'); ?></span>
        <span class="hs-text-xs hs-text-muted"><?php esc_html_e('Helmetsan Decision Support', 'helmetsan-theme'); ?></span>
    </div>

    <!-- Best For -->
    <div class="hs-pdp-verdict-group">
        <span class="hs-pdp-verdict-group__label"><?php esc_html_e('Best For', 'helmetsan-theme'); ?></span>
        <div class="hs-pdp-verdict-group__tags">
            <?php if ($useCase !== '') : ?>
                <span class="hs-pdp-verdict-tag"><?php echo esc_html(ucwords(str_replace('-', ' ', $useCase))); ?></span>
            <?php endif; ?>
            <?php if ($helmetTypeLabel !== '') : ?>
                <span class="hs-pdp-verdict-tag"><?php echo esc_html($helmetTypeLabel); ?></span>
            <?php else : ?>
                <span class="hs-pdp-verdict-tag"><?php esc_html_e('Motorcycle Riding', 'helmetsan-theme'); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Key Considerations -->
    <div class="hs-pdp-verdict-group">
        <span class="hs-pdp-verdict-group__label"><?php esc_html_e('Key Factors', 'helmetsan-theme'); ?></span>
        <div class="hs-pdp-verdict-group__tags">
            <?php if ($weight > 0) : ?>
                <span class="hs-pdp-verdict-tag">⚖️ <?php echo esc_html($weight . 'g'); ?></span>
            <?php endif; ?>
            <?php if ($shell !== '') : ?>
                <span class="hs-pdp-verdict-tag">🛡️ <?php echo esc_html($shell); ?></span>
            <?php endif; ?>
            <span class="hs-pdp-verdict-tag">📐 <?php echo esc_html($headShape !== '' ? ucwords(str_replace('-', ' ', $headShape)) : 'Head shape unverified'); ?></span>
        </div>
    </div>

    <!-- Considerations & Data Notes -->
    <?php if ($headShape === '' || $certs === '' || $certs === 'N/A' || $weight <= 0) : ?>
        <div class="hs-pdp-verdict-group hs-pdp-verdict-group--warning">
            <span class="hs-pdp-verdict-group__label"><?php esc_html_e('Data Notes', 'helmetsan-theme'); ?></span>
            <ul class="hs-pdp-verdict-notes">
                <?php if ($headShape === '') : ?>
                    <li><?php esc_html_e('Head shape fit profile is unverified.', 'helmetsan-theme'); ?></li>
                <?php endif; ?>
                <?php if ($certs === '' || $certs === 'N/A') : ?>
                    <li><?php esc_html_e('Certification standards pending lab verification.', 'helmetsan-theme'); ?></li>
                <?php endif; ?>
                <?php if ($weight <= 0) : ?>
                    <li><?php esc_html_e('Measured weight unavailable.', 'helmetsan-theme'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php
    $pros = [];
    $cons = [];
    if (class_exists(\Helmetsan\Core\AI\AiContentPipeline::class)) {
        $aiPipeline = new \Helmetsan\Core\AI\AiContentPipeline();
        $verdictData = $aiPipeline->renderVerdict($helmetId);
        $pros = $verdictData['pros'] ?? [];
        $cons = $verdictData['cons'] ?? [];
    }
    ?>
    <?php if (!empty($pros) || !empty($cons)) : ?>
        <div class="hs-pdp-verdict-group">
            <span class="hs-pdp-verdict-group__label"><?php esc_html_e('Helmetsan Assessment', 'helmetsan-theme'); ?></span>
            <?php if (!empty($pros)) : ?>
                <ul class="hs-pdp-verdict-pros" style="list-style: none; margin: 0 0 0.35rem 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                    <?php foreach (array_slice($pros, 0, 2) as $pro) : ?>
                        <li style="font-size: 0.775rem; line-height: 1.35; color: var(--hs-text, #334155); display: flex; align-items: baseline; gap: 0.35rem;">
                            <span style="color: #16a34a; font-weight: 700;">✓</span> <?php echo esc_html($pro); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($cons)) : ?>
                <ul class="hs-pdp-verdict-cons" style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                    <?php foreach (array_slice($cons, 0, 1) as $con) : ?>
                        <li style="font-size: 0.775rem; line-height: 1.35; color: var(--hs-muted, #64748b); display: flex; align-items: baseline; gap: 0.35rem;">
                            <span style="color: #ea580c; font-weight: 700;">⚠</span> <?php echo esc_html($con); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Road Legality & Certification Compliance -->
    <div class="hs-pdp-verdict-group hs-pdp-verdict-group--legality" style="margin-bottom: 0.75rem;">
        <?php
        get_template_part('template-parts/components/road-legality-badge', null, [
            'helmet_id' => $helmetId,
        ]);
        ?>
    </div>

    <?php
    $slug = (string) get_post_field('post_name', $helmetId);
    $amazonGoUrl = $slug !== '' ? home_url('/go/' . $slug . '/?marketplace=amazon&source=quick_verdict') : '#where-to-buy';
    ?>
    <!-- Price Hero & Buying Action -->
    <div class="hs-specs-card__price-hero" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(16, 185, 129, 0.05) 100%); padding: 1.1rem 1.25rem; border-radius: var(--hs-radius-md, 8px); border: 1px solid rgba(245, 158, 11, 0.25); margin-bottom: 0.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.25rem;">
            <span class="hs-text-xs hs-text-muted hs-uppercase hs-font-bold" style="letter-spacing: 0.05em;"><?php esc_html_e('Best Online Price', 'helmetsan-theme'); ?></span>
            <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 700; color: #d97706; background: rgba(245, 158, 11, 0.15); padding: 0.15rem 0.5rem; border-radius: 999px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Verified
            </span>
        </div>
        <?php echo helmetsan_render_price_element($helmetId, 'hs-specs-card__price-value'); ?>
    </div>

    <div class="hs-specs-card__cta" style="display:flex; flex-direction:column; gap:0.5rem;">
        <a href="<?php echo esc_url($amazonGoUrl); ?>" class="hs-btn hs-btn--amazon hs-btn--full hs-price-cta" target="_blank" rel="noopener noreferrer sponsored" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #111827; font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border: none; border-radius: var(--hs-radius-md, 8px); padding: 0.85rem 1rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35); text-decoration: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            <?php esc_html_e('Buy on Amazon →', 'helmetsan-theme'); ?>
        </a>
        <a href="#where-to-buy" class="hs-btn hs-btn--secondary hs-btn--full hs-btn--sm" style="text-align: center; font-size: 0.8rem; font-weight: 600; padding: 0.5rem;">
            <?php esc_html_e('Compare All Stores ↓', 'helmetsan-theme'); ?>
        </a>
        <button type="button" class="js-add-to-compare hs-btn hs-btn--ghost hs-btn--full hs-btn--sm" data-id="<?php echo esc_attr((string) $helmetId); ?>" style="font-size: 0.775rem;">
            + <?php esc_html_e('Add to Comparison', 'helmetsan-theme'); ?>
        </button>
    </div>

    <!-- International Import Advisory -->
    <?php
    get_template_part('template-parts/components/import-duty-notice', null, [
        'helmet_id' => $helmetId,
    ]);
    ?>
</aside>

