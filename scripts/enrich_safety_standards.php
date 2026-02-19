<?php
/**
 * Enrich Safety Standards with technical data and official links.
 * Run via: wp eval-file enrich_safety_standards.php --allow-root
 */

$standards = [
    'ece-22-06' => [
        'official_url' => 'https://unece.org/transport/vehicle-regulations',
        'testing_info' => '<h3>Technical Testing Procedures</h3><ul><li><strong>Rotational Impact:</strong> Measures brain rotation forces using 12 sensors.</li><li><strong>Multi-Speed Impact:</strong> Tested at low, medium, and high speeds (up to 8.2 m/s).</li><li><strong>Steel Ball Visor Test:</strong> 6mm steel ball fired at 80 m/s (180 mph).</li><li><strong>Intercom Test:</strong> Official accessories must not compromise shell integrity.</li></ul>',
        'doc_links_json' => json_encode([
            ['label' => 'Full Regulation Document (PDF)', 'url' => 'https://unece.org/fileadmin/DAM/trans/main/wp29/wp29regs/2020/R022r6e.pdf'],
            ['label' => 'Testing Manual', 'url' => 'https://unece.org/transport/vehicle-regulations/rev-6-un-regulation-no-22']
        ]),
        'story_history' => 'The ECE 22.06 standard is the most significant update to European helmet safety in 20 years. Evolved from 22.05, it focuses heavily on modern accident data, specifically addressing brain shear injuries caused by rotation.',
        'cert_slug' => 'ece-22-06'
    ],
    'dot-fmvss-218' => [
        'official_url' => 'https://www.nhtsa.gov/equipment/motorcycle-helmets',
        'testing_info' => '<h3>US DOT Standards</h3><ul><li><strong>Impact Attenuation:</strong> Drop tests onto flat and hemispherical anvils.</li><li><strong>Penetration Resistance:</strong> 6lb lead-weighted striker dropped from 10ft.</li><li><strong>Retention:</strong> Straps must resist 300lbs of force without breaking.</li></ul>',
        'doc_links_json' => json_encode([
            ['label' => 'NHTSA Safety Lab Manual', 'url' => 'https://www.nhtsa.gov/sites/nhtsa.gov/files/documents/tp-218-07_0.pdf'],
            ['label' => 'Official Regulation Text', 'url' => 'https://www.govinfo.gov/content/pkg/CFR-2011-title49-vol6/pdf/CFR-2011-title49-vol6-sec571-218.pdf']
        ]),
        'story_history' => 'Introduced by the US Department of Transportation, FMVSS 218 is the mandatory legal requirement for all helmets used on US public roads. It prioritizes energy absorption and penetration resistance.',
        'cert_slug' => 'dot-fmvss-218'
    ],
    'snell-m2020r' => [
        'official_url' => 'https://smf.org/',
        'testing_info' => '<h3>Racing Performance Tests</h3><ul><li><strong>Edge Anvil Impact:</strong> Specialized tests against sharp curbs and tracks.</li><li><strong>Positional Stability:</strong> Rigorous roll-off tests in multiple directions.</li><li><strong>Double Impact:</strong> Two hits in the same site to ensure shell resilience.</li></ul>',
        'doc_links_json' => json_encode([
            ['label' => 'M2020 R/D Standard Text', 'url' => 'https://smf.org/standards/m/2020/m2020_final.html'],
            ['label' => 'Certified Product List', 'url' => 'https://smf.org/cert']
        ]),
        'story_history' => 'The Snell Memorial Foundation was founded in 1957 after the death of racer William "Pete" Snell. It is a non-profit that sets standards significantly higher than government minimums, specifically for racing environments.',
        'cert_slug' => 'snell-m2020r'
    ],
    'sharp-uk-rating' => [
        'official_url' => 'https://sharp.dft.gov.uk/',
        'testing_info' => '<h3>Consumer Safety Rating</h3><ul><li><strong>Independent Procurement:</strong> Helmets are bought from random retailers to avoid manufacturers sending "special" samples.</li><li><strong>8.5 m/s Impact:</strong> Tested at higher speeds than mandatory ECE pass/fail levels.</li><li><strong>Linear & Rotational:</strong> 32 total impact tests per helmet model.</li></ul>',
        'doc_links_json' => json_encode([
            ['label' => 'SHARP Testing Protocol', 'url' => 'https://sharp.dft.gov.uk/sharp-testing/'],
            ['label' => 'Star Rating Explained', 'url' => 'https://sharp.dft.gov.uk/star-rating-explained/']
        ]),
        'story_history' => 'Launched by the UK Department for Transport in 2007, SHARP provides a 1-5 star safety rating to help consumers compare the relative safety of helmets that all meet the legal ECE minimum.',
        'cert_slug' => 'sharp-uk-rating'
    ],
    'isi-is-4151' => [
        'official_url' => 'https://www.bis.gov.in/',
        'testing_info' => '<h3>Bureau of Indian Standards</h3><ul><li><strong>Impact Absorption:</strong> Maximum acceleration not to exceed 150g.</li><li><strong>Penetration:</strong> Tested with a 3kg striker.</li><li><strong>Retention:</strong> Static and dynamic tests for chin straps.</li></ul>',
        'doc_links_json' => json_encode([
            ['label' => 'ISI Standard Document', 'url' => 'https://www.services.bis.gov.in/php/BIS_2.0/bis_review/standards/detail/6890']
        ]),
        'story_history' => 'ISI IS 4151 is the mandatory certification for all motorcycle helmets sold in India. It was recently updated to ban non-ISI helmets to improve the safety of the world\'s largest two-wheeler market.',
        'cert_slug' => 'isi-is-4151'
    ]
];

foreach ($standards as $slug => $data) {
    $post = get_page_by_path($slug, OBJECT, 'safety_standard');
    if ($post) {
        update_post_meta($post->ID, 'official_url', $data['official_url']);
        update_post_meta($post->ID, 'testing_info', $data['testing_info']);
        update_post_meta($post->ID, 'doc_links_json', $data['doc_links_json']);
        update_post_meta($post->ID, 'story_history', $data['story_history']);
        update_post_meta($post->ID, 'linked_certification_slug', $data['cert_slug']);
        echo "Updated Standard: " . $post->post_title . "\n";
    } else {
        echo "Error: Standard with slug $slug not found.\n";
    }
}
