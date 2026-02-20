<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Amazon India Connector.
 *
 * Provides a dedicated integration for Amazon IN, utilizing the base AmazonConnector
 * under the hood, but allowing for India-specific routing, caching, and analytics.
 */
final class AmazonIndiaConnector implements MarketplaceConnectorInterface
{
    private AmazonConnector $baseConnector;

    /** @var array<string,mixed> */
    private array $config;

    public function __construct(AmazonConnector $baseConnector, array $config)
    {
        $this->baseConnector = $baseConnector;
        $this->config = $config;
    }

    public function id(): string
    {
        return 'amazon-in';
    }

    public function name(): string
    {
        return 'Amazon India';
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
        return $this->baseConnector->fetchPriceForCountry($helmetRef, 'IN');
    }

    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        if (strtoupper($countryCode) !== 'IN') {
            return null;
        }

        return $this->baseConnector->fetchPriceForCountry($helmetRef, 'IN');
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        return $this->baseConnector->fetchOffersForCountry($helmetRef, 'IN');
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffersForCountry(string $helmetRef, string $countryCode): array
    {
        if (strtoupper($countryCode) !== 'IN') {
            return [];
        }

        return $this->baseConnector->fetchOffersForCountry($helmetRef, 'IN');
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        return $this->baseConnector->searchByEan($ean);
    }

    public function healthCheck(): bool
    {
        return $this->baseConnector->healthCheck();
    }
}
