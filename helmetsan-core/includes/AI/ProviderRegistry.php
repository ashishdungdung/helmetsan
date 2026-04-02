<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use Helmetsan\Core\AI\Providers\AnthropicProvider;
use Helmetsan\Core\AI\Providers\CloudflareProvider;
use Helmetsan\Core\AI\Providers\CohereProvider;
use Helmetsan\Core\AI\Providers\FireworksProvider;
use Helmetsan\Core\AI\Providers\GeminiProvider;
use Helmetsan\Core\AI\Providers\GroqProvider;
use Helmetsan\Core\AI\Providers\HuggingFaceProvider;
use Helmetsan\Core\AI\Providers\MistralProvider;
use Helmetsan\Core\AI\Providers\OpenAIProvider;
use Helmetsan\Core\AI\Providers\LMStudioProvider;
use Helmetsan\Core\AI\Providers\OpenRouterProvider;
use Helmetsan\Core\AI\Providers\PerplexityProvider;
use Helmetsan\Core\AI\Providers\TogetherProvider;
use Helmetsan\Core\Support\Config;

/**
 * Builds and returns AI providers from plugin settings (helmetsan_ai option).
 */
final class ProviderRegistry
{
    /** @var array<string, ProviderInterface> */
    private array $instances = [];

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @return list<ProviderInterface>
     */
    public function getEnabledProviders(string $tier = 'free'): array
    {
        $out = [];
        foreach ($this->getAll() as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }
            if ($tier !== '' && $provider->getTier() !== $tier) {
                continue;
            }
            $out[] = $provider;
        }
        return $out;
    }

    /**
     * @return list<ProviderInterface>
     */
    public function getAll(): array
    {
        $settings = get_option(Config::OPTION_AI, null);
        $defaults = $this->config->aiDefaults();
        $providersConfig = is_array($settings['providers'] ?? null) ? $settings['providers'] : $defaults['providers'];

        $order = ['groq', 'gemini', 'mistral', 'openrouter', 'huggingface', 'together', 'fireworks', 'cohere', 'cloudflare', 'lm_studio', 'openai', 'anthropic', 'perplexity'];
        $list = [];
        foreach ($order as $id) {
            $p = $this->get($id, $providersConfig);
            if ($p !== null) {
                $list[] = $p;
            }
        }
        return $list;
    }

    public function get(string $id, ?array $providersConfig = null): ?ProviderInterface
    {
        $settings = $providersConfig === null ? (get_option(Config::OPTION_AI, $this->config->aiDefaults())['providers'] ?? []) : $providersConfig;
        $defaults = $this->config->aiDefaults()['providers'];
        $cfg = array_merge($defaults[$id] ?? ['enabled' => false, 'api_key' => '', 'model' => '', 'tier' => 'free'], $settings[$id] ?? []);
        if (empty($cfg['enabled'])) {
            return null;
        }
        if ($id === 'lm_studio') {
            $baseUrl = trim((string) ($cfg['base_url'] ?? ''));
            // Allow env-var / constant override (e.g. Cloudflare Tunnel URL on production).
            if (defined('HELMETSAN_LMSTUDIO_BASE_URL') && \HELMETSAN_LMSTUDIO_BASE_URL !== '') {
                $baseUrl = (string) \HELMETSAN_LMSTUDIO_BASE_URL;
            }
            if ($baseUrl === '') {
                return null;
            }
            $model = trim((string) ($cfg['model'] ?? ''));
            if ($model === '') {
                $model = $defaults[$id]['model'] ?? 'local';
            }
            $key = trim((string) ($cfg['api_key'] ?? ''));
            return $this->create($id, $key, $model, array_merge($cfg, ['base_url' => $baseUrl]));
        }
        if ($id === 'cloudflare') {
            $accountId = trim((string) ($cfg['base_url'] ?? ''));
            if ($accountId === '') {
                return null;
            }
            $model = trim((string) ($cfg['model'] ?? ''));
            if ($model === '') {
                $model = $defaults[$id]['model'] ?? '@cf/meta/llama-3-8b-instruct';
            }
            $key = trim((string) ($cfg['api_key'] ?? ''));
            return $this->create($id, $key, $model, $cfg);
        }
        if (empty(trim((string) ($cfg['api_key'] ?? '')))) {
            return null;
        }
        $key = trim((string) $cfg['api_key']);
        $model = trim((string) ($cfg['model'] ?? ''));
        if ($model === '') {
            $model = $defaults[$id]['model'] ?? '';
        }
        return $this->create($id, $key, $model, null);
    }

    /**
     * @param array<string,mixed>|null $extraConfig For lm_studio: base_url, etc.
     */
    private function create(string $id, string $apiKey, string $model, ?array $extraConfig = null): ProviderInterface
    {
        if (isset($this->instances[$id . ':' . $model])) {
            return $this->instances[$id . ':' . $model];
        }
        $p = match ($id) {
            'groq' => new GroqProvider($apiKey, $model ?: 'llama-3.1-8b-instant'),
            'gemini' => new GeminiProvider($apiKey, $model ?: 'gemini-1.5-flash'),
            'mistral' => new MistralProvider($apiKey, $model ?: 'mistral-small-latest'),
            'openrouter' => new OpenRouterProvider($apiKey, $model ?: 'google/gemini-flash-1.5'),
            'huggingface' => new HuggingFaceProvider($apiKey, $model ?: 'mistralai/Mistral-7B-Instruct-v0.2'),
            'together' => new TogetherProvider($apiKey, $model ?: 'meta-llama/Llama-3.2-3B-Instruct-Turbo'),
            'fireworks' => new FireworksProvider($apiKey, $model ?: 'accounts/fireworks/models/llama-v3p1-8b-instruct'),
            'cohere' => new CohereProvider($apiKey, $model ?: 'command-r-plus'),
            'cloudflare' => new CloudflareProvider($apiKey, $model ?: '@cf/meta/llama-3-8b-instruct', (string) ($extraConfig['base_url'] ?? '')),
            'lm_studio' => new LMStudioProvider(
                (string) ($extraConfig['base_url'] ?? ''),
                $model ?: 'local',
                $apiKey,
                (int) ($extraConfig['concurrency'] ?? 1)
            ),
            'openai' => new OpenAIProvider($apiKey, $model ?: 'gpt-4o-mini'),
            'anthropic' => new AnthropicProvider($apiKey, $model ?: 'claude-sonnet-4-20250514'),
            'perplexity' => new PerplexityProvider($apiKey, $model ?: 'sonar'),
            default => throw new \InvalidArgumentException('Unknown provider: ' . $id),
        };
        $this->instances[$id . ':' . $model] = $p;
        return $p;
    }

    public function getDefaultFree(): string
    {
        $settings = get_option(Config::OPTION_AI, $this->config->aiDefaults());
        return (string) ($settings['default_free'] ?? 'groq');
    }

    public function getDefaultPremium(): string
    {
        $settings = get_option(Config::OPTION_AI, $this->config->aiDefaults());
        return (string) ($settings['default_premium'] ?? 'openai');
    }

    /** Provider IDs that are free/low-cost. */
    public static function freeProviderIds(): array
    {
        return ['groq', 'gemini', 'mistral', 'openrouter', 'huggingface', 'together', 'fireworks', 'cohere', 'cloudflare', 'lm_studio'];
    }

    /** Provider IDs that are premium (dedicated controls). */
    public static function premiumProviderIds(): array
    {
        return ['openai', 'anthropic', 'perplexity'];
    }
}
