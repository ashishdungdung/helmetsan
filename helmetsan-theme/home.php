<?php
/**
 * Blog index template (Guides & Articles).
 *
 * @package HelmetsanTheme
 */

get_header();
?>

<main id="main-content" class="site-main hs-container" style="max-width: 1200px; margin: 0 auto; padding: 2.5rem 1rem;">
    <header class="hs-section__head" style="margin-bottom: 2.5rem; text-align: center;">
        <p class="hs-eyebrow" style="text-transform: uppercase; font-size: 0.8125rem; font-weight: 700; color: var(--hs-primary, #e30613); letter-spacing: 0.08em; margin-bottom: 0.5rem;">
            Helmetsan Intelligence & Guides
        </p>
        <h1 style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; color: var(--hs-heading, #111); margin: 0 0 1rem 0;">
            Motorcycle Helmet Guides & Safety Insights
        </h1>
        <p style="font-size: 1.125rem; color: var(--hs-text-secondary, #4b5563); max-width: 700px; margin: 0 auto; line-height: 1.6;">
            Authoritative, rider-focused guides covering safety homologations, cranial shape fitment, visor optics, and maintenance.
        </p>
    </header>

    <?php if (have_posts()) : ?>
        <div class="hs-guides-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.75rem;">
            <?php
            while (have_posts()) :
                the_post();
                $categories = get_the_category();
                $primaryCat = ! empty($categories) ? $categories[0]->name : 'Guide';
                $wordCount = str_word_count(strip_tags(get_the_content()));
                $readingTime = max(1, ceil($wordCount / 200));
                ?>
                <article class="hs-guide-card hs-panel" style="background: var(--hs-surface, #fff); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.875rem;">
                            <span class="hs-badge" style="background: rgba(227, 6, 19, 0.08); color: var(--hs-primary, #e30613); padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <?php echo esc_html($primaryCat); ?>
                            </span>
                            <span style="font-size: 0.8125rem; color: var(--hs-muted, #666);">
                                <?php echo esc_html($readingTime); ?> min read
                            </span>
                        </div>

                        <h2 style="font-size: 1.25rem; font-weight: 700; line-height: 1.35; margin: 0 0 0.75rem 0;">
                            <a href="<?php the_permalink(); ?>" style="color: var(--hs-heading, #111); text-decoration: none;">
                                <?php the_title(); ?>
                            </a>
                        </h2>

                        <p style="font-size: 0.9375rem; color: var(--hs-text-secondary, #4b5563); line-height: 1.55; margin: 0 0 1.25rem 0;">
                            <?php echo esc_html(get_the_excerpt()); ?>
                        </p>
                    </div>

                    <div>
                        <a href="<?php the_permalink(); ?>" class="hs-link" style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; font-size: 0.875rem; color: var(--hs-primary, #e30613); text-decoration: none;">
                            Read Guide <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <div style="margin-top: 3rem; text-align: center;">
            <?php the_posts_pagination(); ?>
        </div>

    <?php else : ?>
        <div class="hs-panel" style="text-align: center; padding: 3rem;">
            <p>No guides published yet.</p>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
