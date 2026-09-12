<?php
/**
 * Template part for displaying helmet reviews.
 *
 * @package HelmetsanTheme
 */

$helmetId = $args['helmet_id'] ?? get_the_ID();
$averageRating = helmetsan_get_average_rating($helmetId);
$distribution = helmetsan_get_rating_distribution($helmetId);
$totalReviews = array_sum($distribution);

$reviewService = function_exists('helmetsan_core') ? helmetsan_core()->reviews() : null;
$comments = $reviewService ? $reviewService->getReviews($helmetId, 10) : [];

global $wpdb;
$tableReviews = $wpdb->prefix . 'helmetsan_reviews';
$totalCommentsCount = $reviewService ? (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tableReviews} WHERE post_id = %d AND status = 'approved'",
    $helmetId
)) : 0;
$maxPages = ceil($totalCommentsCount / 10);

$secCfg = function_exists('helmetsan_core') ? helmetsan_core()->config()->securityConfig() : [];
$turnstileEnabled = !empty($secCfg['enable_turnstile']);
$turnstileKey = $secCfg['turnstile_site_key'] ?? '';

// Calculate country statistics dynamically grouped by country code
$countryStats = [];
if ($totalReviews > 0) {
    global $wpdb;
    $tableReviews = $wpdb->prefix . 'helmetsan_reviews';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT country_code, COUNT(*) as count FROM {$tableReviews} WHERE post_id = %d AND status = 'approved' AND country_code != '' GROUP BY country_code ORDER BY count DESC",
        $helmetId
    ));
    foreach ($results as $row) {
        $countryStats[$row->country_code] = (int) $row->count;
    }
}
?>

<section class="hs-panel hs-reviews" id="reviews">
    <div class="hs-reviews__header">
        <h2 class="hs-section-icon-title">
            <span class="hs-section-icon-title__icon" aria-hidden="true">
                <?php echo helmetsan_get_icon('star'); ?>
            </span>
            <?php esc_html_e('User Reviews', 'helmetsan-theme'); ?>
        </h2>
        <button type="button" class="hs-btn hs-btn--sm hs-btn--primary js-toggle-review-form">
            <?php esc_html_e('Write a review', 'helmetsan-theme'); ?>
        </button>
    </div>

    <div class="hs-reviews__layout">
        <div class="hs-reviews__summary">
            <?php if ($totalReviews > 0) : ?>
            <div class="hs-reviews__score-card hs-panel">
                <div class="hs-reviews__average-score"><?php echo number_format($averageRating, 1); ?></div>
                <div class="hs-reviews__stars">
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <span class="hs-star <?php echo $i <= round($averageRating) ? 'is-active' : ''; ?>">
                            <?php echo helmetsan_get_icon('star'); ?>
                        </span>
                    <?php endfor; ?>
                </div>
                <p class="hs-reviews__count"><?php printf(esc_html__('Based on %d verified reviews', 'helmetsan-theme'), $totalReviews); ?></p>
            </div>
            <?php else : ?>
            <div class="hs-reviews__score-card hs-panel hs-reviews__score-card--empty">
                <div class="hs-reviews__empty-title"><?php esc_html_e('NO VERIFIED RIDER REVIEWS YET', 'helmetsan-theme'); ?></div>
                <p class="hs-reviews__empty-sub"><?php esc_html_e('Be the first to share your riding experience with this helmet.', 'helmetsan-theme'); ?></p>
            </div>
            <?php endif; ?>

            <div class="hs-reviews__distribution hs-panel">
                <?php foreach (range(5, 1) as $stars) : 
                    $count = $distribution[$stars];
                    $percentage = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0;
                ?>
                    <div class="hs-reviews__dist-row">
                        <span class="hs-reviews__dist-label"><?php printf(esc_html__('%d stars', 'helmetsan-theme'), $stars); ?></span>
                        <div class="hs-reviews__dist-bar">
                            <div class="hs-reviews__dist-fill" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                        </div>
                        <span class="hs-reviews__dist-count"><?php echo esc_html($count); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($countryStats)) : ?>
                <div class="hs-reviews__regions hs-panel" style="margin-top: 1rem; padding: 1.25rem; border-radius: var(--hs-radius, 8px); background: var(--hs-panel-bg, rgba(255,255,255,0.03)); border: 1px solid var(--hs-border, rgba(255,255,255,0.08));">
                    <h4 class="hs-reviews__regions-title" style="margin-top: 0; margin-bottom: 0.85rem; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; color: var(--hs-text, #fff);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hs-icon" style="color: var(--hs-accent, #ff385c);"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <?php esc_html_e('Reviews by Region', 'helmetsan-theme'); ?>
                    </h4>
                    <div class="hs-reviews__regions-list" style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <?php foreach ($countryStats as $code => $count) : 
                            $percentage = ($count / $totalReviews) * 100;
                        ?>
                            <div class="hs-reviews__region-row" style="display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 50px;">
                                    <span style="font-size: 1.1rem; line-height: 1;"><?php echo helmetsan_get_country_flag($code); ?></span>
                                    <span style="font-weight: 600; color: var(--hs-text, #fff);"><?php echo esc_html($code); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-grow: 1; margin: 0 0.75rem;">
                                    <div style="flex-grow: 1; height: 5px; background: var(--hs-border, rgba(255,255,255,0.08)); border-radius: 3px; overflow: hidden;">
                                        <div style="width: <?php echo esc_attr($percentage); ?>%; height: 100%; background: var(--hs-accent, #ff385c); border-radius: 3px;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 500; color: var(--hs-muted, #888); min-width: 30px; text-align: right;"><?php printf('%d%%', round($percentage)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="hs-reviews__content">
            <!-- Review Form (Hidden by default) -->
            <div class="hs-reviews__form-wrap hs-panel is-hidden" id="review-form-container">
                <div class="hs-reviews__form-header">
                    <h3><?php esc_html_e('Share your experience', 'helmetsan-theme'); ?></h3>
                    <button type="button" class="hs-btn hs-btn--icon js-toggle-review-form" aria-label="Close form">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                
                <form id="hs-review-form" class="hs-form" data-product-id="<?php echo esc_attr($helmetId); ?>">
                    <div class="hs-form__row">
                        <label><?php esc_html_e('How many stars?', 'helmetsan-theme'); ?></label>
                        <div class="hs-rating-input js-rating-input">
                            <?php foreach (range(1, 5) as $i) : ?>
                                <button type="button" data-value="<?php echo $i; ?>" class="hs-rating-star">
                                    <?php echo helmetsan_get_icon('star'); ?>
                                </button>
                            <?php endforeach; ?>
                            <input type="hidden" name="rating" value="0" required>
                        </div>
                    </div>
                    
                    <div class="hs-form__grid">
                        <div class="hs-form__field">
                            <label for="review_name"><?php esc_html_e('Display Name', 'helmetsan-theme'); ?></label>
                            <input type="text" id="review_name" name="name" required placeholder="e.g. MotoRider99">
                        </div>
                        <div class="hs-form__field">
                            <label for="review_email"><?php esc_html_e('Email (private)', 'helmetsan-theme'); ?></label>
                            <input type="email" id="review_email" name="email" required placeholder="your@email.com">
                        </div>
                    </div>
                    
                    <div class="hs-form__field">
                        <label for="review_content"><?php esc_html_e('Review Details', 'helmetsan-theme'); ?></label>
                        <textarea id="review_content" name="content" rows="4" required placeholder="Tell us about the fit, comfort, and performance..."></textarea>
                    </div>
                    
                    <div class="hs-form__grid">
                        <div class="hs-form__field">
                            <label for="review_pros"><?php esc_html_e('Pros (comma separated)', 'helmetsan-theme'); ?></label>
                            <input type="text" id="review_pros" name="pros" placeholder="Quiet, Light, Great FOV">
                        </div>
                        <div class="hs-form__field">
                            <label for="review_cons"><?php esc_html_e('Cons (comma separated)', 'helmetsan-theme'); ?></label>
                            <input type="text" id="review_cons" name="cons" placeholder="Expensive, Small vents">
                        </div>
                    </div>

                    <?php if ($turnstileEnabled && $turnstileKey) : ?>
                        <div class="hs-form__row">
                            <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($turnstileKey); ?>" data-theme="auto"></div>
                        </div>
                    <?php endif; ?>

                    <div class="hs-form__actions">
                        <button type="submit" class="hs-btn hs-btn--primary"><?php esc_html_e('Submit for Review', 'helmetsan-theme'); ?></button>
                    </div>
                    
                    <div class="hs-form__message js-form-message"></div>
                </form>
            </div>

            <div class="hs-reviews__toolbar" style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <label for="review-sort" class="hs-sr-only"><?php esc_html_e('Sort Reviews', 'helmetsan-theme'); ?></label>
                <select id="review-sort" class="hs-input js-review-sort" style="width: auto; padding: 0.5rem; border-radius: var(--hs-radius); border: 1px solid var(--hs-border); background: var(--hs-panel); color: var(--hs-text);">
                    <option value="newest"><?php esc_html_e('Newest First', 'helmetsan-theme'); ?></option>
                    <option value="helpful"><?php esc_html_e('Most Helpful', 'helmetsan-theme'); ?></option>
                </select>
            </div>

            <div class="hs-reviews__list js-reviews-list">
                <?php if (!empty($comments)) : ?>
                    <?php foreach ($comments as $comment) : 
                        $rating = (int) $comment['rating'];
                        $prosArr = $comment['pros'] ?: [];
                        $consArr = $comment['cons'] ?: [];
                        $author = $comment['author_name'] ?: 'Anonymous';
                        $commentId = $comment['id'];
                    ?>
                        <article class="hs-review-card hs-panel">
                            <header class="hs-review-card__header">
                                <div class="hs-review-card__stars">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <span class="hs-star <?php echo $i <= $rating ? 'is-active' : ''; ?>">
                                            <?php echo helmetsan_get_icon('star'); ?>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                                <div class="hs-review-card__meta">
                                    <span class="hs-review-card__author"><?php echo esc_html($author); ?></span>
                                    <?php if (!empty($comment['country_code'])) : ?>
                                        <span class="hs-review-card__flag" title="<?php echo esc_attr($comment['country_code']); ?>" style="margin-left: 0.35rem; font-size: 1.1rem; vertical-align: middle; line-height: 1;">
                                            <?php echo helmetsan_get_country_flag($comment['country_code']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="hs-review-card__sep">·</span>
                                    <span class="hs-review-card__date"><?php echo date_i18n(get_option('date_format'), strtotime($comment['created_at'])); ?></span>
                                </div>
                            </header>
                            
                            <div class="hs-review-card__content">
                                <?php echo apply_filters('the_content', $comment['content']); ?>
                            </div>
                            
                             <?php if (!empty($prosArr) || !empty($consArr)) : ?>
                                <div class="hs-review-card__pros-cons">
                                    <?php if (!empty($prosArr)) : ?>
                                        <div class="hs-review-card__pros" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                                            <span style="font-weight: 600; color: var(--hs-success, #22c55e); font-size: 0.85rem; margin-right: 0.25rem;"><?php esc_html_e('Pros:', 'helmetsan-theme'); ?></span>
                                            <?php foreach ($prosArr as $pro) : ?>
                                                <span class="hs-review-tag hs-review-tag--pro" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); color: #4ade80;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <?php echo esc_html($pro); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($consArr)) : ?>
                                        <div class="hs-review-card__cons" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                                            <span style="font-weight: 600; color: var(--hs-error, #ef4444); font-size: 0.85rem; margin-right: 0.25rem;"><?php esc_html_e('Cons:', 'helmetsan-theme'); ?></span>
                                            <?php foreach ($consArr as $con) : ?>
                                                <span class="hs-review-tag hs-review-tag--con" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    <?php echo esc_html($con); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <footer class="hs-review-card__footer" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--hs-border);">
                                <div class="hs-review-card__voting js-review-voting" data-review-id="<?php echo esc_attr($commentId); ?>">
                                    <span class="hs-muted" style="font-size: 0.875rem; margin-right: 0.5rem;"><?php esc_html_e('Was this helpful?', 'helmetsan-theme'); ?></span>
                                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-vote-btn" data-vote="helpful">
                                        <?php esc_html_e('Yes', 'helmetsan-theme'); ?> <span class="js-vote-count-helpful">(<?php echo (int) $comment['helpful_votes']; ?>)</span>
                                    </button>
                                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost js-vote-btn" data-vote="unhelpful">
                                        <?php esc_html_e('No', 'helmetsan-theme'); ?> <span class="js-vote-count-unhelpful">(<?php echo (int) $comment['unhelpful_votes']; ?>)</span>
                                    </button>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="hs-reviews__empty">
                        <div class="hs-reviews__empty-icon"><?php echo helmetsan_get_icon('star'); ?></div>
                        <p><?php esc_html_e('No reviews yet. Be the first to share your experience with this helmet!', 'helmetsan-theme'); ?></p>
                        <button type="button" class="hs-btn hs-btn--sm js-toggle-review-form"><?php esc_html_e('Write a review', 'helmetsan-theme'); ?></button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($maxPages > 1) : ?>
                <div class="hs-reviews__pagination">
                    <button type="button" class="hs-btn hs-btn--ghost js-load-more-reviews" data-page="1" data-total-pages="<?php echo esc_attr($maxPages); ?>">
                        <?php esc_html_e('Load More Reviews', 'helmetsan-theme'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
