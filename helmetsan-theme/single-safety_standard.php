<?php
/**
 * Single safety standard template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        $id = get_the_ID();
        $regionsJson = (string) get_post_meta($id, 'standard_regions_json', true);
        $mandatoryJson = (string) get_post_meta($id, 'mandatory_markets_json', true);
        $focusJson = (string) get_post_meta($id, 'test_focus_json', true);
        $refUrl = (string) get_post_meta($id, 'official_reference_url', true);

        $regions = json_decode($regionsJson, true);
        $mandatory = json_decode($mandatoryJson, true);
        $focus = json_decode($focusJson, true);

        $certTerms = get_the_terms($id, 'certification');
        $termIds = [];
        if (is_array($certTerms)) {
            foreach ($certTerms as $term) {
                if ($term instanceof WP_Term) {
                    $termIds[] = (int) $term->term_id;
                }
            }
        }

        $linkedHelmets = [];
        if ($termIds !== []) {
            $linkedHelmets = get_posts([
                'post_type' => 'helmet',
                'post_status' => 'publish',
                'posts_per_page' => 8,
                'tax_query' => [
                    [
                        'taxonomy' => 'certification',
                        'field' => 'term_id',
                        'terms' => $termIds,
                    ],
                ],
            ]);
        }
        ?>
        <article <?php post_class('hs-section'); ?>>
            <header class="hs-section__head">
                <p class="hs-eyebrow">Safety Standard</p>
                <h1><?php the_title(); ?></h1>
            </header>

            <div class="hs-panel"><?php the_content(); ?></div>

            <div class="hs-grid hs-grid--2">
                <section class="hs-panel">
                    <h2>Regions</h2>
                    <ul class="hs-list">
                        <?php if (is_array($regions)) : foreach ($regions as $item) : ?>
                            <li><?php echo esc_html((string) $item); ?></li>
                        <?php endforeach; endif; ?>
                    </ul>
                </section>
                <section class="hs-panel">
                    <h2>Mandatory Markets</h2>
                    <ul class="hs-list">
                        <?php if (is_array($mandatory) && $mandatory !== []) : foreach ($mandatory as $item) : ?>
                            <li><?php echo esc_html((string) $item); ?></li>
                        <?php endforeach; else : ?>
                            <li>Voluntary or mixed by jurisdiction.</li>
                        <?php endif; ?>
                    </ul>
                </section>
            </div>

            <section class="hs-panel">
                <h2>Test Focus</h2>
                <ul class="hs-list">
                    <?php if (is_array($focus)) : foreach ($focus as $item) : ?>
                        <li><?php echo esc_html((string) $item); ?></li>
                    <?php endforeach; endif; ?>
                </ul>
                <?php if ($refUrl !== '') : ?>
                    <p><a class="hs-link" href="<?php echo esc_url($refUrl); ?>" target="_blank" rel="noopener noreferrer">Official Reference</a></p>
                <?php endif; ?>
            </section>

            <?php if ($linkedHelmets !== []) : ?>
                <section class="hs-panel">
                    <h2>Helmets Linked to This Standard</h2>
                    <div class="helmet-grid">
                        <?php foreach ($linkedHelmets as $post) : setup_postdata($post); get_template_part('template-parts/helmet', 'card'); endforeach; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
        <?php
    }
}

get_footer();

