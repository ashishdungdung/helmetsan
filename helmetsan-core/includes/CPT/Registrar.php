<?php

declare(strict_types=1);

namespace Helmetsan\Core\CPT;

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
            'helmet'          => 'Helmets',
            'brand'           => 'Brands',
            'accessory'       => 'Accessories',
            'motorcycle'      => 'Motorcycles',
            'safety_standard' => 'Safety Standards',
            'dealer'          => 'Dealers',
            'distributor'     => 'Distributors',
            'technology'      => 'Technologies',
            'comparison'      => 'Comparisons',
        ];

        foreach ($types as $slug => $label) {
            register_post_type($slug, [
                'label'           => $label,
                'public'          => true,
                'show_in_rest'    => true,
                'supports'        => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
                'has_archive'     => true,
                'rewrite'         => ['slug' => $slug],
                'menu_position'   => 20,
                'menu_icon'       => 'dashicons-database',
            ]);
        }
    }

    public function registerTaxonomies(): void
    {
        register_taxonomy('helmet_type', ['helmet'], [
            'label'        => 'Helmet Types',
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]);

        register_taxonomy('region', ['helmet', 'brand', 'dealer', 'distributor'], [
            'label'        => 'Regions',
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]);

        register_taxonomy('certification', ['helmet', 'safety_standard'], [
            'label'        => 'Certifications',
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]);

        register_taxonomy('feature_tag', ['helmet', 'accessory', 'technology'], [
            'label'        => 'Feature Tags',
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => false,
        ]);
    }
}
