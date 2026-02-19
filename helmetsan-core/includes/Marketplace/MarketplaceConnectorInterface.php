<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace;

/**
 * Contract every marketplace connector must implement.
 *
 * The engine treats all marketplaces identically through this interface.
 * Connectors handle authentication, rate-limiting, and response mapping
 * internally — the rest of the system only sees PriceResult objects.
 */
interface MarketplaceConnectorInterface
{
    /**
     * Unique marketplace identifier, e.g. "amazon-us", "allegro-pl".
     */
    public function id(): string;

    /**
     * Human-readable marketplace name, e.g. "Amazon US", "Allegro".
     */
    public function name(): string;

    /**
     * ISO 3166-1 alpha-2 country codes this connector serves.
     *
     * @return string[]
     */
    public function supportedCountries(): array;

    /**
     * Whether this connector serves the given country.
     */
    public function supports(string $countryCode): bool;

    /**
     * Fetch the current price for a specific helmet.
     *
     * @param string $helmetRef  Helmetsan slug, e.g. "shoei-rf-1400"
     * @return PriceResult|null  null if no listing found
     */
    public function fetchPrice(string $helmetRef): ?PriceResult;

    /**
     * Fetch all available offers (multiple sellers) for a helmet.
     *
     * @param string $helmetRef
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array;

    /**
     * Search for a helmet by EAN/UPC barcode.
     *
     * @param string $ean
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array;

    /**
     * Quick health/connectivity check.
     * Should return true if the API is reachable and credentials are valid.
     */
    public function healthCheck(): bool;
}
