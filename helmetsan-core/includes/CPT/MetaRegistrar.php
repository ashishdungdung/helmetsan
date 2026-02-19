<?php

declare(strict_types=1);

namespace Helmetsan\Core\CPT;

/**
 * Registers all post meta keys with WordPress so they are:
 *  - Exposed via the REST API (show_in_rest = true)
 *  - Validated against a declared type
 *  - Sanitized via a callback
 *
 * @package HelmetsanCore
 */
final class MetaRegistrar
{
    public function register(): void
    {
        add_action('init', [$this, 'registerHelmetMeta']);
        add_action('init', [$this, 'registerBrandMeta']);
        add_action('init', [$this, 'registerSafetyStandardMeta']);
        add_action('init', [$this, 'registerMotorcycleMeta']);
        add_action('init', [$this, 'registerAccessoryMeta']);
        add_action('init', [$this, 'registerDealerMeta']);
        add_action('init', [$this, 'registerDistributorMeta']);
        add_action('init', [$this, 'registerComparisonMeta']);
        add_action('init', [$this, 'registerRecommendationMeta']);
    }

    public function registerHelmetMeta(): void
    {
        $stringMeta = [
            '_helmet_unique_id'         => 'External unique ID',
            '_source_file'              => 'Source JSON file path',
            'spec_shell_material'       => 'Shell material',
            'spec_weight_lbs'           => 'Weight (lbs)',
            'price_retail_usd'          => 'Retail price USD',
            'helmet_family'             => 'Helmet family/line',
            'head_shape'                => 'Head shape fit',
            'affiliate_asin'            => 'Amazon ASIN',
            'brand_name_cached'         => 'Brand name (cached)',
            'brand_origin_country'      => 'Brand origin country (cached)',
            'brand_warranty_terms'      => 'Brand warranty (cached)',
            'brand_support_url'         => 'Brand support URL (cached)',
            'brand_support_email'       => 'Brand support email (cached)',
            'brand_cascade_at'          => 'Brand cascade timestamp',
            'brand_cascade_source'      => 'Brand cascade source',
        ];

        $intMeta = [
            'spec_weight_g' => 'Weight (grams)',
            'rel_brand'     => 'Related brand post ID',
        ];

        $jsonMeta = [
            'geo_pricing_json'                => 'Geo pricing',
            'geo_legality_json'               => 'Geo legality',
            'certification_documents_json'    => 'Certification documents',
            'geo_media_json'                  => 'Geo media',
            'variants_json'                   => 'Variants',
            'product_details_json'            => 'Product details',
            'part_numbers_json'               => 'Part numbers',
            'sizing_fit_json'                 => 'Sizing & fit',
            'related_videos_json'             => 'Related videos',
            'features_json'                   => 'Features',
            'helmet_types_json'               => 'Helmet types (normalized cache)',
            'technical_analysis'              => 'Technical analysis',
            'key_specs_json'                  => 'Key specifications',
            'compatible_accessories_json'     => 'Compatible accessories',
        ];

        foreach ($stringMeta as $key => $description) {
            register_post_meta('helmet', $key, [
                'type'              => 'string',
                'description'       => $description,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }

        foreach ($intMeta as $key => $description) {
            register_post_meta('helmet', $key, [
                'type'              => 'integer',
                'description'       => $description,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }

        foreach ($jsonMeta as $key => $description) {
            register_post_meta('helmet', $key, [
                'type'              => 'string',
                'description'       => $description . ' (JSON)',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => static fn(string $v) => wp_kses_post($v),
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    public function registerBrandMeta(): void
    {
        $fields = [
            'brand_origin_country'       => ['type' => 'string', 'label' => 'Origin country'],
            'brand_warranty_terms'       => ['type' => 'string', 'label' => 'Warranty terms'],
            'brand_support_url'          => ['type' => 'string', 'label' => 'Support URL'],
            'brand_support_email'        => ['type' => 'string', 'label' => 'Support email'],
            'brand_manufacturing_ethos'  => ['type' => 'string', 'label' => 'Manufacturing ethos'],
            'brand_distributor_regions'  => ['type' => 'string', 'label' => 'Distributor regions'],
            'brand_size_chart_json'      => ['type' => 'string', 'label' => 'Size chart JSON'],
            'brand_total_models'         => ['type' => 'string', 'label' => 'Total models'],
            'brand_helmet_types'         => ['type' => 'string', 'label' => 'Helmet types'],
            'brand_helmet_types_json'    => ['type' => 'string', 'label' => 'Helmet types JSON cache'],
            'brand_certification_coverage' => ['type' => 'string', 'label' => 'Certification coverage'],
            'brand_motto'                => ['type' => 'string', 'label' => 'Brand motto'],
            'brand_story'                => ['type' => 'string', 'label' => 'Brand story'],
            'brand_founded_year'         => ['type' => 'string', 'label' => 'Founded year'],
            '_brand_unique_id'           => ['type' => 'string', 'label' => 'External unique ID'],
        ];

        foreach ($fields as $key => $config) {
            register_post_meta('brand', $key, [
                'type'              => $config['type'],
                'description'       => $config['label'],
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    public function registerSafetyStandardMeta(): void
    {
        $stringFields = [
            '_safety_standard_unique_id'      => 'External unique ID',
            'standard_issuing_body'           => 'Issuing body',
            'standard_year_introduced'        => 'Year introduced',
            'standard_last_updated'           => 'Last updated year',
            'official_reference_url'          => 'Official reference URL',
            'standard_status'                 => 'Status (active/superseded/regional)',
            'standard_story'                  => 'Story & history',
        ];

        $jsonFields = [
            'standard_regions_json'           => 'Active regions',
            'mandatory_markets_json'          => 'Mandatory markets',
            'test_focus_json'                 => 'Test focus areas',
            'standard_timeline_json'          => 'Timeline events',
            'standard_testing_protocol_json'  => 'Testing protocol',
            'standard_performance_specs_json' => 'Performance specs',
        ];

        foreach ($stringFields as $key => $description) {
            register_post_meta('safety_standard', $key, [
                'type'              => 'string',
                'description'       => $description,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }

        foreach ($jsonFields as $key => $description) {
            register_post_meta('safety_standard', $key, [
                'type'              => 'string',
                'description'       => $description . ' (JSON)',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => static fn(string $v) => wp_kses_post($v),
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    public function registerMotorcycleMeta(): void
    {
        $fields = [
            '_motorcycle_unique_id'          => ['type' => 'string',  'label' => 'External unique ID'],
            'motorcycle_make'                => ['type' => 'string',  'label' => 'Make'],
            'motorcycle_model'               => ['type' => 'string',  'label' => 'Model'],
            'bike_segment'                   => ['type' => 'string',  'label' => 'Segment'],
            'engine_cc'                      => ['type' => 'number',  'label' => 'Engine CC'],
            'recommended_helmet_types_json'  => ['type' => 'string',  'label' => 'Recommended helmet types (JSON)'],
        ];

        foreach ($fields as $key => $config) {
            register_post_meta('motorcycle', $key, [
                'type'              => $config['type'],
                'description'       => $config['label'],
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $config['type'] === 'number'
                    ? static fn($v) => (float) $v
                    : 'sanitize_text_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    public function registerAccessoryMeta(): void
    {
        $fields = [
            '_accessory_unique_id'               => 'External unique ID',
            'accessory_type'                     => 'Accessory type',
            'accessory_parent_category'          => 'Parent category',
            'accessory_subcategory'              => 'Subcategory',
            'accessory_color'                    => 'Color',
            'accessory_youth_adult'              => 'Youth / Adult',
            'accessory_electric_compatible'      => 'Electric compatible (0/1)',
            'accessory_pinlock_ready'            => 'Pinlock ready (0/1)',
            'accessory_snow_compatible'          => 'Snow compatible (0/1)',
            'compatible_helmet_types_json'       => 'Compatible helmet types (JSON)',
            'compatible_brands_json'             => 'Compatible brands (JSON)',
            'compatible_helmet_families_json'    => 'Compatible helmet families (JSON)',
            'compatibility_json'                 => 'Compatibility details (JSON)',
            'accessory_features_json'            => 'Features (JSON)',
            'accessory_global_filters_json'      => 'Global filters (JSON)',
            'price_json'                         => 'Price (JSON)',
        ];

        foreach ($fields as $key => $description) {
            register_post_meta('accessory', $key, [
                'type'              => 'string',
                'description'       => $description,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    public function registerDealerMeta(): void
    {
        $stringFields = [
            '_dealer_unique_id'      => 'External unique ID',
            'dealer_type'            => 'Dealer type',
            'dealer_website'         => 'Website URL',
            'dealer_phone'           => 'Phone',
            'dealer_email'           => 'Email',
            'dealer_address'         => 'Address',
            'dealer_city'            => 'City',
            'dealer_country_code'    => 'Country code',
            'dealer_region_code'     => 'Region/state code',
            'dealer_online_store'    => 'Online store (0/1)',
            'dealer_offline_store'   => 'Physical store (0/1)',
        ];
        $jsonFields = [
            'dealer_geo_json'          => 'Geo coordinates',
            'dealer_brands_json'       => 'Brands stocked',
            'dealer_marketplaces_json' => 'Marketplaces',
            'dealer_services_json'     => 'Services offered',
        ];

        foreach ($stringFields as $key => $description) {
            register_post_meta('dealer', $key, [
                'type'              => 'string',
                'description'       => $description,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
        foreach ($jsonFields as $key => $description) {
            register_post_meta('dealer', $key, [
                'type'              => 'string',
                'description'       => $description . ' (JSON)',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    public function registerDistributorMeta(): void
    {
        $stringFields = [
            '_distributor_unique_id'   => 'External unique ID',
            'distributor_type'         => 'Distributor type',
            'distributor_website'      => 'Website URL',
            'distributor_phone'        => 'Phone',
            'distributor_email'        => 'Email',
            'distributor_address'      => 'Address',
            'distributor_country_code' => 'Country code',
        ];
        $jsonFields = [
            'distributor_regions_json'    => 'Regions',
            'distributor_countries_json'  => 'Countries',
            'distributor_brands_json'     => 'Brands',
            'distributor_warehouses_json' => 'Warehouses',
            'distributor_contacts_json'   => 'Contacts',
        ];

        foreach ($stringFields as $key => $description) {
            register_post_meta('distributor', $key, [
                'type'              => 'string',
                'description'       => $description,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
        foreach ($jsonFields as $key => $description) {
            register_post_meta('distributor', $key, [
                'type'              => 'string',
                'description'       => $description . ' (JSON)',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    public function registerComparisonMeta(): void
    {
        $fields = [
            '_comparison_unique_id'           => ['type' => 'string', 'label' => 'External unique ID'],
            'comparison_parameters_json'       => ['type' => 'string', 'label' => 'Parameters (JSON)'],
            'comparison_scores_json'           => ['type' => 'string', 'label' => 'Scores (JSON)'],
            'comparison_recommendations_json'  => ['type' => 'string', 'label' => 'Recommendations (JSON)'],
        ];

        foreach ($fields as $key => $config) {
            register_post_meta('comparison', $key, [
                'type'              => $config['type'],
                'description'       => $config['label'],
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }

        // rel_helmets is an array of integers
        register_post_meta('comparison', 'rel_helmets', [
            'type'          => 'array',
            'description'   => 'Related helmet post IDs',
            'single'        => true,
            'show_in_rest'  => [
                'schema' => [
                    'type'  => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);
    }

    public function registerRecommendationMeta(): void
    {
        $fields = [
            '_recommendation_unique_id'    => ['type' => 'string', 'label' => 'External unique ID'],
            'recommendation_use_case'      => ['type' => 'string', 'label' => 'Use case'],
            'recommendation_region'        => ['type' => 'string', 'label' => 'Region'],
            'recommendation_filters_json'  => ['type' => 'string', 'label' => 'Filters (JSON)'],
            'recommendation_items_json'    => ['type' => 'string', 'label' => 'Items (JSON)'],
        ];

        foreach ($fields as $key => $config) {
            register_post_meta('recommendation', $key, [
                'type'              => $config['type'],
                'description'       => $config['label'],
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => static fn() => current_user_can('edit_posts'),
            ]);
        }

        // rel_helmets is an array of integers
        register_post_meta('recommendation', 'rel_helmets', [
            'type'          => 'array',
            'description'   => 'Related helmet post IDs',
            'single'        => true,
            'show_in_rest'  => [
                'schema' => [
                    'type'  => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]);
    }
}
