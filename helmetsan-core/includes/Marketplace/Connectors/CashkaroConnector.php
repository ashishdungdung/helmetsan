<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Cashkaro Affiliate Connector.
 *
 * Scrapes or uses API to find cashback deals or routes
 * traffic via Cashkaro's affiliate tracking for the Indian market.
 */
final class CashkaroConnector implements MarketplaceConnectorInterface
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'cashkaro';
    }

    public function name(): string
    {
        return 'Cashkaro';
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
        return null;
    }

    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        return null;
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        return [];
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffersForCountry(string $helmetRef, string $countryCode): array
    {
        return [];
    }

    public function getCashbackUrl(string $retailerUrl): string
    {
        $affId = $this->config['cashkaro_id'] ?? '';
        if ($affId === '') {
            return $retailerUrl;
        }

        // Example routing pattern for Cashkaro
        return 'https://cashkaro.com/out?retailer=' . rawurlencode($retailerUrl) . '&aff_id=' . rawurlencode($affId);
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
