/**
 * Helmetsan 1-Click Comparison System Controller (V3 Enterprise)
 * Synchronizes LocalStorage, Sticky Bar UI, Checkboxes, PDP Buttons & Routing
 */
document.addEventListener('DOMContentLoaded', function() {
    const STORAGE_KEY = 'helmetsan_compare_list';
    
    // UI Elements (Supports multiple ID/class variants)
    const barEl = document.getElementById('hs-comparison-bar') || document.getElementById('hsComparisonTray');
    const listEl = document.getElementById('hs-comparison-list') || document.getElementById('hsCompareChips');
    const countEl = document.getElementById('hs-comparison-count') || document.getElementById('hsCompareCount');
    const clearBtn = document.getElementById('hs-comparison-clear') || document.getElementById('hsClearCompare');
    const viewBtn = document.getElementById('hs-comparison-view');

    function getCompareList() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch(e) {
            return [];
        }
    }

    function saveCompareList(list) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        updateTrayUI();
        syncButtonsAndCheckboxes();
    }

    function syncButtonsAndCheckboxes() {
        const list = getCompareList();
        const ids = list.map(item => String(item.id));
        const slugs = list.map(item => String(item.slug || item.id));

        // Sync Checkboxes
        document.querySelectorAll('.hs-compare-checkbox').forEach(cb => {
            const cbId = String(cb.dataset.helmetId || cb.dataset.id || cb.value);
            cb.checked = ids.includes(cbId) || slugs.includes(cbId);
        });

        // Sync PDP / Single-Helmet Buttons (.js-add-to-compare)
        document.querySelectorAll('.js-add-to-compare').forEach(btn => {
            const btnId = String(btn.dataset.id || btn.dataset.helmetId);
            const isAdded = ids.includes(btnId) || slugs.includes(btnId);

            if (btn.tagName.toLowerCase() === 'button') {
                if (isAdded) {
                    btn.classList.add('is-active', 'hs-btn--success');
                    btn.setAttribute('title', 'Remove from comparison');
                } else {
                    btn.classList.remove('is-active', 'hs-btn--success');
                    btn.setAttribute('title', 'Add to comparison');
                }
            } else if (btn.tagName.toLowerCase() === 'a') {
                if (isAdded) {
                    btn.textContent = '✓ In Compare (View →)';
                    btn.classList.add('hs-btn--success');
                } else {
                    btn.textContent = '+ Add to compare';
                    btn.classList.remove('hs-btn--success');
                }
            }
        });
    }

    function updateTrayUI() {
        const list = getCompareList();
        if (!barEl) return;

        if (list.length > 0) {
            barEl.classList.remove('is-hidden', 'hs-hidden');
            if (countEl) countEl.textContent = list.length;
            
            if (listEl) {
                listEl.innerHTML = list.map(item => `
                    <span class="hs-chip hs-chip-dark hs-flex hs-items-center hs-gap-1" style="display:inline-flex; align-items:center; background:rgba(30,41,59,0.9); color:#fff; padding:0.25rem 0.6rem; border-radius:9999px; font-size:0.8rem; border:1px solid rgba(255,255,255,0.15);">
                        ${item.title}
                        <button type="button" class="hs-remove-compare-chip" data-id="${item.id}" style="background:none; border:none; color:#f43f5e; font-weight:bold; margin-left:0.3rem; cursor:pointer;">&times;</button>
                    </span>
                `).join('');
            }

            if (viewBtn) {
                const idsParam = list.map(item => item.slug || item.id).join(',');
                const baseUrl = viewBtn.getAttribute('href') ? viewBtn.getAttribute('href').split('?')[0] : '/comparison/';
                viewBtn.setAttribute('href', `${baseUrl}?ids=${idsParam}`);
            }
        } else {
            barEl.classList.add('is-hidden', 'hs-hidden');
        }
    }

    // Toggle Action Handler
    function toggleHelmetInCompare(helmetObj) {
        let list = getCompareList();
        const existsIndex = list.findIndex(item => String(item.id) === String(helmetObj.id) || String(item.slug) === String(helmetObj.slug));

        if (existsIndex > -1) {
            list.splice(existsIndex, 1);
        } else {
            if (list.length >= 4) {
                alert('You can compare up to 4 helmets simultaneously.');
                return false;
            }
            list.push(helmetObj);
        }
        saveCompareList(list);
        return true;
    }

    // Click Delegation Listener for Buttons & Anchors
    document.addEventListener('click', function(e) {
        // Handle .js-add-to-compare buttons & links
        const addBtn = e.target.closest('.js-add-to-compare');
        if (addBtn) {
            const id = addBtn.dataset.id || addBtn.dataset.helmetId;
            const title = addBtn.dataset.title || addBtn.dataset.helmetTitle || document.title.split('-')[0].trim();
            const slug = addBtn.dataset.slug || id;

            if (id) {
                const list = getCompareList();
                const isAlreadyIn = list.some(item => String(item.id) === String(id) || String(item.slug) === String(slug));
                
                // If it's an anchor tag and already in compare, allow navigating to /comparison/?ids=...
                if (addBtn.tagName.toLowerCase() === 'a' && isAlreadyIn) {
                    const idsParam = list.map(item => item.slug || item.id).join(',');
                    addBtn.setAttribute('href', `/comparison/?ids=${idsParam}`);
                    return;
                }

                e.preventDefault();
                toggleHelmetInCompare({ id: id, slug: slug, title: title });
            }
            return;
        }

        // Handle Chip Remove Buttons
        const removeBtn = e.target.closest('.hs-remove-compare-chip');
        if (removeBtn) {
            e.preventDefault();
            const id = removeBtn.dataset.id;
            let list = getCompareList().filter(item => String(item.id) !== String(id) && String(item.slug) !== String(id));
            saveCompareList(list);
            return;
        }

        // Handle Clear Button
        if (e.target && (e.target.id === 'hs-comparison-clear' || e.target.id === 'hsClearCompare')) {
            e.preventDefault();
            saveCompareList([]);
            return;
        }
    });

    // Checkbox Change Listener
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('hs-compare-checkbox')) {
            const cb = e.target;
            const id = cb.dataset.helmetId || cb.dataset.id || cb.value;
            const title = cb.dataset.helmetTitle || id;
            const slug = cb.dataset.slug || id;
            
            toggleHelmetInCompare({ id: id, slug: slug, title: title });
        }
    });

    // Initial Sync
    updateTrayUI();
    syncButtonsAndCheckboxes();
});
