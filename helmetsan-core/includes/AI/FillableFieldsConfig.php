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
            // Catalog completeness fields (weight, shell, price)
            'spec_weight_g' => [
                'label' => 'Weight in grams (integer, e.g. 1450)',
                'max_length' => 6,
            ],
            'spec_shell_material' => [
                'label' => 'Shell material (e.g. Polycarbonate, Carbon fiber, AIM+, composite)',
                'max_length' => 80,
            ],
            'price_retail_usd' => [
                'label' => 'Suggested retail price in USD (number, e.g. 299 or 349.99)',
                'max_length' => 10,
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
                'label' => 'Primary use case',
                'max_length' => 60,
                'allowed_values' => ['touring', 'racing', 'commuter', 'adventure', 'track', 'daily', 'off-road', 'sport', 'cruising', 'dual-sport', 'motocross', 'street'],
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
                'label' => 'SEO title: include brand and helmet type, under 60 characters',
                'max_length' => 60,
            ],
            'yoast_metadesc' => [
                'label' => 'Meta description: benefit-led, mention certs/specs, 150–160 chars, end with CTA',
                'max_length' => 160,
            ],
            'yoast_focuskw' => [
                'label' => 'Focus keyphrase: primary search phrase (lowercase, e.g. brand model helmet)',
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
            'brand_total_models' => [
                'label' => 'Approximate total helmet models (number or short phrase, e.g. 45 or 50+)',
                'max_length' => 20,
            ],
            'brand_helmet_types' => [
                'label' => 'Helmet types offered (comma-separated, e.g. Full Face, Modular, Open Face, Dual Sport)',
                'max_length' => 200,
            ],
            'brand_certification_coverage' => [
                'label' => 'Certification coverage (e.g. ECE, DOT, Snell, SHARP, FIM)',
                'max_length' => 120,
            ],
            'brand_support_url' => [
                'label' => 'Official support or contact URL (https preferred)',
                'max_length' => 500,
            ],
            'brand_warranty_terms' => [
                'label' => 'Typical warranty terms (e.g. 5 years, 2 years)',
                'max_length' => 60,
            ],
            'brand_origin_country' => [
                'label' => 'Country of origin (single country name or code)',
                'max_length' => 60,
            ],
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
            'brand_founded_year' => [
                'label' => 'Year founded (exactly 4 digits, e.g. 1959)',
                'max_length' => 4,
            ],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    /**
     * Canonical accessory category names (must match seed_accessory_categories / mapTypeToAccessoryCategory).
     * Used so AI fill-missing returns values that backfill-accessory-categories can assign.
     */
    private const ACCESSORY_CATEGORY_NAMES = [
        'Face Shields', 'Pinlock Inserts', 'Tear-Offs', 'Goggles', 'Replacement Lenses', 'Anti-Fog Solutions', 'Sun Visors',
        'Bluetooth Headsets', 'Mesh Intercoms', 'Helmet Cameras', 'Audio Kits', 'GPS Navigation', 'Smart Helmet Add-ons',
        'Communications', 'Maintenance & Care', 'Electronics', 'Inner Liners', 'Visors & Shields',
        'Cheek Pads', 'Liners', 'Helmet Cleaners', 'Visor Cleaners', 'Helmet Bags', 'Balaclavas', 'Breath Guards',
        'Breath Boxes', 'Peak Visors', 'Replacement Vents', 'Pivot Kits', 'Chin Curtains', 'Reflective Stickers',
    ];

    public static function forAccessory(): array
    {
        return [
            'accessory_type' => [
                'label' => 'Accessory product type (e.g. Pinlock Insert, Bluetooth Headset, Face Shield, Liner, Helmet Bag). Use a short descriptive type so it can map to a category.',
                'max_length' => 60,
            ],
            'accessory_parent_category' => [
                'label' => 'Parent category: choose ONE from the canonical list (e.g. Face Shields, Pinlock Inserts, Tear-Offs, Bluetooth Headsets, Helmet Cameras, Audio Kits, Liners, Helmet Cleaners, Helmet Bags, Balaclavas, Breath Guards, Chin Curtains, Communications, Anti-Fog Solutions).',
                'max_length' => 80,
                'allowed_values' => self::ACCESSORY_CATEGORY_NAMES,
            ],
            'accessory_subcategory' => [
                'label' => 'Subcategory or variant name (e.g. Max Vision, Pack of 20, Universal)',
                'max_length' => 80,
            ],
            'accessory_color' => [
                'label' => 'Color if applicable (e.g. Clear, Dark Smoke, Black)',
                'max_length' => 40,
            ],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forSafetyStandard(): array
    {
        return [
            'yoast_title'    => ['label' => 'SEO title (under 60 chars)', 'max_length' => 60],
            'yoast_metadesc' => ['label' => 'Meta description (under 160 chars)', 'max_length' => 160],
            'yoast_focuskw'  => ['label' => 'Focus keyphrase (lowercase)', 'max_length' => 60],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forDealer(): array
    {
        return [
            'yoast_title'    => ['label' => 'SEO title (under 60 chars)', 'max_length' => 60],
            'yoast_metadesc' => ['label' => 'Meta description (under 160 chars)', 'max_length' => 160],
            'yoast_focuskw'  => ['label' => 'Focus keyphrase (lowercase)', 'max_length' => 60],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forDistributor(): array
    {
        return [
            'yoast_title'    => ['label' => 'SEO title (under 60 chars)', 'max_length' => 60],
            'yoast_metadesc' => ['label' => 'Meta description (under 160 chars)', 'max_length' => 160],
            'yoast_focuskw'  => ['label' => 'Focus keyphrase (lowercase)', 'max_length' => 60],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forComparison(): array
    {
        return [
            'yoast_title'    => ['label' => 'SEO title (under 60 chars)', 'max_length' => 60],
            'yoast_metadesc' => ['label' => 'Meta description (under 160 chars)', 'max_length' => 160],
            'yoast_focuskw'  => ['label' => 'Focus keyphrase (lowercase)', 'max_length' => 60],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forRecommendation(): array
    {
        return [
            'yoast_title'    => ['label' => 'SEO title (under 60 chars)', 'max_length' => 60],
            'yoast_metadesc' => ['label' => 'Meta description (under 160 chars)', 'max_length' => 160],
            'yoast_focuskw'  => ['label' => 'Focus keyphrase (lowercase)', 'max_length' => 60],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forTechnology(): array
    {
        return [
            'yoast_title'    => ['label' => 'SEO title (under 60 chars)', 'max_length' => 60],
            'yoast_metadesc' => ['label' => 'Meta description (under 160 chars)', 'max_length' => 160],
            'yoast_focuskw'  => ['label' => 'Focus keyphrase (lowercase)', 'max_length' => 60],
        ];
    }

    /**
     * @return array<string, string|FieldConfig>
     */
    public static function forMotorcycle(): array
    {
        return [
            'yoast_title'    => ['label' => 'SEO title (under 60 chars)', 'max_length' => 60],
            'yoast_metadesc' => ['label' => 'Meta description (under 160 chars)', 'max_length' => 160],
            'yoast_focuskw'  => ['label' => 'Focus keyphrase (lowercase)', 'max_length' => 60],
        ];
    }

    public static function forPostType(string $postType): array
    {
        return match ($postType) {
            'helmet' => self::forHelmet(),
            'brand' => self::forBrand(),
            'accessory' => self::forAccessory(),
            'safety_standard' => self::forSafetyStandard(),
            'dealer' => self::forDealer(),
            'distributor' => self::forDistributor(),
            'comparison' => self::forComparison(),
            'recommendation' => self::forRecommendation(),
            'technology' => self::forTechnology(),
            'motorcycle' => self::forMotorcycle(),
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
                'region'        => 'Region (e.g. North America, Europe, Global)',
                'certification' => 'Safety certifications (e.g. DOT, ECE, Snell)',
                'feature_tag'   => 'Feature tags (e.g. Bluetooth-ready, Pinlock, MIPS)',
                'helmet_brand'  => 'Brand taxonomy term (match to rel_brand when possible)',
                'use_case'      => 'Use case (e.g. touring, racing, commuter)',
                'price_range'   => 'Price range (e.g. budget, mid-range, premium, luxury)',
            ],
            'brand' => [
                'helmet_type' => 'Helmet types this brand offers (e.g. Full Face, Modular)',
                'region'      => 'Regions served (e.g. North America, Europe, Global)',
            ],
            'accessory' => [
                'accessory_category' => 'Accessory category (e.g. Visors & Shields, Communications)',
                'helmet_type'       => 'Compatible helmet type (e.g. Full Face, Modular)',
                'region'            => 'Region (e.g. North America, Europe)',
                'feature_tag'       => 'Feature tags (e.g. Pinlock-ready, Bluetooth)',
                'use_case'          => 'Use case (e.g. touring, commuting)',
                'price_range'       => 'Price range (e.g. budget, mid-range, premium)',
            ],
            'safety_standard' => [
                'region'        => 'Region(s) where standard applies (e.g. North America, Europe, Global)',
                'certification' => 'Certification term (match standard name when possible)',
            ],
            'dealer' => [
                'region' => 'Region(s) served (e.g. North America, Europe, Asia Pacific)',
            ],
            'distributor' => [
                'region' => 'Region(s) distributed (e.g. South Asia, Europe)',
            ],
            'comparison' => [
                'region' => 'Region(s) relevant to comparison (e.g. North America, India)',
            ],
            'recommendation' => [
                'region' => 'Region(s) for recommendation (e.g. India, USA, Europe)',
            ],
            'technology' => [
                'feature_tag' => 'Feature tags (e.g. MIPS, Bluetooth-ready, Pinlock)',
            ],
            'motorcycle' => [
                'region' => 'Region(s) relevant (e.g. North America, Europe)',
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
