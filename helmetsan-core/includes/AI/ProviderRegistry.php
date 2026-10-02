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
use Helmetsan\Core\AI\Providers\ExperientialProvider;
use Helmetsan\Core\AI\Providers\LMStudioProvider;
use Helmetsan\Core\AI\Providers\OpenRouterProvider;
use Helmetsan\Core\AI\Providers\PerplexityProvider;
use Helmetsan\Core\AI\Providers\TogetherProvider;
use Helmetsan\Core\AI\Providers\NvidiaNimProvider;
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

        $order = ['groq', 'gemini', 'mistral', 'openrouter', 'huggingface', 'together', 'fireworks', 'cohere', 'cloudflare', 'lm_studio', 'nvidia_nim', 'openai', 'anthropic', 'perplexity', 'experiential'];
        $list = [];
        foreach ($order as $id) {
            try {
                $p = $this->get($id, $providersConfig);
                if ($p !== null) {
                    $list[] = $p;
                }
            } catch (\Throwable) {
                // Provider missing environment pre-requisites or failed configuration
                continue;
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
            $cfSettings = get_option(\Helmetsan\Core\Support\Config::OPTION_CLOUDFLARE, []);
            $cfEnabled = !empty($cfSettings['enable_workers_ai']);
            $cfToken = defined('HELMETSAN_CLOUDFLARE_API_TOKEN') ? HELMETSAN_CLOUDFLARE_API_TOKEN : ($cfSettings['cf_api_token'] ?? '');
            $cfAccountId = defined('HELMETSAN_CLOUDFLARE_ACCOUNT_ID') ? HELMETSAN_CLOUDFLARE_ACCOUNT_ID : ($cfSettings['cf_account_id'] ?? '');
            $cfModel = !empty($cfSettings['workers_ai_model']) ? $cfSettings['workers_ai_model'] : '@cf/meta/llama-3-8b-instruct';

            if ($cfEnabled && !empty($cfToken) && !empty($cfAccountId)) {
                return $this->create($id, $cfToken, $cfModel, ['base_url' => $cfAccountId]);
            }

            // Fallback to original config
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
        if ($id === 'openai') {
            $model = trim((string) ($cfg['model'] ?? ''));
            if ($model === '') {
                $model = $defaults[$id]['model'] ?? 'gpt-4o-mini';
            }
            if ($model === 'gpt-5.6-luna' || str_starts_with($model, 'gpt-5.6-')) {
                return $this->getForModel($model, 'experiential');
            }
        }
        if ($id === 'experiential') {
            $baseUrl = trim((string) ($cfg['base_url'] ?? ''));
            if ($baseUrl === '') {
                $baseUrl = ExperientialProvider::DEFAULT_BASE_URL;
            }
            $model = trim((string) ($cfg['model'] ?? ''));
            if ($model === '') {
                $model = $defaults[$id]['model'] ?? ExperientialProvider::DEFAULT_MODEL;
            }
            $explabsKey = getenv('EXPLABS_API_KEY') ?: trim((string) ($cfg['api_key'] ?? ''));
            if (empty($explabsKey)) {
                throw new \RuntimeException('EXPLABS_API_KEY environment variable is not set. Please create one under Settings -> API Keys and export it.');
            }
            return $this->create('experiential', $explabsKey, $model, ['base_url' => $baseUrl]);
        }
        if ($id === 'nvidia_nim') {
            $baseUrl = trim((string) ($cfg['base_url'] ?? ''));
            if ($baseUrl === '') {
                $baseUrl = NvidiaNimProvider::CHAT_BASE_URL;
            }
            $model = trim((string) ($cfg['model'] ?? ''));
            if ($model === '') {
                $model = $defaults[$id]['model'] ?? NvidiaNimProvider::DEFAULT_MODEL;
            }
            $nimKey = getenv('NVIDIA_API_KEY') ?: trim((string) ($cfg['api_key'] ?? ''));
            return $this->create('nvidia_nim', $nimKey, $model, ['base_url' => $baseUrl]);
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
     * Get or build a provider instance specifically for a given model ID.
     */
    public function getForModel(string $model, ?string $provider = null): ?ProviderInterface
    {
        if ($provider === 'experiential' || $model === 'gpt-5.6-luna' || str_starts_with($model, 'gpt-5.6-') || in_array($model, ['qwen3.8-27b', 'deepseek-v4-flash', 'claude-sonnet-4.5', 'gpt-5-mini'], true)) {
            $explabsKey = getenv('EXPLABS_API_KEY');
            if ($explabsKey === false || trim((string) $explabsKey) === '') {
                throw new \RuntimeException('EXPLABS_API_KEY environment variable is not set. Please create one under Settings -> API Keys and export it.');
            }
            return $this->create('experiential', trim((string) $explabsKey), $model, [
                'base_url' => ExperientialProvider::DEFAULT_BASE_URL,
            ]);
        }
        if (str_starts_with($model, 'moonshotai/') || str_starts_with($model, 'deepseek-ai/') || str_starts_with($model, 'black-forest-labs/') || str_starts_with($model, 'nvidia/')) {
            $nimKey = getenv('NVIDIA_API_KEY');
            return $this->create('nvidia_nim', $nimKey ?: '', $model, [
                'base_url' => NvidiaNimProvider::CHAT_BASE_URL,
            ]);
        }
        return null;
    }

    /**
     * Build an Experiential gateway provider for any arbitrary model slug.
     */
    public function getExperiential(string $model = ExperientialProvider::DEFAULT_MODEL, ?string $baseUrl = null): ProviderInterface
    {
        $explabsKey = getenv('EXPLABS_API_KEY');
        if ($explabsKey === false || trim((string) $explabsKey) === '') {
            throw new \RuntimeException('EXPLABS_API_KEY environment variable is not set. Please create one under Settings -> API Keys and export it.');
        }
        return $this->create('experiential', trim((string) $explabsKey), $model, [
            'base_url' => $baseUrl ?: ExperientialProvider::DEFAULT_BASE_URL,
        ]);
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
            'openai' => new OpenAIProvider(
                $model === 'gpt-5.6-luna' ? (getenv('EXPLABS_API_KEY') ?: $apiKey) : $apiKey,
                $model ?: 'gpt-4o-mini',
                $model === 'gpt-5.6-luna' ? 'https://api.experientiallabs.ai/v1' : (string) ($extraConfig['base_url'] ?? '')
            ),
            'experiential' => new ExperientialProvider(
                $apiKey,
                $model ?: ExperientialProvider::DEFAULT_MODEL,
                (string) ($extraConfig['base_url'] ?? ExperientialProvider::DEFAULT_BASE_URL)
            ),
            'nvidia_nim' => new NvidiaNimProvider(
                $apiKey,
                $model ?: NvidiaNimProvider::DEFAULT_MODEL,
                (string) ($extraConfig['base_url'] ?? NvidiaNimProvider::CHAT_BASE_URL)
            ),
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
        return ['groq', 'gemini', 'mistral', 'openrouter', 'huggingface', 'together', 'fireworks', 'cohere', 'cloudflare', 'lm_studio', 'nvidia_nim'];
    }

    /** Provider IDs that are premium (dedicated controls). */
    public static function premiumProviderIds(): array
    {
        return ['openai', 'anthropic', 'perplexity', 'experiential'];
    }
}
