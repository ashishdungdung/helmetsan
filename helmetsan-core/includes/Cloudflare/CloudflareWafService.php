<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

use WP_Error;

/**
 * Handles Cloudflare WAF, Bot Management, and Edge Access Rules.
 */
class CloudflareWafService
{
    /**
     * @return array{zone_id: string, api_token: string}|WP_Error
     */
    private function getCredentials(): array|WP_Error
    {
        $zoneId = defined('HELMETSAN_CLOUDFLARE_ZONE_ID') ? HELMETSAN_CLOUDFLARE_ZONE_ID : '';
        $apiToken = defined('HELMETSAN_CLOUDFLARE_API_TOKEN') ? HELMETSAN_CLOUDFLARE_API_TOKEN : '';

        if (empty($zoneId) || empty($apiToken)) {
            return new WP_Error('cf_not_configured', 'Cloudflare API credentials (HELMETSAN_CLOUDFLARE_ZONE_ID / HELMETSAN_CLOUDFLARE_API_TOKEN) are not defined.');
        }

        return ['zone_id' => $zoneId, 'api_token' => $apiToken];
    }

    /**
     * Set a challenge (CAPTCHA/Managed Challenge) rule for a country.
     */
    public function challengeCountry(string $countryCode): bool|WP_Error
    {
        $creds = $this->getCredentials();
        if (is_wp_error($creds)) {
            return $creds;
        }

        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/firewall/access_rules/rules', urlencode($creds['zone_id']));

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
                'Authorization' => 'Bearer ' . $creds['api_token'],
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

    /**
     * Get Cloudflare Bot Management settings.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function getBotManagementStatus(): array|WP_Error
    {
        $creds = $this->getCredentials();
        if (is_wp_error($creds)) {
            return $creds;
        }

        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/bot_management', urlencode($creds['zone_id']));

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $creds['api_token'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('cf_network_error', 'Failed to retrieve Bot Management status: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data['success'])) {
            $err = $data['errors'][0]['message'] ?? 'Unknown Cloudflare API error.';
            return new WP_Error('cf_api_error', 'Cloudflare Bot Management query failed: ' . $err);
        }

        return (array) ($data['result'] ?? []);
    }

    /**
     * Enable AI scraper and crawler blocking at Cloudflare Edge.
     */
    public function enableAiScraperBlocking(): bool|WP_Error
    {
        $creds = $this->getCredentials();
        if (is_wp_error($creds)) {
            return $creds;
        }

        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/bot_management', urlencode($creds['zone_id']));

        $payload = [
            'ai_bots_protection' => 'block',
            'ai_training'        => 'disallow',
            'fight_mode'         => true,
        ];

        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $creds['api_token'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('cf_network_error', 'Failed to update Bot Management: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data['success'])) {
            $err = $data['errors'][0]['message'] ?? 'Unknown Cloudflare API error.';
            return new WP_Error('cf_api_error', 'Cloudflare Bot Management update failed: ' . $err);
        }

        return true;
    }

    /**
     * Retrieve the entrypoint rules for phase http_request_firewall_custom.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function getCustomRuleset(): array|WP_Error
    {
        $creds = $this->getCredentials();
        if (is_wp_error($creds)) {
            return $creds;
        }

        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/rulesets/phases/http_request_firewall_custom/entrypoint', urlencode($creds['zone_id']));

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $creds['api_token'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('cf_network_error', 'Failed to retrieve custom ruleset: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data['success'])) {
            $err = $data['errors'][0]['message'] ?? 'Unknown Cloudflare API error.';
            return new WP_Error('cf_api_error', 'Cloudflare Ruleset query failed: ' . $err);
        }

        return (array) ($data['result'] ?? []);
    }

    /**
     * Ensure the custom edge WAF rule blocking AI scrapers (ByteDance, Diffbot, Amazonbot, etc.) exists and is active.
     */
    public function syncAiScraperEdgeRule(): bool|WP_Error
    {
        $creds = $this->getCredentials();
        if (is_wp_error($creds)) {
            return $creds;
        }

        $ruleset = $this->getCustomRuleset();
        if (is_wp_error($ruleset)) {
            return $ruleset;
        }

        $rulesetId = $ruleset['id'] ?? '';
        if (empty($rulesetId)) {
            return new WP_Error('cf_ruleset_missing', 'Custom firewall ruleset ID not found.');
        }

        $existingRules = $ruleset['rules'] ?? [];
        $aiExpression = '(http.user_agent contains "Bytespider" or http.user_agent contains "ByteSpider" or http.user_agent contains "Diffbot" or http.user_agent contains "Amazonbot" or http.user_agent contains "CCBot" or http.user_agent contains "DataForSeoBot") and not cf.client.bot';
        $description = 'Block Unattributed AI Scrapers and Commercial Harvesters (ByteDance, Diffbot, Amazonbot) at Edge';

        foreach ($existingRules as $rule) {
            if (isset($rule['expression']) && str_contains((string) $rule['expression'], 'Bytespider') && ($rule['action'] ?? '') === 'block') {
                return true; // Already synchronized
            }
        }

        // Add rule to ruleset via POST to rules
        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/rulesets/%s/rules', urlencode($creds['zone_id']), urlencode((string) $rulesetId));

        $payload = [
            'action'      => 'block',
            'expression'  => $aiExpression,
            'description' => $description,
            'enabled'     => true,
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $creds['api_token'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('cf_network_error', 'Failed to append rule: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 && $code !== 201) {
            $err = $data['errors'][0]['message'] ?? 'Unknown Cloudflare API error.';
            return new WP_Error('cf_api_error', 'Cloudflare WAF Rule Sync Failed: ' . $err);
        }

        return true;
    }
}
