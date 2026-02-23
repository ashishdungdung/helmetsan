<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

final class GroqProvider extends BaseProvider
{
    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'llama-3.1-8b-instant'
    ) {
    }

    public function getId(): string
    {
        return 'groq';
    }

    public function getLabel(): string
    {
        return 'Groq';
    }

    public function getTier(): string
    {
        return 'free';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function generate(string $prompt, array $options = []): ?string
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
        $data = $this->post(self::URL, [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], $body);
        if ($data === null || empty($data['choices'][0]['message']['content'])) {
            return null;
        }
        return $this->normalizeText((string) $data['choices'][0]['message']['content']);
    }
}
