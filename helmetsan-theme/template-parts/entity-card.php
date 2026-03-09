<?php
/**
 * Generic entity card partial.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$postId = get_the_ID();
$type   = get_post_type($postId);
$logoUrl = helmetsan_get_logo_url($postId);
$metaA  = '';
$metaB  = '';
$catTerms = null;

if ($type === 'brand') {
    $metaA = (string) get_post_meta($postId, 'brand_origin_country', true);
    $metaB = (string) get_post_meta($postId, 'brand_helmet_types', true);
} elseif ($type === 'accessory') {
    $catTerms = get_the_terms($postId, 'accessory_category');
    if (is_array($catTerms) && ! is_wp_error($catTerms) && $catTerms !== []) {
        $catTerms = array_values($catTerms);
        $metaA = implode(', ', array_map(static function ($t) {
            return $t->name;
        }, array_slice($catTerms, 0, 2)));
    }
    if ($metaA === '') {
        $metaA = (string) get_post_meta($postId, 'accessory_parent_category', true);
        if ($metaA === '') {
            $metaA = (string) get_post_meta($postId, 'accessory_type', true);
        }
    }
    $metaB = (string) get_post_meta($postId, 'accessory_subcategory', true);
    if ($metaB === '') {
        $metaB = (string) get_post_meta($postId, 'accessory_youth_adult', true);
    }
    // Don't show placeholder/generic values that aren't real categories
    $placeholderMeta = ['variant', 'n/a', '—', '-', 'unknown'];
    $metaA = trim($metaA);
    $metaB = trim($metaB);
    if ($metaA !== '' && in_array(strtolower($metaA), $placeholderMeta, true)) {
        $metaA = '';
    }
    if ($metaB !== '' && in_array(strtolower($metaB), $placeholderMeta, true)) {
        $metaB = '';
    }
} elseif ($type === 'motorcycle') {
    $metaA = (string) get_post_meta($postId, 'motorcycle_make', true);
    $metaB = (string) get_post_meta($postId, 'bike_segment', true);
} elseif ($type === 'safety_standard') {
    $metaA = (string) get_post_meta($postId, 'official_reference_url', true);
    $metaB = (string) get_post_meta($postId, 'mandatory_markets_json', true);
}
?>
<article <?php post_class('hs-panel entity-card'); ?>>
    <?php if ($logoUrl !== '') : ?>
        <div class="entity-card__logo-wrap">
            <img class="entity-card__logo" src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" />
        </div>
    <?php endif; ?>
    <h3 class="entity-card__title"><a class="hs-link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    <?php if (has_excerpt()) : ?>
        <p class="entity-card__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
    <?php endif; ?>
    <?php
    if ($type === 'accessory' && $catTerms !== null && is_array($catTerms) && $catTerms !== []) {
        $seen = [];
        $deduped = [];
        foreach ($catTerms as $t) {
            $name = $t instanceof \WP_Term ? $t->name : '';
            if ($name !== '' && ! isset($seen[ $name ])) {
                $seen[ $name ] = true;
                $deduped[] = $t;
            }
        }
        $catTerms = array_slice($deduped, 0, 3);
    }
    ?>
    <?php if ($type === 'accessory' && $catTerms !== null && $catTerms !== []) : ?>
        <div class="entity-card__categories">
            <?php foreach ($catTerms as $t) :
                $catUrl = get_term_link($t);
                if (is_wp_error($catUrl)) {
                    $catUrl = '#';
                }
                ?>
                <a class="entity-card__category-tag" href="<?php echo esc_url($catUrl); ?>"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    $showMeta = true;
    if ($type === 'accessory' && is_array($catTerms) && $catTerms !== []) {
        $showMeta = false;
    }
    ?>
    <?php if ($showMeta && ($metaA !== '' || $metaB !== '')) : ?>
    <div class="entity-card__meta">
        <?php if ($metaA !== '') : ?>
            <code><?php echo esc_html(wp_trim_words($metaA, 12, '...')); ?></code>
        <?php endif; ?>
        <?php if ($metaB !== '' && $metaB !== $metaA) : ?>
            <code><?php echo esc_html(wp_trim_words($metaB, 12, '...')); ?></code>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <p class="entity-card__action">
        <a class="hs-btn hs-btn--sm hs-btn--primary entity-card__cta" href="<?php the_permalink(); ?>">View</a>
    </p>
</article>
