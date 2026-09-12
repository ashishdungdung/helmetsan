<?php
/**
 * International Import & Customs Advisory Component.
 *
 * Provides transparent consumer protection regarding import duties, GST/customs,
 * and delivery clearance for premium imported helmets in high-tariff regions (e.g. India).
 *
 * @package HelmetsanTheme
 */

declare(strict_types=1);

$helmetId = $args['helmet_id'] ?? get_the_ID();
if (!$helmetId) {
    return;
}

$brandName = helmetsan_get_brand_name((int) $helmetId);
$brandUpper = strtoupper(trim($brandName));
$domesticIndianBrands = ['VEGA', 'STEELBIRD', 'STUDDS', 'AXOR', 'SMK'];
$isDomesticIn = in_array($brandUpper, $domesticIndianBrands, true);

$currentCountry = function_exists('helmetsan_core') ? helmetsan_core()->geo()->getCountry() : 'IN';
$shouldShow = ($currentCountry === 'IN' && !$isDomesticIn);
?>

<div class="hs-import-duty-notice hs-import-duty-notice--customs-due"
     id="hs-import-duty-notice"
     data-brand="<?= esc_attr($brandName) ?>"
     data-domestic-in="<?= $isDomesticIn ? 'true' : 'false' ?>"
     style="display: <?= $shouldShow ? 'block' : 'none' ?>;">
    <div class="hs-import-duty-notice__inner">
        <span class="hs-import-duty-notice__icon" aria-hidden="true">📦</span>
        <div class="hs-import-duty-notice__content">
            <div class="hs-import-duty-notice__title"><?php esc_html_e('International Import & Customs Advisory', 'helmetsan-theme'); ?></div>
            <p class="hs-import-duty-notice__desc">
                <?php
                /* translators: %s: brand name */
                printf(
                    esc_html__('%s is an international import. Orders via cross-border distributors may be subject to customs clearance and local import taxes upon delivery.', 'helmetsan-theme'),
                    esc_html($brandName)
                );
                ?>
            </p>
        </div>
    </div>
</div>
