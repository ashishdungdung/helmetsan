<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Serves ads.txt at the site root for AdSense compliance (IAB Authorized Digital Sellers).
 * When the static file is not present (e.g. before deploy), WordPress serves the same content.
 */
final class AdsTxt
{
    /** AdSense publisher ID (ca-pub-XXXXXXXXXX → pub-XXXXXXXXXX in ads.txt). */
    private const PUBLISHER_ID = 'pub-5006746847998381';

    /** Google's certification authority ID for ads.txt (AdSense). */
    private const GOOGLE_CERTIFICATION_ID = 'f08c47fec0942fa0';

    public function register(): void
    {
        add_action('template_redirect', [$this, 'serveAdsTxt'], 0);
    }

    /**
     * If the request is for /ads.txt, output compliant ads.txt and exit.
     */
    public function serveAdsTxt(): void
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        if ($path !== 'ads.txt') {
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $this->getAdsTxtContent();
        exit;
    }

    /**
     * Returns the full ads.txt body (IAB-compliant line for Google AdSense).
     */
    public function getAdsTxtContent(): string
    {
        $line = sprintf(
            "google.com, %s, DIRECT, %s\n",
            self::PUBLISHER_ID,
            self::GOOGLE_CERTIFICATION_ID
        );
        return "# Helmetsan Authorized Digital Sellers (IAB ads.txt)\n# https://support.google.com/adsense/answer/12171612\n" . $line;
    }
}
