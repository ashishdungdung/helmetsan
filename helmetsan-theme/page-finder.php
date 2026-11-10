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
            <div class="hs-finder-badge hs-mb-3">⚡ 6-DIMENSIONAL MATCH ENGINE</div>
            <h1 class="hs-finder-title">Find My Helmet</h1>
            <p class="hs-finder-subtitle">
                Correlate your motorcycle's aerodynamic tuck angle, riding discipline, cranial morphology, and safety homologation with our catalog of 2,216 helmets.
            </p>
        </header>

        <!-- Interactive Stepper Breadcrumbs Bar -->
        <nav class="hs-finder-stepper hs-mb-6" aria-label="Finder Steps">
            <div class="hs-finder-stepper__track">
                <button type="button" class="hs-stepper-pill is-active" data-goto-step="1">
                    <span class="hs-stepper-pill__num">1</span>
                    <span class="hs-stepper-pill__label">Machine</span>
                </button>
                <div class="hs-stepper-divider"></div>
                <button type="button" class="hs-stepper-pill" data-goto-step="2">
                    <span class="hs-stepper-pill__num">2</span>
                    <span class="hs-stepper-pill__label">Discipline</span>
                </button>
                <div class="hs-stepper-divider"></div>
                <button type="button" class="hs-stepper-pill" data-goto-step="3">
                    <span class="hs-stepper-pill__num">3</span>
                    <span class="hs-stepper-pill__label">Head Shape</span>
                </button>
                <div class="hs-stepper-divider"></div>
                <button type="button" class="hs-stepper-pill" data-goto-step="4">
                    <span class="hs-stepper-pill__num">4</span>
                    <span class="hs-stepper-pill__label">Safety</span>
                </button>
                <div class="hs-stepper-divider"></div>
                <button type="button" class="hs-stepper-pill" data-goto-step="5">
                    <span class="hs-stepper-pill__num">5</span>
                    <span class="hs-stepper-pill__label">Features</span>
                </button>
                <div class="hs-stepper-divider"></div>
                <button type="button" class="hs-stepper-pill" data-goto-step="6">
                    <span class="hs-stepper-pill__num">6</span>
                    <span class="hs-stepper-pill__label">Budget</span>
                </button>
            </div>
        </nav>

        <!-- Wizard Card Container -->
        <div id="hsHelmetFinderWizard" class="hs-finder-wizard hs-card hs-p-6 md:hs-p-8 hs-mb-10">
            <!-- Progress Bar -->
            <div class="hs-finder-progress-wrap hs-mb-6">
                <div class="hs-finder-progress-track">
                    <div id="hsFinderProgressBar" class="hs-finder-progress-fill" style="width: 16.66%;"></div>
                </div>
                <div class="hs-finder-progress-meta hs-flex hs-justify-between hs-text-xs hs-text-muted hs-mt-2">
                    <span id="hsFinderStepCount">Step 1 of 6</span>
                    <span id="hsFinderStepTitle" class="hs-font-bold hs-text-accent">Motorcycle & Aerodynamics</span>
                </div>
            </div>

            <form id="hsFinderForm" onsubmit="return false;">
                <!-- Step 1: Motorcycle & Aero -->
                <div class="hs-finder-step-panel" data-step="1">
                    <div class="hs-step-header hs-mb-4">
                        <span class="hs-step-kicker">DIMENSION 1 OF 6</span>
                        <h2 class="hs-step-heading">What machine do you ride?</h2>
                        <p class="hs-step-desc">Your motorcycle dictates your aerodynamic tuck angle (30°–90°) and windscreen deflection turbulence.</p>
                    </div>

                    <div class="hs-mb-4 hs-relative">
                        <label for="hsFinderBikeInput" class="hs-form-label">Search Your Motorcycle Model:</label>
                        <div class="hs-input-icon-wrap">
                            <span class="hs-input-icon">🔍</span>
                            <input type="text" id="hsFinderBikeInput" class="hs-input hs-w-full hs-input--with-icon" placeholder="Type your bike: e.g. Himalayan 450, MT-07, Ninja ZX-6R, GS 1250, Street Triple..." autocomplete="off">
                        </div>
                        <div id="hsBikeSuggestions" class="hs-autocomplete-dropdown hs-hidden"></div>
                    </div>

                    <!-- Quick-Select Popular Machines -->
                    <div class="hs-quick-machines hs-mb-4">
                        <div class="hs-flex hs-flex-wrap hs-items-center hs-gap-2">
                            <span class="hs-text-xxs hs-uppercase hs-font-black hs-text-muted">Popular:</span>
                            <button type="button" class="hs-quick-pill" data-bike="Royal Enfield Himalayan 450" data-cat="adv">RE Himalayan 450</button>
                            <button type="button" class="hs-quick-pill" data-bike="Kawasaki Ninja ZX-6R" data-cat="superbike">Ninja ZX-6R</button>
                            <button type="button" class="hs-quick-pill" data-bike="BMW R 1250 GS" data-cat="adv">BMW R 1250 GS</button>
                            <button type="button" class="hs-quick-pill" data-bike="Yamaha MT-07" data-cat="roadster">Yamaha MT-07</button>
                            <button type="button" class="hs-quick-pill" data-bike="Ducati Panigale V4" data-cat="superbike">Panigale V4</button>
                            <button type="button" class="hs-quick-pill" data-bike="Honda Rebel 500" data-cat="cruiser">Honda Rebel 500</button>
                        </div>
                    </div>

                    <!-- Selected Machine Feedback Callout -->
                    <div id="hsSelectedMachineCallout" class="hs-selected-bike-callout hs-hidden hs-mb-4 hs-p-3 hs-bg-surface-alt hs-rounded hs-border">
                        <div class="hs-flex hs-justify-between hs-items-center">
                            <div>
                                <span class="hs-text-xxs hs-uppercase hs-font-black hs-text-accent hs-block">✓ Active Motorcycle Profile</span>
                                <span id="hsSelectedBikeName" class="hs-font-bold hs-text-sm">Kawasaki Ninja 300 ABS</span>
                                <span id="hsSelectedBikeDetails" class="hs-text-xs hs-text-muted hs-block">Middleweight Sportbike · Aggressive Forward Tuck (35°)</span>
                            </div>
                            <button type="button" id="hsClearBikeBtn" class="hs-btn hs-btn-ghost hs-btn-xs" title="Clear motorcycle selection">✕ Clear</button>
                        </div>
                    </div>

                    <div class="hs-divider-text hs-my-4">
                        <span>OR SELECT RIDING CATEGORY & ERGONOMIC POSTURE</span>
                    </div>

                    <div class="hs-grid hs-grid-cols-2 md:hs-grid-cols-3 hs-gap-3" id="hsCategoryCards">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="bike_category" value="roadster" checked class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🏍️</span>
                                <span class="hs-tag hs-tag--sm">80° Lean</span>
                            </div>
                            <span class="hs-option-card__title">Naked / Roadster</span>
                            <span class="hs-option-card__desc">Upright roadster stance, direct laminar chest wind.</span>
                            <span class="hs-option-card__meta">Yamaha MT, KTM Duke, Triumph Street Triple</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="superbike" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🏁</span>
                                <span class="hs-tag hs-tag--sm">35° Tuck</span>
                            </div>
                            <span class="hs-option-card__title">Superbike / Sport</span>
                            <span class="hs-option-card__desc">Forward race tuck, high-velocity aerodynamic downforce.</span>
                            <span class="hs-option-card__meta">Ducati Panigale, BMW S1000RR, Ninja ZX</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="adv" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🏔️</span>
                                <span class="hs-tag hs-tag--sm">90° Upright</span>
                            </div>
                            <span class="hs-option-card__title">Adventure / ADV</span>
                            <span class="hs-option-card__desc">Commanding upright stance, tall windscreen vortices.</span>
                            <span class="hs-option-card__meta">BMW GS, RE Himalayan, Ténéré 700</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="tourer" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🛣️</span>
                                <span class="hs-tag hs-tag--sm">75° Bubble</span>
                            </div>
                            <span class="hs-option-card__title">Grand Tourer / Bagger</span>
                            <span class="hs-option-card__desc">Neutral touring stance, cockpit still-air bubble isolation.</span>
                            <span class="hs-option-card__meta">Honda Gold Wing, BMW RT, Harley Road Glide</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="cruiser" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🦅</span>
                                <span class="hs-tag hs-tag--sm">Relaxed Stance</span>
                            </div>
                            <span class="hs-option-card__title">Cruiser / Heritage</span>
                            <span class="hs-option-card__desc">Relaxed low-slung stance, low-speed highway buffeting.</span>
                            <span class="hs-option-card__meta">Harley Street Bob, Indian Scout, Meteor 350</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="bike_category" value="scooter" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🛵</span>
                                <span class="hs-tag hs-tag--sm">City Agility</span>
                            </div>
                            <span class="hs-option-card__title">Urban Mobility / Scooter</span>
                            <span class="hs-option-card__desc">Step-through upright city posture, stop-and-go ventilation.</span>
                            <span class="hs-option-card__meta">Vespa, Yamaha XMAX, Honda PCX</span>
                        </label>
                    </div>
                </div>

                <!-- Step 2: Riding Discipline -->
                <div class="hs-finder-step-panel hs-hidden" data-step="2">
                    <div class="hs-step-header hs-mb-4">
                        <span class="hs-step-kicker">DIMENSION 2 OF 6</span>
                        <h2 class="hs-step-heading">What is your primary riding discipline?</h2>
                        <p class="hs-step-desc">Different riding environments prioritize unique acoustic, field of view, and aero requirements.</p>
                    </div>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 hs-gap-3">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="riding_style" value="commute" checked class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🏙️</span>
                                <span class="hs-tag hs-tag--sm">30–80 km/h</span>
                            </div>
                            <span class="hs-option-card__title">Daily Urban Commute</span>
                            <span class="hs-option-card__desc">Wide 180° peripheral traffic awareness, lightweight (<1,450g), rapid on/off latching.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="riding_style" value="highway_touring" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🌅</span>
                                <span class="hs-tag hs-tag--sm">100–140 km/h</span>
                            </div>
                            <span class="hs-option-card__title">Long Highway Touring</span>
                            <span class="hs-option-card__desc">Whisper-quiet acoustic cabin (<84 dB), internal drop-down sun shield, all-day neck comfort.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="riding_style" value="sport_canyon" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">⛰️</span>
                                <span class="hs-tag hs-tag--sm">80–180 km/h</span>
                            </div>
                            <span class="hs-option-card__title">Sport & Canyon Carving</span>
                            <span class="hs-option-card__desc">Pinpoint lateral stability, aggressive brow cooling, snug cheek hold during apex transitions.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="riding_style" value="track" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🏎️</span>
                                <span class="hs-tag hs-tag--sm">160–300+ km/h</span>
                            </div>
                            <span class="hs-option-card__title">Track Days & Closed Circuit</span>
                            <span class="hs-option-card__desc">FIM racing homologation, high-camber upper eyeport for tank tuck, negative lift spoiler.</span>
                        </label>

                        <label class="hs-option-card md:hs-col-span-2">
                            <input type="radio" name="riding_style" value="adventure" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🧭</span>
                                <span class="hs-tag hs-tag--sm">All-Terrain</span>
                            </div>
                            <span class="hs-option-card__title">All-Terrain Adventure / Dual-Sport</span>
                            <span class="hs-option-card__desc">Dual-mode aerodynamic peak visor, wide goggle eyeport, high-volume dust intake filtration.</span>
                        </label>
                    </div>
                </div>

                <!-- Step 3: Cranial Morphology & Ergonomics -->
                <div class="hs-finder-step-panel hs-hidden" data-step="3">
                    <div class="hs-step-header hs-mb-4">
                        <span class="hs-step-kicker">DIMENSION 3 OF 6</span>
                        <h2 class="hs-step-heading">Head Shape & Sizing Ergonomics</h2>
                        <p class="hs-step-desc">A helmet must match your cranial contour to prevent painful forehead hotspot pressure.</p>
                    </div>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-3 hs-gap-3 hs-mb-6">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="head_shape" value="intermediate_oval" checked class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🥚</span>
                                <span class="hs-tag hs-tag--sm">75% of Riders</span>
                            </div>
                            <span class="hs-option-card__title">Intermediate Oval</span>
                            <span class="hs-option-card__desc">Slightly longer front-to-back than ear-to-ear. Balanced global standard fit.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="head_shape" value="round_oval" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">⚪</span>
                                <span class="hs-tag hs-tag--sm">15% of Riders</span>
                            </div>
                            <span class="hs-option-card__title">Round Oval</span>
                            <span class="hs-option-card__desc">Equal length and width. Extra temple clearance popular in Asian markets.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="head_shape" value="long_oval" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🏉</span>
                                <span class="hs-tag hs-tag--sm">10% of Riders</span>
                            </div>
                            <span class="hs-option-card__title">Long Oval</span>
                            <span class="hs-option-card__desc">Noticeably longer forehead-to-back with narrower side temple clearance.</span>
                        </label>
                    </div>

                    <!-- Eyewear & Sizing Options -->
                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 hs-gap-4">
                        <div class="hs-card hs-p-4 hs-bg-surface-alt">
                            <label class="hs-checkbox-label hs-flex hs-items-start hs-gap-3 hs-cursor-pointer">
                                <input type="checkbox" id="hsFinderEyewear" name="eyewear" value="1" class="hs-checkbox hs-mt-1">
                                <div>
                                    <strong class="hs-block">I wear glasses or sunglasses 👓</strong>
                                    <span class="hs-text-xs hs-text-muted">Prioritizes helmets with dedicated EPS eyeglass groove channels to prevent temple arm pinch.</span>
                                </div>
                            </label>
                        </div>

                        <div class="hs-card hs-p-4 hs-bg-surface-alt">
                            <label for="hsFinderRiderSize" class="hs-form-label hs-mb-1">Target Helmet Size (Optional):</label>
                            <select id="hsFinderRiderSize" class="hs-select hs-w-full">
                                <option value="">Any Size / Flexible</option>
                                <option value="XS">XS (53–54 cm)</option>
                                <option value="S">S (55–56 cm)</option>
                                <option value="M" selected>M (57–58 cm) — Most Common</option>
                                <option value="L">L (59–60 cm)</option>
                                <option value="XL">XL (61–62 cm)</option>
                                <option value="2XL">2XL (63–64 cm)</option>
                            </select>
                        </div>
                    </div>

                    <div class="hs-mt-4 hs-text-center">
                        <button type="button" id="hsToggleSizeGuideBtn" class="hs-btn hs-btn-ghost hs-btn-xs">📏 How to Measure Your Head & Find Your Shape ▾</button>
                    </div>
                    <div id="hsSizeGuidePanel" class="hs-size-guide-drawer hs-hidden hs-mt-3 hs-p-4 hs-bg-surface-alt hs-rounded-lg hs-border">
                        <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 hs-gap-4 hs-text-left">
                            <div>
                                <h4 class="hs-text-xs hs-font-black hs-text-accent hs-uppercase hs-mb-1">How to Measure</h4>
                                <p class="hs-text-xs hs-text-muted hs-mb-2">Wrap a flexible cloth tape measure around the widest portion of your head, approximately 1 inch (2.5 cm) above your eyebrows and directly above your ears.</p>
                                <span class="hs-tag hs-tag--sm">Tape should feel snug, not tight</span>
                            </div>
                            <div>
                                <h4 class="hs-text-xs hs-font-black hs-text-accent hs-uppercase hs-mb-1">Cranial Shape Test</h4>
                                <p class="hs-text-xs hs-text-muted hs-mb-2">Have someone look down at the top of your head from above. If your head is noticeably narrow and longer front-to-back, choose <strong>Long Oval</strong>. If it appears nearly circular, choose <strong>Round Oval</strong>. 75% of riders fall in <strong>Intermediate Oval</strong>.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Safety & Homologation -->
                <div class="hs-finder-step-panel hs-hidden" data-step="4">
                    <div class="hs-step-header hs-mb-4">
                        <span class="hs-step-kicker">DIMENSION 4 OF 6</span>
                        <h2 class="hs-step-heading">Safety Standards & Certification Priority</h2>
                        <p class="hs-step-desc">All helmets in our catalog are authentic, but you can prioritize specific rigorous impact protocols.</p>
                    </div>

                    <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-3 hs-gap-3">
                        <label class="hs-option-card is-selected">
                            <input type="radio" name="safety_priority" value="max_safety" checked class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🛡️</span>
                                <span class="hs-tag hs-tag--sm hs-tag--accent">Recommended</span>
                            </div>
                            <span class="hs-option-card__title">Maximum Protection</span>
                            <span class="hs-option-card__desc">Prioritizes ECE 22.06, SHARP 4-5 Stars, and Multi-Density EPS rotational energy mitigation.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="safety_priority" value="track_homologated" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">🏁</span>
                                <span class="hs-tag hs-tag--sm">Circuit Legal</span>
                            </div>
                            <span class="hs-option-card__title">Track & Race Legal</span>
                            <span class="hs-option-card__desc">Mandates FIM FRHPhe-01/02 or Snell M2025 homologation for competition circuits.</span>
                        </label>

                        <label class="hs-option-card">
                            <input type="radio" name="safety_priority" value="standard" class="hs-hidden">
                            <div class="hs-option-card__top">
                                <span class="hs-option-card__icon">⚖️</span>
                                <span class="hs-tag hs-tag--sm">Road Legal</span>
                            </div>
                            <span class="hs-option-card__title">Standard Road Legal</span>
                            <span class="hs-option-card__desc">Compliant DOT FMVSS 218 / ECE 22.05 baseline for general street legal transit.</span>
                        </label>
                    </div>
                </div>

                <!-- Step 5: Features & Peripheral Tech -->
                <div class="hs-finder-step-panel hs-hidden" data-step="5">
                    <div class="hs-step-header hs-mb-4">
                        <span class="hs-step-kicker">DIMENSION 5 OF 6</span>
                        <h2 class="hs-step-heading">Which features and tech are essential to you?</h2>
                        <p class="hs-step-desc">Select any specific functional requirements you want included:</p>
                    </div>

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
                                <span class="hs-text-xs hs-text-muted hs-block">Factory-cutout 40mm EPS speaker pockets for Cardo or Sena intercoms.</span>
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

                        <label class="hs-feature-pill">
                            <input type="checkbox" name="features" value="eqrs" class="hs-checkbox">
                            <span class="hs-feature-pill__icon">🚑</span>
                            <div>
                                <strong>Emergency Quick Release (E.Q.R.S.)</strong>
                                <span class="hs-text-xs hs-text-muted hs-block">Allows paramedics to safely remove cheek pads during post-impact trauma.</span>
                            </div>
                        </label>

                        <label class="hs-feature-pill">
                            <input type="checkbox" name="features" value="optical_class_1" class="hs-checkbox">
                            <span class="hs-feature-pill__icon">👁️</span>
                            <div>
                                <strong>Optical Class 1 Shield</strong>
                                <span class="hs-text-xs hs-text-muted hs-block">Zero optical distortion across the entire field of peripheral view.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Step 6: Budget & Currency -->
                <div class="hs-finder-step-panel hs-hidden" data-step="6">
                    <div class="hs-step-header hs-mb-4">
                        <span class="hs-step-kicker">DIMENSION 6 OF 6</span>
                        <h2 class="hs-step-heading">What is your target budget ceiling?</h2>
                        <p class="hs-step-desc">Set your local currency and maximum price ceiling, or explore all tiers.</p>
                    </div>

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
                                <label for="hsFinderBudget" class="hs-form-label">Budget Ceiling:</label>
                                <span id="hsBudgetDisplay" class="hs-font-black hs-text-accent">$600 USD</span>
                            </div>
                            <input type="range" id="hsFinderBudget" min="150" max="1500" step="50" value="600" class="hs-range hs-w-full">
                            <div class="hs-flex hs-justify-between hs-text-xs hs-text-muted hs-mt-1">
                                <span>$150 (Entry)</span>
                                <span>$600 (Mid-Range)</span>
                                <span>$1,500+ (No Limit)</span>
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

        <!-- Sticky Mobile Bottom Bar -->
        <div id="hsFinderMobileBar" class="hs-finder-mobile-bar md:hs-hidden">
            <button type="button" id="hsFinderMobilePrevBtn" class="hs-btn hs-btn-ghost hs-btn-sm" disabled>← Back</button>
            <span id="hsFinderMobileProgress" class="hs-text-xs hs-text-muted hs-font-bold">Step 1/6</span>
            <button type="button" id="hsFinderMobileNextBtn" class="hs-btn hs-btn-primary hs-btn-sm">Next →</button>
        </div>

        <!-- Results Section Container -->
        <section id="hsFinderResults" class="hs-finder-results hs-hidden">
            <!-- Dynamically populated by helmet-finder.js -->
        </section>
    </div>
</main>

<?php get_footer(); ?>
