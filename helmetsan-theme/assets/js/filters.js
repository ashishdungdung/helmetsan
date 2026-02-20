(function () {
    'use strict';

    // Config
    const config = window.helmetsan_ajax || { url: '/wp-admin/admin-ajax.php' };
    const form = document.getElementById('hsHelmetFilterForm');
    const container = document.querySelector('.hs-catalog__results');
    const countContainer = document.querySelector('.hs-catalog__count');
    const chipsContainer = document.querySelector('.hs-catalog__chips');
    const sortSelect = document.getElementById('hsSort');
    
    // State
    let isLoading = false;

    if (!form || !container) return;

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
        for (const [key, value] of params) {
            url.searchParams.append(key, value);
        }

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                updateUI(data.data, append);
                if (!append) updateURL(params);
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
        let grid = container.querySelector('.helmet-grid');
        if (!grid) {
             const msg = container.querySelector('p');
             if(msg) msg.remove();
             grid = document.createElement('div');
             grid.className = 'helmet-grid';
             container.appendChild(grid);
        }
        
        let targetArea = container.querySelector('.hs-catalog__results-content');
        if (!targetArea) {
            targetArea = container; // fallback
        } else {
            // we will replace the whole section if present
        }

        if (data.html) {
            // The API returns the full grid AND pagination
            if (!append) {
                const resultsSection = container.querySelector('.hs-catalog__results-content');
                if (resultsSection) {
                    resultsSection.innerHTML = data.html;
                } else {
                    // Try to inject at bottom
                    const newWrapper = document.createElement('section');
                    newWrapper.className = 'hs-catalog__results-content';
                    newWrapper.innerHTML = data.html;
                    container.appendChild(newWrapper);
                }
            } else {
                // If appending, assuming data.html has <div class="helmet-grid"> wrap
                // Extract just the inner HTML of the grid
                const temp = document.createElement('div');
                temp.innerHTML = data.html;
                const newGrid = temp.querySelector('.helmet-grid');
                if (newGrid && grid) {
                    grid.insertAdjacentHTML('beforeend', newGrid.innerHTML);
                }
                
                // Replace pagination
                const oldPag = container.querySelector('.hs-pagination-wrap');
                const newPag = temp.querySelector('.hs-pagination-wrap');
                if (oldPag && newPag) {
                    oldPag.innerHTML = newPag.innerHTML;
                }
            }
        } else if (!append) {
            const resultsSection = container.querySelector('.hs-catalog__results-content');
            if (resultsSection) {
                resultsSection.innerHTML = '<p>No helmets found for the selected filters.</p>';
            }
        }

        // Count
        if (countContainer) countContainer.textContent = data.count + ' Helmets';

        // Scroll to top only if NEW filter (not append)
        if (!append) {
            const topOfResults = document.querySelector('.hs-catalog');
            if (topOfResults) topOfResults.scrollIntoView({ behavior: 'smooth' });
        }
        
        document.body.classList.remove('hs-filter-open');
        document.getElementById('hsFilterPanel')?.classList.remove('is-open');

        // Re-bind pagination clicks
        bindPagination();
    }

    function bindPagination() {
        const pagLinks = container.querySelectorAll('.hs-pagination-wrap a.page-numbers');
        pagLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.searchParams.get('paged') || url.pathname.match(/page\/(\d+)/)?.[1] || 1;
                
                const formData = new FormData(form);
                if (sortSelect) formData.set('sort', sortSelect.value);
                formData.set('paged', page);
                
                const params = new URLSearchParams(formData);
                fetchResults(params, false);
            });
        });
    }

    // Trigger on form input change
    form.addEventListener('change', (e) => {
        // Skip text inputs unless they blur
        if (e.target.type === 'text' || e.target.type === 'number') return;
        submitFilter();
    });

    // Handle text input enter key or submit button
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        submitFilter();
    });

    function submitFilter() {
        const formData = new FormData(form);
        if (sortSelect) formData.set('sort', sortSelect.value);
        // Reset to page 1 on new filter
        formData.set('paged', '1');
        const params = new URLSearchParams(formData);
        fetchResults(params, false);
    }

    // Handle sort change
    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            submitFilter();
        });
    }

    // Chip removal logic
    if (chipsContainer) {
        chipsContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('a.hs-chip');
            if (!btn) return;
            e.preventDefault();

            // Just follow the link which is an absolute URL of the removed filter
            // Or parse the URL and fetch AJAX.
            // For simplicity, just follow the link to reload without the filter.
            window.location.href = btn.href;
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

    // Bind original pagination on load
    bindPagination();

})();
