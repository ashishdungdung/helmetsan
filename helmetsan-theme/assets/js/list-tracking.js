(function() {
    'use strict';
    if (typeof window.helmetsanListContext === 'undefined') return;
    var ctx = window.helmetsanListContext;
    var listId = ctx.list_id || 'list';
    var listName = ctx.list_name || 'List';

    function getGtag() {
        if (typeof window.gtag === 'function') return window.gtag;
        if (typeof window.dataLayer !== 'undefined') {
            return function(cmd, action, params) {
                if (cmd === 'event' && action === 'view_item_list' && params && typeof params === 'object') {
                    window.dataLayer.push({ event: action, ...params });
                }
            };
        }
        return function() {};
    }

    document.addEventListener('DOMContentLoaded', function() {
        var cards = document.querySelectorAll('[data-helmet-id]');
        if (!cards.length) return;
        var items = [];
        cards.forEach(function(card, idx) {
            items.push({
                item_id: card.getAttribute('data-helmet-id') || '',
                item_name: card.getAttribute('data-helmet-name') || '',
                item_brand: card.getAttribute('data-helmet-brand') || '',
                price: parseFloat(card.getAttribute('data-helmet-price') || '0', 10) || 0,
                index: idx + 1
            });
        });
        if (items.length) getGtag()('event', 'view_item_list', { item_list_id: listId, item_list_name: listName, items: items });
    });
})();
