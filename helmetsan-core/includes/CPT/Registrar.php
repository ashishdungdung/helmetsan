<?php

declare(strict_types=1);

namespace Helmetsan\Core\CPT;

/**
 * Registers all Custom Post Types and Taxonomies for the Helmetsan platform.
 *
 * @package HelmetsanCore
 */
final class Registrar
{
    public function register(): void
    {
        add_action('init', [$this, 'registerTypes']);
        add_action('init', [$this, 'registerTaxonomies']);
    }

    public function registerTypes(): void
    {
        $types = [
            'helmet' => [
                'label'        => 'Helmets',
                'singular'     => 'Helmet',
                'slug'         => 'helmets',
                'icon'         => 'dashicons-shield-alt',
                'menu_pos'     => 5,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes'],
                'has_archive'  => true,
                'hierarchical' => true,
            ],
            'brand' => [
                'label'        => 'Brands',
                'singular'     => 'Brand',
                'slug'         => 'brands',
                'icon'         => 'dashicons-tag',
                'menu_pos'     => 6,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'safety_standard' => [
                'label'        => 'Safety Standards',
                'singular'     => 'Safety Standard',
                'slug'         => 'safety-standards',
                'icon'         => 'dashicons-awards',
                'menu_pos'     => 7,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'accessory' => [
                'label'        => 'Accessories',
                'singular'     => 'Accessory',
                'slug'         => 'accessories',
                'icon'         => 'dashicons-admin-tools',
                'menu_pos'     => 8,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'motorcycle' => [
                'label'        => 'Motorcycles',
                'singular'     => 'Motorcycle',
                'slug'         => 'motorcycles',
                'icon'         => 'dashicons-car',
                'menu_pos'     => 9,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'dealer' => [
                'label'        => 'Dealers',
                'singular'     => 'Dealer',
                'slug'         => 'dealers',
                'icon'         => 'dashicons-store',
                'menu_pos'     => 10,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'distributor' => [
                'label'        => 'Distributors',
                'singular'     => 'Distributor',
                'slug'         => 'distributors',
                'icon'         => 'dashicons-networking',
                'menu_pos'     => 11,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'technology' => [
                'label'        => 'Technologies',
                'singular'     => 'Technology',
                'slug'         => 'technologies',
                'icon'         => 'dashicons-lightbulb',
                'menu_pos'     => 12,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'comparison' => [
                'label'        => 'Comparisons',
                'singular'     => 'Comparison',
                'slug'         => 'comparisons',
                'icon'         => 'dashicons-chart-bar',
                'menu_pos'     => 13,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
            'recommendation' => [
                'label'        => 'Recommendations',
                'singular'     => 'Recommendation',
                'slug'         => 'recommendations',
                'icon'         => 'dashicons-star-filled',
                'menu_pos'     => 14,
                'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
                'has_archive'  => true,
            ],
        ];

        foreach ($types as $slug => $config) {
            $singular = $config['singular'];
            $plural   = $config['label'];

            register_post_type($slug, [
                'labels' => [
                    'name'               => $plural,
                    'singular_name'      => $singular,
                    'add_new'            => 'Add New',
                    'add_new_item'       => 'Add New ' . $singular,
                    'edit_item'          => 'Edit ' . $singular,
                    'new_item'           => 'New ' . $singular,
                    'view_item'          => 'View ' . $singular,
                    'view_items'         => 'View ' . $plural,
                    'search_items'       => 'Search ' . $plural,
                    'not_found'          => 'No ' . strtolower($plural) . ' found.',
                    'not_found_in_trash' => 'No ' . strtolower($plural) . ' found in Trash.',
                    'all_items'          => 'All ' . $plural,
                    'menu_name'          => $plural,
                ],
                'public'              => true,
                'show_in_rest'        => true,
                'show_in_nav_menus'   => true,
                'show_in_admin_bar'   => true,
                'supports'            => $config['supports'],
                'has_archive'         => $config['has_archive'],
                'rewrite'             => ['slug' => $config['slug'], 'with_front' => false],
                'menu_position'       => $config['menu_pos'],
                'menu_icon'           => $config['icon'],
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
            ]);
        }
    }

    public function registerTaxonomies(): void
    {
        // --- Helmet Type ---
        register_taxonomy('helmet_type', ['helmet', 'brand', 'accessory'], [
            'label'             => 'Helmet Types',
            'labels'            => $this->taxonomyLabels('Helmet Type', 'Helmet Types'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'helmet-type', 'with_front' => false],
        ]);

        // --- Region ---
        register_taxonomy('region', [
            'helmet', 'brand', 'dealer', 'distributor', 'accessory',
            'motorcycle', 'safety_standard', 'comparison', 'recommendation',
        ], [
            'label'             => 'Regions',
            'labels'            => $this->taxonomyLabels('Region', 'Regions'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'region', 'with_front' => false],
        ]);

        // --- Certification ---
        register_taxonomy('certification', ['helmet', 'safety_standard'], [
            'label'             => 'Certifications',
            'labels'            => $this->taxonomyLabels('Certification', 'Certifications'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'certification', 'with_front' => false],
        ]);

        // --- Feature Tag ---
        register_taxonomy('feature_tag', ['helmet', 'accessory', 'technology'], [
            'label'             => 'Feature Tags',
            'labels'            => $this->taxonomyLabels('Feature Tag', 'Feature Tags'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'feature', 'with_front' => false],
        ]);

        // --- Accessory Category ---
        register_taxonomy('accessory_category', ['accessory'], [
            'label'             => 'Accessory Categories',
            'labels'            => $this->taxonomyLabels('Accessory Category', 'Accessory Categories'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'accessory-category', 'with_front' => false],
        ]);

        // --- Brand (taxonomy) ---
        // Enables WP_Query filtering by brand slug and REST API ?brand= param.
        // The `rel_brand` post meta is kept for backward compatibility.
        // Renamed internal key to 'helmet_brand' to avoid query_var conflict with 'brand' CPT.
        register_taxonomy('helmet_brand', ['helmet'], [
            'label'             => 'Brands',
            'labels'            => $this->taxonomyLabels('Brand', 'Brands'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'brand', 'with_front' => false],
        ]);

        // --- Use Case ---
        // Tags like "commuting", "track day", "touring", "adventure touring"
        register_taxonomy('use_case', ['helmet', 'accessory'], [
            'label'             => 'Use Cases',
            'labels'            => $this->taxonomyLabels('Use Case', 'Use Cases'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'use-case', 'with_front' => false],
        ]);

        // --- Price Range ---
        // Buckets: budget, mid-range, premium, ultra-premium
        register_taxonomy('price_range', ['helmet', 'accessory'], [
            'label'             => 'Price Range',
            'labels'            => $this->taxonomyLabels('Price Range', 'Price Ranges'),
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'price-range', 'with_front' => false],
        ]);
    }

    /**
     * Generate a standard labels array for a taxonomy.
     *
     * @return array<string,string>
     */
    private function taxonomyLabels(string $singular, string $plural): array
    {
        return [
            'name'              => $plural,
            'singular_name'     => $singular,
            'search_items'      => 'Search ' . $plural,
            'all_items'         => 'All ' . $plural,
            'parent_item'       => 'Parent ' . $singular,
            'parent_item_colon' => 'Parent ' . $singular . ':',
            'edit_item'         => 'Edit ' . $singular,
            'update_item'       => 'Update ' . $singular,
            'add_new_item'      => 'Add New ' . $singular,
            'new_item_name'     => 'New ' . $singular . ' Name',
            'menu_name'         => $plural,
        ];
    }
}
