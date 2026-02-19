<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * Allegro.pl REST API connector.
 *
 * Uses OAuth2 device-code or client-credentials flow.
 * Primarily serves the Polish (PL) market.
 *
 * @see https://developer.allegro.pl/documentation/
 */
final class AllegroConnector implements MarketplaceConnectorInterface
{
    private const API_BASE       = 'https://api.allegro.pl';
    private const AUTH_URL       = 'https://allegro.pl/auth/oauth/token';
    private const TRANSIENT_KEY  = 'helmetsan_allegro_token';
    private const CACHE_PREFIX   = 'helmetsan_allegro_';
    private const CACHE_TTL      = 3600;
    private const TOKEN_TTL      = 43000; // ~12 hours

    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config  Keys: client_id, client_secret, refresh_token, affiliate_id
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'allegro-pl';
    }

    public function name(): string
    {
        return 'Allegro';
    }

    public function supportedCountries(): array
    {
        return ['PL'];
    }

    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'PL';
    }

    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        $cacheKey = self::CACHE_PREFIX . 'price_' . $helmetRef;
        $cached   = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $this->arrayToResult($cached);
        }

        // Resolve EAN from post meta
        $ean = $this->resolveEan($helmetRef);
        $results = $ean !== '' ? $this->searchByEan($ean) : $this->searchByKeyword($helmetRef);

        if (empty($results)) {
            return null;
        }

        // Cheapest available
        $best = $results[0];
        set_transient($cacheKey, $best->toArray(), self::CACHE_TTL);

        return $best;
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        $ean = $this->resolveEan($helmetRef);

        return $ean !== '' ? $this->searchByEan($ean) : $this->searchByKeyword($helmetRef);
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        $token = $this->getAccessToken();
        if ($token === '') {
            return [];
        }

        $response = $this->apiGet('/offers/listing', [
            'phrase'              => $ean,
            'category.id'        => '261267', // Motorcycle helmets on Allegro
            'sellingMode.format'  => 'BUY_NOW',
            'sort'                => 'price_asc',
            'limit'               => '5',
        ], $token);

        return $this->parseListingResponse($response, '');
    }

    public function healthCheck(): bool
    {
        return $this->getAccessToken() !== '';
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * @return PriceResult[]
     */
    private function searchByKeyword(string $helmetRef): array
    {
        $token = $this->getAccessToken();
        if ($token === '') {
            return [];
        }

        // Convert slug to search phrase: "shoei-rf-1400" → "shoei rf 1400"
        $phrase = str_replace('-', ' ', $helmetRef);

        $response = $this->apiGet('/offers/listing', [
            'phrase'              => $phrase,
            'category.id'        => '261267',
            'sellingMode.format'  => 'BUY_NOW',
            'sort'                => 'price_asc',
            'limit'               => '5',
        ], $token);

        return $this->parseListingResponse($response, $helmetRef);
    }

    /**
     * @param array<string,mixed>|null $response
     * @return PriceResult[]
     */
    private function parseListingResponse(?array $response, string $helmetRef): array
    {
        if ($response === null) {
            return [];
        }

        $items   = $response['items'] ?? $response['promoted'] ?? [];
        $regular = $response['regular'] ?? [];
        if (is_array($regular)) {
            $items = array_merge(is_array($items) ? $items : [], $regular);
        }

        $results = [];
        foreach ($items as $item) {
            $price = $this->extractPrice($item);
            if ($price <= 0) {
                continue;
            }

            $offerId = (string) ($item['id'] ?? '');
            $name    = (string) ($item['name'] ?? '');
            $seller  = $item['seller']['login'] ?? 'Allegro Seller';
            $url     = 'https://allegro.pl/oferta/' . $offerId;

            $affiliateId = $this->config['affiliate_id'] ?? '';
            $affiliateUrl = $affiliateId !== ''
                ? $url . '?aff_id=' . rawurlencode($affiliateId)
                : $url;

            $results[] = new PriceResult(
                marketplaceId: 'allegro-pl',
                helmetRef:     $helmetRef,
                countryCode:   'PL',
                currency:      'PLN',
                price:         $price,
                url:           $url,
                affiliateUrl:  $affiliateUrl,
                availability:  'in_stock',
                sellerName:    (string) $seller,
                condition:     'new',
                capturedAt:    gmdate('c'),
                extra:         ['allegro_offer_id' => $offerId, 'title' => $name],
            );
        }

        // Sort by price
        usort($results, static fn(PriceResult $a, PriceResult $b) => $a->price <=> $b->price);

        return $results;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function extractPrice(array $item): float
    {
        $selling = $item['sellingMode'] ?? [];
        $price   = $selling['price'] ?? $selling['fixedPrice'] ?? [];
        $amount  = $price['amount'] ?? null;

        return $amount !== null ? (float) $amount : 0.0;
    }

    private function resolveEan(string $helmetRef): string
    {
        $post = get_page_by_path($helmetRef, OBJECT, 'helmet');
        if (!$post instanceof \WP_Post) {
            return '';
        }

        return (string) get_post_meta($post->ID, 'ean', true);
    }

    private function getAccessToken(): string
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId     = $this->config['client_id'] ?? '';
        $clientSecret = $this->config['client_secret'] ?? '';

        if ($clientId === '' || $clientSecret === '') {
            return '';
        }

        // Try refresh token first, fallback to client_credentials
        $refreshToken = $this->config['refresh_token'] ?? '';
        $body = $refreshToken !== ''
            ? ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]
            : ['grant_type' => 'client_credentials'];

        $response = wp_remote_post(self::AUTH_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $json  = json_decode(wp_remote_retrieve_body($response), true);
        $token = $json['access_token'] ?? '';

        if (is_string($token) && $token !== '') {
            set_transient(self::TRANSIENT_KEY, $token, self::TOKEN_TTL);

            // Persist new refresh token if returned
            $newRefresh = $json['refresh_token'] ?? '';
            if (is_string($newRefresh) && $newRefresh !== '') {
                $opts = get_option('helmetsan_marketplace', []);
                if (!is_array($opts)) $opts = [];
                $opts['allegro_refresh_token'] = $newRefresh;
                update_option('helmetsan_marketplace', $opts, false);
            }
        }

        return is_string($token) ? $token : '';
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>|null
     */
    private function apiGet(string $path, array $params, string $token): ?array
    {
        $url = self::API_BASE . $path . '?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/vnd.allegro.public.v1+json',
            ],
        ]);

        if (is_wp_error($response)) {
            do_action('helmetsan_connector_error', $this->id(), 'apiGet', $response);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function arrayToResult(array $data): PriceResult
    {
        return new PriceResult(
            marketplaceId: (string) ($data['marketplace_id'] ?? 'allegro-pl'),
            helmetRef:     (string) ($data['helmet_ref'] ?? ''),
            countryCode:   (string) ($data['country_code'] ?? 'PL'),
            currency:      (string) ($data['currency'] ?? 'PLN'),
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
