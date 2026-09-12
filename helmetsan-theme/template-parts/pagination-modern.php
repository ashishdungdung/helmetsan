<?php
/**
 * Modern Pagination Controls
 */
$paged = $args['paged'] ?? (get_query_var('paged') ?: 1);
$total = $args['total'] ?? 1;

if ($total <= 1) return;

$prev_url = $args['prev_url'] ?? get_pagenum_link($paged - 1);
$next_url = $args['next_url'] ?? get_pagenum_link($paged + 1);
$first_url = $args['first_url'] ?? get_pagenum_link(1);
?>
<div class="hs-pagination-modern">
    <div class="hs-pagination-modern__side hs-pagination-modern__side--left">
        <?php if ($paged > 1) : ?>
            <a href="<?php echo esc_url($first_url); ?>" class="hs-pag-jump">
                <span class="hs-pag-icon">«</span>
                <span class="hs-pag-label">Page 1</span>
            </a>
        <?php endif; ?>
        
        <?php if ($paged > 1) : ?>
            <a href="<?php echo esc_url($prev_url); ?>" class="hs-pag-btn">
                <span class="hs-pag-icon">‹</span>
                <span class="hs-pag-text">Previous</span>
            </a>
        <?php else : ?>
            <span class="hs-pag-btn is-disabled">
                <span class="hs-pag-icon">‹</span>
                <span class="hs-pag-text">Previous</span>
            </span>
        <?php endif; ?>
    </div>

    <div class="hs-pagination-modern__center">
        <span class="hs-pag-status">Page <?php echo number_format($paged); ?> of <?php echo number_format($total); ?></span>
        <?php if (isset($args['count_text'])) : ?>
            <span class="hs-pag-count"><?php echo esc_html($args['count_text']); ?></span>
        <?php endif; ?>
    </div>

    <div class="hs-pagination-modern__side hs-pagination-modern__side--right">
        <?php if ($paged < $total) : ?>
            <a href="<?php echo esc_url($next_url); ?>" class="hs-pag-btn hs-pag-btn--next">
                <span class="hs-pag-text">Next</span>
                <span class="hs-pag-icon">›</span>
            </a>
        <?php else : ?>
            <span class="hs-pag-btn hs-pag-btn--next is-disabled">
                <span class="hs-pag-text">Next</span>
                <span class="hs-pag-icon">›</span>
            </span>
        <?php endif; ?>
    </div>
</div>
