# Multi-Swarm Translation Architecture for Helmetsan

Yes. A multi-model swarm can be substantially faster and more accurate than the current sequential single-model process—but only if the models are assigned specialized roles and coordinated through deterministic validation.

The recommended design is **not** “ask every model to translate everything and choose randomly.” That increases cost, latency, and inconsistency. Instead, use:

1. **Parallel specialist drafting**
2. **Terminology and structure locks**
3. **A dedicated editorial/polishing pass**
4. **Independent verification**
5. **Human review only for high-risk or low-confidence records**

For Helmetsan’s current scope:

- Base language: `en`
- Target locales: `de`, `zh`, `fr`, `es`, `it`, `pl`, `pt`, `nl`, `ja`
- Product inventory:
  - 2,219 helmets
  - 1,642 variants
  - 3,247 motorcycles
- CMS: WordPress + Polylang
- Existing worker: `metal_translation_bot.py`

---

# 1. Recommended Swarm Topology

## 1.1 High-level topology

```text
                    ┌─────────────────────────┐
                    │ English Source Record   │
                    │ Product / Variant / Bike │
                    └────────────┬────────────┘
                                 │
                       ┌─────────▼─────────┐
                       │ Canonicalization  │
                       │ TM + Glossary Lock │
                       │ Structure Parser   │
                       └─────────┬─────────┘
                                 │
       ┌─────────────────────────┼─────────────────────────┐
       │                         │                         │
┌──────▼──────┐           ┌──────▼──────┐           ┌──────▼──────┐
│ Spec Swarm  │           │ Editorial   │           │ SEO Swarm   │
│ Deterministic│          │ Translation │           │ Metadata    │
│ Fields       │           │ + Local Tone│           │ + Slugs     │
└──────┬──────┘           └──────┬──────┘           └──────┬──────┘
       │                         │                         │
       └─────────────────────────┼─────────────────────────┘
                                 │
                       ┌─────────▼─────────┐
                       │ Language Reviewer │
                       │ Model-specific QA │
                       └─────────┬─────────┘
                                 │
                       ┌─────────▼─────────┐
                       │ Cross-field       │
                       │ Consistency QA    │
                       └─────────┬─────────┘
                                 │
                 ┌───────────────┴────────────────┐
                 │                                │
          Auto-publish                       Human review
          High confidence                    Low confidence
```

Each product should be decomposed into independent content layers:

```text
Product
├── title
├── short description
├── long description
├── specification table
├── safety / homologation statements
├── sizing information
├── fitment / motorcycle compatibility
├── materials and features
├── review content
├── SEO title
├── SEO description
├── slug
├── image alt text
└── taxonomy / attributes
```

Do not send the entire WordPress post as one uncontrolled prompt. Split it into typed fields, translate each field according to its risk class, then reassemble and validate it.

---

# 2. Swarm Role Matrix

## 2.1 Model responsibilities

| Model | Primary responsibility | Best use |
|---|---|---|
| `gpt-5.6-luna` | Final editorial authority | Brand voice, nuanced prose, cultural adaptation, final polish, HTML/Markdown preservation |
| `deepseek-v4-flash` | High-throughput drafting | First-pass translation, repetitive fields, bulk descriptions, structured output |
| `moonshotai/kimi-k3` | CJK specialist | Chinese/Japanese translation, automotive terminology, long context, terminology consistency |
| `deepseek-ai/deepseek-r1` | Safety and standards verifier | Homologation checks, contradiction detection, standards reasoning, ambiguity analysis |
| `meta/llama-3.3-70b-instruct` | Multilingual fallback and independent reviewer | Drafting, review, language comparison, provider failover |
| `meta/llama-3.2-11b-vision-instruct` | Visual auditor | Image text, visual attributes, color/shape checks, image alt text support |

Important: `deepseek-r1` should not be used as the primary prose translator for every field. It is more valuable as a **verification and reasoning service**. Its internal reasoning should remain private; expose only structured verification results such as:

```json
{
  "status": "warning",
  "issues": [
    {
      "type": "standards_mismatch",
      "source_value": "ECE 22.06",
      "translated_value": "ECE 22.05",
      "severity": "critical"
    }
  ],
  "confidence": 0.98
}
```

---

## 2.2 Language-family allocation

### Germanic languages

- `de` German
- `nl` Dutch

Primary:

- `deepseek-v4-flash` for first draft
- `llama-3.3-70b-instruct` for independent comparison
- `gpt-5.6-luna` for final editorial pass

### Romance languages

- `fr` French
- `es` Spanish
- `it` Italian
- `pt` Portuguese

Primary:

- `deepseek-v4-flash` for high-throughput draft
- `llama-3.3-70b-instruct` for alternative draft or verification
- `gpt-5.6-luna` for final brand and regional polish

Portuguese should include an explicit locale policy:

```text
pt-BR or pt-PT?
```

If the site targets Europe, use `pt-PT`; if Brazil is a major market, use `pt-BR`. Do not use a generic Portuguese translation without declaring the locale.

### Slavic language

- `pl` Polish

Primary:

- `llama-3.3-70b-instruct` or `deepseek-v4-flash`
- `gpt-5.6-luna` final review
- additional morphology and terminology checks

Polish requires special attention to:

- grammatical gender
- inflected technical nouns
- case agreement
- motorcycle model names remaining unchanged

### CJK languages

- `zh` Chinese
- `ja` Japanese

Primary:

- `moonshotai/kimi-k3`
- `gpt-5.6-luna` final editorial review
- `llama-3.3-70b-instruct` as fallback
- `deepseek-r1` for safety-standard verification

For Chinese, define whether the target is:

- Simplified Chinese: `zh-CN`
- Traditional Chinese: `zh-TW` or `zh-HK`

The current `zh` locale should be replaced by a specific policy internally, even if Polylang continues to use `zh`.

For Japanese, use an automotive-native style guide. Product copy should not read like literal English translation.

---

# 3. Content-Layer Mapping

## 3.1 Product titles

Recommended path:

```text
deepseek-v4-flash draft
        ↓
language-specific reviewer
        ↓
gpt-5.6-luna final title
```

Rules:

- Do not translate brand names.
- Do not translate model names.
- Preserve certification names exactly.
- Avoid adding marketing claims not present in English.
- Keep title length within SEO limits.
- Do not convert technical model codes into localized words.

Example locked tokens:

```text
SHOEI
Arai
AGV
HJC
ECE 22.06
ECE 22.05
DOT FMVSS No. 218
FIM FRHPhe-02
Snell M2020D
MIPS
Pinlock
D-ring
Double-D
```

---

## 3.2 Specification tables

Primary model:

- `deepseek-v4-flash`

Verification:

- deterministic code validation
- `deepseek-r1` only when a field is ambiguous or safety-related

Specifications should generally be translated using a controlled terminology dictionary rather than creative prose generation.

Example:

```json
{
  "source_key": "shell_material",
  "source_value": "Carbon fiber",
  "de": "Carbonfaser",
  "fr": "Fibre de carbone",
  "es": "Fibra de carbono",
  "it": "Fibra di carbonio",
  "pl": "Włókno węglowe",
  "pt": "Fibra de carbono",
  "nl": "Koolstofvezel",
  "ja": "カーボンファイバー",
  "zh": "碳纤维"
}
```

The system should prefer this locked translation over a model-generated alternative.

---

## 3.3 Safety and homologation content

Recommended pipeline:

```text
Source extraction
      ↓
Terminology lock
      ↓
Draft translation
      ↓
R1 verification
      ↓
Luna editorial pass
      ↓
Automated standards validator
```

Never allow the translation model to:

- upgrade a certification
- downgrade a certification
- infer compliance
- convert “tested to” into “certified”
- convert “compatible with” into “approved for”
- alter a standard number
- alter a revision number
- invent regulatory claims

For example, these are materially different:

```text
Meets ECE 22.06
Tested according to ECE 22.06
Designed for use with ECE 22.06 products
Compatible with ECE 22.06 visors
```

They must remain distinct in every language.

---

## 3.4 Reviews and editorial copy

Primary:

- `gpt-5.6-luna`

Draft assist:

- `deepseek-v4-flash`
- `llama-3.3-70b-instruct`

Reviews should use a more natural translation strategy than specification fields:

- retain factual claims
- preserve reviewer sentiment
- preserve uncertainty
- preserve first-person perspective
- avoid culturally awkward literal phrases
- avoid adding local claims or legal advice

Recommended prompt metadata:

```json
{
  "content_type": "review",
  "style": "expert_motorcycle_editorial",
  "preserve_sentiment": true,
  "preserve_claim_strength": true,
  "do_not_invent_facts": true
}
```

---

## 3.5 Sizing information

Sizing is high-risk because incorrect localization can lead to product misuse.

Pipeline:

```text
Structured extraction
      ↓
Unit and measurement validation
      ↓
Translation
      ↓
Cross-language table comparison
      ↓
Human review for anomalies
```

Do not allow automatic conversion between:

- centimeters and inches
- EU, UK, and US sizes
- helmet shell sizes
- head circumference ranges

unless the conversion is explicitly specified by the source data.

---

## 3.6 SEO titles, descriptions, and slugs

Primary:

- `gpt-5.6-luna`

Draft:

- `deepseek-v4-flash`

SEO validation should check:

- character length
- keyword presence
- naturalness
- no keyword stuffing
- no untranslated English fragments unless intentional
- unique slug
- no collision with an existing Polylang translation
- brand/model preservation

SEO fields should not be translated word-for-word if this produces unnatural search language. They should be localized while remaining factually equivalent.

---

## 3.7 Image alt text and visual fields

Primary:

- `llama-3.2-11b-vision-instruct`

Final wording:

- `gpt-5.6-luna`

The vision model may identify:

- helmet color
- visor color
- visible graphics
- whether a product is shown from front/side/rear
- whether an image contains text

It should not determine technical certification, material composition, or safety claims solely from an image.

---

# 4. Three-Tier Quality Pipeline

## Tier 1: Drafting

Purpose: maximize throughput.

- Fan out all eligible fields.
- Use `deepseek-v4-flash` for repetitive text.
- Use `kimi-k3` for Chinese and Japanese.
- Use `llama-3.3-70b-instruct` as a fallback or second draft.
- Apply glossary and protected-token constraints before generation.

Output:

```json
{
  "text": "...",
  "model": "deepseek-v4-flash",
  "glossary_violations": [],
  "protected_token_violations": [],
  "confidence": 0.87
}
```

## Tier 2: Polishing

Purpose: make the translation publication-ready.

- `gpt-5.6-luna` reviews the draft against the English source.
- It preserves structure and factual strength.
- It corrects unnatural phrasing.
- It applies language-specific style rules.
- It does not alter locked values.

## Tier 3: Verification

Purpose: prevent silent factual errors.

Verification should include:

1. Source-to-target numeric comparison
2. Unit comparison
3. Certification and standard comparison
4. Brand/model preservation
5. HTML/Markdown integrity
6. Table row and column preservation
7. Required-field validation
8. Terminology compliance
9. Translation-memory consistency
10. Duplicate or near-duplicate detection
11. SEO length validation
12. Slug collision detection

A record should only be automatically published if:

```text
critical_errors == 0
protected_token_errors == 0
numeric_errors == 0
glossary_errors == 0
confidence >= threshold
```

Suggested confidence thresholds:

| Content | Auto-publish threshold |
|---|---:|
| Simple attributes | 0.90 |
| Product descriptions | 0.93 |
| Sizing tables | 0.97 |
| Homologation/safety statements | 0.98 |
| Legal or regulatory text | Human approval |

---

# 5. Translation Memory and Glossary System

## 5.1 Translation memory hierarchy

Use the following precedence:

```text
1. Human-approved translation
2. Existing published translation
3. Approved translation-memory segment
4. Locked glossary entry
5. Model-generated translation
```

A TM segment should include:

```json
{
  "source_hash": "sha256...",
  "source_text": "Removable interior",
  "locale": "de",
  "target_text": "Herausnehmbares Innenfutter",
  "content_type": "specification",
  "approved": true,
  "version": 3,
  "created_at": "2026-...",
  "last_reviewed_at": "2026-..."
}
```

The source hash should be normalized so trivial whitespace changes do not invalidate useful memory.

---

## 5.2 Glossary classes

### Protected tokens

Must remain unchanged:

```text
brand names
model names
part numbers
SKU values
EAN/GTIN values
ECE 22.06
FIM FRHPhe-02
Snell M2020D
DOT FMVSS No. 218
MIPS
Pinlock
Bluetooth product names
```

### Controlled translations

Must use approved language-specific mappings:

```text
shell
visor
chin strap
double-D ring
ventilation
removable liner
intercom-ready
head circumference
```

### Non-translatable technical codes

Use regex protection:

```regex
\b[A-Z]{2,}(?:[- ][A-Za-z0-9.]+)*\b
\b\d{2}\.\d{2}\b
\b[A-Z0-9]+-[A-Z0-9-]+\b
```

Regex protection must be reviewed carefully. Overly broad regular expressions can accidentally protect ordinary words.

---

## 5.3 Glossary enforcement

The orchestrator should:

1. Extract protected tokens from English.
2. Replace them with placeholders.
3. Translate the remaining text.
4. Restore protected tokens.
5. Verify all source tokens exist in the output.
6. Reject any record that changes locked values.

Example:

```text
Source:
ECE 22.06 approved shell with MIPS liner

Protected:
__TOKEN_001__ approved shell with __TOKEN_002__ liner

Translation:
__TOKEN_001__ zugelassene Schale mit __TOKEN_002__ Innenfutter
```

---

# 6. Throughput and Speed Optimization

## 6.1 Fan-out strategy

For each product:

```text
Product 001
├── de: title, description, specs, SEO
├── zh: title, description, specs, SEO
├── fr: title, description, specs, SEO
├── es: title, description, specs, SEO
├── it: title, description, specs, SEO
├── pl: title, description, specs, SEO
├── pt: title, description, specs, SEO
├── nl: title, description, specs, SEO
└── ja: title, description, specs, SEO
```

Do not process:

```text
product 1, all fields, all languages, serially
```

Instead process:

```text
product 1:
  all low-risk fields and locales concurrently
product 2:
  all low-risk fields and locales concurrently
...
```

Use bounded concurrency. Unlimited concurrency will cause:

- provider throttling
- WordPress contention
- memory pressure
- difficult retries
- increased failure rates

---

## 6.2 Recommended execution pools

Separate pools are preferable:

```text
POOL_DRAFT:
  deepseek-v4-flash
  llama-3.3-70b-instruct
  kimi-k3

POOL_EDITORIAL:
  gpt-5.6-luna

POOL_VERIFICATION:
  deepseek-r1
  local deterministic