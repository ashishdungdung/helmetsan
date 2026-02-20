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
            <header class="hs-section__head" style="background: var(--hs-bg-alt); padding: 4rem 2rem; border-radius: var(--hs-border-radius); text-align: center; margin-bottom: 3rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--hs-text-muted); margin-bottom: 1rem;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <p class="hs-eyebrow">Trust & Privacy Center</p>
                <h1 style="font-size: 3rem; margin-top: 0.5rem;"><?php the_title(); ?></h1>
                <p style="color: var(--hs-text-muted); max-width: 600px; margin: 1rem auto 0 auto;">Transparency is central to everything we do. Review our core policies and terms detailing how we protect your data and operate Helmetsan.</p>
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

