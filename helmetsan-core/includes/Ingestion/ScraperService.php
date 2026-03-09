<?php

declare(strict_types=1);

namespace Helmetsan\Core\Ingestion;

use WP_Error;

/**
 * Service to scrape product images from external URLs.
 */
class ScraperService
{
    /**
     * Attempts to scrape image URLs from a given product page URL.
     * Currently supports Myntra format (extracting from window.__myx JSON payload).
     *
     * @param string $url Target product URL.
     * @return array<string>|WP_Error Array of image URLs or WP_Error on failure.
     */
    public function scrapeImagesFromUrl(string $url)
    {
        $response = wp_remote_get($url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('scrape_failed', sprintf('Failed to fetch URL. HTTP status: %d', $code));
        }

        $body = wp_remote_retrieve_body($response);

        // Myntra-specific logic: looking for the embedded JSON state
        if (strpos($url, 'myntra.com') !== false) {
            return $this->parseMyntraImages($body);
        }

        // Generic fallback parsing (e.g. looking for og:image or first big image)
        return $this->parseGenericImages($body);
    }

    private function parseMyntraImages(string $html): array
    {
        $images = [];

        // Myntra commonly stores product data in a script block: window.__myx = {...}
        if (preg_match('/window\.__myx\s*=\s*(\{.*?\})\s*</s', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if (isset($jsonData['pdpData']['media']['albums'])) {
                foreach ($jsonData['pdpData']['media']['albums'] as $album) {
                    if (isset($album['images'])) {
                        foreach ($album['images'] as $img) {
                            if (isset($img['src'])) {
                                $images[] = $img['src'];
                            } elseif (isset($img['imageURL'])) {
                                $images[] = $img['imageURL'];
                            }
                        }
                    }
                }
            }

            // Fallback for older Myntra structure
            if (empty($images) && isset($jsonData['pdpData']['activeProduct']['media']['albums'])) {
                foreach ($jsonData['pdpData']['activeProduct']['media']['albums'] as $album) {
                    if (isset($album['images'])) {
                        foreach ($album['images'] as $img) {
                            if (isset($img['src'])) {
                                $images[] = $img['src'];
                            }
                        }
                    }
                }
            }
        }

        // Fallback: simple Regex for Myntra image assets if JSON parsing fails
        if (empty($images)) {
            if (preg_match_all('/"imageURL":"(https:\/\/[^"]+\.jpg)"/', $html, $matches)) {
                $images = $matches[1];
            }
        }

        return array_values(array_unique($images));
    }

    private function parseGenericImages(string $html): array
    {
        // extremely basic generic extraction of large images
        $images = [];
        if (preg_match_all('/<meta property="og:image" content="([^"]+)"/', $html, $matches)) {
            $images = array_merge($images, $matches[1]);
        }

        return array_unique($images);
    }
}
