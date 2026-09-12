<?php
/**
 * Helmetsan Persistent Floating Comparison Bar Component
 */
?>

<div id="hsComparisonTray" class="hs-comparison-tray hs-fixed hs-bottom-0 hs-left-0 hs-right-0 hs-z-50 hs-p-4 hs-bg-dark hs-text-white hs-border-t hs-border-subtle hs-shadow-xl hs-hidden">
    <div class="hs-container hs-flex hs-items-center hs-justify-between">
        <div class="hs-flex hs-items-center hs-gap-4">
            <div class="hs-flex hs-items-center hs-gap-2">
                <span class="hs-badge hs-badge-accent">COMPARE</span>
                <span id="hsCompareCount" class="hs-font-bold hs-text-sm">0 helmets selected</span>
            </div>
            <div id="hsCompareChips" class="hs-hidden md:hs-flex hs-items-center hs-gap-2">
                <!-- Selected helmet chips injected dynamically via comparison.js -->
            </div>
        </div>

        <div class="hs-flex hs-items-center hs-gap-3">
            <button id="hsClearCompare" class="hs-btn hs-btn-ghost hs-btn-sm hs-text-muted hover:hs-text-white">Clear All</button>
            <a id="hsLaunchCompareBtn" href="<?php echo esc_url(helmetsan_url('/comparison/')); ?>" class="hs-btn hs-btn-primary hs-btn-sm">Compare Now →</a>
        </div>
    </div>
</div>
