<?php
/**
 * Global Metadata Enrichment Script
 */

require_once 'wp-load.php';

$taxonomy_updates = [
    'accessory_category' => [
        'visors-shields' => 'Premium optical-grade shields, anti-fog inserts, and adaptive tinting solutions.',
        'communications' => 'Integrated and universal Bluetooth systems, mesh intercoms, and high-fidelity audio.',
        'maintenance-care' => 'Specialized cleaners, anti-microbial treatments, and protective wax for shell longevity.',
    ],
    'certification' => [
        'ece-22-06' => 'The latest European safety standard featuring rigorous multi-point impact and rotational testing.',
        'snell-m2020' => 'The gold standard for track performance, exceeding federal requirements for high-energy impacts.',
        'dot-fmvss-218' => 'The essential US federal safety requirement ensuring basic penetration and retention safety.',
    ],
    'region' => [
        'north-america' => 'Vast highways and rugged mountain passes catering to long-distance touring and ADV riding.',
        'europe' => 'The spiritual home of sport riding with alpine curves and historic track circuits.',
        'asia-pacific' => 'Dense urban landscapes and diverse tropical environments requiring high-ventilation gear.',
    ],
    'feature_tag' => [
        'emergency-release' => 'Medical-safety cheek pads that can be removed quickly by first responders in an accident.',
        'carbon-shell' => 'Aero-grade carbon fiber construction for maximum structural integrity at the lowest possible weight.',
        'mips-equipped' => 'Slip-plane technology inside the helmet designed to reduce rotational motion to the brain.',
    ]
];

foreach ($taxonomy_updates as $tax => $terms) {
    foreach ($terms as $slug => $desc) {
        $term = get_term_by('slug', $slug, $tax);
        if ($term) {
            wp_update_term($term->term_id, $tax, ['description' => $desc]);
            echo "Updated $tax: $slug\n";
        }
    }
}

$post_updates = [
    'safety_standard' => [
        'ece-standard' => 'A deep dive into the ECE 22.06 protocol—the world\'s most widely recognized safety certification.',
        'snell-memorial' => 'Understanding the non-profit foundation\'s mission to exceed global racing safety standards.',
    ],
    'technology' => [
        'mips-explained' => 'How Multi-directional Impact Protection Systems save lives through rotational management.',
        'advanced-comms' => 'Exploring the evolution from simple Bluetooth to self-healing Mesh communication networks.',
    ]
];

foreach ($post_updates as $type => $posts) {
    foreach ($posts as $slug => $desc) {
        $post = get_page_by_path($slug, OBJECT, $type);
        if ($post) {
            wp_update_post(['ID' => $post->ID, 'post_excerpt' => $desc]);
            echo "Updated $type: $slug\n";
        }
    }
}
echo "Metadata enrichment complete.\n";
