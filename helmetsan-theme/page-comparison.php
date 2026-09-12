<?php
/**
 * Template Name: Comparison
 *
 * @package HelmetsanTheme
 */

// 1. Fetch Helmets by numeric ID or post_name slug
$helmets = [];
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $raw = array_filter(array_map('trim', explode(',', sanitize_text_field($_GET['ids']))));
    
    // Try numeric IDs
    $numericIds = array_filter(array_map('intval', $raw));
    if ($numericIds !== []) {
        $helmets = get_posts([
            'post_type'   => 'helmet',
            'post__in'    => $numericIds,
            'numberposts' => 4,
            'orderby'     => 'post__in',
        ]);
    }

    // Try post_name slugs if numeric IDs returned nothing
    if ($helmets === [] && $raw !== []) {
        $helmets = get_posts([
            'post_type'     => 'helmet',
            'post_name__in' => $raw,
            'numberposts'   => 4,
        ]);
    }
}

// 2. Set dynamic title, meta description, and AI Markdown discovery before get_header()
if (count($helmets) >= 2) {
    $comparedNames = array_map(static fn($h) => get_the_title($h), $helmets);
    $vsTitle = implode(' vs ', $comparedNames);

    add_filter('document_title_parts', static function (array $parts) use ($vsTitle): array {
        $parts['title'] = "{$vsTitle} — Weight, Noise, Safety & Price Comparison";
        $parts['site'] = 'Helmetsan';
        return $parts;
    }, 20);

    add_filter('pre_get_document_title', static function () use ($vsTitle): string {
        return "{$vsTitle} — Weight, Noise, Safety & Price Comparison | Helmetsan";
    }, 20);

    add_action('wp_head', static function () use ($helmets, $vsTitle): void {
        $first = $helmets[0];
        $second = $helmets[1];
        $w1 = get_post_meta($first->ID, 'spec_weight_g', true) ?: get_post_meta($first->ID, 'weight_g', true);
        $w2 = get_post_meta($second->ID, 'spec_weight_g', true) ?: get_post_meta($second->ID, 'weight_g', true);
        $n1 = get_post_meta($first->ID, 'noise_db_at_100kph', true) ?: get_post_meta($first->ID, 'spec_noise_db', true);
        $n2 = get_post_meta($second->ID, 'noise_db_at_100kph', true) ?: get_post_meta($second->ID, 'spec_noise_db', true);

        $specParts = [];
        if ($w1 && $w2) {
            $specParts[] = "weights ({$w1}g vs {$w2}g)";
        }
        if ($n1 && $n2) {
            $specParts[] = "noise levels ({$n1}dB vs {$n2}dB)";
        }
        $specDesc = ! empty($specParts) ? ' Compare ' . implode(' and ', $specParts) . '.' : '';

        $desc = "Side-by-side technical comparison of {$vsTitle}.{$specDesc} Which one is safer, lighter, and quieter? Verified specs by Helmetsan.";
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";

        $mdUrl = add_query_arg('format', 'md');
        echo '<link rel="alternate" type="text/markdown" href="' . esc_url($mdUrl) . '" title="Structured Comparison (Markdown)">' . "\n";
    }, 1);
} else {
    add_action('wp_head', static function (): void {
        $mdUrl = add_query_arg('format', 'md');
        echo '<link rel="alternate" type="text/markdown" href="' . esc_url($mdUrl) . '" title="Comparison Matrix (Markdown)">' . "\n";
    }, 1);
}

get_header();

if (count($helmets) >= 2) {
    $itemElements = [];
    $names = [];
    foreach ($helmets as $idx => $h) {
        $names[] = get_the_title($h);
        $tech = function_exists('helmetsan_get_technical_profile') ? helmetsan_get_technical_profile($h->ID) : [];
        $weight = $tech['weight_g'] ?? get_post_meta($h->ID, 'spec_weight_g', true);
        $homologation = $tech['homologation_standard'] ?? get_post_meta($h->ID, 'homologation_standard', true);
        $price = get_post_meta($h->ID, 'price_retail_usd', true);

        $prodSchema = [
            '@type' => 'Product',
            'name'  => get_the_title($h),
            'url'   => get_permalink($h),
            'image' => get_the_post_thumbnail_url($h, 'large') ?: '',
        ];
        if (!empty($price) && is_numeric((string)$price)) {
            $prodSchema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (float)$price,
                'priceCurrency' => 'USD',
                'availability'  => 'https://schema.org/InStock',
            ];
        }
        $additional = [];
        if (!empty($homologation)) {
            $additional[] = ['@type' => 'PropertyValue', 'name' => 'Safety Homologation', 'value' => (string)$homologation];
        }
        if (!empty($weight) && is_numeric((string)$weight)) {
            $additional[] = ['@type' => 'PropertyValue', 'name' => 'Weight', 'value' => (int)$weight . ' g'];
        }
        if (!empty($tech['noise_db_at_100kph'])) {
            $additional[] = ['@type' => 'PropertyValue', 'name' => 'Noise Level @ 100km/h', 'value' => (string)$tech['noise_db_at_100kph'] . ' dB'];
        }
        if ($additional !== []) {
            $prodSchema['additionalProperty'] = $additional;
        }

        $itemElements[] = [
            '@type'    => 'ListItem',
            'position' => $idx + 1,
            'item'     => $prodSchema,
        ];
    }

    $comparisonSchema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => implode(' vs ', $names) . ' — Motorcycle Helmet Comparison',
        'description'     => 'Direct head-to-head comparison of motorcycle helmet specifications, verified weights, decibel noise levels, and safety ratings on Helmetsan.',
        'numberOfItems'   => count($itemElements),
        'itemListElement' => $itemElements,
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($comparisonSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>' . "\n";
}
?>
<script>
// Auto-redirect to ?ids=... if localStorage has items but URL parameter is missing
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.get('ids')) {
        try {
            const list = JSON.parse(localStorage.getItem('helmetsan_compare_list')) || [];
            if (list.length > 0) {
                const ids = list.map(item => item.slug || item.id).join(',');
                window.location.href = window.location.pathname + '?ids=' + encodeURIComponent(ids);
            }
        } catch(e) {}
    }
});
</script>
<?php
// 3. Define Attributes Rows
$attributes = [
    'score_header' => ['type' => 'header', 'label' => 'Helmetsan Intelligence Score'],
    'score' => [
        'label' => 'Helmetsan Score',
        'callback' => function($p) {
            $scoreObj = function_exists('helmetsan_calculate_score') ? helmetsan_calculate_score([
                'specs' => ['weight_g' => (int)get_post_meta($p->ID, 'spec_weight_g', true)],
                'safety_intelligence' => ['homologation_standard' => (string)get_post_meta($p->ID, 'certifications', true)],
                'price' => ['current' => 350]
            ]) : ['overall' => 92];
            $score = $scoreObj['overall'];
            $color = ($score >= 90) ? 'hs-chip-success' : 'hs-chip-neutral';
            return '<div class="hs-flex hs-items-center hs-gap-2"><span class="hs-chip ' . $color . ' hs-font-black hs-text-base">' . $score . ' / 100</span></div>';
        },
        'is_html' => true
    ],

    'general_header' => ['type' => 'header', 'label' => 'General'],

    'price' => [
        'label' => 'Price',
        'callback' => function($p) { return helmetsan_render_price_element($p->ID, 'hs-comparison-price'); },
        'is_html' => true,
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

    'safety_header' => ['type' => 'header', 'label' => 'Safety & Technical'],
    'certs' => [
        'label' => 'Certifications',
        'callback' => function($p) { return helmetsan_get_certifications($p->ID); }
    ],
    'homologation' => [
        'label' => 'Homologation',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['homologation']; }
    ],
    'sharp' => [
        'label' => 'SHARP Rating',
        'callback' => function($p) {
            $rating = helmetsan_get_technical_profile($p->ID)['sharp_rating'];
            if (!$rating) return '—';
            return '<div class="hs-rating" aria-label="' . esc_attr($rating) . ' stars">' . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . '</div>';
        },
        'is_html' => true
    ],
    'rotational' => [
        'label' => 'Rotational Tech',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['rotational_tech']; }
    ],
    'weight' => [
        'label' => 'Weight',
        'callback' => function($p) { 
            $w = helmetsan_get_technical_profile($p->ID)['weight'];
            return $w > 0 ? $w . 'g' : '-';
        }
    ],
    'material' => [
        'label' => 'Shell Material',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['shell']; }
    ],
    'shape' => [
        'label' => 'Head Shape',
        'callback' => function($p) { 
            $s = helmetsan_get_technical_profile($p->ID)['head_shape'];
            return $s ? ucwords(str_replace('-', ' ', $s)) : '-';
        }
    ],
    'noise' => [
        'label' => 'Noise @ 100kph',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['noise_db']; }
    ],
    'ventilation' => [
        'label' => 'Ventilation',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['ventilation_score']; }
    ],

    'features_header' => ['type' => 'header', 'label' => 'Features & Comfort'],
    'warranty' => [
        'label' => 'Warranty',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['warranty']; }
    ],
    'strap' => [
        'label' => 'Strap Type',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['strap_type']; }
    ],
    'visor' => [
        'label' => 'Visor Features',
        'callback' => function($p) {
            $features = helmetsan_get_technical_profile($p->ID)['visor_features'];
            return !empty($features) ? '<ul class="hs-list-compact"><li>' . implode('</li><li>', array_map('esc_html', array_slice($features, 0, 5))) . '</li></ul>' : '-';
        },
        'is_html' => true
    ],
    'liner' => [
        'label' => 'Liner Features',
        'callback' => function($p) {
            $features = helmetsan_get_technical_profile($p->ID)['liner_features'];
            return !empty($features) ? '<ul class="hs-list-compact"><li>' . implode('</li><li>', array_map('esc_html', array_slice($features, 0, 5))) . '</li></ul>' : '-';
        },
        'is_html' => true
    ],
    'comms' => [
        'label' => 'Comms Ready',
        'callback' => function($p) { return helmetsan_get_technical_profile($p->ID)['comms_ready']; }
    ],
    'key_features' => [
        'label' => 'Key Features',
        'callback' => function($p) { return helmetsan_get_helmet_key_features_html($p->ID); },
        'is_html' => true
    ],
];

?>

<?php
$helmets_link = get_post_type_archive_link('helmet');
if (! $helmets_link) {
    $helmets_link = home_url('/helmets/');
}
$comparison_link = home_url('/comparison/');
?>
<div class="hs-section hs-section--comparison">
    <div class="hs-container">
        <header class="hs-comparison-hero">
            <h1>Helmet Comparison</h1>
            <p class="hs-comparison-hero__lead">Compare specs, certifications, weight, and price side by side. Add up to four helmets from the catalog, then use this page to see differences at a glance and choose the right one.</p>
        </header>

        <?php if (empty($helmets)): ?>
            <div class="hs-panel hs-comparison-empty">
                <p><strong>No helmets selected yet.</strong> Browse the catalog and click “Compare” on any helmet to add it here. You can compare up to four helmets at once.</p>
                <a href="<?php echo esc_url($helmets_link); ?>" class="hs-btn hs-btn--primary">Browse helmets</a>
            </div>
        <?php else: 
            $helmet_ids = array_map(static fn($p) => $p->ID, $helmets);
            $helmet_titles = array_combine($helmet_ids, array_map(static fn($p) => $p->post_title, $helmets));
            ?>
            <script>
                window.helmetsanComparisonIds = <?php echo wp_json_encode(array_values($helmet_ids)); ?>;
                window.helmetsanComparisonTitles = <?php echo wp_json_encode($helmet_titles); ?>;
                <?php if (count($helmets) === 2): ?>
                window.helmetsanShareableUrl = <?php echo wp_json_encode(home_url('/vs/' . $helmets[0]->post_name . '-vs-' . $helmets[1]->post_name . '/')); ?>;
                <?php endif; ?>
            </script>
            <p class="hs-comp-toolbar">
                <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost" id="hs-comp-toggle-empty" aria-pressed="false">Show empty fields</button>
                <button type="button" class="hs-btn hs-btn--ghost js-comparison-clear">Clear All</button>
                <button type="button" class="hs-btn hs-btn--sm hs-btn--primary js-share-comparison" id="hs-comp-share" title="Copy link to clipboard">
                    Share this comparison
                </button>
            </p>
            <div class="hs-comparison-table-wrap">
                <table class="hs-comparison-table" id="hs-comparison-table">
                    <thead>
                        <tr>
                            <th scope="col" id="col-feature" class="hs-comp-label-col">Feature</th>
                            <?php foreach ($helmets as $helmet): 
                                $colId = 'col-helmet-' . $helmet->ID;
                                ?>
                                <th scope="col" id="<?php echo esc_attr($colId); ?>" class="hs-comp-header">
                                    <div class="hs-comp-img">
                                        <?php if (has_post_thumbnail($helmet->ID)) : ?>
                                            <?php echo get_the_post_thumbnail($helmet->ID, 'medium', ['alt' => sprintf(__('Photo of %s', 'helmetsan-theme'), $helmet->post_title)]); ?>
                                        <?php else : ?>
                                            <span class="hs-comp-img-placeholder" aria-hidden="true">—</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hs-comp-title">
                                        <a href="<?php echo esc_url(get_permalink($helmet->ID)); ?>">
                                            <?php echo esc_html($helmet->post_title); ?>
                                        </a>
                                    </div>
                                    <div class="hs-comp-header-actions">
                                        <a href="<?php echo esc_url(get_permalink($helmet->ID)); ?>" class="hs-btn hs-btn--sm hs-btn--ghost" aria-label="<?php echo esc_attr(sprintf(__('View details for %s', 'helmetsan-theme'), $helmet->post_title)); ?>">View</a>
                                        <?php
                                        $slug = get_post_field('post_name', $helmet->ID);
                                        $go_url = $slug ? home_url('/go/' . $slug . '/?source=comparison') : get_permalink($helmet->ID);
                                        ?>
                                        <a href="<?php echo esc_url($go_url); ?>" class="hs-btn hs-btn--sm hs-btn--primary" rel="nofollow sponsored" aria-label="<?php echo esc_attr(sprintf(__('Check price for %s', 'helmetsan-theme'), $helmet->post_title)); ?>">Check price</a>
                                        <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-add-to-compare is-active" 
                                                data-id="<?php echo (int) $helmet->ID; ?>" 
                                                aria-label="<?php echo esc_attr(sprintf(__('Remove %s from comparison', 'helmetsan-theme'), $helmet->post_title)); ?>">Remove</button>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attributes as $key => $attr): 
                            $rowId = 'row-' . sanitize_title($key);
                            ?>
                            <?php if (isset($attr['type']) && $attr['type'] === 'header'): ?>
                                <tr class="hs-comp-section-header">
                                    <th scope="colgroup" id="<?php echo esc_attr($rowId); ?>" colspan="<?php echo count($helmets) + 1; ?>"><?php echo esc_html($attr['label']); ?></th>
                                </tr>
                            <?php else: 
                                $values = array_map($attr['callback'], $helmets);
                                $is_html = isset($attr['is_html']) && $attr['is_html'];
                                $all_empty = count(array_filter($values, static function($v) {
                                    $v = trim(strip_tags((string)$v));
                                    return $v !== '' && $v !== '-' && $v !== '—' && $v !== 'N/A';
                                })) === 0;
                                $row_class = $all_empty ? 'hs-comp-row--empty' : '';
                            ?>
                                <tr class="<?php echo esc_attr($row_class); ?>" <?php echo $all_empty ? ' data-empty="1"' : ''; ?>>
                                    <th scope="row" id="<?php echo esc_attr($rowId); ?>" class="hs-comp-label"><?php echo esc_html($attr['label']); ?></th>
                                    <?php foreach ($helmets as $index => $h): 
                                        $colId = 'col-helmet-' . $h->ID;
                                        $val = $values[$index];
                                    ?>
                                        <td class="hs-comp-value <?php echo $is_html ? 'hs-comp-value--html' : ''; ?>" headers="<?php echo esc_attr($colId . ' ' . $rowId); ?>">
                                            <?php echo $is_html ? $val : esc_html((string)$val); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="hs-comparison-cta hs-panel">
                <p class="hs-comparison-cta__lead">Share this comparison or add more helmets to compare.</p>
                <div class="hs-comp-toolbar-bottom">
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost" id="hs-comp-toggle-empty-bottom" aria-pressed="false">Show empty fields</button>
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--primary js-share-comparison" id="hs-comp-share-bottom" title="Copy link to clipboard">Share link</button>
                    <a href="<?php echo esc_url($helmets_link); ?>" class="hs-btn hs-btn--sm hs-btn--primary">Add more helmets</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var table = document.getElementById('hs-comparison-table');
    function setToggle(pressed) {
        if (table) table.classList.toggle('show-empty-rows', !!pressed);
        document.querySelectorAll('#hs-comp-toggle-empty, #hs-comp-toggle-empty-bottom').forEach(function(btn) {
            if (btn) { btn.setAttribute('aria-pressed', pressed ? 'true' : 'false'); btn.textContent = pressed ? 'Hide empty fields' : 'Show empty fields'; }
        });
    }
    document.getElementById('hs-comp-toggle-empty') && document.getElementById('hs-comp-toggle-empty').addEventListener('click', function() { setToggle(this.getAttribute('aria-pressed') !== 'true'); });
    document.getElementById('hs-comp-toggle-empty-bottom') && document.getElementById('hs-comp-toggle-empty-bottom').addEventListener('click', function() { setToggle(this.getAttribute('aria-pressed') !== 'true'); });

    function shareComparison(btn) {
        var url = window.helmetsanShareableUrl || window.location.href;
        if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                var orig = btn.textContent;
                btn.textContent = 'Link copied!';
                btn.disabled = true;
                setTimeout(function() { btn.textContent = orig; btn.disabled = false; }, 2000);
            }).catch(function() { btn.textContent = 'Copy link'; });
        } else {
            var input = document.createElement('input');
            input.value = url;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed'; input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand('copy');
                var orig = btn.textContent;
                btn.textContent = 'Link copied!';
                btn.disabled = true;
                setTimeout(function() { btn.textContent = orig; btn.disabled = false; document.body.removeChild(input); }, 2000);
            } catch (e) { document.body.removeChild(input); }
        }
    }
    document.querySelectorAll('.js-share-comparison').forEach(function(btn) {
        btn.addEventListener('click', function() { shareComparison(this); });
    });
})();
</script>

<?php
get_footer();
