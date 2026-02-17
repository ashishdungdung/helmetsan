<?php
/**
 * Legal hub page template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();

        $children = get_pages([
            'child_of' => get_the_ID(),
            'parent' => get_the_ID(),
            'sort_column' => 'menu_order,post_title',
            'post_status' => 'publish',
        ]);
        ?>
        <article <?php post_class('hs-section'); ?>>
            <header class="hs-section__head">
                <p class="hs-eyebrow">Policy Center</p>
                <h1><?php the_title(); ?></h1>
            </header>
            <div class="hs-panel page-content"><?php the_content(); ?></div>

            <?php if (is_array($children) && $children !== []) : ?>
                <section class="hs-panel">
                    <h2>Legal Documents</h2>
                    <div class="helmet-grid">
                        <?php foreach ($children as $page) : ?>
                            <article class="hs-panel entity-card">
                                <h3 class="entity-card__title"><a class="hs-link" href="<?php echo esc_url(get_permalink($page->ID)); ?>"><?php echo esc_html($page->post_title); ?></a></h3>
                                <p class="entity-card__excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $page->post_content), 22, '...')); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
        <?php
    }
}

get_footer();

