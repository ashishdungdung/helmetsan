/**
 * Helmetsan 5-Shot Product Gallery Frontend Controller
 * Angle switching, WebP preloading, zoom lightbox, and mobile touch swipe.
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    initGalleries();
  });

  function initGalleries() {
    const galleries = document.querySelectorAll('.helmetsan-gallery-wrap');
    galleries.forEach(gallery => setupGallery(gallery));
  }

  function setupGallery(wrap) {
    const mainImg = wrap.querySelector('.hs-gallery-main-img');
    const thumbBtns = wrap.querySelectorAll('.hs-gallery-thumb-btn');
    const angleTitle = wrap.querySelector('.hs-gallery-angle-title');
    const angleDesc = wrap.querySelector('.hs-gallery-angle-desc');
    const kimiBadge = wrap.querySelector('.hs-gallery-badge-kimi');
    const lightbox = wrap.querySelector('.hs-gallery-lightbox');
    const lightboxImg = wrap.querySelector('.hs-lightbox-img');

    if (!mainImg || thumbBtns.length === 0) return;

    let currentIndex = 0;

    // Preload all gallery URLs
    thumbBtns.forEach(btn => {
      const heroUrl = btn.dataset.heroUrl;
      if (heroUrl) {
        const img = new Image();
        img.src = heroUrl;
      }
    });

    function selectShot(index) {
      if (index < 0) index = thumbBtns.length - 1;
      if (index >= thumbBtns.length) index = 0;

      currentIndex = index;
      const btn = thumbBtns[index];

      thumbBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const heroUrl = btn.dataset.heroUrl || btn.dataset.fullUrl;
      const title = btn.dataset.title || '';
      const desc = btn.dataset.desc || '';
      const score = btn.dataset.score || '';

      // Cross-fade image
      mainImg.classList.add('loading');
      setTimeout(() => {
        mainImg.src = heroUrl;
        mainImg.alt = title;
        mainImg.classList.remove('loading');
      }, 150);

      if (angleTitle) angleTitle.textContent = title;
      if (angleDesc) angleDesc.textContent = desc;

      if (kimiBadge) {
        if (score && parseInt(score, 10) > 0) {
          kimiBadge.innerHTML = `✓ Kimi-K3 Inspected • <strong>${score}/100</strong>`;
          kimiBadge.style.display = 'flex';
        } else {
          kimiBadge.style.display = 'none';
        }
      }
    }

    thumbBtns.forEach((btn, idx) => {
      btn.addEventListener('click', () => selectShot(idx));
    });

    // Lightbox zoom
    mainImg.addEventListener('click', () => {
      if (!lightbox || !lightboxImg) return;
      lightboxImg.src = mainImg.src;
      lightbox.classList.remove('hidden');
    });

    if (lightbox) {
      lightbox.addEventListener('click', () => {
        lightbox.classList.add('hidden');
      });
    }

    // Keyboard navigation
    document.addEventListener('keydown', e => {
      if (lightbox && !lightbox.classList.contains('hidden')) {
        if (e.key === 'Escape') lightbox.classList.add('hidden');
        return;
      }

      if (e.key === 'ArrowRight') selectShot(currentIndex + 1);
      if (e.key === 'ArrowLeft') selectShot(currentIndex - 1);
    });

    // Mobile swipe gestures
    let touchStartX = 0;
    let touchEndX = 0;

    wrap.addEventListener('touchstart', e => {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    wrap.addEventListener('touchend', e => {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
    }, { passive: true });

    function handleSwipe() {
      const diff = touchStartX - touchEndX;
      if (Math.abs(diff) > 40) {
        if (diff > 0) {
          selectShot(currentIndex + 1); // Swipe left -> next
        } else {
          selectShot(currentIndex - 1); // Swipe right -> prev
        }
      }
    }
  }
})();
