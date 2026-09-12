<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * eBay Partner Network (EPN) & Buy API Connector.
 *
 * Connects to the eBay Browse API to retrieve product listings, prices,
 * and generates EPN affiliate URLs.
 *
 * @see https://developer.ebay.com/api-docs/buy/browse/static/overview.html
 */
final class EbayConnector implements MarketplaceConnectorInterface
{
    private const AUTH_URL      = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const API_BASE      = 'https://api.ebay.com/buy/browse/v1';
    private const TRANSIENT_KEY = 'helmetsan_ebay_token';
    private const CACHE_PREFIX  = 'helmetsan_ebay_';
    private const CACHE_TTL     = 3600;
    private const TOKEN_TTL     = 7000; // eBay tokens last 2 hours (7200s)

    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config Keys: client_id, client_secret, campaign_id, ebay_countries
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'ebay-global';
    }

    public function name(): string
    {
        return 'eBay';
    }

    /**
     * @return string[]
     */
    public function supportedCountries(): array
    {
        return $this->config['ebay_countries'] ?? ['US', 'GB', 'DE', 'FR', 'IT', 'ES', 'CA', 'AU'];
    }

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->supportedCountries(), true);
    }

    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        $defaultCountry = $this->supportedCountries()[0] ?? 'US';
        return $this->fetchPriceForCountry($helmetRef, $defaultCountry);
    }

    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
        if (!$this->supports($countryCode)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . 'price_' . $helmetRef . '_' . strtolower($countryCode);
        $cached   = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $this->arrayToResult($cached);
        }

        $offers = $this->fetchOffersForCountry($helmetRef, $countryCode);
        if (empty($offers)) {
            return null;
        }

        $best = $offers[0]; // Cheapest
        set_transient($cacheKey, $best->toArray(), self::CACHE_TTL);

        return $best;
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffers(string $helmetRef): array
    {
        $defaultCountry = $this->supportedCountries()[0] ?? 'US';
        return $this->fetchOffersForCountry($helmetRef, $defaultCountry);
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffersForCountry(string $helmetRef, string $countryCode): array
    {
        if (!$this->supports($countryCode)) {
            return [];
        }

        $ean = $this->resolveEan($helmetRef);
        if ($ean !== '') {
            $offers = $this->searchByEanAndCountry($ean, $countryCode);
            if (!empty($offers)) {
                $mapped = [];
                foreach ($offers as $offer) {
                    $mapped[] = new PriceResult(
                        marketplaceId: $offer->marketplaceId,
                        helmetRef:     $helmetRef,
                        countryCode:   $offer->countryCode,
                        currency:      $offer->currency,
                        price:         $offer->price,
                        mrp:           $offer->mrp,
                        url:           $offer->url,
                        affiliateUrl:  $offer->affiliateUrl,
                        availability:  $offer->availability,
                        sellerName:    $offer->sellerName,
                        condition:     $offer->condition,
                        capturedAt:    $offer->capturedAt,
                        extra:         $offer->extra
                    );
                }
                return $mapped;
            }
        }

        return $this->searchByKeywordAndCountry($helmetRef, $countryCode);
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEan(string $ean): array
    {
        $defaultCountry = $this->supportedCountries()[0] ?? 'US';
        return $this->searchByEanAndCountry($ean, $defaultCountry);
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEanAndCountry(string $ean, string $countryCode): array
    {
        $token = $this->getAccessToken();
        if ($token === '') {
            return [];
        }

        $params = [
            'q'            => $ean,
            'category_ids' => '177074', // Motorcycle Helmets & Accessories
            'filter'       => 'conditions:{NEW}',
            'limit'        => '5',
        ];

        $response = $this->apiGet('/item_summary/search', $params, $countryCode, $token);
        return $this->parseSearchResponse($response, '', $countryCode);
    }

    public function healthCheck(): bool
    {
        return $this->getAccessToken() !== '';
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * @return PriceResult[]
     */
    private function searchByKeywordAndCountry(string $helmetRef, string $countryCode): array
    {
        $token = $this->getAccessToken();
        if ($token === '') {
            return [];
        }

        $phrase = str_replace('-', ' ', $helmetRef);
        $params = [
            'q'            => $phrase,
            'category_ids' => '177074',
            'filter'       => 'conditions:{NEW}',
            'limit'        => '5',
        ];

        $response = $this->apiGet('/item_summary/search', $params, $countryCode, $token);
        return $this->parseSearchResponse($response, $helmetRef, $countryCode);
    }

    /**
     * @param array<string,mixed>|null $response
     * @return PriceResult[]
     */
    private function parseSearchResponse(?array $response, string $helmetRef, string $countryCode): array
    {
        if ($response === null || empty($response['itemSummaries'])) {
            return [];
        }

        $summaries = $response['itemSummaries'];
        $results = [];

        foreach ($summaries as $item) {
            $priceData = $item['price'] ?? [];
            $priceVal  = isset($priceData['value']) ? (float) $priceData['value'] : 0.0;
            if ($priceVal <= 0.0) {
                continue;
            }

            $itemId = (string) ($item['itemId'] ?? '');
            $title  = (string) ($item['title'] ?? '');
            $seller = $item['seller']['username'] ?? 'eBay Seller';
            
            // Raw eBay URL
            $rawUrl = (string) ($item['itemWebUrl'] ?? '');
            if ($rawUrl === '' && $itemId !== '') {
                $rawUrl = 'https://www.ebay.com/itm/' . $itemId;
            }

            // Generate EPN affiliate tracking link
            $campaignId = $this->config['campaign_id'] ?? '';
            $affiliateUrl = $rawUrl;

            if ($campaignId !== '' && $itemId !== '') {
                $rotationId = $this->getRotationId($countryCode);
                // Standard EPN redirection format
                $affiliateUrl = sprintf(
                    'https://rover.ebay.com/rover/1/%s/4?mpre=%s&campid=%s&toolid=20008&customid=%s',
                    $rotationId,
                    rawurlencode($rawUrl),
                    rawurlencode($campaignId),
                    rawurlencode($helmetRef !== '' ? $helmetRef : 'ean-search')
                );
            }

            $results[] = new PriceResult(
                marketplaceId: 'ebay-global',
                helmetRef:     $helmetRef,
                countryCode:   strtoupper($countryCode),
                currency:      (string) ($priceData['currency'] ?? $this->getCurrency($countryCode)),
                price:         $priceVal,
                url:           $rawUrl,
                affiliateUrl:  $affiliateUrl,
                availability:  'in_stock',
                sellerName:    (string) $seller,
                condition:     'new',
                capturedAt:    gmdate('c'),
                extra:         ['ebay_item_id' => $itemId, 'title' => $title],
            );
        }

        // Sort by cheapest
        usort($results, static fn(PriceResult $a, PriceResult $b) => $a->price <=> $b->price);

        return $results;
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

        $authHeader = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);

        $response = wp_remote_post(self::AUTH_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => $authHeader,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'client_credentials',
                'scope'      => 'https://api.ebay.com/oauthapi/appid_signin',
            ],
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return '';
        }

        $json  = json_decode(wp_remote_retrieve_body($response), true);
        $token = $json['access_token'] ?? '';

        if (is_string($token) && $token !== '') {
            set_transient(self::TRANSIENT_KEY, $token, self::TOKEN_TTL);
        }

        return is_string($token) ? $token : '';
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>|null
     */
    private function apiGet(string $path, array $params, string $countryCode, string $token): ?array
    {
        $url = self::API_BASE . $path . '?' . http_build_query($params);

        $marketplaceId = $this->getMarketplaceId($countryCode);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization'           => 'Bearer ' . $token,
                'X-EBAY-C-MARKETPLACE-ID' => $marketplaceId,
                'Content-Type'            => 'application/json',
                'Accept'                  => 'application/json',
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

    private function getRotationId(string $countryCode): string
    {
        $map = [
            'US' => '711-53200-19255-0',
            'GB' => '710-53481-19255-0',
            'DE' => '707-53477-19255-0',
            'FR' => '709-53476-19255-0',
            'IT' => '724-53478-19255-0',
            'ES' => '1185-53479-19255-0',
            'CA' => '706-53473-19255-0',
            'AU' => '705-53470-19255-0',
        ];
        return $map[strtoupper($countryCode)] ?? '711-53200-19255-0';
    }

    private function getMarketplaceId(string $countryCode): string
    {
        $map = [
            'US' => 'EBAY_US',
            'GB' => 'EBAY_GB',
            'DE' => 'EBAY_DE',
            'FR' => 'EBAY_FR',
            'IT' => 'EBAY_IT',
            'ES' => 'EBAY_ES',
            'CA' => 'EBAY_CA',
            'AU' => 'EBAY_AU',
        ];
        return $map[strtoupper($countryCode)] ?? 'EBAY_US';
    }

    private function getCurrency(string $countryCode): string
    {
        $map = [
            'US' => 'USD',
            'GB' => 'GBP',
            'DE' => 'EUR',
            'FR' => 'EUR',
            'IT' => 'EUR',
            'ES' => 'EUR',
            'CA' => 'CAD',
            'AU' => 'AUD',
        ];
        return $map[strtoupper($countryCode)] ?? 'USD';
    }

    /**
     * @param array<string,mixed> $data
     */
    private function arrayToResult(array $data): PriceResult
    {
        return new PriceResult(
            marketplaceId: (string) ($data['marketplace_id'] ?? 'ebay-global'),
            helmetRef:     (string) ($data['helmet_ref'] ?? ''),
            countryCode:   (string) ($data['country_code'] ?? 'US'),
            currency:      (string) ($data['currency'] ?? 'USD'),
            price:         (float) ($data['price'] ?? 0),
            mrp:           isset($data['mrp']) ? (float) $data['mrp'] : null,
            url:           (string) ($data['url'] ?? ''),
            affiliateUrl:  (string) ($data['affiliate_url'] ?? ''),
            availability:  (string) ($data['availability'] ?? 'in_stock'),
            sellerName:    (string) ($data['seller_name'] ?? ''),
            condition:     (string) ($data['condition'] ?? 'new'),
            capturedAt:    (string) ($data['captured_at'] ?? ''),
            extra:         isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : [],
        );
    }
}
