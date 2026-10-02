<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

final class OpenAIProvider extends BaseProvider
{
    public const OPENAI_BASE_URL = 'https://api.openai.com/v1';

    private string $resolvedApiKey;
    private string $resolvedBaseUrl;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $model = 'gpt-4o-mini',
        private readonly string $baseUrl = ''
    ) {
        $this->resolvedApiKey = trim($this->apiKey);
        $this->resolvedBaseUrl = !empty($this->baseUrl) ? rtrim($this->baseUrl, '/') : self::OPENAI_BASE_URL;
    }

    public function getId(): string
    {
        return 'openai';
    }

    public function getLabel(): string
    {
        return 'OpenAI (' . $this->model . ')';
    }

    public function getTier(): string
    {
        return 'premium';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getBaseUrl(): string
    {
        return $this->resolvedBaseUrl;
    }

    public function isConfigured(): bool
    {
        return $this->resolvedApiKey !== '';
    }

    public function prepareRequest(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $payload = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
        ];

        // Preserve streaming and tool-calls
        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }
        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }
        if (isset($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }

        return [
            'url' => $this->resolvedBaseUrl . '/chat/completions',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->resolvedApiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ];
    }

    public function generate(string $prompt, array $options = []): ?string
    {
        $result = $this->generateDetailed($prompt, $options);
        return $result['content'] ?? null;
    }

    /**
     * Executes completion request and returns both reply content and token usage.
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return array{content: ?string, usage: ?array<string, mixed>, raw: ?array<string, mixed>}|null
     */
    public function generateDetailed(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $body = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
        ];

        // Preserve streaming and tool-calls
        if (isset($options['stream'])) {
            $body['stream'] = (bool) $options['stream'];
        }
        if (isset($options['tools'])) {
            $body['tools'] = $options['tools'];
        }
        if (isset($options['tool_choice'])) {
            $body['tool_choice'] = $options['tool_choice'];
        }

        $endpoint = $this->resolvedBaseUrl . '/chat/completions';
        $data = $this->post($endpoint, [
            'Authorization' => 'Bearer ' . $this->resolvedApiKey,
        ], $body);

        if ($data === null) {
            return null;
        }

        $choice = $data['choices'][0] ?? [];
        $rawMessage = $choice['message'] ?? [];
        $rawContent = $rawMessage['content'] ?? null;
        $toolCalls = $rawMessage['tool_calls'] ?? null;
        $finishReason = $choice['finish_reason'] ?? null;

        $content = null;
        if (is_string($rawContent)) {
            $content = $this->normalizeText($rawContent);
        } elseif (is_array($rawContent)) {
            $textParts = [];
            foreach ($rawContent as $part) {
                if (is_array($part) && ($part['type'] ?? '') === 'text' && isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }
            $content = $this->normalizeText(implode("\n", $textParts));
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'finish_reason' => $finishReason,
            'usage' => $data['usage'] ?? null,
            'raw' => $data,
        ];
    }
}
