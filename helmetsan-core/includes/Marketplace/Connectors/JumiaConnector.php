<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Jumia marketplace connector for African markets.
 *
 * Uses the Jumia Open Platform / KOL affiliate API to search for
 * helmets and retrieve pricing across multiple African countries.
 *
 * Supported countries: Nigeria (NG), Kenya (KE), Egypt (EG),
 * Morocco (MA), Ghana (GH), Uganda (UG), Tanzania (TZ).
 */
final class JumiaConnector implements MarketplaceConnectorInterface
{
    /** @var array<string, array{domain: string, currency: string}> */
    private const MARKETS = [
        'NG' => ['domain' => 'jumia.com.ng',  'currency' => 'NGN'],
        'KE' => ['domain' => 'jumia.co.ke',   'currency' => 'KES'],
        'EG' => ['domain' => 'jumia.com.eg',  'currency' => 'EGP'],
        'MA' => ['domain' => 'jumia.ma',      'currency' => 'MAD'],
        'GH' => ['domain' => 'jumia.com.gh',  'currency' => 'GHS'],
        'UG' => ['domain' => 'jumia.co.ug',   'currency' => 'UGX'],
        'TZ' => ['domain' => 'jumia.co.tz',   'currency' => 'TZS'],
    ];

    private const API_BASE     = 'https://affiliate-api.jumia.com';
    private const CACHE_PREFIX = 'helmetsan_jumia_';
    private const CACHE_TTL    = 3600;

    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config  Keys: api_key, affiliate_id, enabled_countries
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'jumia';
    }

    public function name(): string
    {
        return 'Jumia';
    }

    public function supportedCountries(): array
    {
        $enabled = $this->config['enabled_countries'] ?? array_keys(self::MARKETS);

        return array_values(array_intersect(
            is_array($enabled) ? $enabled : array_keys(self::MARKETS),
            array_keys(self::MARKETS)
        ));
    }

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->supportedCountries(), true);
    }

    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        $countries = $this->supportedCountries();
        if (empty($countries)) {
            return null;
        }

        return $this->fetchPriceForCountry($helmetRef, $countries[0]);
    }

    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        $cc = strtoupper($countryCode);
        if (!isset(self::MARKETS[$cc])) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . $helmetRef . '_' . $cc;
        $cached   = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $this->arrayToResult($cached);
        }

        $results = $this->searchProducts($helmetRef, $cc);
        if (empty($results)) {
            return null;
        }

        $best = $results[0];
        set_transient($cacheKey, $best->toArray(), self::CACHE_TTL);

        return $best;
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        $all = [];
        foreach ($this->supportedCountries() as $cc) {
            $results = $this->searchProducts($helmetRef, $cc);
            foreach ($results as $r) {
                $all[] = $r;
            }
        }

        usort($all, static fn(PriceResult $a, PriceResult $b) => $a->price <=> $b->price);

        return $all;
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        // Jumia API doesn't support EAN search — fall back to keyword
        return $this->searchProducts($ean, $this->supportedCountries()[0] ?? 'NG');
    }

    public function healthCheck(): bool
    {
        $apiKey = $this->config['api_key'] ?? '';

        return $apiKey !== '';
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * @return PriceResult[]
     */
    private function searchProducts(string $helmetRef, string $cc): array
    {
        $apiKey = $this->config['api_key'] ?? '';
        if ($apiKey === '') {
            return [];
        }

        $market = self::MARKETS[$cc] ?? null;
        if ($market === null) {
            return [];
        }

        // Convert slug to search phrase
        $phrase = str_replace('-', ' ', $helmetRef) . ' helmet';

        $url = self::API_BASE . '/products/search?' . http_build_query([
            'country'  => strtolower($cc),
            'q'        => $phrase,
            'category' => 'motorcycle-helmets',
            'limit'    => 5,
            'sort'     => 'price_asc',
        ]);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            do_action('helmetsan_connector_error', $this->id(), 'searchProducts', $response);
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return [];
        }

        $body  = json_decode(wp_remote_retrieve_body($response), true);
        $items = $body['products'] ?? $body['data'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        $affiliateId = $this->config['affiliate_id'] ?? '';
        $results     = [];

        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? $item['current_price'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $mrp = isset($item['old_price']) ? (float) $item['old_price'] : null;
            if ($mrp !== null && $mrp <= 0) {
                $mrp = null;
            }

            $productUrl = (string) ($item['url'] ?? $item['product_url'] ?? '');
            if ($productUrl === '') {
                $slug = $item['slug'] ?? $item['sku'] ?? '';
                $productUrl = 'https://www.' . $market['domain'] . '/' . $slug;
            }

            $affiliateUrl = $productUrl;
            if ($affiliateId !== '') {
                $sep = str_contains($productUrl, '?') ? '&' : '?';
                $affiliateUrl = $productUrl . $sep . 'aff_id=' . rawurlencode($affiliateId);
            }

            $results[] = new PriceResult(
                marketplaceId: 'jumia-' . strtolower($cc),
                helmetRef:     $helmetRef,
                countryCode:   $cc,
                currency:      $market['currency'],
                price:         $price,
                mrp:           $mrp,
                url:           $productUrl,
                affiliateUrl:  $affiliateUrl,
                availability:  'in_stock',
                sellerName:    (string) ($item['seller_name'] ?? $item['brand'] ?? 'Jumia Seller'),
                condition:     'new',
                capturedAt:    gmdate('c'),
                extra:         [
                    'jumia_sku' => $item['sku'] ?? '',
                    'title'     => $item['name'] ?? '',
                    'rating'    => $item['rating'] ?? null,
                ],
            );
        }

        return $results;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function arrayToResult(array $data): PriceResult
    {
        return new PriceResult(
            marketplaceId: (string) ($data['marketplace_id'] ?? 'jumia'),
            helmetRef:     (string) ($data['helmet_ref'] ?? ''),
            countryCode:   (string) ($data['country_code'] ?? 'NG'),
            currency:      (string) ($data['currency'] ?? 'NGN'),
            price:         (float) ($data['price'] ?? 0),
            mrp:           isset($data['mrp']) ? (float) $data['mrp'] : null,
            url:           (string) ($data['url'] ?? ''),
            affiliateUrl:  (string) ($data['affiliate_url'] ?? ''),
            availability:  (string) ($data['availability'] ?? 'unknown'),
            sellerName:    (string) ($data['seller_name'] ?? ''),
            condition:     (string) ($data['condition'] ?? 'new'),
            capturedAt:    (string) ($data['captured_at'] ?? ''),
            extra:         isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : [],
        );
    }
}
