/**
 * Helmetsan Interactive Guided Helmet Finder Engine Controller
 * Connects directly to the 6-Dimensional Matching Engine via REST API
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const finderWrap = document.getElementById('hsHelmetFinderWizard');
        if (!finderWrap) return;

        const apiBase = (window.hsFinderData && window.hsFinderData.apiBase) 
            ? window.hsFinderData.apiBase.replace(/\/$/, '') 
            : '/wp-json/hs/v1';

        let currentStep = 1;
        const totalSteps = 6;

        const stepTitles = [
            'Motorcycle & Aerodynamics',
            'Riding Discipline',
            'Cranial Morphology & Ergonomics',
            'Safety Standards & Homologation',
            'Features & Peripheral Tech',
            'Target Budget Ceiling'
        ];

        const formData = {
            bike: '',
            bike_category: 'roadster',
            riding_style: 'commute',
            head_shape: 'intermediate_oval',
            eyewear: false,
            rider_size: 'M',
            safety_priority: 'max_safety',
            features: ['sun_visor', 'bluetooth_ready', 'pinlock_included'],
            budget_max: 600,
            currency: 'USD'
        };

        // Cache matches for instant 0ms client-side filtering & sorting
        let rawMatches = [];
        let activeSort = 'score';
        let activeTypeFilter = 'all';
        let activeBrandFilter = 'all';

        // DOM Elements
        const nextBtn = document.getElementById('hsFinderNextBtn');
        const prevBtn = document.getElementById('hsFinderPrevBtn');
        const mobileNextBtn = document.getElementById('hsFinderMobileNextBtn');
        const mobilePrevBtn = document.getElementById('hsFinderMobilePrevBtn');
        const mobileProgress = document.getElementById('hsFinderMobileProgress');
        const stepCountEl = document.getElementById('hsFinderStepCount');
        const stepTitleEl = document.getElementById('hsFinderStepTitle');
        const progressBar = document.getElementById('hsFinderProgressBar');
        const resultsContainer = document.getElementById('hsFinderResults');
        const stepperPills = document.querySelectorAll('.hs-stepper-pill');

        const currencySymbols = {
            'USD': '$',
            'EUR': '€',
            'GBP': '£',
            'INR': '₹'
        };

        // 1. Stepper Breadcrumb Click Navigation
        stepperPills.forEach(pill => {
            pill.addEventListener('click', function() {
                const targetStep = parseInt(this.getAttribute('data-goto-step'), 10);
                if (targetStep >= 1 && targetStep <= totalSteps) {
                    currentStep = targetStep;
                    updateWizardStep();
                }
            });
        });

        // 2. Radio Card Handlers
        document.querySelectorAll('.hs-option-card').forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (!radio) return;

                const name = radio.name;
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                    const parent = r.closest('.hs-option-card');
                    if (parent) parent.classList.remove('is-selected');
                });

                radio.checked = true;
                this.classList.add('is-selected');

                if (name === 'bike_category') formData.bike_category = radio.value;
                if (name === 'riding_style') formData.riding_style = radio.value;
                if (name === 'head_shape') formData.head_shape = radio.value;
                if (name === 'safety_priority') formData.safety_priority = radio.value;
            });
        });

        // 3. Eyewear & Sizing
        const eyewearCheck = document.getElementById('hsFinderEyewear');
        if (eyewearCheck) {
            eyewearCheck.addEventListener('change', function() {
                formData.eyewear = this.checked;
            });
        }
        const sizeSelect = document.getElementById('hsFinderRiderSize');
        if (sizeSelect) {
            sizeSelect.addEventListener('change', function() {
                formData.rider_size = this.value;
            });
        }

        // 4. Feature Checkboxes
        document.querySelectorAll('input[name="features"]').forEach(chk => {
            chk.addEventListener('change', function() {
                const checked = [];
                document.querySelectorAll('input[name="features"]:checked').forEach(c => {
                    checked.push(c.value);
                });
                formData.features = checked;
            });
        });

        // 5. Budget & Currency Slider
        const budgetSlider = document.getElementById('hsFinderBudget');
        const currencySelect = document.getElementById('hsFinderCurrency');
        const budgetDisplay = document.getElementById('hsBudgetDisplay');

        function updateBudgetDisplay() {
            if (!budgetSlider || !currencySelect || !budgetDisplay) return;
            const cur = currencySelect.value;
            const val = parseInt(budgetSlider.value, 10);
            formData.currency = cur;
            formData.budget_max = val;
            const sym = currencySymbols[cur] || '$';
            budgetDisplay.textContent = val >= 1500 ? `No Budget Limit (${sym}1,500+)` : `${sym}${val.toLocaleString()} ${cur}`;
        }

        if (budgetSlider) budgetSlider.addEventListener('input', updateBudgetDisplay);
        if (currencySelect) currencySelect.addEventListener('change', updateBudgetDisplay);

        // Helper: Select Motorcycle
        function selectMotorcycle(title, cat, brand, ridingPosition) {
            if (!title) return;
            formData.bike = title;
            if (bikeInput) bikeInput.value = title;
            if (bikeDropdown) bikeDropdown.classList.add('hs-hidden');

            // Category normalization
            if (cat) {
                const catLower = cat.toLowerCase();
                let matchedCategory = 'roadster';
                if (catLower.includes('superbike') || catLower.includes('sport')) matchedCategory = 'superbike';
                else if (catLower.includes('adventure') || catLower.includes('adv')) matchedCategory = 'adv';
                else if (catLower.includes('tour')) matchedCategory = 'tourer';
                else if (catLower.includes('cruiser')) matchedCategory = 'cruiser';
                else if (catLower.includes('scooter')) matchedCategory = 'scooter';

                formData.bike_category = matchedCategory;
                const radio = document.querySelector(`input[name="bike_category"][value="${matchedCategory}"]`);
                if (radio) {
                    radio.checked = true;
                    document.querySelectorAll('input[name="bike_category"]').forEach(r => {
                        const p = r.closest('.hs-option-card');
                        if (p) p.classList.remove('is-selected');
                    });
                    const card = radio.closest('.hs-option-card');
                    if (card) card.classList.add('is-selected');
                }
            }

            // Update Selected Machine Callout Feedback
            const callout = document.getElementById('hsSelectedMachineCallout');
            const nameEl = document.getElementById('hsSelectedBikeName');
            const detailsEl = document.getElementById('hsSelectedBikeDetails');
            if (callout && nameEl) {
                nameEl.textContent = title;
                const pos = ridingPosition || (cat ? cat.toUpperCase() : 'Standard Posture');
                if (detailsEl) detailsEl.textContent = `${brand || 'Motorcycle'} · ${pos}`;
                callout.classList.remove('hs-hidden');
            }
        }

        // Quick-Select Popular Machines
        document.querySelectorAll('.hs-quick-pill').forEach(pill => {
            pill.addEventListener('click', function() {
                const bike = this.getAttribute('data-bike');
                const cat = this.getAttribute('data-cat');
                selectMotorcycle(bike, cat);
            });
        });

        // Clear Motorcycle Button
        const clearBikeBtn = document.getElementById('hsClearBikeBtn');
        if (clearBikeBtn) {
            clearBikeBtn.addEventListener('click', function() {
                formData.bike = '';
                if (bikeInput) bikeInput.value = '';
                const callout = document.getElementById('hsSelectedMachineCallout');
                if (callout) callout.classList.add('hs-hidden');
            });
        }

        // Size Guide Drawer Toggle
        const toggleSizeGuideBtn = document.getElementById('hsToggleSizeGuideBtn');
        const sizeGuidePanel = document.getElementById('hsSizeGuidePanel');
        if (toggleSizeGuideBtn && sizeGuidePanel) {
            toggleSizeGuideBtn.addEventListener('click', function() {
                sizeGuidePanel.classList.toggle('hs-hidden');
                const isHidden = sizeGuidePanel.classList.contains('hs-hidden');
                this.textContent = isHidden 
                    ? '📏 How to Measure Your Head & Find Your Shape ▾' 
                    : '📏 Close Sizing Guide ▴';
            });
        }

        // 6. Motorcycle Autocomplete
        const bikeInput = document.getElementById('hsFinderBikeInput');
        const bikeDropdown = document.getElementById('hsBikeSuggestions');
        let bikeDebounceTimer = null;

        if (bikeInput && bikeDropdown) {
            bikeInput.addEventListener('input', function() {
                const query = this.value.trim();
                formData.bike = query;

                clearTimeout(bikeDebounceTimer);
                if (query.length < 2) {
                    bikeDropdown.classList.add('hs-hidden');
                    bikeDropdown.innerHTML = '';
                    return;
                }

                bikeDebounceTimer = setTimeout(async () => {
                    try {
                        const res = await fetch(`${apiBase}/finder/bikes?q=${encodeURIComponent(query)}&limit=6`);
                        if (!res.ok) return;
                        const data = await res.json();
                        const bikes = data.bikes || [];

                        if (bikes.length === 0) {
                            bikeDropdown.classList.add('hs-hidden');
                            return;
                        }

                        bikeDropdown.innerHTML = bikes.map(b => `
                            <div class="hs-autocomplete-item" data-title="${b.title}" data-category="${b.category || ''}" data-brand="${b.brand || ''}" data-pos="${b.riding_position || ''}">
                                <div>
                                    <span class="hs-font-bold hs-block">${b.title}</span>
                                    <span class="hs-text-xs hs-text-muted">${b.brand || 'Motorcycle'} · ${b.riding_position || b.category || 'Roadster'}</span>
                                </div>
                                <span class="hs-tag hs-tag--sm">Select ↵</span>
                            </div>
                        `).join('');
                        bikeDropdown.classList.remove('hs-hidden');

                        bikeDropdown.querySelectorAll('.hs-autocomplete-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const title = this.getAttribute('data-title');
                                const cat = this.getAttribute('data-category');
                                const brand = this.getAttribute('data-brand');
                                const pos = this.getAttribute('data-pos');
                                selectMotorcycle(title, cat, brand, pos);
                            });
                        });
                    } catch (err) {
                        console.warn('[Helmetsan Finder] Autocomplete error:', err);
                    }
                }, 200);
            });

            document.addEventListener('click', function(e) {
                if (!bikeInput.contains(e.target) && !bikeDropdown.contains(e.target)) {
                    bikeDropdown.classList.add('hs-hidden');
                }
            });
        }

        // 7. Step Navigation Logic
        function updateWizardStep() {
            document.querySelectorAll('.hs-finder-step-panel').forEach(panel => {
                panel.classList.add('hs-hidden');
            });

            const activePanel = document.querySelector(`.hs-finder-step-panel[data-step="${currentStep}"]`);
            if (activePanel) activePanel.classList.remove('hs-hidden');

            // Progress bar
            const pct = (currentStep / totalSteps) * 100;
            if (progressBar) progressBar.style.width = `${pct}%`;

            if (stepCountEl) stepCountEl.textContent = `Step ${currentStep} of ${totalSteps}`;
            if (stepTitleEl) stepTitleEl.textContent = stepTitles[currentStep - 1] || '';
            if (mobileProgress) mobileProgress.textContent = `Step ${currentStep}/${totalSteps}`;

            // Stepper pills
            stepperPills.forEach(pill => {
                const stepNum = parseInt(pill.getAttribute('data-goto-step'), 10);
                pill.classList.remove('is-active', 'is-completed');
                if (stepNum === currentStep) pill.classList.add('is-active');
                else if (stepNum < currentStep) pill.classList.add('is-completed');
            });

            const isFirst = (currentStep === 1);
            const isLast = (currentStep === totalSteps);

            if (prevBtn) prevBtn.disabled = isFirst;
            if (mobilePrevBtn) mobilePrevBtn.disabled = isFirst;

            const nextText = isLast ? 'Calculate Best Matches ⚡' : 'Next Step →';
            if (nextBtn) nextBtn.textContent = nextText;
            if (mobileNextBtn) mobileNextBtn.textContent = isLast ? 'Match ⚡' : 'Next →';
        }

        function handleNext() {
            if (currentStep < totalSteps) {
                currentStep++;
                updateWizardStep();
                window.scrollTo({ top: finderWrap.offsetTop - 20, behavior: 'smooth' });
            } else {
                calculateResults();
            }
        }

        function handlePrev() {
            if (currentStep > 1) {
                currentStep--;
                updateWizardStep();
                window.scrollTo({ top: finderWrap.offsetTop - 20, behavior: 'smooth' });
            }
        }

        if (nextBtn) nextBtn.addEventListener('click', handleNext);
        if (mobileNextBtn) mobileNextBtn.addEventListener('click', handleNext);
        if (prevBtn) prevBtn.addEventListener('click', handlePrev);
        if (mobilePrevBtn) mobilePrevBtn.addEventListener('click', handlePrev);

        // 8. Matching Calculation Execution
        async function calculateResults() {
            if (!resultsContainer) return;

            resultsContainer.classList.remove('hs-hidden');
            resultsContainer.innerHTML = `
                <div class="hs-card hs-p-10 hs-text-center">
                    <div class="hs-spinner hs-mb-4"></div>
                    <h3 class="hs-title-md hs-mb-2">Correlating 6-Axial Matrix across 2,216 Helmets...</h3>
                    <p class="hs-text-muted hs-max-w-md hs-mx-auto">
                        Evaluating motorcycle aerodynamics (${formData.bike || formData.bike_category}), 
                        ${formData.riding_style.replace('_', ' ')} posture vectors, and ${formData.head_shape.replace('_', ' ')} morphology.
                    </p>
                </div>
            `;
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

            try {
                const params = new URLSearchParams({
                    bike: formData.bike,
                    bike_category: formData.bike_category,
                    riding_style: formData.riding_style,
                    head_shape: formData.head_shape,
                    eyewear: formData.eyewear ? '1' : '0',
                    safety_priority: formData.safety_priority,
                    features: formData.features.join(','),
                    budget_max: formData.budget_max,
                    currency: formData.currency,
                    limit: '15'
                });

                const res = await fetch(`${apiBase}/finder/match?${params.toString()}`);
                if (!res.ok) throw new Error(`API returned HTTP ${res.status}`);
                const data = await res.json();

                rawMatches = data.matches || [];
                renderResults(data);
            } catch (err) {
                console.error('[Helmetsan Finder] Matching error:', err);
                resultsContainer.innerHTML = `
                    <div class="hs-card hs-p-8 hs-text-center hs-border-danger">
                        <div class="hs-text-3xl hs-mb-2">⚠️</div>
                        <h3 class="hs-title-md hs-mb-2">Matching Engine Temporarily Busy</h3>
                        <p class="hs-text-muted hs-mb-4">Could not calculate matches right now. Please try again or explore our catalog directly.</p>
                        <button type="button" class="hs-btn hs-btn-primary" onclick="location.reload();">Retry Advisor</button>
                    </div>
                `;
            }
        }

        // 9. Client-Side Filtering & Sorting
        function getFilteredAndSortedMatches() {
            let list = [...rawMatches];

            // Filter by type
            if (activeTypeFilter !== 'all') {
                list = list.filter(h => h.helmet_type && h.helmet_type.toLowerCase().includes(activeTypeFilter.toLowerCase()));
            }

            // Filter by brand
            if (activeBrandFilter !== 'all') {
                list = list.filter(h => h.brand && h.brand.toLowerCase() === activeBrandFilter.toLowerCase());
            }

            // Sort
            if (activeSort === 'price_asc') {
                list.sort((a, b) => a.price.amount - b.price.amount);
            } else if (activeSort === 'price_desc') {
                list.sort((a, b) => b.price.amount - a.price.amount);
            } else if (activeSort === 'weight_asc') {
                list.sort((a, b) => (a.specs.weight_g || 9999) - (b.specs.weight_g || 9999));
            } else { // default: match_score desc
                list.sort((a, b) => b.match_score - a.match_score);
            }

            return list;
        }

        // 10. Render Results
        function renderResults(data) {
            const summary = data.profile_summary || {};
            const facets = data.facets || {};
            const brands = facets.brands || [];
            const types = facets.types || [];

            const filteredMatches = getFilteredAndSortedMatches();

            if (rawMatches.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="hs-card hs-p-8 hs-text-center">
                        <h3 class="hs-title-md hs-mb-2">No exact matches found for your filter criteria.</h3>
                        <p class="hs-text-muted hs-mb-4">Try increasing your budget ceiling or relaxing specific must-have features.</p>
                        <button type="button" id="hsFinderResetBtn" class="hs-btn hs-btn-primary">Adjust Filters</button>
                    </div>
                `;
                const resetBtn = document.getElementById('hsFinderResetBtn');
                if (resetBtn) resetBtn.addEventListener('click', () => {
                    currentStep = 1;
                    updateWizardStep();
                    resultsContainer.classList.add('hs-hidden');
                });
                return;
            }

            const brandOptionsHtml = brands.map(b => `
                <option value="${b}" ${activeBrandFilter === b.toLowerCase() ? 'selected' : ''}>${b}</option>
            `).join('');

            const cardsHtml = filteredMatches.map((h, idx) => {
                const tierClass = idx === 0 && activeSort === 'score' ? 'is-best-overall' : (idx === 1 && activeSort === 'score' ? 'is-runner-up' : '');
                const awardBadge = h.match_tier && activeSort === 'score' ? `<div class="hs-finder-card__award">${h.match_tier}</div>` : '';

                const gearItems = (h.compatible_gear || []).map(g => `
                    <div class="hs-gear-item hs-flex hs-justify-between hs-items-center hs-p-2 hs-bg-surface-alt hs-rounded hs-mb-1">
                        <div>
                            <span class="hs-text-xs hs-font-bold hs-block">${g.title}</span>
                            <span class="hs-text-xxs hs-text-muted">${g.category} · ${g.note}</span>
                        </div>
                        <span class="hs-text-xs hs-font-black hs-text-accent">$${g.price}</span>
                    </div>
                `).join('');

                const keyProsHtml = (h.key_pros || []).map(p => `
                    <li class="hs-pro-item">✓ ${p}</li>
                `).join('');

                const marketplaceButtons = [];
                if (h.marketplace_links && h.marketplace_links.revzilla) {
                    marketplaceButtons.push(`<a href="${h.marketplace_links.revzilla}" target="_blank" rel="noopener" class="hs-retailer-btn hs-retailer-btn--revzilla">RevZilla ↗</a>`);
                }
                if (h.marketplace_links && h.marketplace_links.amazon) {
                    marketplaceButtons.push(`<a href="${h.marketplace_links.amazon}" target="_blank" rel="noopener" class="hs-retailer-btn hs-retailer-btn--amazon">Amazon ↗</a>`);
                }

                const fallbackImg = `https://placehold.co/400x400/1e293b/00d2be?text=${encodeURIComponent(h.brand + ' ' + h.title)}`;
                const displayImg = h.image_url || fallbackImg;

                // SVG Circular Dial calculation
                const radius = 24;
                const circumference = 2 * Math.PI * radius;
                const strokeDashoffset = circumference - (h.match_score / 100) * circumference;

                return `
                    <div class="hs-finder-card hs-card ${tierClass}">
                        ${awardBadge}
                        <div class="hs-finder-card__top-row hs-flex hs-gap-4 hs-items-start hs-mb-4">
                            <!-- Helmet Image Container -->
                            <div class="hs-finder-card__img-box">
                                <img src="${displayImg}" alt="${h.title}" loading="lazy" class="hs-finder-card__img" onerror="this.src='${fallbackImg}'">
                            </div>

                            <!-- Header Meta -->
                            <div class="hs-flex-1">
                                <span class="hs-finder-card__brand">${h.brand}</span>
                                <h3 class="hs-finder-card__title">
                                    <a href="${h.permalink}">${h.title}</a>
                                </h3>
                                <div class="hs-flex hs-items-center hs-gap-2 hs-mt-1">
                                    <span class="hs-finder-card__type">${h.helmet_type}</span>
                                    <span class="hs-bullet">·</span>
                                    <span class="hs-text-xs hs-text-muted">${h.specs.material || 'Composite'}</span>
                                </div>
                            </div>

                            <!-- Circular SVG Match Dial -->
                            <div class="hs-score-ring" title="Overall Match Score: ${h.match_score}%">
                                <svg width="60" height="60" viewBox="0 0 60 60">
                                    <circle class="hs-score-ring__bg" cx="30" cy="30" r="${radius}" stroke-width="4"></circle>
                                    <circle class="hs-score-ring__fill" cx="30" cy="30" r="${radius}" stroke-width="4"
                                            stroke-dasharray="${circumference}" stroke-dashoffset="${strokeDashoffset}"></circle>
                                </svg>
                                <span class="hs-score-ring__text">${h.match_score}%</span>
                            </div>
                        </div>

                        <!-- Sub-Score Breakdown Bars -->
                        <div class="hs-sub-scores hs-mb-4">
                            <div class="hs-sub-score-row">
                                <span class="hs-sub-score-label">🏎️ Aero & Posture</span>
                                <div class="hs-sub-score-bar"><div class="hs-sub-score-fill" style="width: ${h.sub_scores.aero_posture}%"></div></div>
                                <span class="hs-sub-score-val">${h.sub_scores.aero_posture}</span>
                            </div>
                            <div class="hs-sub-score-row">
                                <span class="hs-sub-score-label">🎯 Discipline</span>
                                <div class="hs-sub-score-bar"><div class="hs-sub-score-fill" style="width: ${h.sub_scores.discipline}%"></div></div>
                                <span class="hs-sub-score-val">${h.sub_scores.discipline}</span>
                            </div>
                            <div class="hs-sub-score-row">
                                <span class="hs-sub-score-label">🛡️ Safety Homologation</span>
                                <div class="hs-sub-score-bar"><div class="hs-sub-score-fill" style="width: ${h.sub_scores.safety}%"></div></div>
                                <span class="hs-sub-score-val">${h.sub_scores.safety}</span>
                            </div>
                            <div class="hs-sub-score-row">
                                <span class="hs-sub-score-label">🧠 Cranial Morphology</span>
                                <div class="hs-sub-score-bar"><div class="hs-sub-score-fill" style="width: ${h.sub_scores.morphology}%"></div></div>
                                <span class="hs-sub-score-val">${h.sub_scores.morphology}</span>
                            </div>
                        </div>

                        <!-- Specs Pills Grid -->
                        <div class="hs-spec-pills hs-flex hs-flex-wrap hs-gap-1 hs-mb-3">
                            <span class="hs-pill" title="Helmet Shell Weight">⚖️ ${h.specs.weight_g}g</span>
                            <span class="hs-pill" title="Impact Safety Homologation">🛡️ ${h.specs.primary_cert}</span>
                            <span class="hs-pill" title="Internal Head Contour">🥚 ${h.specs.head_shape}</span>
                            <span class="hs-pill" title="Cabin Sound Pressure at 100 km/h">🔊 ${h.specs.noise_db} dB</span>
                            ${h.specs.has_sun_visor ? '<span class="hs-pill">🕶️ Drop-Down Sun Visor</span>' : ''}
                            ${h.specs.comms_ready ? '<span class="hs-pill">🎧 40mm Speaker Cutouts</span>' : ''}
                        </div>

                        <!-- Key Verified Pros -->
                        ${keyProsHtml ? `
                            <ul class="hs-key-pros-list hs-mb-3">
                                ${keyProsHtml}
                            </ul>
                        ` : ''}

                        <!-- Why It Matches You -->
                        <div class="hs-finder-card__reason hs-mb-4">
                            <p class="hs-text-xs hs-text-soft"><strong>Why it matches:</strong> ${h.editorial_reason}</p>
                        </div>

                        <!-- Compatible Gear Bundle -->
                        ${gearItems ? `
                            <div class="hs-finder-gear-box hs-mb-4">
                                <span class="hs-text-xxs hs-uppercase hs-font-black hs-text-muted hs-block hs-mb-1">⚡ Compatible Peripheral Gear:</span>
                                ${gearItems}
                            </div>
                        ` : ''}

                        <!-- Retailer Links & Footer -->
                        <div class="hs-finder-card__footer hs-flex hs-justify-between hs-items-center hs-pt-3 hs-border-t hs-mt-auto">
                            <div class="hs-finder-card__price">
                                <span class="hs-text-xxs hs-text-muted hs-block">Starting from</span>
                                <strong class="hs-text-lg hs-font-black hs-text-accent">${h.price.formatted}</strong>
                            </div>
                            <div class="hs-flex hs-gap-2 hs-items-center">
                                <button type="button" class="hs-btn hs-btn-ghost hs-btn-xs js-add-to-compare" data-id="${h.id}" data-slug="${h.slug}" data-title="${h.title}" data-image="${displayImg}">+ Compare</button>
                                <a href="${h.permalink}" class="hs-btn hs-btn-primary hs-btn-sm">View Full Specs →</a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            resultsContainer.innerHTML = `
                <div class="hs-results-header hs-mb-6 hs-text-center">
                    <div class="hs-badge hs-badge-success hs-mb-2">6-AXIAL MATCH COMPLETE</div>
                    <h2 class="hs-title-lg">Your Top Personalized Helmet Matches</h2>
                    <p class="hs-text-soft hs-max-w-lg hs-mx-auto hs-mb-3">
                        Ranked specifically for your <strong>${summary.bike || summary.bike_category}</strong>, 
                        <strong>${summary.riding_style}</strong> posture, and 
                        <strong>${summary.head_shape}</strong> cranial contour.
                    </p>
                    <div class="hs-flex hs-justify-center hs-gap-2 hs-mb-4">
                        <button type="button" id="hsFinderShareBtn" class="hs-btn hs-btn-ghost hs-btn-xs">🔗 Share My Matches</button>
                        <button type="button" id="hsFinderRestartBtnTop" class="hs-btn hs-btn-ghost hs-btn-xs">🔄 Modify Profile</button>
                    </div>
                </div>

                <!-- Live Client-Side Filter & Sort Toolbar -->
                <div class="hs-results-toolbar hs-card hs-p-4 hs-mb-6 hs-flex hs-flex-wrap hs-justify-between hs-items-center hs-gap-4">
                    <div class="hs-flex hs-items-center hs-gap-3">
                        <span class="hs-text-xs hs-font-bold hs-text-muted">SORT BY:</span>
                        <select id="hsToolbarSort" class="hs-select hs-select-sm">
                            <option value="score" ${activeSort === 'score' ? 'selected' : ''}>Highest Match Score</option>
                            <option value="price_asc" ${activeSort === 'price_asc' ? 'selected' : ''}>Price: Low to High</option>
                            <option value="price_desc" ${activeSort === 'price_desc' ? 'selected' : ''}>Price: High to Low</option>
                            <option value="weight_asc" ${activeSort === 'weight_asc' ? 'selected' : ''}>Lightest Weight (g)</option>
                        </select>
                    </div>

                    <div class="hs-flex hs-items-center hs-gap-3">
                        <span class="hs-text-xs hs-font-bold hs-text-muted">SHELL TYPE:</span>
                        <div class="hs-filter-chips hs-flex hs-gap-1">
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'all' ? 'is-active' : ''}" data-type="all">All</button>
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'full face' ? 'is-active' : ''}" data-type="full face">Full Face</button>
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'modular' ? 'is-active' : ''}" data-type="modular">Modular</button>
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'adventure' ? 'is-active' : ''}" data-type="adventure">Adventure</button>
                        </div>
                    </div>

                    <div class="hs-flex hs-items-center hs-gap-2">
                        <button type="button" id="hsFinderRestartBtn" class="hs-btn hs-btn-ghost hs-btn-xs">🔄 Modify Profile</button>
                    </div>
                </div>

                <!-- Matches Grid -->
                <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 lg:hs-grid-cols-3 hs-gap-6 hs-mb-12">
                    ${cardsHtml}
                </div>
            `;

            // Wire up toolbar interactions
            const sortSelect = document.getElementById('hsToolbarSort');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    activeSort = this.value;
                    renderResults(data);
                });
            }

            document.querySelectorAll('.hs-filter-chip').forEach(chip => {
                chip.addEventListener('click', function() {
                    activeTypeFilter = this.getAttribute('data-type');
                    renderResults(data);
                });
            });

            const restartBtns = [document.getElementById('hsFinderRestartBtn'), document.getElementById('hsFinderRestartBtnTop')];
            restartBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        currentStep = 1;
                        updateWizardStep();
                        resultsContainer.classList.add('hs-hidden');
                        window.scrollTo({ top: finderWrap.offsetTop - 40, behavior: 'smooth' });
                    });
                }
            });

            // Share Matches Button
            const shareBtn = document.getElementById('hsFinderShareBtn');
            if (shareBtn) {
                shareBtn.addEventListener('click', function() {
                    const params = new URLSearchParams({
                        bike: formData.bike || '',
                        category: formData.bike_category || 'roadster',
                        style: formData.riding_style || 'street',
                        shape: formData.head_shape || 'intermediate_oval',
                        safety: formData.safety_priority || 'max_safety',
                        budget: formData.budget_max || 600,
                        cur: formData.currency || 'USD'
                    });
                    const shareUrl = `${window.location.origin}${window.location.pathname}?${params.toString()}`;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareUrl).then(() => {
                            const orig = shareBtn.textContent;
                            shareBtn.textContent = '✓ Link Copied!';
                            setTimeout(() => { shareBtn.textContent = orig; }, 2500);
                        }).catch(() => {
                            prompt('Copy your custom match link:', shareUrl);
                        });
                    } else {
                        prompt('Copy your custom match link:', shareUrl);
                    }
                });
            }

            // Trigger sync of comparison buttons
            window.dispatchEvent(new CustomEvent('helmetsan:sync-compare'));
        }

        // 8. URL Query Parameters Pre-Fill
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('bike')) {
            const bikeParam = urlParams.get('bike');
            const catParam = urlParams.get('category');
            selectMotorcycle(bikeParam, catParam);
        }
        if (urlParams.has('style')) {
            const style = urlParams.get('style');
            const radio = document.querySelector(`input[name="riding_style"][value="${style}"]`);
            if (radio) radio.click();
        }
        if (urlParams.has('shape')) {
            const shape = urlParams.get('shape');
            const radio = document.querySelector(`input[name="head_shape"][value="${shape}"]`);
            if (radio) radio.click();
        }
        if (urlParams.has('budget') && budgetSlider) {
            budgetSlider.value = urlParams.get('budget');
            updateBudgetDisplay();
        }
        if (urlParams.has('cur') && currencySelect) {
            currencySelect.value = urlParams.get('cur');
            updateBudgetDisplay();
        }
    });
})();
