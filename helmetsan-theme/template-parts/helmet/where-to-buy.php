<?php
/**
 * Helmet Single: Where to Buy, Live Pricing & Local Dealers
 *
 * @package HelmetsanTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

$helmetId          = (int) ($args['helmetId'] ?? get_the_ID());
$post              = $args['post'] ?? get_post($helmetId);
$bestOffer         = $args['bestOffer'] ?? null;
$allOffers         = $args['allOffers'] ?? [];
$geoRelevantLinks  = $args['geoRelevantLinks'] ?? [];
$localDealers      = $args['localDealers'] ?? [];
$hasWhereToBuy     = !empty($allOffers) || !empty($geoRelevantLinks) || !empty($localDealers) || ($bestOffer !== null && $bestOffer->price > 0);

if (!$hasWhereToBuy) {
    return;
}
?>

<section class="hs-panel hs-where-to-buy helmet-single__where hs-reveal" id="where-to-buy">
    <div class="hs-price-comparer__alert-banner" style="border-radius: var(--hs-radius-lg) var(--hs-radius-lg) 0 0; margin: -1.5rem -1.5rem 1.5rem -1.5rem;">
        <div class="hs-price-comparer__alert-text">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span><?php esc_html_e('Price tracking active across international retailers.', 'helmetsan-theme'); ?></span>
        </div>
        <button type="button" class="hs-price-comparer__alert-btn" id="hsPriceAlertTrigger" data-helmet-id="<?php echo esc_attr((string) $helmetId); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php esc_html_e('Set Drop Alert', 'helmetsan-theme'); ?>
        </button>
    </div>
    
    <div class="hs-where-to-buy__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--hs-sp-4); margin-bottom: var(--hs-sp-6);">
        <h2 class="hs-section-icon-title" style="margin: 0;">
            <span class="hs-section-icon-title__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </span>
            <?php esc_html_e('Where to buy', 'helmetsan-theme'); ?>
        </h2>

        <?php if (!empty($localDealers)) : ?>
            <div class="hs-purchase-tabs">
                <button type="button" class="hs-purchase-tab hs-purchase-tab--active" data-target="hs-tab-online">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <?php esc_html_e('Online Stores', 'helmetsan-theme'); ?>
                </button>
                <button type="button" class="hs-purchase-tab" data-target="hs-tab-local">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?php esc_html_e('Buy Local', 'helmetsan-theme'); ?> (<?php echo count($localDealers); ?>)
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ─── Tab A: Online Stores ─── -->
    <div id="hs-tab-online" class="hs-purchase-pane hs-purchase-pane--active">
        <?php if ($bestOffer !== null && $bestOffer->price > 0) : ?>
            <?php
            $bestGoUrl = home_url('/go/' . ($post ? $post->post_name : $helmetId) . '/?marketplace=' . urlencode($bestOffer->marketplaceId) . '&source=pdp');
            ?>
            <a href="<?php echo esc_url($bestGoUrl); ?>" class="helmet-single__best-offer" target="_blank" rel="noopener noreferrer sponsored">
                <span class="helmet-single__best-offer-label"><?php esc_html_e('Best price', 'helmetsan-theme'); ?></span>
                    <?php 
                    $formattedBestPrice = (isset($priceService) && $priceService) ? $priceService->formatPrice($bestOffer->price, $bestOffer->currency) : '$' . number_format($bestOffer->price, 2);
                    ?>
                    <span class="helmet-single__best-offer-price hs-price" data-base-price="<?php echo esc_attr((string) $bestOffer->price); ?>" data-base-currency="<?php echo esc_attr((string) $bestOffer->currency); ?>">
                        <?php echo esc_html($formattedBestPrice); ?>
                    </span>
                    <span class="helmet-single__best-offer-source"><?php echo esc_html(function_exists('helmetsan_marketplace_label') ? helmetsan_marketplace_label($bestOffer->marketplaceId) : $bestOffer->marketplaceId); ?></span>
            </a>
        <?php endif; ?>

        <?php
        // Build list of displayed offers
        $displayedOffers = [];
        $seenMarketplaces = [];
        
        // Add live offers first
        if (!empty($allOffers)) {
            foreach ($allOffers as $offer) {
                $displayedOffers[] = [
                    'marketplaceId' => $offer->marketplaceId,
                    'price' => $offer->price,
                    'currency' => $offer->currency,
                    'availability' => $offer->availability,
                    'capturedAt' => $offer->capturedAt,
                    'is_live' => true,
                ];
                $seenMarketplaces[strtolower($offer->marketplaceId)] = true;
            }
        }
        
        // Append geo fallback links if not already present in live offers
        if (!empty($geoRelevantLinks)) {
            foreach ($geoRelevantLinks as $mpId => $entry) {
                $mpIdLower = strtolower($mpId);
                if (!isset($seenMarketplaces[$mpIdLower])) {
                    $displayedOffers[] = [
                        'marketplaceId' => $mpId,
                        'price' => null,
                        'currency' => '',
                        'availability' => 'in_stock',
                        'capturedAt' => null,
                        'is_live' => false,
                        'direct_url' => $entry['url'] ?? '',
                        'label' => $entry['label'] ?? '',
                        'priority' => $entry['priority'] ?? 99,
                    ];
                    $seenMarketplaces[$mpIdLower] = true;
                }
            }
        }
        ?>
        <?php if (!empty($displayedOffers)) : ?>
            <ul class="helmet-single__offers-list hs-offers-list">
                <?php foreach ($displayedOffers as $offerItem) : 
                    $mpKey = $offerItem['marketplaceId'];
                    $mpLabel = $offerItem['label'] ?? (function_exists('helmetsan_marketplace_label') ? helmetsan_marketplace_label($mpKey) : $mpKey);
                    $itemGoUrl = home_url('/go/' . ($post ? $post->post_name : $helmetId) . '/?marketplace=' . urlencode($mpKey) . '&source=pdp');
                ?>
                    <li class="hs-offers-list__item">
                        <span class="hs-offers-list__retailer"><?php echo esc_html($mpLabel); ?></span>
                        <div class="hs-offers-list__price-wrap">
                            <?php if ($offerItem['is_live'] && $offerItem['price'] > 0) : ?>
                                <span class="hs-offers-list__price"><?php echo esc_html(function_exists('helmetsan_format_currency') ? helmetsan_format_currency($offerItem['price'], $offerItem['currency']) : ($offerItem['currency'] . ' ' . $offerItem['price'])); ?></span>
                            <?php else : ?>
                                <span class="hs-offers-list__check-price"><?php esc_html_e('Check live price', 'helmetsan-theme'); ?></span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($itemGoUrl); ?>" class="hs-btn hs-btn--sm hs-btn--primary" target="_blank" rel="noopener noreferrer sponsored"><?php esc_html_e('View Deal →', 'helmetsan-theme'); ?></a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- Cross-Border Shipping & Import Duty Notice -->
        <div style="margin-top: 1.25rem;">
            <?php
            get_template_part('template-parts/components/import-duty-notice', null, [
                'helmet_id' => $helmetId,
            ]);
            ?>
        </div>
    </div>

    <!-- ─── Tab B: Local Dealers ─── -->
    <?php if (!empty($localDealers)) : ?>
        <div id="hs-tab-local" class="hs-purchase-pane">
            <div class="hs-local-dealers-grid">
                <?php foreach ($localDealers as $dealer) : 
                    $directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($dealer['address'] ?? ($dealer['name'] ?? ''));
                ?>
                    <article class="hs-local-dealer-card">
                        <div class="hs-local-dealer-card__header">
                            <h3 class="hs-local-dealer-card__title"><?php echo esc_html($dealer['name']); ?></h3>
                            <?php if (!empty($dealer['distance'])) : ?>
                                <span class="hs-badge hs-badge--accent"><?php echo esc_html($dealer['distance']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hs-local-dealer-card__body">
                            <p class="hs-local-dealer-card__address">📍 <?php echo esc_html($dealer['address']); ?></p>
                            <?php if (!empty($dealer['services'])) : ?>
                                <div class="hs-local-dealer-card__services">
                                    <?php foreach (array_slice($dealer['services'], 0, 3) as $service) : ?>
                                        <span class="hs-badge hs-badge--sm"><?php echo esc_html(ucwords(str_replace('-', ' ', $service))); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hs-local-dealer-card__actions">
                            <?php if (!empty($dealer['phone'])) : ?>
                                <a href="tel:<?php echo esc_attr($dealer['phone']); ?>" class="hs-btn hs-btn--sm hs-btn--ghost" title="<?php echo esc_attr($dealer['phone']); ?>">📞 <?php esc_html_e('Call', 'helmetsan-theme'); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($dealer['website'])) : ?>
                                <a href="<?php echo esc_url($dealer['website']); ?>" class="hs-btn hs-btn--sm hs-btn--ghost" target="_blank" rel="noopener noreferrer">🌐 <?php esc_html_e('Website', 'helmetsan-theme'); ?></a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($directionsUrl); ?>" class="hs-btn hs-btn--sm hs-btn--primary" target="_blank" rel="noopener noreferrer">🗺️ <?php esc_html_e('Directions', 'helmetsan-theme'); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Price History Chart & Affiliate Disclosure -->
    <div class="hs-price-chart-wrap" id="hs-price-chart-wrap" style="margin-top: var(--hs-sp-6); padding-top: var(--hs-sp-4); border-top: 1px solid var(--hs-border);">
        <h3><?php esc_html_e('Price History & Trends', 'helmetsan-theme'); ?></h3>
        <p id="hs-price-chart-empty" class="hs-muted" style="font-size: var(--hs-fs-sm); display:none;"><?php esc_html_e('No historical observations recorded yet. Helmetsan will track price trends as observations accumulate.', 'helmetsan-theme'); ?></p>
        <div class="hs-price-date-toggles" id="hs-date-toggles">
            <button class="hs-btn hs-btn--sm is-active" data-days="30"><?php esc_html_e('30 Days', 'helmetsan-theme'); ?></button>
            <button class="hs-btn hs-btn--sm" data-days="90"><?php esc_html_e('90 Days', 'helmetsan-theme'); ?></button>
            <button class="hs-btn hs-btn--sm" data-days="365"><?php esc_html_e('1 Year', 'helmetsan-theme'); ?></button>
        </div>
        <canvas id="hs-price-chart" data-helmet-id="<?php echo esc_attr((string) $helmetId); ?>" height="300"></canvas>
    </div>

    <p class="hs-affiliate-disclosure" style="margin-top: var(--hs-sp-4); font-size: 11px; color: var(--hs-muted); opacity: 0.85;">
        ℹ️ <em><?php esc_html_e('Helmetsan is an independent motorcycle safety & decision engine. When you check prices or purchase via retailer links, Helmetsan may earn an affiliate commission at no additional cost to you.', 'helmetsan-theme'); ?></em>
    </p>
</section>
