<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace;

use Helmetsan\Core\Geo\GeoService;

/**
 * Maps a visitor's country to the marketplace connectors that serve it.
 *
 * Combines GeoService (IP → country) with ConnectorRegistry (country → connectors)
 * to power the "Where to Buy" section on the PDP.
 *
 * Also provides region-level fallbacks: if no connector directly supports
 * a user's country, the router broadens to region-level matching.
 */
final class MarketplaceRouter
{
    /** @var array<string, string[]>  Region → fallback country codes */
    private const REGION_FALLBACKS = [
        'NA'   => ['US', 'CA', 'MX'],
        'EU'   => ['UK', 'DE', 'FR', 'IT', 'ES', 'PL'],
        'APAC' => ['IN', 'JP', 'AU'],
        'SA'   => ['BR'],
        'ME'   => ['AE'],
        'AF'   => ['NG', 'KE', 'EG', 'MA', 'GH', 'UG', 'TZ'],
    ];

    public function __construct(
        private readonly GeoService $geo,
        private readonly ConnectorRegistry $registry,
    ) {
    }

    /**
     * Get connectors applicable to the current visitor.
     *
     * @return MarketplaceConnectorInterface[]
     */
    public function getConnectorsForVisitor(): array
    {
        $cc = $this->geo->getCountry();

        return $this->getConnectorsForCountry($cc);
    }

    /**
     * Get connectors for a specific country, with region-level fallback.
     *
     * @return MarketplaceConnectorInterface[]
     */
    public function getConnectorsForCountry(string $countryCode): array
    {
        $cc = strtoupper($countryCode);

        // Direct country match first
        $connectors = $this->registry->forCountry($cc);
        if (!empty($connectors)) {
            return $connectors;
        }

        // Fallback to region siblings
        $region = $this->geo->getRegion($cc);
        $siblings = self::REGION_FALLBACKS[$region] ?? [];

        $seen = [];
        foreach ($siblings as $sibling) {
            if ($sibling === $cc) {
                continue;
            }
            foreach ($this->registry->forCountry($sibling) as $c) {
                if (!isset($seen[$c->id()])) {
                    $seen[$c->id()] = $c;
                }
            }
        }

        return array_values($seen);
    }

    /**
     * Get the best price for a helmet for the current visitor.
     */
    public function bestPriceForVisitor(string $helmetRef): ?PriceResult
    {
        return $this->registry->bestPriceForCountry(
            $helmetRef,
            $this->geo->getCountry()
        );
    }

    /**
     * Get all offers sorted by price for the current visitor.
     *
     * @return PriceResult[]
     */
    public function allOffersForVisitor(string $helmetRef): array
    {
        return $this->registry->allOffersForCountry(
            $helmetRef,
            $this->geo->getCountry()
        );
    }

    /**
     * List marketplace IDs applicable to a country.
     *
     * @return string[]
     */
    public function marketplaceIdsForCountry(string $countryCode): array
    {
        $connectors = $this->getConnectorsForCountry($countryCode);

        return array_map(static fn(MarketplaceConnectorInterface $c) => $c->id(), $connectors);
    }

    /**
     * Get the full routing context for the current visitor.
     *
     * @return array{country: string, region: string, currency: string, marketplaces: string[]}
     */
    public function getRoutingContext(): array
    {
        $geo = $this->geo->getContext();
        $marketplaces = $this->marketplaceIdsForCountry($geo['country']);

        return array_merge($geo, ['marketplaces' => $marketplaces]);
    }
}
