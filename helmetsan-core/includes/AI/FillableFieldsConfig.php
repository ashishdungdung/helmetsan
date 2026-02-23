<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Defines which entity meta fields can be AI-filled when missing (Phase 2).
 * Each field can have: label (for prompt), max_length, allowed_values (validation).
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
}
