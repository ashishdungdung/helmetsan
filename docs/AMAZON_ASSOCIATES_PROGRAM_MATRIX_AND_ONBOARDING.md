# Amazon Associates Regional Program Matrix & Onboarding Guide

## Overview

Helmetsan operates a global affiliate monetization engine supporting **21 Amazon regional marketplaces**. The system is built with a **dual-path configuration model** that allows new Associate IDs to be integrated either via instant WordPress Admin input (zero deployment needed) or permanently encoded in version-controlled configuration defaults.

---

## 1. Regional Marketplace Status Matrix

| # | Country | ISO | Flag | Marketplace Domain | Current Associate Tag | Status | Target Action |
|---|---|:---:|:---:|---|---|:---:|---|
| 1 | **United States** | `US` | 🇺🇸 | `www.amazon.com` | `vtete-20` | **Approved** | Primary OneLink Hub |
| 2 | **United Kingdom** | `UK`/`GB` | 🇬🇧 | `www.amazon.co.uk` | `vtete-21` | **Approved** | Linked in OneLink |
| 3 | **Ireland** | `IE` | 🇮🇪 | `www.amazon.co.uk` | `vtete-21` | **Approved** | Regional UK Route |
| 4 | **India** | `IN` | 🇮🇳 | `www.amazon.in` | `virginiatete-21` | **Approved** | Dedicated Program |
| 5 | **Japan** | `JP` | 🇯🇵 | `www.amazon.co.jp` | `vtete-22` | **Approved** | Dedicated Program |
| 6 | **United Arab Emirates** | `AE` | 🇦🇪 | `www.amazon.ae` | `vtete0c-21` | **Approved** | Linked in OneLink |
| 7 | **Australia** | `AU` | 🇦🇺 | `www.amazon.com.au` | `vtete-20` *(fallback)* | *Pending* | Apply at affiliate-program.amazon.com.au |
| 8 | **Canada** | `CA` | 🇨🇦 | `www.amazon.ca` | `vtete-20` *(fallback)* | *Pending* | Link via US Associates Central |
| 9 | **Germany** | `DE` | 🇩🇪 | `www.amazon.de` | `vtete-20` *(fallback)* | *Pending* | Apply at partnernet.amazon.de |
| 10 | **France** | `FR` | 🇫🇷 | `www.amazon.fr` | `vtete-20` *(fallback)* | *Pending* | Apply at partenaires.amazon.fr |
| 11 | **Italy** | `IT` | 🇮🇹 | `www.amazon.it` | `vtete-20` *(fallback)* | *Pending* | Apply at programma-affiliazione.amazon.it |
| 12 | **Spain** | `ES` | 🇪🇸 | `www.amazon.es` | `vtete-20` *(fallback)* | *Pending* | Apply at afiliados.amazon.es |
| 13 | **Netherlands** | `NL` | 🇳🇱 | `www.amazon.nl` | `vtete-20` *(fallback)* | *Pending* | Apply at partnernet.amazon.nl |
| 14 | **Poland** | `PL` | 🇵🇱 | `www.amazon.pl` | `vtete-20` *(fallback)* | *Pending* | Apply at partnernet.amazon.pl |
| 15 | **Sweden** | `SE` | 🇸🇪 | `www.amazon.se` | `vtete-20` *(fallback)* | *Pending* | Apply at partnernet.amazon.se |
| 16 | **Belgium** | `BE` | 🇧🇪 | `www.amazon.com.be` | `vtete-20` *(fallback)* | *Pending* | Apply at partnernet.amazon.com.be |
| 17 | **Brazil** | `BR` | 🇧🇷 | `www.amazon.com.br` | `vtete-20` *(fallback)* | *Pending* | Apply at associados.amazon.com.br |
| 18 | **Mexico** | `MX` | 🇲🇽 | `www.amazon.com.mx` | `vtete-20` *(fallback)* | *Pending* | Apply at afiliados.amazon.com.mx |
| 19 | **Saudi Arabia** | `SA` | 🇸🇦 | `www.amazon.sa` | `vtete-20` *(fallback)* | *Pending* | Apply at associates.amazon.sa |
| 20 | **Singapore** | `SG` | 🇸🇬 | `www.amazon.sg` | `vtete-20` *(fallback)* | *Pending* | Apply at affiliate-program.amazon.sg |
| 21 | **Turkey** | `TR` | 🇹🇷 | `www.amazon.com.tr` | `vtete-20` *(fallback)* | *Pending* | Apply at gelirortakligi.amazon.com.tr |

---

## 2. Onboarding Workflow for New Associate IDs

When you apply and receive an approval email with your unique Associate ID for any of the pending countries above:

### Method 1: Instant Runtime Update (Zero Code Changes)
1. Log into WordPress Admin: `https://helmetsan.com/wp-admin/`
2. Navigate to **Settings → Revenue**
3. Locate the **Amazon Regional Affiliate StoreIDs (21 Marketplaces)** table
4. Paste the new Associate ID into the corresponding country row (e.g. `AU` or `DE`)
5. Click **Save Changes**
6. *Instant Effect*: The server redirect engine (`/go/`) and the client-side JavaScript button switchers immediately adopt the new tag without requiring a site build or deploy.

### Method 2: Permanent Codebase Hardening (Chat Workflow)
1. Paste the confirmation email snippet or Associate ID directly into this conversation.
2. The agent will:
   - Update default configurations across `Config.php`, `Admin.php`, `AmazonCreatorConnector.php`, `amazon_creators_api.json`, and `currency-selector.js`.
   - Update unit tests in `RevenueServiceGeotargetingTest.php`.
   - Recompile and minify CSS bundle.
   - Run PHPUnit (135 tests) to guarantee regression-free code.
   - Deploy to production with cache purge via `deploy.sh`.
   - Verify live redirection with automated `curl` tests.

---

## 3. Amazon OneLink Linking Best Practices

After receiving a new Associate ID, always link it within your master Amazon Associates account:

1. Log into **Amazon Associates Central (US)**: `https://affiliate-program.amazon.com/`
2. Navigate to **Tools → OneLink**
3. Under **Link Store IDs**, find the country you just got approved for
4. Click **Add Store ID** and input your regional Store ID (e.g., `vtete0c-21` for UAE)
5. Click **Save Changes**

> [!NOTE]
> Linking Store IDs in OneLink enables Amazon to track cross-border shopping sessions even if a user switches marketplaces after landing on Amazon.

---

## 4. Architecture & Fallback Protection Mechanisms

1. **404 Dog Prevention**:
   - US ASINs do not always exist in international catalogs.
   - When a user selects a non-US region, `RevenueService::resolveGeotargetedUrl()` checks if the product exists in that catalog. If not, it automatically creates a high-converting search query URL (`https://www.amazon.{tld}/s?k={title}&tag={regional_tag}`) rather than pointing to a dead ASIN URL.
2. **Dynamic Client Hydration**:
   - `helmetsan-theme/inc/enqueue.php` dynamically injects all 21 regional tags into `window.helmetsan_geo_config.amazon_tags`.
   - When visitors select their country from the currency selector dropdown, buttons instantly retarget to that country's Amazon storefront without page reload.
