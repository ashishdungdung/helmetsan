<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

final class GeminiProvider extends BaseProvider
{
    private const URL_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-1.5-flash'
    ) {
    }

    public function getId(): string
    {
        return 'gemini';
    }

    public function getLabel(): string
    {
        return 'Google Gemini';
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
        $url = sprintf(self::URL_TEMPLATE, $this->model) . '?key=' . rawurlencode($this->apiKey);
        $body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
                'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
            ],
        ];
        $data = $this->post($url, [], $body);
        if ($data === null || empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return null;
        }
        return $this->normalizeText((string) $data['candidates'][0]['content']['parts'][0]['text']);
    }
}
