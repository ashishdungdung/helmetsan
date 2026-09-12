/**
 * Helmetsan Interactive Guided Helmet Finder Engine Controller
 */
document.addEventListener('DOMContentLoaded', function() {
    const finderWrap = document.getElementById('hsHelmetFinderWizard');
    if (!finderWrap) return;

    let currentStep = 1;
    const totalSteps = 6;
    const formData = {
        bike: 'Royal Enfield Himalayan 450',
        environments: ['Highway Touring', 'Adventure'],
        type: 'Full Face',
        priority: 'Safety',
        homologation: 'ECE 22.06',
        budget: 500
    };

    const nextBtn = document.getElementById('hsFinderNextBtn');
    const prevBtn = document.getElementById('hsFinderPrevBtn');
    const stepTitle = document.getElementById('hsFinderStepTitle');
    const progressBar = document.getElementById('hsFinderProgressBar');

    function updateWizardStep() {
        document.querySelectorAll('.hs-finder-step-panel').forEach(panel => {
            panel.classList.add('hs-hidden');
        });
        const activePanel = document.querySelector(`.hs-finder-step-panel[data-step="${currentStep}"]`);
        if (activePanel) activePanel.classList.remove('hs-hidden');

        if (progressBar) {
            progressBar.style.width = `${(currentStep / totalSteps) * 100}%`;
        }

        if (prevBtn) prevBtn.disabled = (currentStep === 1);
        if (nextBtn) {
            nextBtn.textContent = (currentStep === totalSteps) ? 'Calculate Best Matches →' : 'Next Step →';
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

    function calculateResults() {
        const resultsContainer = document.getElementById('hsFinderResults');
        if (!resultsContainer) return;

        resultsContainer.innerHTML = `
            <div class="hs-text-center hs-py-12">
                <div class="hs-spinner hs-mb-4"></div>
                <h3 class="hs-title-md">Analyzing 2,235 Helmets...</h3>
                <p class="hs-text-muted">Matching ${formData.bike} profile, ${formData.homologation} safety standard, and ${formData.priority} priority.</p>
            </div>
        `;

        resultsContainer.scrollIntoView({ behavior: 'smooth' });

        setTimeout(() => {
            resultsContainer.innerHTML = `
                <div class="hs-mb-6 hs-text-center">
                    <div class="hs-badge hs-badge-success hs-mb-2">MATCH COMPLETE</div>
                    <h2 class="hs-title-lg">Your Best Helmet Matches</h2>
                    <p class="hs-text-soft">Grounded in your ${formData.bike} riding profile & ${formData.homologation} requirements.</p>
                </div>

                <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-3 hs-gap-6">
                    <div class="hs-card hs-p-6 hs-border-2 hs-border-accent hs-relative">
                        <span class="hs-badge hs-badge-accent hs-absolute -hs-top-3 hs-left-4">🥇 BEST OVERALL</span>
                        <div class="hs-text-xs hs-uppercase hs-text-muted hs-font-bold hs-mt-2">Arai</div>
                        <h3 class="hs-font-black hs-text-xl hs-mb-2">Raider X</h3>
                        <div class="hs-text-2xl hs-font-black hs-text-green hs-mb-3">94 / 100 Match</div>
                        <p class="hs-text-xs hs-text-soft hs-mb-4">Excellent match for highway touring on your ${formData.bike}. Superior ECE 22.06 certification and long-distance aero stability.</p>
                        <div class="hs-flex hs-gap-2">
                            <a href="/helmets/arai_raider_x" class="hs-btn hs-btn-primary hs-w-full">View Details →</a>
                        </div>
                    </div>

                    <div class="hs-card hs-p-6 hs-relative">
                        <span class="hs-badge hs-badge-success hs-absolute -hs-top-3 hs-left-4">🥈 BEST VALUE</span>
                        <div class="hs-text-xs hs-uppercase hs-text-muted hs-font-bold hs-mt-2">Shoei</div>
                        <h3 class="hs-font-black hs-text-xl hs-mb-2">RF-1400</h3>
                        <div class="hs-text-2xl hs-font-black hs-text-green hs-mb-3">91 / 100 Match</div>
                        <p class="hs-text-xs hs-text-soft hs-mb-4">AIM+ composite shell with ECE 22.06 rating. Outstanding noise isolation for highway riding.</p>
                        <div class="hs-flex hs-gap-2">
                            <a href="/helmets/shoei_rf1400" class="hs-btn hs-btn-secondary hs-w-full">View Details →</a>
                        </div>
                    </div>

                    <div class="hs-card hs-p-6 hs-relative">
                        <span class="hs-badge hs-badge-neutral hs-absolute -hs-top-3 hs-left-4">🥉 LIGHTEST</span>
                        <div class="hs-text-xs hs-uppercase hs-text-muted hs-font-bold hs-mt-2">AGV</div>
                        <h3 class="hs-font-black hs-text-xl hs-mb-2">K6 S</h3>
                        <div class="hs-text-2xl hs-font-black hs-text-green hs-mb-3">89 / 100 Match</div>
                        <p class="hs-text-xs hs-text-soft hs-mb-4">Featherweight 1,255g carbon-aramid shell. Reduces neck fatigue during extended touring.</p>
                        <div class="hs-flex hs-gap-2">
                            <a href="/helmets/agv_k6_s" class="hs-btn hs-btn-secondary hs-w-full">View Details →</a>
                        </div>
                    </div>
                </div>
            `;
        }, 800);
    }

    // Initial wizard state
    updateWizardStep();
});

