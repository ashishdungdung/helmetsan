<?php
/**
 * Overhauled High-Fidelity Safety & Acoustics HUD.
 *
 * @package HelmetsanTheme
 */

$helmetId = $args['helmet_id'] ?? get_the_ID();
$profile = helmetsan_get_technical_profile($helmetId);

if (empty($profile)) return;

$sharpStars = (int) $profile['sharp_rating'];
$sharpPercentage = $sharpStars > 0 ? ($sharpStars / 5) * 100 : 0;

// Noise dB parsing
$noiseStr = $profile['noise_db'];
$noiseDb = 0;
if (preg_match('/(\d+)/', $noiseStr, $matches)) {
    $noiseDb = (int) $matches[1];
}
// Noise percentage: lower is better quietness score
// Standard quietness ranges from 105 dB (loudest, 10% score) to 90 dB (quietest, 95% score)
$noiseQuietnessScore = 70; // baseline fallback
if ($noiseDb > 0) {
    $noiseQuietnessScore = max(5, min(98, 100 - ($noiseDb - 88) * 6));
}

// Ventilation score parsing
$ventStr = $profile['ventilation_score'];
$ventScore = 0;
if (preg_match('/(\d+)/', $ventStr, $matches)) {
    $ventScore = (int) $matches[1];
}
$ventPercentage = $ventScore > 0 ? $ventScore * 10 : 0;
?>

<section class="hs-pdp-panel hs-tech-scorecard" id="helmet-technical-hud">
    <h2 class="hs-pdp-panel__title">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <?php esc_html_e('Safety & Aero-Acoustics HUD', 'helmetsan-theme'); ?>
    </h2>
    
    <div class="hs-safety-hud">
        <!-- 1. Safety Gauge & Homologation -->
        <div class="hs-safety-hud__cell">
            <?php if ($sharpStars > 0) : ?>
                <div class="hs-safety-hud__circular-gauge">
                    <svg class="hs-safety-hud__svg-ring" width="100" height="100">
                        <circle class="hs-safety-hud__circle-bg" cx="50" cy="50" r="40" />
                        <circle class="hs-safety-hud__circle-val" cx="50" cy="50" r="40" data-percent="<?php echo $sharpPercentage; ?>" />
                    </svg>
                    <div class="hs-safety-hud__gauge-content">
                        <?php echo $sharpStars; ?>/5
                    </div>
                </div>
                <div class="hs-safety-hud__rating-stars" aria-label="<?php echo esc_attr($sharpStars); ?> SHARP stars">
                    <?php echo str_repeat('★', $sharpStars) . str_repeat('☆', 5 - $sharpStars); ?>
                </div>
                <span style="font-size: var(--hs-fs-xs); font-weight: 700; text-transform: uppercase; color: var(--hs-muted); margin-top: var(--hs-sp-1);">SHARP Impact Rating</span>
            <?php else : ?>
                <div class="hs-safety-hud__awaiting-badge">
                    <?php esc_html_e('Awaiting SHARP Audit', 'helmetsan-theme'); ?>
                </div>
                <p style="font-size: var(--hs-fs-xs); color: var(--hs-muted); max-width: 140px; margin: 0; line-height: 1.3;">
                    <?php esc_html_e('SHARP impact test results pending.', 'helmetsan-theme'); ?>
                </p>
            <?php endif; ?>

            <div class="hs-safety-hud__badges-wrap" style="margin-top: var(--hs-sp-4);">
                <?php if ($profile['homologation'] !== 'N/A') : ?>
                    <span class="hs-safety-hud__badge hs-safety-hud__badge--accent"><?php echo esc_html($profile['homologation']); ?></span>
                <?php endif; ?>
                <?php if ($profile['rotational_tech'] !== 'N/A') : ?>
                    <span class="hs-safety-hud__badge"><?php echo esc_html($profile['rotational_tech']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Acoustics Quietness -->
        <div class="hs-safety-hud__cell">
            <div class="hs-safety-hud__metric-box">
                <div class="hs-safety-hud__metric-header">
                    <span class="hs-safety-hud__metric-title"><?php esc_html_e('Aero-Acoustics', 'helmetsan-theme'); ?></span>
                    <span class="hs-safety-hud__metric-val"><?php echo $noiseDb > 0 ? $noiseDb . ' dB' : 'N/A'; ?></span>
                </div>
                <div class="hs-safety-hud__bar-outer">
                    <div class="hs-safety-hud__bar-inner" data-width="<?php echo $noiseQuietnessScore; ?>%"></div>
                </div>
                <p class="hs-safety-hud__metric-note">
                    <?php
                    if ($noiseDb === 0) {
                        esc_html_e('Aero-acoustic profile pending track testing.', 'helmetsan-theme');
                    } elseif ($noiseDb <= 95) {
                        esc_html_e('Extremely Quiet: Superior long-distance sound damping.', 'helmetsan-theme');
                    } elseif ($noiseDb <= 99) {
                        esc_html_e('Moderate: Balanced road noise levels.', 'helmetsan-theme');
                    } else {
                        esc_html_e('High Aero Noise: Ear protection strongly recommended.', 'helmetsan-theme');
                    }
                    ?>
                </p>
            </div>
        </div>

        <!-- 3. Ventilation & Comfort Specs -->
        <div class="hs-safety-hud__cell">
            <div class="hs-safety-hud__metric-box">
                <div class="hs-safety-hud__metric-header">
                    <span class="hs-safety-hud__metric-title"><?php esc_html_e('Ventilation Flow', 'helmetsan-theme'); ?></span>
                    <span class="hs-safety-hud__metric-val"><?php echo $ventScore > 0 ? $ventScore . '/10' : 'N/A'; ?></span>
                </div>
                <div class="hs-safety-hud__bar-outer">
                    <div class="hs-safety-hud__bar-inner hs-safety-hud__bar-inner--blue" data-width="<?php echo $ventPercentage; ?>%"></div>
                </div>
                <p class="hs-safety-hud__metric-note">
                    <?php
                    if ($ventScore === 0) {
                        esc_html_e('Ventilation flow score awaiting CFD test.', 'helmetsan-theme');
                    } elseif ($ventScore >= 8) {
                        esc_html_e('High Flow: Dynamic multi-channel active venting.', 'helmetsan-theme');
                    } elseif ($ventScore >= 5) {
                        esc_html_e('Good Flow: Standard EPS exhaust ports.', 'helmetsan-theme');
                    } else {
                        esc_html_e('Low Flow: Minimal venting ports.', 'helmetsan-theme');
                    }
                    ?>
                </p>
            </div>

            <div class="hs-safety-hud__badges-wrap" style="margin-top: var(--hs-sp-3);">
                <?php if ($profile['strap_type'] !== 'N/A') : ?>
                    <span class="hs-safety-hud__badge" title="Retention Strap type"><?php echo esc_html($profile['strap_type']); ?></span>
                <?php endif; ?>
                <?php if ($profile['comms_ready'] !== 'N/A' && strtolower($profile['comms_ready']) !== 'no') : ?>
                    <span class="hs-safety-hud__badge" title="Comms integration status">🔊 <?php esc_html_e('Comms Ready', 'helmetsan-theme'); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($profile['visor_features']) || !empty($profile['liner_features'])) : ?>
        <div class="hs-tech-scorecard__extra" style="margin-top: var(--hs-sp-6); padding-top: var(--hs-sp-4); border-top: 1px solid var(--hs-border);">
            <?php if (!empty($profile['visor_features'])) : ?>
                <div class="hs-tech-scorecard__tags-wrap" style="margin-bottom: var(--hs-sp-4);">
                    <h4 style="font-size: var(--hs-fs-xs); font-weight: 700; text-transform: uppercase; color: var(--hs-muted); margin-bottom: var(--hs-sp-2);"><?php esc_html_e('Visor Features', 'helmetsan-theme'); ?></h4>
                    <div class="hs-feature-pills">
                        <?php foreach ($profile['visor_features'] as $f) : ?>
                            <span class="hs-feature-pill"><?php echo esc_html($f); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($profile['liner_features'])) : ?>
                <div class="hs-tech-scorecard__tags-wrap">
                    <h4 style="font-size: var(--hs-fs-xs); font-weight: 700; text-transform: uppercase; color: var(--hs-muted); margin-bottom: var(--hs-sp-2);"><?php esc_html_e('Interior & Liner Tech', 'helmetsan-theme'); ?></h4>
                    <div class="hs-feature-pills">
                        <?php foreach ($profile['liner_features'] as $f) : ?>
                            <span class="hs-feature-pill"><?php echo esc_html($f); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
