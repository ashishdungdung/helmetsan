# Helmetsan Human-Grade Editorial Guidelines & Content Engineering Spec

> **Version:** 2.0.0 (Masterwork Edition)  
> **Purpose:** Canonical style guide, technical lexicon, system prompt templates, anti-AI-slop rules, and JSON-LD schema guidelines for all content generated across Helmetsan (Helmets, Accessories, Brands, Safety Standards, SEO Metadata, FAQ Schemas, and Buying Guides).

---

## 🎯 Core Philosophy: Dual Content Discipline

Helmetsan content is split into two distinct tiers:

| Content Tier | Scope | Style & Length | Rule |
| :--- | :--- | :--- | :--- |
| **Tier A: Technical Data** | Weight (g), ECE 22.06 rating, EAN/ASIN, price, dimensions, shell count | Zero narrative filler; pure structured key-value data | Minimalist, exact, zero fluff |
| **Tier B: Product Narrative & SEO** | SEO Meta Descriptions, Editorial Excerpts, Review Narratives, Fit Notes, Brand Ethos | Expert gear journalist voice, grounded in rider use-cases | Natural cadence, concrete specs, zero AI clichés |

---

## 🛡️ The 5 Anti-AI Content Pillars

### Pillar I: Concrete Spec Grounding (No Floating Claims)
Every narrative section **must** include at least 2 verifiable physical specs (e.g. *1,350g Tri-Matrix composite shell*, *Pinlock 120 anti-fog lens*, *intermediate-oval fit*, *Emergency Quick Release cheek pads*).

### Pillar II: Natural Rhythm & Active Voice
* **Vary sentence length**: Alternate 6-to-8 word punchy statements with 14-to-18 word technical explanations.
* **Active voice**: Write *"High-velocity chin vents channel air into EPS grooves"* instead of *"Air is channeled through the vents for a cooling effect."*

### Pillar III: Zero Banned AI Words & Structural Tropes (v2 Lexicon)

#### Banned Words Lexicon
* ❌ `ultimate`
* ❌ `game-changer` / `game changer`
* ❌ `unrivaled` / `unmatched`
* ❌ `elevate your ride` / `elevate`
* ❌ `revolutionize`
* ❌ `testament to`
* ❌ `boasts` / `boasting`
* ❌ `seamless` / `seamlessly`
* ❌ `delve`
* ❌ `tapestry` / `realm` / `beacon`
* ❌ `epitome` / `pinnacle` / `masterpiece`
* ❌ `cutting-edge` / `uncompromising`
* ❌ `nestled` / `paradigm`

#### Banned Sentence Patterns
* ❌ *"Whether you're hitting the track or commuting..."*
* ❌ *"Look no further than..."*
* ❌ *"Designed with X in mind..."*
* ❌ *"A perfect blend of..."*
* ❌ *"Offering a seamless balance between..."*
* ❌ *"In a world where safety meets style..."*
* ❌ *"At the heart of..."*
* ❌ *"It's no secret that..."*
* ❌ *"When it comes to..."*
* ❌ *"Setting a new standard for..."*
* ❌ *"Crafted for those who demand..."*

### Pillar IV: Authentic Rider Persona Grounding
Map content directly to the target riding environment:
* **Track / Race**: Aerodynamic drag reduction, Snell M2020 / FIM certification, Double D-Ring closure, Tear-off posts, 2D race shield.
* **Adventure / Dual-Sport**: Peak visor drag reduction, goggle strap channels, high-airflow chin venturi ports, neck brace compatibility.
* **Urban / Commuter**: Drop-down sun visor (SV), Pinlock 120 anti-fog lens, Bluetooth speaker recess pockets, eyewear arms channel.
* **Cruiser / Retro**: Low-profile DOT shell, genuine leather liner, vintage aesthetic, Double D-Ring, goggle strap retaining loop.

### Pillar V: Aero-Acoustic & Decibel Rating Precision
* Specify decibel noise levels where applicable (e.g. *84dB at 100km/h* behind a standard windscreen).
* Detail neck roll acoustic seals, chin curtain draft blocking, and visor gasket pressure seating.

---

## 📐 Anatomical Head Shape & Sizing Fit Standards

| Head Shape | Crown & Temple Geometry | Example Helmets | Fit Copy Guideline |
| :--- | :--- | :--- | :--- |
| **Intermediate Oval** | Standard US/EU 75% market fit; balanced front-to-back and side-to-side crown clearance | Shoei RF-1400, Bell Race Star, HJC RPHA 11 Pro | Highlight universal forehead comfort and standard cheek pad pressure. |
| **Round Oval** | Shorter front-to-back, wider temple clearance; prevents side-of-head pinching | Arai Quantum-X, HJC i90 | Highlight temple pressure relief for rounder head shapes. |
| **Long Oval** | Longer front-to-back, narrower temple clearance; prevents forehead hot spots | Arai Signet-X, Icon Airflite | Highlight forehead hot-spot elimination for long-head profiles. |

* **Cheek Pad Break-In Rule**: Note that 3D polyurethane cheek pads experience a 15–20 riding hour break-in period, relaxing by ~20% for tailored facial contouring.

---

## 🛡️ Safety Certification Reference Matrix

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 🛡️ HELMETSAN SAFETY CERTIFICATION REFERENCE MATRIX                          │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. ECE 22.06 (Global/European Standard)                                     │
│    - Impact speeds: 6.0 m/s & 8.2 m/s against flat & kerbstone anvils.      │
│    - Oblique Rotational Test: 45-degree angled impact at 8.0 m/s.           │
│    - Visor Steel Pellet Test: 60 m/s steel ball impact resistance.          │
├─────────────────────────────────────────────────────────────────────────────┤
│ 2. DOT FMVSS 218 (US Federal Minimum)                                       │
│    - Attenuation: Peak acceleration limit 400G (150G dwell limit).           │
│    - Penetration: Striker drop test on shell.                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ 3. SNELL M2020D / M2020R (Track Standard)                                    │
│    - Double impact drop on hemispherical anvil. Peak limit <275G.           │
├─────────────────────────────────────────────────────────────────────────────┤
│ 4. FIM FRHPhe-01 (MotoGP / WorldSBK Mandatory)                               │
│    - High-velocity oblique rotational acceleration & fracture testing.       │
├─────────────────────────────────────────────────────────────────────────────┤
│ 5. SHARP UK (1 to 5 Star Rating)                                            │
│    - 32 linear and oblique impact tests measuring site-specific brain risk. │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 📝 Canonical System Prompts (For Local LLMs & IDE LLMs)

### Prompt 1: Helmet SEO Meta Description (150–160 chars)
```text
System Prompt: You are a senior motorcycle gear editor writing natural SEO meta descriptions for Helmetsan.

Rule: 150-160 characters. Active voice. Must state: (1) Model Name & Type, (2) Shell Material or Weight, (3) Safety Certification or Fit. Zero marketing fluff.

Input: Title: Shoei RF-1400 | Weight: 1450g | Certification: ECE 22.06 | Fit: Intermediate Oval

Output Formula: "The Shoei RF-1400 full-face helmet combines a 1,450g AIM+ composite shell with ECE 22.06 safety. Features an intermediate-oval fit and quiet wind-tunnel aerodynamics."
```

### Prompt 2: Helmet 4-Part Review Narrative
```text
System Prompt: Write a 4-part commercial review story for a motorcycle helmet.

Formulas:
1. DESIGN & SHELL MATRIX: Detail shell material (e.g. AIM+ multi-ply fiberglass), aerodynamic wind-tunnel testing, drag mitigation at 140+ km/h.
2. SAFETY & IMPACT: State ECE 22.06 / DOT rating, multi-density EPS liner architecture, MIPS/rotational safety, EQRS cheek pads.
3. AIRFLOW & ACOUSTICS: Detail venturi exhaust ports, chin bar intakes, sound dB isolation (e.g. 85dB at 100km/h).
4. ERGONOMICS & FIT: State head shape (Intermediate Oval), cheek pad break-in notes, eyewear channel, liner moisture wicking.

Zero fluff words. Must contain >= 2 technical engineering terms per section.
```

### Prompt 3: Schema.org `FAQPage` JSON-LD Generation
```text
System Prompt: Generate Schema.org FAQPage JSON-LD array containing 3 technical Q&As for a helmet post.

Questions:
1. Is the [Model] ECE 22.06 / DOT certified?
2. What head shape does the [Model] fit best?
3. Does the [Model] include speaker cutouts for intercoms?

Format as valid JSON array of { "question": "...", "answer": "..." }.
```

### Prompt 4: Accessory Guided Installation & Specs
```text
System Prompt: Write technical accessory copy with step-by-step installation instructions.

Rules: State exact compatible helmet models, technical function (e.g. Pinlock 120 fog prevention), and 3 numbered installation steps referencing physical parts (visor posts, ratchet plate, silicone seal).
```

### Prompt 5: Brand Engineering Heritage
```text
System Prompt: Write a 3-part brand history focusing on engineering ethos and safety innovation.

Formulas:
Part 1: Factory Origins (founding year, country, factory headquarters e.g. Omiya, Japan).
Part 2: Safety Innovations (composite shell laying, EPS density zones, race sponsorship feedback loop e.g. MotoGP).
Part 3: Quality Control Standards (shell penetration testing, warranty backing).
```

---

## 🔍 Before vs. After Editorial Examples

### Example 1: Helmet SEO Meta Description
* ❌ **BAD (Generic AI Slop)**:  
  > *"Experience the ultimate protection with the Bell Star DLX. Designed to elevate your ride with unmatched comfort and state-of-the-art safety features."*
* ✅ **GOOD (Helmetsan Spec-Grounded)**:  
  > *"The Bell Star DLX MIPS delivers track-level aerodynamic stability in a 1,500g Tri-Matrix shell. Features ECE 22.06 certification and Panovision Class 1 optics."*

### Example 2: Helmet Editorial Story Narrative
* ❌ **BAD (Generic AI Slop)**:  
  > *"Whether you're hitting the track or commuting to work, the Shoei RF-1400 is a game-changer. It boasts a seamless balance of style and protection that is second to none."*
* ✅ **GOOD (Helmetsan Spec-Grounded)**:  
  > *"Engineered in Shoei's Ibaraki wind tunnel, the RF-1400 uses an AIM+ multi-ply composite shell weighing 1,450g. Four shell sizes ensure a compact profile across XS-XXL, while multi-density EPS channels dissipate impact forces under ECE 22.06 testing."*

### Example 3: Accessory Installation Guide
* ❌ **BAD (Generic AI Slop)**:  
  > *"Installing your new Pinlock lens is super easy! Just pop it on your shield and elevate your riding experience with clear vision."*
* ✅ **GOOD (Helmetsan Spec-Grounded)**:  
  > *"1. Remove visor from helmet ratchet plates and flex flat on a clean surface. 2. Align Pinlock 120 silicone seal against inner visor face between locator pins. 3. Release visor tension to trap dry air pocket and verify airtight seal along perimeter."*

---

## 🛡️ Automated Validation & Quality Assurance Checklist

Before committing generated content to the Helmetsan repository, automated Tier 2 verification enforces:
- [ ] **Character Count**: SEO Meta Description strictly between 140 and 160 characters.
- [ ] **Technical Noun Density**: At least 2 technical engineering terms per paragraph.
- [ ] **Anti-Fluff & Trope Scan**: 0 banned words or generic AI sentence opening patterns.
- [ ] **EAN-13 Barcode Checksum**: Modulo-10 checksum validation on 13-digit EANs.
- [ ] **ASIN Format**: Strictly 10 alphanumeric characters (`^[A-Z0-9]{10}$`).
- [ ] **Fact Grounding**: All weight, safety rating, and fit shape values match structured JSON specs.
