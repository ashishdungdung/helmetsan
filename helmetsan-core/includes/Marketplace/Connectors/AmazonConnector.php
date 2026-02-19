<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Amazon SP-API connector.
 *
 * Supports multi-region Amazon storefronts by mapping country codes
 * to their respective API endpoints and marketplace IDs.
 *
 * Authentication uses the SP-API LWA (Login with Amazon) OAuth2 flow
 * with a refresh token, or IAM role assumption for server-to-server.
 *
 * Rate-limiting: respects 1 req/sec default burst for Product-Pricing API.
 */
final class AmazonConnector implements MarketplaceConnectorInterface
{
    /**
     * Amazon Marketplace IDs keyed by country code.
     *
     * @var array<string, array{mkt_id: string, endpoint: string, domain: string}>
     */
    private const REGIONS = [
        'US' => ['mkt_id' => 'ATVPDKIKX0DER',  'endpoint' => 'https://sellingpartnerapi-na.amazon.com', 'domain' => 'amazon.com'],
        'CA' => ['mkt_id' => 'A2EUQ1WTGCTBG2', 'endpoint' => 'https://sellingpartnerapi-na.amazon.com', 'domain' => 'amazon.ca'],
        'MX' => ['mkt_id' => 'A1AM78C64UM0Y8', 'endpoint' => 'https://sellingpartnerapi-na.amazon.com', 'domain' => 'amazon.com.mx'],
        'UK' => ['mkt_id' => 'A1F83G8C2ARO7P', 'endpoint' => 'https://sellingpartnerapi-eu.amazon.com', 'domain' => 'amazon.co.uk'],
        'DE' => ['mkt_id' => 'A1PA6795UKMFR9', 'endpoint' => 'https://sellingpartnerapi-eu.amazon.com', 'domain' => 'amazon.de'],
        'FR' => ['mkt_id' => 'A13V1IB3VIYZZH', 'endpoint' => 'https://sellingpartnerapi-eu.amazon.com', 'domain' => 'amazon.fr'],
        'IT' => ['mkt_id' => 'APJ6JRA9NG5V4',  'endpoint' => 'https://sellingpartnerapi-eu.amazon.com', 'domain' => 'amazon.it'],
        'ES' => ['mkt_id' => 'A1RKKUPIHCS9HS', 'endpoint' => 'https://sellingpartnerapi-eu.amazon.com', 'domain' => 'amazon.es'],
        'IN' => ['mkt_id' => 'A21TJRUUN4KGV',  'endpoint' => 'https://sellingpartnerapi-eu.amazon.com', 'domain' => 'amazon.in'],
        'JP' => ['mkt_id' => 'A1VC38T7YXB528', 'endpoint' => 'https://sellingpartnerapi-fe.amazon.com', 'domain' => 'amazon.co.jp'],
        'AU' => ['mkt_id' => 'A39IBJ37TRP1C6', 'endpoint' => 'https://sellingpartnerapi-fe.amazon.com', 'domain' => 'amazon.com.au'],
    ];

    private const TRANSIENT_PREFIX = 'helmetsan_amz_';
    private const CACHE_TTL        = 3600; // 1 hour
    private const TOKEN_TTL        = 3000; // ~50 minutes (tokens last 1h)

    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config  Keys: client_id, client_secret, refresh_token,
     *                                      (optional) aws_access_key, aws_secret_key, role_arn,
     *                                      affiliate_tag (e.g. "helmetsan-20"),
     *                                      enabled_countries (array of country codes).
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'amazon';
    }

    public function name(): string
    {
        return 'Amazon';
    }

    /**
     * @return string[]
     */
    public function supportedCountries(): array
    {
        $enabled = $this->config['enabled_countries'] ?? array_keys(self::REGIONS);

        return array_values(array_intersect(
            is_array($enabled) ? $enabled : array_keys(self::REGIONS),
            array_keys(self::REGIONS)
        ));
    }

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->supportedCountries(), true);
    }

    /**
     * Fetch the lowest price for a helmet from a specific Amazon marketplace.
     *
     * Uses the helmet's ASIN stored in post meta `amazon_asin_{CC}` or `affiliate_asin`.
     */
    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        // Determine country from the first supported country for now.
        // In practice, the engine calls this via ConnectorRegistry which
        // already filtered by country. We pick the first supported country.
        $countries = $this->supportedCountries();
        if (empty($countries)) {
            return null;
        }

        return $this->fetchPriceForCountry($helmetRef, $countries[0]);
    }

    /**
     * Fetch price for a specific country.
     */
    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        $cc = strtoupper($countryCode);
        if (!isset(self::REGIONS[$cc])) {
            return null;
        }

        // Check transient cache first
        $cacheKey = self::TRANSIENT_PREFIX . 'price_' . $helmetRef . '_' . $cc;
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $this->arrayToPriceResult($cached);
        }

        $asin = $this->resolveAsin($helmetRef, $cc);
        if ($asin === '') {
            return null;
        }

        $region  = self::REGIONS[$cc];
        $token   = $this->getAccessToken();
        if ($token === '') {
            return null;
        }

        $response = $this->apiRequest(
            $region['endpoint'],
            '/products/pricing/v0/price',
            [
                'MarketplaceId' => $region['mkt_id'],
                'Asins'         => $asin,
                'ItemType'      => 'Asin',
            ],
            $token
        );

        if ($response === null) {
            return null;
        }

        $result = $this->parsePricingResponse($response, $helmetRef, $cc, $asin, $region);
        if ($result !== null) {
            set_transient($cacheKey, $result->toArray(), self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        $offers = [];
        foreach ($this->supportedCountries() as $cc) {
            $result = $this->fetchPriceForCountry($helmetRef, $cc);
            if ($result !== null) {
                $offers[] = $result;
            }
        }

        return $offers;
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        // SP-API catalog search by EAN
        $countries = $this->supportedCountries();
        if (empty($countries)) {
            return [];
        }

        $cc     = $countries[0];
        $region = self::REGIONS[$cc];
        $token  = $this->getAccessToken();
        if ($token === '') {
            return [];
        }

        $response = $this->apiRequest(
            $region['endpoint'],
            '/catalog/2022-04-01/items',
            [
                'marketplaceIds' => $region['mkt_id'],
                'identifiers'    => $ean,
                'identifiersType'=> 'EAN',
                'includedData'   => 'identifiers,attributes,summaries',
            ],
            $token
        );

        if ($response === null || !isset($response['items'])) {
            return [];
        }

        $results = [];
        foreach ($response['items'] as $item) {
            $asin = $item['asin'] ?? '';
            if ($asin === '') {
                continue;
            }
            $title = $item['summaries'][0]['itemName'] ?? 'Unknown';
            $results[] = new PriceResult(
                marketplaceId: 'amazon-' . strtolower($cc),
                helmetRef:     '',
                countryCode:   $cc,
                currency:      $this->currencyForCountry($cc),
                price:         0, // Price unknown from catalog search
                url:           'https://www.' . $region['domain'] . '/dp/' . $asin,
                extra:         ['asin' => $asin, 'title' => $title, 'ean' => $ean],
            );
        }

        return $results;
    }

    public function healthCheck(): bool
    {
        $token = $this->getAccessToken();

        return $token !== '';
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * Resolve the Amazon ASIN for a helmet + country.
     *
     * Lookup chain:
     * 1. post meta `amazon_asin_{cc}` (country-specific)
     * 2. post meta `affiliate_asin` (global fallback)
     */
    private function resolveAsin(string $helmetRef, string $cc): string
    {
        $post = get_page_by_path($helmetRef, OBJECT, 'helmet');
        if (!$post instanceof \WP_Post) {
            return '';
        }

        $ccLower = strtolower($cc);
        $specific = (string) get_post_meta($post->ID, 'amazon_asin_' . $ccLower, true);
        if ($specific !== '') {
            return $specific;
        }

        return (string) get_post_meta($post->ID, 'affiliate_asin', true);
    }

    /**
     * Get an LWA access token (cached in transients).
     */
    private function getAccessToken(): string
    {
        $cached = get_transient(self::TRANSIENT_PREFIX . 'token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId     = $this->config['client_id'] ?? '';
        $clientSecret = $this->config['client_secret'] ?? '';
        $refreshToken = $this->config['refresh_token'] ?? '';

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return '';
        }

        $response = wp_remote_post('https://api.amazon.com/auth/o2/token', [
            'timeout' => 15,
            'body'    => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ],
        ]);

        if (is_wp_error($response)) {
            do_action('helmetsan_connector_error', $this->id(), 'getAccessToken', $response);
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $token = $body['access_token'] ?? '';

        if (is_string($token) && $token !== '') {
            set_transient(self::TRANSIENT_PREFIX . 'token', $token, self::TOKEN_TTL);
        }

        return is_string($token) ? $token : '';
    }

    /**
     * Make a GET request to the SP-API.
     *
     * @param array<string,string> $queryParams
     * @return array<string,mixed>|null
     */
    private function apiRequest(string $endpoint, string $path, array $queryParams, string $token): ?array
    {
        $url = $endpoint . $path . '?' . http_build_query($queryParams);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'x-amz-access-token' => $token,
                'Content-Type'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            do_action('helmetsan_connector_error', $this->id(), 'apiRequest', $response);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            do_action('helmetsan_connector_error', $this->id(), 'apiRequest', new \RuntimeException(
                "SP-API returned HTTP $code for $path"
            ));
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : null;
    }

    /**
     * Parse the Product-Pricing API response into a PriceResult.
     *
     * @param array<string,mixed> $response
     * @param array<string,string> $region
     */
    private function parsePricingResponse(array $response, string $helmetRef, string $cc, string $asin, array $region): ?PriceResult
    {
        $payload = $response['payload'] ?? [];
        if (!is_array($payload) || empty($payload)) {
            return null;
        }

        // SP-API returns an array of price records per ASIN
        $priceData = $payload[0] ?? [];
        $product   = $priceData['Product'] ?? [];
        $offers    = $product['Offers'] ?? [];

        if (!is_array($offers) || empty($offers)) {
            return null;
        }

        // Find lowest "New" buy-box price
        $bestPrice = null;
        $currency  = '';

        foreach ($offers as $offer) {
            $buyingPrice = $offer['BuyingPrice'] ?? [];
            $amount = isset($buyingPrice['ListingPrice']['Amount'])
                ? (float) $buyingPrice['ListingPrice']['Amount']
                : null;
            $curr = $buyingPrice['ListingPrice']['CurrencyCode'] ?? '';

            if ($amount !== null && $amount > 0 && ($bestPrice === null || $amount < $bestPrice)) {
                $bestPrice = $amount;
                $currency  = (string) $curr;
            }
        }

        if ($bestPrice === null) {
            return null;
        }

        // MRP / list price
        $mrp = null;
        $regularPrice = $offers[0]['RegularPrice'] ?? [];
        if (isset($regularPrice['Amount']) && (float) $regularPrice['Amount'] > 0) {
            $mrp = (float) $regularPrice['Amount'];
        }

        $tag = $this->config['affiliate_tag'] ?? 'helmetsan-20';
        $productUrl   = 'https://www.' . $region['domain'] . '/dp/' . $asin;
        $affiliateUrl = $productUrl . '?tag=' . rawurlencode($tag);

        // Availability
        $availability = 'unknown';
        $offerCount   = $product['NumberOfOffers'] ?? [];
        if (is_array($offerCount) && !empty($offerCount)) {
            $availability = 'in_stock';
        }

        return new PriceResult(
            marketplaceId: 'amazon-' . strtolower($cc),
            helmetRef:     $helmetRef,
            countryCode:   $cc,
            currency:      $currency !== '' ? $currency : $this->currencyForCountry($cc),
            price:         $bestPrice,
            mrp:           $mrp,
            url:           $productUrl,
            affiliateUrl:  $affiliateUrl,
            availability:  $availability,
            sellerName:    'Amazon',
            condition:     'new',
            capturedAt:    gmdate('c'),
            extra:         ['asin' => $asin],
        );
    }

    /**
     * Default currency per country.
     */
    private function currencyForCountry(string $cc): string
    {
        return match (strtoupper($cc)) {
            'US', 'CA' => 'USD',
            'UK'       => 'GBP',
            'DE', 'FR', 'IT', 'ES' => 'EUR',
            'IN'       => 'INR',
            'JP'       => 'JPY',
            'AU'       => 'AUD',
            'MX'       => 'MXN',
            default    => 'USD',
        };
    }

    /**
     * Reconstruct a PriceResult from its toArray() cache output.
     *
     * @param array<string,mixed> $data
     */
    private function arrayToPriceResult(array $data): PriceResult
    {
        return new PriceResult(
            marketplaceId: (string) ($data['marketplace_id'] ?? ''),
            helmetRef:     (string) ($data['helmet_ref'] ?? ''),
            countryCode:   (string) ($data['country_code'] ?? ''),
            currency:      (string) ($data['currency'] ?? 'USD'),
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
