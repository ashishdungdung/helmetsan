(function () {
    'use strict';

    // Config
    const config = window.helmetsan_ajax || {};
    const form = document.getElementById('hsHelmetFilterForm');
    const container = document.querySelector('.hs-catalog__results');
    const gridContainer = document.querySelector('.helmet-grid');
    const paginationContainer = document.querySelector('.pagination'); // Verify selector
    const chipsContainer = document.querySelector('.hs-catalog__chips');
    const countContainer = document.querySelector('.hs-catalog__count');
    const sortSelect = document.getElementById('hsSort');
    
    // State
    let isLoading = false;
    const mqDesktop = window.matchMedia('(min-width: 961px)');

    if (!form || !container) return;

    // --- Core Functions ---

    /**
     * Fetch Results via AJAX
     * @param {URLSearchParams} params 
     */
    async function fetchResults(params) {
        if (isLoading) return;
        setLoading(true);

        const url = new URL(config.url);
        url.searchParams.set('action', 'helmetsan_filter');
        // Merge params
        for (const [key, value] of params) {
            url.searchParams.append(key, value);
        }

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                updateUI(data.data);
                updateURL(params);
            } else {
                console.error('Filter error', data);
            }
        } catch (err) {
            console.error('Fetch error', err);
        } finally {
            setLoading(false);
        }
    }

    /**
     * Update DOM Elements
     */
    function updateUI(data) {
        // 1. Grid
        // Check if wrapper exists, if not create/select it
        let grid = container.querySelector('.helmet-grid');
        if (!grid) {
             // Maybe no results previously?
             const msg = container.querySelector('p');
             if(msg) msg.remove();
             grid = document.createElement('div');
             grid.className = 'helmet-grid';
             container.appendChild(grid);
        }
        
        if (data.html) {
            grid.innerHTML = data.html;
        } else {
            grid.innerHTML = '<p>No helmets found.</p>';
        }

    // 3. Pagination (Load More)
    const paginationContainer = document.querySelector('.navigation.pagination');
    // Replace standard pagination with Load More if we have results
    
    function setupPagination(data) {
        const oldPag = container.querySelector('.navigation.pagination');
        if (oldPag) oldPag.remove();
        
        const oldBtn = container.querySelector('.hs-load-more');
        if (oldBtn) oldBtn.remove();
        
        if (data.next_page) {
            const btn = document.createElement('button');
            btn.className = 'hs-btn hs-btn--secondary hs-load-more';
            btn.textContent = 'Load More Helmets';
            btn.dataset.nextPage = data.next_page;
            
            // Insert after grid
            const grid = container.querySelector('.helmet-grid');
            if (grid) grid.insertAdjacentElement('afterend', btn);
            
            btn.addEventListener('click', () => {
                const formData = new FormData(form);
                if(sortSelect) formData.set('sort', sortSelect.value);
                formData.set('paged', data.next_page);
                
                const params = new URLSearchParams(formData);
                fetchResults(params, true); // true = append
            });
        }
    }

    /**
     * Fetch Results
     * @param {URLSearchParams} params 
     * @param {boolean} append - If true, append results
     */
    async function fetchResults(params, append = false) {
        if (isLoading) return;
        setLoading(true);

        const url = new URL(config.url);
        url.searchParams.set('action', 'helmetsan_filter');
        for (const [key, value] of params) {
            url.searchParams.append(key, value);
        }

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                updateUI(data.data, append);
                // Only update URL if NOT appending (don't change URL for page 2 Load More)
                if (!append) updateURL(params);
            }
        } catch (err) {
            console.error('Fetch error', err);
        } finally {
            setLoading(false);
        }
    }

    function updateUI(data, append) {
        let grid = container.querySelector('.helmet-grid');
        if (!grid) {
             const msg = container.querySelector('p');
             if(msg) msg.remove();
             grid = document.createElement('div');
             grid.className = 'helmet-grid';
             container.appendChild(grid);
        }
        
        if (data.html) {
            if (append) {
                grid.insertAdjacentHTML('beforeend', data.html);
            } else {
                grid.innerHTML = data.html;
            }
        } else if (!append) {
            grid.innerHTML = '<p>No helmets found.</p>';
        }

        // Setup Pagination Button
        setupPagination(data);

        // Count
        if (countContainer) countContainer.textContent = data.count + ' Helmets';

        // Chips
        if (chipsContainer && !append) chipsContainer.innerHTML = data.chips || '';

        // Scroll to top only if NEW filter (not append)
        if (!append) {
            const topOfResults = document.querySelector('.hs-catalog');
            if (topOfResults) topOfResults.scrollIntoView({ behavior: 'smooth' });
        }
        
        document.body.classList.remove('hs-filter-open');
        document.getElementById('hsFilterPanel')?.classList.remove('is-open');
    }

    // 4. Chip Removal & Initial Validation
    // ... rest of event listeners ...
    
    // Initial Load: Check if we need to convert standard pagination to Load More
    // (Optional: For now, let's just let the standard one exist until first interaction, 
    // OR we trigger a fetch on load to "hydrate" the button? 
    // Better: Just hide the standard pagination via CSS if JS is active and init button?
    // Actually, simple way: The FIRST interaction (filter/sort) will trigger updateUI which adds the button.
    // The standard pagination works fine for SEO/JS-off.
    
    // Chip removal logic
    if (chipsContainer) {
        chipsContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.hs-chip');
            if (!btn) return;
            e.preventDefault();
            
            if (btn.tagName === 'A') {
                // If it's a link, we need to manually parse what to remove or just trigger a reset?
                // Actually, the links for chips are usually ?param=... excluding the current one.
                // We can just fetch that URL? No, that's full page.
                // Best bet: Parse the filter key/value from the chip (if we added data attrs to PHP output).
                // The PHP output in archive-helmet.php DID NOT add data attrs for initial chips (only URL).
                // So for initial chips, we might have to just follow the link or accept reload.
                // Let's allow reload for initial chips to be safe.
                 window.location.href = btn.href;
                 return;
            }
            
            const key = btn.dataset.filterKey;
            const value = btn.dataset.filterValue;
                
            if (key) {
                if (value === '') {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input) input.value = '';
                } else {
                    const input = form.querySelector(`[name="${key}[]"][value="${value}"], [name="${key}"][value="${value}"]`);
                    if (input) input.checked = false;
                }
                form.dispatchEvent(new Event('submit'));
            }
        });
    }

    // ... rest of listeners ...

    // 5. Back Button
    window.addEventListener('popstate', () => {
        window.location.reload(); 
        // Full reload is safer/easier than restoring state from URL to form inputs manually
    });
    
    // 6. Mobile Toggles (Keep existing UI logic)
    const openBtn = document.querySelector('[data-open-filter]');
    const closeBtn = document.querySelector('[data-close-filter]');
    const panel = document.getElementById('hsFilterPanel');
    
    if (openBtn && panel) {
        openBtn.addEventListener('click', () => {
            panel.classList.add('is-open');
            document.body.classList.add('hs-filter-open');
        });
    }
    if (closeBtn && panel) {
        closeBtn.addEventListener('click', () => {
            panel.classList.remove('is-open');
            document.body.classList.remove('hs-filter-open');
        });
    }

})();
