<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Generic CSV/XML affiliate product-feed connector.
 *
 * Specialist motorcycle gear stores (RevZilla, Cycle Gear, FC-Moto, etc.)
 * export product feeds that we ingest on a schedule. This connector reads
 * the cached feed data from wp_options and returns PriceResult objects.
 *
 * Feed import happens via SchedulerService cron → importFeed().
 * At query time, fetchPrice() / fetchOffers() simply reads the cache.
 */
final class AffiliateFeedConnector implements MarketplaceConnectorInterface
{
    private const OPTION_FEEDS    = 'helmetsan_affiliate_feeds';
    private const OPTION_PREFIX   = 'helmetsan_feed_cache_';
    private const TRANSIENT_TTL   = 21600; // 6 hours

    /** @var array<string,mixed> */
    private array $feedConfig;

    /**
     * @param string               $feedId       e.g. "revzilla-us"
     * @param string               $feedName     e.g. "RevZilla"
     * @param string[]             $countries    e.g. ["US"]
     * @param array<string,mixed>  $feedConfig   url, column_map, affiliate_network, affiliate_params
     */
    public function __construct(
        private readonly string $feedId,
        private readonly string $feedName,
        private readonly array  $countries,
        array $feedConfig = [],
    ) {
        $this->feedConfig = $feedConfig;
    }

    public function id(): string
    {
        return $this->feedId;
    }

    public function name(): string
    {
        return $this->feedName;
    }

    public function supportedCountries(): array
    {
        return $this->countries;
    }

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->countries, true);
    }

    /**
     * Fetch the best price from cached feed data.
     */
    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        $products = $this->getCachedProducts();
        $match    = $this->findMatch($products, $helmetRef);

        if ($match === null) {
            return null;
        }

        return $this->toResult($match, $helmetRef);
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        $products = $this->getCachedProducts();
        $matches  = $this->findAllMatches($products, $helmetRef);
        $results  = [];

        foreach ($matches as $m) {
            $result = $this->toResult($m, $helmetRef);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        if (!$this->supports($countryCode)) {
            return null;
        }

        return $this->fetchPrice($helmetRef);
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffersForCountry(string $helmetRef, string $countryCode): array
    {
        if (!$this->supports($countryCode)) {
            return [];
        }

        return $this->fetchOffers($helmetRef);
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        $products = $this->getCachedProducts();
        $results  = [];

        foreach ($products as $p) {
            $pEan = (string) ($p['ean'] ?? $p['upc'] ?? $p['gtin'] ?? '');
            if ($pEan !== '' && $pEan === $ean) {
                $result = $this->toResult($p, '');
                if ($result !== null) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    public function healthCheck(): bool
    {
        $url = $this->feedConfig['url'] ?? '';
        if ($url === '') {
            return false;
        }

        $response = wp_remote_head($url, ['timeout' => 10]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    // ─── Feed Import (called by Scheduler) ──────────────────────────────

    /**
     * Download and parse the product feed, caching results in wp_options.
     *
     * @return array{ok: bool, imported: int, message?: string}
     */
    public function importFeed(): array
    {
        $url = $this->feedConfig['url'] ?? '';
        if ($url === '') {
            return ['ok' => false, 'imported' => 0, 'message' => 'No feed URL configured'];
        }

        $response = wp_remote_get($url, ['timeout' => 60]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'imported' => 0, 'message' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $contentType = wp_remote_retrieve_header($response, 'content-type');

        $products = str_contains((string) $contentType, 'xml')
            ? $this->parseXmlFeed($body)
            : $this->parseCsvFeed($body);

        if (empty($products)) {
            return ['ok' => false, 'imported' => 0, 'message' => 'Empty or unparseable feed'];
        }

        // Cache parsed products
        update_option(self::OPTION_PREFIX . $this->feedId, [
            'products'   => $products,
            'updated_at' => gmdate('c'),
            'count'      => count($products),
        ], false);

        return ['ok' => true, 'imported' => count($products)];
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * @return array<int, array<string,mixed>>
     */
    private function getCachedProducts(): array
    {
        $cache = get_option(self::OPTION_PREFIX . $this->feedId, []);
        if (!is_array($cache) || !isset($cache['products'])) {
            return [];
        }

        return is_array($cache['products']) ? $cache['products'] : [];
    }

    /**
     * Find the single best matching product for a helmet slug.
     *
     * @param array<int, array<string,mixed>> $products
     * @return array<string,mixed>|null
     */
    private function findMatch(array $products, string $helmetRef): ?array
    {
        $matches = $this->findAllMatches($products, $helmetRef);

        if (empty($matches)) {
            return null;
        }

        // Return cheapest match
        usort($matches, static fn($a, $b) => ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0)));

        return $matches[0];
    }

    /**
     * @param array<int, array<string,mixed>> $products
     * @return array<int, array<string,mixed>>
     */
    private function findAllMatches(array $products, string $helmetRef): array
    {
        if ($helmetRef === '') {
            return [];
        }

        $matches = [];
        $slug = sanitize_title($helmetRef);
        $map  = $this->feedConfig['column_map'] ?? [];

        // Look for our ref in the mapped column, or fallback to slug-matching product name
        $refColumn = $map['helmetsan_id'] ?? '';

        foreach ($products as $p) {
            // Direct ID match
            if ($refColumn !== '' && isset($p[$refColumn]) && sanitize_title((string) $p[$refColumn]) === $slug) {
                $matches[] = $p;
                continue;
            }

            // Slug match on product name
            $name = (string) ($p[$map['name'] ?? 'name'] ?? $p['product_name'] ?? '');
            if ($name !== '' && str_contains(sanitize_title($name), $slug)) {
                $matches[] = $p;
            }
        }

        return $matches;
    }

    /**
     * @param array<string,mixed> $product
     */
    private function toResult(array $product, string $helmetRef): ?PriceResult
    {
        $map = $this->feedConfig['column_map'] ?? [];

        $price = (float) ($product[$map['price'] ?? 'price'] ?? 0);
        if ($price <= 0) {
            return null;
        }

        $mrp = isset($product[$map['mrp'] ?? 'retail_price'])
            ? (float) $product[$map['mrp'] ?? 'retail_price']
            : null;
        if ($mrp !== null && $mrp <= 0) {
            $mrp = null;
        }

        $url = (string) ($product[$map['url'] ?? 'url'] ?? $product['product_url'] ?? '');
        $affParams = $this->feedConfig['affiliate_params'] ?? [];
        $affiliateUrl = $url;
        if (!empty($affParams) && $url !== '') {
            $sep = str_contains($url, '?') ? '&' : '?';
            $affiliateUrl = $url . $sep . http_build_query($affParams);
        }

        $availability = (string) ($product[$map['availability'] ?? 'availability'] ?? 'unknown');
        if (stripos($availability, 'in') !== false) {
            $availability = 'in_stock';
        } elseif (stripos($availability, 'out') !== false) {
            $availability = 'out_of_stock';
        }

        return new PriceResult(
            marketplaceId: $this->feedId,
            helmetRef:     $helmetRef,
            countryCode:   $this->countries[0] ?? 'US',
            currency:      (string) ($this->feedConfig['currency'] ?? 'USD'),
            price:         $price,
            mrp:           $mrp,
            url:           $url,
            affiliateUrl:  $affiliateUrl,
            availability:  $availability,
            sellerName:    $this->feedName,
            condition:     'new',
            capturedAt:    gmdate('c'),
            extra:         [
                'feed_id'    => $this->feedId,
                'product_id' => $product[$map['product_id'] ?? 'id'] ?? '',
                'ean'        => $product[$map['ean'] ?? 'ean'] ?? '',
            ],
        );
    }

    /**
     * @return array<int, array<string,string>>
     */
    private function parseCsvFeed(string $body): array
    {
        $lines = explode("\n", $body);
        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        if ($header === false || empty($header)) {
            return [];
        }

        $products = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = str_getcsv($line);
            if ($row === false || count($row) !== count($header)) {
                continue;
            }
            $products[] = array_combine($header, $row);
        }

        return $products;
    }

    /**
     * @return array<int, array<string,string>>
     */
    private function parseXmlFeed(string $body): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            return [];
        }

        $products = [];

        // Try common feed structures
        $items = $xml->xpath('//product') ?: $xml->xpath('//item') ?: [];
        foreach ($items as $item) {
            $row = [];
            foreach ($item->children() as $child) {
                $row[$child->getName()] = (string) $child;
            }
            if (!empty($row)) {
                $products[] = $row;
            }
        }

        return $products;
    }
}
