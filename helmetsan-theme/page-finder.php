<?php
/**
 * Template Name: Find My Helmet - Guided Advisor
 *
 * @package HelmetsanTheme
 */

get_header();
?>

<main id="primary" class="site-main hs-finder-page">
    <div class="hs-container hs-py-8">
        <!-- Hero Header -->
        <header class="hs-finder-hero hs-text-center hs-mb-8">
            <div class="hs-finder-badge hs-mb-2">⚡ 6-DIMENSIONAL MATCH ENGINE</div>
            <h1 class="hs-finder-title">Find My Helmet</h1>
            <p class="hs-finder-subtitle">
                Correlate your motorcycle's aerodynamic tuck angle, riding discipline, cranial morphology, and safety requirements with our comprehensive catalog.
            </p>
        </header>

        <!-- Wizard Container -->
        <div id="hsHelmetFinderWizard" class="hs-finder-wizard hs-card hs-p-6 md:hs-p-8 hs-mb-10">
            <!-- Progress Bar -->
            <div class="hs-finder-progress-wrap hs-mb-6">
                <div class="hs-finder-progress-track">
                    <div id="hsFinderProgressBar" class="hs-finder-progress-fill" style="width: 16.66%;"></div>
                </div>
                <div class="hs-finder-progress-meta hs-flex hs-justify-between hs-text-xs hs-text-muted hs-mt-2">
                    <span id="hsFinderStepCount">Step 1 of 6</span>
                    <span id="hsFinderStepTitle">Motorcycle & Aerodynamics</span>
                </div>
            </div>

            <form id="hsFinderForm" onsubmit="return false;">
                <!-- Step 1: Motorcycle & Aero -->
                <div class="hs-finder-step-panel" data-step="1">
                    <h2 class="hs-step-heading">What machine do you ride?</h2>
                    <p class="hs-step-desc">Your motorcycle dictates riding tuck angle (30°–90°) and windscreen deflection turbulence.</p>

                    <div class="hs-mb-4 hs-relative">
                        <label for="hsFinderBikeInput" class="hs-form-label">Search Your Motorcycle Model:</label>
                        <input type="text" id="hsFinderBikeInput" class="hs-input hs-w-full" placeholder="e.g. Himalayan 450, MT-07, Ninja ZX-6R, R 1250 GS..." autocomplete="off">
                        <div id="hsBikeSuggestions" class="hs-autocomplete-dropdown hs-hidden"></div>
                    </div>

                    <div class="hs-text-center hs-my-3 hs-text-xs hs-text-muted hs-font-bold">— OR SELECT RIDING CATEGORY —</div>

                    <div class="hs-grid hs-grid-cols-2 md:hs-grid-cols-3 hs-gap-3" id="hsCategoryCards">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="bike_category" value="roadster" checked class="hs-hidden">
                            <span class="hs-option-card__icon">🏍️</span>
                            <span class="hs-option-card__title">Naked / Roadster</span>
                            <span class="hs-option-card__desc">80° upright posture, direct laminar chest wind</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="superbike" class="hs-hidden">
                            <span class="hs-option-card__icon">🏁</span>
                            <span class="hs-option-card__title">Superbike / Sport</span>
                            <span class="hs-option-card__desc">35° aggressive forward tuck, high-velocity airflow</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="adv" class="hs-hidden">
                            <span class="hs-option-card__icon">🏔️</span>
                            <span class="hs-option-card__title">Adventure / ADV</span>
                            <span class="hs-option-card__desc">90° commanding stance, tall windscreen vortices</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="tourer" class="hs-hidden">
                            <span class="hs-option-card__icon">🛣️</span>
                            <span class="hs-option-card__title">Grand Tourer</span>
                            <span class="hs-option-card__desc">75° neutral stance, still-air bubble isolation</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="cruiser" class="hs-hidden">
                            <span class="hs-option-card__icon">🦅</span>
                            <span class="hs-option-card__title">Cruiser / Heritage</span>
                            <span class="hs-option-card__desc">Relaxed upright stance, low-speed road buffeting</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="scooter" class="hs-hidden">
                            <span class="hs-option-card__icon">🛵</span>
                            <span class="hs-option-card__title">Urban Commuter</span>
                            <span class="hs-option-card__desc">Upright city agility, stop-and-go airflow</span>
                        </label>
                    </div>
                </div>

                <!-- Step 2: Riding Discipline -->
                <div class="hs-finder-step-panel hs-hidden" data-step="2">
                    <h2 class="hs-step-heading">What is your primary riding discipline?</h2>
                    <p class="hs-step-desc">Different riding environments prioritize unique acoustic, field of view, and aero requirements.</p>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 hs-gap-3">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="riding_style" value="commute" checked class="hs-hidden">
                            <span class="hs-option-card__icon">🏙️</span>
                            <span class="hs-option-card__title">Daily Urban Commute</span>
                            <span class="hs-option-card__desc">Wide 180° peripheral traffic awareness, lightweight (<1,450g), easy on/off latching.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="riding_style" value="highway_touring" class="hs-hidden">
                            <span class="hs-option-card__icon">🌅</span>
                            <span class="hs-option-card__title">Long Highway Touring</span>
                            <span class="hs-option-card__desc">Whisper-quiet acoustic cabin (<84 dB), internal sun shield, all-day plush neck comfort.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="riding_style" value="sport_canyon" class="hs-hidden">
                            <span class="hs-option-card__icon">⛰️</span>
                            <span class="hs-option-card__title">Sport & Canyon Carving</span>
                            <span class="hs-option-card__desc">Pinpoint lateral stability, aggressive brow cooling, snug cheek hold during apex transitions.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="riding_style" value="track" class="hs-hidden">
                            <span class="hs-option-card__icon">🏎️</span>
                            <span class="hs-option-card__title">Track Days & Closed Circuit</span>
                            <span class="hs-option-card__desc">FIM racing homologation, chin-on-tank upper field of vision, negative aero lift spoiler.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="riding_style" value="adventure" class="hs-hidden">
                            <span class="hs-option-card__icon">🧭</span>
                            <span class="hs-option-card__title">All-Terrain Adventure / ADV</span>
                            <span class="hs-option-card__desc">Dual-mode sun peak, wide goggle eyeport, high-volume dust intake filtration.</span>
                        </label>
                    </div>
                </div>

                <!-- Step 3: Cranial Morphology & Ergonomics -->
                <div class="hs-finder-step-panel hs-hidden" data-step="3">
                    <h2 class="hs-step-heading">What is your head shape & ergonomic profile?</h2>
                    <p class="hs-step-desc">A helmet must match your cranial shape to avoid painful forehead hotspot pinch.</p>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-3 hs-gap-3 hs-mb-6">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="head_shape" value="intermediate_oval" checked class="hs-hidden">
                            <span class="hs-option-card__icon">🥚</span>
                            <span class="hs-option-card__title">Intermediate Oval</span>
                            <span class="hs-option-card__desc">Slightly longer front-to-back than ear-to-ear. (~75% of riders globally).</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="head_shape" value="round_oval" class="hs-hidden">
                            <span class="hs-option-card__icon">⚪</span>
                            <span class="hs-option-card__title">Round Oval</span>
                            <span class="hs-option-card__desc">Nearly equal length and width, wider ear-to-ear clearance.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="head_shape" value="long_oval" class="hs-hidden">
                            <span class="hs-option-card__icon">🏉</span>
                            <span class="hs-option-card__title">Long Oval</span>
                            <span class="hs-option-card__desc">Noticeably longer forehead-to-occiput with narrower temples.</span>
                        </label>
                    </div>

                    <div class="hs-card hs-p-4 hs-bg-surface-alt">
                        <label class="hs-checkbox-label hs-flex hs-items-center hs-gap-3 hs-cursor-pointer">
                            <input type="checkbox" id="hsFinderEyewear" name="eyewear" value="1" class="hs-checkbox">
                            <div>
                                <strong class="hs-block">I wear prescription glasses or sunglasses</strong>
                                <span class="hs-text-xs hs-text-muted">Matches helmets with dedicated EPS eyeglass groove channels to prevent temple arm pinch.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Step 4: Safety & Homologation -->
                <div class="hs-finder-step-panel hs-hidden" data-step="4">
                    <h2 class="hs-step-heading">What is your safety certification requirement?</h2>
                    <p class="hs-step-desc">All helmets in our catalog are authentic, but you can prioritize specific homologations.</p>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-3 hs-gap-3">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="safety_priority" value="max_safety" checked class="hs-hidden">
                            <span class="hs-option-card__icon">🛡️</span>
                            <span class="hs-option-card__title">Maximum Protection</span>
                            <span class="hs-option-card__desc">Prioritizes ECE 22.06, SHARP 4-5 Stars, and Multi-Density EPS rotational mitigation.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="safety_priority" value="track_homologated" class="hs-hidden">
                            <span class="hs-option-card__icon">🏁</span>
                            <span class="hs-option-card__title">Track & Race Legal</span>
                            <span class="hs-option-card__desc">Requires FIM FRHPhe-01/02 or Snell M2025 homologation for competition circuits.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="safety_priority" value="standard" class="hs-hidden">
                            <span class="hs-option-card__icon">⚖️</span>
                            <span class="hs-option-card__title">Standard Road Legal</span>
                            <span class="hs-option-card__desc">Compliant DOT FMVSS 218 / ECE 22.05 baseline for general street legal transit.</span>
                        </label>
                    </div>
                </div>

                <!-- Step 5: Features & Peripheral Tech -->
                <div class="hs-finder-step-panel hs-hidden" data-step="5">
                    <h2 class="hs-step-heading">Which features and tech are essential to you?</h2>
                    <p class="hs-step-desc">Select any specific functional requirements you want included:</p>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 hs-gap-3">
                        <label class="hs-feature-pill">
                            <input type="checkbox" name="features" value="sun_visor" checked class="hs-checkbox">
                            <span class="hs-feature-pill__icon">🕶️</span>
                            <div>
                                <strong>Internal Drop-Down Sun Visor</strong>
                                <span class="hs-text-xs hs-text-muted hs-block">Instant glare defense without carrying an extra tinted shield.</span>
                            </div>
                        </label>

                        <label class="hs-feature-pill">
                            <input type="checkbox" name="features" value="bluetooth_ready" checked class="hs-checkbox">
                            <span class="hs-feature-pill__icon">🎧</span>
                            <div>
                                <strong>Bluetooth Speaker Cavities Ready</strong>
                                <span class="hs-text-xs hs-text-muted hs-block">Pre-cut 40mm EPS speaker pockets for Cardo or Sena intercoms.</span>
                            </div>
                        </label>

                        <label class="hs-feature-pill">
                            <input type="checkbox" name="features" value="pinlock_included" checked class="hs-checkbox">
                            <span class="hs-feature-pill__icon">🌫️</span>
                            <div>
                                <strong>Pinlock 120 Anti-Fog Included</strong>
                                <span class="hs-text-xs hs-text-muted hs-block">Hydrophilic dual-pane lens chamber prevents cold-weather visor fogging.</span>
                            </div>
                        </label>

                        <label class="hs-feature-pill">
                            <input type="checkbox" name="features" value="carbon_fiber" class="hs-checkbox">
                            <span class="hs-feature-pill__icon">🪶</span>
                            <div>
                                <strong>Ultra-Lightweight Carbon Fiber</strong>
                                <span class="hs-text-xs hs-text-muted hs-block">Lightweight composite (<1,350g) to eliminate cervical neck fatigue.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Step 6: Budget & Currency -->
                <div class="hs-finder-step-panel hs-hidden" data-step="6">
                    <h2 class="hs-step-heading">What is your target budget?</h2>
                    <p class="hs-step-desc">Set your currency and maximum price ceiling, or explore all tiers.</p>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 hs-gap-6 hs-items-center">
                        <div>
                            <label for="hsFinderCurrency" class="hs-form-label">Currency:</label>
                            <select id="hsFinderCurrency" class="hs-select hs-w-full">
                                <option value="USD" selected>USD ($) — United States</option>
                                <option value="EUR">EUR (€) — Eurozone</option>
                                <option value="GBP">GBP (£) — United Kingdom</option>
                                <option value="INR">INR (₹) — India</option>
                            </select>
                        </div>

                        <div>
                            <div class="hs-flex hs-justify-between hs-mb-1">
                                <label for="hsFinderBudget" class="hs-form-label">Budget Limit:</label>
                                <span id="hsBudgetDisplay" class="hs-font-black hs-text-accent">$600 USD</span>
                            </div>
                            <input type="range" id="hsFinderBudget" min="150" max="1500" step="50" value="600" class="hs-range hs-w-full">
                            <div class="hs-flex hs-justify-between hs-text-xs hs-text-muted hs-mt-1">
                                <span>Entry ($150)</span>
                                <span>Mid-Range ($600)</span>
                                <span>Premium Flagship ($1,500+)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wizard Actions Bar -->
                <div class="hs-finder-actions hs-flex hs-justify-between hs-items-center hs-mt-8 hs-pt-6 hs-border-t">
                    <button type="button" id="hsFinderPrevBtn" class="hs-btn hs-btn-ghost" disabled>← Back</button>
                    <button type="button" id="hsFinderNextBtn" class="hs-btn hs-btn-primary">Next Step →</button>
                </div>
            </form>
        </div>

        <!-- Results Section Container -->
        <section id="hsFinderResults" class="hs-finder-results hs-hidden">
            <!-- Dynamically populated by helmet-finder.js -->
        </section>
    </div>
</main>

<?php get_footer(); ?>
