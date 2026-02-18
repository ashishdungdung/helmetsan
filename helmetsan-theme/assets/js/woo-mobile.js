(function () {
    'use strict';

    var isWoo = document.body.classList.contains('woocommerce') || document.body.classList.contains('woocommerce-page');
    if (!isWoo) {
        return;
    }

    var orderSelect = document.querySelector('form.woocommerce-ordering select.orderby');
    var firstQty = document.querySelector('.woocommerce-cart-form input.qty, form.cart input.qty');

    var mount = document.querySelector('.hs-woo-mobile-tools');
    if (!mount) {
        mount = document.createElement('div');
        mount.className = 'hs-woo-mobile-tools';

        var btnFilter = document.createElement('button');
        btnFilter.type = 'button';
        btnFilter.setAttribute('data-woo-filter', '1');
        btnFilter.textContent = 'Filter';

        var btnSort = document.createElement('button');
        btnSort.type = 'button';
        btnSort.setAttribute('data-woo-sort', '1');
        btnSort.textContent = 'Sort';

        var btnSize = document.createElement('button');
        btnSize.type = 'button';
        btnSize.setAttribute('data-woo-size', '1');
        btnSize.textContent = 'Size';

        mount.appendChild(btnFilter);
        mount.appendChild(btnSort);
        mount.appendChild(btnSize);

        var target = document.querySelector('.woocommerce-notices-wrapper') || document.querySelector('.site-main');
        if (target && target.parentNode) {
            target.parentNode.insertBefore(mount, target.nextSibling);
        }
    }

    var filterBtn = mount.querySelector('[data-woo-filter]');
    var sortBtn = mount.querySelector('[data-woo-sort]');
    var sizeBtn = mount.querySelector('[data-woo-size]');

    if (filterBtn) {
        filterBtn.addEventListener('click', function () {
            var sidebars = document.querySelectorAll('.widget-area, .shop-sidebar, #secondary');
            if (!sidebars.length) {
                return;
            }
            sidebars[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    if (sortBtn) {
        sortBtn.addEventListener('click', function () {
            if (orderSelect) {
                orderSelect.focus();
            }
        });
    }

    if (sizeBtn) {
        sizeBtn.addEventListener('click', function () {
            var sizeField = document.querySelector('form.cart select[name^=\"attribute_pa_size\"], form.cart select[name*=\"size\"], form.cart input[name*=\"size\"], form.cart .variations select');
            if (sizeField) {
                sizeField.focus();
                sizeField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (firstQty) {
                firstQty.focus();
            }
        });
    }

    var stickyBar = document.querySelector('.hs-woo-sticky-atc');
    if (!stickyBar) {
        return;
    }

    var stickyBtn = stickyBar.querySelector('.hs-woo-sticky-atc__btn');
    var stickyPrice = stickyBar.querySelector('.hs-woo-sticky-atc__price');
    var stickyStock = stickyBar.querySelector('.hs-woo-sticky-atc__stock');
    var nativeBtn = document.querySelector('form.cart .single_add_to_cart_button');
    var nativePrice = document.querySelector('.summary .price');
    var variationForm = document.querySelector('form.variations_form');

    var setStockBadge = function (label, kind) {
        if (!stickyStock) {
            return;
        }
        stickyStock.textContent = label;
        stickyStock.classList.remove('hs-stock--in', 'hs-stock--low', 'hs-stock--out', 'hs-stock--neutral');
        stickyStock.classList.add(kind || 'hs-stock--neutral');
    };

    var updateSimpleStock = function () {
        var stockNode = document.querySelector('.summary .stock');
        if (!stockNode) {
            setStockBadge('Select size', 'hs-stock--neutral');
            return;
        }
        var text = (stockNode.textContent || '').trim().toLowerCase();
        if (text.indexOf('out of stock') !== -1) {
            setStockBadge('Out of Stock', 'hs-stock--out');
        } else if (text.indexOf('only') !== -1 || text.indexOf('low') !== -1) {
            setStockBadge('Low Stock', 'hs-stock--low');
        } else {
            setStockBadge('In Stock', 'hs-stock--in');
        }
    };

    var syncStickyState = function () {
        if (!stickyBtn || !nativeBtn) {
            return;
        }
        var isDisabled = nativeBtn.disabled || nativeBtn.classList.contains('disabled');
        stickyBtn.disabled = !!isDisabled;
        stickyBtn.textContent = isDisabled ? 'Select Options' : 'Add to Cart';
    };

    if (stickyPrice && nativePrice && nativePrice.textContent) {
        stickyPrice.textContent = nativePrice.textContent.trim();
    }

    syncStickyState();
    updateSimpleStock();

    if (stickyBtn && nativeBtn) {
        stickyBtn.addEventListener('click', function () {
            if (stickyBtn.disabled) {
                var firstVariation = document.querySelector('form.variations_form select');
                if (firstVariation) {
                    firstVariation.focus();
                }
                return;
            }
            nativeBtn.click();
        });
    }

    if (variationForm && window.jQuery) {
        window.jQuery(variationForm).on('found_variation', function (event, variation) {
            if (variation && stickyPrice && variation.price_html) {
                var temp = document.createElement('div');
                temp.innerHTML = variation.price_html;
                var txt = (temp.textContent || '').trim();
                if (txt) {
                    stickyPrice.textContent = txt;
                }
            }
            if (variation) {
                if (variation.is_in_stock) {
                    var maxQty = Number(variation.max_qty || 0);
                    if (maxQty > 0 && maxQty <= 3) {
                        setStockBadge('Low Stock', 'hs-stock--low');
                    } else {
                        setStockBadge('In Stock', 'hs-stock--in');
                    }
                } else {
                    setStockBadge('Out of Stock', 'hs-stock--out');
                }
            }
            window.setTimeout(syncStickyState, 0);
        });

        window.jQuery(variationForm).on('reset_data', function () {
            setStockBadge('Select size', 'hs-stock--neutral');
            window.setTimeout(function () {
                if (stickyPrice && nativePrice && nativePrice.textContent) {
                    stickyPrice.textContent = nativePrice.textContent.trim();
                }
                syncStickyState();
            }, 0);
        });
    }

    document.addEventListener('change', function (event) {
        if (!variationForm) {
            return;
        }
        if (variationForm.contains(event.target)) {
            window.setTimeout(syncStickyState, 0);
        }
    });

    var toTitle = function (value) {
        return value.replace(/_/g, ' ').replace(/\\b\\w/g, function (m) { return m.toUpperCase(); });
    };

    if (window.matchMedia('(max-width: 960px)').matches) {
        var tabsContainer = document.querySelector('.woocommerce-tabs');
        if (tabsContainer && !tabsContainer.querySelector('.hs-woo-mobile-accordion')) {
            var tabs = tabsContainer.querySelectorAll('ul.tabs li a');
            if (tabs.length) {
                var accordion = document.createElement('div');
                accordion.className = 'hs-woo-mobile-accordion';
                tabs.forEach(function (a, index) {
                    var href = a.getAttribute('href');
                    if (!href || href.charAt(0) !== '#') {
                        return;
                    }
                    var panel = tabsContainer.querySelector(href);
                    if (!panel) {
                        return;
                    }
                    var details = document.createElement('details');
                    if (index === 0) {
                        details.open = true;
                    }
                    var summary = document.createElement('summary');
                    summary.textContent = (a.textContent || '').trim() || toTitle(href.replace('#tab-', ''));
                    var wrap = document.createElement('div');
                    wrap.className = 'hs-woo-mobile-accordion__panel';
                    wrap.appendChild(panel.cloneNode(true));
                    details.appendChild(summary);
                    details.appendChild(wrap);
                    accordion.appendChild(details);
                });

                if (accordion.children.length) {
                    tabsContainer.appendChild(accordion);
                }
            }
        }
    }
})();
