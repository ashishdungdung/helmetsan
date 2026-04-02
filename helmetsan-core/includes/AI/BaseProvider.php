<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Base for AI providers: HTTP POST, timeout, optional retry on 5xx, normalize response text.
 */
abstract class BaseProvider implements ProviderInterface
{
    protected const DEFAULT_TIMEOUT = 30;
    protected const DEFAULT_MAX_TOKENS = 256;
    protected const DEFAULT_TEMPERATURE = 0.4;
    private const RETRY_ON_5XX_SLEEP_SECONDS = 2;

    /**
     * POST request with optional single retry on 5xx.
     */
    protected function post(string $url, array $headers, array $body): ?array
    {
        $response = $this->doPost($url, $headers, $body);
        if (is_wp_error($response)) {
            return null;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            $data = json_decode((string) wp_remote_retrieve_body($response), true);
            return is_array($data) ? $data : null;
        }
        if ($code >= 500 && $code < 600) {
            sleep(self::RETRY_ON_5XX_SLEEP_SECONDS);
            $retry = $this->doPost($url, $headers, $body);
            if (! is_wp_error($retry) && (int) wp_remote_retrieve_response_code($retry) === 200) {
                $data = json_decode((string) wp_remote_retrieve_body($retry), true);
                return is_array($data) ? $data : null;
            }
        }
        return null;
    }

    private function doPost(string $url, array $headers, array $body): \WP_Error|array
    {
        return wp_remote_post($url, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body' => wp_json_encode($body),
        ]);
    }

    /**
     * Prepare request data for parallel execution (e.g. via curl_multi).
     * @return array{url: string, headers: array<string, string>, body: string}|null
     */
    abstract public function prepareRequest(string $prompt, array $options = []): ?array;

    public function getConcurrency(): int
    {
        return 1;
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
