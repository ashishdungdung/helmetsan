<?php
/**
 * Template Name: Contact Platform Hub
 * Helmetsan Decision Platform Contact Hub
 */

get_header();
?>

<main id="main-content" class="site-main hs-container" style="max-width: 900px; margin: 0 auto; padding: 3rem 1rem;">
    <div class="hs-text-center" style="text-align: center; margin-bottom: 3rem;">
        <span class="hs-badge" style="background: rgba(227, 6, 19, 0.08); color: var(--hs-primary, #e30613); padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
            Contact & Support
        </span>
        <h1 style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; color: var(--hs-heading, #111); margin: 0.75rem 0 1rem 0;">
            Get in Touch with Helmetsan
        </h1>
        <p style="font-size: 1.125rem; color: var(--hs-text-secondary, #4b5563); max-width: 600px; margin: 0 auto; line-height: 1.6;">
            Have a question regarding helmet technical specifications, safety data verification, editorial inquiries, or commercial partnerships? Our team is here to assist.
        </p>
    </div>

    <!-- Contact Channels Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
        <div class="hs-panel" style="background: var(--hs-surface, #fff); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: 1.75rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.75rem;">🛠️</div>
            <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--hs-heading, #111); margin: 0 0 0.5rem 0;">Data & Corrections</h2>
            <p style="font-size: 0.875rem; color: var(--hs-text-secondary, #4b5563); line-height: 1.5; margin: 0 0 1rem 0;">
                Report an inaccurate spec, weight deviation, or certification discrepancy.
            </p>
            <a href="mailto:corrections@helmetsan.com" style="font-size: 0.875rem; font-weight: 600; color: var(--hs-primary, #e30613); text-decoration: none;">
                corrections@helmetsan.com →
            </a>
        </div>

        <div class="hs-panel" style="background: var(--hs-surface, #fff); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: 1.75rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.75rem;">📝</div>
            <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--hs-heading, #111); margin: 0 0 0.5rem 0;">Editorial & Reviews</h2>
            <p style="font-size: 0.875rem; color: var(--hs-text-secondary, #4b5563); line-height: 1.5; margin: 0 0 1rem 0;">
                Editorial pitches, testing inquiries, or gear review suggestions.
            </p>
            <a href="mailto:editorial@helmetsan.com" style="font-size: 0.875rem; font-weight: 600; color: var(--hs-primary, #e30613); text-decoration: none;">
                editorial@helmetsan.com →
            </a>
        </div>

        <div class="hs-panel" style="background: var(--hs-surface, #fff); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: 1.75rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.75rem;">💬</div>
            <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--hs-heading, #111); margin: 0 0 0.5rem 0;">General Inquiries</h2>
            <p style="font-size: 0.875rem; color: var(--hs-text-secondary, #4b5563); line-height: 1.5; margin: 0 0 1rem 0;">
                Partnership inquiries, technical support, or platform feedback.
            </p>
            <a href="mailto:contact@helmetsan.com" style="font-size: 0.875rem; font-weight: 600; color: var(--hs-primary, #e30613); text-decoration: none;">
                contact@helmetsan.com →
            </a>
        </div>
    </div>

    <!-- Direct Correspondence Details -->
    <div class="hs-panel" style="background: var(--hs-bg-alt, #f9fafb); border: 1px solid var(--hs-border, #e5e7eb); border-radius: 12px; padding: 2rem; margin-bottom: 3rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--hs-heading, #111); margin-top: 0; margin-bottom: 1rem;">
            Direct Correspondence & Business Information
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; font-size: 0.9375rem; line-height: 1.6; color: var(--hs-text, #374151);">
            <div>
                <strong>Operating Entity:</strong><br/>
                Ash Digital Services<br/>
                <em>Publisher of Helmetsan.com</em>
            </div>
            <div>
                <strong>Email Response Time:</strong><br/>
                Within 24–48 business hours<br/>
                Monday – Friday
            </div>
            <div>
                <strong>Legal & Compliance:</strong><br/>
                <a href="<?php echo esc_url(home_url('/legal/privacy-policy/')); ?>" style="color: var(--hs-primary, #e30613); text-decoration: underline;">Privacy Policy</a> · 
                <a href="<?php echo esc_url(home_url('/legal/terms-of-use/')); ?>" style="color: var(--hs-primary, #e30613); text-decoration: underline;">Terms of Use</a>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
