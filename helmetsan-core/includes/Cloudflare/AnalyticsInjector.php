<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

use Helmetsan\Core\Support\Config;

/**
 * Injects the Cloudflare Web Analytics beacon script into the site footer.
 * Privacy-first analytics without cookies.
 */
class AnalyticsInjector
{
    private string $cfAnalyticsToken;
    private ?\Helmetsan\Core\Geo\GeoService $geo;

    public function __construct(Config $config, ?\Helmetsan\Core\Geo\GeoService $geo = null)
    {
        $settings = get_option(Config::OPTION_ANALYTICS, $config->analyticsDefaults());
        // Retrieve the token from settings, or fall back to a CONSTANT for dev overriding
        $this->cfAnalyticsToken = $settings['cf_analytics_token']
            ?? (defined('HELMETSAN_CF_ANALYTICS_TOKEN') ? constant('HELMETSAN_CF_ANALYTICS_TOKEN') : '');
        $this->geo = $geo;
    }

    public function bootstrap(): void
    {
        // Disable for China visitors to prevent GFW loading lag
        if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) {
            return;
        }

        if (!empty($this->cfAnalyticsToken) && !is_admin()) {
            add_action('wp_footer', [$this, 'injectBeacon'], 99);
        }
    }

    public function injectBeacon(): void
    {
        // Must output the exact script tag required by Cloudflare
        echo sprintf(
            "\n<!-- Cloudflare Web Analytics -->\n<script defer src='https://static.cloudflareinsights.com/beacon.min.js' data-cf-beacon='{\"token\": \"%s\"}'></script>\n<!-- End Cloudflare Web Analytics -->\n",
            esc_attr($this->cfAnalyticsToken)
        );
    }
}
