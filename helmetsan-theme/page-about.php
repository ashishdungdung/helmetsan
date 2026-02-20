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
        <article <?php post_class('about-page'); ?>>
            
            <!-- Hero Section -->
            <header class="about-hero hs-section" style="text-align: center; padding: 6rem 1rem; background: linear-gradient(135deg, rgba(30,30,30,1) 0%, rgba(20,20,20,1) 100%); color: white; border-radius: var(--hs-border-radius); overflow: hidden; position: relative;">
                <div style="position: absolute; top:0; left:0; right:0; bottom:0; opacity: 0.1; background-image: radial-gradient(circle at center, #ffffff 1px, transparent 1px); background-size: 20px 20px;"></div>
                <div style="position: relative; z-index: 1;">
                    <p class="hs-eyebrow" style="color: var(--hs-brand-primary);">Our Mission</p>
                    <h1 style="font-size: 4rem; font-weight: 800; max-width: 800px; margin: 0 auto 1.5rem auto; line-height: 1.1;">Protecting the Ride. <br/> Empowering the Rider.</h1>
                    <p style="font-size: 1.25rem; max-width: 600px; margin: 0 auto; color: #ccc;">We believe that safety shouldn't be a guessing game. Helmetsan is dedicated to decoding complex helmet technical data so you can hit the road with confidence.</p>
                </div>
            </header>

            <!-- Story Section -->
            <section class="hs-section about-story hs-grid hs-grid--2" style="margin-top: 4rem; align-items: center;">
                <div class="about-story__image hs-panel" style="padding: 0; aspect-ratio: 4/5; background: #eaecf0; display: flex; align-items:center; justify-content:center; overflow:hidden;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <!-- <img src="/path/to/story-image.jpg" alt="Our Story" style="width: 100%; height: 100%; object-fit: cover;" /> -->
                </div>
                <div class="about-story__content">
                    <p class="hs-eyebrow">The Origin</p>
                    <h2>Why We Build Helmetsan</h2>
                    <div style="font-size: 1.1rem; line-height: 1.7; color: var(--hs-text-muted);">
                        <?php the_content(); ?>
                        <?php if (empty(get_the_content())): ?>
                            <p>It started with a simple frustration: trying to find reliable data on motorcycle helmets was nearly impossible. Marketing jargon clouded real safety performance.</p>
                            <p>We created Helmetsan to cut through the noise. By aggregating certifications, technical tear-downs, and real-world ride data, we give you the unvarnished truth about what protects your head.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Stats/Metrics Section -->
            <section class="about-stats hs-section" style="padding: 4rem 2rem; background: var(--hs-bg-alt); border-radius: var(--hs-border-radius); text-align: center; margin-top: 3rem;">
                <h2 style="margin-bottom: 3rem;">By the Numbers</h2>
                <div class="hs-grid hs-grid--3">
                    <div>
                        <div style="font-size: 3rem; font-weight: 800; color: var(--hs-brand-primary); margin-bottom: 0.5rem;">5,000+</div>
                        <p style="font-weight: 600;">Helmets Indexed</p>
                    </div>
                    <div>
                        <div style="font-size: 3rem; font-weight: 800; color: var(--hs-brand-primary); margin-bottom: 0.5rem;">12+</div>
                        <p style="font-weight: 600;">Global Markets Tracked</p>
                    </div>
                    <div>
                        <div style="font-size: 3rem; font-weight: 800; color: var(--hs-brand-primary); margin-bottom: 0.5rem;">1M+</div>
                        <p style="font-weight: 600;">Data Points Analyzed</p>
                    </div>
                </div>
            </section>

            <!-- Core Values Section -->
            <section class="hs-section about-values" style="margin-top: 4rem;">
                <div class="hs-section__head" style="text-align: center; margin-bottom: 3rem;">
                    <h2>Our Core Values</h2>
                </div>
                <div class="hs-grid hs-grid--3">
                    <article class="hs-panel" style="text-align: center;">
                        <svg style="margin-bottom: 1rem; color: var(--hs-brand-primary);" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        <h3>Radical Transparency</h3>
                        <p class="hs-text-muted">We don't sell helmets, we present data. Our algorithms and testing data sources are transparent and untethered from brand influence.</p>
                    </article>
                    <article class="hs-panel" style="text-align: center;">
                        <svg style="margin-bottom: 1rem; color: var(--hs-brand-primary);" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <h3>Safety Above All</h3>
                        <p class="hs-text-muted">Aesthetics are nice, but safety ratings save lives. We prioritize stringent certifications like ECE 22.06 and FIM in our ranking logic.</p>
                    </article>
                    <article class="hs-panel" style="text-align: center;">
                        <svg style="margin-bottom: 1rem; color: var(--hs-brand-primary);" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        <h3>Constantly Evolving</h3>
                        <p class="hs-text-muted">Technology changes rapidly. From AI assistance to real-time dynamic pricing across the globe, our platform never stops improving.</p>
                    </article>
                </div>
            </section>

            <!-- Call to Action -->
            <section class="about-cta hs-section" style="margin-top: 4rem; padding: 4rem 2rem; background: var(--hs-brand-primary); color: white; border-radius: var(--hs-border-radius); text-align: center;">
                <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: white;">Ready to find your perfect fit?</h2>
                <p style="font-size: 1.1rem; max-width: 500px; margin: 0 auto 2rem auto; opacity: 0.9;">Explore thousands of thoroughly vetted helmets using our advanced filtering system or try our AI-powered assistant.</p>
                <a href="<?php echo esc_url(home_url('/helmets/')); ?>" class="hs-btn" style="background: white; color: var(--hs-brand-primary); padding: 1rem 2rem; font-size: 1.1rem;">Explore the Hub</a>
            </section>

        </article>
        <?php
    }
}

get_footer();
