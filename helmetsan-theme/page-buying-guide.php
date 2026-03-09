<?php
/**
 * Template Name: Buying Guide
 * Editorial page for helmet buying guides (e.g. How to choose a helmet).
 * Ensures substantial, unique content for quality and AdSense.
 *
 * @package HelmetsanTheme
 */

get_header();

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
                <p>Choosing the right motorcycle helmet depends on your riding style, head shape, budget, and the safety standards that matter in your region. This guide helps you narrow the options.</p>
                <h2>1. Helmet type</h2>
                <p><strong>Full face</strong> helmets offer the best protection and are ideal for sport and touring. <strong>Modular</strong> (flip-up) helmets add convenience for communication and stops. <strong>Open face</strong> and <strong>half</strong> helmets suit cruisers and low-speed riding. <strong>Adventure</strong> and <strong>dual sport</strong> helmets work for on- and off-road. Use our <a href="<?php echo esc_url(home_url('/helmet-types/')); ?>">helmet types</a> page to browse by category.</p>
                <h2>2. Fit and head shape</h2>
                <p>Helmets come in <strong>long-oval</strong>, <strong>intermediate-oval</strong>, and <strong>round-oval</strong> fits. A wrong shape causes pressure points and fatigue. Try before you buy when possible, or check brand sizing guides. Our helmet pages show head-shape info when available.</p>
                <h2>3. Safety standards</h2>
                <p><strong>DOT</strong> (USA), <strong>ECE</strong> (Europe), and <strong>Snell</strong> are common certifications. <strong>SHARP</strong> ratings give extra impact information. Filter by certification in the <a href="<?php echo esc_url(get_post_type_archive_link('helmet')); ?>">helmet catalog</a> and read our <a href="<?php echo esc_url(home_url('/safety-standards/')); ?>">safety standards</a> page for details.</p>
                <h2>4. Compare and buy</h2>
                <p>Use our <a href="<?php echo esc_url(home_url('/comparison/')); ?>">comparison tool</a> to see helmets side by side. Check weight, shell material, and price. Then use the “Check price” or “Buy now” links on each helmet page to see current offers from trusted retailers.</p>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

get_footer();
