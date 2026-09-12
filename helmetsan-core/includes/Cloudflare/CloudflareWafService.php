<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

use WP_Error;

/**
 * Handles Cloudflare WAF and Access Rules operations.
 */
class CloudflareWafService
{
    /**
     * Set a challenge (CAPTCHA/Managed Challenge) rule for a country.
     */
    public function challengeCountry(string $countryCode): bool|WP_Error
    {
        $zoneId = defined('HELMETSAN_CLOUDFLARE_ZONE_ID') ? HELMETSAN_CLOUDFLARE_ZONE_ID : '';
        $apiToken = defined('HELMETSAN_CLOUDFLARE_API_TOKEN') ? HELMETSAN_CLOUDFLARE_API_TOKEN : '';

        if (empty($zoneId) || empty($apiToken)) {
            return new WP_Error('cf_not_configured', 'Cloudflare API credentials are not defined.');
        }

        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/firewall/access_rules/rules', urlencode($zoneId));

        $payload = [
            'mode'          => 'challenge',
            'configuration' => [
                'target' => 'country',
                'value'  => strtoupper($countryCode),
            ],
            'notes'         => 'Automated GA4 Traffic Spike Anomaly Mitigation',
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('cf_network_error', 'Failed to contact Cloudflare WAF: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 && $code !== 201) {
            $err = $data['errors'][0]['message'] ?? 'Unknown Cloudflare WAF API error.';
            return new WP_Error('cf_api_error', 'Cloudflare WAF Rule Creation Failed: ' . $err);
        }

        return true;
    }
}
