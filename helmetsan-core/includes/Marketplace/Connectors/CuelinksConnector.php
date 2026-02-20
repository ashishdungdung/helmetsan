<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Cuelinks Affiliate Connector.
 *
 * Cuelinks acts as a sub-affiliate network. It wraps existing URLs
 * to track sales without needing direct merchant integration.
 */
final class CuelinksConnector implements MarketplaceConnectorInterface
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'cuelinks';
    }

    public function name(): string
    {
        return 'Cuelinks';
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
        return null; // Used for wrapping affiliate links, not fetching live prices directly
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

    public function buildRoutedUrl(string $originalUrl): string
    {
        $channelId = $this->config['cuelinks_channel_id'] ?? '';
        if ($channelId === '') {
            return $originalUrl;
        }

        return 'https://clnk.in/' . rawurlencode($channelId) . '?url=' . rawurlencode($originalUrl);
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
