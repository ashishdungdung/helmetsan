<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Injects Google AdSense script on the frontend when enabled in Settings.
 * Requires ads.txt to be in place for compliance (see AdsTxt service).
 */
final class AdSense
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'injectScript'], 1);
    }

    public function injectScript(): void
    {
        if (is_admin() || is_feed()) {
            return;
        }

        $cfg = $this->config->adsenseConfig();
        if (empty($cfg['enable_adsense'])) {
            return;
        }

        $publisherId = isset($cfg['publisher_id']) ? trim((string) $cfg['publisher_id']) : '';
        if ($publisherId === '') {
            return;
        }

        $publisherId = preg_replace('/[^a-z0-9\-]/i', '', $publisherId);
        if ($publisherId === '') {
            return;
        }

        if (! str_starts_with(strtolower($publisherId), 'ca-pub-')) {
            $publisherId = 'ca-pub-' . $publisherId;
        }

        $scriptUrl = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_attr($publisherId);
        echo '<!-- Helmetsan AdSense -->' . "\n";
        echo '<script async src="' . esc_url($scriptUrl) . '" crossorigin="anonymous"></script>' . "\n";
    }

    public function isEnabled(): bool
    {
        $cfg = $this->config->adsenseConfig();
        return ! empty($cfg['enable_adsense']) && trim((string) ($cfg['publisher_id'] ?? '')) !== '';
    }
}
