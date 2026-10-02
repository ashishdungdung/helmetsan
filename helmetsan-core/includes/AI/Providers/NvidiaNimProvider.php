<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Providers;

use Helmetsan\Core\AI\BaseProvider;

/**
 * NVIDIA NIM Enterprise Multi-Model AI Provider
 *
 * Integrates:
 * - moonshotai/kimi-k3 (1M Context, 16K Output, Reasoning & Multimodal Vision)
 * - deepseek-ai/deepseek-r1 (Deep Chain-of-Thought Reasoning)
 * - meta/llama-3.2-90b-vision-instruct (Visual Quality Auditing & OCR)
 * - black-forest-labs/flux.1-dev (SOTA Photorealistic Image Generation)
 * - black-forest-labs/flux.1-schnell (Fast Image Previews)
 * - stabilityai/stable-diffusion-3.5-large (Secondary Image Fallback)
 *
 * @see https://build.nvidia.com
 */
final class NvidiaNimProvider extends BaseProvider
{
    public const CHAT_BASE_URL = 'https://integrate.api.nvidia.com/v1';
    public const GENAI_BASE_URL = 'https://ai.api.nvidia.com/v1/genai';
    public const DEFAULT_MODEL = 'moonshotai/kimi-k3';

    public const ALLOWED_HOSTS = [
        'integrate.api.nvidia.com',
        'ai.api.nvidia.com',
    ];

    private string $resolvedApiKey;
    private string $resolvedBaseUrl;
    private ?array $lastError = null;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly string $baseUrl = self::CHAT_BASE_URL
    ) {
        $envKey = getenv('NVIDIA_API_KEY');
        $key = !empty($envKey) ? trim((string) $envKey) : trim($this->apiKey);

        // Fallback: check central vault (~/.config/antigravity/ai_mesh.env)
        if ($key === '') {
            $vault = ($_SERVER['HOME'] ?? getenv('HOME') ?? '/Users/anumac') . '/.config/antigravity/ai_mesh.env';
            if (file_exists($vault)) {
                $lines = file($vault, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, 'NVIDIA_API_KEY=')) {
                        $key = trim(substr($trimmed, strlen('NVIDIA_API_KEY=')));
                        break;
                    }
                }
            }
        }

        // Fallback: check HelmetsanManager/.env if in local environment
        if ($key === '') {
            $localEnv = dirname(__DIR__, 5) . '/HelmetsanManager/.env';
            if (file_exists($localEnv)) {
                $lines = file($localEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, 'NVIDIA_API_KEY=')) {
                        $key = trim(substr($trimmed, strlen('NVIDIA_API_KEY=')));
                        break;
                    }
                }
            }
        }

        $this->resolvedApiKey = $key;
        $this->resolvedBaseUrl = $this->validateBaseUrl(!empty($this->baseUrl) ? $this->baseUrl : self::CHAT_BASE_URL);
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
            throw new \InvalidArgumentException("Insecure gateway scheme '{$scheme}'. NVIDIA NIM requires HTTPS.");
        }

        $isAllowed = in_array($host, self::ALLOWED_HOSTS, true);
        if (! $isAllowed) {
            throw new \InvalidArgumentException("Untrusted gateway host '{$host}'. Only NVIDIA NIM endpoints are permitted.");
        }

        return $trimmed;
    }

    public function getId(): string
    {
        return 'nvidia_nim';
    }

    public function getLabel(): string
    {
        return 'NVIDIA NIM Multi-Model Fleet (' . $this->model . ')';
    }

    public function getTier(): string
    {
        return 'cloud_free';
    }

    public function isConfigured(): bool
    {
        return $this->resolvedApiKey !== '';
    }

    public function getConcurrency(): int
    {
        return 4;
    }

    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    /**
     * Generate completion for the given prompt.
     *
     * @param array{
     *     max_tokens?: int,
     *     temperature?: float,
     *     reasoning_effort?: string,
     *     seed?: int,
     *     json_schema?: array,
     *     image?: string,
     *     system_prompt?: string,
     *     timeout?: int
     * } $options
     */
    public function generate(string $prompt, array $options = []): ?string
    {
        if (! $this->isConfigured()) {
            $this->lastError = ['code' => 'NOT_CONFIGURED', 'message' => 'NVIDIA API key not set.'];
            return null;
        }

        $timeout = $options['timeout'] ?? 120; // Default generous 120s timeout for deep reasoning
        $url = $this->resolvedBaseUrl . '/chat/completions';

        $messages = [];
        if (!empty($options['system_prompt'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system_prompt'],
            ];
        }

        // Multimodal Vision handling
        if (!empty($options['image'])) {
            $imageUrl = $options['image'];
            if (file_exists($imageUrl)) {
                $mime = mime_content_type($imageUrl) ?: 'image/jpeg';
                $data = base64_encode((string) file_get_contents($imageUrl));
                $imageUrl = "data:{$mime};base64,{$data}";
            }

            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                ],
            ];
        } else {
            $messages[] = [
                'role' => 'user',
                'content' => $prompt,
            ];
        }

        $body = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        if (isset($options['reasoning_effort'])) {
            $body['reasoning_effort'] = $options['reasoning_effort'];
        }

        if (isset($options['seed'])) {
            $body['seed'] = (int) $options['seed'];
        }

        if (!empty($options['json_schema'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = wp_remote_post($url, [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->resolvedApiKey,
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $this->lastError = ['code' => 'WP_ERROR', 'message' => $response->get_error_message()];
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);

        if ($code !== 200) {
            $this->lastError = ['code' => 'HTTP_' . $code, 'message' => $rawBody];
            return null;
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
            $this->lastError = ['code' => 'MALFORMED_RESPONSE', 'message' => 'Missing choices content'];
            return null;
        }

        return $this->normalizeText($data['choices'][0]['message']['content']);
    }

    /**
     * Generate photorealistic image via NVIDIA NIM (FLUX.1-dev or FLUX.1-schnell).
     *
     * @param string $prompt
     * @param array{
     *     model?: string,
     *     width?: int,
     *     height?: int,
     *     steps?: int,
     *     guidance_scale?: float,
     *     cfg_scale?: float,
     *     seed?: int,
     *     timeout?: int
     * } $options
     * @return array{b64_json?: string, url?: string, metadata?: array, artifacts?: array, raw?: array, model_used?: string}|null
     */
    public function generateImage(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            $this->lastError = ['code' => 'NOT_CONFIGURED', 'message' => 'NVIDIA API key not set.'];
            return null;
        }

        $requestedModel = $options['model'] ?? 'black-forest-labs/flux.1-dev';
        $modelsToTry = [$requestedModel];
        if (str_contains($requestedModel, 'schnell') && !in_array('black-forest-labs/flux.1-dev', $modelsToTry, true)) {
            $modelsToTry[] = 'black-forest-labs/flux.1-dev';
        }

        foreach ($modelsToTry as $attemptIndex => $model) {
            $isSchnell = str_contains($model, 'schnell');
            // Schnell times out after 20s if container is cold/stuck; Dev given 55s
            $timeout = $isSchnell ? 20 : ($options['timeout'] ?? 55);

            $url = self::GENAI_BASE_URL . '/' . $model;

            $body = [
                'prompt' => $prompt,
                'mode' => 'base',
                'steps' => $options['steps'] ?? ($isSchnell ? 4 : 28),
            ];

            if (isset($options['cfg_scale']) || isset($options['guidance_scale'])) {
                $body['cfg_scale'] = (float) ($options['cfg_scale'] ?? $options['guidance_scale']);
            }

            if (isset($options['seed'])) {
                $body['seed'] = (int) $options['seed'];
            }

            $response = wp_remote_post($url, [
                'timeout' => $timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->resolvedApiKey,
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]);

            if (is_wp_error($response)) {
                $this->lastError = ['code' => 'IMAGE_GEN_WP_ERROR', 'message' => $response->get_error_message()];
                if ($attemptIndex < count($modelsToTry) - 1) {
                    continue;
                }
                return null;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $rawBody = (string) wp_remote_retrieve_body($response);

            if ($code !== 200) {
                $this->lastError = ['code' => 'IMAGE_GEN_HTTP_' . $code, 'message' => $rawBody];
                if ($attemptIndex < count($modelsToTry) - 1) {
                    continue;
                }
                return null;
            }

            $data = json_decode($rawBody, true);
            if (!is_array($data)) {
                $this->lastError = ['code' => 'IMAGE_GEN_MALFORMED', 'message' => 'Invalid JSON payload'];
                if ($attemptIndex < count($modelsToTry) - 1) {
                    continue;
                }
                return null;
            }

            $b64 = $data['artifacts'][0]['base64'] ?? $data['image'] ?? $data['b64_json'] ?? ($data['data'][0]['b64_json'] ?? null);
            if (empty($b64) && $attemptIndex < count($modelsToTry) - 1) {
                continue;
            }

            return [
                'b64_json' => $b64,
                'artifacts' => $data['artifacts'] ?? [],
                'raw' => $data,
                'model_used' => $model,
            ];
        }

        return null;
    }

    /**
     * Prepare parallel request array for multi_curl.
     */
    public function prepareRequest(string $prompt, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $body = [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        if (isset($options['reasoning_effort'])) {
            $body['reasoning_effort'] = $options['reasoning_effort'];
        }

        return [
            'url' => $this->resolvedBaseUrl . '/chat/completions',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->resolvedApiKey,
                'Accept'        => 'application/json',
            ],
            'body' => (string) wp_json_encode($body),
        ];
    }
}
