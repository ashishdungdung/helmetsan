<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use Helmetsan\Core\Support\RateLimiter;

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
    private const SLOW_DOWN_CODE = 429;
    private const FORBIDDEN_CODE = 403;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15',
    ];

    private readonly RateLimiter $rateLimiter;

    public function __construct(?RateLimiter $rateLimiter = null)
    {
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
    }

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
     * Resolve all product images for a helmet from RevZilla.
     * Returns an array of image URLs and provider keys.
     *
     * @return array<array{url: string, provider: string}>
     */
    public function getAllImagesForHelmet(int $helmetId): array
    {
        $productUrl = $this->getRevZillaUrlForHelmet($helmetId);
        if ($productUrl === null) {
            return [];
        }
        return $this->fetchAllImagesFromProductPage($productUrl);
    }

    /**
     * Fetch the product page and extract all product image URLs.
     *
     * @return array<array{url: string, provider: string}>
     */
    public function fetchAllImagesFromProductPage(string $productUrl): array
    {
        $productUrl = esc_url_raw($productUrl);
        if ($productUrl === '' || ! str_contains(strtolower($productUrl), 'revzilla.com')) {
            return [];
        }
        $cacheKey = self::CACHE_GROUP . '_all_' . md5($productUrl);
        $cached   = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $body = $this->fetchHtmlFromUrl($productUrl);
        if ($body === '') {
            return [];
        }

        $images = $this->extractAllImagesFromHtml($body, $productUrl);
        if ($images !== []) {
            set_transient($cacheKey, $images, self::CACHE_TTL);
        }
        return $images;
    }

    /**
     * Fetch HTML from a URL with rate limiting and error handling.
     */
    private function fetchHtmlFromUrl(string $url): string
    {
        if (! $this->rateLimiter->check('revzilla')) {
            return '';
        }

        $res = wp_remote_get($url, [
            'timeout'    => 15,
            'user-agent' => $this->getRandomUserAgent(),
        ]);

        if (is_wp_error($res)) {
            return '';
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code === self::SLOW_DOWN_CODE || $code === self::FORBIDDEN_CODE) {
            $this->rateLimiter->recordFailure('revzilla', $code);
            return '';
        }

        if ($code < 200 || $code >= 300) {
            return '';
        }

        return wp_remote_retrieve_body($res);
    }

    private function getRandomUserAgent(): string
    {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }

    /**
     * Fetch the product page and extract the main image URL (og:image or primary product image).
     */
    public function fetchImageFromProductPage(string $productUrl): ?string
    {
        $images = $this->fetchAllImagesFromProductPage($productUrl);
        return ! empty($images[0]['url']) ? $images[0]['url'] : null;
    }

    /**
     * Clear cached image URLs for a RevZilla product URL.
     */
    public function clearCacheForUrl(string $productUrl): void
    {
        $hash = md5(esc_url_raw($productUrl));
        delete_transient(self::CACHE_GROUP . '_' . $hash);
        delete_transient(self::CACHE_GROUP . '_all_' . $hash);
    }

    /**
     * Extract multiple product images from HTML.
     *
     * @return array<array{url: string, provider: string}>
     */
    private function extractAllImagesFromHtml(string $html, string $baseUrl): array
    {
        $images = [];
        // 1. Meta tags (og:image, twitter:image) - flexible order of property/name and content
        $metaPatterns = [
            '/<meta[^>]+(?:property|name)=["\'](?:og|twitter):image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\'](?:og|twitter):image["\']/i',
        ];

        foreach ($metaPatterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $url = $this->normalizeImageUrl($m[1], $baseUrl);
                if ($this->isValidImageUrl($url)) {
                    $images[] = ['url' => $url, 'provider' => 'revzilla'];
                    break;
                }
            }
        }

        // 2. Look for all potential product images in common attributes (RevZilla often uses data-src or data-zoom-image)
        if (preg_match_all('/(?:data-src|src|data-zoom-image)=["\']([^"\']+\.(?:jpg|jpeg|png|webp|gif)[^"\']*)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $url = $this->normalizeImageUrl($url, $baseUrl);
                $isProductImage = str_contains(strtolower($url), 'revzilla') && str_contains($url, '/product_images/');
                if ($this->isValidImageUrl($url) && $isProductImage) {
                    // Avoid duplicates
                    $exists = false;
                    foreach ($images as $img) {
                        if ($img['url'] === $url) {
                            $exists = true;
                            break;
                        }
                    }
                    if (! $exists) {
                        $images[] = ['url' => $url, 'provider' => 'revzilla'];
                    }
                }
            }
        }

        return $images;
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

    /**
     * Normalize URL and optionally upgrade to high-resolution.
     */
    private function normalizeImageUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $parsed = wp_parse_url($baseUrl);
            $scheme = $parsed['scheme'] ?? 'https';
            $host   = $parsed['host'] ?? 'www.revzilla.com';
            $path   = str_starts_with($url, '/') ? $url : '/' . ltrim($url, '/');
            $url    = $scheme . '://' . $host . $path;
        }

        // RevZilla high-res upgrade: replace _150, _300, _600 with _900 or original
        // Example: .../product_images/001/0101/arai_corsair_x_helmet_white_150.jpg
        // Also support _w150, _w300, _w600 -> _w1200
        if (str_contains($url, 'revzilla')) {
            if (preg_match('/_(\d{3})\.(jpg|jpeg|png|webp)$/i', $url, $m)) {
                $size = (int) $m[1];
                if ($size < 900) {
                    $url = str_replace('_' . $m[1] . '.' . $m[2], '_900.' . $m[2], $url);
                }
            } elseif (preg_match('/_w(\d{2,4})\.(jpg|jpeg|png|webp)$/i', $url, $m)) {
                $size = (int) $m[1];
                if ($size < 1200) {
                    $url = str_replace('_w' . $m[1] . '.' . $m[2], '_w1200.' . $m[2], $url);
                }
            }
        }

        return $url;
    }
}
