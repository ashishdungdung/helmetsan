(function() {
    'use strict';

    function getGtag() {
        if (typeof window.gtag === 'function') {
            return window.gtag;
        }
        if (typeof window.dataLayer !== 'undefined') {
            return function(cmd, action, params) {
                if (cmd === 'event') {
                    window.dataLayer.push({
                        event: action,
                        ecommerce: params
                    });
                }
            };
        }
        return function() {};
    }

    function trackViewItem(data) {
        const gtag = getGtag();
        gtag('event', 'view_item', {
            currency: data.currency || 'USD',
            value: data.price,
            items: [{
                item_id: data.id,
                item_name: data.name,
                item_brand: data.brand,
                item_category: data.category,
                price: data.price,
                quantity: 1
            }]
        });
    }

    function trackLead(data, marketplace) {
        const gtag = getGtag();
        gtag('event', 'generate_lead', {
            currency: data.currency || 'USD',
            value: data.price,
            items: [{
                item_id: data.id,
                item_name: data.name,
                item_brand: data.brand,
                item_category: data.category,
                price: data.price,
                marketplace: marketplace
            }]
        });
        
        // Also support select_item for funnel tracking
        gtag('event', 'select_item', {
            item_list_id: 'where_to_buy',
            item_list_name: 'Where to Buy',
            items: [{
                item_id: data.id,
                item_name: data.name,
                marketplace: marketplace
            }]
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Check for data layer
        if (typeof window.helmetsanData === 'undefined') {
            return;
        }

        const data = window.helmetsanData;

        // 2. Track View Item
        trackViewItem(data);

        // 3. Track Affiliate Clicks (Event Delegation)
        document.body.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;

            // Check if it's a Buy button or inside Where to Buy section
            if (link.classList.contains('hs-price-cta') || link.closest('.hs-where-to-buy') || link.closest('.hs-price-table')) {
                // Determine marketplace: prefer query param, then data attribute, then domain
                let marketplace = 'unknown';
                const href = link.getAttribute('href') || '';
                
                // 1. Extract from marketplace= query param (internal redirect links)
                const paramMatch = href.match(/[?&]marketplace=([^&]+)/);
                if (paramMatch && paramMatch[1]) {
                    marketplace = decodeURIComponent(paramMatch[1]);
                }
                // 2. data-marketplace attribute on link or parent row
                else if (link.dataset.marketplace) {
                    marketplace = link.dataset.marketplace;
                } else if (link.closest('[data-marketplace]')) {
                    marketplace = link.closest('[data-marketplace]').dataset.marketplace;
                }
                // 3. Domain matching for direct external links
                else if (href.includes('amazon')) marketplace = 'amazon';
                else if (href.includes('revzilla')) marketplace = 'revzilla';
                else if (href.includes('fc-moto')) marketplace = 'fc-moto';
                else if (href.includes('allegro')) marketplace = 'allegro';
                else if (href.includes('jumia')) marketplace = 'jumia';

                trackLead(data, marketplace);
            }
        });
    });
})();
