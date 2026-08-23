# Website Indexation, Google Search Console API & AdSense Quality Playbook

This playbook defines the standardized, battle-tested methodology for preparing websites for **Google Search Quality compliance, Google Search Console API automation, and Google AdSense publisher network approval**.

---

## 1. Google Search Console API Automation

Connecting an agent or automated CI pipeline directly to Google Search Console enables instant sitemap submission, live indexation verification, and URL inspection without logging into the web portal.

### 1.1 Service Account Setup
1. **Google Cloud Console**:
   - Create a dedicated Project (or use existing).
   - Enable the **Google Search Console API** (under *APIs & Services → Library*).
   - Create a Service Account (e.g. `website-auditor@project-id.iam.gserviceaccount.com`).
   - Create and download a **JSON Private Key** (save as `credentials/gsc-service-account.json` and ensure it is `.gitignore`d).
2. **Google Search Console**:
   - Navigate to **Settings → Users & Permissions**.
   - Add the Service Account email.
   - Set Permission to **Owner** (or **Full**; *Owner is required for URL Inspection API and programmatic sitemap deletion/querying*).

### 1.2 Python API Management Tool (`scripts/gsc_manager.py`)
Requirements: `pip install google-auth google-api-python-client`

```bash
# List all verified properties
python3 scripts/gsc_manager.py --list-sites

# Query sitemaps and crawl error status
python3 scripts/gsc_manager.py --site-url "sc-domain:example.com" --sitemaps

# Submit fresh sitemaps
python3 scripts/gsc_manager.py --site-url "sc-domain:example.com" --submit-sitemap https://example.com/sitemap_index.xml

# Perform live real-time URL inspection
python3 scripts/gsc_manager.py --site-url "sc-domain:example.com" --inspect https://example.com/target-article/
```

---

## 2. Thin Content & Indexation Shielding Architecture

The #1 reason modern websites fail AdSense or receive "Low Value Content" penalties is **uncontrolled mass programmatic indexation** of thin pages (product SKUs, placeholder directories, empty tags, and dummy WooCommerce endpoints).

### 2.1 Post Types & Taxonomies to Exclude
Every custom post type (CPT) and taxonomy must be classified before opening to search engines:

| Entity Type | Examples | Indexation Strategy |
|---|---|---|
| **Pillar Editorial Articles** | Blog guides, tutorials, teardowns | **INDEX, FOLLOW** (In sitemaps) |
| **Primary Entities** | Parent products (`post_parent == 0`), Brand profiles | **INDEX, FOLLOW** (Requires >300 words unique copy) |
| **Child Variants / SKUs** | Color/size variants (`post_parent > 0`) | **NOINDEX, FOLLOW** + Canonical to Parent |
| **Directory / Stub CPTs** | Dealers, distributors, comparison matrices | **NOINDEX, FOLLOW** (Excluded from sitemaps) |
| **Granular Taxonomies** | Price ranges, regions, feature tags | **NOINDEX, FOLLOW** (Excluded from sitemaps) |
| **Dummy / Staging Endpoints** | `/cart/`, `/checkout/`, `/shop/`, multilingual clones | **NOINDEX, FOLLOW** or Purge/Trash |

### 2.2 Dynamic Robots Header & Meta Rule
In WordPress / backend routing:
- Issue `X-Robots-Tag: noindex, follow` HTTP response headers.
- Inject `<meta name="robots" content="noindex, follow">`.
- Set `wpseo_sitemap_exclude_post_type` and `wpseo_sitemap_exclude_taxonomy` filters to remove sub-trees from XML sitemaps.

---

## 3. Essential Trust & Transparency Pages Checklist

AdSense reviewers manually inspect trust and compliance signals. All trust pages must be linked directly in the **global site footer**:

### 1. About Us (`/about/`)
- **Origin Story**: Why the platform was created and the specific user problem it solves.
- **Data & Testing Methodology**: Document testing equipment, laboratory standards (e.g. ECE, ISO, ANSI, benchmark criteria), and data verification pipelines.
- **Team Credentials**: Physical team overview, editorial board credentials, and operating company identity (*Ash Digital Services*).

### 2. Contact Us (`/contact/`)
- **Direct Departmental Emails**: Listed in plain text (e.g. `contact@example.com`, `editorial@example.com`, `corrections@example.com`).
- **Response SLA**: Explicitly state response timeframes (e.g. *Within 24–48 business hours*).
- **Physical / Entity Identity**: Operating business name, jurisdiction, and legal contact route.

### 3. Privacy Policy (`/privacy-policy/`)
- Fully compliant with **GDPR & CCPA**.
- Explicitly disclose third-party advertising vendors, including **Google AdSense and DoubleClick DART cookies**.
- Provide consumer opt-out links ([Google Ads Settings](https://www.google.com/settings/ads) and [AboutAds.info](https://www.aboutads.info)).

### 4. Affiliate & Advertising Disclosure (`/affiliate-disclosure/`)
- **FTC Compliance**: Declare referral commissions on merchant links (Amazon, RevZilla, etc.) at zero cost to readers.
- **Editorial Independence**: Reaffirm that commercial partnerships never influence scoring, benchmark rankings, or review conclusions.
- **Display Ad Separation**: Clearly separate programmatic banner ads from editorial content.

### 5. Terms of Use & Safety/Compliance (`/terms-of-use/`, `/compliance/`)
- Platform usage conditions, intellectual property rights, fair-use trademark notices, and safety/disclaimer advisories.

---

## 4. Substantive Editorial Base (E-E-A-T)

Do **not** apply for AdSense with only programmatic catalog listings.
- **Minimum Requirement**: Seed **15 to 25 long-form pillar articles** (800–2,000 words each).
- **Format**:
  - High-resolution featured images (no generic placeholders).
  - Clear H2/H3 subheadings and comparison markdown tables.
  - Author byline with editorial trust box.
  - Deep internal cross-linking to relevant catalog entities and related guides.

---

## 5. Automated Quality Audit Workflow

Before submitting to Google Search Console and AdSense, run an automated multi-point audit script (`scripts/deep_audit.py`):

```bash
# Automated audit checks:
1. HTTP Response Codes (verify 200 OK across Home, Blog, Guides, Products).
   * Gotcha: Check WP option `show_on_front` and `page_on_front` to avoid homepage 404s.
2. Meta Robots & X-Robots-Tag alignment.
3. Canonical tag self-referencing vs variant consolidation.
4. Word count calculation (ensure no pages under 300 words in sitemaps).
5. Schema JSON-LD markup presence (Article, WebSite, Product, Organization).
```

---

## 6. Project-Specific Deployment Map

| Project | Target Domain | Key Action Items |
|---|---|---|
| **Helmetsan** | `helmetsan.com` | GSC API active; 15 pillar guides live; variant `noindex` enforced. |
| **The Modern Theme** | Target Client Sites | Ship `gsc_manager.py` in `scripts/`; include standard Trust page templates in `page-templates/`. |
| **Dazestack** | `dazestack.com` | Audit catalog archives; implement FTC disclosure & developer tooling guides. |
| **Android Emulator** | Target Web Portal | Add technical benchmark guides; verify sitemap exclusions for thin build variants. |
