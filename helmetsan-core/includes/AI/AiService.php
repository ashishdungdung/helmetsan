<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use Helmetsan\Core\Support\RateLimiter;

/**
 * High-level AI service: generates completions using registered providers.
 * Load-balances across free providers by seed (e.g. post_id); falls back to next on failure.
 * Use premium provider when explicitly requested (e.g. from admin).
 */
final class AiService implements AiServiceInterface
{
    private const RATE_LIMIT_DELAY_SECONDS = 1;

    private readonly RateLimiter $rateLimiter;

    public function __construct(
        private readonly ProviderRegistry $registry,
        ?RateLimiter $rateLimiter = null
    ) {
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
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

    /**
     * Resolve a helmet's product image source: EAN-13 and/or direct image URL.
     * Used by helmet image enrichment to match catalog helmets with external images.
     *
     * @return array{ean: string, image_url: string}|null Parsed JSON or null on failure
     */
    public function resolveHelmetImageSource(int $helmetId): ?array
    {
        $post = get_post($helmetId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'helmet') {
            return null;
        }
        $title = $post->post_title;
        $brand = '';
        $brandId = (int) get_post_meta($helmetId, 'rel_brand', true);
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            $brand = $brandPost instanceof \WP_Post ? $brandPost->post_title : '';
        }
        if ($brand === '') {
            $terms = get_the_terms($helmetId, 'helmet_brand');
            if (is_array($terms) && $terms !== []) {
                $brand = $terms[0]->name ?? '';
            }
        }
        $prompt = ContextBuilder::forResolveHelmetImageSource($title, $brand);
        $raw = $this->generate($prompt, $helmetId, null, ['max_tokens' => 256]);
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        $raw = preg_replace('/^```\w*\s*|\s*```$/m', '', $raw);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }
        return [
            'ean'       => isset($decoded['ean']) && is_string($decoded['ean']) ? preg_replace('/\D/', '', $decoded['ean']) : '',
            'image_url' => isset($decoded['image_url']) && is_string($decoded['image_url']) ? trim($decoded['image_url']) : '',
        ];
    }

    public function resolveManufacturerImageUrls(int $helmetId): array
    {
        if (! $this->rateLimiter->check('ai_search')) {
            return [];
        }

        $post = get_post($helmetId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'helmet') {
            return [];
        }
        $title = $post->post_title;
        $brand = '';
        $brandId = (int) get_post_meta($helmetId, 'rel_brand', true);
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            $brand = $brandPost instanceof \WP_Post ? $brandPost->post_title : '';
        }
        if ($brand === '') {
            $terms = get_the_terms($helmetId, 'helmet_brand');
            if (is_array($terms) && $terms !== []) {
                $brand = $terms[0]->name ?? '';
            }
        }
        $prompt = ContextBuilder::forResolveManufacturerImages($title, $brand);
        $raw = $this->generate($prompt, $helmetId, null, ['max_tokens' => 512]);
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $raw = trim($raw);
        $raw = preg_replace('/^```\w*\s*|\s*```$/m', '', $raw);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || empty($decoded['images']) || ! is_array($decoded['images'])) {
            return [];
        }
        return array_values(array_filter(array_map('trim', $decoded['images']), function($u) {
            return filter_var($u, FILTER_VALIDATE_URL) && (str_starts_with(strtolower($u), 'http://') || str_starts_with(strtolower($u), 'https://'));
        }));
    }

    /**
     * Use AI to find the RevZilla product page URL for a helmet (title + brand).
     * Used when affiliate_links_json has no RevZilla link; result can be used for image fetch or stored.
     *
     * @return string|null RevZilla product URL or null if not found / AI failed
     */
    public function resolveRevZillaUrlForHelmet(int $helmetId): ?string
    {
        $post = get_post($helmetId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'helmet') {
            return null;
        }
        $title = $post->post_title;
        $brand = '';
        $brandId = (int) get_post_meta($helmetId, 'rel_brand', true);
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            $brand = $brandPost instanceof \WP_Post ? $brandPost->post_title : '';
        }
        if ($brand === '') {
            $terms = get_the_terms($helmetId, 'helmet_brand');
            if (is_array($terms) && $terms !== []) {
                $brand = $terms[0]->name ?? '';
            }
        }
        $prompt = ContextBuilder::forResolveRevZillaUrl($title, $brand);
        $raw = $this->generate($prompt, $helmetId, null, ['max_tokens' => 256]);
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        $raw = preg_replace('/^["\']|["\']$/u', '', $raw);
        if (strtolower($raw) === 'none' || $raw === '') {
            return null;
        }
        $url = esc_url_raw($raw);
        if ($url === '' || ! str_contains(strtolower($url), 'revzilla.com')) {
            return null;
        }
        return $url;
    }

    public function hasAnyConfiguredProvider(): bool
    {
        return $this->registry->getEnabledProviders('') !== [];
    }

    /**
     * IDs of all enabled providers (any tier). For health/api-check display.
     * @return list<string>
     */
    public function getConfiguredProviderIds(): array
    {
        $providers = $this->registry->getEnabledProviders('');
        $ids = [];
        foreach ($providers as $p) {
            $ids[] = $p->getId();
        }
        return $ids;
    }

    /**
     * Test a single provider with a minimal prompt. For admin "Test connection".
     * @return array{ok: bool, message?: string}
     */
    public function testProvider(string $providerId): array
    {
        $provider = $this->registry->get($providerId);
        if ($provider === null) {
            return ['ok' => false, 'message' => __('Provider not enabled or not found.', 'helmetsan-core')];
        }
        if (! $provider->isConfigured()) {
            return ['ok' => false, 'message' => __('API key or model missing.', 'helmetsan-core')];
        }
        $out = $this->generate('Reply with exactly: OK', 0, $providerId, ['max_tokens' => 5]);
        if ($out !== null && trim($out) !== '') {
            return ['ok' => true];
        }
        return ['ok' => false, 'message' => __('No response. Check API key and model name.', 'helmetsan-core')];
    }

    /**
     * Generate and return which provider responded. For --live API check.
     * @return array{text: string, provider_id: string}|null
     */
    public function generateWithProviderId(string $prompt, int $seed = 0, array $options = []): ?array
    {
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
                return ['text' => $out, 'provider_id' => $provider->getId()];
            }
        }
        return null;
    }

    /**
     * Generate completions for multiple prompts in parallel, distributing them across providers (field-level parallelism).
     */
    public function generateMultiplexed(array $prompts, ?array $providerIds = null, array $options = []): array
    {
        if ($prompts === []) {
            return [];
        }

        $providers = [];
        if ($providerIds !== null && $providerIds !== []) {
            foreach ($providerIds as $id) {
                $p = $this->registry->get($id);
                if ($p !== null && $p->isConfigured()) {
                    $providers[] = $p;
                }
            }
        } else {
            $providers = $this->registry->getEnabledProviders('free');
        }

        if ($providers === []) {
            return array_fill_keys(array_keys($prompts), null);
        }

        $requests = [];
        $keyToProvider = [];
        $providerCount = count($providers);
        $i = 0;

        foreach ($prompts as $key => $prompt) {
            $provider = $providers[$i % $providerCount];
            $req = $provider->prepareRequest($prompt, $options);
            if ($req !== null) {
                $requestId = $key . '||' . $provider->getId();
                $requests[$requestId] = $req;
                $keyToProvider[$requestId] = [
                    'key' => $key,
                    'provider' => $provider
                ];
            }
            $i++;
        }

        if ($requests === []) {
            return array_fill_keys(array_keys($prompts), null);
        }

        $client = new ParallelAiClient();
        $responses = $client->execute($requests);

        $results = array_fill_keys(array_keys($prompts), null);
        foreach ($responses as $requestId => $raw) {
            $mapping = $keyToProvider[$requestId] ?? null;
            if ($mapping === null || $raw === null || $raw === '') {
                continue;
            }

            $key = $mapping['key'];
            $data = json_decode($raw, true);
            if (! is_array($data)) {
                continue;
            }

            $val = null;
            if (isset($data['choices'][0]['message']['content'])) {
                $val = (string) $data['choices'][0]['message']['content'];
            } elseif (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $val = (string) $data['candidates'][0]['content']['parts'][0]['text'];
            }

            if ($val !== null && trim($val) !== '') {
                $results[$key] = $this->normalizeText($val);
            }
        }

        $this->rateLimit();
        return $results;
    }

    private function normalizeText(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^["\']|["\']$/u', '', $s);
        return trim($s);
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
