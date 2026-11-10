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
            'Safety & Homologation',
            'Features & Peripheral Tech',
            'Budget & Currency'
        ];

        const formData = {
            bike: '',
            bike_category: 'roadster',
            riding_style: 'commute',
            head_shape: 'intermediate_oval',
            eyewear: false,
            safety_priority: 'max_safety',
            features: ['sun_visor', 'bluetooth_ready', 'pinlock_included'],
            budget_max: 600,
            currency: 'USD'
        };

        const nextBtn = document.getElementById('hsFinderNextBtn');
        const prevBtn = document.getElementById('hsFinderPrevBtn');
        const stepCountEl = document.getElementById('hsFinderStepCount');
        const stepTitleEl = document.getElementById('hsFinderStepTitle');
        const progressBar = document.getElementById('hsFinderProgressBar');
        const resultsContainer = document.getElementById('hsFinderResults');

        // Currency symbols map
        const currencySymbols = {
            'USD': '$',
            'EUR': '€',
            'GBP': '£',
            'INR': '₹'
        };

        // Initialize Card Click Handlers for Radio Inputs
        document.querySelectorAll('.hs-option-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const radio = this.querySelector('input[type="radio"]');
                if (!radio) return;

                const name = radio.name;
                // Unselect siblings
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

        // Initialize Eyewear Checkbox
        const eyewearCheck = document.getElementById('hsFinderEyewear');
        if (eyewearCheck) {
            eyewearCheck.addEventListener('change', function() {
                formData.eyewear = this.checked;
            });
        }

        // Initialize Feature Checkboxes
        document.querySelectorAll('input[name="features"]').forEach(chk => {
            chk.addEventListener('change', function() {
                const checked = [];
                document.querySelectorAll('input[name="features"]:checked').forEach(c => {
                    checked.push(c.value);
                });
                formData.features = checked;
            });
        });

        // Initialize Budget & Currency Slider
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

        // Motorcycle Autocomplete
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
                            <div class="hs-autocomplete-item" data-title="${b.title}" data-category="${b.category || ''}">
                                <span class="hs-font-bold">${b.title}</span>
                                <span class="hs-text-xs hs-text-muted">(${b.brand} · ${b.category || 'Roadster'})</span>
                            </div>
                        `).join('');
                        bikeDropdown.classList.remove('hs-hidden');

                        bikeDropdown.querySelectorAll('.hs-autocomplete-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const title = this.getAttribute('data-title');
                                const cat = this.getAttribute('data-category');
                                bikeInput.value = title;
                                formData.bike = title;
                                bikeDropdown.classList.add('hs-hidden');

                                // Sync category card if category matches
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
                            });
                        });
                    } catch (err) {
                        console.warn('[Helmetsan Finder] Autocomplete error:', err);
                    }
                }, 220);
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!bikeInput.contains(e.target) && !bikeDropdown.contains(e.target)) {
                    bikeDropdown.classList.add('hs-hidden');
                }
            });
        }

        // Wizard Step Navigation
        function updateWizardStep() {
            document.querySelectorAll('.hs-finder-step-panel').forEach(panel => {
                panel.classList.add('hs-hidden');
            });

            const activePanel = document.querySelector(`.hs-finder-step-panel[data-step="${currentStep}"]`);
            if (activePanel) activePanel.classList.remove('hs-hidden');

            if (progressBar) {
                progressBar.style.width = `${(currentStep / totalSteps) * 100}%`;
            }

            if (stepCountEl) stepCountEl.textContent = `Step ${currentStep} of ${totalSteps}`;
            if (stepTitleEl) stepTitleEl.textContent = stepTitles[currentStep - 1] || '';

            if (prevBtn) prevBtn.disabled = (currentStep === 1);
            if (nextBtn) {
                nextBtn.textContent = (currentStep === totalSteps) ? 'Calculate Best Matches ⚡' : 'Next Step →';
            }
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateWizardStep();
                } else {
                    calculateResults();
                }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentStep > 1) {
                    currentStep--;
                    updateWizardStep();
                }
            });
        }

        // Main Calculation Execution
        async function calculateResults() {
            if (!resultsContainer) return;

            resultsContainer.classList.remove('hs-hidden');
            resultsContainer.innerHTML = `
                <div class="hs-card hs-p-10 hs-text-center">
                    <div class="hs-spinner hs-mb-4"></div>
                    <h3 class="hs-title-md hs-mb-2">Correlating 6-Axial Matrix...</h3>
                    <p class="hs-text-muted hs-max-w-md hs-mx-auto">
                        Evaluating 2,216 helmets against your ${formData.bike || formData.bike_category} aerodynamics,
                        ${formData.riding_style.replace('_', ' ')} riding profile, and ${formData.head_shape.replace('_', ' ')} morphology.
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
                    limit: '9'
                });

                const res = await fetch(`${apiBase}/finder/match?${params.toString()}`);
                if (!res.ok) throw new Error(`API returned HTTP ${res.status}`);
                const data = await res.json();

                renderResults(data);
            } catch (err) {
                console.error('[Helmetsan Finder] Matching failed:', err);
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

        // Render Results Cards
        function renderResults(data) {
            const matches = data.matches || [];
            const summary = data.profile_summary || {};

            if (matches.length === 0) {
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

            const cardsHtml = matches.map((h, idx) => {
                const tierClass = idx === 0 ? 'is-best-overall' : (idx === 1 ? 'is-runner-up' : '');
                const awardBadge = h.match_tier ? `<div class="hs-finder-card__award">${h.match_tier}</div>` : '';

                const gearItems = (h.compatible_gear || []).map(g => `
                    <div class="hs-gear-item hs-flex hs-justify-between hs-items-center hs-p-2 hs-bg-surface-alt hs-rounded hs-mb-1">
                        <div>
                            <span class="hs-text-xs hs-font-bold hs-block">${g.title}</span>
                            <span class="hs-text-xxs hs-text-muted">${g.category} · ${g.note}</span>
                        </div>
                        <span class="hs-text-xs hs-font-black hs-text-accent">$${g.price}</span>
                    </div>
                `).join('');

                return `
                    <div class="hs-finder-card hs-card ${tierClass}">
                        ${awardBadge}
                        <div class="hs-finder-card__header hs-flex hs-justify-between hs-items-start hs-mb-3">
                            <div>
                                <span class="hs-finder-card__brand">${h.brand}</span>
                                <h3 class="hs-finder-card__title">
                                    <a href="${h.permalink}">${h.title}</a>
                                </h3>
                                <span class="hs-finder-card__type">${h.helmet_type}</span>
                            </div>
                            <div class="hs-score-dial" title="Overall Match Score">
                                <span class="hs-score-dial__num">${h.match_score}</span>
                                <span class="hs-score-dial__pct">%</span>
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
                                <span class="hs-sub-score-label">🧠 Head Shape</span>
                                <div class="hs-sub-score-bar"><div class="hs-sub-score-fill" style="width: ${h.sub_scores.morphology}%"></div></div>
                                <span class="hs-sub-score-val">${h.sub_scores.morphology}</span>
                            </div>
                        </div>

                        <!-- Specs Pills -->
                        <div class="hs-spec-pills hs-flex hs-flex-wrap hs-gap-1 hs-mb-3">
                            <span class="hs-pill">⚖️ ${h.specs.weight_g}g</span>
                            <span class="hs-pill">🛡️ ${h.specs.primary_cert}</span>
                            <span class="hs-pill">🥚 ${h.specs.head_shape}</span>
                            ${h.specs.has_sun_visor ? '<span class="hs-pill">🕶️ Sun Visor</span>' : ''}
                            ${h.specs.comms_ready ? '<span class="hs-pill">🎧 40mm Comms</span>' : ''}
                        </div>

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

                        <!-- Price & Action CTA -->
                        <div class="hs-finder-card__footer hs-flex hs-justify-between hs-items-center hs-pt-3 hs-border-t">
                            <div class="hs-finder-card__price">
                                <span class="hs-text-xxs hs-text-muted hs-block">Starting from</span>
                                <strong class="hs-text-lg hs-font-black hs-text-accent">${h.price.formatted}</strong>
                            </div>
                            <a href="${h.permalink}" class="hs-btn hs-btn-primary hs-btn-sm">View Full Specs →</a>
                        </div>
                    </div>
                `;
            }).join('');

            resultsContainer.innerHTML = `
                <div class="hs-results-header hs-mb-8 hs-text-center">
                    <div class="hs-badge hs-badge-success hs-mb-2">MATCH COMPLETE</div>
                    <h2 class="hs-title-lg">Your Top Personalized Helmet Matches</h2>
                    <p class="hs-text-soft hs-max-w-lg hs-mx-auto">
                        Ranked for your <strong>${summary.bike || summary.bike_category}</strong> machine, 
                        <strong>${summary.riding_style}</strong> discipline, and 
                        <strong>${summary.head_shape}</strong> cranial profile.
                    </p>
                    <div class="hs-mt-4">
                        <button type="button" id="hsFinderRestartBtn" class="hs-btn hs-btn-ghost hs-btn-sm">🔄 Recalculate with New Profile</button>
                    </div>
                </div>

                <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 lg:hs-grid-cols-3 hs-gap-6 hs-mb-12">
                    ${cardsHtml}
                </div>
            `;

            const restartBtn = document.getElementById('hsFinderRestartBtn');
            if (restartBtn) {
                restartBtn.addEventListener('click', () => {
                    currentStep = 1;
                    updateWizardStep();
                    resultsContainer.classList.add('hs-hidden');
                    window.scrollTo({ top: finderWrap.offsetTop - 40, behavior: 'smooth' });
                });
            }
        }
    });
})();
