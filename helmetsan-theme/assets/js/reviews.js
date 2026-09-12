/**
 * Helmetsan Review System Frontend
 */
document.addEventListener('DOMContentLoaded', () => {
    const formWrap = document.getElementById('review-form-container');
    const toggleBtns = document.querySelectorAll('.js-toggle-review-form');
    const reviewForm = document.getElementById('hs-review-form');
    const ratingInput = document.querySelector('.js-rating-input');
    
    const stars = ratingInput ? ratingInput.querySelectorAll('.hs-rating-star') : [];
    const ratingHiddenInput = ratingInput ? ratingInput.querySelector('input[name="rating"]') : null;

    const apiBase = typeof hsReviews !== 'undefined' ? hsReviews.apiBase : '';
    const nonce = typeof hsReviews !== 'undefined' ? hsReviews.nonce : '';
    const productId = typeof hsReviews !== 'undefined' ? hsReviews.productId : '';

    // Toggle Form
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (formWrap) {
                formWrap.classList.toggle('is-hidden');
                if (!formWrap.classList.contains('is-hidden')) {
                    formWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });

    // Dynamic Tag Editor Upgrade for Pros and Cons
    ['review_pros', 'review_cons'].forEach(id => {
        const originalInput = document.getElementById(id);
        if (!originalInput) return;

        // Hide original input but keep it for form compatibility
        originalInput.style.display = 'none';

        // Create container
        const container = document.createElement('div');
        container.className = `hs-tag-editor hs-input hs-tag-editor--${id === 'review_pros' ? 'pro' : 'con'}`;
        container.style.cssText = 'display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; min-height: 42px; padding: 0.35rem 0.5rem; cursor: text; border-radius: var(--hs-radius); border: 1px solid var(--hs-border); background: var(--hs-panel);';

        const tagList = document.createElement('div');
        tagList.className = 'hs-tag-editor__list';
        tagList.style.cssText = 'display: flex; flex-wrap: wrap; gap: 0.35rem;';

        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = originalInput.placeholder;
        input.style.cssText = 'border: none; background: transparent; padding: 0; margin: 0; outline: none; flex-grow: 1; min-width: 120px; color: var(--hs-text); font-size: 0.9rem;';

        container.appendChild(tagList);
        container.appendChild(input);

        // Insert container right after the original input
        originalInput.parentNode.insertBefore(container, originalInput.nextSibling);

        // Click container focuses input
        container.addEventListener('click', () => input.focus());

        const tags = [];

        function updateTags() {
            tagList.innerHTML = '';
            tags.forEach((tag, idx) => {
                const tagEl = document.createElement('span');
                const isPro = id === 'review_pros';
                tagEl.className = `hs-review-tag hs-review-tag--${isPro ? 'pro' : 'con'}`;
                tagEl.style.cssText = `display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; cursor: pointer; transition: all 0.2s; ${
                    isPro ? 'background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); color: #4ade80;' 
                          : 'background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171;'
                }`;
                
                tagEl.innerHTML = `
                    <span>${tag}</span>
                    <span class="hs-tag-close" style="font-weight: 700; margin-left: 0.15rem; font-size: 0.85rem; opacity: 0.6;">&times;</span>
                `;

                // Hover transitions
                tagEl.addEventListener('mouseenter', () => tagEl.style.opacity = '0.8');
                tagEl.addEventListener('mouseleave', () => tagEl.style.opacity = '1');

                // Click removes tag
                tagEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    tags.splice(idx, 1);
                    updateTags();
                });

                tagList.appendChild(tagEl);
            });

            // Update original input value
            originalInput.value = tags.join(', ');
        }

        // Intercept keys
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const val = input.value.trim().replace(/,/g, '');
                if (val && !tags.includes(val)) {
                    tags.push(val);
                    updateTags();
                }
                input.value = '';
            } else if (e.key === 'Backspace' && input.value === '' && tags.length > 0) {
                tags.pop();
                updateTags();
            }
        });

        // Add tag on blur if value exists
        input.addEventListener('blur', () => {
            const val = input.value.trim().replace(/,/g, '');
            if (val && !tags.includes(val)) {
                tags.push(val);
                updateTags();
            }
            input.value = '';
        });
    });

    // Rating Input Logic
    if (ratingInput && ratingHiddenInput && stars.length > 0) {
        stars.forEach(star => {
            star.addEventListener('click', () => {
                const val = parseInt(star.dataset.value);
                ratingHiddenInput.value = val;
                stars.forEach(s => {
                    const sVal = parseInt(s.dataset.value);
                    s.classList.toggle('is-active', sVal <= val);
                });
            });

            star.addEventListener('mouseenter', () => {
                const val = parseInt(star.dataset.value);
                stars.forEach(s => {
                    const sVal = parseInt(s.dataset.value);
                    s.classList.toggle('is-hover', sVal <= val);
                });
            });

            star.addEventListener('mouseleave', () => {
                stars.forEach(s => s.classList.remove('is-hover'));
            });
        });
    }

    // Form Submission
    if (reviewForm) {
        reviewForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = reviewForm.querySelector('button[type="submit"]');
            const messageEl = reviewForm.querySelector('.js-form-message');
            
            const formData = new FormData(reviewForm);
            
            // Collect standard fields
            const data = {
                product_id: parseInt(reviewForm.dataset.productId),
                rating: ratingHiddenInput ? parseInt(formData.get('rating')) : 0,
                name: formData.get('name'),
                email: formData.get('email'),
                content: formData.get('content'),
                pros: formData.get('pros') ? formData.get('pros').split(',').map(s => s.trim()).filter(s => s !== '') : [],
                cons: formData.get('cons') ? formData.get('cons').split(',').map(s => s.trim()).filter(s => s !== '') : [],
                cf_turnstile_response: formData.get('cf-turnstile-response') || ''
            };

            // Basic validation
            if (ratingHiddenInput && data.rating === 0) {
                if (messageEl) {
                    messageEl.textContent = 'Please select a rating.';
                    messageEl.className = 'hs-form__message is-error';
                }
                return;
            }

            let originalText = '';
            if (submitBtn) {
                submitBtn.disabled = true;
                originalText = submitBtn.textContent;
                submitBtn.textContent = 'Submitting...';
            }
            if (messageEl) {
                messageEl.textContent = '';
                messageEl.className = 'hs-form__message';
            }

            try {
                const response = await fetch(apiBase + '/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce || ''
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    if (messageEl) {
                        messageEl.textContent = result.message;
                        messageEl.className = 'hs-form__message is-success';
                    }
                    reviewForm.reset();
                    if (stars) {
                        stars.forEach(s => s.classList.remove('is-active'));
                    }
                    // Hide form after delay
                    setTimeout(() => {
                        if (formWrap) {
                            formWrap.classList.add('is-hidden');
                        }
                        if (messageEl) {
                            messageEl.textContent = '';
                        }
                    }, 4000);
                } else {
                    if (messageEl) {
                        messageEl.textContent = result.message || 'Error submitting review. Please check all fields.';
                        messageEl.className = 'hs-form__message is-error';
                    }
                }
            } catch (error) {
                console.error('Review submission error:', error);
                if (messageEl) {
                    messageEl.textContent = 'Network error. Please try again later.';
                    messageEl.className = 'hs-form__message is-error';
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        });
    }

    // Load More Reviews
    const loadMoreBtn = document.querySelector('.js-load-more-reviews');
    const sortSelect = document.querySelector('.js-review-sort');
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', async () => {
            const page = parseInt(loadMoreBtn.dataset.page) + 1;
            const sort = sortSelect ? sortSelect.value : 'newest';
            
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = 'Loading...';

            try {
                const response = await fetch(`${apiBase}/${productId}?page=${page}&sort=${sort}`);
                const result = await response.json();

                if (result.success && Array.isArray(result.reviews)) {
                    const list = document.querySelector('.hs-reviews__list');
                    if (list) {
                        result.reviews.forEach(review => {
                            const card = document.createElement('article');
                            card.className = 'hs-review-card hs-panel';
                            card.innerHTML = renderReviewHtml(review);
                            list.appendChild(card);
                        });
                    }

                    loadMoreBtn.dataset.page = page;
                    if (page >= result.total_pages) {
                        loadMoreBtn.remove();
                    }
                }
            } catch (error) {
                console.error('Load more error:', error);
            } finally {
                if (loadMoreBtn && loadMoreBtn.parentNode) {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'Load More Reviews';
                }
            }
        });
    }

    // Sort change logic
    if (sortSelect) {
        sortSelect.addEventListener('change', async () => {
            const list = document.querySelector('.js-reviews-list');
            if (!list) return;

            const sort = sortSelect.value;
            list.style.opacity = '0.5';

            try {
                const response = await fetch(`${apiBase}/${productId}?page=1&sort=${sort}`);
                const result = await response.json();

                if (result.success && Array.isArray(result.reviews)) {
                    list.innerHTML = '';
                    result.reviews.forEach(review => {
                        const card = document.createElement('article');
                        card.className = 'hs-review-card hs-panel';
                        card.innerHTML = renderReviewHtml(review);
                        list.appendChild(card);
                    });

                    if (loadMoreBtn) {
                        loadMoreBtn.dataset.page = 1;
                        if (1 >= result.total_pages) {
                            loadMoreBtn.style.display = 'none';
                        } else {
                            loadMoreBtn.style.display = 'inline-flex';
                        }
                    }
                }
            } catch (error) {
                console.error('Sort error:', error);
            } finally {
                if (list) {
                    list.style.opacity = '1';
                }
            }
        });
    }

    // Voting logic (Event Delegation)
    document.addEventListener('click', async (e) => {
        const voteBtn = e.target.closest('.js-vote-btn');
        if (!voteBtn) return;

        const container = voteBtn.closest('.js-review-voting');
        if (!container) return;

        const reviewId = container.dataset.reviewId;
        const vote = voteBtn.dataset.vote;

        // Optimistic UI update and disable buttons
        const btns = container.querySelectorAll('.js-vote-btn');
        btns.forEach(b => b.disabled = true);

        try {
            const response = await fetch(apiBase + '/vote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce || ''
                },
                body: JSON.stringify({ review_id: parseInt(reviewId), vote: vote })
            });
            const result = await response.json();

            if (result.success) {
                const helpfulSpan = container.querySelector('.js-vote-count-helpful');
                const unhelpfulSpan = container.querySelector('.js-vote-count-unhelpful');
                if (helpfulSpan) helpfulSpan.textContent = `(${result.helpful_votes})`;
                if (unhelpfulSpan) unhelpfulSpan.textContent = `(${result.unhelpful_votes})`;
            }
        } catch (error) {
            console.error('Vote error:', error);
            btns.forEach(b => b.disabled = false);
        }
    });

    function renderReviewHtml(review) {
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            starsHtml += `<span class="hs-star ${i <= review.rating ? 'is-active' : ''}">
                <svg class="hs-icon hs-icon--star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </span>`;
        }

        let prosConsHtml = '';
        if ((review.pros && review.pros.length > 0) || (review.cons && review.cons.length > 0)) {
            prosConsHtml = `<div class="hs-review-card__pros-cons">
                ${review.pros && review.pros.length > 0 ? `
                    <div class="hs-review-card__pros" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                        <span style="font-weight: 600; color: var(--hs-success, #22c55e); font-size: 0.85rem; margin-right: 0.25rem;">Pros:</span>
                        ${review.pros.map(pro => `
                            <span class="hs-review-tag hs-review-tag--pro" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); color: #4ade80;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><polyline points="20 6 9 17 4 12"/></svg>
                                ${pro}
                            </span>
                        `).join('')}
                    </div>
                ` : ''}
                ${review.cons && review.cons.length > 0 ? `
                    <div class="hs-review-card__cons" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                        <span style="font-weight: 600; color: var(--hs-error, #ef4444); font-size: 0.85rem; margin-right: 0.25rem;">Cons:</span>
                        ${review.cons.map(con => `
                            <span class="hs-review-tag hs-review-tag--con" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                ${con}
                            </span>
                        `).join('')}
                    </div>
                ` : ''}
            </div>`;
        }

        return `
            <header class="hs-review-card__header">
                <div class="hs-review-card__stars">${starsHtml}</div>
                <div class="hs-review-card__meta">
                    <span class="hs-review-card__author">${review.author}</span>
                    ${review.country_code ? `
                        <span class="hs-review-card__flag" title="${review.country_code}" style="margin-left: 0.35rem; font-size: 1.1rem; vertical-align: middle; line-height: 1;">
                            ${review.country_flag || ''}
                        </span>
                    ` : ''}
                    <span class="hs-review-card__sep">·</span>
                    <span class="hs-review-card__date">${review.date}</span>
                </div>
            </header>
            <div class="hs-review-card__content">${review.content}</div>
            ${prosConsHtml}
            <footer class="hs-review-card__footer" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--hs-border);">
                <div class="hs-review-card__voting js-review-voting" data-review-id="${review.id}">
                    <span class="hs-muted" style="font-size: 0.875rem; margin-right: 0.5rem;">Was this helpful?</span>
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-vote-btn" data-vote="helpful">
                        Yes <span class="js-vote-count-helpful">(${review.helpful_votes || 0})</span>
                    </button>
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-vote-btn" data-vote="unhelpful">
                        No <span class="js-vote-count-unhelpful">(${review.unhelpful_votes || 0})</span>
                    </button>
                </div>
            </footer>
        `;
    }
});
