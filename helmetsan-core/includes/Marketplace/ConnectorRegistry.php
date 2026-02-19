<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace;

/**
 * Central registry for marketplace connectors.
 *
 * Services call $registry->register(new AmazonConnector(...)) at boot time.
 * Then the engine can ask "give me all prices for country X" and the
 * registry fans-out to every connector that supports that country.
 */
final class ConnectorRegistry
{
    /** @var array<string, MarketplaceConnectorInterface> */
    private array $connectors = [];

    /**
     * Register a connector instance.
     */
    public function register(MarketplaceConnectorInterface $connector): void
    {
        $this->connectors[$connector->id()] = $connector;
    }

    /**
     * Get a specific connector by ID.
     */
    public function get(string $id): ?MarketplaceConnectorInterface
    {
        return $this->connectors[$id] ?? null;
    }

    /**
     * All registered connector IDs.
     *
     * @return string[]
     */
    public function ids(): array
    {
        return array_keys($this->connectors);
    }

    /**
     * All connectors that serve a given country.
     *
     * @return MarketplaceConnectorInterface[]
     */
    public function forCountry(string $countryCode): array
    {
        $code = strtoupper($countryCode);
        $result = [];

        foreach ($this->connectors as $c) {
            if ($c->supports($code)) {
                $result[] = $c;
            }
        }

        return $result;
    }

    /**
     * Fetch the best price for a helmet from every connector serving a country.
     *
     * Aggregates all prices and returns the cheapest available one, or null.
     *
     * @return PriceResult|null
     */
    public function bestPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        $connectors = $this->forCountry($countryCode);
        $best = null;

        foreach ($connectors as $c) {
            $result = $this->safeFetchPrice($c, $helmetRef);
            if ($result === null || !$result->isAvailable()) {
                continue;
            }
            if ($best === null || $result->price < $best->price) {
                $best = $result;
            }
        }

        return $best;
    }

    /**
     * Fetch all offers across every connector for a given country.
     *
     * @return PriceResult[]
     */
    public function allOffersForCountry(string $helmetRef, string $countryCode): array
    {
        $connectors = $this->forCountry($countryCode);
        $all = [];

        foreach ($connectors as $c) {
            $offers = $this->safeFetchOffers($c, $helmetRef);
            foreach ($offers as $offer) {
                $all[] = $offer;
            }
        }

        // Sort by price ascending
        usort($all, static fn(PriceResult $a, PriceResult $b) => $a->price <=> $b->price);

        return $all;
    }

    /**
     * Run health checks on all connectors.
     *
     * @return array<string, bool>
     */
    public function healthCheckAll(): array
    {
        $results = [];

        foreach ($this->connectors as $id => $c) {
            try {
                $results[$id] = $c->healthCheck();
            } catch (\Throwable $e) {
                $results[$id] = false;
            }
        }

        return $results;
    }

    /**
     * Fetch price with error isolation — a failing connector must not
     * take down the entire fan-out.
     */
    private function safeFetchPrice(MarketplaceConnectorInterface $c, string $helmetRef): ?PriceResult
    {
        try {
            return $c->fetchPrice($helmetRef);
        } catch (\Throwable $e) {
            do_action('helmetsan_connector_error', $c->id(), 'fetchPrice', $e);
            return null;
        }
    }

    /**
     * @return PriceResult[]
     */
    private function safeFetchOffers(MarketplaceConnectorInterface $c, string $helmetRef): array
    {
        try {
            return $c->fetchOffers($helmetRef);
        } catch (\Throwable $e) {
            do_action('helmetsan_connector_error', $c->id(), 'fetchOffers', $e);
            return [];
        }
    }
}
