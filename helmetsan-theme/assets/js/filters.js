(function () {
    'use strict';

    // Config
    const config = window.helmetsan_ajax || { url: '/wp-admin/admin-ajax.php' };
    const form = document.getElementById('hsHelmetFilterForm');
    const container = document.querySelector('.hs-catalog__results');
    const countContainer = document.querySelector('.hs-catalog__count');
    const chipsContainer = document.querySelector('.hs-catalog__chips');
    const sortSelect = document.getElementById('hs-catalog-sort') || document.getElementById('hsSort');
    
    // State
    let isLoading = false;
    let filterTimeout = null;

    if (!form || !container || config.enable_ajax === false) return;

    function setLoading(loading) {
        isLoading = loading;
        if (loading) {
            container.style.opacity = '0.5';
            container.style.pointerEvents = 'none';
        } else {
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        }
    }

    function updateURL(params) {
        const url = new URL(window.location.href);
        url.search = params.toString();
        window.history.pushState({}, '', url);
    }

    async function fetchResults(params, append = false) {
        if (isLoading) return;
        setLoading(true);

        const url = new URL(config.url, window.location.origin);
        url.searchParams.set('action', 'helmetsan_filter');
        if (config.lang && !url.searchParams.has('lang')) {
            url.searchParams.set('lang', config.lang);
        }
        for (const [key, value] of params) {
            url.searchParams.append(key, value);
        }

        // Inject active view and column count from localStorage to render cards in the correct layout state
        if (!url.searchParams.has('view')) {
            url.searchParams.set('view', localStorage.getItem('hs_catalog_view') || 'grid');
        }
        if (!url.searchParams.has('cols')) {
            url.searchParams.set('cols', localStorage.getItem('hs_catalog_density') || '4');
        }

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                updateUI(data.data, append);
                if (!append) {
                    updateURL(params);
                    // Telemetry: Track filter_applied in GA4
                    if (typeof window.gtag === 'function') {
                        const activeFilters = {};
                        for (const [key, value] of params.entries()) {
                            if (['action', 'paged', 'view', 'cols', 'sort'].includes(key)) continue;
                            if (!activeFilters[key]) {
                                activeFilters[key] = [];
                            }
                            activeFilters[key].push(value);
                        }
                        if (Object.keys(activeFilters).length > 0) {
                            window.gtag('event', 'filter_applied', {
                                filter_type: Object.keys(activeFilters).join(','),
                                filter_values: JSON.stringify(activeFilters),
                            });
                        }
                    }
                }
            } else {
                console.error('Filter error', data);
            }
        } catch (err) {
            console.error('Fetch error', err);
        } finally {
            setLoading(false);
        }
    }

    function updateUI(data, append) {
        if (data.html) {
            if (!append) {
                const resultsSection = container.querySelector('.hs-catalog__results-content');
                if (resultsSection) {
                    resultsSection.innerHTML = data.html;
                } else {
                    container.innerHTML = data.html;
                }
            } else {
                const grid = container.querySelector('.hs-catalog-grid') || container.querySelector('.helmet-grid');
                if (grid) {
                    grid.insertAdjacentHTML('beforeend', data.html);
                }
            }
        } else if (!append) {
            const resultsSection = container.querySelector('.hs-catalog__results-content');
            if (resultsSection) {
                resultsSection.innerHTML = '<p>No helmets found for the selected filters.</p>';
            }
        }

        // Count
        if (countContainer && data.count !== undefined) {
            countContainer.textContent = data.count + ' Helmets';
        }

        // Accessibility Announcement
        const announcer = document.getElementById('hs-a11y-announcer');
        if (announcer) {
            announcer.textContent = `Filtering complete: ${data.count} helmets found.`;
        }

        // Scroll to top only if NEW filter (not append)
        if (!append) {
            const topOfResults = document.querySelector('.hs-catalog');
            if (topOfResults) topOfResults.scrollIntoView({ behavior: 'smooth' });
        }
        
        document.body.classList.remove('hs-filter-open');
        document.getElementById('hsFilterPanel')?.classList.remove('is-open');
        
        // Update Mobile Trigger ARIA
        const openBtn = document.querySelector('[data-open-filter]');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');

        // Re-bind pagination clicks
        bindPagination();

        // Notify currency selector and other listeners that catalog HTML updated
        document.dispatchEvent(new CustomEvent('helmetsan:catalog_updated', {
            detail: { append, count: data.count }
        }));
    }

    function bindPagination() {
        const pagLinks = container.querySelectorAll('.hs-pagination-modern a');
        pagLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = new URL(link.href, window.location.origin);
                const page = url.searchParams.get('paged') || url.pathname.match(/page\/(\d+)/)?.[1] || '1';

                let params;
                if (url.search && url.search.length > 1) {
                    params = new URLSearchParams(url.search);
                    params.set('paged', page);
                } else {
                    const formData = new FormData(form);
                    if (sortSelect) formData.set('sort', sortSelect.value);
                    formData.set('paged', page);
                    params = new URLSearchParams(formData);
                }
                fetchResults(params, false);
            });
        });
    }

    function submitFilter() {
        const formData = new FormData(form);
        if (sortSelect) formData.set('sort', sortSelect.value);
        formData.set('paged', '1');
        const params = new URLSearchParams(formData);
        fetchResults(params, false);
    }

    function debounceSubmitFilter(delay = 400) {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            submitFilter();
        }, delay);
    }

    // Trigger on form input change (with debouncing to allow quick multiple selections)
    form.addEventListener('change', (e) => {
        if (e.target.type === 'text' || e.target.type === 'number') return;
        debounceSubmitFilter(400);
    });

    // Handle text input enter key or submit button
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        submitFilter();
    });

    // Handle sort change
    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            submitFilter();
        });
    }

    // Dynamic Chip removal logic via AJAX (No Full Page Reload!)
    if (chipsContainer) {
        chipsContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('a.hs-chip');
            if (!btn) return;
            e.preventDefault();

            try {
                const url = new URL(btn.href, window.location.origin);
                const params = url.searchParams;

                // Reset all form inputs to match the target URL parameters
                const inputs = form.querySelectorAll('input[type="checkbox"], input[type="radio"]');
                inputs.forEach(input => {
                    const name = input.name.replace('[]', '');
                    const val = input.value;
                    if (params.has(name)) {
                        const values = params.getAll(name);
                        input.checked = values.includes(val);
                    } else {
                        input.checked = false;
                    }
                });

                // Reset text fields
                form.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => {
                    const name = input.name;
                    input.value = params.get(name) || '';
                });

                if (sortSelect) {
                    sortSelect.value = params.get('sort') || 'newest';
                }

                // Instantly filter via AJAX
                submitFilter();
            } catch (err) {
                // Fallback
                window.location.href = btn.href;
            }
        });
    }

    // Clear All button AJAX behavior
    const clearAllBtn = form.querySelector('.hs-filter-actions a.hs-btn--outline');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', (e) => {
            e.preventDefault();
            form.reset();
            form.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => {
                input.value = '';
            });
            form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
                input.checked = false;
            });
            if (sortSelect) sortSelect.value = 'newest';
            
            submitFilter();
        });
    }

    // Back Button Support
    window.addEventListener('popstate', () => {
        window.location.reload(); 
    });
    
    // Mobile Toggles
    const openBtn = document.querySelector('[data-open-filter]');
    const closeBtn = document.querySelector('[data-close-filter]');
    const panel = document.getElementById('hsFilterPanel');
    const backdrop = document.getElementById('hsModalBackdrop');
    
    if (openBtn && panel) {
        openBtn.addEventListener('click', () => {
            panel.classList.add('is-open');
            document.body.classList.add('hs-filter-open');
            if (backdrop) backdrop.classList.add('is-visible');
            openBtn.setAttribute('aria-expanded', 'true');
        });
    }

    function closeFilterPanel() {
        if (!panel) return;
        panel.classList.remove('is-open');
        document.body.classList.remove('hs-filter-open');
        if (backdrop) backdrop.classList.remove('is-visible');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        if (openBtn) openBtn.focus();
    }

    if (closeBtn) closeBtn.addEventListener('click', closeFilterPanel);
    if (backdrop) backdrop.addEventListener('click', closeFilterPanel);

    /* ── Focus Trap for Mobile Filter ──────────────── */
    if (panel) {
        panel.addEventListener('keydown', function(e) {
            if (!panel.classList.contains('is-open')) return;
            if (e.key !== 'Tab') return;

            const focusables = panel.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    last.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === last) {
                    first.focus();
                    e.preventDefault();
                }
            }
        });
    }

    // Bind original pagination on load
    bindPagination();

})();
