<?php
/**
 * Helmet Accessories & Optics Hub V3 Major UX Redesign (Final Refinement Pass)
 *
 * Architecture:
 * 1. Hero ("Find the right accessories for your helmet")
 * 2. Accessory Finder ("My Helmet" Compatibility Engine)
 * 3. Explore by Purpose (5 Compact Visual Purpose Tiles with SVG Icons)
 * 4. Browse by Category (Grouped into 4 Functional Taxonomy Sections)
 * 5. Accessory Catalog (Dynamic Selection + Grouped Filters + Product Grid)
 *
 * @package HelmetsanTheme
 */

// Redirect clean URL when all query params are empty
$cleanUrl = home_url('/accessories/');
$allEmpty = true;
foreach (['s', 'brand_id', 'helmet_id', 'accessory_category', 'helmet_type', 'pinlock_ready', 'electric', 'snow', 'sort', 'purpose'] as $key) {
    if (! isset($_GET[$key])) {
        continue;
    }
    $v = $_GET[$key];
    if (is_array($v)) {
        if (array_filter(array_map('trim', $v)) !== []) {
            $allEmpty = false;
            break;
        }
    } elseif (trim((string) $v) !== '') {
        $allEmpty = false;
        break;
    }
}
if ($allEmpty && (! isset($_GET['paged']) || (int) $_GET['paged'] <= 1) && ! empty($_GET)) {
    wp_safe_redirect($cleanUrl, 302);
    exit;
}

get_header();

$pageUrl = get_post_type_archive_link('accessory');
if (! is_string($pageUrl) || $pageUrl === '') {
    $pageUrl = home_url('/accessories/');
}

$getString = static function (string $key): string {
    if (! isset($_GET[$key])) {
        return '';
    }
    $v = $_GET[$key];
    return is_array($v) ? '' : sanitize_text_field(wp_unslash((string) $v));
};
$getArray = static function (string $key): array {
    if (! isset($_GET[$key])) {
        return [];
    }
    $raw = $_GET[$key];
    if (! is_array($raw)) {
        $raw = [$raw];
    }
    $out = [];
    foreach ($raw as $item) {
        $value = sanitize_text_field(wp_unslash((string) $item));
        if ($value !== '') {
            $out[] = $value;
        }
    }
    return array_values(array_unique($out));
};

$search           = $getString('s');
$selectedBrandId  = (int) $getString('brand_id');
$selectedHelmetId = (int) $getString('helmet_id');
$selectedPurpose  = $getString('purpose');
$categories       = $getArray('accessory_category');
$helmetTypes      = $getArray('helmet_type');
$pinlock          = $getString('pinlock_ready');
$electric         = $getString('electric');
$snow             = $getString('snow');
$sort             = $getString('sort') !== '' ? $getString('sort') : 'title_asc';
$paged            = max(1, (int) ($getString('paged') !== '' ? $getString('paged') : get_query_var('paged', 1)));

$selectedHelmetObj = $selectedHelmetId > 0 ? get_post($selectedHelmetId) : null;
$selectedHelmetTitle = ($selectedHelmetObj instanceof WP_Post) ? ltrim($selectedHelmetObj->post_title, '- ') : '';

if ($selectedBrandId === 0 && $selectedHelmetObj instanceof WP_Post) {
    $selectedBrandId = (int) get_post_meta($selectedHelmetId, 'rel_brand', true);
}
$selectedBrandObj = $selectedBrandId > 0 ? get_post($selectedBrandId) : null;
$selectedBrandTitle = ($selectedBrandObj instanceof WP_Post) ? $selectedBrandObj->post_title : '';

// Fetch taxonomy terms (non-empty, ordered by count)
$categoryTerms = get_terms([
    'taxonomy'   => 'accessory_category',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
]);

// Map categories into 4 functional groups (§5: Comfort & Fit)
$functionalGroups = [
    'visibility' => [
        'title' => '👁️ Visibility & Optics',
        'desc'  => 'Anti-fog solutions, visors, pinlocks, tear-offs & optics maintenance.',
        'slugs' => ['anti-fog-solutions', 'face-shields', 'pinlock-inserts', 'tear-offs', 'visor-cleaners', 'visors-shields'],
    ],
    'comfort' => [
        'title' => '🛋️ Comfort & Fit',
        'desc'  => 'Balaclavas, replacement cheek pads, breath guards & inner liners.',
        'slugs' => ['balaclavas', 'breath-guards', 'cheek-pads', 'inner-liners', 'liners'],
    ],
    'tech' => [
        'title' => '🎧 Communication & Technology',
        'desc'  => 'Bluetooth intercoms, helmet cameras & communication accessories.',
        'slugs' => ['communications', 'helmet-cameras'],
    ],
    'care' => [
        'title' => '🧳 Protection & Care',
        'desc'  => 'Helmet transport bags, cleaners, sprays & peak visors.',
        'slugs' => ['helmet-bags', 'helmet-cleaners', 'maintenance-care', 'peak-visors'],
    ],
];

// Map purposes to category slugs
$purposeCategoryMap = [
    'fog'     => ['anti-fog-solutions', 'pinlock-inserts', 'breath-guards', 'visors-shields', 'face-shields'],
    'comms'   => ['communications', 'helmet-cameras'],
    'comfort' => ['balaclavas', 'breath-guards', 'cheek-pads', 'inner-liners', 'liners'],
    'visor'   => ['face-shields', 'pinlock-inserts', 'tear-offs', 'visor-cleaners', 'visors-shields', 'peak-visors'],
    'care'    => ['helmet-bags', 'helmet-cleaners', 'maintenance-care'],
];

// Query accessories
$queryArgs = [
    'post_type'      => 'accessory',
    'post_status'    => 'publish',
    'posts_per_page' => 24,
    'paged'          => $paged,
];

if ($search !== '') {
    $queryArgs['s'] = $search;
}

// Merge explicit categories or purpose category mappings
$activeCategories = $categories;
if ($activeCategories === [] && $selectedPurpose !== '' && isset($purposeCategoryMap[$selectedPurpose])) {
    $activeCategories = $purposeCategoryMap[$selectedPurpose];
}

$taxQuery = [];
if ($activeCategories !== []) {
    $taxQuery[] = [
        'taxonomy' => 'accessory_category',
        'field'    => 'slug',
        'terms'    => $activeCategories,
    ];
}
if ($taxQuery !== []) {
    $queryArgs['tax_query'] = $taxQuery;
}

$metaQuery = [];
if ($pinlock !== '') {
    $metaQuery[] = ['key' => 'accessory_pinlock_ready', 'value' => $pinlock === '1' ? '1' : '0'];
}
if ($electric !== '') {
    $metaQuery[] = ['key' => 'accessory_electric_compatible', 'value' => $electric === '1' ? '1' : '0'];
}
if ($snow !== '') {
    $metaQuery[] = ['key' => 'accessory_snow_compatible', 'value' => $snow === '1' ? '1' : '0'];
}

// Compatibility Engine: Filter accessories confirmed or likely compatible with selected helmet
if ($selectedHelmetObj instanceof WP_Post) {
    $brandTerms = get_the_terms($selectedHelmetId, 'brand');
    $brandName = '';
    if (is_array($brandTerms) && ! empty($brandTerms) && $brandTerms[0] instanceof WP_Term) {
        $brandName = $brandTerms[0]->name;
    }
    if ($brandName === '') {
        $brandName = (string) get_post_meta($selectedHelmetId, 'brand_name', true);
    }

    $htTerms = get_the_terms($selectedHelmetId, 'helmet_type');
    $htSlugs = [];
    if (is_array($htTerms)) {
        foreach ($htTerms as $t) {
            if ($t instanceof WP_Term) {
                $htSlugs[] = $t->slug;
                $htSlugs[] = $t->name;
            }
        }
    }
    $htSlugs = array_values(array_unique(array_filter($htSlugs)));

    $family = (string) get_post_meta($selectedHelmetId, 'helmet_family', true);
    $slug   = $selectedHelmetObj->post_name;

    $compatOr = [
        [
            'key'     => 'compatible_brands_json',
            'value'   => '"Universal"',
            'compare' => 'LIKE',
        ],
    ];

    if ($brandName !== '') {
        $compatOr[] = [
            'key'     => 'compatible_brands_json',
            'value'   => '"' . $brandName . '"',
            'compare' => 'LIKE',
        ];
    }
    if ($family !== '') {
        $compatOr[] = [
            'key'     => 'compatible_helmet_families_json',
            'value'   => '"' . $family . '"',
            'compare' => 'LIKE',
        ];
    }
    if ($slug !== '') {
        $compatOr[] = [
            'key'     => 'compatible_helmet_ids',
            'value'   => '"' . $slug . '"',
            'compare' => 'LIKE',
        ];
    }
    foreach ($htSlugs as $ht) {
        $compatOr[] = [
            'key'     => 'compatible_helmet_types_json',
            'value'   => '"' . $ht . '"',
            'compare' => 'LIKE',
        ];
    }

    $metaQuery[] = array_merge(['relation' => 'OR'], $compatOr);
} elseif ($selectedBrandId > 0 && ($selectedBrandObj instanceof WP_Post)) {
    // Brand-level compatibility: accessories compatible with this brand or Universal
    $brandName = $selectedBrandObj->post_title;
    $metaQuery[] = [
        'relation' => 'OR',
        [
            'key'     => 'compatible_brands_json',
            'value'   => '"Universal"',
            'compare' => 'LIKE',
        ],
        [
            'key'     => 'compatible_brands_json',
            'value'   => '"' . $brandName . '"',
            'compare' => 'LIKE',
        ],
    ];
}

if ($metaQuery !== []) {
    $queryArgs['meta_query'] = $metaQuery;
}

switch ($sort) {
    case 'title_desc':
        $queryArgs['orderby'] = 'title';
        $queryArgs['order']   = 'DESC';
        break;
    case 'newest':
        $queryArgs['orderby'] = 'date';
        $queryArgs['order']   = 'DESC';
        break;
    case 'title_asc':
    default:
        $queryArgs['orderby'] = 'title';
        $queryArgs['order']   = 'ASC';
        break;
}

$accessoryQuery = new WP_Query($queryArgs);
$total_accessories = $accessoryQuery->found_posts > 0 ? $accessoryQuery->found_posts : (int) (wp_count_posts('accessory')->publish ?? 0);
if ($search === '' && $selectedHelmetId === 0 && $selectedPurpose === '' && $categories === [] && $pinlock === '' && $electric === '' && $snow === '') {
    $total_accessories = $accessoryQuery->found_posts;
}
$active_cats_count = is_array($categoryTerms) ? count($categoryTerms) : 31;

// Helper to remove a single filter parameter while preserving the rest
$removeFilterUrl = static function (string $key, ?string $val = null) use ($pageUrl): string {
    $params = $_GET;
    if ($val === null) {
        unset($params[$key]);
    } elseif (isset($params[$key]) && is_array($params[$key])) {
        $params[$key] = array_values(array_filter($params[$key], static fn($v) => (string) $v !== (string) $val));
        if ($params[$key] === []) {
            unset($params[$key]);
        }
    } else {
        unset($params[$key]);
    }
    unset($params['paged']);
    return !empty($params) ? add_query_arg($params, $pageUrl) . '#accessories-catalog' : $pageUrl . '#accessories-catalog';
};

// Build active filter chips for context bar and counts
$activeChips = [];
if ($search !== '') {
    $activeChips[] = ['label' => 'Search: "' . $search . '"', 'url' => $removeFilterUrl('s')];
}
if ($selectedHelmetTitle !== '') {
    $activeChips[] = ['label' => 'Helmet: ' . $selectedHelmetTitle, 'url' => $removeFilterUrl('helmet_id')];
} elseif ($selectedBrandTitle !== '') {
    $activeChips[] = ['label' => 'Brand: ' . $selectedBrandTitle, 'url' => $removeFilterUrl('brand_id')];
}
if ($selectedPurpose !== '') {
    $activeChips[] = ['label' => 'Purpose: ' . ucfirst($selectedPurpose), 'url' => $removeFilterUrl('purpose')];
}
foreach ($categories as $catSlug) {
    $catTerm = get_term_by('slug', $catSlug, 'accessory_category');
    $activeChips[] = [
        'label' => ($catTerm instanceof WP_Term) ? $catTerm->name : ucfirst(str_replace('-', ' ', $catSlug)),
        'url'   => $removeFilterUrl('accessory_category', $catSlug),
    ];
}
if ($pinlock === '1') {
    $activeChips[] = ['label' => 'Pinlock Ready', 'url' => $removeFilterUrl('pinlock_ready')];
}
if ($electric === '1') {
    $activeChips[] = ['label' => 'Electric Compatible', 'url' => $removeFilterUrl('electric')];
}
if ($snow === '1') {
    $activeChips[] = ['label' => 'Snow Compatible', 'url' => $removeFilterUrl('snow')];
}
$activeFilterCount = count($activeChips);

// Fetch all active Brands for Step 1 of the Compatibility Finder
$brandPosts = get_posts([
    'post_type'      => 'brand',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

// If a Brand is selected, fetch that Brand's parent models for Step 2
$brandHelmets = [];
if ($selectedBrandId > 0) {
    $brandHelmets = get_posts([
        'post_type'      => 'helmet',
        'post_status'    => 'publish',
        'post_parent'    => 0,
        'posts_per_page' => 150,
        'meta_key'       => 'rel_brand',
        'meta_value'     => $selectedBrandId,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    if (is_array($brandHelmets)) {
        $brandHelmets = array_values(array_filter($brandHelmets, static function ($h) {
            if (! ($h instanceof WP_Post)) {
                return false;
            }
            $t = trim($h->post_title);
            return $t !== '' && ! preg_match('/[\x{4e00}-\x{9fa5}]/u', $t);
        }));
    }
}
if ($selectedHelmetObj instanceof WP_Post && !empty($brandHelmets)) {
    $alreadyInList = false;
    foreach ($brandHelmets as $bh) {
        if ($bh->ID === $selectedHelmetId) {
            $alreadyInList = true;
            break;
        }
    }
    if (! $alreadyInList) {
        array_unshift($brandHelmets, $selectedHelmetObj);
    }
}
?>

<section class="hs-section hs-section--archive-wide hs-accessories-hub">
    <!-- 1. HERO SECTION (§1) -->
    <header class="hs-accessories-hero">
        <div class="hs-accessories-hero__content">
            <span class="hs-eyebrow">EQUIPMENT & OPTICS INTELLIGENCE</span>
            <h1 class="hs-accessories-hero__title">Helmet Accessories & Optics</h1>
            <p class="hs-accessories-hero__subtitle">
                Find the right accessories for your helmet — from visibility and communication upgrades to comfort, protection and care.
            </p>
            
            <div class="hs-accessories-hero__actions">
                <a href="#accessory-finder" class="hs-btn hs-btn--primary">Find accessories for my helmet →</a>
                <a href="#accessories-catalog" class="hs-btn hs-btn--secondary">Browse all accessories</a>
            </div>

            <div class="hs-accessories-hero__stats">
                <div class="hs-accessories-stat">
                    <span class="hs-accessories-stat__num"><?php echo number_format($total_accessories); ?></span>
                    <span class="hs-accessories-stat__lbl">Accessories Tracked</span>
                </div>
                <div class="hs-accessories-stat">
                    <span class="hs-accessories-stat__num"><?php echo (int) $active_cats_count; ?></span>
                    <span class="hs-accessories-stat__lbl">Categories</span>
                </div>
                <div class="hs-accessories-stat">
                    <span class="hs-accessories-stat__num">Verified</span>
                    <span class="hs-accessories-stat__lbl">Compatibility Data</span>
                </div>
            </div>
        </div>
    </header>

    <!-- 2. ACCESSORY FINDER — MY HELMET COMPATIBILITY ENGINE (§2, §3, §4) -->
    <section id="accessory-finder" class="hs-accessory-finder hs-panel">
        <div class="hs-accessory-finder__header">
            <span class="hs-eyebrow" style="color: var(--hs-accent);">COMPATIBILITY ENGINE</span>
            <h2 class="hs-accessory-finder__title">Find Accessories for Your Helmet</h2>
            <p class="hs-accessory-finder__desc">
                Select your specific helmet model to view confirmed compatible visors, pinlock inserts, communication headsets, liners, and care gear.
            </p>
        </div>

        <form method="get" action="<?php echo esc_url($pageUrl); ?>#accessories-catalog" class="hs-accessory-finder__form" id="hsFinderForm">
            <div class="hs-accessory-finder__grid">
                <!-- Field 1: Cascading Brand & Model Selector -->
                <div class="hs-finder-field">
                    <label class="hs-finder-label">1. Select your helmet</label>
                    <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                        <div>
                            <label for="finder-brand" style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem;">Brand</label>
                            <select name="brand_id" id="finder-brand" class="hs-finder-select">
                                <option value="">-- Choose Brand --</option>
                                <?php foreach ($brandPosts as $bPost) : ?>
                                    <option value="<?php echo (int) $bPost->ID; ?>" <?php selected($selectedBrandId, $bPost->ID); ?>>
                                        <?php echo esc_html($bPost->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="finder-helmet" style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem;">Helmet Model</label>
                            <select name="helmet_id" id="finder-helmet" class="hs-finder-select" <?php disabled(empty($brandHelmets) && $selectedBrandId === 0); ?>>
                                <option value=""><?php echo $selectedBrandId > 0 ? '-- Choose Model (Optional) --' : '-- Select Brand First --'; ?></option>
                                <?php if (!empty($brandHelmets)) : ?>
                                    <?php foreach ($brandHelmets as $hPost) : ?>
                                        <option value="<?php echo (int) $hPost->ID; ?>" <?php selected($selectedHelmetId, $hPost->ID); ?>>
                                            <?php echo esc_html(ltrim($hPost->post_title, '- ')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <a href="#accessories-catalog" class="hs-finder-skip-link" style="font-size: 0.8125rem; font-weight: 700; color: #64748b; text-decoration: underline;">
                            Browse without selecting a helmet →
                        </a>
                    </div>
                </div>

                <!-- Field 2: Purpose Category -->
                <div class="hs-finder-field">
                    <label class="hs-finder-label">2. What are you looking to improve? (Optional)</label>
                    <div class="hs-finder-chips">
                        <label class="hs-finder-chip">
                            <input type="radio" name="purpose" value="fog" <?php checked($selectedPurpose, 'fog'); ?> />
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg> Prevent Fogging</span>
                        </label>
                        <label class="hs-finder-chip">
                            <input type="radio" name="purpose" value="comms" <?php checked($selectedPurpose, 'comms'); ?> />
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg> Communication</span>
                        </label>
                        <label class="hs-finder-chip">
                            <input type="radio" name="purpose" value="comfort" <?php checked($selectedPurpose, 'comfort'); ?> />
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2a10 10 0 0 0-10 10c0 5.5 4.5 10 10 10s10-4.5 10-10A10 10 0 0 0 12 2z"/></svg> Comfort &amp; Fit</span>
                        </label>
                        <label class="hs-finder-chip">
                            <input type="radio" name="purpose" value="visor" <?php checked($selectedPurpose, 'visor'); ?> />
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Visor &amp; Optics</span>
                        </label>
                        <label class="hs-finder-chip">
                            <input type="radio" name="purpose" value="care" <?php checked($selectedPurpose, 'care'); ?> />
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg> Travel &amp; Care</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="hs-accessory-finder__submit-wrap">
                <button type="submit" id="hsFinderSubmit" class="hs-btn hs-btn--primary hs-finder-submit">
                    Show Compatible Accessories →
                </button>
            </div>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var brandSelect = document.getElementById('finder-brand');
            var helmetSelect = document.getElementById('finder-helmet');
            if (!brandSelect || !helmetSelect) return;

            var brandHelmetsCache = {};

            brandSelect.addEventListener('change', function() {
                var brandId = this.value;

                if (!brandId) {
                    helmetSelect.innerHTML = '<option value="">-- Select Brand First --</option>';
                    helmetSelect.disabled = true;
                    return;
                }

                if (brandHelmetsCache[brandId]) {
                    renderModels(brandHelmetsCache[brandId]);
                    return;
                }

                helmetSelect.disabled = true;
                helmetSelect.innerHTML = '<option value="">Loading models...</option>';

                var ajaxUrl = (window.helmetsan_ajax && window.helmetsan_ajax.url) ? window.helmetsan_ajax.url : '/wp-admin/admin-ajax.php';
                fetch(ajaxUrl + '?action=helmetsan_get_helmets_by_brand&brand_id=' + encodeURIComponent(brandId))
                    .then(function(res) { return res.json(); })
                    .then(function(res) {
                        if (res && res.success && Array.isArray(res.data)) {
                            brandHelmetsCache[brandId] = res.data;
                            renderModels(res.data);
                        } else {
                            helmetSelect.innerHTML = '<option value="">-- Choose Model (Optional) --</option>';
                            helmetSelect.disabled = false;
                        }
                    })
                    .catch(function() {
                        helmetSelect.innerHTML = '<option value="">-- Choose Model (Optional) --</option>';
                        helmetSelect.disabled = false;
                    });
            });

            function renderModels(models) {
                var html = '<option value="">-- Choose Model (Optional) --</option>';
                if (models.length === 0) {
                    html = '<option value="">No models listed for this brand</option>';
                } else {
                    models.forEach(function(m) {
                        html += '<option value="' + m.id + '">' + m.title + '</option>';
                    });
                }
                helmetSelect.innerHTML = html;
                helmetSelect.disabled = false;
            }
        });
        </script>
    </section>

    <!-- 3. EXPLORE BY PURPOSE (5 TILES WITH CONSISTENT SVG ICONS) (§4, §6) -->
    <section class="hs-purpose-section">
        <div class="hs-section-header">
            <span class="hs-eyebrow">WHAT ARE YOU TRYING TO IMPROVE?</span>
            <h2 class="hs-section-title">Explore Accessories by Purpose</h2>
        </div>

        <div class="hs-purpose-grid">
            <a href="<?php echo esc_url(add_query_arg('accessory_category', 'anti-fog-solutions', $pageUrl)); ?>#accessories-catalog" class="hs-purpose-card hs-panel">
                <div class="hs-purpose-card__icon-wrap">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
                <h3 class="hs-purpose-card__title">Prevent Fogging</h3>
                <p class="hs-purpose-card__desc">Anti-fog solutions, Pinlocks & breath guards for clear vision.</p>
                <span class="hs-purpose-card__link">Explore anti-fog →</span>
            </a>

            <a href="<?php echo esc_url(add_query_arg('accessory_category', 'communications', $pageUrl)); ?>#accessories-catalog" class="hs-purpose-card hs-panel">
                <div class="hs-purpose-card__icon-wrap">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                </div>
                <h3 class="hs-purpose-card__title">Improve Communication</h3>
                <p class="hs-purpose-card__desc">Intercoms, Bluetooth headsets & audio accessories.</p>
                <span class="hs-purpose-card__link">Explore comms →</span>
            </a>

            <a href="<?php echo esc_url(add_query_arg('accessory_category', 'inner-liners', $pageUrl)); ?>#accessories-catalog" class="hs-purpose-card hs-panel">
                <div class="hs-purpose-card__icon-wrap">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 0-10 10c0 5.5 4.5 10 10 10s10-4.5 10-10A10 10 0 0 0 12 2z"/></svg>
                </div>
                <h3 class="hs-purpose-card__title">Improve Comfort</h3>
                <p class="hs-purpose-card__desc">Balaclavas, replacement cheek pads & inner liners.</p>
                <span class="hs-purpose-card__link">Explore comfort →</span>
            </a>

            <a href="<?php echo esc_url(add_query_arg('accessory_category', 'visors-shields', $pageUrl)); ?>#accessories-catalog" class="hs-purpose-card hs-panel">
                <div class="hs-purpose-card__icon-wrap">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3 class="hs-purpose-card__title">Protect Visor</h3>
                <p class="hs-purpose-card__desc">Replacement visors, shields, tear-offs & peak visors.</p>
                <span class="hs-purpose-card__link">Explore optics →</span>
            </a>

            <a href="<?php echo esc_url(add_query_arg('accessory_category', 'maintenance-care', $pageUrl)); ?>#accessories-catalog" class="hs-purpose-card hs-panel">
                <div class="hs-purpose-card__icon-wrap">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                </div>
                <h3 class="hs-purpose-card__title">Travel & Care</h3>
                <p class="hs-purpose-card__desc">Cleaner sprays, microfiber care & helmet transport bags.</p>
                <span class="hs-purpose-card__link">Explore care →</span>
            </a>
        </div>
    </section>

    <!-- 4. BROWSE BY CATEGORY (4 FUNCTIONAL GROUPS) (§5: Comfort & Fit) -->
    <section class="hs-category-taxonomy-section">
        <div class="hs-section-header">
            <span class="hs-eyebrow">EQUIPMENT TAXONOMY</span>
            <h2 class="hs-section-title">Browse by Functional Category</h2>
        </div>

        <div class="hs-taxonomy-groups">
            <?php foreach ($functionalGroups as $gKey => $group) : ?>
                <div class="hs-taxonomy-group hs-panel">
                    <div class="hs-taxonomy-group__header">
                        <h3 class="hs-taxonomy-group__title"><?php echo esc_html($group['title']); ?></h3>
                        <p class="hs-taxonomy-group__desc"><?php echo esc_html($group['desc']); ?></p>
                    </div>

                    <div class="hs-taxonomy-tiles">
                        <?php
                        if (is_array($categoryTerms)) :
                            foreach ($categoryTerms as $term) :
                                if (! ($term instanceof WP_Term) || ! in_array($term->slug, $group['slugs'], true)) {
                                    continue;
                                }
                                ?>
                                <a href="<?php echo esc_url(add_query_arg('accessory_category', $term->slug, $pageUrl)); ?>#accessories-catalog"
                                   class="hs-taxonomy-tile">
                                    <span class="hs-taxonomy-tile__name"><?php echo esc_html($term->name); ?></span>
                                    <span class="hs-taxonomy-tile__count"><?php echo (int) $term->count; ?> items</span>
                                </a>
                            <?php
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 5. ACCESSORY CATALOG DIRECTORY (§7, §11, §18) -->
    <div class="hs-catalog hs-catalog--wide" id="accessories-catalog">
        <!-- Sidebar Filters -->
        <aside id="hsFilterPanel" class="hs-catalog__filters hs-catalog__filters--sticky hs-panel" aria-label="Accessory filters">
            <div class="hs-catalog__filters-head">
                <div class="hs-filter-head-left">
                    <svg class="hs-filter-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="4" y1="21" x2="4" y2="14"></line>
                        <line x1="4" y1="10" x2="4" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12" y2="3"></line>
                        <line x1="20" y1="21" x2="20" y2="16"></line>
                        <line x1="20" y1="12" x2="20" y2="3"></line>
                        <line x1="1" y1="14" x2="7" y2="14"></line>
                        <line x1="9" y1="8" x2="15" y2="8"></line>
                        <line x1="17" y1="16" x2="23" y2="16"></line>
                    </svg>
                    <strong>Filter Accessories</strong>
                    <?php if ($activeFilterCount > 0) : ?>
                        <span class="hs-filter-active-count"><?php echo (int) $activeFilterCount; ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" class="hs-mobile-filter-close" data-close-filter id="hsFilterCloseBtn" aria-label="Close filters">✕</button>
            </div>

            <form id="hsAccessoryFilterForm" method="get" action="<?php echo esc_url($pageUrl); ?>#accessories-catalog">
                <?php if ($search !== '') : ?><input type="hidden" name="s" value="<?php echo esc_attr($search); ?>" /><?php endif; ?>
                <?php if ($selectedBrandId > 0) : ?><input type="hidden" name="brand_id" value="<?php echo (int) $selectedBrandId; ?>" /><?php endif; ?>
                <?php if ($selectedHelmetId > 0) : ?><input type="hidden" name="helmet_id" value="<?php echo (int) $selectedHelmetId; ?>" /><?php endif; ?>
                <?php if ($selectedPurpose !== '') : ?><input type="hidden" name="purpose" value="<?php echo esc_attr($selectedPurpose); ?>" /><?php endif; ?>

                <?php if ($selectedHelmetTitle !== '') : ?>
                    <div class="hs-selected-helmet-widget" style="margin: 0.75rem 0.25rem 0.5rem; padding: 0.75rem 0.85rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
                        <div style="font-size: 0.6875rem; font-weight: 800; color: #15803d; text-transform: uppercase; letter-spacing: 0.05em;">Matched Helmet</div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin: 0.2rem 0 0.35rem; line-height: 1.25;"><?php echo esc_html($selectedHelmetTitle); ?></div>
                        <a href="<?php echo esc_url(remove_query_arg(['helmet_id', 'brand_id'], $pageUrl)); ?>#accessories-catalog" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 600; color: #dc2626; text-decoration: none;">
                            <span>Change helmet</span> &times;
                        </a>
                    </div>
                <?php elseif ($selectedBrandTitle !== '') : ?>
                    <div class="hs-selected-helmet-widget" style="margin: 0.75rem 0.25rem 0.5rem; padding: 0.75rem 0.85rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
                        <div style="font-size: 0.6875rem; font-weight: 800; color: #15803d; text-transform: uppercase; letter-spacing: 0.05em;">Matched Brand</div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin: 0.2rem 0 0.35rem; line-height: 1.25;"><?php echo esc_html($selectedBrandTitle); ?></div>
                        <a href="<?php echo esc_url(remove_query_arg('brand_id', $pageUrl)); ?>#accessories-catalog" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 600; color: #dc2626; text-decoration: none;">
                            <span>Change brand</span> &times;
                        </a>
                    </div>
                <?php endif; ?>

                <!-- GROUP 1: CATEGORY -->
                <details class="hs-filter-group" open>
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Category</span>
                        <?php if (!empty($categories)) : ?>
                            <span class="hs-filter-badge"><?php echo count($categories); ?></span>
                        <?php endif; ?>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Category</legend>
                        <div class="hs-filter-checks hs-filter-scroll">
                            <?php
                            if (is_array($categoryTerms)) :
                                foreach ($categoryTerms as $term) :
                                    if (! ($term instanceof WP_Term) || (int) $term->count === 0) continue;
                                    $isChecked = in_array($term->slug, $categories, true);
                                    ?>
                                    <label class="hs-filter-check-label">
                                        <input type="checkbox" name="accessory_category[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked($isChecked); ?> />
                                        <span class="hs-filter-check-text"><?php echo esc_html($term->name); ?></span>
                                        <span class="hs-filter-check-count"><?php echo (int) $term->count; ?></span>
                                    </label>
                                <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 2: FEATURES & STANDARDS -->
                <details class="hs-filter-group" open>
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Features &amp; Standards</span>
                        <?php
                        $featuresActive = ($pinlock === '1' ? 1 : 0) + ($electric === '1' ? 1 : 0) + ($snow === '1' ? 1 : 0);
                        if ($featuresActive > 0) : ?>
                            <span class="hs-filter-badge"><?php echo $featuresActive; ?></span>
                        <?php endif; ?>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Features</legend>
                        <div class="hs-filter-checks">
                            <label class="hs-filter-check-label">
                                <input type="checkbox" name="pinlock_ready" value="1" <?php checked($pinlock, '1'); ?> />
                                <span class="hs-filter-check-text">Pinlock Ready</span>
                            </label>
                            <label class="hs-filter-check-label">
                                <input type="checkbox" name="electric" value="1" <?php checked($electric, '1'); ?> />
                                <span class="hs-filter-check-text">Electric Compatible</span>
                            </label>
                            <label class="hs-filter-check-label">
                                <input type="checkbox" name="snow" value="1" <?php checked($snow, '1'); ?> />
                                <span class="hs-filter-check-text">Snow Compatible</span>
                            </label>
                        </div>
                    </fieldset>
                </details>

                <!-- GROUP 3: RIDING PURPOSE -->
                <details class="hs-filter-group">
                    <summary class="hs-filter-summary">
                        <span class="hs-filter-summary__label">Riding Purpose</span>
                        <?php if ($selectedPurpose !== '') : ?>
                            <span class="hs-filter-badge">1</span>
                        <?php endif; ?>
                        <svg class="hs-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <fieldset>
                        <legend class="screen-reader-text">Filter by Purpose</legend>
                        <div class="hs-filter-checks">
                            <label class="hs-filter-check-label">
                                <input type="radio" name="purpose" value="" <?php checked($selectedPurpose, ''); ?> />
                                <span class="hs-filter-check-text">All Purposes</span>
                            </label>
                            <label class="hs-filter-check-label">
                                <input type="radio" name="purpose" value="fog" <?php checked($selectedPurpose, 'fog'); ?> />
                                <span class="hs-filter-check-text">Anti-Fog &amp; Breath</span>
                            </label>
                            <label class="hs-filter-check-label">
                                <input type="radio" name="purpose" value="comms" <?php checked($selectedPurpose, 'comms'); ?> />
                                <span class="hs-filter-check-text">Audio &amp; Intercoms</span>
                            </label>
                            <label class="hs-filter-check-label">
                                <input type="radio" name="purpose" value="comfort" <?php checked($selectedPurpose, 'comfort'); ?> />
                                <span class="hs-filter-check-text">Comfort &amp; Liners</span>
                            </label>
                            <label class="hs-filter-check-label">
                                <input type="radio" name="purpose" value="visor" <?php checked($selectedPurpose, 'visor'); ?> />
                                <span class="hs-filter-check-text">Visors &amp; Shields</span>
                            </label>
                            <label class="hs-filter-check-label">
                                <input type="radio" name="purpose" value="care" <?php checked($selectedPurpose, 'care'); ?> />
                                <span class="hs-filter-check-text">Protection &amp; Care</span>
                            </label>
                        </div>
                    </fieldset>
                </details>

                <div class="hs-filter-actions">
                    <a class="hs-btn hs-btn--outline" href="<?php echo esc_url($pageUrl); ?>#accessories-catalog">Clear All</a>
                    <button class="hs-btn hs-btn--primary" type="submit">Apply Filters</button>
                </div>
            </form>
        </aside>

        <!-- Main Results Content -->
        <div class="hs-catalog__results">
            <!-- ACTIVE FILTER CHIPS -->
            <?php if (!empty($activeChips)) : ?>
                <div class="hs-results-context-bar" style="margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--hs-panel); border: 1px solid var(--hs-border); border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                    <div class="hs-results-context-chips" style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem;">
                        <span style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-right: 0.25rem;">Active:</span>
                        <?php foreach ($activeChips as $chip) : ?>
                            <a href="<?php echo esc_url($chip['url']); ?>" class="hs-context-chip" title="Remove filter">
                                <span><?php echo esc_html($chip['label']); ?></span>
                                <span style="margin-left: 0.35rem; color: #dc2626; font-weight: 700;">&times;</span>
                            </a>
                        <?php endforeach; ?>
                        <a href="<?php echo esc_url($pageUrl); ?>#accessories-catalog" class="hs-context-clear-all" style="font-size: 0.8125rem; font-weight: 700; color: var(--hs-accent); text-decoration: none; margin-left: 0.5rem;">Clear all</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- DIRECTORY TOOLBAR (§7: Accessory Catalog) -->
            <div class="hs-catalog__topbar hs-panel" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <div class="hs-catalog__topbar-left" style="display: flex; align-items: baseline; gap: 0.5rem;">
                    <span class="hs-catalog__count" style="font-weight: 800; font-size: 1.05rem; color: #0f172a;">
                        Accessory Catalog
                    </span>
                    <?php if ($accessoryQuery->found_posts > 0) :
                        $ppp = $accessoryQuery->get('posts_per_page');
                        $start = (($paged - 1) * $ppp) + 1;
                        $end = min($paged * $ppp, $accessoryQuery->found_posts);
                        ?>
                        <span class="hs-results-context-count" style="font-size: 0.84rem; color: #64748b;">
                            · Showing <?php echo (int) $start; ?>–<?php echo (int) $end; ?> of <?php echo (int) $accessoryQuery->found_posts; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="hs-catalog__topbar-right" style="display: flex; align-items: center; gap: 0.75rem;">
                    <form method="get" action="<?php echo esc_url($pageUrl); ?>#accessories-catalog" class="hs-toolbar-sort-form">
                        <?php if ($search !== '') : ?><input type="hidden" name="s" value="<?php echo esc_attr($search); ?>" /><?php endif; ?>
                        <?php if ($selectedBrandId > 0) : ?><input type="hidden" name="brand_id" value="<?php echo (int) $selectedBrandId; ?>" /><?php endif; ?>
                        <?php if ($selectedHelmetId > 0) : ?><input type="hidden" name="helmet_id" value="<?php echo (int) $selectedHelmetId; ?>" /><?php endif; ?>
                        <?php if ($selectedPurpose !== '') : ?><input type="hidden" name="purpose" value="<?php echo esc_attr($selectedPurpose); ?>" /><?php endif; ?>
                        <select name="sort" onchange="this.form.submit()" class="hs-toolbar-sort-select" style="padding: 0.4rem 0.75rem; border: 1px solid var(--hs-border); border-radius: 8px; font-size: 0.8125rem; font-weight: 700; color: #0f172a; background: #ffffff;">
                            <option value="title_asc" <?php selected($sort, 'title_asc'); ?>>Sort: Name A–Z ▾</option>
                            <option value="title_desc" <?php selected($sort, 'title_desc'); ?>>Sort: Name Z–A ▾</option>
                            <option value="newest" <?php selected($sort, 'newest'); ?>>Sort: Newest First ▾</option>
                        </select>
                    </form>

                    <div class="hs-catalog__view-switcher">
                        <button type="button" class="hs-view-btn is-active" data-view="grid" title="Grid View">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            <span>Grid</span>
                        </button>
                        <button type="button" class="hs-view-btn" data-view="list" title="List View">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            <span>List</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- PRODUCT GRID (4 Columns Desktop / 2 Tablet / 1 Mobile) -->
            <section class="hs-catalog__results-content">
                <?php if ($accessoryQuery->have_posts()) : ?>
                    <div class="hs-catalog-grid hs-catalog-grid--4cols">
                        <?php
                        while ($accessoryQuery->have_posts()) :
                            $accessoryQuery->the_post();
                            get_template_part('template-parts/accessory-card');
                        endwhile;
                        ?>
                    </div>

                    <?php
                    $ppp = $accessoryQuery->get('posts_per_page');
                    $start = (($paged - 1) * $ppp) + 1;
                    $end = min($paged * $ppp, $accessoryQuery->found_posts);
                    $count_text = sprintf(__('Showing %d–%d of %d', 'helmetsan-theme'), $start, $end, $accessoryQuery->found_posts);

                    get_template_part('template-parts/pagination-modern', null, [
                        'paged'      => $paged,
                        'total'      => (int) $accessoryQuery->max_num_pages,
                        'count_text' => $count_text,
                    ]);
                    ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <p class="hs-catalog__empty">
                        No accessory products found matching criteria. <a href="<?php echo esc_url($pageUrl); ?>">Clear all filters</a>.
                    </p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<div class="hs-mobile-tools" aria-label="Mobile catalog tools">
    <button type="button" data-open-filter aria-controls="hsFilterPanel" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M22 3H2l8 9v7l4 3v-10L22 3z"/></svg>
        <span>Filter</span>
        <?php if ($activeFilterCount > 0) : ?>
            <span class="hs-filter-badge"><?php echo (int) $activeFilterCount; ?></span>
        <?php endif; ?>
    </button>
</div>

<?php
get_footer();


