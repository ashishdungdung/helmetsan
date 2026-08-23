<?php
/**
 * Helmetsan Legal & Trust Pages Content Updater
 *
 * Usage: wp eval-file scripts/seed_legal_trust_pages.php --allow-root
 */

if (! defined('ABSPATH')) {
    require_once __DIR__ . '/../wp-load.php';
}

echo "⚖️  Updating Helmetsan Legal & Trust Pages...\n";

$privacyContent = <<<HTML
<p><em>Last Updated: August 2026</em></p>

<p>Welcome to <strong>Helmetsan</strong> (accessible at <a href="https://helmetsan.com/">https://helmetsan.com/</a>), owned and operated by <strong>Ash Digital Services</strong>. We are committed to protecting your privacy and ensuring complete transparency regarding how data is collected, stored, and protected.</p>

<h2>1. Information We Collect</h2>
<p>We collect standard non-personally identifiable technical information to improve website performance and user experience:</p>
<ul>
    <li><strong>Server Log Files:</strong> Standard server logs including IP addresses, browser user-agents, operating systems, referring URLs, date/time stamps, and page response times.</li>
    <li><strong>Cookies & Local Storage:</strong> We use cookies to remember currency preferences, dark/light theme state, and helmet comparison selections.</li>
</ul>

<h2>2. Google AdSense & Advertising Cookies</h2>
<p>Helmetsan utilizes third-party advertising platforms, including <strong>Google AdSense</strong>:</p>
<ul>
    <li>Google, as a third-party vendor, uses cookies (including the DoubleClick DART cookie) to serve relevant advertisements to visitors based on visits to Helmetsan and other sites across the internet.</li>
    <li>You may opt out of personalized advertising at any time by visiting <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener noreferrer">Google Ads Settings</a> or through the Network Advertising Initiative opt-out portal at <a href="https://www.aboutads.info" target="_blank" rel="noopener noreferrer">aboutads.info</a>.</li>
</ul>

<h2>3. Analytics & Performance Monitoring</h2>
<p>We use Google Analytics and Microsoft Clarity to track aggregated site traffic patterns and user interface interactions. These tools do not collect passwords or personal payment information.</p>

<h2>4. GDPR & CCPA Consumer Rights</h2>
<p>Users hold the following rights under global privacy frameworks:</p>
<ul>
    <li><strong>Right of Access & Portability:</strong> Request details of personal data processed by Helmetsan.</li>
    <li><strong>Right to Rectification & Erasure:</strong> Request deletion or correction of stored user records.</li>
    <li><strong>CCPA Non-Discrimination:</strong> We do not sell personally identifiable consumer information to data brokers.</li>
</ul>

<h2>5. Contact Information</h2>
<p>For inquiries regarding this Privacy Policy, contact our privacy desk at: <a href="mailto:contact@helmetsan.com">contact@helmetsan.com</a>.</p>
HTML;

$affiliateContent = <<<HTML
<p><em>Last Updated: August 2026</em></p>

<p><strong>Helmetsan</strong> is an independent motorcycle helmet discovery and safety data platform published by <strong>Ash Digital Services</strong>. We believe in 100% radical transparency with our audience regarding how our research is funded and how affiliate relationships work.</p>

<h2>1. Affiliate Disclosures (FTC Compliance)</h2>
<p>To fund continuous laboratory homologation research, server infrastructure, and editorial testing, Helmetsan participates in various affiliate marketing programs. When you click links to merchant retailers (such as RevZilla, Amazon, Chromeburner, FC-Moto, or authorized dealer networks) and make a qualifying purchase, Helmetsan may receive a referral commission.</p>

<p><strong>This referral commission comes at zero additional cost to you.</strong> In many instances, our partnership feeds surface real-time stock availability, verified manufacturer warranty links, and promotional pricing.</p>

<h2>2. Uncompromising Editorial Independence</h2>
<p>Our editorial integrity and testing ratings are completely independent of commercial partnerships:</p>
<ul>
    <li><strong>Zero Paid Rankings:</strong> Brands and manufacturers cannot pay for favorable test scores, top list placements, or altered review conclusions.</li>
    <li><strong>Objective Safety Homologations:</strong> Ratings are calculated using verified laboratory testing standards (ECE 22.06, DOT FMVSS 218, SNELL M2020, FIM FRHPhe).</li>
    <li><strong>Unfiltered Pros & Cons:</strong> If a helmet has poor ventilation, high wind noise, or tight cranial pressure hotspots, our technical analysis explicitly documents it regardless of retailer partnerships.</li>
</ul>

<h2>3. Display Advertising (Google AdSense)</h2>
<p>Helmetsan displays standard programmatic advertising through Google AdSense and authorized ad exchanges. All display ads are clearly demarcated from editorial content and have zero influence over our product reviews or comparison algorithms.</p>

<h2>4. Questions or Feedback</h2>
<p>If you have questions regarding our affiliate disclosures or editorial testing standards, please reach out to: <a href="mailto:editorial@helmetsan.com">editorial@helmetsan.com</a>.</p>
HTML;

$termsContent = <<<HTML
<p><em>Last Updated: August 2026</em></p>

<p>Welcome to <strong>Helmetsan</strong>. By using our website, comparison engines, databases, and editorial guides, you agree to comply with and be bound by these Terms of Use.</p>

<h2>1. Educational & Informational Purpose</h2>
<p>Helmetsan provides motorcycle helmet specifications, sizing calculations, safety certification data, and buying guides for informational purposes. Motorcycle riding involves inherent risk. Helmets are critical personal protective equipment; riders must always verify physical fit, proper retention fastening, and regional homologation labels prior to riding.</p>

<h2>2. Intellectual Property</h2>
<p>All original editorial articles, scoring algorithms, database structures, and platform code are the property of <strong>Ash Digital Services</strong>. Manufacturer trademarks, model names, and logos belong to their respective owners and are used nominatively for product identification and comparative evaluation.</p>

<h2>3. Disclaimer of Warranties</h2>
<p>The platform is provided "as is" without warranty of any kind. Helmetsan does not manufacture or sell physical helmets directly, nor does it guarantee specific crash outcomes.</p>

<h2>4. Legal Inquiries</h2>
<p>For formal legal inquiries, contact: <a href="mailto:contact@helmetsan.com">contact@helmetsan.com</a>.</p>
HTML;

$safetyContent = <<<HTML
<p><em>Last Updated: August 2026</em></p>

<h2>Motorcycle Helmet Safety & Compliance Statement</h2>
<p>At <strong>Helmetsan</strong>, rider safety is our primary focus. We maintain strict validation criteria for indexing safety homologations and crash test records across our database.</p>

<h2>1. Recognized International Safety Homologations</h2>
<p>We index and verify compliance for the following global standards:</p>
<ul>
    <li><strong>ECE 22.06 / 22.05:</strong> United Nations Economic Commission for Europe standards (including modern multi-axis rotational impact tests).</li>
    <li><strong>DOT FMVSS 218:</strong> United States Department of Transportation legal road baseline.</li>
    <li><strong>SNELL M2020D / M2020R:</strong> Snell Memorial Foundation independent high-energy impact testing.</li>
    <li><strong>FIM FRHPhe-01 / 02:</strong> Fédération Internationale de Motocyclisme circuit racing standard.</li>
</ul>

<h2>2. Critical Sizing & Fit Advisory</h2>
<p>A safety certification only protects you if the helmet fits your skull shape correctly. A loose helmet can twist or eject during an impact. Always measure head circumference and match your cranial profile (Long Oval, Intermediate Oval, Round) before riding.</p>

<h2>3. Safety Data Corrections</h2>
<p>To report manufacturer safety recalls or specification discrepancies, contact our compliance desk: <a href="mailto:corrections@helmetsan.com">corrections@helmetsan.com</a>.</p>
HTML;

$updates = [
    112 => $affiliateContent,
    3   => $privacyContent,
    111 => $termsContent,
    117 => $safetyContent,
    18477 => $affiliateContent,
    18482 => $privacyContent,
    18476 => $termsContent,
    18466 => $safetyContent,
];

foreach ($updates as $id => $content) {
    $res = wp_update_post([
        'ID'           => $id,
        'post_content' => $content,
        'post_status'  => 'publish',
    ]);
    echo "   ✅ Updated Post ID {$id}: " . ($res ? "OK" : "FAILED") . "\n";
}

echo "🎉 Completed legal & trust pages updates!\n";
