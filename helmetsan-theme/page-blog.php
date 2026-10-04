<?php
/**
 * Template Name: Blog & Engineering Guides Archive
 *
 * Displays the 15 comprehensive E-E-A-T cornerstone guides and masterclasses.
 *
 * @package HelmetsanTheme
 */

declare(strict_types=1);

get_header();
?>

<main id="main-content" class="hs-container" style="padding-top: 2rem; padding-bottom: 4rem;">
    <!-- Breadcrumb -->
    <nav class="hs-breadcrumb" aria-label="<?php esc_attr_e(
        "Breadcrumbs",
        "helmetsan-theme",
    ); ?>" style="margin-bottom: 1.5rem;">
        <ol style="display: flex; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.875rem; color: var(--hs-muted);">
            <li><a href="<?php echo esc_url(
                home_url("/"),
            ); ?>" style="color: var(--hs-muted); text-decoration: none;"><?php esc_html_e(
    "Home",
    "helmetsan-theme",
); ?></a></li>
            <li><span aria-hidden="true">&rsaquo;</span></li>
            <li aria-current="page" style="color: var(--hs-text-primary); font-weight: 600;"><?php esc_html_e(
                "Editorial Guides & Engineering Analyses",
                "helmetsan-theme",
            ); ?></li>
        </ol>
    </nav>

    <!-- Header Banner -->
    <header class="hs-guides-header" style="margin-bottom: 2.5rem; border-bottom: 1px solid var(--hs-border); padding-bottom: 2rem;">
        <span class="hs-badge" style="background: var(--hs-accent-soft); color: var(--hs-accent); padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 0.75rem;">
            <?php esc_html_e(
                "Technical Masterclasses & Research",
                "helmetsan-theme",
            ); ?>
        </span>
        <h1 style="font-size: 2.25rem; font-weight: 800; line-height: 1.2; margin: 0 0 0.75rem 0;">
            <?php esc_html_e(
                "Motorcycle Helmet Engineering, Safety & Fitment Guides",
                "helmetsan-theme",
            ); ?>
        </h1>
        <p style="font-size: 1.125rem; color: var(--hs-text-dim); max-width: 800px; margin: 0; line-height: 1.6;">
            <?php esc_html_e(
                "Deep technical evaluations, biomechanical safety standards analysis, acoustic benchmarking, and zygomatic fitment protocols authored by our engineering research team.",
                "helmetsan-theme",
            ); ?>
        </p>
    </header>

    <!-- Guides Grid -->
    <?php
    $guidesQuery = new WP_Query([
        "post_type" => "post",
        "post_status" => "publish",
        "posts_per_page" => 50,
        "orderby" => "date",
        "order" => "DESC",
    ]);

    if ($guidesQuery->have_posts()): ?>
        <div class="hs-guides-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.75rem;">
            <?php
            while ($guidesQuery->have_posts()):

                $guidesQuery->the_post();
                $categories = get_the_category();
                $primaryCat = !empty($categories)
                    ? $categories[0]->name
                    : "Engineering Guide";
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
                                <?php echo esc_html($readingTime); ?> min read
                            </span>
                        </div>

                        <h2 style="font-size: 1.25rem; font-weight: 700; line-height: 1.35; margin: 0 0 0.75rem 0;">
                            <a href="<?php the_permalink(); ?>" style="color: var(--hs-text-primary); text-decoration: none;">
                                <?php the_title(); ?>
                            </a>
                        </h2>

                        <p style="font-size: 0.9375rem; color: var(--hs-text-dim); line-height: 1.55; margin: 0 0 1.25rem 0;">
                            <?php echo esc_html(
                                wp_trim_words(get_the_excerpt(), 25),
                            ); ?>
                        </p>
                    </div>

                    <div style="margin-top: 1rem;">
                        <a href="<?php the_permalink(); ?>" class="hs-link" style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; font-size: 0.875rem; color: var(--hs-accent); text-decoration: none;">
                            <?php esc_html_e(
                                "Read Full Guide",
                                "helmetsan-theme",
                            ); ?> <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </article>
            <?php
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    <?php else: ?>
        <p style="color: var(--hs-muted);"><?php esc_html_e(
            "No editorial guides found.",
            "helmetsan-theme",
        ); ?></p>
    <?php endif;
    ?>
</main>

<?php get_footer();
