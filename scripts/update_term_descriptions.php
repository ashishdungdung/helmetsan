<?php
/**
 * Update Helmet Type Term Descriptions
 */

require_once 'wp-load.php';

$descriptions = [
    'full-face'            => 'Maximum protection with a chin bar and visor. Ideal for road and track.',
    'modular'              => 'Combines full-face protection with the convenience of an open-face flip-up chin bar.',
    'open-face'            => 'Classic 3/4 style offering visibility and airflow. Best for cruising and city riding.',
    'half'                 => 'Lightweight and minimal, providing the most airflow. Essential for local cruises.',
    'dirt-mx'              => 'Lightweight with aggressive ventilation and a peak. Designed for off-road performance.',
    'adventure-dual-sport' => 'Versatile hybrid for on and off-road use with a peak and face shield.',
    'touring'              => 'Optimized for long-distance comfort, low noise, and integrated sun visors.',
    'track-race'           => 'Ultra-aerodynamic and lightweight for high-speed performance and safety.',
    'youth'                => 'Specifically sized and weighted for younger riders to ensure safety and comfort.',
    'snow'                 => 'Dual-pane lenses and breath boxes to prevent fogging in cold environments.',
    'carbon-fiber'         => 'Premium, lightweight construction for reduced neck strain and maximum strength.',
    'graphics'             => 'Helmets featuring unique artwork, replica designs, and high-visibility patterns.',
    'sale'                 => 'Current manufacturer-authorized discounts on premium helmet models.',
];

foreach ($descriptions as $slug => $desc) {
    $term = get_term_by('slug', $slug, 'helmet_type');
    if ($term) {
        wp_update_term($term->term_id, 'helmet_type', [
            'description' => $desc
        ]);
        echo "Updated $slug\n";
    } else {
        echo "Term $slug not found\n";
    }
}
