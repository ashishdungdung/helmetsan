<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Flipkart Affiliate Connector.
 *
 * Supports fetching product deals for the Indian market via the Flipkart API or scraping,
 * and routing links through Flipkart's affiliate program.
 */
final class FlipkartConnector implements MarketplaceConnectorInterface
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'flipkart';
    }

    public function name(): string
    {
        return 'Flipkart';
    }

    /**
     * @return string[]
     */
    public function supportedCountries(): array
    {
        return ['IN'];
    }

    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'IN';
    }

    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        return $this->fetchPriceForCountry($helmetRef, 'IN');
    }

    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        if (strtoupper($countryCode) !== 'IN') {
            return null;
        }

        $post = get_page_by_path($helmetRef, OBJECT, 'helmet');
        if (!$post instanceof \WP_Post) {
            return null;
        }

        $flipkartUrl = (string) get_post_meta($post->ID, 'flipkart_url', true);
        if ($flipkartUrl === '') {
            return null;
        }

        $affId = $this->config['affiliate_id'] ?? '';
        $affiliateUrl = $flipkartUrl;
        if ($affId !== '') {
            $affiliateUrl .= (str_contains($flipkartUrl, '?') ? '&' : '?') . 'affid=' . rawurlencode($affId);
        }

        return new PriceResult(
            marketplaceId: 'flipkart-in',
            helmetRef:     $helmetRef,
            countryCode:   'IN',
            currency:      'INR',
            price:         0, // Placeholder until API is fully implemented
            mrp:           null,
            url:           $flipkartUrl,
            affiliateUrl:  $affiliateUrl,
            availability:  'unknown',
            sellerName:    'Flipkart',
            condition:     'new',
            capturedAt:    gmdate('c')
        );
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        $result = $this->fetchPriceForCountry($helmetRef, 'IN');
        return $result !== null ? [$result] : [];
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffersForCountry(string $helmetRef, string $countryCode): array
    {
        $result = $this->fetchPriceForCountry($helmetRef, $countryCode);
        return $result !== null ? [$result] : [];
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        return [];
    }

    public function healthCheck(): bool
    {
        return true;
    }
}
