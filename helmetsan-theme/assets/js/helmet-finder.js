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

                // Hide the wizard container, hero, and stepper so results take full focus
                if (finderWrap) finderWrap.classList.add('hs-hidden');
                const stepper = document.querySelector('.hs-finder-stepper');
                if (stepper) stepper.classList.add('hs-hidden');
                const hero = document.querySelector('.hs-finder-hero');
                if (hero) hero.classList.add('hs-hidden');

                resultsContainer.classList.remove('hs-hidden');
                renderResults(data);
                resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

        function returnToWizard() {
            if (finderWrap) finderWrap.classList.remove('hs-hidden');
            const stepper = document.querySelector('.hs-finder-stepper');
            if (stepper) stepper.classList.remove('hs-hidden');
            const hero = document.querySelector('.hs-finder-hero');
            if (hero) hero.classList.remove('hs-hidden');
            resultsContainer.classList.add('hs-hidden');
            currentStep = 1;
            updateWizardStep();
            finderWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
                if (resetBtn) resetBtn.addEventListener('click', returnToWizard);
                return;
            }

            const cardsHtml = filteredMatches.map((h, idx) => {
                const isBest = idx === 0 && activeSort === 'score';
                const isRunnerUp = idx === 1 && activeSort === 'score';
                const cardModifier = isBest ? 'is-best-overall' : (isRunnerUp ? 'is-runner-up' : '');

                let awardBadge = '';
                if (isBest) {
                    awardBadge = `<span class="hs-finder-card__award hs-finder-card__award--gold">🥇 TOP OVERALL MATCH</span>`;
                } else if (isRunnerUp) {
                    awardBadge = `<span class="hs-finder-card__award hs-finder-card__award--silver">🥈 HIGH COMPATIBILITY</span>`;
                } else if (h.specs.weight_g && h.specs.weight_g < 1350) {
                    awardBadge = `<span class="hs-finder-card__award hs-finder-card__award--light">🪶 ULTRA LIGHTWEIGHT</span>`;
                }

                const keyProsHtml = (h.key_pros || []).slice(0, 2).map(p => `
                    <li class="hs-card-pro-item">
                        <span class="hs-card-pro-icon">✓</span>
                        <span>${p}</span>
                    </li>
                `).join('');

                const fallbackImg = `https://placehold.co/400x400/1e293b/00d2be?text=${encodeURIComponent(h.brand + ' ' + h.title)}`;
                const displayImg = h.image_url || fallbackImg;

                return `
                    <article class="hs-finder-card ${cardModifier}">
                        <div class="hs-finder-card__header hs-flex hs-justify-between hs-items-center hs-mb-3">
                            <div>${awardBadge}</div>
                            <div class="hs-card-score-badge" title="Overall Rider Fit Score: ${h.match_score}%">
                                <span class="hs-card-score-badge__num">${h.match_score}%</span>
                                <span class="hs-card-score-badge__label">MATCH</span>
                            </div>
                        </div>

                        <!-- Helmet Visual Showcase -->
                        <div class="hs-finder-card__img-showcase hs-mb-4">
                            <a href="${h.permalink}" class="hs-finder-card__img-link">
                                <img src="${displayImg}" alt="${h.brand} ${h.title}" loading="lazy" class="hs-finder-card__img" onerror="this.src='${fallbackImg}'">
                            </a>
                        </div>

                        <!-- Title & Meta -->
                        <div class="hs-finder-card__body hs-flex-1 hs-flex hs-flex-col">
                            <span class="hs-finder-card__brand">${h.brand}</span>
                            <h3 class="hs-finder-card__title">
                                <a href="${h.permalink}">${h.title}</a>
                            </h3>
                            <div class="hs-finder-card__type-row hs-mb-3">
                                <span>${h.helmet_type}</span>
                                <span class="hs-bullet">·</span>
                                <span>${h.specs.material || 'Fiberglass Composite'}</span>
                            </div>

                            <!-- Spec Badges -->
                            <div class="hs-finder-card__specs-row hs-flex hs-flex-wrap hs-gap-1 hs-mb-3">
                                <span class="hs-spec-pill" title="Impact Safety Standard">🛡️ ${h.specs.primary_cert}</span>
                                <span class="hs-spec-pill" title="Shell Weight">⚖️ ${h.specs.weight_g}g</span>
                                <span class="hs-spec-pill" title="Internal Cranial Form">🥚 ${h.specs.head_shape}</span>
                                ${h.specs.has_sun_visor ? '<span class="hs-spec-pill" title="Internal Sun Visor">🕶️ Sun Visor</span>' : ''}
                                ${h.specs.comms_ready ? '<span class="hs-spec-pill" title="Bluetooth Speaker Cavities">🎧 Comms Ready</span>' : ''}
                            </div>

                            <!-- Rider Fit Insight Callout -->
                            <div class="hs-rider-insight-box hs-mb-3">
                                <span class="hs-rider-insight-label">💡 Rider Fit Analysis:</span>
                                <p class="hs-rider-insight-text">${h.editorial_reason}</p>
                            </div>

                            <!-- Key Highlights -->
                            ${keyProsHtml ? `
                                <ul class="hs-card-pros-list hs-mb-4">
                                    ${keyProsHtml}
                                </ul>
                            ` : ''}

                            <!-- Footer Price & CTAs -->
                            <div class="hs-finder-card__footer hs-flex hs-justify-between hs-items-center hs-pt-3 hs-mt-auto">
                                <div class="hs-finder-card__pricing">
                                    <span class="hs-price-label">Starting from</span>
                                    <strong class="hs-price-val">${h.price.formatted}</strong>
                                </div>
                                <div class="hs-finder-card__actions hs-flex hs-gap-2 hs-items-center">
                                    <button type="button" class="hs-btn hs-btn-ghost hs-btn-xs js-add-to-compare" data-id="${h.id}" data-slug="${h.slug}" data-title="${h.title}" data-image="${displayImg}">+ Compare</button>
                                    <a href="${h.permalink}" class="hs-btn hs-btn-primary hs-btn-xs">View Details →</a>
                                </div>
                            </div>
                        </div>
                    </article>
                `;
            }).join('');

            const bikeMap = {
                'roadster': 'Naked / Roadster',
                'superbike': 'Superbike / Sport',
                'adv': 'Adventure / Dual Sport',
                'tourer': 'Grand Tourer',
                'cruiser': 'Cruiser / Heritage',
                'scooter': 'Urban Mobility'
            };
            const disciplineMap = {
                'commute': 'City & Commuting',
                'touring': 'Long Distance Touring',
                'adventure': 'Off-Road & Adventure',
                'track': 'Track Day & Circuit Racing',
                'spirited': 'Weekend Twisties'
            };
            const shapeMap = {
                'intermediate_oval': 'Intermediate Oval',
                'round_oval': 'Round Oval',
                'long_oval': 'Long Oval'
            };
            const safetyMap = {
                'max_safety': 'ECE 22.06 & SHARP 5★',
                'track_homologated': 'FIM / Snell Circuit',
                'standard': 'DOT / ECE Road Standard'
            };

            const displayMachine = formData.bike || bikeMap[formData.bike_category] || 'Motorcycle';
            const displayDiscipline = disciplineMap[formData.riding_style] || 'Versatile Riding';
            const displayShape = shapeMap[formData.head_shape] || 'Intermediate Oval';
            const displaySafety = safetyMap[formData.safety_priority] || 'ECE 22.06';
            const displayBudget = `${formData.currency} ${formData.budget_max}`;

            resultsContainer.innerHTML = `
                <!-- Profile Summary Banner -->
                <div class="hs-profile-summary-card hs-mb-8">
                    <div class="hs-profile-summary-inner hs-flex hs-flex-wrap hs-justify-between hs-items-center hs-gap-4">
                        <div>
                            <span class="hs-profile-summary-kicker">ACTIVE RIDER PROFILE</span>
                            <div class="hs-profile-pills hs-flex hs-flex-wrap hs-gap-2 hs-mt-2">
                                <span class="hs-summary-pill">🏍️ <strong>${displayMachine}</strong></span>
                                <span class="hs-summary-pill">🧭 <strong>${displayDiscipline}</strong></span>
                                <span class="hs-summary-pill">🥚 <strong>${displayShape}</strong></span>
                                <span class="hs-summary-pill">🛡️ <strong>${displaySafety}</strong></span>
                                <span class="hs-summary-pill">💰 <strong>Under ${displayBudget}</strong></span>
                            </div>
                        </div>
                        <button type="button" id="hsFinderModifyProfileBtn" class="hs-btn hs-btn-ghost hs-btn-sm hs-gap-2">
                            ✏️ Modify Profile
                        </button>
                    </div>
                </div>

                <!-- Curated Results Header -->
                <header class="hs-results-header hs-text-center hs-mb-8">
                    <span class="hs-finder-eyebrow">CURATED RECOMMENDATIONS</span>
                    <h2 class="hs-results-title">Your Optimal Helmet Matches</h2>
                    <p class="hs-results-subtitle">
                        Screened across 2,216 verified helmets for your <strong>${displayMachine}</strong> posture, aerodynamic turbulence profile, and <strong>${displayShape}</strong> cranial contour.
                    </p>
                </header>

                <!-- Filter & Sorting Toolbar -->
                <div class="hs-results-toolbar hs-card hs-p-4 hs-mb-8 hs-flex hs-flex-wrap hs-justify-between hs-items-center hs-gap-4">
                    <div class="hs-flex hs-items-center hs-gap-3">
                        <span class="hs-text-xs hs-font-black hs-text-muted hs-uppercase">SORT BY:</span>
                        <select id="hsToolbarSort" class="hs-select hs-select-sm">
                            <option value="score" ${activeSort === 'score' ? 'selected' : ''}>Highest Fit Score</option>
                            <option value="price_asc" ${activeSort === 'price_asc' ? 'selected' : ''}>Price: Low to High</option>
                            <option value="price_desc" ${activeSort === 'price_desc' ? 'selected' : ''}>Price: High to Low</option>
                            <option value="weight_asc" ${activeSort === 'weight_asc' ? 'selected' : ''}>Lightest Weight</option>
                        </select>
                    </div>

                    <div class="hs-flex hs-items-center hs-gap-3">
                        <span class="hs-text-xs hs-font-black hs-text-muted hs-uppercase">SHELL TYPE:</span>
                        <div class="hs-filter-chips hs-flex hs-gap-1">
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'all' ? 'is-active' : ''}" data-type="all">All (${rawMatches.length})</button>
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'full face' ? 'is-active' : ''}" data-type="full face">Full Face</button>
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'modular' ? 'is-active' : ''}" data-type="modular">Modular</button>
                            <button type="button" class="hs-filter-chip ${activeTypeFilter === 'adventure' ? 'is-active' : ''}" data-type="adventure">Adventure</button>
                        </div>
                    </div>

                    <div class="hs-flex hs-items-center hs-gap-2">
                        <button type="button" id="hsFinderShareBtn" class="hs-btn hs-btn-ghost hs-btn-xs hs-gap-1">🔗 Share</button>
                    </div>
                </div>

                <!-- 3-Column Matches Grid -->
                <div class="hs-results-grid hs-mb-12">
                    ${cardsHtml}
                </div>

                <!-- Reassuring Footer Banner -->
                <div class="hs-results-footer-card hs-text-center hs-p-8 hs-rounded-2xl hs-mb-12">
                    <h3 class="hs-text-xl hs-font-black hs-mb-2">Need to compare helmets side-by-side?</h3>
                    <p class="hs-text-muted hs-max-w-md hs-mx-auto hs-mb-4 hs-text-sm">Select up to 4 helmets using the "+ Compare" button to inspect EPS density, shell composite layers, and acoustic dB measurements head-to-head.</p>
                    <div class="hs-flex hs-justify-center hs-gap-3">
                        <a href="/safety-standards/" class="hs-btn hs-btn-ghost hs-btn-sm">Learn About ECE 22.06 &amp; FIM</a>
                        <button type="button" id="hsFinderRestartBottomBtn" class="hs-btn hs-btn-primary hs-btn-sm">Start New Search ↺</button>
                    </div>
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

            const modifyBtn = document.getElementById('hsFinderModifyProfileBtn');
            if (modifyBtn) modifyBtn.addEventListener('click', returnToWizard);

            const restartBottomBtn = document.getElementById('hsFinderRestartBottomBtn');
            if (restartBottomBtn) restartBottomBtn.addEventListener('click', returnToWizard);

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
