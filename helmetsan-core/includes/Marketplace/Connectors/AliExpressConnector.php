<?php

declare(strict_types=1);

namespace Helmetsan\Core\Marketplace\Connectors;

use Helmetsan\Core\Marketplace\MarketplaceConnectorInterface;
use Helmetsan\Core\Marketplace\PriceResult;

/**
 * AliExpress Affiliate Open API Connector.
 *
 * Utilizes the AliExpress product query API to fetch prices, EAN matches,
 * and generates tracked promotional URLs.
 *
 * @see https://portals.aliexpress.com/help/help_center.html
 */
final class AliExpressConnector implements MarketplaceConnectorInterface
{
    private const API_URL      = 'https://api.aliexpress.com/sync';
    private const CACHE_PREFIX = 'helmetsan_aliexpress_';
    private const CACHE_TTL    = 3600;

    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config Keys: app_key, app_secret, tracking_id
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function id(): string
    {
        return 'aliexpress-global';
    }

    public function name(): string
    {
        return 'AliExpress';
    }

    /**
     * AliExpress ships globally. Supports all active country routing.
     *
     * @return string[]
     */
    public function supportedCountries(): array
    {
        return ['*'];
    }

    public function supports(string $countryCode): bool
    {
        return $countryCode !== '';
    }

    public function fetchPrice(string $helmetRef): ?PriceResult
    {
        return $this->fetchPriceForCountry($helmetRef, 'US');
    }

    public function fetchPriceForCountry(string $helmetRef, string $countryCode): ?PriceResult
    {
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
        return $this->fetchOffersForCountry($helmetRef, 'US');
    }

    /**
     * @return PriceResult[]
     */
    public function fetchOffersForCountry(string $helmetRef, string $countryCode): array
    {
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
        return $this->searchByEanAndCountry($ean, 'US');
    }

    /**
     * @return PriceResult[]
     */
    public function searchByEanAndCountry(string $ean, string $countryCode): array
    {
        return $this->queryAliExpress($ean, $countryCode);
    }

    public function healthCheck(): bool
    {
        $appKey    = $this->config['app_key'] ?? '';
        $appSecret = $this->config['app_secret'] ?? '';

        return $appKey !== '' && $appSecret !== '';
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * @return PriceResult[]
     */
    private function searchByKeywordAndCountry(string $helmetRef, string $countryCode): array
    {
        $phrase = str_replace('-', ' ', $helmetRef);
        return $this->queryAliExpress($phrase, $countryCode);
    }

    /**
     * Call the aliexpress.affiliate.product.query API.
     *
     * @return PriceResult[]
     */
    private function queryAliExpress(string $query, string $countryCode): array
    {
        $appKey    = $this->config['app_key'] ?? '';
        $appSecret = $this->config['app_secret'] ?? '';
        $tracking  = $this->config['tracking_id'] ?? '';

        if ($appKey === '' || $appSecret === '') {
            return [];
        }

        $currency = $this->getCurrency($countryCode);

        // Parameters required by AliExpress Top API
        $params = [
            'method'             => 'aliexpress.affiliate.product.query',
            'app_key'            => $appKey,
            'timestamp'          => current_time('mysql'), // Formatted as YYYY-MM-DD HH:MM:SS (local server time)
            'format'             => 'json',
            'v'                  => '2.0',
            'sign_method'        => 'md5',
            'keywords'           => $query,
            'page_size'          => '5',
            'sort'               => 'priceAsc',
            'tracking_id'        => $tracking,
            'target_currency'    => $currency,
            'target_language'    => $this->getLanguage($countryCode),
        ];

        // Sign parameters
        $params['sign'] = $this->generateSignature($params, $appSecret);

        $response = wp_remote_get(self::API_URL . '?' . http_build_query($params), [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            do_action('helmetsan_connector_error', $this->id(), 'queryAliExpress', $response);
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $this->parseResponse($body, $query, $countryCode);
    }

    /**
     * Taobao/Aliexpress Open Platform MD5 request signature generator.
     */
    private function generateSignature(array $params, string $appSecret): string
    {
        // 1. Sort alphabetically
        ksort($params);

        // 2. Concatenate
        $raw = '';
        foreach ($params as $k => $v) {
            if (is_string($v) || is_numeric($v) || is_bool($v)) {
                $raw .= $k . $v;
            }
        }

        // 3. Prepend & Append secret, compute MD5
        return strtoupper(md5($appSecret . $raw . $appSecret));
    }

    /**
     * @param array<string,mixed>|null $body
     * @return PriceResult[]
     */
    private function parseResponse(?array $body, string $helmetRef, string $countryCode): array
    {
        if ($body === null) {
            return [];
        }

        // Response payload structure resolver
        $resultsPayload = $body['aliexpress_affiliate_product_query_response']['resp_result']['result']['products']['product'] ?? [];
        if (empty($resultsPayload)) {
            return [];
        }

        $results = [];
        foreach ($resultsPayload as $product) {
            // Target Price
            $priceStr = (string) ($product['target_sale_price'] ?? $product['target_original_price'] ?? '0.0');
            $priceVal = (float) $priceStr;
            if ($priceVal <= 0.0) {
                continue;
            }

            $productId = (string) ($product['product_id'] ?? '');
            $title     = (string) ($product['product_title'] ?? '');
            $storeName = (string) ($product['store_name'] ?? 'AliExpress Store');
            
            // Link formats
            $url          = (string) ($product['product_detail_url'] ?? '');
            $affiliateUrl = (string) ($product['promotion_link'] ?? $url);

            $results[] = new PriceResult(
                marketplaceId: 'aliexpress-global',
                helmetRef:     $helmetRef,
                countryCode:   strtoupper($countryCode),
                currency:      (string) ($product['target_sale_price_currency'] ?? $this->getCurrency($countryCode)),
                price:         $priceVal,
                url:           $url,
                affiliateUrl:  $affiliateUrl,
                availability:  'in_stock',
                sellerName:    $storeName,
                condition:     'new',
                capturedAt:    gmdate('c'),
                extra:         ['aliexpress_product_id' => $productId, 'title' => $title],
            );
        }

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

    private function getCurrency(string $countryCode): string
    {
        $map = [
            'PL' => 'PLN',
            'GB' => 'GBP',
            'DE' => 'EUR',
            'FR' => 'EUR',
            'IT' => 'EUR',
            'ES' => 'EUR',
            'IN' => 'INR',
            'CA' => 'CAD',
            'AU' => 'AUD',
        ];
        return $map[strtoupper($countryCode)] ?? 'USD';
    }

    private function getLanguage(string $countryCode): string
    {
        $map = [
            'DE' => 'DE',
            'ES' => 'ES',
            'FR' => 'FR',
            'IT' => 'IT',
            'PL' => 'PL',
            'CN' => 'ZH',
        ];
        return $map[strtoupper($countryCode)] ?? 'EN';
    }

    /**
     * @param array<string,mixed> $data
     */
    private function arrayToResult(array $data): PriceResult
    {
        return new PriceResult(
            marketplaceId: (string) ($data['marketplace_id'] ?? 'aliexpress-global'),
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
