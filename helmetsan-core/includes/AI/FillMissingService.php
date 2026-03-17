<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use WP_Post;
use Helmetsan\Core\Support\TaskTracker;

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

    private ?TaskTracker $tracker = null;
    private ?string $taskId = null;

    public function __construct(
        private readonly AiService $aiService,
        ?TaskTracker $tracker = null,
        private ?JsonValidator $jsonValidator = null
    ) {
        $this->tracker = $tracker;
        $this->jsonValidator = $jsonValidator ?? new JsonValidator();
    }

    public function setTracker(TaskTracker $tracker): void
    {
        $this->tracker = $tracker;
    }

    public function setTaskId(string $id): void
    {
        $this->taskId = $id;
    }

    /**
     * Process one post type batch.
     * @param list<string>|null $onlyFields If set, only fill these meta keys (e.g. from --fields=key1,key2).
     * @param callable(int $processed, int $total, int $postId): void $onProgress Called after each post (optional).
     * @param callable(string $type, int $postId, string $metaKey, string|null $detail): void $onVerbose Optional. $type: 'filled'|'error'|'skipped', $detail: value or reason.
     * @param int|null $rateLimitSeconds Sleep between API calls (null = default 1s, 0 = no sleep).
     * @param bool $refillAccessoryIfNoCategory When true and postType is accessory, also process accessories that have no accessory_category term and re-fill type/parent_category even if set (so backfill can map them).
     * @param list<string>|null $onlyTaxonomies If set, only fill these taxonomy slugs (e.g. ['certification']). When non-empty, meta fill can be skipped (onlyFields=[]).
     * @param bool $refillHelmetSpecs When true and postType is helmet, (re)fill spec_weight_g, spec_shell_material, price_retail_usd even if already set (overwrite with AI).
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
        ?int $rateLimitSeconds = null,
        bool $refillAccessoryIfNoCategory = false,
        ?array $onlyTaxonomies = null,
        bool $refillHelmetSpecs = false,
        bool $multiplex = false,
        ?int $postId = null
    ): array {
        $fillable = FillableFieldsConfig::forPostType($postType);
        if ($onlyFields !== null && $onlyFields !== []) {
            $fillable = array_intersect_key($fillable, array_flip($onlyFields));
        }
        $taxonomyOnly = $onlyTaxonomies !== null && $onlyTaxonomies !== [];
        if ($fillable === [] && ! $taxonomyOnly) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }
        if ($fillable === [] && $taxonomyOnly && ! $fillTaxonomies) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }

        if (! $this->aiService->hasAnyConfiguredProvider()) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }

        // When refill-unmapped for accessories, fetch all so unmapped ones (no category term) are in the set
        $effectiveLimit = ($refillAccessoryIfNoCategory && $postType === 'accessory')
            ? -1
            : ($limit > 0 ? $limit : -1);
        $queryArgs = [
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $effectiveLimit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        ];
        if ($postId !== null && $postId > 0) {
            $queryArgs['post__in'] = [$postId];
        }
        $query = new \WP_Query($queryArgs);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        if ($onlyIncomplete && ! $taxonomyOnly) {
            $ids = $this->filterIncompletePosts($ids, $fillable);
        }
        if ($refillAccessoryIfNoCategory && $postType === 'accessory') {
            $allIds = is_array($query->posts) ? array_map('intval', $query->posts) : [];
            $noCatIds = array_filter($allIds, [$this, 'accessoryHasNoCategoryTerm']);
            $ids = array_values(array_unique(array_merge($ids, $noCatIds)));
        }
        $totalPosts = count($ids);
        $filled = 0;
        $skipped = 0;
        $errors = 0;
        $apiCalls = 0;
        $useCache = $cacheTtl > 0;
        $processed = 0;
        self::$rateLimitOverrideSeconds = $rateLimitSeconds;

        $id = $this->taskId ?? 'fm-' . getmypid();
        if ($this->tracker !== null) {
            $this->tracker->start($id, "Fill Missing ($postType)", 'ai-enrichment');
        }

        foreach ($ids as $postId) {
            $post = get_post($postId);
            if (! $post instanceof WP_Post) {
                continue;
            }
            $existingData = $this->gatherExistingData($postType, $postId, $post);
            $helmetSpecKeys = ['spec_weight_g', 'spec_shell_material', 'price_retail_usd'];
            $fieldsToFill = [];
            $metaToProcess = [];

            // 1. Identify missing metadata fields
            foreach ($fillable as $metaKey => $config) {
                $current = get_post_meta($postId, $metaKey, true);
                $forceRefill = ($refillAccessoryIfNoCategory && $postType === 'accessory'
                    && ($metaKey === 'accessory_parent_category' || $metaKey === 'accessory_type')
                    && $this->accessoryHasNoCategoryTerm($postId))
                    || ($refillHelmetSpecs && $postType === 'helmet' && in_array($metaKey, $helmetSpecKeys, true));
                if ($current !== '' && $current !== null && $current !== [] && ! $forceRefill) {
                    $skipped++;
                    $onVerbose && $onVerbose('skipped', $postId, $metaKey, null);
                    continue;
                }
                $metaToProcess[$metaKey] = $config;
            }

            // 2. Identify missing taxonomies
            $taxToProcess = [];
            if ($fillTaxonomies) {
                $taxonomyConfig = FillableFieldsConfig::taxonomyFillableConfig()[$postType] ?? [];
                if ($onlyTaxonomies !== null && $onlyTaxonomies !== []) {
                    $taxonomyConfig = array_intersect_key($taxonomyConfig, array_flip($onlyTaxonomies));
                }
                foreach ($taxonomyConfig as $taxonomy => $label) {
                    $existingTerms = get_the_terms($postId, $taxonomy);
                    if (is_array($existingTerms) && $existingTerms !== []) {
                        $skipped++;
                        $onVerbose && $onVerbose('skipped', $postId, 'taxonomy:' . $taxonomy, null);
                        continue;
                    }
                    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                    if (! is_wp_error($terms) && is_array($terms) && $terms !== []) {
                        $allowedNames = array_map(static fn($t) => $t instanceof \WP_Term ? $t->name : '', array_filter($terms, static fn($t) => $t instanceof \WP_Term));
                        $allowedNames = array_values(array_filter($allowedNames));
                        if ($allowedNames !== []) {
                            $taxToProcess[$taxonomy] = [
                                'label' => $label,
                                'allowed' => $allowedNames
                            ];
                        }
                    }
                }
            }

            // 3. Prepare prompts for batch processing if multiplexing is enabled
            if ($multiplex && ($metaToProcess !== [] || $taxToProcess !== [])) {
                $batchPrompts = [];
                foreach ($metaToProcess as $metaKey => $config) {
                    $label = FillableFieldsConfig::getLabel($metaKey, $postType, $config);
                    $maxLen = FillableFieldsConfig::getMaxLength($config);
                    $allowed = FillableFieldsConfig::getAllowedValues($config);
                    $batchPrompts['meta_' . $metaKey] = ContextBuilder::forFillField($postType, $metaKey, $label, $existingData, $maxLen, $allowed);
                }
                foreach ($taxToProcess as $taxonomy => $info) {
                    $batchPrompts['tax_' . $taxonomy] = ContextBuilder::forFillField($postType, 'taxonomy_' . $taxonomy, $info['label'], $existingData, null, $info['allowed']);
                }

                $batchResults = $this->aiService->generateMultiplexed($batchPrompts);
                $apiCalls++; // Counted as one batch call

                // Apply metadata results
                foreach ($metaToProcess as $metaKey => $config) {
                    $rawValue = $batchResults['meta_' . $metaKey] ?? '';
                    if (trim((string) $rawValue) === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, $metaKey, 'empty multiplexed response');
                        continue;
                    }
                    $value = $this->sanitizeAndValidate($metaKey, $rawValue, $config);
                    if ($value === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, $metaKey, 'multiplexed validation failed');
                        continue;
                    }
                    if (! $dryRun) {
                        update_post_meta($postId, $metaKey, $value);
                        $yoastMap = FillableFieldsConfig::yoastMetaMapping();
                        if (isset($yoastMap[$metaKey])) {
                            update_post_meta($postId, $yoastMap[$metaKey], $value);
                        }
                        // Propagate to children if this is a parent helmet
                        if ($postType === 'helmet' && ! str_starts_with($metaKey, 'yoast_')) {
                            $this->propagateToChildren($postId, $metaKey, $value);
                        }
                    }
                    $filled++;
                    $onVerbose && $onVerbose('filled', $postId, $metaKey, $value);
                    $existingData[$metaKey] = $value;
                }

                // Apply taxonomy results
                foreach ($taxToProcess as $taxonomy => $info) {
                    $rawValue = $batchResults['tax_' . $taxonomy] ?? '';
                    if (trim((string) $rawValue) === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, 'taxonomy:' . $taxonomy, 'empty multiplexed response');
                        continue;
                    }
                    if (! $dryRun) {
                        wp_set_object_terms($postId, $rawValue, $taxonomy, false);
                    }
                    $filled++;
                    $onVerbose && $onVerbose('filled', $postId, 'taxonomy:' . $taxonomy, $rawValue);
                }
            } else {
                // FALLBACK: Sequential processing if multiplexing disabled
                foreach ($metaToProcess as $metaKey => $config) {
                    $label = FillableFieldsConfig::getLabel($metaKey, $postType, $config);
                    $maxLen = FillableFieldsConfig::getMaxLength($config);
                    $allowed = FillableFieldsConfig::getAllowedValues($config);
                    $value = $this->aiService->generateFillField($postType, $metaKey, $existingData, $label, $maxLen, $allowed);
                    $apiCalls++;
                    if (($value === null || trim((string) $value) === '') && ! $strictMode) {
                        $value = $this->aiService->generateFillField($postType, $metaKey, $existingData, $label, $maxLen, $allowed);
                        $apiCalls++;
                    }
                    $rawValue = $value !== null ? trim((string) $value) : '';
                    if ($rawValue === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, $metaKey, 'empty response');
                        continue;
                    }
                    $value = $this->sanitizeAndValidate($metaKey, $rawValue, $config);
                    if ($value === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, $metaKey, 'validation failed');
                        continue;
                    }
                    if (! $dryRun) {
                        update_post_meta($postId, $metaKey, $value);
                        $yoastMap = FillableFieldsConfig::yoastMetaMapping();
                        if (isset($yoastMap[$metaKey])) {
                            update_post_meta($postId, $yoastMap[$metaKey], $value);
                        }
                        // Propagate to children if this is a parent helmet
                        if ($postType === 'helmet' && ! str_starts_with($metaKey, 'yoast_')) {
                            $this->propagateToChildren($postId, $metaKey, $value);
                        }
                    }
                    $filled++;
                    $onVerbose && $onVerbose('filled', $postId, $metaKey, $value);
                    $existingData[$metaKey] = $value;
                    $this->rateLimit();
                }

                foreach ($taxToProcess as $taxonomy => $info) {
                    $value = $this->aiService->generateFillField($postType, 'taxonomy_' . $taxonomy, $existingData, $info['label'], null, $info['allowed']);
                    $apiCalls++;
                    if ($value === null || trim((string) $value) === '') {
                        $errors++;
                        $onVerbose && $onVerbose('error', $postId, 'taxonomy:' . $taxonomy, 'empty response');
                        continue;
                    }
                    if (! $dryRun) {
                        wp_set_object_terms($postId, $value, $taxonomy, false);
                    }
                    $filled++;
                    $onVerbose && $onVerbose('filled', $postId, 'taxonomy:' . $taxonomy, $value);
                    $this->rateLimit();
                }
            }

            $processed++;
            if ($this->tracker !== null) {
                $this->tracker->heartbeat($this->taskId ?? 'fm-' . getmypid(), $processed);
            }
            $onProgress && $onProgress($processed, $totalPosts, $postId);
        }

        self::$rateLimitOverrideSeconds = null;
        return ['filled' => $filled, 'skipped' => $skipped, 'errors' => $errors, 'total_posts' => $totalPosts, 'api_calls' => $apiCalls];
    }

    /**
     * Propagate a meta value to all child variants of a parent helmet.
     */
    private function propagateToChildren(int $parentId, string $metaKey, mixed $value): void
    {
        $children = get_children([
            'post_parent' => $parentId,
            'post_type'   => 'helmet',
            'fields'      => 'ids',
        ]);
        if (is_array($children) && $children !== []) {
            foreach ($children as $childId) {
                update_post_meta((int) $childId, $metaKey, $value);
            }
        }
    }

    /**
     * True if the post is an accessory with no accessory_category term assigned.
     */
    public function accessoryHasNoCategoryTerm(int $postId): bool
    {
        if (get_post_type($postId) !== 'accessory') {
            return false;
        }
        $terms = get_the_terms($postId, 'accessory_category');
        return is_wp_error($terms) || ! is_array($terms) || $terms === [];
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
            foreach (['helmet_type', 'certification', 'use_case', 'region', 'feature_tag', 'price_range'] as $tax) {
                $terms = get_the_terms($postId, $tax);
                if (is_array($terms) && $terms !== []) {
                    $names = array_map(static fn($t) => $t instanceof \WP_Term ? $t->name : '', $terms);
                    $data[$tax] = implode(', ', array_filter($names));
                }
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
    public function sanitizeAndValidate(string $metaKey, string $value, $config): mixed
    {
        $value = preg_replace('/^["\']|["\']$/u', '', $value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Structural validation for JSON fields
        if (str_ends_with($metaKey, '_json')) {
            $expected = $this->jsonValidator->getExpectedTypeFromField($metaKey);
            $validated = $this->jsonValidator->validate($value, $expected);
            if ($validated === null && $expected !== 'scalar') {
                return '';
            }
            // Return string for DB storage
            return is_string($validated) ? $validated : (string) json_encode($validated);
        }

        $allowed = is_array($config) ? ($config['allowed_values'] ?? null) : null;
        if (is_array($allowed) && $allowed !== []) {
            $lower = strtolower($value);
            foreach ($allowed as $opt) {
                if (strtolower((string) $opt) === $lower) {
                    return (string) $opt;
                }
            }
            // Normalize spaces to hyphens for slug-like fields (e.g. use_case: "dual sport" -> "dual-sport")
            $normalized = strtolower(str_replace(' ', '-', preg_replace('/\s+/', ' ', trim($value))));
            foreach ($allowed as $opt) {
                if (strtolower((string) $opt) === $normalized) {
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

        if ($metaKey === 'spec_weight_g') {
            if (preg_match('/\b(\d{1,5})\s*(?:g|grams?)?\b/i', $value, $m)) {
                $g = (int) $m[1];
                if ($g > 0 && $g <= 99999) {
                    return (string) $g;
                }
            }
            return '';
        }

        if ($metaKey === 'price_retail_usd') {
            if (preg_match('/\$?\s*(\d+(?:\.\d{1,2})?)\b/', $value, $m)) {
                $p = (float) $m[1];
                if ($p >= 0 && $p <= 999999.99) {
                    return $m[1];
                }
            }
            return '';
        }

        if ($metaKey === 'brand_support_url') {
            $url = esc_url_raw($value, ['https', 'http']);
            return $url !== '' ? $url : '';
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
     * Coverage report: per-field counts of set vs empty for a post type (no API calls).
     *
     * @return array{total_posts: int, fields: array<string, array{set: int, empty: int, pct: float}>}
     */
    public function getCoverageReport(string $postType, int $limit = 0): array
    {
        $fillable = FillableFieldsConfig::forPostType($postType);
        $taxonomyConfig = FillableFieldsConfig::taxonomyFillableConfig()[$postType] ?? [];
        $query = new \WP_Query([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        $total = count($ids);
        $fields = [];
        foreach (array_keys($fillable) as $metaKey) {
            $fields[$metaKey] = ['set' => 0, 'empty' => 0, 'pct' => 0.0];
        }
        foreach (array_keys($taxonomyConfig) as $taxonomy) {
            $fields['taxonomy:' . $taxonomy] = ['set' => 0, 'empty' => 0, 'pct' => 0.0];
        }
        foreach ($ids as $postId) {
            foreach (array_keys($fillable) as $metaKey) {
                $v = get_post_meta($postId, $metaKey, true);
                if ($v !== '' && $v !== null && $v !== []) {
                    $fields[$metaKey]['set']++;
                } else {
                    $fields[$metaKey]['empty']++;
                }
            }
            foreach (array_keys($taxonomyConfig) as $taxonomy) {
                $terms = get_the_terms($postId, $taxonomy);
                if (is_array($terms) && $terms !== []) {
                    $fields['taxonomy:' . $taxonomy]['set']++;
                } else {
                    $fields['taxonomy:' . $taxonomy]['empty']++;
                }
            }
        }
        foreach ($fields as $key => $counts) {
            $fields[$key]['pct'] = $total > 0 ? round($counts['set'] / $total * 100, 1) : 0.0;
        }
        return ['total_posts' => $total, 'fields' => $fields];
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
