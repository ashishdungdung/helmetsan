<?php
/**
 * Template Name: Blog / Intelligence Hub
 *
 * @package HelmetsanTheme
 */

get_header();

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$guidesQuery = new WP_Query([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 24,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);
?>

<main id="main-content" class="site-main hs-container" style="max-width: 1200px; margin: 0 auto; padding: 2.5rem 1rem;">
    <header class="hs-section__head" style="margin-bottom: 2.5rem; text-align: center;">
        <p class="hs-eyebrow" style="text-transform: uppercase; font-size: 0.8125rem; font-weight: 700; color: var(--hs-accent); letter-spacing: 0.08em; margin-bottom: 0.5rem;">
            <?php esc_html_e('Helmetsan Intelligence & Guides', 'helmetsan-theme'); ?>
        </p>
        <h1 style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; color: var(--hs-text-primary); margin: 0 0 1rem 0;">
            <?php esc_html_e('Motorcycle Helmet Guides & Safety Insights', 'helmetsan-theme'); ?>
        </h1>
        <p style="font-size: 1.125rem; color: var(--hs-text-dim); max-width: 700px; margin: 0 auto; line-height: 1.6;">
            <?php esc_html_e('Authoritative, rider-focused guides covering safety homologations, cranial shape fitment, visor optics, and maintenance.', 'helmetsan-theme'); ?>
        </p>
    </header>

    <?php if ($guidesQuery->have_posts()) : ?>
        <div class="hs-guides-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.75rem;">
            <?php
            while ($guidesQuery->have_posts()) :
                $guidesQuery->the_post();
                $categories = get_the_category();
                $primaryCat = ! empty($categories) ? $categories[0]->name : 'Engineering Guide';
                $content = get_the_content();
                $wordCount = str_word_count(strip_tags($content));
                $readingTime = max(1, (int) ceil($wordCount / 200));
                ?>
                <article class="hs-guide-card hs-panel" style="background: var(--hs-panel); border: 1px solid var(--hs-border); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.875rem;">
                            <span class="hs-badge" style="background: var(--hs-accent-soft); color: var(--hs-accent); padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <?php echo esc_html($primaryCat); ?>
                            </span>
                            <span style="font-size: 0.8125rem; color: var(--hs-muted);">
                                <?php echo esc_html($readingTime); ?> min read (<?php echo esc_html(number_format_i18n($wordCount)); ?> words)
                            </span>
                        </div>

                        <h2 style="font-size: 1.25rem; font-weight: 700; line-height: 1.35; margin: 0 0 0.75rem 0;">
                            <a href="<?php the_permalink(); ?>" style="color: var(--hs-text-primary); text-decoration: none;">
                                <?php the_title(); ?>
                            </a>
                        </h2>

                        <p style="font-size: 0.9375rem; color: var(--hs-text-dim); line-height: 1.55; margin: 0 0 1.25rem 0;">
                            <?php echo esc_html(wp_trim_words(get_the_excerpt(), 25)); ?>
                        </p>
                    </div>

                    <div style="margin-top: 1rem;">
                        <a href="<?php the_permalink(); ?>" class="hs-link" style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; font-size: 0.875rem; color: var(--hs-accent); text-decoration: none;">
                            <?php esc_html_e('Read Full Guide', 'helmetsan-theme'); ?> <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <?php if ($guidesQuery->max_num_pages > 1) : ?>
            <div style="margin-top: 3rem; text-align: center;">
                <?php
                echo paginate_links([
                    'total'   => $guidesQuery->max_num_pages,
                    'current' => $paged,
                ]);
                ?>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <div class="hs-panel" style="text-align: center; padding: 3rem; background: var(--hs-panel); border: 1px solid var(--hs-border); border-radius: 12px;">
            <p style="color: var(--hs-text-dim);"><?php esc_html_e('No guides published yet.', 'helmetsan-theme'); ?></p>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
