/**
 * Helmetsan Subtle Interactions & Scroll Animations.
 * Clean, purposeful micro-interactions without distracting effects.
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {

        // --- 1. Subtle Card Hover Lift (no 3D tilt, just a clean lift) ---
        const liftTargets = document.querySelectorAll(
            '.helmet-card, .brand-card, .accessory-card'
        );

        liftTargets.forEach(card => {
            card.style.transition = 'transform 0.25s ease, box-shadow 0.25s ease';

            card.addEventListener('mouseenter', function() {
                card.style.transform = 'translateY(-3px)';
                card.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.12)';
            });

            card.addEventListener('mouseleave', function() {
                card.style.transform = 'translateY(0)';
                card.style.boxShadow = '';
            });
        });


        // --- 2. Viewport Scroll Reveal Observer ---
        const revealElements = document.querySelectorAll('.hs-reveal');
        
        if (revealElements.length > 0) {
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-revealed');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, {
                root: null,
                threshold: 0.01,
                rootMargin: '0px 0px -40px 0px'
            });

            revealElements.forEach(el => revealObserver.observe(el));
        }


        // --- 3. Page Scroll Progress Indicator ---
        const progressBar = document.querySelector('.hs-scroll-progress-bar');
        
        if (progressBar) {
            window.addEventListener('scroll', function() {
                const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                const scrolled = height > 0 ? (winScroll / height) * 100 : 0;
                progressBar.style.width = scrolled + '%';
            });
        }
    });
})();
