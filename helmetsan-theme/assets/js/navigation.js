(function () {
    'use strict';

    /* ── Hamburger toggle ─────────────────────────── */
    var toggle = document.querySelector('.hs-nav-toggle');
    var header = document.querySelector('.site-header');
    if (toggle && header) {
        toggle.addEventListener('click', function () {
            var isOpen = header.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        /* Close menu when clicking a nav link (mobile) */
        var nav = header.querySelector('.hs-primary-nav');
        if (nav) {
            nav.addEventListener('click', function (e) {
                if (e.target.tagName === 'A' && window.innerWidth <= 960) {
                    header.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }

    /* ── Legacy mega-menu panel toggle (backward compat) ── */
    var legacyToggle = document.querySelector('.hs-mega-menu-toggle');
    var legacyPanel = document.getElementById('hs-mega-menu-panel');
    if (legacyToggle && legacyPanel) {
        legacyToggle.addEventListener('click', function () {
            var expanded = legacyToggle.getAttribute('aria-expanded') === 'true';
            legacyToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            legacyPanel.hidden = expanded;
        });
    }
})();
