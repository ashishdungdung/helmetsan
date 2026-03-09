<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Defines which entity meta fields can be AI-filled when missing (Phase 2+).
 * Each field can have: label (for prompt), max_length, allowed_values (validation).
 * Used by seeder pipeline, standalone CLI (fill-missing), and AI admin.
 * @see docs/ai-seeder-enrichment-roadmap.md
 * @phpstan-type FieldConfig array{label: string, max_length?: int, allowed_values?: list<string>}
 */
final class FillableFieldsConfig
{
    /**
     * @return array<string, string|FieldConfig> meta_key => label or config array
     */
    public static function forHelmet(): array
    {
        return [
            'spec_shell_material' => [
                'label' => 'Shell material (e.g. Polycarbonate, Carbon fiber, AIM+, composite)',
                'max_length' => 80,
            ],
            'helmet_family' => [
                'label' => 'Product family or series name (e.g. RF-Series, Star, X-Spirit)',
                'max_length' => 60,
            ],
            'head_shape' => [
                'label' => 'Head shape fit',
                'allowed_values' => ['long-oval', 'intermediate-oval', 'round-oval'],
            ],
            'technical_analysis' => [
                'label' => 'Short technical analysis paragraph: safety tech, comfort, ventilation (2-4 sentences)',
                'max_length' => 1200,
            ],
            'warranty_years' => [
                'label' => 'Manufacturer warranty duration in years (single number, e.g. 5)',
                'max_length' => 10,
                'allowed_values' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
            ],
            // Extended meta (roadmap: seeder, standalone, AI admin)
            'use_case' => [
                'label' => 'Primary use case (e.g. touring, racing, commuter, adventure, track)',
                'max_length' => 80,
            ],
            'price_range' => [
                'label' => 'Price tier for filtering',
                'allowed_values' => ['budget', 'mid-range', 'premium', 'luxury'],
            ],
            'model_year' => [
                'label' => 'Model year or release year (4 digits, e.g. 2024)',
                'max_length' => 4,
            ],
            // Yoast SEO (AI can suggest; stored as _yoast_wpseo_* by SEO seed or fill-missing)
            'yoast_title' => [
                'label' => 'SEO title (under 60 chars, include brand and type)',
                'max_length' => 60,
            ],
            'yoast_metadesc' => [
                'label' => 'Meta description (under 160 chars, call-to-action, specs/certs)',
                'max_length' => 160,
            ],
            'yoast_focuskw' => [
                'label' => 'Focus keyphrase (primary search phrase)',
                'max_length' => 60,
            ],
            // Cross-linking (JSON array of internal URLs or post IDs)
            'outgoing_internal_links_json' => [
                'label' => 'JSON array of related internal link URLs or post IDs (same brand, type, or cert)',
                'max_length' => 2000,
            ],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forBrand(): array
    {
        return [
            'brand_story' => [
                'label' => 'Brand story or history (1-2 short paragraphs)',
                'max_length' => 1500,
            ],
            'brand_motto' => [
                'label' => 'Brand motto or tagline (one short phrase)',
                'max_length' => 120,
            ],
            'brand_manufacturing_ethos' => [
                'label' => 'Manufacturing ethos or philosophy (1-2 sentences)',
                'max_length' => 400,
            ],
            'brand_origin_country' => [
                'label' => 'Country of origin (single country name)',
                'max_length' => 60,
            ],
            'brand_founded_year' => [
                'label' => 'Year founded (exactly 4 digits, e.g. 1959)',
                'max_length' => 4,
            ],
            'brand_warranty_terms' => [
                'label' => 'Typical warranty terms (e.g. 5 years, 2 years)',
                'max_length' => 60,
            ],
            'brand_certification_coverage' => [
                'label' => 'Brief certification coverage (e.g. ECE, DOT, SHARP)',
                'max_length' => 120,
            ],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forAccessory(): array
    {
        return [
            'accessory_type' => [
                'label' => 'Accessory type (e.g. Pinlock, Bluetooth, Visor, Liner)',
                'max_length' => 60,
            ],
            'accessory_parent_category' => [
                'label' => 'Parent category name (e.g. Visors & Pinlock, Communication)',
                'max_length' => 80,
            ],
            'accessory_subcategory' => [
                'label' => 'Subcategory name',
                'max_length' => 80,
            ],
            'accessory_color' => [
                'label' => 'Color if applicable (e.g. Clear, Dark, Black)',
                'max_length' => 40,
            ],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forPostType(string $postType): array
    {
        return match ($postType) {
            'helmet' => self::forHelmet(),
            'brand' => self::forBrand(),
            'accessory' => self::forAccessory(),
            default => [],
        };
    }

    /**
     * @param string|FieldConfig $config
     */
    public static function getLabel(string $metaKey, string $postType, $config): string
    {
        if (is_string($config)) {
            return $config;
        }
        return $config['label'] ?? $metaKey;
    }

    /**
     * @param string|FieldConfig $config
     */
    public static function getMaxLength($config): ?int
    {
        if (! is_array($config)) {
            return null;
        }
        $n = $config['max_length'] ?? null;
        return is_int($n) && $n > 0 ? $n : null;
    }

    /**
     * @param string|FieldConfig $config
     * @return list<string>|null
     */
    public static function getAllowedValues($config): ?array
    {
        if (! is_array($config)) {
            return null;
        }
        $a = $config['allowed_values'] ?? null;
        if (! is_array($a)) {
            return null;
        }
        return array_values(array_map('strval', $a));
    }

    /**
     * Taxonomies that AI can suggest terms for (term slug/name → wp_set_object_terms).
     * Used by fill-missing and cross-link; only assign existing terms unless config allows create.
     *
     * @return array<string, array<string, string>> post_type => [ taxonomy => label for prompt ]
     */
    public static function taxonomyFillableConfig(): array
    {
        return [
            'helmet' => [
                'helmet_type'   => 'Helmet type (e.g. Full Face, Modular, Open Face)',
                'certification' => 'Safety certifications (e.g. DOT, ECE, Snell)',
                'feature_tag'   => 'Feature tags (e.g. Bluetooth-ready, Pinlock, MIPS)',
                'helmet_brand'  => 'Brand taxonomy term (match to rel_brand when possible)',
            ],
            'brand' => [],
            'accessory' => [
                'accessory_category' => 'Accessory category (e.g. Visors & Shields, Communications)',
            ],
        ];
    }

    /**
     * Meta keys that map to Yoast SEO post meta (for fill-missing → Yoast sync).
     *
     * @return array<string, string> fillable_key => yoast_meta_key
     */
    public static function yoastMetaMapping(): array
    {
        return [
            'yoast_title'    => '_yoast_wpseo_title',
            'yoast_metadesc' => '_yoast_wpseo_metadesc',
            'yoast_focuskw'  => '_yoast_wpseo_focuskw',
        ];
    }
}
