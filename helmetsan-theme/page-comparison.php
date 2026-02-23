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
    'key_features' => [
        'label' => 'Key Features',
        'html_callback' => function($p) { return helmetsan_get_helmet_key_features_html($p->ID); }
    ],
];

?>

<div class="hs-section hs-section--comparison">
    <div class="hs-container">
        <h1>Helmet Comparison</h1>
        
        <?php if (empty($helmets)): ?>
            <p>No helmets selected to compare. <a href="/helmets/">Browse Helmets</a></p>
        <?php else: 
            $helmet_ids = array_map(static fn($p) => $p->ID, $helmets);
            $helmet_titles = array_combine($helmet_ids, array_map(static fn($p) => $p->post_title, $helmets));
            ?>
            <script>
                window.helmetsanComparisonIds = <?php echo wp_json_encode(array_values($helmet_ids)); ?>;
                window.helmetsanComparisonTitles = <?php echo wp_json_encode($helmet_titles); ?>;
            </script>
            <p class="hs-comp-toolbar">
                <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost" id="hs-comp-toggle-empty" aria-pressed="false">Show empty fields</button>
                <button id="hs-comparison-clear" class="hs-btn hs-btn--ghost">Clear All</button>
                <button type="button" class="hs-btn hs-btn--sm hs-btn--primary js-share-comparison" id="hs-comp-share" title="Copy link to clipboard">
                    Share this comparison
                </button>
            </p>
            <div class="hs-comparison-table-wrap">
                <table class="hs-comparison-table" id="hs-comparison-table">
                    <thead>
                        <tr>
                            <th class="hs-comp-label-col">Feature</th>
                            <?php foreach ($helmets as $helmet): ?>
                                <th class="hs-comp-header">
                                    <div class="hs-comp-img">
                                        <?php if (get_the_post_thumbnail($helmet->ID, 'medium')) : ?>
                                            <?php echo get_the_post_thumbnail($helmet->ID, 'medium'); ?>
                                        <?php else : ?>
                                            <span class="hs-comp-img-placeholder" aria-hidden="true">—</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hs-comp-title">
                                        <a href="<?php echo esc_url(get_permalink($helmet->ID)); ?>">
                                            <?php echo esc_html($helmet->post_title); ?>
                                        </a>
                                    </div>
                                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-add-to-compare is-active" data-id="<?php echo (int) $helmet->ID; ?>">Remove</button>
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
                            <?php else: 
                                $values = isset($attr['html_callback']) 
                                    ? array_map($attr['html_callback'], $helmets) 
                                    : array_map($attr['callback'], $helmets);
                                $all_empty = !isset($attr['html_callback']) && count(array_filter($values, static fn($v) => $v !== '-' && trim((string)$v) !== '')) === 0;
                                $row_class = $all_empty ? 'hs-comp-row--empty' : '';
                            ?>
                                <tr class="<?php echo esc_attr($row_class); ?>" <?php echo $all_empty ? ' data-empty="1"' : ''; ?>>
                                    <td class="hs-comp-label"><?php echo esc_html($attr['label']); ?></td>
                                    <?php if (isset($attr['html_callback'])): ?>
                                        <?php foreach ($values as $html): ?>
                                            <td class="hs-comp-value hs-comp-value--features"><?php echo $html; ?></td>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php foreach ($values as $val): ?>
                                            <td class="hs-comp-value"><?php echo esc_html((string) $val); ?></td>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hs-text-center hs-comp-toolbar-bottom">
                <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost" id="hs-comp-toggle-empty-bottom" aria-pressed="false">Show empty fields</button>
                <button type="button" class="hs-btn hs-btn--sm hs-btn--primary js-share-comparison" id="hs-comp-share-bottom" title="Copy link to clipboard">Share this comparison</button>
                <a href="/helmets/" class="hs-btn hs-btn--sm hs-btn--primary">Compare more helmets</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
.hs-section--comparison { padding: 4rem 1rem; }
.hs-comp-toolbar { display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; }
.hs-comp-toolbar-bottom { margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.hs-comparison-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.hs-comparison-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.hs-comparison-table th, .hs-comparison-table td {
    padding: 0.75rem 1rem;
    border: 1px solid var(--hs-border, #eee);
    text-align: left;
    vertical-align: top;
}
.hs-comparison-table th { background: var(--hs-bg-muted, #f5f5f5); font-weight: 600; }
.hs-comparison-table .hs-comp-section-header th { background: var(--hs-primary, #1a1a1a); color: #fff; padding: 0.5rem 1rem; font-size: 0.9rem; }
.hs-comp-label-col { width: 140px; min-width: 140px; }
.hs-comp-label { font-weight: 600; width: 140px; min-width: 140px; background: var(--hs-bg-muted, #fafafa); }
.hs-comp-header { min-width: 160px; text-align: center; }
.hs-comp-header .hs-comp-img { margin-bottom: 0.5rem; }
.hs-comp-img { min-height: 120px; display: flex; align-items: center; justify-content: center; }
.hs-comp-img img { max-width: 100%; height: auto; max-height: 140px; object-fit: contain; }
.hs-comp-img-placeholder { color: var(--hs-muted, #999); font-size: 2rem; }
.hs-comp-title { margin: 0.5rem 0; font-weight: 700; font-size: 0.95rem; line-height: 1.3; }
.hs-comp-title a { text-decoration: none; color: inherit; }
.hs-comp-title a:hover { text-decoration: underline; }
.hs-comp-value { font-size: 0.9rem; }
.hs-comp-value--features { max-width: 280px; }
.hs-comp-feature-list { margin: 0; padding-left: 1.25rem; max-height: 8em; overflow-y: auto; list-style: disc; font-size: 0.85rem; line-height: 1.4; }
.hs-comp-feature-list li { margin-bottom: 0.25rem; }
.hs-comp-feature-more { color: var(--hs-muted, #666); font-style: italic; }
.hs-comp-feature-tags { font-size: 0.85rem; }
.hs-comp-feature-empty { color: var(--hs-muted, #999); }
.hs-comparison-table tbody tr.hs-comp-row--empty { display: none; }
.hs-comparison-table.show-empty-rows tbody tr.hs-comp-row--empty { display: table-row; }
#hs-comp-toggle-empty[aria-pressed="true"],
#hs-comp-toggle-empty-bottom[aria-pressed="true"] { font-weight: 600; }
</style>
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
        var url = window.location.href;
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
