<?php
/**
 * Helmet card.
 *
 * @package HelmetsanTheme
 */
$helmetId = get_the_ID();
$price = helmetsan_get_helmet_price($helmetId);
$certs = helmetsan_get_certifications($helmetId);
$logoUrl = helmetsan_get_logo_url($helmetId);
?>
<article <?php post_class('helmet-card hs-panel'); ?>>
    <?php if ($logoUrl !== '') : ?>
        <div class="entity-card__logo-wrap">
            <img class="entity-card__logo" src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
        </div>
    <?php endif; ?>
    <button type="button" 
            class="js-add-to-compare hs-btn hs-btn--icon helmet-card__compare-btn" 
            data-id="<?php echo esc_attr((string) $helmetId); ?>" 
            title="<?php esc_attr_e('Compare', 'helmetsan-theme'); ?>"
            aria-pressed="false"
            aria-label="Add to comparison">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hs-icon">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
    </button>
    <a href="/comparison/" 
       class="js-view-compare hs-btn hs-btn--sm hs-btn--primary is-hidden helmet-card__view-compare"
       title="<?php esc_attr_e('View Comparison', 'helmetsan-theme'); ?>">
       View
    </a>

    <a href="<?php the_permalink(); ?>" class="helmet-card__link">
        <?php if (has_post_thumbnail()) : ?>
            <div class="helmet-card__image-wrapper">
                <div class="helmet-card__image"><?php the_post_thumbnail('medium_large'); ?></div>
            </div>
        <?php else : ?>
            <div class="helmet-card__image-wrapper helmet-card__image-wrapper--placeholder" style="background: var(--hs-bg-alt); display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="var(--hs-border)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="hs-icon hs-icon--placeholder" style="color: var(--hs-border);">
                    <path d="M12 2a9 9 0 0 0-9 9v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a9 9 0 0 0-9-9z"></path>
                    <path d="M6 12h12"></path>
                    <path d="M12 12v8"></path>
                    <path d="M8 12v4"></path>
                    <path d="M16 12v4"></path>
                </svg>
            </div>
        <?php endif; ?>
        <h3 class="helmet-card__title">
            <span class="helmet-card__title-text"><?php the_title(); ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hs-icon helmet-card__title-icon"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </h3>
    </a>
    
    <?php 
    $children = get_posts([
        'post_parent'    => $helmetId,
        'post_type'      => 'helmet',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $count = is_array($children) ? count($children) : 0;
    ?>

    <div class="helmet-card__meta">
        <span><?php echo esc_html($price); ?></span>
        <?php if ($count > 0) : ?>
            <span class="hs-badge hs-badge--variants"><?php echo esc_html($count); ?> Colors</span>
        <?php endif; ?>
        <span><?php echo esc_html($certs); ?></span>
    </div>
</article>
