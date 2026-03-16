<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use Helmetsan\Core\Support\Config;

/**
 * Fetches product or brand images by EAN/GTIN using EAN-DB and/or eandata.com APIs.
 * Used to enrich helmet/accessory featured images when identifiers are available.
 */
final class ProductImageByEanService
{
    private const CACHE_GROUP = 'helmetsan_ean_image';
    private const CACHE_TTL   = 86400; // 24 hours
    private const EAN_DB_URL  = 'https://ean-db.com/api/v2/product/';

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Fetch a product image URL for the given EAN/UPC/GTIN.
     * Tries EAN-DB first (returns images array), then eandata image URL pattern if enabled.
     *
     * @return array{url: string, provider: string, width?: int, height?: int}|null
     */
    public function fetchImageByEan(string $ean): ?array
    {
        $ean = preg_replace('/\D/', '', $ean);
        if ($ean === '') {
            return null;
        }

        $cacheKey = self::CACHE_GROUP . '_' . md5($ean);
        $cached   = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['url']) && $cached['url'] !== '') {
            return $cached;
        }

        $result = $this->fetchFromEanDb($ean);
        if ($result === null && strlen($ean) === 13) {
            $result = $this->buildEandataImageUrl($ean);
        }

        if ($result !== null) {
            set_transient($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * @return array{url: string, provider: string, width?: int, height?: int}|null
     */
    private function fetchFromEanDb(string $ean): ?array
    {
        $cfg = $this->config->mediaConfig();
        if (empty($cfg['ean_db_enabled']) || (string) ($cfg['ean_db_token'] ?? '') === '') {
            return null;
        }

        $url  = self::EAN_DB_URL . $ean;
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . (string) $cfg['ean_db_token'],
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if (! is_array($data) || empty($data['images']) || ! is_array($data['images'])) {
            return null;
        }

        // Prefer catalog image, then first available
        $chosen = null;
        foreach ($data['images'] as $img) {
            if (! is_array($img) || empty($img['url'])) {
                continue;
            }
            if (! empty($img['isCatalog'])) {
                $chosen = $img;
                break;
            }
            if ($chosen === null) {
                $chosen = $img;
            }
        }

        if ($chosen === null) {
            return null;
        }

        return [
            'url'      => (string) $chosen['url'],
            'provider' => 'ean-db',
            'width'    => isset($chosen['width']) ? (int) $chosen['width'] : null,
            'height'   => isset($chosen['height']) ? (int) $chosen['height'] : null,
        ];
    }

    /**
     * Build eandata.com image URL from their path pattern (13-digit EAN).
     * Does not verify image existence; use when eandata API is configured.
     *
     * @return array{url: string, provider: string}|null
     */
    private function buildEandataImageUrl(string $ean): ?array
    {
        $cfg = $this->config->mediaConfig();
        if (empty($cfg['eandata_enabled']) || strlen($ean) !== 13) {
            return null;
        }

        $p1 = substr($ean, 0, 3);
        $p2 = substr($ean, 3, 3);
        $p3 = substr($ean, 6, 3);
        $url = sprintf('https://eandata.com/image/product/%s/%s/%s/%s.jpg', $p1, $p2, $p3, $ean);

        return [
            'url'      => $url,
            'provider' => 'eandata',
        ];
    }

    /**
     * Clear cached result for an EAN (e.g. after manual refresh).
     */
    public function clearCache(string $ean): void
    {
        $ean  = preg_replace('/\D/', '', $ean);
        $key  = self::CACHE_GROUP . '_' . md5($ean);
        delete_transient($key);
    }

    /**
     * Test connectivity to EAN-DB API.
     * Uses a dummy request to check if the token is valid and the API is reachable.
     */
    public function healthCheck(): bool
    {
        $cfg = $this->config->mediaConfig();
        if (empty($cfg['ean_db_enabled']) || (string) ($cfg['ean_db_token'] ?? '') === '') {
            return false;
        }

        // We check a dummy/non-existent EAN to see if the API responds with a proper 404 (valid token)
        // or a 401/403 (invalid token).
        $url  = self::EAN_DB_URL . '0000000000000';
        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . (string) $cfg['ean_db_token'],
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($resp)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($resp);
        // 200 means success (unlikely for 0000...), 404 means valid token but product not found.
        // 401/403 means auth failure.
        return $code === 200 || $code === 404;
    }
}
