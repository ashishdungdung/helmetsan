<?php

declare(strict_types=1);

namespace Helmetsan\Core\WooBridge;

use Helmetsan\Core\Support\Config;

final class WooBridgeService
{
    public function __construct(private readonly Config $config)
    {
    }

    public function register(): void
    {
        add_action('save_post_helmet', [$this, 'maybeSyncOnSave'], 20, 2);
    }

    public function available(): bool
    {
        return class_exists('WooCommerce')
            && class_exists('WC_Product_Variable')
            && class_exists('WC_Product_Variation')
            && function_exists('wc_get_product');
    }

    /**
     * @return array<string,mixed>
     */
    public function syncHelmet(int $helmetId, bool $dryRun = false): array
    {
        if (! $this->available()) {
            return ['ok' => false, 'message' => 'WooCommerce is not active'];
        }

        $helmet = get_post($helmetId);
        if (! ($helmet instanceof \WP_Post) || $helmet->post_type !== 'helmet') {
            return ['ok' => false, 'message' => 'Invalid helmet post'];
        }

        $cfg = $this->config();
        $variants = $this->decodeJsonMeta($helmetId, 'variants_json');
        if ($variants === []) {
            return ['ok' => false, 'message' => 'No variants_json found for helmet'];
        }

        $existingProductId = (int) get_post_meta($helmetId, 'wc_product_id', true);
        $product = $existingProductId > 0 ? wc_get_product($existingProductId) : null;
        $action = 'updated';

        if (! $product || ! ($product instanceof \WC_Product_Variable)) {
            $product = new \WC_Product_Variable();
            $action = 'created';
        }

        $title = (string) get_the_title($helmetId);
        $helmetUniqueId = (string) get_post_meta($helmetId, '_helmet_unique_id', true);
        $productDetails = $this->decodeJsonMeta($helmetId, 'product_details_json');
        $description = isset($productDetails['description']) && is_string($productDetails['description']) && $productDetails['description'] !== ''
            ? $productDetails['description']
            : (string) $helmet->post_content;

        $product->set_name($title);
        $product->set_description(wp_kses_post($description));
        $product->set_short_description(wp_kses_post((string) $helmet->post_excerpt));
        $product->set_status(! empty($cfg['publish_products']) ? 'publish' : 'draft');
        if ($helmetUniqueId !== '') {
            $product->set_sku($helmetUniqueId);
        }

        $this->assignProductCategories($product, $helmetId);
        $parentAttributes = $this->buildParentAttributes($variants);
        if ($parentAttributes !== []) {
            $product->set_attributes($parentAttributes);
        }

        if (! $dryRun) {
            $productId = $product->save();
            update_post_meta($productId, '_helmet_post_id', $helmetId);
            update_post_meta($helmetId, 'wc_product_id', $productId);
            update_post_meta($productId, '_helmet_unique_id', $helmetUniqueId);
            update_post_meta($productId, '_product_details_json', wp_json_encode($productDetails, JSON_UNESCAPED_SLASHES));
            update_post_meta($productId, '_part_numbers_json', wp_json_encode($this->decodeJsonMeta($helmetId, 'part_numbers_json'), JSON_UNESCAPED_SLASHES));
            update_post_meta($productId, '_sizing_fit_json', wp_json_encode($this->decodeJsonMeta($helmetId, 'sizing_fit_json'), JSON_UNESCAPED_SLASHES));
            update_post_meta($productId, '_related_videos_json', wp_json_encode($this->decodeJsonMeta($helmetId, 'related_videos_json'), JSON_UNESCAPED_SLASHES));
        } else {
            $productId = $existingProductId;
        }

        $variationResults = $this->syncVariations($productId, $variants, $dryRun);

        if (! $dryRun && $productId > 0) {
            \WC_Product_Variable::sync($productId);
            wc_delete_product_transients($productId);
            update_post_meta($helmetId, 'wc_variation_map_json', wp_json_encode($variationResults['map'], JSON_UNESCAPED_SLASHES));
        }

        return [
            'ok' => true,
            'action' => $action,
            'helmet_id' => $helmetId,
            'product_id' => $productId,
            'dry_run' => $dryRun,
            'variation' => $variationResults,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function syncBatch(int $limit = 100, bool $dryRun = false): array
    {
        $limit = max(1, $limit);
        $ids = get_posts([
            'post_type' => 'helmet',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'variants_json',
                    'compare' => 'EXISTS',
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $processed = 0;
        $accepted = 0;
        $failed = 0;
        $results = [];

        foreach ((array) $ids as $id) {
            $processed++;
            $out = $this->syncHelmet((int) $id, $dryRun);
            $results[] = $out;
            if (! empty($out['ok'])) {
                $accepted++;
            } else {
                $failed++;
            }
        }

        return [
            'ok' => true,
            'processed' => $processed,
            'accepted' => $accepted,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'results' => $results,
        ];
    }

    public function maybeSyncOnSave(int $postId, \WP_Post $post): void
    {
        if ($post->post_type !== 'helmet') {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $cfg = $this->config();
        if (empty($cfg['enable_bridge']) || empty($cfg['auto_sync_on_save'])) {
            return;
        }

        $this->syncHelmet($postId, false);
    }

    /**
     * @param array<int,mixed> $variants
     * @return array<string,mixed>
     */
    private function syncVariations(int $productId, array $variants, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $map = [];

        if ($productId <= 0) {
            return [
                'created' => 0,
                'updated' => 0,
                'failed' => count($variants),
                'map' => [],
            ];
        }

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $variantId = sanitize_title((string) ($variant['id'] ?? $variant['sku'] ?? ''));
            if ($variantId === '') {
                $failed++;
                continue;
            }

            $existingVariationId = $this->findVariationByVariantId($productId, $variantId);
            $wcVariation = $existingVariationId > 0 ? new \WC_Product_Variation($existingVariationId) : new \WC_Product_Variation();

            $attributes = $this->buildVariationAttributes($variant);
            $price = isset($variant['price']) ? (float) $variant['price'] : 0.0;
            $sku = sanitize_text_field((string) ($variant['sku'] ?? $variant['mfr_part_number'] ?? $variantId));

            $wcVariation->set_parent_id($productId);
            $wcVariation->set_attributes($attributes);
            $wcVariation->set_sku($sku);
            if ($price > 0) {
                $wcVariation->set_regular_price((string) $price);
                $wcVariation->set_price((string) $price);
            }
            $availability = strtolower((string) ($variant['availability'] ?? 'in_stock'));
            $wcVariation->set_manage_stock(false);
            $wcVariation->set_stock_status(in_array($availability, ['in_stock', 'available'], true) ? 'instock' : 'outofstock');

            if (! $dryRun) {
                $savedId = $wcVariation->save();
                update_post_meta($savedId, '_helmet_variant_id', $variantId);
                update_post_meta($savedId, '_mfr_part_number', sanitize_text_field((string) ($variant['mfr_part_number'] ?? '')));
                if (isset($variant['geo_pricing']) && is_array($variant['geo_pricing'])) {
                    update_post_meta($savedId, '_geo_pricing_json', wp_json_encode($variant['geo_pricing'], JSON_UNESCAPED_SLASHES));
                }
                $map[$variantId] = (int) $savedId;
            }

            if ($existingVariationId > 0) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'map' => $map,
        ];
    }

    /**
     * @param array<int,mixed> $variants
     * @return array<int,\WC_Product_Attribute>
     */
    private function buildParentAttributes(array $variants): array
    {
        if (! $this->available()) {
            return [];
        }

        $defs = [
            'color' => ['label' => 'Color', 'taxonomy' => $this->ensureAttributeTaxonomy('Color', 'color')],
            'size' => ['label' => 'Size', 'taxonomy' => $this->ensureAttributeTaxonomy('Size', 'size')],
            'style' => ['label' => 'Style', 'taxonomy' => $this->ensureAttributeTaxonomy('Style', 'style')],
        ];

        $valueMap = [
            'color' => [],
            'size' => [],
            'style' => [],
        ];

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            foreach (['color', 'size', 'style'] as $key) {
                $raw = isset($variant[$key]) ? sanitize_text_field((string) $variant[$key]) : '';
                if ($raw !== '') {
                    $valueMap[$key][$raw] = $raw;
                }
            }
        }

        $attributes = [];
        $position = 0;
        foreach ($defs as $key => $def) {
            if ($valueMap[$key] === []) {
                continue;
            }
            $taxonomy = (string) $def['taxonomy'];
            if ($taxonomy === '') {
                continue;
            }

            $termIds = [];
            foreach (array_values($valueMap[$key]) as $value) {
                $term = $this->findOrCreateTerm($taxonomy, $value);
                if ($term > 0) {
                    $termIds[] = $term;
                }
            }

            if ($termIds === []) {
                continue;
            }

            $attr = new \WC_Product_Attribute();
            $attr->set_id((int) wc_attribute_taxonomy_id_by_name($taxonomy));
            $attr->set_name($taxonomy);
            $attr->set_options(array_values(array_unique($termIds)));
            $attr->set_position($position++);
            $attr->set_visible(true);
            $attr->set_variation(true);
            $attributes[] = $attr;
        }

        return $attributes;
    }

    /**
     * @param array<string,mixed> $variant
     * @return array<string,string>
     */
    private function buildVariationAttributes(array $variant): array
    {
        $attrs = [];
        $map = [
            'color' => $this->ensureAttributeTaxonomy('Color', 'color'),
            'size' => $this->ensureAttributeTaxonomy('Size', 'size'),
            'style' => $this->ensureAttributeTaxonomy('Style', 'style'),
        ];

        foreach ($map as $field => $taxonomy) {
            $value = isset($variant[$field]) ? sanitize_text_field((string) $variant[$field]) : '';
            if ($taxonomy === '' || $value === '') {
                continue;
            }
            $termId = $this->findOrCreateTerm($taxonomy, $value);
            if ($termId > 0) {
                $term = get_term($termId, $taxonomy);
                if ($term instanceof \WP_Term) {
                    $attrs[$taxonomy] = $term->slug;
                }
            }
        }

        return $attrs;
    }

    private function ensureAttributeTaxonomy(string $label, string $slug): string
    {
        if (! function_exists('wc_attribute_taxonomy_id_by_name')) {
            return '';
        }

        $attributeId = (int) wc_attribute_taxonomy_id_by_name($slug);
        if ($attributeId <= 0 && function_exists('wc_create_attribute')) {
            $created = wc_create_attribute([
                'name' => $label,
                'slug' => $slug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ]);
            if (! is_wp_error($created) && (int) $created > 0) {
                delete_transient('wc_attribute_taxonomies');
                $attributeId = (int) wc_attribute_taxonomy_id_by_name($slug);
            }
        }

        if ($attributeId <= 0) {
            return '';
        }

        $taxonomy = wc_attribute_taxonomy_name_by_id($attributeId);
        if (! taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, ['product'], [
                'hierarchical' => false,
                'show_ui' => false,
                'query_var' => true,
                'rewrite' => false,
            ]);
        }

        return $taxonomy;
    }

    private function findOrCreateTerm(string $taxonomy, string $value): int
    {
        $value = sanitize_text_field($value);
        if ($value === '') {
            return 0;
        }

        $existing = get_term_by('name', $value, $taxonomy);
        if ($existing instanceof \WP_Term) {
            return (int) $existing->term_id;
        }

        $inserted = wp_insert_term($value, $taxonomy);
        if (is_wp_error($inserted) || ! is_array($inserted)) {
            $fallback = get_term_by('slug', sanitize_title($value), $taxonomy);
            return $fallback instanceof \WP_Term ? (int) $fallback->term_id : 0;
        }

        return isset($inserted['term_id']) ? (int) $inserted['term_id'] : 0;
    }

    private function assignProductCategories(\WC_Product_Variable $product, int $helmetId): void
    {
        $typeTerms = get_the_terms($helmetId, 'helmet_type');
        if (! is_array($typeTerms) || $typeTerms === []) {
            return;
        }

        $catIds = [];
        foreach ($typeTerms as $term) {
            if (! ($term instanceof \WP_Term)) {
                continue;
            }
            $cat = term_exists($term->name, 'product_cat');
            if ($cat === 0 || $cat === null) {
                $created = wp_insert_term($term->name, 'product_cat');
                if (! is_wp_error($created) && is_array($created) && isset($created['term_id'])) {
                    $catIds[] = (int) $created['term_id'];
                }
                continue;
            }
            if (is_array($cat) && isset($cat['term_id'])) {
                $catIds[] = (int) $cat['term_id'];
            }
        }

        if ($catIds !== []) {
            $product->set_category_ids(array_values(array_unique($catIds)));
        }
    }

    private function findVariationByVariantId(int $productId, string $variantId): int
    {
        $posts = get_posts([
            'post_type' => 'product_variation',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_parent' => $productId,
            'meta_key' => '_helmet_variant_id',
            'meta_value' => $variantId,
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }

    /**
     * @return array<int|string,mixed>
     */
    private function decodeJsonMeta(int $postId, string $metaKey): array
    {
        $raw = (string) get_post_meta($postId, $metaKey, true);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function config(): array
    {
        return wp_parse_args((array) get_option(Config::OPTION_WOO_BRIDGE, []), $this->config->wooBridgeDefaults());
    }
}
