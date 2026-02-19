<?php
/**
 * Template Name: Comparison
 *
 * @package HelmetsanTheme
 */

get_header();

// 1. Get IDs
$ids = [];
if (isset($_GET['ids'])) {
    $raw = explode(',', $_GET['ids']);
    $ids = array_filter(array_map('intval', $raw));
}

// 2. Fetch Data
$helmets = [];
if ($ids !== []) {
    $helmets = get_posts([
        'post_type' => 'helmet',
        'post__in'  => $ids,
        'numberposts' => 4, // Limit to 4
        'orderby'   => 'post__in', // Preserve order
    ]);
}

// 3. Define Attributes Rows
// 3. Define Attributes Rows
$attributes = [
    'general_header' => ['type' => 'header', 'label' => 'General'],
    'price' => [
        'label' => 'Price',
        'callback' => function($p) { return helmetsan_get_helmet_price($p->ID); }
    ],
    'brand' => [
        'label' => 'Brand',
        'callback' => function($p) { return helmetsan_get_brand_name($p->ID); }
    ],
    'type' => [
        'label' => 'Type',
        'callback' => function($p) { 
            $terms = get_the_terms($p->ID, 'helmet_type');
            return is_array($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '-';
        }
    ],
    'family' => [
        'label' => 'Family',
        'callback' => function($p) { return get_post_meta($p->ID, 'helmet_family', true) ?: '-'; }
    ],

    'physical_header' => ['type' => 'header', 'label' => 'Physical Specs'],
    'weight' => [
        'label' => 'Weight',
        'callback' => function($p) { 
            $w = helmetsan_get_weight($p->ID);
            return $w > 0 ? $w . ' g' : '-';
        }
    ],
    'weight_lbs' => [
        'label' => 'Weight (lbs)',
        'callback' => function($p) { return get_post_meta($p->ID, 'spec_weight_lbs', true) ?: '-'; }
    ],
    'material' => [
        'label' => 'Shell Material',
        'callback' => function($p) { return helmetsan_get_shell_material($p->ID) ?: '-'; }
    ],
    'shape' => [
        'label' => 'Head Shape',
        'callback' => function($p) { 
            $s = helmetsan_get_head_shape($p->ID);
            return $s ? ucwords(str_replace('-', ' ', $s)) : '-';
        }
    ],

    'safety_header' => ['type' => 'header', 'label' => 'Safety Intelligence'],
    'certs' => [
        'label' => 'Certifications',
        'callback' => function($p) { return helmetsan_get_certifications($p->ID); }
    ],
    'homologation' => [
        'label' => 'Homologation',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'safety_intelligence_json', true);
            $data = json_decode((string)$json, true);
            return $data['homologation_standard'] ?? '-';
        }
    ],
    'sharp' => [
        'label' => 'SHARP Rating',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'safety_intelligence_json', true);
            $data = json_decode((string)$json, true);
            $stars = (int)($data['sharp_rating'] ?? 0);
            return $stars > 0 ? str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) : '-';
        }
    ],
    'rotational' => [
        'label' => 'Rotational Tech',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'safety_intelligence_json', true);
            $data = json_decode((string)$json, true);
            return $data['rotational_mitigation'] ?? '-';
        }
    ],

    'aero_header' => ['type' => 'header', 'label' => 'Aero & Comfort'],
    'noise' => [
        'label' => 'Noise @ 100kph',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'aero_acoustic_profile_json', true);
            $data = json_decode((string)$json, true);
            return isset($data['noise_db_at_100kph']) ? $data['noise_db_at_100kph'] . ' dB' : '-';
        }
    ],
    'ventilation' => [
        'label' => 'Ventilation',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'aero_acoustic_profile_json', true);
            $data = json_decode((string)$json, true);
            return isset($data['ventilation_efficiency_score']) ? $data['ventilation_efficiency_score'] . '/10' : '-';
        }
    ],

    'features_header' => ['type' => 'header', 'label' => 'Features'],
    'warranty' => [
        'label' => 'Warranty',
        'callback' => function($p) { 
            $y = get_post_meta($p->ID, 'warranty_years', true);
            return $y ? $y . ' Years' : '-';
        }
    ],
    'strap' => [
        'label' => 'Strap Type',
        'callback' => function($p) { return get_post_meta($p->ID, 'strap_type', true) ?: '-'; }
    ],
    'visor' => [
        'label' => 'Visor Features',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'visor_features_json', true);
            $data = json_decode((string)$json, true);
            return is_array($data) ? implode(', ', $data) : '-';
        }
    ],
    'liner' => [
        'label' => 'Liner Features',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'liner_features_json', true);
            $data = json_decode((string)$json, true);
            return is_array($data) ? implode(', ', $data) : '-';
        }
    ],
    'comms' => [
        'label' => 'Comms Ready',
        'callback' => function($p) {
            $json = get_post_meta($p->ID, 'tech_integration_json', true);
            $data = json_decode((string)$json, true);
            return $data['comms_cutout_type'] ?? '-';
        }
    ],
    'tags' => [
        'label' => 'Key Features',
        'callback' => function($p) { 
            $terms = get_the_terms($p->ID, 'feature_tag');
            return is_array($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '-';
        }
    ],
];

?>

<div class="hs-section hs-section--comparison">
    <div class="hs-container">
        <h1>Helmet Comparison</h1>
        
        <?php if (empty($helmets)): ?>
            <p>No helmets selected to compare. <a href="/helmets/">Browse Helmets</a></p>
        <?php else: ?>
            <div class="hs-comparison-table-wrap">
                <table class="hs-comparison-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <?php foreach ($helmets as $helmet): 
                                $logo = helmetsan_get_logo_url($helmet->ID);
                            ?>
                                <th class="hs-comp-header">
                                    <div class="hs-comp-img">
                                        <?php echo get_the_post_thumbnail($helmet->ID, 'medium'); ?>
                                    </div>
                                    <div class="hs-comp-title">
                                        <a href="<?php echo get_permalink($helmet->ID); ?>">
                                            <?php echo esc_html($helmet->post_title); ?>
                                        </a>
                                    </div>
                                    <button class="hs-btn hs-btn--sm hs-btn--ghost js-add-to-compare is-active" data-id="<?php echo $helmet->ID; ?>">Remove</button>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attributes as $key => $attr): ?>
                            <?php if (isset($attr['type']) && $attr['type'] === 'header'): ?>
                                <tr class="hs-comp-section-header">
                                    <th colspan="<?php echo count($helmets) + 1; ?>"><?php echo esc_html($attr['label']); ?></th>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td class="hs-comp-label"><?php echo esc_html($attr['label']); ?></td>
                                    <?php foreach ($helmets as $helmet): ?>
                                        <td class="hs-comp-value">
                                            <?php echo esc_html($attr['callback']($helmet)); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <p class="hs-text-center">
                <br>
                <button id="hs-comparison-clear" class="hs-btn hs-btn--ghost">Clear All</button>
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
.hs-section--comparison { padding: 4rem 1rem; }
.hs-comparison-table-wrap { overflow-x: auto; }
.hs-comparison-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.hs-comparison-table th, .hs-comparison-table td { 
    padding: 1rem; 
    border: 1px solid var(--hs-border, #eee); 
    text-align: left;
    vertical-align: top;
}
.hs-comparison-table th { background: #f9f9f9; }
.hs-comp-label { font-weight: 600; width: 150px; background: #fafafa; }
.hs-comp-img img { max-width: 100%; height: auto; max-height: 150px; object-fit: contain; }
.hs-comp-title { margin: 0.5rem 0; font-weight: 700; font-size: 1.1rem; }
.hs-comp-title a { text-decoration: none; color: inherit; }
</style>

<?php
get_footer();
