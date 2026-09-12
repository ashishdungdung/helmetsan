<?php
/**
 * Road Legality & Certification Badge Component.
 *
 * Renders the road compliance status for the currently selected country,
 * supporting 0ms client-side re-evaluation when country changes.
 *
 * @package HelmetsanTheme
 */

declare(strict_types=1);

$helmetId = $args['helmet_id'] ?? get_the_ID();
if (!$helmetId) {
    return;
}

$certs = helmetsan_get_certifications_array((int) $helmetId);
$brandName = helmetsan_get_brand_name((int) $helmetId);
$currentCountry = function_exists('helmetsan_core') ? helmetsan_core()->geo()->getCountry() : 'IN';

$legality = helmetsan_resolve_road_legality($certs, $currentCountry, $brandName);
$status = $legality['status']; // 'legal' | 'advisory'
$statusClass = $status === 'legal' ? 'hs-road-legality-badge--legal' : 'hs-road-legality-badge--warning';
?>

<div class="hs-road-legality-badge <?= esc_attr($statusClass) ?>" 
     id="hs-road-legality-badge"
     data-certifications="<?= esc_attr(wp_json_encode($certs)) ?>"
     data-brand="<?= esc_attr($brandName) ?>"
     data-country="<?= esc_attr($legality['country']) ?>">
    <div class="hs-road-legality-badge__main">
        <div class="hs-road-legality-badge__icon" aria-hidden="true">
            <?php if ($status === 'legal') : ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            <?php else : ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            <?php endif; ?>
        </div>
        <div class="hs-road-legality-badge__text">
            <div class="hs-road-legality-badge__title">
                <span class="hs-road-legality-badge__flag"><?= esc_html($legality['flag']) ?></span>
                <span class="hs-road-legality-badge__headline-text"><?= esc_html($legality['headline']) ?></span>
            </div>
            <div class="hs-road-legality-badge__subtitle"><?= esc_html($legality['subtitle']) ?></div>
        </div>
    </div>
</div>
