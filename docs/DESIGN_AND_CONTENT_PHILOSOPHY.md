# Helmetsan Design & Content Philosophy

**Purpose:** Helmetsan is the best-in-class helmet comparison and discovery platform for riders. Every page is a destination: informative, actionable, and designed to help people choose and buy. We are not a bare repository—we are a trusted guide with rich content, clear CTAs, and a world-class UX.

---

## 1. Content Philosophy

### 1.1 Every Page Has Substance
- **No thin content.** Every URL that can show ads or be indexed must have a solid amount of **readable, unique text** (not only tables, specs, or one-line fallbacks).
- **Unique, high-quality copy:** Real descriptions, technical analysis, and editorial value. Use AI fill-missing and SEO seed to populate; never leave “Explore our X collection” or “Premium selection of…” as the only body copy.
- **Informative + CTA:** Every template is both **informative** (teach, compare, explain) and **action-oriented** (compare, view offers, see brand, add to comparison). We encourage people to take the next step.

### 1.2 Content Sources
- **Helmet pages:** Post content + `technical_analysis` meta must be filled and **visible** (About + Technical Analysis sections). No hidden content.
- **Brand / accessory / taxonomy:** Real term descriptions and entity copy. No generic fallbacks site-wide.
- **Editorial:** Buying guides, “How to choose a helmet”, certification explainers, and safety content written for humans first.

### 1.3 Deep Pagination
- We have many helmets, accessories, brands, and bikes. Pagination stays **deep** (e.g. 24–40 items per page).
- **Each page is rich:** Archive/category pages include intro copy, filters, and meaningful section structure so page 2, 3, … are valuable, not shallow list-only layouts.

---

## 2. SEO Policy

- **Minimum content requirements:** Every important template meets a minimum of substantial, original text (guidance: 300+ words of unique prose where applicable, plus structured data).
- **Unique titles and meta:** Yoast/SEO seed and AI fill-missing drive unique titles, meta descriptions, and focus keyphrases per entity.
- **No thin or duplicate patterns:** Avoid large numbers of URLs that differ only by pagination or filter with identical boilerplate. Add unique intros, term descriptions, or editorial blocks.
- **Schema & accessibility:** Maintain Product, Brand, and BreadcrumbList schema; keep headings and landmarks logical for screen readers and crawlers.

---

## 3. Design Policy

### 3.1 Design Principles
- **Clear hierarchy:** Dedicated sections that clearly show one thing (e.g. Technical Analysis, Safety Intelligence, Compare CTA). Each section has a purpose.
- **Marketplace-inspired UX:** Take cues from top helmet sites (RevZilla, Chromeburner, HelmetReviews.org): clear filters, strong product presentation, comparison tools, trust signals.
- **Visual language:** Graphical, great icons, thoughtful animation, and color used with intent. Not “AI slop”—distinct, confident, rider-focused.
- **Performance:** Blazing fast. Critical CSS inlined where appropriate; lazy-load below-fold; minimal layout shift.

### 3.2 Theming
- **Light and dark mode** with deep color and text integration. Multiple themes (e.g. light, dark, high-contrast) via design tokens.
- **Design tokens:** Single source of truth for colors, typography, spacing, radius, and shadows. All components consume tokens.

### 3.3 Components & Pages
- **Cards and lists:** Informative (specs, certs, price) plus clear CTA (View, Compare, See offers). No “dead” cards.
- **Archives and categories:** Beautiful layout, real copy, strong structure. Hero or intro area, filters, then grid/list. No bare “Explore our X collection” as the only content.
- **Single templates:** Rich sections (About, Technical Analysis, Safety, Pricing, Related, Compare CTA). Content above the fold where possible; CTAs visible without excessive scrolling.
- **Single helmet (RevZilla-inspired):** Product description (About + Technical Analysis), Part numbers (RevZilla-style table: Product style, MFR. product #, Availability; plus optional label/value rows), Sizing & fit (size chart, How to measure, disclaimer), Images & details (gallery), Related videos. In-page nav jumps to Product description, Part numbers, Sizing & fit. Section headings use icons consistently. **View on retailers** lists linked marketplace URLs (RevZilla, Amazon, etc.) when `marketplace_links` are stored; these links support affiliate and future AI/data/image workflows (see [Data flow – Marketplace links](data-flow.md#marketplace-links-revzilla-amazon-etc)).

---

## 4. Comparison Engine

- **Best-in-class comparison UX:** Clear add-to-compare flow, sticky comparison bar, and comparison page that surfaces specs, certs, and prices side-by-side with a clear “Where to buy” or “View offers” path.
- **Rework all engines:** Comparison, filters, search, and any “engine” (e.g. recommendation, dealer/distributor indexes) follow the same philosophy: informative, actionable, fast, and well-designed.

---

## 5. Implementation Checklist (Theme & Content)

| Area | Requirement |
|------|-------------|
| Single helmet | About + Technical Analysis visible; rich sections; prominent Compare CTA; related products and offers |
| Single brand / accessory | Unique copy; no generic fallbacks; CTAs to helmets/offers |
| Taxonomy/archive | Intro copy or term description; filters; rich cards; meaningful pagination |
| Entity cards | Informative (specs/certs/price) + CTA (View / Compare) |
| Editorial pages | Buying guide, how to choose, certifications — written for humans |
| Global | Header, footer, theme toggle (light/dark), performance, accessibility |
| Comparison | Sticky bar, comparison page redesign, clear actions |

---

## 6. Populating content via AI

- **Catalog → “Fill all missing / outdated”** runs AI fill-missing for up to 100 helmets (meta + taxonomies, including `technical_analysis`). Use it to populate technical analysis and other fillable fields.
- **About section:** When a helmet has no post content, the theme uses `technical_analysis` meta for the About block (see `helmetsan_theme_helmet_content_from_technical_analysis` in the theme). So running fill-missing effectively populates both Technical Analysis and About when content is empty.
- **Larger batch:** From the server run:  
  `wp helmetsan ai fill-missing --post-type=helmet --only-incomplete --limit=500`  
  (then optionally `wp helmetsan seo seed --post-type=helmet --use-ai` for meta descriptions).

## 7. References

- AdSense: Minimum content requirements, unique high-quality content, Webmaster quality guidelines, thin content guidance.
- Data flow: [data-flow.md](data-flow.md). AI enrichment → Export → Push for JSON.
- AI enrichment: [ai-seeder-enrichment-roadmap.md](ai-seeder-enrichment-roadmap.md). Fill-missing and SEO seed feed visible content.
