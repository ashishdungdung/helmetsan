<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Amazon Creator API (v3.1) Connector.
 *
 * Successor to PA-API 5.0 and SP-API Pricing.
 * Uses OAuth 2.0 client credentials grant with scope `creatorsapi::default`
 * and unified global REST endpoint at https://creatorsapi.amazon/catalog/v1/
 */
final class AmazonCreatorConnector implements MarketplaceConnectorInterface
{
    public const TOKEN_ENDPOINT = 'https://api.amazon.com/auth/o2/token';
    public const API_ENDPOINT   = 'https://creatorsapi.amazon/catalog/v1/';

    /**
     * Regional configuration mapped by country code.
     *
     * @var array<string, array{domain: string, currency: string, tag: string}>
     */
    private const MARKETPLACES = [
        'US' => ['domain' => 'www.amazon.com',   'currency' => 'USD', 'tag' => 'vtete-20'],
        'CA' => ['domain' => 'www.amazon.ca',    'currency' => 'CAD', 'tag' => 'vtete-20'],
        'UK' => ['domain' => 'www.amazon.co.uk', 'currency' => 'GBP', 'tag' => 'vtete-21'],
        'GB' => ['domain' => 'www.amazon.co.uk', 'currency' => 'GBP', 'tag' => 'vtete-21'],
        'DE' => ['domain' => 'www.amazon.de',    'currency' => 'EUR', 'tag' => 'vtete-20'],
        'FR' => ['domain' => 'www.amazon.fr',    'currency' => 'EUR', 'tag' => 'vtete-20'],
        'IT' => ['domain' => 'www.amazon.it',    'currency' => 'EUR', 'tag' => 'vtete-20'],
        'ES' => ['domain' => 'www.amazon.es',    'currency' => 'EUR', 'tag' => 'vtete-20'],
        'NL' => ['domain' => 'www.amazon.nl',    'currency' => 'EUR', 'tag' => 'vtete-20'],
        'PL' => ['domain' => 'www.amazon.pl',    'currency' => 'PLN', 'tag' => 'vtete-20'],
        'SE' => ['domain' => 'www.amazon.se',    'currency' => 'SEK', 'tag' => 'vtete-20'],
        'BE' => ['domain' => 'www.amazon.com.be', 'currency' => 'EUR', 'tag' => 'vtete-20'],
        'IE' => ['domain' => 'www.amazon.co.uk', 'currency' => 'EUR', 'tag' => 'vtete-21'],
        'IN' => ['domain' => 'www.amazon.in',    'currency' => 'INR', 'tag' => 'virginiatete-21'],
        'JP' => ['domain' => 'www.amazon.co.jp', 'currency' => 'JPY', 'tag' => 'vtete-22'],
        'AU' => ['domain' => 'www.amazon.com.au', 'currency' => 'AUD', 'tag' => 'vtete-20'],
        'BR' => ['domain' => 'www.amazon.com.br', 'currency' => 'BRL', 'tag' => 'vtete-20'],
        'MX' => ['domain' => 'www.amazon.com.mx', 'currency' => 'MXN', 'tag' => 'vtete-20'],
        'AE' => ['domain' => 'www.amazon.ae',    'currency' => 'AED', 'tag' => 'vtete08-21'],
        'SG' => ['domain' => 'www.amazon.sg',    'currency' => 'SGD', 'tag' => 'vtete-20'],
        'SA' => ['domain' => 'www.amazon.sa',    'currency' => 'SAR', 'tag' => 'vtete-20'],
        'TR' => ['domain' => 'www.amazon.com.tr', 'currency' => 'TRY', 'tag' => 'vtete-20'],
    ];

    private const TRANSIENT_PREFIX = 'helmetsan_creator_';
    private const TOKEN_TTL        = 3000; // ~50 minutes
    private const PRICE_CACHE_TTL  = 3600; // 1 hour

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config Keys: client_id, client_secret, version, partner_tag, enabled_countries
     */
    public function __construct(array $config = [])
    {
        $clientId = defined('HELMETSAN_AMZ_CREATOR_CLIENT_ID') ? (string) \HELMETSAN_AMZ_CREATOR_CLIENT_ID : '';
        $clientSecret = defined('HELMETSAN_AMZ_CREATOR_CLIENT_SECRET') ? (string) \HELMETSAN_AMZ_CREATOR_CLIENT_SECRET : '';

        // Check local gitignored keys file for CLI / tests
        if ($clientId === '' || $clientSecret === '') {
            $keysFile = dirname(__DIR__, 3) . '/keys/amazon_creators_api.json';
            if (file_exists($keysFile)) {
                $keysData = json_decode((string) file_get_contents($keysFile), true);
                if (is_array($keysData) && !empty($keysData['credentials'])) {
                    $clientId = $keysData['credentials']['credential_id'] ?? $clientId;
                    $clientSecret = $keysData['credentials']['credential_secret'] ?? $clientSecret;
                }
            }
        }

        $this->config = array_merge([
            'client_id'         => $clientId,
            'client_secret'     => $clientSecret,
            'version'           => 'v3.1',
            'partner_tag'       => 'vtete-20',
            'uk_tag'            => 'vtete-21',
            'india_tag'         => 'virginiatete-21',
            'enabled_countries' => array_keys(self::MARKETPLACES),
        ], $config);
    }

    public function id(): string
    {
        return 'amazon-creator';
    }

    public function name(): string
    {
        return 'Amazon Creator API (' . ($this->config['version'] ?? 'v3.1') . ')';
    }

    /**
     * @return string[]
     */
    public function supportedCountries(): array
    {
        $enabled = $this->config['enabled_countries'] ?? array_keys(self::MARKETPLACES);
        if (!is_array($enabled)) {
            $enabled = array_keys(self::MARKETPLACES);
        }

        return array_values(array_intersect($enabled, array_keys(self::MARKETPLACES)));
    }

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->supportedCountries(), true);
    }

    /**
     * Fetch price for a helmet in default country (US).
     */
    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        return $this->fetchPriceForCountry($helmetRef, 'US');
    }

    /**
     * Fetch price for a helmet in a specific country.
     */
    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        $cc = strtoupper($countryCode);
        if (!isset(self::MARKETPLACES[$cc])) {
            return null;
        }

        $mkt = self::MARKETPLACES[$cc];
        $cacheKey = self::TRANSIENT_PREFIX . 'price_' . md5($helmetRef . '_' . $cc);

        if (function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $this->arrayToPriceResult($cached);
            }
        }

        // Resolve ASIN for this helmet
        $asin = $this->resolveAsin($helmetRef, $cc);
        if ($asin === '') {
            // Build fallback search link
            $fallback = $this->buildFallbackResult($helmetRef, $cc, $mkt);
            if (function_exists('set_transient')) {
                set_transient($cacheKey, $fallback->toArray(), self::PRICE_CACHE_TTL);
            }
            return $fallback;
        }

        $items = $this->getItems([$asin], $cc);
        if (empty($items)) {
            $fallback = $this->buildFallbackResult($helmetRef, $cc, $mkt, $asin);
            if (function_exists('set_transient')) {
                set_transient($cacheKey, $fallback->toArray(), self::PRICE_CACHE_TTL);
            }
            return $fallback;
        }

        $result = $this->mapItemToPriceResult($items[0], $helmetRef, $cc, $mkt);
        if ($result !== null && function_exists('set_transient')) {
            set_transient($cacheKey, $result->toArray(), self::PRICE_CACHE_TTL);
        }

        return $result;
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        return $this->fetchOffersForCountry($helmetRef, 'US');
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffersForCountry(string $helmetRef, string $countryCode): array
    {
        $price = $this->fetchPriceForCountry($helmetRef, $countryCode);

        return $price !== null ? [$price] : [];
    }

    /**
     * Search products by barcode / EAN.
     *
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        return $this->searchItems($ean, 'US', 5);
    }

    /**
     * Search products by keywords via Creator API.
     *
     * @return PriceResult[]
     */
    public function searchItems(string $keywords, string $countryCode = 'US', int $itemCount = 10): array
    {
        $cc = strtoupper($countryCode);
        $mkt = self::MARKETPLACES[$cc] ?? self::MARKETPLACES['US'];
        $token = $this->getAccessToken();

        if ($token === '') {
            return [];
        }

        $tag = $this->resolvePartnerTag($cc, $mkt);

        $payload = [
            'keywords'    => $keywords,
            'partnerTag'  => $tag,
            'partnerType' => 'Associates',
            'itemCount'   => min(10, max(1, $itemCount)),
            'resources'   => [
                'itemInfo.title',
                'images.primary.large',
                'offersV2.listings.price',
                'offersV2.listings.availability',
            ],
        ];

        $response = $this->executeApiRequest('searchItems', $payload, $mkt['domain'], $token);
        if ($response === null || empty($response['searchResult']['items'])) {
            return [];
        }

        $results = [];
        foreach ($response['searchResult']['items'] as $item) {
            $mapped = $this->mapItemToPriceResult($item, $keywords, $cc, $mkt);
            if ($mapped !== null) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /**
     * Retrieve item details for a list of ASINs via Creator API.
     *
     * @param string[] $itemIds
     * @return array<int, array<string, mixed>>
     */
    public function getItems(array $itemIds, string $countryCode = 'US'): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $cc = strtoupper($countryCode);
        $mkt = self::MARKETPLACES[$cc] ?? self::MARKETPLACES['US'];
        $token = $this->getAccessToken();

        if ($token === '') {
            return [];
        }

        $tag = $this->resolvePartnerTag($cc, $mkt);

        $payload = [
            'itemIds'     => array_slice($itemIds, 0, 10),
            'partnerTag'  => $tag,
            'partnerType' => 'Associates',
            'resources'   => [
                'itemInfo.title',
                'images.primary.large',
                'offersV2.listings.price',
                'offersV2.listings.availability',
            ],
        ];

        $response = $this->executeApiRequest('getItems', $payload, $mkt['domain'], $token);
        if ($response === null || empty($response['itemsResult']['items'])) {
            return [];
        }

        return $response['itemsResult']['items'];
    }

    /**
     * Connectivity check: tests OAuth 2.0 token acquisition.
     */
    public function healthCheck(): bool
    {
        $token = $this->getAccessToken(true);

        return $token !== '';
    }

    /**
     * Get OAuth 2.0 access token via Client Credentials Grant.
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $cacheKey = self::TRANSIENT_PREFIX . 'oauth_token';

        if (!$forceRefresh && function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $clientId     = $this->config['client_id'] ?? '';
        $clientSecret = $this->config['client_secret'] ?? '';

        if ($clientId === '' || $clientSecret === '') {
            return '';
        }

        $bodyParams = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => 'creatorsapi::default',
        ];

        $token = '';

        if (function_exists('wp_remote_post')) {
            $resp = wp_remote_post(self::TOKEN_ENDPOINT, [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'    => http_build_query($bodyParams),
            ]);

            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $data  = json_decode(wp_remote_retrieve_body($resp), true);
                $token = is_array($data) ? ($data['access_token'] ?? '') : '';
            }
        } else {
            // Fallback curl when running outside WordPress runtime
            $ch = curl_init(self::TOKEN_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($bodyParams),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT        => 15,
            ]);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code === 200 && is_string($raw)) {
                $data = json_decode($raw, true);
                $token = is_array($data) ? ($data['access_token'] ?? '') : '';
            }
        }

        if ($token !== '' && function_exists('set_transient')) {
            set_transient($cacheKey, $token, self::TOKEN_TTL);
        }

        return $token;
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * Execute REST API call to Creator API.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function executeApiRequest(string $operation, array $payload, string $marketplaceDomain, string $token): ?array
    {
        $circuitKey = self::TRANSIENT_PREFIX . 'cb_' . md5($marketplaceDomain);
        if (function_exists('get_transient') && get_transient($circuitKey)) {
            return null;
        }

        $url = self::API_ENDPOINT . $operation;
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'x-marketplace' => $marketplaceDomain,
        ];

        $jsonPayload = json_encode($payload);

        if (function_exists('wp_remote_post')) {
            $resp = wp_remote_post($url, [
                'timeout' => 15,
                'headers' => $headers,
                'body'    => $jsonPayload,
            ]);

            if (is_wp_error($resp)) {
                if (function_exists('do_action')) {
                    do_action('helmetsan_connector_error', $this->id(), $operation, $resp);
                }
                return null;
            }

            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
            $data = json_decode($body, true);

            if ($code >= 200 && $code < 300 && is_array($data)) {
                return $data;
            }

            if ($code === 400 || $code === 403) {
                if (function_exists('set_transient')) {
                    set_transient($circuitKey, 1, 1800);
                }
            }

            if (function_exists('do_action')) {
                do_action('helmetsan_connector_error', $this->id(), $operation, new \RuntimeException(
                    "Creator API returned HTTP $code: " . ($data['message'] ?? $body)
                ));
            }

            return null;
        }

        // Curl fallback
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'x-marketplace: ' . $marketplaceDomain,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code >= 200 && $code < 300 && is_string($raw)) {
            return json_decode($raw, true);
        }

        return null;
    }

    /**
     * Map Creator API Item representation to PriceResult.
     *
     * @param array<string, mixed> $item
     * @param array{domain: string, currency: string, tag: string} $mkt
     */
    private function mapItemToPriceResult(array $item, string $helmetRef, string $cc, array $mkt): ?PriceResult
    {
        $asin = $item['asin'] ?? '';
        if ($asin === '') {
            return null;
        }

        $price = 0.0;
        $mrp   = null;
        $avail = 'unknown';

        $listings = $item['offersV2']['listings'] ?? [];
        if (!empty($listings)) {
            $listing = $listings[0];
            $priceAmount = $listing['price']['money']['amount'] ?? null;
            if ($priceAmount !== null) {
                $price = (float) $priceAmount;
            }

            $availType = $listing['availability']['type'] ?? '';
            $avail = ($availType === 'IN_STOCK' || $availType === 'AVAILABLE') ? 'in_stock' : 'out_of_stock';
        }

        $tag = $this->resolvePartnerTag($cc, $mkt);
        $productUrl = 'https://' . $mkt['domain'] . '/dp/' . $asin;
        $affiliateUrl = $productUrl . '?tag=' . urlencode($tag);

        $title = $item['itemInfo']['title']['displayValue'] ?? $helmetRef;

        return new PriceResult(
            marketplaceId: 'amazon-' . strtolower($cc),
            helmetRef:     $helmetRef,
            countryCode:   $cc,
            currency:      $mkt['currency'],
            price:         $price,
            mrp:           $mrp,
            url:           $productUrl,
            affiliateUrl:  $affiliateUrl,
            availability:  $avail,
            sellerName:    'Amazon',
            condition:     'new',
            capturedAt:    gmdate('c'),
            extra:         ['asin' => $asin, 'title' => $title, 'source' => 'creator_api_v3.1'],
        );
    }

    /**
     * Fallback PriceResult when API is waiting for eligibility/activation.
     *
     * @param array{domain: string, currency: string, tag: string} $mkt
     */
    private function buildFallbackResult(string $helmetRef, string $cc, array $mkt, string $asin = ''): PriceResult
    {
        $tag = $this->resolvePartnerTag($cc, $mkt);

        if ($asin !== '') {
            $url = 'https://' . $mkt['domain'] . '/dp/' . $asin;
            $affUrl = $url . '?tag=' . urlencode($tag);
        } else {
            $query = str_replace('-', ' ', $helmetRef) . ' Helmet';
            $url = 'https://' . $mkt['domain'] . '/s?k=' . urlencode($query);
            $affUrl = $url . '&tag=' . urlencode($tag);
        }

        return new PriceResult(
            marketplaceId: 'amazon-' . strtolower($cc),
            helmetRef:     $helmetRef,
            countryCode:   $cc,
            currency:      $mkt['currency'],
            price:         0.0,
            url:           $url,
            affiliateUrl:  $affUrl,
            availability:  'unknown',
            sellerName:    'Amazon',
            condition:     'new',
            capturedAt:    gmdate('c'),
            extra:         ['mode' => 'fallback_search', 'tag' => $tag],
        );
    }

    private function resolveAsin(string $helmetRef, string $cc): string
    {
        if (function_exists('get_page_by_path')) {
            $post = get_page_by_path($helmetRef, OBJECT, 'helmet');
            if ($post instanceof \WP_Post) {
                $ccLower = strtolower($cc);
                $specific = (string) get_post_meta($post->ID, 'amazon_asin_' . $ccLower, true);
                if ($specific !== '') {
                    return $specific;
                }

                return (string) get_post_meta($post->ID, 'affiliate_asin', true);
            }
        }

        return '';
    }

    /**
     * Reconstitute a PriceResult from a serialized array.
     *
     * @param array<string, mixed> $data
     */
    private function arrayToPriceResult(array $data): PriceResult
    {
        return new PriceResult(
            marketplaceId: (string) ($data['marketplace_id'] ?? ''),
            helmetRef:     (string) ($data['helmet_ref'] ?? ''),
            countryCode:   (string) ($data['country_code'] ?? ''),
            currency:      (string) ($data['currency'] ?? 'USD'),
            price:         (float)  ($data['price'] ?? 0.0),
            mrp:           isset($data['mrp']) && $data['mrp'] !== null ? (float) $data['mrp'] : null,
            url:           (string) ($data['url'] ?? ''),
            affiliateUrl:  (string) ($data['affiliate_url'] ?? ''),
            availability:  (string) ($data['availability'] ?? 'unknown'),
            sellerName:    (string) ($data['seller_name'] ?? ''),
            condition:     (string) ($data['condition'] ?? 'new'),
            capturedAt:    (string) ($data['captured_at'] ?? ''),
            extra:         is_array($data['extra'] ?? null) ? $data['extra'] : [],
        );
    }

    private function resolvePartnerTag(string $cc, array $mkt): string
    {
        $upper = strtoupper($cc);
        if ($upper === 'UK' || $upper === 'GB') {
            return $this->config['uk_tag'] ?? $mkt['tag'] ?? 'vtete-21';
        }
        if ($upper === 'IN') {
            return $this->config['india_tag'] ?? 'virginiatete-21';
        }
        $cKey = 'amazon_tag_' . strtolower($cc);
        if (!empty($this->config[$cKey])) {
            return (string) $this->config[$cKey];
        }
        return $mkt['tag'] ?? ($this->config['partner_tag'] ?? 'vtete-20');
    }
}
