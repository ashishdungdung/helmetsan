<?php
/**
 * Single post template for editorial articles and guides.
 *
 * @package HelmetsanTheme
 */

get_header();

while (have_posts()) {
    the_post();
    $categories = get_the_category();
    $primaryCat = ! empty($categories) ? $categories[0]->name : 'Guides';
    $wordCount = str_word_count(strip_tags(get_the_content()));
    $readingTime = max(1, ceil($wordCount / 200));
    ?>
    <main id="main-content" class="site-main hs-container" style="max-width: 900px; margin: 0 auto; padding: 2rem 1rem;">
        <nav class="hs-breadcrumb" aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
            <ol class="hs-breadcrumb__list" style="display: flex; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.875rem; color: var(--hs-muted, #666);">
                <li class="hs-breadcrumb__item"><a href="<?php echo esc_url(home_url('/')); ?>" style="color: inherit; text-decoration: none;">Home</a></li>
                <li class="hs-breadcrumb__sep">/</li>
                <li class="hs-breadcrumb__item"><a href="<?php echo esc_url(home_url('/blog/')); ?>" style="color: inherit; text-decoration: none;">Guides & Articles</a></li>
                <li class="hs-breadcrumb__sep">/</li>
                <li class="hs-breadcrumb__item" aria-current="page" style="color: var(--hs-text, #111); font-weight: 500;"><?php the_title(); ?></li>
            </ol>
        </nav>

        <article <?php post_class('hs-article hs-editorial'); ?>>
            <header class="hs-article__header" style="margin-bottom: 2rem;">
                <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
                    <span class="hs-badge" style="background: rgba(227, 6, 19, 0.1); color: var(--hs-primary, #e30613); padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; text-transform: uppercase;">
                        <?php echo esc_html($primaryCat); ?>
                    </span>
                    <span style="font-size: 0.875rem; color: var(--hs-muted, #666);">
                        <?php echo esc_html($readingTime); ?> min read · Updated <?php echo esc_html(get_the_modified_date('F Y')); ?>
                    </span>
                </div>

                <h1 style="font-size: clamp(1.75rem, 3.5vw, 2.5rem); line-height: 1.2; font-weight: 800; color: var(--hs-heading, #111); margin: 0 0 1rem 0;">
                    <?php the_title(); ?>
                </h1>

                <?php if (has_excerpt()) : ?>
                    <p style="font-size: 1.125rem; line-height: 1.6; color: var(--hs-text-secondary, #4b5563); margin: 0; font-weight: 400;">
                        <?php echo esc_html(get_the_excerpt()); ?>
                    </p>
                <?php endif; ?>
            </header>

            <div class="hs-article__content hs-panel" style="background: var(--hs-surface, #fff); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: clamp(1.5rem, 3vw, 2.5rem); font-size: 1.0625rem; line-height: 1.75; color: var(--hs-text, #1f2937);">
                <style>
                    .hs-article__content h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem; color: var(--hs-heading, #111); border-bottom: 1px solid var(--hs-border, #e5e7eb); padding-bottom: 0.5rem; }
                    .hs-article__content h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem; color: var(--hs-heading, #111); }
                    .hs-article__content p { margin-bottom: 1.25rem; }
                    .hs-article__content ul, .hs-article__content ol { margin-bottom: 1.25rem; padding-left: 1.5rem; }
                    .hs-article__content li { margin-bottom: 0.5rem; }
                    .hs-article__content strong { color: var(--hs-heading, #111); }
                    .hs-article__content table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; font-size: 0.9375rem; }
                    .hs-article__content th, .hs-article__content td { padding: 0.75rem 1rem; border: 1px solid var(--hs-border, #e5e7eb); text-align: left; }
                    .hs-article__content th { background: var(--hs-bg-alt, #f9fafb); font-weight: 600; }
                </style>
                <?php the_content(); ?>
            </div>

            <!-- Author & Editorial Trust Box -->
            <footer class="hs-article__footer" style="margin-top: 2.5rem; padding: 1.5rem; background: var(--hs-bg-alt, #f9fafb); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; display: flex; gap: 1rem; align-items: center;">
                <div style="flex-shrink: 0; width: 48px; height: 48px; border-radius: 50%; background: var(--hs-primary, #e30613); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem;">
                    HS
                </div>
                <div>
                    <h3 style="margin: 0 0 0.25rem 0; font-size: 1rem; font-weight: 700; color: var(--hs-heading, #111);">Helmetsan Technical Editorial Board</h3>
                    <p style="margin: 0; font-size: 0.875rem; color: var(--hs-muted, #666); line-height: 1.4;">
                        Our guides are written and reviewed by experienced motorcycle riders and safety gear analysts adhering to strict <a href="<?php echo esc_url(home_url('/legal/compliance-and-safety/')); ?>" style="color: var(--hs-primary, #e30613); text-decoration: underline;">editorial and testing standards</a>.
                    </p>
                </div>
            </footer>
        </article>
    </main>
    <?php
}

get_footer();
