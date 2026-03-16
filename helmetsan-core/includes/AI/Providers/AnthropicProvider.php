<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

final class AnthropicProvider extends BaseProvider
{
    private const URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-20250514'
    ) {
    }

    public function getId(): string
    {
        return 'anthropic';
    }

    public function getLabel(): string
    {
        return 'Anthropic (Claude)';
    }

    public function getTier(): string
    {
        return 'premium';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }
    public function prepareRequest(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $body = [
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }
        return [
            'url' => self::URL,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ];
    }


    public function generate(string $prompt, array $options = []): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $body = [
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }
        $data = $this->post(self::URL, [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ], $body);
        if ($data === null || empty($data['content'][0]['text'])) {
            return null;
        }
        return $this->normalizeText((string) $data['content'][0]['text']);
    }
}
