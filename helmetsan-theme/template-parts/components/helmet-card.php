<?php
/**
 * Helmetsan Redesigned Decision HelmetCard Component
 * 
 * Answers: "Why should I care about this helmet?"
 */

$helmet = $args['helmet'] ?? [];
if (empty($helmet)) return;

$id         = esc_attr($helmet['id'] ?? 'helmet-' . uniqid());
$title      = esc_html($helmet['title'] ?? 'Helmet');
$brand      = esc_html($helmet['brand'] ?? 'Manufacturer');
$price      = esc_html($helmet['price']['current'] ?? ($helmet['price']['usd'] ?? '350'));
$currency   = esc_html($helmet['price']['currency'] ?? 'USD');
$weight     = esc_html($helmet['specs']['weight_g'] ?? ($helmet['spec_weight_g'] ?? '1,450'));
$material   = esc_html($helmet['specs']['material'] ?? 'Composite');
$shape      = esc_html($helmet['sizing_fit']['head_shape'] ?? ($helmet['head_shape'] ?? 'Intermediate Oval'));
$homo       = esc_html($helmet['safety_intelligence']['homologation_standard'] ?? 'ECE 22.06');

// Calculate Helmetsan Score
$scoreObj   = function_exists('helmetsan_calculate_score') ? helmetsan_calculate_score($helmet) : ['overall' => 92, 'dimensions' => ['safety' => 95, 'comfort' => 90, 'value' => 84]];
$score      = $scoreObj['overall'];
$safetySc   = $scoreObj['dimensions']['safety'];
$comfortSc  = $scoreObj['dimensions']['comfort'];
$valueSc    = $scoreObj['dimensions']['value'];

$helmetSlug = sanitize_title((string) ($helmet['id'] ?? ''));
$permalink = esc_url(helmetsan_url('/helmets/' . $helmetSlug . '/'));
?>

<div class="hs-card hs-helmet-card hs-relative hs-flex hs-flex-col hs-overflow-hidden hs-hover-lift" data-helmet-id="<?php echo $id; ?>">
    <!-- Top Header Bar -->
    <div class="hs-card-top hs-flex hs-items-center hs-justify-between hs-p-4 hs-pb-2">
        <div>
            <span class="hs-text-micro hs-uppercase hs-tracking-wider hs-text-muted hs-font-bold"><?php echo $brand; ?></span>
            <h3 class="hs-font-bold hs-text-base hs-leading-tight hs-mt-0.5">
                <a href="<?php echo $permalink; ?>" class="hs-text-main hs-no-underline hover:hs-text-accent"><?php echo $title; ?></a>
            </h3>
        </div>

        <!-- Helmetsan Score Badge -->
        <div class="hs-score-badge hs-text-center" title="<?php echo esc_attr($scoreObj['disclaimer'] ?? ''); ?>">
            <div class="hs-score-value hs-font-black hs-text-lg <?php echo ($score >= 90) ? 'hs-text-green' : (($score >= 80) ? 'hs-text-amber' : 'hs-text-red'); ?>">
                <?php echo $score; ?>
            </div>
            <div class="hs-score-label hs-text-micro hs-uppercase hs-text-muted hs-font-bold">SCORE</div>
        </div>
    </div>

    <!-- Image Container -->
    <div class="hs-card-image-wrap hs-relative hs-px-4 hs-py-3 hs-bg-alt hs-flex hs-items-center hs-justify-center">
        <a href="<?php echo $permalink; ?>">
            <img src="<?php echo esc_url($image); ?>" alt="<?php echo $title; ?>" class="hs-card-img hs-object-contain hs-h-48 hs-w-full" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/600x600/F5F6F8/111111?text=' + encodeURIComponent('<?php echo esc_js($title); ?>');" />
        </a>

        <!-- Certification & Spec Chips -->
        <div class="hs-card-chips hs-absolute hs-bottom-2 hs-left-2 hs-flex hs-flex-wrap hs-gap-1">
            <span class="hs-chip hs-chip-success">🟢 <?php echo $homo; ?></span>
            <span class="hs-chip hs-chip-neutral">🪶 <?php echo $weight; ?>g</span>
        </div>
    </div>

    <!-- Card Body: Intelligence Breakdown -->
    <div class="hs-card-body hs-p-4 hs-flex-1 hs-flex hs-flex-col hs-justify-between">
        <div>
            <!-- Micro Dimension Radars -->
            <div class="hs-dimension-grid hs-grid hs-grid-cols-3 hs-gap-2 hs-mb-3 hs-text-center hs-py-2 hs-border-y hs-border-subtle">
                <div>
                    <div class="hs-text-micro hs-text-muted hs-uppercase hs-font-bold">Safety</div>
                    <div class="hs-font-bold hs-text-xs hs-text-main"><?php echo $safetySc; ?>/100</div>
                </div>
                <div>
                    <div class="hs-text-micro hs-text-muted hs-uppercase hs-font-bold">Comfort</div>
                    <div class="hs-font-bold hs-text-xs hs-text-main"><?php echo $comfortSc; ?>/100</div>
                </div>
                <div>
                    <div class="hs-text-micro hs-text-muted hs-uppercase hs-font-bold">Value</div>
                    <div class="hs-font-bold hs-text-xs hs-text-main"><?php echo $valueSc; ?>/100</div>
                </div>
            </div>

            <!-- Spec Meta Line -->
            <div class="hs-text-xs hs-text-muted hs-mb-3">
                <span class="hs-font-medium hs-text-main"><?php echo $material; ?></span> · <span><?php echo $shape; ?></span>
            </div>
        </div>

        <!-- Card Footer: Price & Direct Actions -->
        <div class="hs-card-footer hs-pt-3 hs-flex hs-items-center hs-justify-between">
            <div>
                <div class="hs-text-micro hs-uppercase hs-text-muted hs-font-bold">MSRP</div>
                <div class="hs-font-black hs-text-lg hs-text-main">$<?php echo $price; ?></div>
            </div>

            <div class="hs-flex hs-items-center hs-gap-2">
                <!-- + Compare Checkbox Control -->
                <label class="hs-compare-checkbox-label hs-flex hs-items-center hs-gap-1 hs-text-xs hs-font-semibold hs-cursor-pointer">
                    <input type="checkbox" class="hs-compare-checkbox" data-helmet-id="<?php echo $id; ?>" data-helmet-title="<?php echo $title; ?>" data-helmet-score="<?php echo $score; ?>" data-helmet-price="$<?php echo $price; ?>" />
                    <span>+ Compare</span>
                </label>

                <a href="<?php echo $permalink; ?>" class="hs-btn hs-btn-secondary hs-btn-sm">View →</a>
            </div>
        </div>
    </div>
</div>
