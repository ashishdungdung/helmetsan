<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use WP_Post;
use Helmetsan\Core\Support\TaskTracker;

/**
 * Phase 2: Fills missing entity fields using the AI module (context-aware).
 * Supports bulk JSON filling, junk detection, cache, and field propagation.
 */
final class FillMissingService
{
    private const CACHE_PREFIX = 'helmetsan_fill_';
    private const DEFAULT_MAX_LENGTH = 5000;

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
     */
    public function run(
        string $postType,
        int $limit = 0,
        int $offset = 0,
        bool $dryRun = false,
        ?array $onlyFields = null,
        bool $onlyIncomplete = false,
        bool $strictMode = false, // Ignored in bulk mode
        bool $fillTaxonomies = true,
        ?callable $onProgress = null,
        ?callable $onVerbose = null,
        int $cacheTtl = 86400,
        ?int $rateLimitSeconds = null, // Ignored as AiService handles it
        bool $refillAccessoryIfNoCategory = false,
        ?array $onlyTaxonomies = null,
        bool $refillHelmetSpecs = false,
        bool $multiplex = true, // Default to true now for speed
        ?int $postId = null
    ): array {
        $fillable = FillableFieldsConfig::forPostType($postType);
        if ($onlyFields !== null && $onlyFields !== []) {
            $fillable = array_intersect_key($fillable, array_flip($onlyFields));
        }
        
        if ($fillable === [] && ($onlyTaxonomies === null || $onlyTaxonomies === [])) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }

        if (! $this->aiService->hasAnyConfiguredProvider()) {
            return ['filled' => 0, 'skipped' => 0, 'errors' => 0, 'total_posts' => 0, 'api_calls' => 0];
        }

        $queryArgs = [
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
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
        if ($onlyIncomplete) {
            $ids = $this->filterIncompletePosts($ids, $fillable);
        }
        
        $totalPosts = count($ids);
        $filled = 0; $skipped = 0; $errors = 0; $apiCalls = 0; $processed = 0;

        $tId = $this->taskId ?? 'fm-' . getmypid();
        if ($this->tracker !== null) {
            $this->tracker->start($tId, "Fill Missing ($postType)", 'ai-enrichment');
        }

        $junkValues = ['n/a', 'not available', 'unknown', 'none', '-', 'null', 'not specified', 'undefined', 'not applicable'];
        $helmetSpecKeys = ['spec_weight_g', 'spec_shell_material', 'price_retail_usd'];

        foreach ($ids as $postId) {
            $post = get_post($postId);
            if (! $post instanceof WP_Post) continue;

            $existingData = $this->gatherExistingData($postType, $postId, $post);
            
            // 1. Identify fields to fill (Meta)
            $toFill = [];
            foreach ($fillable as $metaKey => $config) {
                $raw = get_post_meta($postId, $metaKey, true);
                $val = is_string($raw) ? trim($raw) : $raw;
                
                $forceRefill = ($refillAccessoryIfNoCategory && $postType === 'accessory'
                    && ($metaKey === 'accessory_parent_category' || $metaKey === 'accessory_type')
                    && $this->accessoryHasNoCategoryTerm($postId))
                    || ($refillHelmetSpecs && $postType === 'helmet' && in_array($metaKey, $helmetSpecKeys, true));

                $isMissing = ($val === '' || $val === null || $val === []);
                if (!$isMissing && is_string($val)) {
                    $lower = strtolower($val);
                    foreach ($junkValues as $junk) {
                        if ($lower === $junk) { $isMissing = true; break; }
                    }
                }

                if ($isMissing || $forceRefill) {
                    $cacheKey = self::CACHE_PREFIX . $postType . '_' . $postId . '_' . $metaKey;
                    if (get_transient($cacheKey) === false || $forceRefill) {
                        $toFill[$metaKey] = $config;
                    } else {
                        $skipped++;
                    }
                }
            }

            // 2. Perform Bulk Fill (Meta)
            if ($toFill !== []) {
                $fieldNames = array_keys($toFill);
                $onVerbose && $onVerbose('processing', $postId, implode(',', $fieldNames), 'bulk start');
                
                $results = $this->aiService->generateFillFieldsJSON($postType, $fieldNames, $toFill, $existingData);
                $apiCalls++;

                foreach ($toFill as $metaKey => $config) {
                    $cacheKey = self::CACHE_PREFIX . $postType . '_' . $postId . '_' . $metaKey;
                    $value = $results[$metaKey] ?? null;
                    if (is_array($value)) {
                        $value = implode(', ', array_filter($value, 'is_scalar'));
                    }

                    if ($value === null || $value === '' || (is_string($value) && in_array(strtolower(trim((string)$value)), $junkValues))) {
                        $onVerbose && $onVerbose('error', $postId, $metaKey, 'empty/junk response');
                        continue;
                    }

                    $sanitized = $this->sanitizeAndValidate($metaKey, (string) $value, $config);
                    if ($sanitized === '') {
                        $onVerbose && $onVerbose('error', $postId, $metaKey, 'validation failed: ' . $value);
                        continue;
                    }

                    if (! $dryRun) {
                        update_post_meta($postId, $metaKey, $sanitized);
                        // Yoast Sync
                        $yoastMap = FillableFieldsConfig::yoastMetaMapping();
                        if (isset($yoastMap[$metaKey])) {
                            update_post_meta($postId, $yoastMap[$metaKey], $sanitized);
                        }
                        // Propagation
                        if ($postType === 'helmet' && ! str_starts_with($metaKey, 'yoast_')) {
                            $this->propagateToChildren($postId, $metaKey, $sanitized);
                        }
                        set_transient($cacheKey, '1', $cacheTtl);
                    }
                    $filled++;
                    $onVerbose && $onVerbose('filled', $postId, $metaKey, (string)$sanitized);
                    $existingData[$metaKey] = $sanitized;
                }
            }

            // 3. Taxonomies (Sequential for now)
            if ($fillTaxonomies) {
                $taxConfig = FillableFieldsConfig::taxonomyFillableConfig()[$postType] ?? [];
                if ($onlyTaxonomies !== null && $onlyTaxonomies !== []) {
                    $taxConfig = array_intersect_key($taxConfig, array_flip($onlyTaxonomies));
                }
                foreach ($taxConfig as $taxonomy => $label) {
                    $existing = get_the_terms($postId, $taxonomy);
                    if (is_array($existing) && $existing !== []) continue;

                    $cacheKey = self::CACHE_PREFIX . $postType . '_' . $postId . '_tax_' . $taxonomy;
                    if (get_transient($cacheKey) !== false) continue;

                    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                    if (is_wp_error($terms) || empty($terms)) continue;

                    $allowed = array_values(array_filter(array_map(static fn($t) => $t instanceof \WP_Term ? $t->name : '', $terms)));
                    if (empty($allowed)) continue;

                    $val = $this->aiService->generateFillField($postType, 'taxonomy_' . $taxonomy, $existingData, $label, null, $allowed);
                    $apiCalls++;
                    
                    if ($val === null || trim((string) $val) === '') continue;

                    $val = trim((string) $val);
                    $term = get_term_by('name', $val, $taxonomy) ?: get_term_by('slug', sanitize_title($val), $taxonomy);

                    if ($term instanceof \WP_Term) {
                        if (! $dryRun) {
                            wp_set_object_terms($postId, [(int) $term->term_id], $taxonomy, false);
                            set_transient($cacheKey, '1', $cacheTtl);
                        }
                        $filled++;
                        $onVerbose && $onVerbose('filled', $postId, 'taxonomy:' . $taxonomy, $term->name);
                    }
                }
            }

            $processed++;
            if ($this->tracker !== null) {
                if ($this->tracker->isCancelled($tId)) break;
                $this->tracker->heartbeat($tId, $processed);
            }
            $onProgress && $onProgress($processed, $totalPosts, $postId);
        }

        return ['filled' => $filled, 'skipped' => $skipped, 'errors' => $errors, 'total_posts' => $totalPosts, 'api_calls' => $apiCalls];
    }

    private function propagateToChildren(int $parentId, string $metaKey, mixed $value): void
    {
        $children = get_children(['post_parent' => $parentId, 'post_type' => 'helmet', 'fields' => 'ids']);
        if (is_array($children)) {
            foreach ($children as $childId) {
                update_post_meta((int) $childId, $metaKey, $value);
            }
        }
    }

    public function accessoryHasNoCategoryTerm(int $postId): bool
    {
        if (get_post_type($postId) !== 'accessory') return false;
        $terms = get_the_terms($postId, 'accessory_category');
        return is_wp_error($terms) || ! is_array($terms) || $terms === [];
    }

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

                // NEW: If it's a helmet description, check if it's just a fallback
                if (get_post_type($postId) === 'helmet' && ($metaKey === 'marketing_description' || $metaKey === 'technical_analysis')) {
                    if ($this->isFallbackDescription($postId, (string) $v)) {
                        $out[] = $postId;
                        break;
                    }
                    if (strlen((string) $v) < 60) {
                        $out[] = $postId;
                        break;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Check if a description matches the generic fallback pattern:
     * "Title | Type: X | Brand: Y"
     */
    private function isFallbackDescription(int $postId, string $desc): bool
    {
        $post = get_post($postId);
        if (! $post) return false;

        $title = (string) $post->post_title;
        $brandId = (int) get_post_meta($postId, 'rel_brand', true);
        $brand = '';
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            $brand = $brandPost ? (string) $brandPost->post_title : '';
        }

        $typeTerms = get_the_terms($postId, 'helmet_type');
        $type = (is_array($typeTerms) && !empty($typeTerms)) ? $typeTerms[0]->name : '';

        // Check for common patterns generated by IngestionService::buildDescription
        $patterns = [
            $title . ' | Type: ' . $type . ' | Brand: ' . $brand,
            $title . ' | Brand: ' . $brand,
            $title . ' | Type: ' . $type,
            $title . ' | ' . $type . ' | ' . $brand,
        ];

        foreach ($patterns as $pattern) {
            if (trim($desc) === trim($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function gatherExistingData(string $postType, int $postId, WP_Post $post): array
    {
        $data = ['title' => (string) $post->post_title];
        $allKeys = FillableFieldsConfig::forPostType($postType);
        foreach (array_keys($allKeys) as $key) {
            $v = get_post_meta($postId, $key, true);
            if ($v !== '' && $v !== null) $data[$key] = is_string($v) ? $v : (string) json_encode($v);
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
        return $data;
    }

    public function sanitizeAndValidate(string $metaKey, string $value, $config): mixed
    {
        $value = preg_replace('/^["\']|["\']$/u', '', trim($value));
        if ($value === '') return '';

        if (str_ends_with($metaKey, '_json')) {
            $expected = $this->jsonValidator->getExpectedTypeFromField($metaKey);
            $validated = $this->jsonValidator->validate($value, $expected, $metaKey);
            if ($validated === null && $expected !== 'scalar') return '';
            return is_string($validated) ? $validated : (string) json_encode($validated);
        }

        $allowed = is_array($config) ? ($config['allowed_values'] ?? null) : null;
        if (is_array($allowed) && $allowed !== []) {
            $lower = strtolower($value);
            foreach ($allowed as $opt) {
                if (strtolower((string) $opt) === $lower) return (string) $opt;
            }
            $normalized = strtolower(str_replace(' ', '-', preg_replace('/\s+/', ' ', trim($value))));
            foreach ($allowed as $opt) {
                if (strtolower((string) $opt) === $normalized) return (string) $opt;
            }
            return '';
        }

        if ($metaKey === 'brand_founded_year') {
            if (preg_match('/\b(19|20)\d{2}\b/', $value, $m)) {
                $year = (int) $m[0];
                if ($year >= 1900 && $year <= (int)date('Y')) return $m[0];
            }
            return '';
        }

        if ($metaKey === 'spec_weight_g' && preg_match('/\b(\d{1,5})\b/', $value, $m)) {
            return $m[1];
        }

        if ($metaKey === 'price_retail_usd' && preg_match('/(\d+(?:\.\d{1,2})?)/', $value, $m)) {
            return $m[1];
        }

        $max = is_array($config) && isset($config['max_length']) ? $config['max_length'] : self::DEFAULT_MAX_LENGTH;
        if (strlen($value) > $max) {
            $value = substr($value, 0, $max - 3);
            $last = strrpos($value, ' ');
            if ($last !== false && $last > (int)($max * 0.5)) $value = substr($value, 0, $last);
        }
        return $value;
    }

    /**
     * Coverage report: per-field counts of set vs empty for a post type (no API calls).
     * @return array{total_posts: int, fields: array<string, array{set: int, empty: int, pct: float}>}
     */
    public function getCoverageReport(string $postType, int $limit = 0): array
    {
        $fillable = FillableFieldsConfig::forPostType($postType);
        $taxConfig = FillableFieldsConfig::taxonomyFillableConfig()[$postType] ?? [];
        $ids = (new \WP_Query([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'fields' => 'ids',
        ]))->posts;
        if (!is_array($ids)) $ids = [];
        
        $total = count($ids);
        $fields = [];
        foreach (array_keys($fillable) as $k) $fields[$k] = ['set' => 0, 'empty' => 0, 'pct' => 0.0];
        foreach (array_keys($taxConfig) as $t) $fields['taxonomy:' . $t] = ['set' => 0, 'empty' => 0, 'pct' => 0.0];
        
        foreach ($ids as $postId) {
            foreach (array_keys($fillable) as $k) {
                $v = get_post_meta((int)$postId, $k, true);
                if ($v !== '' && $v !== null && $v !== []) $fields[$k]['set']++;
                else $fields[$k]['empty']++;
            }
            foreach (array_keys($taxConfig) as $t) {
                $terms = get_the_terms((int)$postId, $t);
                if (is_array($terms) && $terms !== []) $fields['taxonomy:' . $t]['set']++;
                else $fields['taxonomy:' . $t]['empty']++;
            }
        }
        foreach ($fields as $k => $c) {
            $fields[$k]['pct'] = $total > 0 ? round($c['set'] / $total * 100, 1) : 0.0;
        }
        return ['total_posts' => $total, 'fields' => $fields];
    }

    public static function getFillableKeys(string $postType): array
    {
        return array_keys(FillableFieldsConfig::forPostType($postType));
    }
}
