# Helmetsan Legal Architecture & Policy Tracking Registry

This registry serves as the authoritative governance document and version-control ledger for all legal agreements, privacy frameworks, disclaimers, and disclosures across **Helmetsan** (`helmetsan.com`), operated by **Ash Digital Services / Ashish Digital Services** (Rourkela, Odisha, India).

---

## 1. Master Policy Directory

| Document Title | Canonical Route | WordPress ID | Target Jurisdictions | Core Compliance Frameworks | Status | Current Version | Last Audited |
|---|---|---|---|---|---|---|---|
| **Legal Hub & Directory** | `/legal/` | `109` | Global | Site Architecture, ISO/IEC 27001 Governance | Active | `v2.4.0` | September 2026 |
| **Privacy Policy** | `/legal/privacy-policy/` | `3` | Global (EU, UK, US, IN, CN) | GDPR, UK-GDPR, CCPA/CPRA, India DPDP Act 2023, PIPL | Active | `v3.0.0` | September 2026 |
| **Terms of Use** | `/legal/terms-of-use/` | `111` | Global | Indian Contract Act 1872, US DMCA, Consumer Protection Directives | Active | `v2.8.0` | September 2026 |
| **Safety & Medical Disclaimer** | `/legal/disclaimer/` | `113` | Global | ECE 22.06, DOT FMVSS 218, SNELL M2020/2025, FIM FRHPhe, Product Liability | Active | `v2.5.0` | September 2026 |
| **Cookie & Tracking Policy** | `/legal/cookie-policy/` | `114` | Global (EU, UK, US-CA) | ePrivacy Directive, GDPR Art. 7, CCPA/CPRA, GPC Signals | Active | `v2.3.0` | September 2026 |
| **Affiliate & Advertising Disclosure** | `/legal/affiliate-disclosure/` | `112` | US, UK, EU, Global | FTC 16 CFR Part 255, UK ASA / CAP Code, Google AdSense Quality Policies | Active | `v2.6.0` | September 2026 |
| **AI Governance & Transparency Policy** | `/legal/ai-policy/` | `164` | Global, EU | EU AI Act (Regulation 2024/1689), FTC Algorithmic Fairness, E-E-A-T | Active | `v2.0.0` | September 2026 |
| **Intellectual Property Notice** | `/legal/intellectual-property/` | `115` | Global | Berne Convention, Madrid Protocol, US Copyright Office | Active | `v1.5.0` | September 2026 |
| **DMCA Policy & Agent** | `/legal/dmca-policy/` | `116` | US, Global | 17 U.S.C. § 512, Copyright Modernization Act | Active | `v1.6.0` | September 2026 |
| **Compliance & Safety Standards** | `/legal/compliance-and-safety/` | `117` | EU, US, IN, JP | UNECE WP.29, NHTSA, BIS ISI, JIS T 8133 | Active | `v2.0.0` | September 2026 |
| **Legal Contact & DPO Route** | `/legal/legal-contact/` | `120` | Global | GDPR Art. 37, India DPDP Consent Management | Active | `v1.8.0` | September 2026 |

---

## 2. Policy Governance & Review Schedule

1. **Quarterly Audit Cycle**:
   - Legal compliance audits are executed quarterly to incorporate emerging helmet safety standards (e.g., UNECE amendments, SNELL updates) and privacy jurisprudence (e.g., CPRA enforcement updates, DPDP rules).
2. **Automated Synchronization**:
   - Source code files reside in Git (`HelmetsanWeb/legal/*.html`).
   - Synchronization is executed via `scripts/sync_legal_pages.php` to push clean, sanitised, and validated HTML to production WordPress instances.
3. **Data Protection Officer & Legal Contact**:
   - **Operating Entity**: Ash Digital Services / Ashish Digital Services
   - **Principal Officer / Grievance Officer**: Ashish Dungdung
   - **Jurisdiction**: Rourkela, Sundargarh District, Odisha 769004, India
   - **Legal Inquiries**: `legal@helmetsan.com`
   - **Privacy & DPO Inquiries**: `privacy@helmetsan.com`
   - **Affiliate & Commercial**: `compliance@helmetsan.com`

---

## 3. Versioning Changelog Ledger

### Release `2026-09-07` — Comprehensive Legal Infrastructure Overhaul
- **AI Policy (`v2.0.0`)**: Complete ground-up rewrite under the EU AI Act (Regulation 2024/1689), establishing clear demarcations between automated specification parsing and human crash-test validation. Included anti-bias guarantees forbidding commercial payout influence on safety rankings.
- **Affiliate Disclosure (`v2.6.0`)**: Expanded FTC 16 CFR Part 255 disclosures; detailed explicit tracking mechanics, cookie lifespans, zero consumer cost pledges, and comprehensive lists of authorized retail networks (Amazon, RevZilla, Motoin, FC-Moto, Chromeburner, Champion Helmets).
- **Safety Disclaimer (`v2.5.0`)**: Replaced 278-byte placeholder with an exhaustive life-safety advisory detailing the physical limitations of motorcycle helmets, the 40% retention efficiency drop of improperly sized helmets, multi-jurisdictional legal validity (ECE vs. DOT vs. SNELL vs. ISI), and the mandatory 5-year replacement interval.
- **Cookie Policy (`v2.3.0`)**: Detailed technical audit of all active cookies (Cloudflare edge security, Polylang language state, Google Analytics 4, AdSense, affiliate attribution tags). Added Global Privacy Control (GPC) protocol recognition.
- **Terms of Use (`v2.8.0`)**: Expanded from basic template into an enterprise-grade terms document with robust database copyright protection, anti-scraping provisions, UGC licensing terms, limitation of liability capped at $100, and dispute resolution centered in Rourkela, Odisha.
- **Privacy Policy (`v3.0.0`)**: Comprehensive global framework harmonized across GDPR, CCPA/CPRA, and India's DPDP Act 2023, specifying exact data retention schedules, third-party data flows, and automated rights request procedures.
- **Legal Hub (`v2.4.0`)**: Unified directory featuring structured categorization, emergency contact avenues, and cross-navigational indices.
