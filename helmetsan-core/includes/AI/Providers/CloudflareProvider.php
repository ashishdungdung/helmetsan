<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

/**
 * Cloudflare Workers AI provider.
 * Uses the REST API: https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/run/{model}
 * The Account ID is passed as base_url string.
 */
final class CloudflareProvider extends BaseProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = '@cf/meta/llama-3-8b-instruct',
        private readonly string $accountId = ''
    ) {
    }

    public function getId(): string
    {
        return 'cloudflare';
    }

    public function getLabel(): string
    {
        return 'Cloudflare';
    }

    public function getTier(): string
    {
        return 'free';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->accountId !== '';
    }

    public function prepareRequest(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $endpoint = sprintf('https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s', urlencode($this->accountId), urlencode($this->model));
        $messages = [
            ['role' => 'system', 'content' => "You are a helpful, professional motorcycle helmet expert AI."],
            ['role' => 'user', 'content' => $prompt]
        ];

        return [
            'url' => $endpoint,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'messages' => $messages,
                'max_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
                'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
            ]),
        ];
    }

    public function generate(string $prompt, array $options = []): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $endpoint = sprintf('https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s', urlencode($this->accountId), urlencode($this->model));

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => "You are a helpful, professional motorcycle helmet expert AI."];
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
        ];

        $data = $this->post($endpoint, [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], $body);

        if ($data === null || empty($data['success']) || empty($data['result']['response'])) {
            return null;
        }

        return $this->normalizeText((string) $data['result']['response']);
    }
}
