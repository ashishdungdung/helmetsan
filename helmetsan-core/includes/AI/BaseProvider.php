<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Base for AI providers: HTTP POST, timeout, normalize response text.
 */
abstract class BaseProvider implements ProviderInterface
{
    protected const DEFAULT_TIMEOUT = 25;
    protected const DEFAULT_MAX_TOKENS = 256;
    protected const DEFAULT_TEMPERATURE = 0.4;

    protected function post(string $url, array $headers, array $body): ?array
    {
        $response = wp_remote_post($url, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : null;
    }

    protected function normalizeText(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $s = trim($s);
        $s = preg_replace('/^["\']|["\']$/u', '', $s);
        return trim($s);
    }
}
