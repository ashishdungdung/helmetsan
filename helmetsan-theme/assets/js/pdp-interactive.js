(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // --- 1. SIZING & FIT FINDER ---
        const finder = document.getElementById('hsSizeFinder');
        if (finder) {
            const rangeInput = document.getElementById('hsSizeFinderRange');
            const circumferenceVal = document.getElementById('hsSizeFinderCircumference');
            const sizeVal = document.getElementById('hsSizeFinderResultVal');
            const fitText = document.getElementById('hsSizeFinderResultFit');
            const shapeNote = document.getElementById('hsSizeFinderResultShape');
            const fallbackBadge = document.getElementById('hsSizeFinderFallback');
            const shapeButtons = document.querySelectorAll('.hs-size-finder__shape-btn');
            const resultCard = document.querySelector('.hs-size-finder__card');

            // Parse size chart from data attribute
            let sizeChart = [];
            let isFallback = true;

            try {
                const rawChart = finder.getAttribute('data-sizing-chart');
                if (rawChart) {
                    sizeChart = JSON.parse(rawChart);
                    if (Array.isArray(sizeChart) && sizeChart.length > 0) {
                        isFallback = false;
                    }
                }
            } catch (e) {
                console.warn('Failed to parse sizing chart metadata, falling back to standard ECE/DOT baseline.', e);
            }

            // Global standard baseline ECE/DOT size converter
            const standardBaseline = [
                { size: '2XS', minCm: 51, maxCm: 52 },
                { size: 'XS', minCm: 53, maxCm: 54 },
                { size: 'S', minCm: 55, maxCm: 56 },
                { size: 'M', minCm: 57, maxCm: 58 },
                { size: 'L', minCm: 59, maxCm: 60 },
                { size: 'XL', minCm: 61, maxCm: 62 },
                { size: '2XL', minCm: 63, maxCm: 64 },
                { size: '3XL', minCm: 65, maxCm: 66 }
            ];

            let selectedShape = finder.getAttribute('data-default-shape') || 'intermediate-oval';

            // Active shape selections
            shapeButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    shapeButtons.forEach(b => b.classList.remove('is-active'));
                    this.classList.add('is-active');
                    selectedShape = this.getAttribute('data-shape');
                    updateSizeFinder();
                });
            });

            function parseCmRange(cmStr) {
                // Extracts numbers from strings like "57-58" or "57cm - 58cm" or "57"
                if (!cmStr) return null;
                const matches = cmStr.match(/(\d+(?:\.\d+)?)/g);
                if (!matches) return null;
                
                if (matches.length === 1) {
                    const singleVal = parseFloat(matches[0]);
                    return { min: singleVal - 0.5, max: singleVal + 0.5 };
                }
                
                return {
                    min: parseFloat(matches[0]),
                    max: parseFloat(matches[1])
                };
            }

            function findSizeForCircumference(cm) {
                if (!isFallback && sizeChart.length > 0) {
                    // Try to match against custom manufacturer sizing chart
                    for (const row of sizeChart) {
                        const cmStr = row.cm || '';
                        const range = parseCmRange(cmStr);
                        if (range && cm >= range.min && cm <= range.max) {
                            return row.size || 'N/A';
                        }
                    }
                    // Secondary loose match if exact bounds missed
                    for (const row of sizeChart) {
                        const cmStr = row.cm || '';
                        const range = parseCmRange(cmStr);
                        if (range && cm >= range.min - 1 && cm <= range.max + 1) {
                            return row.size || 'N/A';
                        }
                    }
                }

                // Standard fallback ECE/DOT calculations
                for (const item of standardBaseline) {
                    if (cm >= item.minCm && cm <= item.maxCm) {
                        return item.size;
                    }
                }
                
                if (cm < 51) return '2XS (Tight)';
                if (cm > 66) return '3XL+';
                
                return 'Medium'; // Default fallback
            }

            let lastSize = null;
            let lastShape = null;

            function updateSizeFinder() {
                const cm = parseFloat(rangeInput.value);
                circumferenceVal.textContent = cm + ' cm (' + (cm / 2.54).toFixed(1) + ' in)';

                const size = findSizeForCircumference(cm);

                // Run heavy DOM updates and visual animations ONLY if the size or shape changed
                if (size !== lastSize || selectedShape !== lastShape) {
                    sizeVal.textContent = size;

                    // Highlight and animate result card when recommended size changes
                    if (resultCard) {
                        resultCard.classList.remove('is-recommended');
                        // Use requestAnimationFrame to reset the CSS transition cleanly
                        requestAnimationFrame(() => {
                            resultCard.classList.add('is-recommended');
                        });
                    }

                    // Dynamic fit description based on shape
                    let fitAdvice = 'Excellent standard fit.';
                    let shapeAdvice = 'Designed for intermediate oval heads (most common).';

                    if (selectedShape === 'long-oval') {
                        fitAdvice = 'Generous front-to-back room.';
                        shapeAdvice = 'Good for narrow heads. May feel loose on sides; avoid sizing up.';
                    } else if (selectedShape === 'round-oval') {
                        fitAdvice = 'Even cylindrical spacing.';
                        shapeAdvice = 'Good for wider heads. If you have pressure points on sides, this shape is perfect.';
                    } else {
                        fitAdvice = 'Optimized contours.';
                        shapeAdvice = 'Perfect for standard intermediate head profiles.';
                    }

                    if (fitText) fitText.textContent = fitAdvice;
                    if (shapeNote) shapeNote.textContent = shapeAdvice;

                    // Toggle visibility of standard fallback badge
                    if (fallbackBadge) {
                        fallbackBadge.style.display = isFallback ? 'inline-flex' : 'none';
                    }

                    lastSize = size;
                    lastShape = selectedShape;
                }
            }

            // Bind slider slide
            rangeInput.addEventListener('input', updateSizeFinder);
            
            // Trigger initial calculation
            updateSizeFinder();
        }

        // --- 2. SAFETY HUD METRICS ANIMATION ON VIEW ---
        const safetyHud = document.querySelector('.hs-safety-hud');
        if (safetyHud) {
            const circles = safetyHud.querySelectorAll('.hs-safety-hud__circle-val');
            const bars = safetyHud.querySelectorAll('.hs-safety-hud__bar-inner');

            // Set dasharrays and animate circles
            circles.forEach(circle => {
                const percent = parseFloat(circle.getAttribute('data-percent') || '0');
                const radius = parseFloat(circle.getAttribute('r') || '40');
                const circumference = 2 * Math.PI * radius;
                
                circle.style.strokeDasharray = circumference;
                circle.style.strokeDashoffset = circumference;
                
                // Animate on scroll or immediate
                setTimeout(() => {
                    const offset = circumference - (percent / 100) * circumference;
                    circle.style.strokeDashoffset = offset;
                }, 300);
            });

            // Animate progress bars
            bars.forEach(bar => {
                const width = bar.getAttribute('data-width') || '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 400);
            });
        }

        // --- 3. PRICE DROP TRACKER MODAL ---
        const alertTrigger = document.getElementById('hsPriceAlertTrigger');
        const alertModal = document.getElementById('hsPriceAlertModal');
        
        if (alertTrigger && alertModal) {
            const alertClose = alertModal.querySelector('.hs-pdp-modal__close');
            const alertOverlay = alertModal.querySelector('.hs-pdp-modal__overlay');
            const alertForm = document.getElementById('hsPriceAlertForm');
            const helmetId = alertTrigger.getAttribute('data-helmet-id');

            // Check if alert is already set in localStorage
            const localKey = 'hs_alert_' + helmetId;
            const alreadySet = localStorage.getItem(localKey);
            if (alreadySet) {
                alertTrigger.textContent = '✓ Drop Alert Active';
                alertTrigger.classList.add('hs-btn--ghost');
                alertTrigger.classList.remove('hs-price-comparer__alert-btn');
                alertTrigger.style.background = 'rgba(52, 211, 153, 0.1)';
                alertTrigger.style.color = 'var(--hs-success)';
                alertTrigger.style.borderColor = 'var(--hs-success)';
            }

            alertTrigger.addEventListener('click', function () {
                alertModal.classList.add('is-active');
                document.body.style.overflow = 'hidden';
            });

            function closeModal() {
                alertModal.classList.remove('is-active');
                document.body.style.overflow = '';
            }

            if (alertClose) alertClose.addEventListener('click', closeModal);
            if (alertOverlay) alertOverlay.addEventListener('click', closeModal);

            if (alertForm) {
                alertForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const email = document.getElementById('hsAlertEmail').value;
                    const price = document.getElementById('hsAlertPrice').value;

                    if (!email || !price) return;

                    // Show visual success checkmark feedback
                    const submitBtn = alertForm.querySelector('.hs-pdp-modal__submit');
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = '✓ Active!';
                    submitBtn.style.background = 'var(--hs-success)';
                    submitBtn.disabled = true;

                    // Save alert setting
                    localStorage.setItem(localKey, JSON.stringify({ email: email, targetPrice: price, date: new Date().toISOString() }));

                    setTimeout(() => {
                        closeModal();
                        
                        // Update the trigger button state on page
                        alertTrigger.textContent = '✓ Drop Alert Active';
                        alertTrigger.classList.add('hs-btn--ghost');
                        alertTrigger.classList.remove('hs-price-comparer__alert-btn');
                        alertTrigger.style.background = 'rgba(52, 211, 153, 0.1)';
                        alertTrigger.style.color = 'var(--hs-success)';
                        alertTrigger.style.borderColor = 'var(--hs-success)';
                    }, 1200);
                });
            }
        }

        // --- 4. PDP TABS SMOOTH SCROLL ---
        const tabLinks = document.querySelectorAll('.helmet-single__tab-link');
        tabLinks.forEach(link => {
            link.addEventListener('click', function (e) {
                const targetId = this.getAttribute('href');
                if (targetId && targetId.startsWith('#')) {
                    const targetEl = document.querySelector(targetId);
                    if (targetEl) {
                        e.preventDefault();
                        
                        // Scroll to element accounting for possible sticky headers
                        const offsetTop = targetEl.getBoundingClientRect().top + window.pageYOffset - 90;
                        window.scrollTo({
                            top: offsetTop,
                            behavior: 'smooth'
                        });

                        // Set active state on clicked link
                        tabLinks.forEach(l => l.classList.remove('is-active'));
                        this.classList.add('is-active');
                    }
                }
            });
        });

        // IntersectionObserver to highlight tabs as user scrolls through sections
        const sections = document.querySelectorAll('#helmet-product-description, #helmet-part-numbers, #helmet-sizing-fit');
        if (sections.length > 0 && tabLinks.length > 0) {
            const observerOptions = {
                root: null,
                rootMargin: '-10% 0px -80% 0px',
                threshold: 0
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        tabLinks.forEach(link => {
                            if (link.getAttribute('href') === '#' + id) {
                                link.classList.add('is-active');
                            } else {
                                link.classList.remove('is-active');
                            }
                        });
                    }
                });
            }, observerOptions);

            sections.forEach(section => observer.observe(section));
        }

        // --- 5. PDP WHERE TO BUY PURCHASE TABS ---
        const purchaseTabs = document.querySelectorAll('.hs-purchase-tab');
        const purchasePanes = document.querySelectorAll('.hs-purchase-pane');
        
        if (purchaseTabs.length > 0) {
            purchaseTabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-target');
                    if (!targetId) return;

                    purchaseTabs.forEach(t => t.classList.remove('hs-purchase-tab--active'));
                    purchasePanes.forEach(p => p.classList.remove('hs-purchase-pane--active'));

                    this.classList.add('hs-purchase-tab--active');
                    const activePane = document.getElementById(targetId);
                    if (activePane) {
                        activePane.classList.add('hs-purchase-pane--active');
                    }
                });
            });
        }

        // --- 6. PDP MODULAR TELEMETRY GAUGES SCROLL ANIMATION ---
        const radialGauges = document.querySelectorAll('.hs-hud-radial-gauge');
        if (radialGauges.length > 0) {
            const gaugeObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const ring = entry.target.querySelector('.hs-gauge-fill-ring');
                        if (ring) {
                            const targetVal = ring.getAttribute('data-width') || '0';
                            ring.style.strokeDasharray = '0, 100';
                            setTimeout(() => {
                                ring.style.strokeDasharray = `${targetVal}, 100`;
                            }, 80);
                        }
                        gaugeObserver.unobserve(entry.target);
                    }
                });
            }, { root: null, threshold: 0.1 });

            radialGauges.forEach(gauge => gaugeObserver.observe(gauge));
        }
    });
})();
