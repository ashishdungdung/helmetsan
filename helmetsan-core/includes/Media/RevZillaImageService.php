<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

/**
 * Resolves helmet product image URLs from RevZilla.
 *
 * Uses stored RevZilla product URLs (affiliate_links_json) to fetch the product page
 * and extract the main image (og:image or primary product image). Caches result per URL.
 * Integrates with HelmetImageEnrichmentService so helmets linked to RevZilla can
 * get their catalog images imported via the Media Engine.
 *
 * @see docs/data-flow.md (Marketplace links as source for images)
 */
final class RevZillaImageService
{
    private const CACHE_GROUP = 'helmetsan_revzilla_image';
    private const CACHE_TTL   = 86400; // 24 hours
    private const REVZILLA_KEYS = ['revzilla-us', 'revzilla_us'];

    /**
     * Get the RevZilla product URL stored for a helmet (affiliate_links_json).
     */
    public function getRevZillaUrlForHelmet(int $helmetId): ?string
    {
        $json = (string) get_post_meta($helmetId, 'affiliate_links_json', true);
        if ($json === '') {
            return null;
        }
        $links = json_decode($json, true);
        if (! is_array($links)) {
            return null;
        }
        foreach (self::REVZILLA_KEYS as $key) {
            $entry = $links[$key] ?? null;
            if (is_array($entry) && ! empty($entry['url'])) {
                $url = trim((string) $entry['url']);
                if ($url !== '' && str_contains(strtolower($url), 'revzilla.com')) {
                    return $url;
                }
            }
            if (is_string($entry) && trim($entry) !== '' && str_contains(strtolower($entry), 'revzilla.com')) {
                return trim($entry);
            }
        }
        return null;
    }

    /**
     * Resolve a product image URL for a helmet from RevZilla.
     * Returns the image URL and provider key, or null if not found.
     *
     * @return array{url: string, provider: string}|null
     */
    public function getImageUrlForHelmet(int $helmetId): ?array
    {
        $productUrl = $this->getRevZillaUrlForHelmet($helmetId);
        if ($productUrl === null) {
            return null;
        }
        $imageUrl = $this->fetchImageFromProductPage($productUrl);
        if ($imageUrl === null) {
            return null;
        }
        return [
            'url'      => $imageUrl,
            'provider' => 'revzilla',
        ];
    }

    /**
     * Fetch the product page and extract the main image URL (og:image or primary product image).
     */
    public function fetchImageFromProductPage(string $productUrl): ?string
    {
        $productUrl = esc_url_raw($productUrl);
        if ($productUrl === '' || ! str_contains(strtolower($productUrl), 'revzilla.com')) {
            return null;
        }
        $cacheKey = self::CACHE_GROUP . '_' . md5($productUrl);
        $cached   = get_transient($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $imageUrl = $this->fetchAndParseProductPage($productUrl);
        if ($imageUrl !== null) {
            set_transient($cacheKey, $imageUrl, self::CACHE_TTL);
        }
        return $imageUrl;
    }

    /**
     * Clear cached image URL for a RevZilla product URL (e.g. after manual refresh).
     */
    public function clearCacheForUrl(string $productUrl): void
    {
        $key = self::CACHE_GROUP . '_' . md5(esc_url_raw($productUrl));
        delete_transient($key);
    }

    private function fetchAndParseProductPage(string $url): ?string
    {
        $response = wp_remote_get($url, [
            'timeout'    => 15,
            'user-agent' => 'Helmetsan/1.0 (Catalog image enrichment; +https://helmetsan.com)',
            'redirection' => 3,
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return null;
        }
        return $this->extractImageFromHtml($body, $url);
    }

    /**
     * Extract main product image from HTML: og:image first, then common product image patterns.
     */
    private function extractImageFromHtml(string $html, string $baseUrl): ?string
    {
        // og:image (most reliable on e‑commerce pages)
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $url = trim($m[1]);
            if ($url !== '' && $this->isValidImageUrl($url)) {
                return $this->normalizeImageUrl($url, $baseUrl);
            }
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            $url = trim($m[1]);
            if ($url !== '' && $this->isValidImageUrl($url)) {
                return $this->normalizeImageUrl($url, $baseUrl);
            }
        }
        // RevZilla often uses data-src or src on main product image (e.g. first large image in gallery)
        if (preg_match('/<img[^>]+(?:data-src|src)=["\']([^"\']+)["\'][^>]+(?:data-src|src)=/i', $html, $m)) {
            $url = trim($m[1]);
            if ($url !== '' && $this->isValidImageUrl($url) && str_contains(strtolower($url), 'revzilla')) {
                return $this->normalizeImageUrl($url, $baseUrl);
            }
        }
        if (preg_match('/<img[^>]+(?:data-src|src)=["\']([^"\']+\.(?:jpg|jpeg|png|webp))["\']/i', $html, $m)) {
            $url = trim($m[1]);
            if ($url !== '' && $this->isValidImageUrl($url)) {
                return $this->normalizeImageUrl($url, $baseUrl);
            }
        }
        return null;
    }

    private function isValidImageUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000) {
            return false;
        }
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    private function normalizeImageUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $parsed = wp_parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'] ?? 'www.revzilla.com';
        $path   = str_starts_with($url, '/') ? $url : '/' . ltrim($url, '/');
        return $scheme . '://' . $host . $path;
    }
}
