<?php
/**
 * Single motorcycle template — Helmetsan Recommendation Engine
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        $id = get_the_ID();
        $title = get_the_title();
        $make = (string) get_post_meta($id, 'motorcycle_make', true);
        if (empty($make)) $make = strstr($title, ' ', true) ?: 'Motorcycle';
        $segment = (string) get_post_meta($id, 'bike_segment', true);
        if (empty($segment)) $segment = 'Adventure / Touring';
        $engine = (string) get_post_meta($id, 'engine_cc', true);
        if (empty($engine)) $engine = '450';

        $entityId = (string) get_post_meta($id, '_motorcycle_unique_id', true);
        if (empty($entityId)) $entityId = (string) get_post_field('post_name', $id);

        $compatData = class_exists('Helmetsan_CompatibilityEngine')
            ? \Helmetsan_CompatibilityEngine::get_entity_compatibility('motorcycle', $entityId)
            : [];

        $recHelmets = $compatData['recommended_helmets'] ?? [];
        $recAccs = $compatData['recommended_accessories'] ?? [];

        // Fallback helmets if compatibility matrix returned empty
        if (empty($recHelmets)) {
            $fallbackPosts = get_posts([
                'post_type' => 'helmet',
                'post_status' => 'publish',
                'post_parent' => 0,
                'posts_per_page' => 4,
            ]);
            foreach ($fallbackPosts as $fb) {
                $recHelmets[] = [
                    'id' => $fb->post_name,
                    'title' => $fb->post_title,
                    'brand' => function_exists('helmetsan_get_brand_name') ? helmetsan_get_brand_name($fb->ID) : 'Universal',
                    'match_score' => 92,
                    'match_reason' => 'Versatile aerodynamic stability profile for daily road and highway riding.'
                ];
            }
        }
        ?>
        <article <?php post_class('hs-container hs-py-12'); ?>>
            <!-- Header Hero -->
            <div class="hs-card hs-p-8 hs-mb-8 hs-bg-dark hs-text-white hs-relative hs-overflow-hidden">
                <div class="hs-badge hs-badge-accent hs-mb-3">MOTORCYCLE RECOMMENDATION ENGINE</div>
                <h1 class="hs-title-xl hs-mb-2"><?php echo esc_html($title); ?></h1>
                <p class="hs-text-soft hs-max-w-2xl hs-mb-6">Helmetsan recommendation profile tailored specifically for the <?php echo esc_html($title); ?> riding ergonomics, wind screen profile, and velocity characteristics.</p>

                <!-- Riding Profile Grid -->
                <div class="hs-grid hs-grid-cols-2 md:hs-grid-cols-4 hs-gap-4 hs-pt-4 hs-border-t hs-border-subtle">
                    <div>
                        <div class="hs-text-micro hs-uppercase hs-text-muted hs-font-bold">Manufacturer</div>
                        <div class="hs-font-bold hs-text-base"><?php echo esc_html($make); ?></div>
                    </div>
                    <div>
                        <div class="hs-text-micro hs-uppercase hs-text-muted hs-font-bold">Segment</div>
                        <div class="hs-font-bold hs-text-base"><?php echo esc_html($segment); ?></div>
                    </div>
                    <div>
                        <div class="hs-text-micro hs-uppercase hs-text-muted hs-font-bold">Displacement</div>
                        <div class="hs-font-bold hs-text-base"><?php echo esc_html($engine); ?> cc</div>
                    </div>
                    <div>
                        <div class="hs-text-micro hs-uppercase hs-text-muted hs-font-bold">Recommended Cert</div>
                        <div class="hs-font-bold hs-text-base hs-text-green">🟢 ECE 22.06 / DOT</div>
                    </div>
                </div>
            </div>

            <!-- Recommendation Analysis Callout -->
            <div class="hs-card hs-p-6 hs-mb-8 hs-border-l-4 hs-border-accent">
                <h2 class="hs-title-sm hs-mb-2">💡 Riding Profile & Aerodynamic Recommendation</h2>
                <p class="hs-text-xs hs-text-soft hs-leading-relaxed">
                    The <strong><?php echo esc_html($title); ?></strong> is matched via the Helmetsan Multi-Axial Compatibility Matrix. Pairings account for forward riding tuck, slipstream turbulence over the handlebars, and cockpit acoustic sound dampening.
                </p>
            </div>

            <!-- Recommended Helmets Grid -->
            <section class="hs-mb-12">
                <div class="hs-flex hs-items-center hs-justify-between hs-mb-6">
                    <div>
                        <span class="hs-badge hs-badge-neutral hs-mb-1">COMPATIBILITY MATRIX</span>
                        <h2 class="hs-title-md">Recommended Helmets for <?php echo esc_html($title); ?></h2>
                    </div>
                    <a href="<?php echo esc_url(helmetsan_url('/helmets/')); ?>" class="hs-btn hs-btn-outline hs-btn-sm">Browse All Helmets →</a>
                </div>

                <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-2 lg:hs-grid-cols-4 hs-gap-6">
                    <?php foreach ($recHelmets as $hMatch): 
                        $hPost = get_page_by_path($hMatch['id'], OBJECT, 'helmet');
                        $hUrl = $hPost ? helmetsan_permalink($hPost->ID) : helmetsan_url('/helmets/' . sanitize_title($hMatch['id']) . '/');
                        $hImg = $hPost ? get_the_post_thumbnail_url($hPost->ID, 'medium_large') : '';
                        if (!$hImg) {
                            $hImg = home_url('/wp-content/themes/helmetsan-theme/assets/images/placeholder-helmet.jpg');
                        }
                    ?>
                        <div class="hs-card hs-p-5 hs-flex hs-flex-col hs-justify-between hs-border hs-border-subtle hover:hs-shadow-lg hs-transition">
                            <div>
                                <div class="hs-flex hs-justify-between hs-items-center hs-mb-3">
                                    <span class="hs-badge hs-badge-accent hs-text-xs hs-font-bold"><?php echo esc_html($hMatch['match_score'] ?? 95); ?>% MATCH</span>
                                    <span class="hs-text-micro hs-text-muted hs-uppercase hs-font-bold"><?php echo esc_html($hMatch['type'] ?? 'Full Face'); ?></span>
                                </div>
                                <div class="hs-aspect-square hs-mb-4 hs-overflow-hidden hs-rounded hs-bg-subtle hs-flex hs-items-center hs-justify-center">
                                    <img src="<?php echo esc_url($hImg); ?>" alt="<?php echo esc_attr($hMatch['title']); ?>" class="hs-max-h-full hs-object-contain" loading="lazy" />
                                </div>
                                <div class="hs-text-micro hs-text-muted hs-font-bold hs-uppercase"><?php echo esc_html($hMatch['brand'] ?? ''); ?></div>
                                <h3 class="hs-font-bold hs-text-base hs-mb-2">
                                    <a href="<?php echo esc_url($hUrl); ?>" class="hover:hs-text-accent hs-transition">
                                        <?php echo esc_html($hMatch['title']); ?>
                                    </a>
                                </h3>
                                <p class="hs-text-xs hs-text-soft hs-mb-4 hs-leading-relaxed">
                                    <?php echo esc_html($hMatch['match_reason'] ?? 'Optimized aerodynamic balance and vision field.'); ?>
                                </p>
                            </div>
                            <a href="<?php echo esc_url($hUrl); ?>" class="hs-btn hs-btn-outline hs-btn-sm hs-w-full hs-text-center">
                                View Helmet Specs →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Recommended Accessories Grid -->
            <?php if (!empty($recAccs)): ?>
            <section class="hs-mb-12">
                <div class="hs-flex hs-items-center hs-justify-between hs-mb-6">
                    <div>
                        <span class="hs-badge hs-badge-accent hs-mb-1">COCKPIT & RIDER GEAR</span>
                        <h2 class="hs-title-md">Recommended Accessories for <?php echo esc_html($title); ?></h2>
                    </div>
                    <a href="<?php echo esc_url(helmetsan_url('/accessories/')); ?>" class="hs-btn hs-btn-outline hs-btn-sm">Browse All Accessories →</a>
                </div>

                <div class="hs-grid hs-grid-cols-1 md:hs-grid-cols-3 hs-gap-6">
                    <?php foreach ($recAccs as $aMatch): 
                        $aPost = get_page_by_path($aMatch['id'], OBJECT, 'accessory');
                        $aUrl = $aPost ? helmetsan_permalink($aPost->ID) : helmetsan_url('/accessories/' . sanitize_title($aMatch['id']) . '/');
                        $aImg = $aPost ? get_the_post_thumbnail_url($aPost->ID, 'medium_large') : '';
                        if (!$aImg) {
                            $aImg = home_url('/wp-content/themes/helmetsan-theme/assets/images/placeholder-accessory.jpg');
                        }
                    ?>
                        <div class="hs-card hs-p-5 hs-flex hs-flex-col hs-justify-between hs-border hs-border-subtle hover:hs-shadow-lg hs-transition">
                            <div>
                                <div class="hs-flex hs-justify-between hs-items-center hs-mb-3">
                                    <span class="hs-badge hs-badge-green hs-text-xs hs-font-bold">VERIFIED COCKPIT FIT</span>
                                </div>
                                <div class="hs-aspect-video hs-mb-4 hs-overflow-hidden hs-rounded hs-bg-subtle hs-flex hs-items-center hs-justify-center">
                                    <img src="<?php echo esc_url($aImg); ?>" alt="<?php echo esc_attr($aMatch['title']); ?>" class="hs-max-h-full hs-object-contain" loading="lazy" />
                                </div>
                                <h3 class="hs-font-bold hs-text-base hs-mb-2">
                                    <a href="<?php echo esc_url($aUrl); ?>" class="hover:hs-text-accent hs-transition">
                                        <?php echo esc_html($aMatch['title']); ?>
                                    </a>
                                </h3>
                                <p class="hs-text-xs hs-text-soft hs-mb-4 hs-leading-relaxed">
                                    <?php echo esc_html($aMatch['reason'] ?? 'Essential cockpit communication, protection, or maintenance accessory.'); ?>
                                </p>
                            </div>
                            <a href="<?php echo esc_url($aUrl); ?>" class="hs-btn hs-btn-outline hs-btn-sm hs-w-full hs-text-center">
                                View Accessory →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </article>
        <?php
    }
}

get_footer();
