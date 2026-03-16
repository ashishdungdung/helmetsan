<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Ingestion\LogRepository;

class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly Config $config,
        private readonly LogRepository $logger
    ) {}

    /**
     * Verifies a Turnstile token with Cloudflare.
     * 
     * @param string $token The cf-turnstile-response token from the client.
     * @param string|null $ip The client's IP address (optional).
     * @return bool True if verification succeeded, false otherwise.
     */
    public function verify(string $token, ?string $ip = null): bool
    {
        $securityConfig = $this->config->securityConfig();

        if (!($securityConfig['enable_turnstile'] ?? false)) {
            // If Turnstile is not enabled, we bypass verification (fail open or closed depends on implementation, but typically open if not enabled)
            return true;
        }

        $secretKey = $securityConfig['turnstile_secret_key'] ?? '';
        if ($secretKey === '') {
            $this->logger->error('Turnstile verification failed: Secret key not configured.', ['context' => 'turnstile']);
            return false;
        }

        $body = [
            'secret'   => $secretKey,
            'response' => $token,
        ];

        if ($ip !== null) {
            $body['remoteip'] = $ip;
        }

        $args = [
            'body'    => $body,
            'timeout' => 10,
        ];

        $response = wp_remote_post(self::VERIFY_URL, $args);

        if (is_wp_error($response)) {
            $this->logger->error('Turnstile verification API error', [
                'error'   => $response->get_error_message(),
                'context' => 'turnstile',
            ]);
            return false;
        }

        $responseBody = wp_remote_retrieve_body($response);
        $data         = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Turnstile verification response invalid JSON', [
                'body'    => $responseBody,
                'context' => 'turnstile',
            ]);
            return false;
        }

        if (!isset($data['success']) || $data['success'] !== true) {
            $this->logger->warning('Turnstile verification failed', [
                'error-codes' => $data['error-codes'] ?? [],
                'context'     => 'turnstile',
            ]);
            return false;
        }

        return true;
    }
}
