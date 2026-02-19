<?php

declare(strict_types=1);

namespace Helmetsan\Core\Price;

use Helmetsan\Core\Geo\GeoService;
use Helmetsan\Core\Marketplace\MarketplaceRouter;
use Helmetsan\Core\Marketplace\PriceResult;
use WP_Post;

/**
 * Geo-aware price engine.
 *
 * Combines static post-meta pricing (USD/EUR/GBP) with live
 * marketplace data from the connector engine. The service picks
 * the best price for the visitor's country and formats it in the
 * local currency.
 */
final class PriceService
{
    private CurrencyFormatter $formatter;
    private GeoService $geo;
    private MarketplaceRouter $router;
    private PriceHistory $history;

    public function __construct(
        GeoService $geo,
        MarketplaceRouter $router,
        PriceHistory $history,
        ?CurrencyFormatter $formatter = null
    ) {
        $this->geo       = $geo;
        $this->router    = $router;
        $this->history   = $history;
        $this->formatter = $formatter ?? new CurrencyFormatter();
    }

    // ─── Legacy API (backward-compatible) ───────────────────────────────

    /**
     * Get formatted price for a helmet from static post meta.
     *
     * @param int|WP_Post $post
     * @param string $currency 'USD', 'EUR', 'GBP'
     * @return string
     */
    public function getPrice($post, string $currency = 'USD'): string
    {
        $post = get_post($post);
        if (!$post) return '';

        $key = match (strtoupper($currency)) {
            'EUR' => 'price_eur',
            'GBP' => 'price_gbp',
            default => 'price_usd',
        };

        // Try specific currency meta
        $price = get_post_meta($post->ID, $key, true);

        // Fallback to retail_usd if USD requested and specific missing
        if ($currency === 'USD' && $price === '') {
            $price = get_post_meta($post->ID, 'price_retail_usd', true);
        }

        if (!is_numeric($price)) return 'Check Retailer';

        return $this->formatPrice((float) $price, $currency);
    }

    public function formatPrice(float $amount, string $currency): string
    {
        return $this->formatter->format($amount, $currency);
    }

    // ─── Geo-Aware API ──────────────────────────────────────────────────

    /**
     * Get the best marketplace price for a helmet in the visitor's country.
     *
     * Resolution order:
     *  1. Fresh PriceHistory cache (< 1 hour old)
     *  2. Live marketplace query via MarketplaceRouter
     *  3. Static post-meta fallback (price_usd / price_eur / price_gbp)
     */
    public function getBestPrice(int $postId, ?string $countryCode = null): ?PriceResult
    {
        $cc = $countryCode ?? $this->geo->getCountry();

        // 1. Check recent price history (under 1 hour old)
        $latest = $this->history->getLatestByMarketplace($postId, $cc);
        if (!empty($latest)) {
            $best = null;
            foreach ($latest as $mpId => $entry) {
                $capturedAt = strtotime($entry['captured_at']);
                // Skip stale entries (>1 hour)
                if ($capturedAt !== false && (time() - $capturedAt) > 3600) {
                    continue;
                }
                if ($best === null || $entry['price'] < $best->price) {
                    $best = new PriceResult(
                        marketplaceId: $mpId,
                        helmetRef: (string) get_post_field('post_name', $postId),
                        countryCode: $cc,
                        currency: $entry['currency'],
                        price: $entry['price'],
                        availability: 'in_stock',
                        capturedAt: $entry['captured_at'],
                    );
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        // 2. Live query via MarketplaceRouter
        $helmetRef = (string) get_post_field('post_name', $postId);
        if ($helmetRef !== '') {
            $live = $this->router->bestPriceForVisitor($helmetRef);
            if ($live !== null) {
                // Record in history for caching
                $this->history->record(
                    $postId,
                    $live->marketplaceId,
                    $live->countryCode,
                    $live->currency,
                    $live->price
                );
                return $live;
            }
        }

        // 3. Static post-meta fallback
        return $this->buildStaticFallback($postId, $cc);
    }

    /**
     * Get all marketplace offers for a helmet, sorted by price.
     *
     * @return PriceResult[]
     */
    public function getAllOffers(int $postId, ?string $countryCode = null): array
    {
        $cc = $countryCode ?? $this->geo->getCountry();
        $helmetRef = (string) get_post_field('post_name', $postId);

        if ($helmetRef === '') {
            return [];
        }

        $offers = $this->router->allOffersForVisitor($helmetRef);

        // Record each offer in price history
        foreach ($offers as $offer) {
            $this->history->record(
                $postId,
                $offer->marketplaceId,
                $offer->countryCode,
                $offer->currency,
                $offer->price
            );
        }

        // If no live offers, include static fallback
        if (empty($offers)) {
            $static = $this->buildStaticFallback($postId, $cc);
            if ($static !== null) {
                $offers[] = $static;
            }
        }

        return $offers;
    }

    /**
     * Get a formatted price string localised to the visitor's country.
     *
     * Returns the best available price formatted in the visitor's currency,
     * or falls back to static post meta.
     */
    public function getGeoPrice(int $postId, ?string $countryCode = null): string
    {
        $best = $this->getBestPrice($postId, $countryCode);

        if ($best !== null) {
            return $this->formatter->format($best->price, $best->currency);
        }

        // Ultimate fallback
        return $this->getPrice($postId, 'USD');
    }

    /**
     * Get the CurrencyFormatter instance.
     */
    public function formatter(): CurrencyFormatter
    {
        return $this->formatter;
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * Build a PriceResult from static post meta (price_usd, price_eur, price_gbp).
     */
    private function buildStaticFallback(int $postId, string $countryCode): ?PriceResult
    {
        $currency = $this->geo->getCurrency($countryCode);

        // Map currency to the post-meta key we have
        $metaKey = match ($currency) {
            'EUR' => 'price_eur',
            'GBP' => 'price_gbp',
            default => 'price_usd',
        };

        // For currencies we don't have direct meta for, try USD
        $fallbackCurrency = match ($metaKey) {
            'price_eur' => 'EUR',
            'price_gbp' => 'GBP',
            default => 'USD',
        };

        $price = get_post_meta($postId, $metaKey, true);
        if (!is_numeric($price)) {
            // Try USD fallback
            $price = get_post_meta($postId, 'price_usd', true);
            if (!is_numeric($price)) {
                $price = get_post_meta($postId, 'price_retail_usd', true);
            }
            $fallbackCurrency = 'USD';
        }

        if (!is_numeric($price)) {
            return null;
        }

        return new PriceResult(
            marketplaceId: 'static',
            helmetRef: (string) get_post_field('post_name', $postId),
            countryCode: $countryCode,
            currency: $fallbackCurrency,
            price: (float) $price,
            availability: 'in_stock',
            capturedAt: gmdate('c'),
        );
    }
}
