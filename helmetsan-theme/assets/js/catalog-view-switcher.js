/**
 * Catalog View Switcher & Density Manager
 * Handles switching between grid, card, and list layouts and managing grid columns.
 */
document.addEventListener('DOMContentLoaded', () => {
    const resultsContainer = document.querySelector('.hs-catalog-grid'); // Select by class to work on all archives
    const viewButtons = document.querySelectorAll('.hs-view-btn');
    const densitySwitcher = document.getElementById('js-density-switcher');
    const densityButtons = document.querySelectorAll('.hs-density-btn');
    
    if (!resultsContainer) return;

    // View Switching Logic
    function applyView(view) {
        // Remove existing view classes
        resultsContainer.classList.remove('hs-catalog-grid--grid', 'hs-catalog-grid--classic', 'hs-catalog-grid--card', 'hs-catalog-grid--list');
        
        // Add new view class
        resultsContainer.classList.add(`hs-catalog-grid--${view}`);
        
        // Update button states
        viewButtons.forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.view === view);
        });
        
        // Handle density switcher visibility
        if (densitySwitcher) {
            densitySwitcher.style.display = (view === 'grid' || view === 'classic') ? 'flex' : 'none';
        }

        localStorage.setItem('hs_catalog_view', view);
        window.dispatchEvent(new Event('resize'));
    }

    viewButtons.forEach(btn => {
        btn.addEventListener('click', () => applyView(btn.dataset.view));
    });

    // Density Logic
    function applyDensity(cols) {
        resultsContainer.classList.remove('hs-catalog-grid--cols-2', 'hs-catalog-grid--cols-3', 'hs-catalog-grid--cols-4');
        resultsContainer.classList.add(`hs-catalog-grid--cols-${cols}`);
        
        densityButtons.forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.cols === cols);
        });
        
        localStorage.setItem('hs_catalog_density', cols);
    }

    densityButtons.forEach(btn => {
        btn.addEventListener('click', () => applyDensity(btn.dataset.cols));
    });

    // Initial State
    const savedView = localStorage.getItem('hs_catalog_view') || 'grid';
    const savedDensity = localStorage.getItem('hs_catalog_density') || '4';
    
    applyView(savedView);
    applyDensity(savedDensity);
});
