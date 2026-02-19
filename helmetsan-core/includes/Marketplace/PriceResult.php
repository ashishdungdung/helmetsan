<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace;

/**
 * Immutable value object returned by every marketplace connector.
 *
 * Normalises the heterogeneous API responses into a single shape
 * that the rest of the engine (PriceService, CommerceService, frontend)
 * can consume without knowing which connector produced it.
 */
final class PriceResult
{
    /**
     * @param string      $marketplaceId  e.g. "amazon-us", "revzilla-us"
     * @param string      $helmetRef      Helmetsan helmet slug, e.g. "shoei-rf-1400"
     * @param string      $countryCode    ISO 3166-1 alpha-2
     * @param string      $currency       ISO 4217, e.g. "USD"
     * @param float       $price          Current / offer price
     * @param float|null  $mrp            Retail / list price (null if unknown)
     * @param string      $url            Direct product URL on the marketplace
     * @param string      $affiliateUrl   Affiliate-tagged URL (may equal $url)
     * @param string      $availability   "in_stock" | "out_of_stock" | "pre_order" | "unknown"
     * @param string      $sellerName     Seller / shop name
     * @param string      $condition      "new" | "used" | "refurbished"
     * @param string      $capturedAt     ISO 8601 timestamp
     * @param array<string,mixed> $extra  Connector-specific metadata
     */
    public function __construct(
        public readonly string $marketplaceId,
        public readonly string $helmetRef,
        public readonly string $countryCode,
        public readonly string $currency,
        public readonly float  $price,
        public readonly ?float $mrp = null,
        public readonly string $url = '',
        public readonly string $affiliateUrl = '',
        public readonly string $availability = 'unknown',
        public readonly string $sellerName = '',
        public readonly string $condition = 'new',
        public readonly string $capturedAt = '',
        public readonly array  $extra = [],
    ) {
    }

    /**
     * Discount percentage (0 if no MRP or MRP <= price).
     */
    public function discountPercent(): float
    {
        if ($this->mrp === null || $this->mrp <= 0 || $this->mrp <= $this->price) {
            return 0.0;
        }

        return round((($this->mrp - $this->price) / $this->mrp) * 100, 2);
    }

    /**
     * True when the item can be purchased right now.
     */
    public function isAvailable(): bool
    {
        return $this->availability === 'in_stock' || $this->availability === 'pre_order';
    }

    /**
     * Convert to the associative array shape used by CommerceService.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'marketplace_id' => $this->marketplaceId,
            'helmet_ref'     => $this->helmetRef,
            'country_code'   => $this->countryCode,
            'currency'       => $this->currency,
            'price'          => $this->price,
            'mrp'            => $this->mrp,
            'url'            => $this->url,
            'affiliate_url'  => $this->affiliateUrl,
            'availability'   => $this->availability,
            'seller_name'    => $this->sellerName,
            'condition'      => $this->condition,
            'captured_at'    => $this->capturedAt !== '' ? $this->capturedAt : gmdate('c'),
            'discount_pct'   => $this->discountPercent(),
            'extra'          => $this->extra,
        ];
    }
}
