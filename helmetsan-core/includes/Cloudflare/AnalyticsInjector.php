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

    public function __construct(Config $config)
    {
        $settings = get_option(Config::OPTION_ANALYTICS, $config->analyticsDefaults());
        // Retrieve the token from settings, or fall back to a CONSTANT for dev overriding
        $this->cfAnalyticsToken = $settings['cf_analytics_token']
            ?? (defined('HELMETSAN_CF_ANALYTICS_TOKEN') ? constant('HELMETSAN_CF_ANALYTICS_TOKEN') : '');
    }

    public function bootstrap(): void
    {
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
