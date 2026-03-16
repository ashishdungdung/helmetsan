<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

/**
 * Local LLM via LM Studio (or any OpenAI-compatible server).
 * Use base_url e.g. http://localhost:1234/v1 (LM Studio default).
 * API key optional; LM Studio often requires none.
 */
final class LMStudioProvider extends BaseProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model = 'local',
        private readonly string $apiKey = ''
    ) {
    }

    public function getId(): string
    {
        return 'lm_studio';
    }

    public function getLabel(): string
    {
        return 'LM Studio (local)';
    }

    public function getTier(): string
    {
        return 'free';
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    public function prepareRequest(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        return [
            'url' => rtrim($this->baseUrl, '/') . '/chat/completions',
            'headers' => $headers,
            'body' => wp_json_encode([
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
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
        $url = rtrim($this->baseUrl, '/') . '/chat/completions';
        $body = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
        ];
        $headers = [];
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        $data = $this->post($url, $headers, $body);
        if ($data === null || empty($data['choices'][0]['message']['content'])) {
            return null;
        }
        return $this->normalizeText((string) $data['choices'][0]['message']['content']);
    }
}
