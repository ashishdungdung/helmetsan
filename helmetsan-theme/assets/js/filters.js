(function () {
    'use strict';

    var form = document.getElementById('hsHelmetFilterForm');
    var panel = document.getElementById('hsFilterPanel');
    var openBtn = document.querySelector('[data-open-filter]');
    var closeBtn = document.querySelector('[data-close-filter]');
    var openSortBtn = document.querySelector('[data-open-sort]');
    var openSizeBtn = document.querySelector('[data-open-size]');
    var sortSelect = document.getElementById('hsSort');
    var sizeSection = document.getElementById('hs-filter-size');

    if (!form || !panel) {
        return;
    }

    var mqDesktop = window.matchMedia('(min-width: 961px)');

    var openPanel = function () {
        panel.classList.add('is-open');
        document.body.classList.add('hs-filter-open');
    };

    var closePanel = function () {
        panel.classList.remove('is-open');
        document.body.classList.remove('hs-filter-open');
    };

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            openPanel();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closePanel();
        });
    }

    if (openSortBtn && sortSelect) {
        openSortBtn.addEventListener('click', function () {
            sortSelect.focus();
        });
    }

    if (openSizeBtn) {
        openSizeBtn.addEventListener('click', function () {
            openPanel();
            if (sizeSection) {
                sizeSection.setAttribute('open', 'open');
                sizeSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    if (mqDesktop.matches) {
        var controls = form.querySelectorAll('input[type="checkbox"], input[type="radio"], select');
        controls.forEach(function (el) {
            el.addEventListener('change', function () {
                form.submit();
            });
        });
    }

    form.addEventListener('submit', function () {
        closePanel();
    });
})();
