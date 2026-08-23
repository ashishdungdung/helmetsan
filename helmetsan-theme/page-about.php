<?php
/**
 * Custom template for the About page.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        ?>
        <article <?php post_class('about-page hs-container'); ?> style="max-width: 1000px; margin: 0 auto; padding: 2.5rem 1rem;">
            
            <!-- Hero Section -->
            <header class="about-hero hs-section" style="text-align: center; padding: 5rem 1.5rem; background: linear-gradient(135deg, #18181b 0%, #09090b 100%); color: white; border-radius: 16px; overflow: hidden; position: relative; margin-bottom: 3.5rem;">
                <div style="position: absolute; top:0; left:0; right:0; bottom:0; opacity: 0.12; background-image: radial-gradient(circle at center, #ffffff 1px, transparent 1px); background-size: 24px 24px;"></div>
                <div style="position: relative; z-index: 1;">
                    <p class="hs-eyebrow" style="color: var(--hs-primary, #e30613); text-transform: uppercase; font-size: 0.8125rem; font-weight: 700; letter-spacing: 0.08em; margin-bottom: 0.75rem;">About Helmetsan</p>
                    <h1 style="font-size: clamp(2.25rem, 5vw, 3.75rem); font-weight: 800; max-width: 800px; margin: 0 auto 1.25rem auto; line-height: 1.15; color: #ffffff;">
                        Protecting the Ride.<br/>Empowering the Rider.
                    </h1>
                    <p style="font-size: 1.1875rem; max-width: 650px; margin: 0 auto; color: #d4d4d8; line-height: 1.6;">
                        We believe rider safety should never rely on marketing hype. Helmetsan provides independent technical data, certified crash test analysis, and ergonomic intelligence.
                    </p>
                </div>
            </header>

            <!-- Mission & Origin -->
            <section class="hs-panel" style="background: var(--hs-surface, #fff); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: clamp(1.5rem, 3vw, 2.5rem); margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--hs-heading, #111); margin-top: 0; margin-bottom: 1rem;">Why Helmetsan Exists</h2>
                <p style="font-size: 1.0625rem; line-height: 1.75; color: var(--hs-text, #374151); margin-bottom: 1.25rem;">
                    Finding reliable, verified information on motorcycle helmets used to be fragmented. Manufacturers often highlight subjective styling while obscuring critical details—such as multi-density EPS construction, shell weight variance by size, rotational impact mitigation, or regional homologation limits.
                </p>
                <p style="font-size: 1.0625rem; line-height: 1.75; color: var(--hs-text, #374151); margin-bottom: 0;">
                    We created <strong>Helmetsan</strong> as a dedicated motorcycle helmet discovery and analysis platform. By unifying lab homologations, geometric fit calculations, and verified rider feedback, we give motorcyclists unbiased clarity on the gear that protects their lives.
                </p>
            </section>

            <!-- Methodology & Data Standards -->
            <section class="hs-panel" style="background: var(--hs-bg-alt, #f9fafb); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: clamp(1.5rem, 3vw, 2.5rem); margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--hs-heading, #111); margin-top: 0; margin-bottom: 1.25rem;">Our Data & Editorial Methodology</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--hs-heading, #111); margin-bottom: 0.5rem;">1. Certified Homologations</h3>
                        <p style="font-size: 0.9375rem; line-height: 1.6; color: var(--hs-text-secondary, #4b5563); margin: 0;">
                            We only index verified testing standards (ECE 22.06, DOT FMVSS 218, SNELL M2020, FIM FRHPhe) backed by official laboratory testing records.
                        </p>
                    </div>
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--hs-heading, #111); margin-bottom: 0.5rem;">2. Independent Evaluation</h3>
                        <p style="font-size: 0.9375rem; line-height: 1.6; color: var(--hs-text-secondary, #4b5563); margin: 0;">
                            We do not accept paid manufacturer ranking placements. Our editorial comparisons and guides are written strictly from a safety and ergonomic perspective.
                        </p>
                    </div>
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--hs-heading, #111); margin-bottom: 0.5rem;">3. True Head Shape Profiling</h3>
                        <p style="font-size: 0.9375rem; line-height: 1.6; color: var(--hs-text-secondary, #4b5563); margin: 0;">
                            Every helmet is mapped to cranial profiles (Long Oval, Intermediate Oval, Round) to help riders avoid dangerous pressure hotspots and sizing mismatches.
                        </p>
                    </div>
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--hs-heading, #111); margin-bottom: 0.5rem;">4. Continuous Verification</h3>
                        <p style="font-size: 0.9375rem; line-height: 1.6; color: var(--hs-text-secondary, #4b5563); margin: 0;">
                            Our data pipeline continuously verifies part numbers, safety recalls, and sizing charts to maintain accurate technical records.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Editorial Board -->
            <section class="hs-panel" style="background: var(--hs-surface, #fff); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: clamp(1.5rem, 3vw, 2.5rem); margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--hs-heading, #111); margin-top: 0; margin-bottom: 1rem;">The Editorial & Technical Team</h2>
                <p style="font-size: 1.0625rem; line-height: 1.7; color: var(--hs-text, #374151); margin-bottom: 1.5rem;">
                    Helmetsan is built and maintained by passionate track riders, long-distance touring enthusiasts, and software engineers at <strong>Ash Digital Services</strong>. Our team combines over three decades of collective riding experience with deep expertise in mechanical safety standards and fluid dynamics.
                </p>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="hs-btn hs-btn-primary" style="background: var(--hs-primary, #e30613); color: #fff; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9375rem;">
                        Contact Editorial Team →
                    </a>
                    <a href="<?php echo esc_url(home_url('/blog/')); ?>" class="hs-btn hs-btn-outline" style="border: 1px solid var(--hs-border, #e5e7eb); color: var(--hs-heading, #111); padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9375rem;">
                        Explore Our Guides →
                    </a>
                </div>
            </section>

            <!-- Metrics -->
            <section class="about-stats" style="padding: 3rem 1.5rem; background: var(--hs-bg-alt, #f9fafb); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; text-align: center; margin-bottom: 3rem;">
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--hs-heading, #111); margin-bottom: 2rem;">Platform Intelligence at Scale</h2>
                <div class="hs-grid hs-grid--3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                    <div>
                        <div style="font-size: 2.75rem; font-weight: 800; color: var(--hs-primary, #e30613); margin-bottom: 0.25rem;">2,200+</div>
                        <p style="font-weight: 600; color: var(--hs-text, #374151); margin: 0;">Verified Helmet Models</p>
                    </div>
                    <div>
                        <div style="font-size: 2.75rem; font-weight: 800; color: var(--hs-primary, #e30613); margin-bottom: 0.25rem;">60+</div>
                        <p style="font-weight: 600; color: var(--hs-text, #374151); margin: 0;">Global Helmet Brands</p>
                    </div>
                    <div>
                        <div style="font-size: 2.75rem; font-weight: 800; color: var(--hs-primary, #e30613); margin-bottom: 0.25rem;">100%</div>
                        <p style="font-weight: 600; color: var(--hs-text, #374151); margin: 0;">Independent Data</p>
                    </div>
                </div>
            </section>

        </article>
        <?php
    }
}

get_footer();
