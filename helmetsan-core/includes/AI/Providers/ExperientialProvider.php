<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

/**
 * Experiential Labs Multi-Model AI Gateway Provider
 * 
 * Routes Chat Completions requests to https://api.experientiallabs.ai/v1
 * across 300+ models (OpenAI, Claude, Qwen, DeepSeek, Mistral, etc.)
 * with unified credits drawdown and BYOK support.
 *
 * @see https://platform.experientiallabs.ai/docs
 * @see https://platform.experientiallabs.ai/models
 */
final class ExperientialProvider extends BaseProvider
{
    public const DEFAULT_BASE_URL = 'https://api.experientiallabs.ai/v1';
    public const DEFAULT_MODEL = 'gpt-6-luna';

    public const ALLOWED_HOSTS = ['api.experientiallabs.ai'];

    private string $resolvedApiKey;
    private string $resolvedBaseUrl;
    private ?array $lastError = null;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL
    ) {
        $envKey = getenv('EXPLABS_API_KEY');
        $key = !empty($envKey) ? trim((string) $envKey) : trim($this->apiKey);

        $isTesting = defined('PHPUNIT_COMPOSER_INSTALL') || (getenv('APP_ENV') === 'testing') || (bool) getenv('HELMETSAN_DISABLE_VAULT_FALLBACK');
        if ($key === '' && ! $isTesting) {
            $vault = ($_SERVER['HOME'] ?? getenv('HOME') ?? '/Users/anumac') . '/.config/antigravity/ai_mesh.env';
            if (file_exists($vault)) {
                $lines = file($vault, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, 'EXPLABS_API_KEY=')) {
                        $key = trim(substr($trimmed, strlen('EXPLABS_API_KEY=')));
                        break;
                    }
                }
            }
        }

        if ($key === '') {
            throw new \RuntimeException('EXPLABS_API_KEY environment variable is not set. Please create one under Settings -> API Keys and export it.');
        }

        $this->resolvedApiKey = $key;
        $this->resolvedBaseUrl = $this->validateBaseUrl(!empty($this->baseUrl) ? $this->baseUrl : self::DEFAULT_BASE_URL);
    }

    /**
     * SSRF Protection: Validates gateway base URL to guarantee trusted HTTPS endpoint.
     */
    private function validateBaseUrl(string $url): string
    {
        $trimmed = rtrim($url, '/');
        $parsed = parse_url($trimmed);
        $scheme = $parsed['scheme'] ?? '';
        $host = $parsed['host'] ?? '';

        if ($scheme !== 'https') {
            throw new \InvalidArgumentException("Insecure gateway scheme '{$scheme}'. Experiential Labs gateway requires HTTPS.");
        }

        $isAllowed = in_array($host, self::ALLOWED_HOSTS, true) || str_ends_with($host, '.experientiallabs.ai');
        if (! $isAllowed) {
            throw new \InvalidArgumentException("Untrusted gateway host '{$host}'. Only experientiallabs.ai endpoints are permitted.");
        }

        return $trimmed;
    }

    public function getId(): string
    {
        return 'experiential';
    }

    public function getLabel(): string
    {
        return 'Experiential Labs (' . $this->model . ')';
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

    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    /**
     * Query gateway model catalog to dynamically list available models.
     *
     * @return list<array{id: string, owned_by?: string}>
     */
    public function listModels(): array
    {
        $url = $this->resolvedBaseUrl . '/models';
        $headers = [
            'Authorization' => 'Bearer ' . $this->resolvedApiKey,
            'Content-Type'  => 'application/json',
        ];

        if (function_exists('wp_remote_get')) {
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'headers' => $headers,
            ]);
            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                return [];
            }
            $raw = (string) wp_remote_retrieve_body($response);
        } else {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->resolvedApiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch);
            if ($code !== 200 || $raw === false) {
                return [];
            }
        }

        $data = json_decode((string) $raw, true);
        return is_array($data['data'] ?? null) ? $data['data'] : [];
    }

    public function prepareRequest(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $messages = !empty($options['messages']) && is_array($options['messages'])
            ? $options['messages']
            : [['role' => 'user', 'content' => $prompt]];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
        ];

        if (!empty($options['prompt_cache_key'])) {
            $payload['prompt_cache_key'] = (string) $options['prompt_cache_key'];
        }

        // Mutual exclusivity: only emit one token limit parameter to prevent gateway rejection
        if (isset($options['max_completion_tokens'])) {
            $payload['max_completion_tokens'] = (int) $options['max_completion_tokens'];
        } else {
            $payload['max_tokens'] = (int) ($options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS);
        }

        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        // Preserve streaming and tool-calling
        if (isset($options['stream'])) {
            $payload['stream'] = (bool) $options['stream'];
        }
        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }
        if (isset($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->resolvedApiKey,
            'Content-Type' => 'application/json',
        ];
        if (!empty($options['prompt_cache_key'])) {
            $headers['X-Prompt-Cache-Key'] = (string) $options['prompt_cache_key'];
        }

        return [
            'url' => $this->resolvedBaseUrl . '/chat/completions',
            'headers' => $headers,
            'body' => wp_json_encode($payload),
        ];
    }

    public function generate(string $prompt, array $options = []): ?string
    {
        $result = $this->generateDetailed($prompt, $options);
        return $result['content'] ?? null;
    }

    /**
     * Executes completion request and returns reply content, token counts, cached tokens, and cost.
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return array{content: ?string, tool_calls: ?list<array>, finish_reason: ?string, usage: ?array<string, mixed>, cached_tokens: int, cache_hit_rate: float, cost: ?float, raw: ?array<string, mixed>}|null
     */
    public function generateDetailed(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $messages = !empty($options['messages']) && is_array($options['messages'])
            ? $options['messages']
            : [['role' => 'user', 'content' => $prompt]];

        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? self::DEFAULT_TEMPERATURE,
        ];

        if (!empty($options['prompt_cache_key'])) {
            $body['prompt_cache_key'] = (string) $options['prompt_cache_key'];
        }

        // Mutual exclusivity: only emit one token limit parameter to prevent gateway rejection
        if (isset($options['max_completion_tokens'])) {
            $body['max_completion_tokens'] = (int) $options['max_completion_tokens'];
        } else {
            $body['max_tokens'] = (int) ($options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS);
        }

        if (isset($options['response_format'])) {
            $body['response_format'] = $options['response_format'];
        }

        // Preserve streaming and tool-calling
        if (isset($options['stream'])) {
            $body['stream'] = (bool) $options['stream'];
        }
        if (isset($options['tools'])) {
            $body['tools'] = $options['tools'];
        }
        if (isset($options['tool_choice'])) {
            $body['tool_choice'] = $options['tool_choice'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->resolvedApiKey,
            'Content-Type' => 'application/json',
        ];
        if (!empty($options['prompt_cache_key'])) {
            $headers['X-Prompt-Cache-Key'] = (string) $options['prompt_cache_key'];
        }

        $endpoint = $this->resolvedBaseUrl . '/chat/completions';
        $timeout = (int) ($options['timeout'] ?? 60);

        $data = $this->postWithTimeout($endpoint, $headers, $body, $timeout);

        if ($data === null) {
            $this->lastError = [
                'status' => 'http_error',
                'message' => 'Failed to receive a valid response from Experiential Labs gateway.',
                'model' => $this->model,
            ];
            return null;
        }

        $this->lastError = null;

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

        $usage = $data['usage'] ?? [];
        $cachedTokens = 0;
        if (isset($usage['prompt_tokens_details']['cached_tokens'])) {
            $cachedTokens = (int) $usage['prompt_tokens_details']['cached_tokens'];
        } elseif (isset($usage['cached_tokens'])) {
            $cachedTokens = (int) $usage['cached_tokens'];
        }
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $cacheHitRate = ($promptTokens > 0) ? round(($cachedTokens / $promptTokens) * 100, 1) : 0.0;
        $cost = isset($usage['cost']) ? (float) $usage['cost'] : null;

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'finish_reason' => $finishReason,
            'usage' => $usage,
            'cached_tokens' => $cachedTokens,
            'cache_hit_rate' => $cacheHitRate,
            'cost' => $cost,
            'raw' => $data,
        ];
    }

    /**
     * Executes HTTP POST with configurable extended timeout and retry on 429/5xx.
     */
    protected function postWithTimeout(string $url, array $headers, array $body, int $timeout = 60): ?array
    {
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if (function_exists('wp_remote_post')) {
                $response = wp_remote_post($url, [
                    'timeout' => $timeout,
                    'headers' => $headers,
                    'body' => wp_json_encode($body),
                ]);

                if (! is_wp_error($response)) {
                    $code = (int) wp_remote_retrieve_response_code($response);
                    if ($code === 200) {
                        $data = json_decode((string) wp_remote_retrieve_body($response), true);
                        if (is_array($data)) {
                            return $data;
                        }
                    } elseif ($code === 429 || ($code >= 500 && $code < 600)) {
                        usleep(1000000 * $attempt);
                        continue;
                    }
                }
            } else {
                $ch = curl_init($url);
                $formattedHeaders = [];
                foreach ($headers as $k => $v) {
                    $formattedHeaders[] = "{$k}: {$v}";
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                $raw = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                unset($ch);

                if ($code === 200 && $raw !== false) {
                    $data = json_decode((string) $raw, true);
                    if (is_array($data)) {
                        return $data;
                    }
                } elseif ($code === 429 || ($code >= 500 && $code < 600)) {
                    usleep(1000000 * $attempt);
                    continue;
                }
            }
        }

        return null;
    }
}
