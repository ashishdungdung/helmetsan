<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use Helmetsan\Core\AI\Providers\GeminiProvider;
use Helmetsan\Core\AI\Providers\GroqProvider;
use Helmetsan\Core\AI\Providers\HuggingFaceProvider;
use Helmetsan\Core\AI\Providers\MistralProvider;
use Helmetsan\Core\AI\Providers\OpenAIProvider;
use Helmetsan\Core\AI\Providers\OpenRouterProvider;
use Helmetsan\Core\AI\Providers\PerplexityProvider;
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

        $order = ['groq', 'gemini', 'mistral', 'openrouter', 'huggingface', 'openai', 'perplexity'];
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
        if (empty($cfg['enabled']) || empty(trim((string) ($cfg['api_key'] ?? '')))) {
            return null;
        }
        $key = trim((string) $cfg['api_key']);
        $model = trim((string) ($cfg['model'] ?? ''));
        if ($model === '') {
            $model = $defaults[$id]['model'] ?? '';
        }
        return $this->create($id, $key, $model);
    }

    private function create(string $id, string $apiKey, string $model): ProviderInterface
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
            'openai' => new OpenAIProvider($apiKey, $model ?: 'gpt-4o-mini'),
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
        return ['groq', 'gemini', 'mistral', 'openrouter', 'huggingface'];
    }

    /** Provider IDs that are premium (dedicated controls). */
    public static function premiumProviderIds(): array
    {
        return ['openai', 'perplexity'];
    }
}
