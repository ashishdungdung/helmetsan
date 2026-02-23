<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

/**
 * Hugging Face Inference API (free tier available).
 * Model format: "model-id" e.g. "mistralai/Mistral-7B-Instruct-v0.2"
 */
final class HuggingFaceProvider extends BaseProvider
{
    private const URL_TEMPLATE = 'https://api-inference.huggingface.co/models/%s';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'mistralai/Mistral-7B-Instruct-v0.2'
    ) {
    }

    public function getId(): string
    {
        return 'huggingface';
    }

    public function getLabel(): string
    {
        return 'Hugging Face';
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
        $url = sprintf(self::URL_TEMPLATE, $this->model);
        $body = [
            'inputs' => $prompt,
            'parameters' => [
                'max_new_tokens' => $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
                'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
                'return_full_text' => false,
            ],
        ];
        $data = $this->post($url, [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], $body);
        if ($data === null || ! is_array($data)) {
            return null;
        }
        if (isset($data[0]['generated_text'])) {
            return $this->normalizeText((string) $data[0]['generated_text']);
        }
        if (isset($data['generated_text'])) {
            return $this->normalizeText((string) $data['generated_text']);
        }
        return null;
    }
}
