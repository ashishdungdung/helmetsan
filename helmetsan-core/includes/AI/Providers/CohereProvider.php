<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

final class CohereProvider extends BaseProvider
{
    private const URL = 'https://api.cohere.com/v1/chat';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'command-r-plus'
    ) {
    }

    public function getId(): string
    {
        return 'cohere';
    }

    public function getLabel(): string
    {
        return 'Cohere';
    }

    public function getTier(): string
    {
        return 'free';
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
            'message' => $prompt,
            'model' => $this->model,
        ];
        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }
        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }
        return [
            'url' => self::URL,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
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
            'message' => $prompt,
            'model' => $this->model,
        ];
        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }
        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }
        $data = $this->post(self::URL, [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], $body);
        if ($data === null || empty($data['text'])) {
            return null;
        }
        return $this->normalizeText((string) $data['text']);
    }
}
