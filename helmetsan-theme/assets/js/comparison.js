/**
 * Comparison Feature Logic
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'helmetsan_compare';
    const MAX_ITEMS = 4;
    
    // State: Array of objects { id, title, img }
    let comparedItems = [];
    
    // DOM Elements
    let floatBar, listContainer, countSpan, viewBtn;

    function init() {
        console.log('Helmetsan Comparison: initializing...');
        
        // Initialize DOM elements
        floatBar = document.getElementById('hs-comparison-bar');
        listContainer = document.getElementById('hs-comparison-list');
        countSpan = document.getElementById('hs-comparison-count');
        viewBtn = document.getElementById('hs-comparison-view');

        if (!floatBar) {
            console.warn('Helmetsan Comparison: #hs-comparison-bar not found.');
        }

        // Load Data (with migration from old format)
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                const parsed = JSON.parse(stored);
                if (!Array.isArray(parsed)) {
                    comparedItems = [];
                } else if (parsed.length > 0 && typeof parsed[0] === 'number') {
                    // MIGRATION: Old format was [id, id, id]
                    // Convert to new format [{id, title, img}, ...]
                    comparedItems = parsed.map(id => ({ id: id, title: 'Helmet', img: '' }));
                    save(); // Persist the migrated format
                    console.log('Helmetsan Comparison: Migrated old data format.');
                } else {
                    comparedItems = parsed;
                }
            }
        } catch (e) {
            console.error('Comparison storage error', e);
        }

        // Single One-Time Event Binding (Delegated)
        bindGlobalEvents();

        // Initial UI Update
        updateUI();
    }

    function bindGlobalEvents() {
        // We bind to body ONCE. No more re-binding on mutations.
        document.body.addEventListener('click', function(e) {
            // 1. Toggle Button
            const btn = e.target.closest('.js-add-to-compare');
            if (btn) {
                e.preventDefault();
                const id = parseInt(btn.dataset.id, 10);
                if (id) {
                    toggleItem(btn, id);
                }
                return;
            }

            // 2. Clear Button
            const clearBtn = e.target.closest('#hs-comparison-clear');
            if (clearBtn) {
                e.preventDefault();
                console.log('Helmetsan Comparison: Clearing all');
                comparedItems = [];
                save();
                updateUI();
                return;
            }
        });
    }

    function toggleItem(btn, id) {
        if (!id) return;

        const idx = comparedItems.findIndex(item => item.id === id);
        
        if (idx > -1) {
            // Remove
            console.log('Helmetsan Comparison: Removing ID', id);
            comparedItems.splice(idx, 1);
        } else {
            // Add
            if (comparedItems.length >= MAX_ITEMS) {
                alert(`You can compare up to ${MAX_ITEMS} helmets. Please remove one first.`);
                return;
            }
            
            console.log('Helmetsan Comparison: Adding ID', id);
            
            // Extract data from context
            let title = 'Helmet';
            let img = '';
            
            if (btn && btn.closest) {
                const card = btn.closest('.helmet-card') || btn.closest('.helmet-single');
                if (card) {
                    const titleEl = card.querySelector('.helmet-card__title a') || card.querySelector('h1');
                    if (titleEl) title = titleEl.textContent.trim();
                    const imgEl = card.querySelector('img');
                    if (imgEl) img = imgEl.src;
                }
            }
            
            comparedItems.push({ id, title, img });
        }
        save();
        updateUI();
    }

    function save() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(comparedItems));
    }

    function updateUI() {
        // console.log('Helmetsan Comparison: UI Update'); // Too verbose for prod
        const currentIds = comparedItems.map(i => i.id);

        // 1. Reactively update ALL buttons currently in DOM
        const allBtns = document.querySelectorAll('.js-add-to-compare');
        allBtns.forEach(btn => {
            const id = parseInt(btn.dataset.id, 10);
            const span = btn.querySelector('span');
            // Safe check
            if(!id) return;

            if (currentIds.includes(id)) {
                btn.classList.add('is-active', 'hs-btn--primary');
                btn.setAttribute('aria-pressed', 'true');
                if(span) span.textContent = 'Remove';
            } else {
                btn.classList.remove('is-active', 'hs-btn--primary');
                btn.setAttribute('aria-pressed', 'false');
                if(span) span.textContent = 'Compare';
            }
        });

        // 2. Update specific "View" buttons next to active compare buttons
        const viewLinkBtns = document.querySelectorAll('.js-view-compare');
        viewLinkBtns.forEach(btn => {
             // Find closest structure
             const context = btn.closest('.helmet-single__media') || btn.closest('.helmet-card') || btn.parentElement;
             if (context) {
                 const sibling = context.querySelector('.js-add-to-compare');
                 if (sibling) {
                     const id = parseInt(sibling.dataset.id, 10);
                     if (currentIds.includes(id)) {
                         btn.classList.remove('is-hidden');
                         btn.href = '/comparison/?ids=' + currentIds.join(',');
                     } else {
                         btn.classList.add('is-hidden');
                     }
                 }
             }
        });

        // 3. Update Float Bar
        if (floatBar) {
            if (comparedItems.length > 0) {
                floatBar.classList.remove('is-hidden');
                floatBar.style.display = 'flex'; 
                
                if (countSpan) countSpan.textContent = comparedItems.length;
                
                // Update Thumbnails
                if (listContainer) {
                    listContainer.innerHTML = '';
                    comparedItems.forEach(item => {
                        const thumb = document.createElement('div');
                        thumb.className = 'hs-comp-thumb';
                        thumb.title = 'Remove ' + item.title;
                        
                        if (item.img) {
                             const img = document.createElement('img');
                             img.src = item.img;
                             img.alt = item.title;
                             thumb.appendChild(img);
                        } else {
                             const span = document.createElement('span');
                             span.textContent = item.id;
                             thumb.appendChild(span);
                        }
                        
                        // Inline binding for generated elements is fine/simplest here
                        thumb.onclick = (e) => {
                             e.preventDefault(); 
                             e.stopPropagation();
                             toggleItem({}, item.id); // Passing empty obj as btn mimics external call
                        };

                        listContainer.appendChild(thumb);
                    });
                }

                if (viewBtn) {
                     viewBtn.href = `/comparison/?ids=${currentIds.join(',')}`;
                     viewBtn.textContent = `Compare Now (${comparedItems.length})`;
                }
            } else {
                floatBar.classList.add('is-hidden');
            }
        }
    }

    // Export for debug
    window.HelmetsanCompare = {
        update: updateUI,
        reset: () => { comparedItems = []; save(); updateUI(); }
    };

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Auto-update on AJAX mutations
    // Key: Only call updateUI(), never re-bind events!
    const observer = new MutationObserver((mutations) => {
        let shouldUpdate = false;
        mutations.forEach(mutation => {
            if (mutation.addedNodes.length) shouldUpdate = true;
        });
        if (shouldUpdate) {
            setTimeout(updateUI, 100);
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

})();
