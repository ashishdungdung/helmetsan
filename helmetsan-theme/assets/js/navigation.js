(function () {
    'use strict';

    /* ── Full-screen Navigation ───────────────────── */
    var toggle = document.querySelector('.hs-nav-toggle');
    var header = document.querySelector('.site-header');
    var body = document.body;
    
    if (toggle && header) {
        toggle.addEventListener('click', function () {
            var isOpen = header.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            body.classList.toggle('hs-nav-active', isOpen);
        });

        var nav = header.querySelector('.hs-primary-nav');
        if (nav) {
            nav.addEventListener('click', function (e) {
                if (e.target.tagName === 'A' && window.innerWidth <= 960) {
                    header.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    body.classList.remove('hs-nav-active');
                }
            });
        }
    }

    /* ── Search Overlay ───────────────────────────── */
    var searchTrigger = document.querySelector('.hs-search-trigger');
    var searchOverlay = document.getElementById('hsSearchOverlay');
    var searchClose = document.querySelector('.hs-search-overlay__close');
    
    if (searchTrigger && searchOverlay && searchClose) {
        searchTrigger.addEventListener('click', function() {
            searchOverlay.classList.add('is-active');
            searchOverlay.setAttribute('aria-hidden', 'false');
            body.classList.add('hs-search-active');
            var input = searchOverlay.querySelector('input');
            if (input) setTimeout(function() { input.focus(); }, 300);
        });

        searchClose.addEventListener('click', function() {
            searchOverlay.classList.remove('is-active');
            searchOverlay.setAttribute('aria-hidden', 'true');
            body.classList.remove('hs-search-active');
        });
        
        // Escape key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && searchOverlay.classList.contains('is-active')) {
                searchClose.click();
            }
        });
    }

    /* ── Mobile PDP Sticky Head ───────────────────── */
    var stickyHead = document.getElementById('hs-mobile-sticky-header');
    if (stickyHead) {
        var threshold = 300; 
        window.addEventListener('scroll', function () {
            if (window.scrollY > threshold) {
                if (!stickyHead.classList.contains('is-visible')) {
                    stickyHead.classList.add('is-visible');
                    stickyHead.setAttribute('aria-hidden', 'false');
                }
            } else {
                if (stickyHead.classList.contains('is-visible')) {
                    stickyHead.classList.remove('is-visible');
                    stickyHead.setAttribute('aria-hidden', 'true');
                }
            }
        }, { passive: true });
    }

    /* ── Segmented Control ────────────────────────── */
    var segmentContainer = document.getElementById('hsPdpSegments');
    if (segmentContainer) {
        var btns = segmentContainer.querySelectorAll('.hs-segmented-control__btn');
        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var segmentId = btn.getAttribute('data-segment');
                
                // Update buttons
                btns.forEach(function(b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                
                // Update content
                var allSegments = document.querySelectorAll('.hs-segment-content');
                allSegments.forEach(function(s) { s.classList.remove('is-active'); });
                var activeSegment = document.getElementById('segment-' + segmentId);
                if (activeSegment) activeSegment.classList.add('is-active');

                // Haptic-like scroll top
                window.scrollTo({ top: segmentContainer.offsetTop - 120, behavior: 'smooth' });
            });
        });
    }

    /* ── Language Selector Navigation ────────────── */
    var langSelects = document.querySelectorAll('.hs-language-select');
    if (langSelects && langSelects.length > 0) {
        for (var i = 0; i < langSelects.length; i++) {
            langSelects[i].addEventListener('change', function () {
                var targetUrl = this.value;
                if (targetUrl && targetUrl !== window.location.href) {
                    window.location.href = targetUrl;
                }
            });
        }
    }
})();
