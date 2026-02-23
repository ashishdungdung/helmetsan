<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

final class PerplexityProvider extends BaseProvider
{
    private const URL = 'https://api.perplexity.ai/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'sonar'
    ) {
    }

    public function getId(): string
    {
        return 'perplexity';
    }

    public function getLabel(): string
    {
        return 'Perplexity';
    }

    public function getTier(): string
    {
        return 'premium';
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
