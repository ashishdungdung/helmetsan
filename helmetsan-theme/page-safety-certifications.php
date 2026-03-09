<?php
/**
 * Template Name: Safety & Certifications
 * Editorial page explaining helmet safety standards and certification marks.
 * Use for a dedicated "Safety & certifications" or "Certification explainer" page.
 *
 * @package HelmetsanTheme
 */

get_header();

$standards_url = home_url('/safety-standards/');
$catalog_url = get_post_type_archive_link('helmet');

while (have_posts()) {
    the_post();
    $content = get_the_content();
    $hasContent = trim(strip_tags($content)) !== '';
    ?>
    <article <?php post_class('hs-section hs-editorial'); ?>>
        <header class="hs-editorial__header">
            <h1><?php the_title(); ?></h1>
            <?php if (has_excerpt()) : ?>
                <p class="hs-editorial__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
            <?php endif; ?>
        </header>
        <div class="hs-editorial__body hs-panel">
            <?php if ($hasContent) : ?>
                <?php the_content(); ?>
            <?php else : ?>
                <p>Helmet certifications indicate that a helmet has passed tests defined by a standard. Standards vary by region and focus. Here’s a concise overview of the main marks you’ll see on motorcycle helmets.</p>

                <h2>DOT (USA)</h2>
                <p>The <strong>Department of Transportation</strong> standard is the minimum required for road use in the United States. Helmets are tested for impact absorption and penetration resistance. DOT is a self-certification by the manufacturer; not all products are retested by the government. Many riders look for ECE or Snell in addition for stronger assurance.</p>

                <h2>ECE 22.05 / 22.06 (Europe)</h2>
                <p><strong>ECE</strong> (Economic Commission for Europe) is mandatory for helmets sold in the EU and many other countries. ECE 22.05 has been the long-standing norm; <strong>ECE 22.06</strong> introduces updated impact and visor requirements. ECE testing is done by approved labs, so the mark indicates independent verification.</p>

                <h2>Snell (USA / global)</h2>
                <p>The <strong>Snell Memorial Foundation</strong> runs a voluntary, stricter standard. Snell-certified helmets typically exceed DOT and often ECE in certain impact tests. Snell M2020 and similar revs are common on high-end sport and racing helmets. Useful when you want more than legal minimums.</p>

                <h2>SHARP (UK, ratings)</h2>
                <p><strong>SHARP</strong> (Safety Helmet Assessment and Rating Programme) is a UK programme that tests helmets and publishes star ratings (1–5). It gives riders a simple way to compare real-world impact performance. Helmets still need ECE (or equivalent) to be legal; SHARP adds comparative safety information. You can filter by certification in our <a href="<?php echo esc_url($catalog_url); ?>">helmet catalog</a>.</p>

                <h2>What to choose</h2>
                <p>For road use in the USA, at least <strong>DOT</strong> is required; <strong>ECE</strong> or <strong>Snell</strong> are often preferred for better protection. In Europe, <strong>ECE</strong> is required. Check <strong>SHARP</strong> when available for extra impact data. On Helmetsan, each helmet page shows its certifications and safety intelligence; use the <a href="<?php echo esc_url($catalog_url); ?>">catalog</a> to filter by type and certification, and the <a href="<?php echo esc_url(home_url('/comparison/')); ?>">comparison tool</a> to compare specs side by side.</p>

                <p><a href="<?php echo esc_url($standards_url); ?>" class="hs-btn hs-btn--primary">Browse safety standards</a></p>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

get_footer();
