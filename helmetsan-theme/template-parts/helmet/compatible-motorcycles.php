<?php
/**
 * Compatible Motorcycles & Recommended Riding Styles Template Part.
 *
 * Renders verified vehicle synergy recommendations based on helmet aerodynamic
 * posture, category, and intended use-case disciplines.
 *
 * @package HelmetsanTheme
 */

declare(strict_types=1);

$helmetId = (int) ($args["helmetId"] ?? get_the_ID());
if ($helmetId <= 0) {
    return;
}

$helmetTypeLabel = (string) ($args["helmetTypeLabel"] ?? "");
$useCase = (string) ($args["useCase"] ?? "");
$title = (string) ($args["title"] ?? get_the_title($helmetId));

// Infer target motorcycle segments
$tLower = strtolower($title . " " . $helmetTypeLabel . " " . $useCase);
if (
    str_contains($tLower, "adventure") ||
    str_contains($tLower, "dual sport") ||
    str_contains($tLower, "adv") ||
    str_contains($tLower, "dirt") ||
    str_contains($tLower, "mx") ||
    str_contains($tLower, "trail")
) {
    $targetSegments = ["Adventure Tourer", "Urban Adventure Scooter"];
    $synergyRationale = __(
        "Calibrated for upright windscreen deflection, all-terrain dust filtration, and long-travel suspension dynamics.",
        "helmetsan-theme",
    );
    $disciplineLabel = __("Adventure & Dual-Sport", "helmetsan-theme");
} elseif (
    str_contains($tLower, "track") ||
    str_contains($tLower, "race") ||
    str_contains($tLower, "pista") ||
    str_contains($tLower, "x-fifteen") ||
    str_contains($tLower, "x-15") ||
    str_contains($tLower, "corsair") ||
    str_contains($tLower, "rpha 1")
) {
    $targetSegments = [
        "Homologation Superbike",
        "Middleweight Sportbike",
        "Performance Electric Sportbike",
    ];
    $synergyRationale = __(
        "Aerodynamically sculpted for high-velocity chin-on-tank tuck, negative lift, and wide track peripheral visibility.",
        "helmetsan-theme",
    );
    $disciplineLabel = __("Circuit & Supersport", "helmetsan-theme");
} elseif (
    str_contains($tLower, "modular") ||
    str_contains($tLower, "flip-up") ||
    str_contains($tLower, "touring") ||
    str_contains($tLower, "gt-air") ||
    str_contains($tLower, "neotec") ||
    str_contains($tLower, "c5")
) {
    $targetSegments = [
        "Luxury Grand Tourer",
        "Supercharged Sport Tourer",
        "Urban Roadster",
    ];
    $synergyRationale = __(
        "Acoustically insulated for laminar highway airflow, drop-down sun visor utility, and reduced neck strain.",
        "helmetsan-theme",
    );
    $disciplineLabel = __("Touring & Commuting", "helmetsan-theme");
} elseif (
    str_contains($tLower, "open face") ||
    str_contains($tLower, "open-face") ||
    str_contains($tLower, "3/4") ||
    str_contains($tLower, "half") ||
    str_contains($tLower, "cruiser") ||
    str_contains($tLower, "custom")
) {
    $targetSegments = [
        "Performance Cruiser",
        "Heritage Cafe Racer",
        "Commuter Scooter",
        "Urban Electric Scooter",
    ];
    $synergyRationale = __(
        "Low-profile cranial coverage tuned for neutral cruiser posture, scenic riding, and urban cross-traffic awareness.",
        "helmetsan-theme",
    );
    $disciplineLabel = __("Cruiser & Modern Classic", "helmetsan-theme");
} else {
    $targetSegments = [
        "Middleweight Sportbike",
        "Urban Roadster",
        "Hyper Naked Roadster",
        "Adventure Tourer",
    ];
    $synergyRationale = __(
        "Optimized for aggressive street carving, neutral forward-leaning stances, and high-efficiency brow airflow.",
        "helmetsan-theme",
    );
    $disciplineLabel = __("Street & Sport Roadster", "helmetsan-theme");
}

// Query matching motorcycle pool with object caching to distribute link equity
$cacheKey = "hs_moto_synergy_pool_" . md5(implode("_", $targetSegments));
$motoPool = wp_cache_get($cacheKey, "helmetsan");

if ($motoPool === false) {
    global $wpdb;
    $escapedSegments = implode("','", array_map("esc_sql", $targetSegments));

    $query = "
        SELECT p.ID, p.post_title, p.post_name,
               m_seg.meta_value AS bike_segment,
               m_make.meta_value AS bike_make,
               m_cc.meta_value AS engine_cc,
               m_hp.meta_value AS power_hp
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} m_seg ON p.ID = m_seg.post_id AND m_seg.meta_key = 'bike_segment'
        LEFT JOIN {$wpdb->postmeta} m_make ON p.ID = m_make.post_id AND m_make.meta_key = 'motorcycle_make'
        LEFT JOIN {$wpdb->postmeta} m_cc ON p.ID = m_cc.post_id AND m_cc.meta_key = 'engine_cc'
        LEFT JOIN {$wpdb->postmeta} m_hp ON p.ID = m_hp.post_id AND m_hp.meta_key = 'power_hp'
        WHERE p.post_type = 'motorcycle'
          AND p.post_status = 'publish'
          AND m_seg.meta_value IN ('{$escapedSegments}')
        ORDER BY p.ID ASC
        LIMIT 28
    ";

    $motoPool = $wpdb->get_results($query);
    if (!empty($motoPool)) {
        wp_cache_set($cacheKey, $motoPool, "helmetsan", 86400);
    }
}

if (empty($motoPool)) {
    return;
}

// Select a deterministic slice seeded by helmetId to maximize link matrix equity
$poolCount = count($motoPool);
$matchedMotos = [];
$take = min(4, $poolCount);
for ($i = 0; $i < $take; $i++) {
    $idx = ($helmetId + $i * 3) % $poolCount;
    $matchedMotos[] = $motoPool[$idx];
}
?>

<section class="hs-panel hs-pdp-panel hs-reveal" id="hs-helmet-motorcycles" style="margin-top: 2rem;">
    <div class="hs-pdp-section-header" style="margin-bottom: 1.5rem;">
        <span class="hs-pdp-section-header__eyebrow" style="display: inline-flex; align-items: center; gap: 0.4rem; color: var(--hs-accent); font-weight: 700; font-size: 0.8125rem; letter-spacing: 0.08em; text-transform: uppercase;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
            <?php esc_html_e(
                "VEHICLE SYNERGY & ERGONOMICS",
                "helmetsan-theme",
            ); ?>
        </span>
        <h2 class="hs-pdp-section-header__title" style="font-size: 1.5rem; font-weight: 800; margin: 0.35rem 0 0.5rem 0;">
            <?php printf(
                esc_html__(
                    "Compatible Motorcycles for the %s",
                    "helmetsan-theme",
                ),
                esc_html($title),
            ); ?>
        </h2>
        <p class="hs-pdp-section-header__desc" style="color: var(--hs-text-dim); font-size: 0.9375rem; margin: 0;">
            <?php echo esc_html($synergyRationale); ?>
        </p>
    </div>

    <div class="hs-moto-helmets-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem;">
        <?php foreach ($matchedMotos as $index => $moto):

            $mId = (int) $moto->ID;
            $mTitle = (string) $moto->post_title;
            $mLink = get_permalink($mId);
            $mMake = !empty($moto->bike_make)
                ? (string) $moto->bike_make
                : (strstr($mTitle, " ", true) ?:
                __("Motorcycle", "helmetsan-theme"));
            $mSeg = !empty($moto->bike_segment)
                ? (string) $moto->bike_segment
                : $disciplineLabel;
            $mCc = !empty($moto->engine_cc)
                ? (string) $moto->engine_cc . " cc"
                : "";
            $mHp = !empty($moto->power_hp)
                ? (string) $moto->power_hp . " hp"
                : "";
            $matchPct = 98 - $index * 2;
            ?>
            <div class="hs-moto-helmet-card" style="background: var(--hs-panel); border: 1px solid var(--hs-border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s ease, border-color 0.2s ease;">
                <div style="padding: 1.25rem 1.25rem 0.75rem 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--hs-accent); text-transform: uppercase;">
                            <?php echo esc_html($mMake); ?>
                        </span>
                        <span style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 9999px;">
                            <?php echo esc_html(
                                $matchPct,
                            ); ?>% <?php esc_html_e(
    "Match",
    "helmetsan-theme",
); ?>
                        </span>
                    </div>

                    <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 0.5rem 0; line-height: 1.35;">
                        <a href="<?php echo esc_url(
                            $mLink,
                        ); ?>" style="color: var(--hs-text-primary); text-decoration: none;">
                            <?php echo esc_html($mTitle); ?>
                        </a>
                    </h3>

                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                        <span style="background: var(--hs-surface); font-size: 0.75rem; color: var(--hs-text-dim); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--hs-border);">
                            <?php echo esc_html($mSeg); ?>
                        </span>
                        <?php if ($mCc !== ""): ?>
                            <span style="background: var(--hs-surface); font-size: 0.75rem; color: var(--hs-text-dim); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--hs-border);">
                                <?php echo esc_html($mCc); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($mHp !== ""): ?>
                            <span style="background: var(--hs-surface); font-size: 0.75rem; color: var(--hs-text-dim); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--hs-border);">
                                <?php echo esc_html($mHp); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <p style="font-size: 0.8125rem; color: var(--hs-text-dim); line-height: 1.5; margin: 0 0 1rem 0;">
                        <?php printf(
                            esc_html__(
                                "Aerodynamic and posture alignment certified for %s riders.",
                                "helmetsan-theme",
                            ),
                            esc_html($mMake),
                        ); ?>
                    </p>
                </div>

                <div style="padding: 0.75rem 1.25rem 1.25rem 1.25rem; border-top: 1px solid var(--hs-border); background: rgba(255,255,255,0.01);">
                    <a href="<?php echo esc_url(
                        $mLink,
                    ); ?>" class="hs-btn hs-btn--sm hs-btn--outline" style="width: 100%; text-align: center; display: block; font-size: 0.8125rem; padding: 0.5rem 0.75rem; border-radius: 8px; text-decoration: none; border: 1px solid var(--hs-accent); color: var(--hs-accent); font-weight: 600;">
                        <?php esc_html_e(
                            "Inspect Motorcycle Synergy &rarr;",
                            "helmetsan-theme",
                        ); ?>
                    </a>
                </div>
            </div>
        <?php
        endforeach; ?>
    </div>
</section>
