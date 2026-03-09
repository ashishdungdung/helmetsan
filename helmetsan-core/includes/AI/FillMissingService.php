<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use WP_Post;

/**
 * Phase 2: Fills missing entity fields using the AI module (context-aware).
 * Supports per-field max_length, allowed_values validation, retry with feedback, cache, and strict mode.
 */
final class FillMissingService
{
    private const RATE_LIMIT_SECONDS = 1;

    /** When set (e.g. by CLI --no-rate-limit), skip sleep between API calls. */
    private static ?int $rateLimitOverrideSeconds = null;
    private const DEFAULT_MAX_LENGTH = 5000;
    private const CACHE_PREFIX = 'helmetsan_fill_';

    public function __construct(
        private readonly AiService $aiService
    ) {
    }

    /**
     * Process one post type batch.
     * @param list<string>|null $onlyFields If set, only fill these meta keys (e.g. from --fields=key1,key2).
     * @param callable(int $processed, int $total, int $postId): void $onProgress Called after each post (optional).
     * @param callable(string $type, int $postId, string $metaKey, string|null $detail): void $onVerbose Optional. $type: 'filled'|'error'|'skipped', $detail: value or reason.
     * @param int|null $rateLimitSeconds Sleep between API calls (null = default 1s, 0 = no sleep).
     * @return array{filled: int, skipped: int, errors: int, total_posts: int, api_calls: int}
     */
    public function run(
        string $postType,
        int $limit = 0,
        int $offset = 0,
        bool $dryRun = false,
        ?array $onlyFields = null,
        bool $onlyIncomplete = false,
        bool $strictMode = false,
        bool $fillTaxonomies = true,
        ?callable $onProgress = null,
        ?callable $onVerbose = null,
        int $cacheTtl = 86400,
        ?int $rateLimitSeconds = null
    ): array {
        $fillable = FillableFieldsConfig::forPostType($postType);
        if ($fillable === []) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }
        if ($onlyFields !== null && $onlyFields !== []) {
            $fillable = array_intersect_key($fillable, array_flip($onlyFields));
        }
        if ($fillable === []) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }

        if (! $this->aiService->hasAnyConfiguredProvider()) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }

        $query = new \WP_Query([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        if ($onlyIncomplete) {
            $ids = $this->filterIncompletePosts($ids, $fillable);
        }
        $totalPosts = count($ids);
        $filled = 0;
        $skipped = 0;
        $errors = 0;
        $apiCalls = 0;
        $useCache = $cacheTtl > 0;
        $processed = 0;
        self::$rateLimitOverrideSeconds = $rateLimitSeconds;

        foreach ($ids as $postId) {
            $post = get_post($postId);
            if (! $post instanceof WP_Post) {
                continue;
            }
            $existingData = $this->gatherExistingData($postType, $postId, $post);
            foreach ($fillable as $metaKey => $config) {
                $current = get_post_meta($postId, $metaKey, true);
                if ($current !== '' && $current !== null && $current !== []) {
                    $skipped++;
                    $onVerbose && $onVerbose('skipped', $postId, $metaKey, null);
                    continue;
                }
                $label = FillableFieldsConfig::getLabel($metaKey, $postType, $config);
                $maxLen = FillableFieldsConfig::getMaxLength($config);
                $allowed = FillableFieldsConfig::getAllowedValues($config);

                $cacheKey = null;
                if ($useCache) {
                    $cacheKey = self::CACHE_PREFIX . md5($postType . $metaKey . json_encode($existingData));
                    $cached = get_transient($cacheKey);
                    if (is_string($cached) && $cached !== '') {
                        $value = $cached;
                    } else {
                        $value = null;
                    }
                } else {
                    $value = null;
                }

                if ($value === null) {
                    $value = $this->aiService->generateFillField($postType, $metaKey, $existingData, $label, $maxLen, $allowed);
                    $apiCalls++;
                    if (($value === null || trim((string) $value) === '') && ! $strictMode) {
                        $value = $this->aiService->generateFillField($postType, $metaKey, $existingData, $label, $maxLen, $allowed);
                        $apiCalls++;
                    }
                }

                $rawValue = $value !== null ? trim((string) $value) : '';
                if ($rawValue === '') {
                    $errors++;
                    $onVerbose && $onVerbose('error', $postId, $metaKey, 'empty response');
                    continue;
                }
                $value = self::sanitizeAndValidate($metaKey, $rawValue, $config);
                if ($value === '') {
                    if (! $strictMode && $allowed !== null && $allowed !== []) {
                        $retryValue = $this->aiService->generateFillFieldWithFeedback(
                            $postType,
                            $metaKey,
                            $existingData,
                            $label,
                            $allowed,
                            $rawValue
                        );
                        $apiCalls++;
                        if ($retryValue !== null && trim($retryValue) !== '') {
                            $value = self::sanitizeAndValidate($metaKey, trim($retryValue), $config);
                        }
                    }
                    if ($value === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, $metaKey, 'validation failed');
                        continue;
                    }
                }
                if ($useCache && $cacheKey !== null) {
                    set_transient($cacheKey, $value, $cacheTtl);
                }
                if (! $dryRun) {
                    update_post_meta($postId, $metaKey, $value);
                    // Sync fillable Yoast keys to Yoast meta so SEO plugins use them
                    $yoastMap = FillableFieldsConfig::yoastMetaMapping();
                    if (isset($yoastMap[$metaKey])) {
                        update_post_meta($postId, $yoastMap[$metaKey], $value);
                    }
                }
                $filled++;
                $onVerbose && $onVerbose('filled', $postId, $metaKey, $value);
                $existingData[$metaKey] = $value;
                $this->rateLimit();
            }

            // Phase B: fill missing taxonomy terms (only assign existing terms)
            if ($fillTaxonomies) {
                $taxonomyConfig = FillableFieldsConfig::taxonomyFillableConfig()[$postType] ?? [];
                foreach ($taxonomyConfig as $taxonomy => $label) {
                    $existingTerms = get_the_terms($postId, $taxonomy);
                    if (is_array($existingTerms) && $existingTerms !== []) {
                        $skipped++;
                        $onVerbose && $onVerbose('skipped', $postId, 'taxonomy:' . $taxonomy, null);
                        continue;
                    }
                    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                    if (is_wp_error($terms) || ! is_array($terms) || $terms === []) {
                        continue;
                    }
                    $allowedNames = array_map(static fn($t) => $t instanceof \WP_Term ? $t->name : '', array_filter($terms, static fn($t) => $t instanceof \WP_Term));
                    $allowedNames = array_values(array_filter($allowedNames));
                    if ($allowedNames === []) {
                        continue;
                    }
                    $value = $this->aiService->generateFillField(
                        $postType,
                        'taxonomy_' . $taxonomy,
                        $existingData,
                        $label,
                        null,
                        $allowedNames
                    );
                    $apiCalls++;
                    if ($value === null || trim((string) $value) === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, 'taxonomy:' . $taxonomy, 'empty response');
                        continue;
                    }
                    $value = trim((string) $value);
                    $term = get_term_by('name', $value, $taxonomy);
                    if (! $term instanceof \WP_Term) {
                        $term = get_term_by('slug', sanitize_title($value), $taxonomy);
                    }
                    if (! $term instanceof \WP_Term) {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, 'taxonomy:' . $taxonomy, 'term not found: ' . $value);
                        continue;
                    }
                    if (! $dryRun) {
                        wp_set_object_terms($postId, [(int) $term->term_id], $taxonomy, false);
                    }
                    $filled++;
                    $onVerbose && $onVerbose('filled', $postId, 'taxonomy:' . $taxonomy, $term->name);
                    $existingData['term_' . $taxonomy] = $term->name;
                    $this->rateLimit();
                }
            }

            $processed++;
            $onProgress && $onProgress($processed, $totalPosts, $postId);
        }

        self::$rateLimitOverrideSeconds = null;
        return ['filled' => $filled, 'skipped' => $skipped, 'errors' => $errors, 'total_posts' => $totalPosts, 'api_calls' => $apiCalls];
    }

    /**
     * @param array<string, mixed> $fillable
     * @return list<int>
     */
    private function filterIncompletePosts(array $ids, array $fillable): array
    {
        $out = [];
        foreach ($ids as $postId) {
            foreach (array_keys($fillable) as $metaKey) {
                $v = get_post_meta($postId, $metaKey, true);
                if ($v === '' || $v === null || $v === []) {
                    $out[] = $postId;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Build context for the AI from post title and non-empty meta (compact).
     */
    private function gatherExistingData(string $postType, int $postId, WP_Post $post): array
    {
        $data = ['title' => (string) $post->post_title];
        $allKeys = FillableFieldsConfig::forPostType($postType);
        foreach (array_keys($allKeys) as $key) {
            $v = get_post_meta($postId, $key, true);
            if ($v !== '' && $v !== null) {
                $data[$key] = is_string($v) ? $v : (string) json_encode($v);
            }
        }
        if ($postType === 'helmet') {
            $brandId = (int) get_post_meta($postId, 'rel_brand', true);
            if ($brandId > 0) {
                $brand = get_post($brandId);
                $data['brand'] = $brand instanceof WP_Post ? (string) $brand->post_title : '';
            }
            $types = get_the_terms($postId, 'helmet_type');
            if (is_array($types) && $types !== []) {
                $data['helmet_type'] = implode(', ', array_map(static fn($t) => $t->name, $types));
            }
        }
        if ($postType === 'brand') {
            $country = get_post_meta($postId, 'brand_origin_country', true);
            if ($country !== '') {
                $data['brand_origin_country'] = (string) $country;
            }
        }
        if ($postType === 'accessory') {
            $cat = get_post_meta($postId, 'accessory_parent_category', true)
                ?: get_post_meta($postId, 'accessory_subcategory', true)
                ?: get_post_meta($postId, 'accessory_type', true);
            if ($cat !== '') {
                $data['category'] = (string) $cat;
            }
        }
        return $data;
    }

    /**
     * Public for unit tests. Sanitize and validate AI output (allowed_values, year range, max_length).
     * @param string|array{label: string, max_length?: int, allowed_values?: list<string>} $config
     */
    public static function sanitizeAndValidate(string $metaKey, string $value, $config): string
    {
        $value = preg_replace('/^["\']|["\']$/u', '', $value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $allowed = is_array($config) ? ($config['allowed_values'] ?? null) : null;
        if (is_array($allowed) && $allowed !== []) {
            $lower = strtolower($value);
            foreach ($allowed as $opt) {
                if (strtolower((string) $opt) === $lower) {
                    return (string) $opt;
                }
            }
            if (preg_match('/\b(long[- ]?oval|intermediate[- ]?oval|round[- ]?oval)\b/i', $value, $m)) {
                $k = strtolower(str_replace(' ', '-', $m[1]));
                $canon = ['longoval' => 'long-oval', 'intermediateoval' => 'intermediate-oval', 'roundoval' => 'round-oval'];
                $key = str_replace('-', '', $k);
                if (isset($canon[$key])) {
                    return $canon[$key];
                }
            }
            return '';
        }

        if ($metaKey === 'brand_founded_year') {
            if (preg_match('/\b(19|20)\d{2}\b/', $value, $m)) {
                $year = (int) $m[0];
                $maxYear = (int) date('Y');
                if ($year >= 1900 && $year <= $maxYear) {
                    return $m[0];
                }
            }
            return '';
        }

        $maxLength = is_array($config) && isset($config['max_length']) && is_int($config['max_length'])
            ? $config['max_length']
            : self::DEFAULT_MAX_LENGTH;
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength - 3);
            $last = strrpos($value, ' ');
            if ($last !== false && $last > (int) ($maxLength * 0.5)) {
                $value = substr($value, 0, $last);
            }
        }
        return $value;
    }

    private function rateLimit(): void
    {
        $seconds = self::$rateLimitOverrideSeconds;
        if ($seconds !== null) {
            if ($seconds <= 0) {
                return;
            }
            sleep($seconds);
            return;
        }
        if (\defined('HELMETSAN_SEO_AI_SKIP_RATE_LIMIT') && constant('HELMETSAN_SEO_AI_SKIP_RATE_LIMIT')) {
            return;
        }
        sleep(self::RATE_LIMIT_SECONDS);
    }

    /**
     * List of fillable meta keys for a post type (for CLI --fields validation).
     * @return list<string>
     */
    public static function getFillableKeys(string $postType): array
    {
        return array_keys(FillableFieldsConfig::forPostType($postType));
    }
}
