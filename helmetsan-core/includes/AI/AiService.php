<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * High-level AI service: generates completions using registered providers.
 * Load-balances across free providers by seed (e.g. post_id); falls back to next on failure.
 * Use premium provider when explicitly requested (e.g. from admin).
 */
final class AiService
{
    private const RATE_LIMIT_DELAY_SECONDS = 1;

    public function __construct(
        private readonly ProviderRegistry $registry
    ) {
    }

    /**
     * Generate completion for a prompt. Uses enabled free providers with load balance by seed; optional premium override.
     */
    public function generate(string $prompt, int $seed = 0, ?string $useProviderId = null, array $options = []): ?string
    {
        if ($useProviderId !== null && $useProviderId !== '') {
            $provider = $this->registry->get($useProviderId);
            if ($provider !== null) {
                $out = $provider->generate($prompt, $options);
                if ($out !== null && $out !== '') {
                    return $out;
                }
            }
            return null;
        }

        $free = $this->registry->getEnabledProviders('free');
        if ($free === []) {
            return null;
        }
        $index = abs($seed) % count($free);
        $order = [];
        for ($i = 0; $i < count($free); $i++) {
            $order[] = $free[($index + $i) % count($free)];
        }
        foreach ($order as $provider) {
            $out = $provider->generate($prompt, $options);
            if ($out !== null && $out !== '') {
                $this->rateLimit();
                return $out;
            }
        }
        return null;
    }

    /**
     * Phase 1: Generate SEO meta description. Truncates to 160 chars.
     */
    public function generateSeoDescription(string $entityType, array $context, int $postId): ?string
    {
        $prompt = ContextBuilder::forSeoDescription($entityType, $context, $postId);
        if ($prompt === '') {
            return null;
        }
        $text = $this->generate($prompt, $postId, null, ['max_tokens' => 120]);
        if ($text === null) {
            return null;
        }
        if (strlen($text) > 160) {
            $text = substr($text, 0, 157);
            $last = strrpos($text, ' ');
            if ($last !== false && $last > 80) {
                $text = substr($text, 0, $last);
            }
        }
        return $text;
    }

    /**
     * Phase 2: Generate value for a missing field (context-aware).
     * @param string|null $fieldLabel Human-readable label for the prompt (defaults to $fieldName)
     * @param int|null $maxLength Max character length for the value
     * @param list<string>|null $allowedValues If set, output must be one of these
     */
    public function generateFillField(
        string $entityType,
        string $fieldName,
        array $existingData,
        ?string $fieldLabel = null,
        ?int $maxLength = null,
        ?array $allowedValues = null
    ): ?string {
        $label = $fieldLabel ?? $fieldName;
        $prompt = ContextBuilder::forFillField($entityType, $fieldName, $label, $existingData, $maxLength, $allowedValues);
        $maxTokens = $allowedValues !== null ? 20 : ($maxLength !== null ? min(256, (int) ceil($maxLength / 2)) : 200);
        return $this->generate($prompt, crc32($fieldName . json_encode($existingData)), null, ['max_tokens' => $maxTokens]);
    }

    /**
     * Phase 2 retry: generate with "previous reply invalid" feedback when allowed_values validation failed.
     * @param list<string> $allowedValues
     */
    public function generateFillFieldWithFeedback(
        string $entityType,
        string $fieldName,
        array $existingData,
        string $fieldLabel,
        array $allowedValues,
        string $previousInvalidValue
    ): ?string {
        $prompt = ContextBuilder::forFillFieldRetry($entityType, $fieldName, $fieldLabel, $existingData, $allowedValues, $previousInvalidValue);
        return $this->generate($prompt, crc32($fieldName . json_encode($existingData) . $previousInvalidValue), null, ['max_tokens' => 20]);
    }

    /**
     * Phase 3: Check entity integrity; returns assessment string or null.
     */
    public function checkIntegrity(string $entityType, array $entityData): ?string
    {
        $prompt = ContextBuilder::forIntegrityCheck($entityType, $entityData);
        return $this->generate($prompt, crc32(json_encode($entityData)), null, ['max_tokens' => 120]);
    }

    public function hasAnyConfiguredProvider(): bool
    {
        return $this->registry->getEnabledProviders('') !== [];
    }

    private function rateLimit(): void
    {
        $skip = \defined('HELMETSAN_SEO_AI_SKIP_RATE_LIMIT') ? constant('HELMETSAN_SEO_AI_SKIP_RATE_LIMIT') : false;
        if ($skip) {
            return;
        }
        sleep(self::RATE_LIMIT_DELAY_SECONDS);
    }
}
