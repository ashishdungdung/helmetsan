# Helmetsan Master Implementation Plan  
## AI-Generated, Verified, and Managed Helmet Image Galleries

## 0. Executive Decision

Helmetsan should implement a **five-shot image coverage system** for every helmet, managed as structured media attached directly to each helmet record.

The recommended architecture is:

1. **FLUX.1-dev through NVIDIA NIM** for high-quality final image generation.
2. **FLUX.1-schnell through NVIDIA NIM** for low-cost previews and prompt experimentation.
3. **Stable Diffusion 3.5 Large** as the secondary generation fallback.
4. **Kimi-K3** for multimodal image inspection, OCR, long-context reasoning, defect detection, and catalog-quality auditing.
5. **Llama 3.2 90B Vision** as a secondary visual verification model.
6. **WordPress Helmetsan Core** as the product/image source-of-truth.
7. **HelmetsanManager Mission Control** as the operational queue, GPU, audit, and batch-generation interface.
8. **Cloudflare R2** as the durable image object store, with WordPress media synchronization and CDN delivery.

The image system must distinguish clearly between:

- **AI-generated marketing imagery**
- **Uploaded manufacturer photography**
- **Technical/product reference imagery**
- **Verified catalog imagery**
- **Pending or rejected imagery**

AI-generated images should not automatically be treated as authoritative technical documentation. Any detail related to certification, fastening mechanism, vents, safety construction, shell material, or included accessories must be checked against manufacturer data or verified product photographs.

---

# 1. Model Selection and Technical Deliberation

## 1.1 Kimi-K3: What It Does and Does Not Do

Kimi-K3 should be treated as a **multimodal understanding, reasoning, OCR, and long-context verification model**.

It can be used to:

- Inspect generated helmet images.
- Compare images against helmet reference photographs.
- Read certification markings and labels using OCR.
- Check whether a particular shot contains the requested visual elements.
- Identify distorted geometry.
- Detect missing or hallucinated vents.
- Inspect visor alignment.
- Check shell proportions.
- Identify duplicated or near-duplicated images.
- Evaluate composition, lighting, and product visibility.
- Produce structured audit results.
- Compare a generation against the canonical five-shot protocol.
- Review multiple images and product specifications together.
- Explain why an image should be rejected.

Kimi-K3 should **not** be used as the primary image-generation model for this workflow. It does not generate final photorealistic helmet images from scratch in the same manner as FLUX or Stable Diffusion.

### Kimi-K3 verification responsibility

Kimi-K3 should return structured results such as:

```json
{
  "decision": "pass",
  "confidence": 0.94,
  "shot_type": "front_hero",
  "checks": {
    "helmet_present": true,
    "correct_orientation": true,
    "visor_alignment": true,
    "shell_proportions": true,
    "extra_vents_detected": false,
    "strap_distortion": false,
    "brand_marking_legible": true,
    "requested_context_present": true
  },
  "warnings": [],
  "rejection_reasons": [],
  "recommended_action": "publish_after_human_review"
}
```

---

## 1.2 Primary Generative Model: FLUX.1-dev

### Selected model

**NVIDIA NIM hosted `black-forest-labs/flux.1-dev`**

This is the recommended final-generation model because it is suitable for:

- High-fidelity studio product photography.
- Carbon-fiber weave rendering.
- Fiberglass and polycarbonate surface differentiation.
- Metallic flake paint.
- Gloss and satin reflections.
- Visor transparency and highlights.
- Softbox lighting.
- Product-oriented camera composition.
- Detailed hard-surface geometry.
- High-resolution hero imagery.

FLUX.1-dev should be used for:

- Final hero images.
- Final lateral and rear views.
- Macro detail shots.
- High-quality lifestyle/context imagery.
- Images intended for catalog publication after verification.

### Important implementation requirement

To maintain product fidelity, FLUX should not be given only a text prompt. It should receive, where available:

- Manufacturer product images.
- Existing official front, side, rear, and interior photographs.
- Helmet colorway references.
- Brand and model reference images.
- Shell-material information.
- Known vent layout.
- Spoiler shape.
- Visor mechanism.
- Retention system type.
- Certification information.
- Graphic/decal reference images.

Use image conditioning, reference-image workflows, ControlNet/IP-Adapter-style controls where supported, or an equivalent NVIDIA NIM-compatible mechanism.

Text-only generation is not sufficient for a catalog where a specific real helmet model must be represented accurately.

---

## 1.3 Preview Model: FLUX.1-schnell

**`flux.1-schnell`** should be used for:

- Prompt testing.
- Initial composition previews.
- Batch experimentation.
- Low-cost variant generation.
- Admin preview generation.
- Determining the best camera angle before spending GPU capacity on FLUX.1-dev.

A recommended workflow is:

1. Generate 2–4 preview variants using FLUX.1-schnell.
2. Select one composition.
3. Generate the final version using FLUX.1-dev.
4. Run Kimi-K3 and Llama Vision verification.
5. Send failed images back for regeneration.

---

## 1.4 Secondary Fallback: Stable Diffusion 3.5 Large

**`stabilityai/stable-diffusion-3.5-large`** should be integrated as a fallback generator.

Use it when:

- FLUX capacity is unavailable.
- NVIDIA NIM is rate-limited.
- A specific prompt performs better on SD3.5.
- A generation must be retried using a different image prior.
- Helmetsan needs a vendor-diversification strategy.

The model used for every image must be recorded in metadata:

```json
{
  "provider": "nvidia_nim",
  "model": "black-forest-labs/flux.1-dev",
  "model_revision": "configured_revision",
  "seed": 18377429,
  "generation_parameters": {
    "width": 1536,
    "height": 1536,
    "steps": 28,
    "guidance_scale": 3.5
  }
}
```

---

## 1.5 Llama 3.2 90B Vision Verification

Llama 3.2 90B Vision should operate as a secondary visual reviewer.

It can:

- Confirm whether the shot matches its assigned shot type.
- Verify broad geometry and orientation.
- Check for visible product defects.
- Detect missing visual context.
- Compare image and textual product specifications.
- Provide a second opinion when Kimi-K3 is uncertain.
- Detect image duplication or excessive similarity across the five shots.

### Recommended verification policy

| Result | Action |
|---|---|
| Kimi pass, Llama pass | Eligible for publication |
| Kimi pass, Llama uncertain | Human review |
| Kimi uncertain, Llama pass | Human review |
| Either model rejects | Regenerate or manually review |
| Both reject | Reject generation |
| Technical contradiction detected | Block automatic publication |

The verification models should never be allowed to “approve” a physically incorrect product merely because the image is attractive.

---

# 2. Canonical Five-Shot Helmet Photography Protocol

Every helmet must have five required shot types. A helmet may have additional images, but these five define minimum catalog coverage.

## 2.1 Shot 1 — 3/4 Front Isometric Hero

### Purpose

Primary commercial image for:

- Product-page hero carousel.
- Category cards.
- Search results.
- Social sharing.
- Structured product preview.

### Required composition

- Three-quarter front isometric view.
- Helmet facing approximately 20–35 degrees away from camera.
- Visor slightly cracked/open where the actual model supports this.
- Pinlock pins visible where applicable.
- Intake vents open where the actual model has functional vents.
- Three-point softbox studio lighting.
- Neutral studio background.
- Entire helmet in frame.
- No rider unless specifically requested.
- No invented accessories.

### Prompt template

```text
Create a photorealistic commercial studio product photograph of the exact
[BRAND] [MODEL] motorcycle helmet.

Shot type: 3/4 front isometric hero view.
Camera angle: approximately 30 degrees from front, slightly above helmet centerline.
Product orientation: front and [LEFT/RIGHT] side visible.
Helmet construction: [CARBON/FIBERGLASS/POLYCARBONATE].
Colorway and graphics: [EXACT COLORWAY AND GRAPHIC DESCRIPTION].
Shell finish: [GLOSS/SATIN/MATTE/CARBON WEAVE/METALLIC FLAKE].
Visor: [CLOSED / SLIGHTLY CRACKED / OPEN], matching the real product.
Pinlock hardware: [VISIBLE / NOT PRESENT / NOT APPLICABLE].
Vent configuration: show only the verified [LIST OF INTAKE VENTS] in the correct locations.
Certification: [ECE 22.06 / DOT / FIM / OTHER], do not invent markings.

Lighting: three-point softbox studio lighting, controlled reflections,
soft key light, subtle fill, clean rim light, realistic visor reflections.
Background: neutral [WHITE/LIGHT GREY/DARK GREY] seamless studio background.
Lens: 85mm product photography lens, realistic perspective, f/8,
high detail, accurate hard-surface geometry.

Do not add extra vents, spoilers, logos, graphics, buttons, seams, straps,
visor mechanisms, or accessories not present in the reference images.
Do not distort the shell, visor, chin bar, or rear profile.
```

### Negative prompt

```text
extra vents, incorrect shell shape, distorted visor, duplicate helmet,
floating parts, malformed chin strap, extra logos, incorrect graphics,
invented certification labels, melted geometry, deformed visor pins,
unreadable brand logo, excessive fisheye distortion, rider,
hands, watermark, text artifacts
```

---

## 2.2 Shot 2 — Lateral Side Profile

### Purpose

Technical and aerodynamic side reference.

### Required composition

- Exact lateral side profile.
- Camera perpendicular to helmet side.
- Aerodynamic spoiler contour visible.
- Visor pivot baseplate visible.
- Cheek-pad recess or lower shell shape visible.
- Certification sticker location visible only if verified.
- Minimal perspective distortion.

### Prompt template

```text
Create a photorealistic technical product photograph of the exact
[BRAND] [MODEL] motorcycle helmet.

Shot type: strict lateral side profile.
Camera: perpendicular to the helmet side, centered on the shell,
minimal perspective distortion, no three-quarter angle.
Visible side: [LEFT/RIGHT].
Shell material: [CARBON/FIBERGLASS/POLYCARBONATE].
Colorway: [EXACT COLORWAY].
Graphic layout: [DESCRIPTION].
Finish: [GLOSS/MATTE/SATIN].
Show the verified aerodynamic spoiler contour, visor pivot baseplate,
cheek-pad recess, lower shell edge, neck opening, and rear profile.

Certification sticker: show at [VERIFIED LOCATION] only if supported by a
reference image. If not sufficiently visible in the reference, omit it rather
than inventing it.

Neutral technical studio lighting, clean background, sharp shell edges,
accurate product scale, realistic reflections, catalog photography.
```

### Negative prompt

```text
three-quarter perspective, rotated helmet, extra spoiler, incorrect pivot,
wrong visor hinge, fake certification label, incorrect shell length,
asymmetrical shell, invented graphics, distorted cheek opening,
extra hardware, text artifacts
```

---

## 2.3 Shot 3 — Rear Exhaust and Diffuser

### Purpose

Show rear aerodynamic and ventilation systems.

### Required composition

- Rear three-quarter or near-rear view.
- Exhaust extractor ports.
- Neck-roll taper.
- Spoiler exit lip.
- Rear diffuser geometry.
- ECE 22.06 badge only if verified from reference material.
- Correct colorway continuation.

### Prompt template

```text
Create a photorealistic rear technical product photograph of the exact
[BRAND] [MODEL] motorcycle helmet.

Shot type: rear exhaust and diffuser view.
Camera: rear three-quarter angle, centered slightly above the rear shell.
Show the verified rear exhaust extractor ports, spoiler exit lip,
rear diffuser geometry, neck-roll taper, lower rear shell, and graphic
continuation from the [COLORWAY] design.

Shell material: [CARBON/FIBERGLASS/POLYCARBONATE].
Finish: [GLOSS/SATIN/MATTE].
Rear ventilation layout: [VERIFIED DESCRIPTION].
Certification badge: include [ECE 22.06 / OTHER] only when supported by
the supplied reference asset; otherwise do not add a badge.

Use controlled studio lighting with a soft rim light to define aerodynamic
surfaces, realistic reflective behavior, sharp product edges, and neutral
background.
```

### Negative prompt

```text
invented rear vents, extra spoiler, incorrect diffuser, fake badge,
fake certification text, random logos, asymmetrical shell,
deformed rear profile, over-dark exhaust ports, melted graphics,
incorrect neck roll, watermark
```

---

## 2.4 Shot 4 — Macro Interior and Retention System

### Purpose

Provide technical evidence of fit, internal construction, and fastening system.

### Required composition

- Helmet opened or positioned to expose interior.
- Emergency quick-release red tabs where present.
- Multi-density EPS channels where visible.
- Titanium Double-D ring or micrometric ratchet, according to actual product.
- Cheek pads and liner.
- Macro detail without inventing internal construction.
- No claims beyond available reference data.

### Prompt template

```text
Create a high-detail macro product photograph of the interior of the exact
[BRAND] [MODEL] motorcycle helmet.

Shot type: macro interior and retention-system detail.
Show the verified interior liner, cheek pads, neck roll, EPS channels where
visible, emergency quick-release system [PRESENT/NOT PRESENT], and the exact
retention system: [TITANIUM DOUBLE-D RING / MICROMETRIC RATCHET / OTHER].

Interior material and color: [DESCRIPTION].
Shell material: [CARBON/FIBERGLASS/POLYCARBONATE].
Retention hardware finish: [DESCRIPTION].
Quick-release tabs: [COLOR AND VERIFIED POSITION].
Use a realistic macro lens, shallow but controlled depth of field,
soft technical lighting, visible material texture, and accurate fastening
hardware.

Do not expose or invent internal safety structures that are not present in
the reference data. Do not substitute a Double-D ring for a micrometric
ratchet, or vice versa.
```

### Negative prompt

```text
wrong retention system, extra straps, missing strap, invented EPS channels,
fake red tabs, distorted cheek pads, melted liner, deformed ring,
incorrect hardware, impossible interior geometry, unreadable labels,
medical imagery, watermark
```

---

## 2.5 Shot 5 — Ergonomic Context / Motorcycle Cockpit Pairing

### Purpose

Demonstrate scale, usage context, and lifestyle positioning.

### Required composition

Either:

- Helmet resting on a sportbike or adventure motorcycle tank, or
- Helmet mounted on a rider in a realistic environment.

The selected context must be stored in metadata.

### Prompt template

```text
Create a photorealistic lifestyle product photograph featuring the exact
[BRAND] [MODEL] motorcycle helmet.

Shot type: ergonomic motorcycle cockpit pairing.
Context: helmet [RESTING ON / MOUNTED ON RIDER AT]
a [SPORTBIKE/ADVENTURE/NAKED/TOURING] motorcycle.

Motorcycle context: [MODEL OR GENERIC CLASS], color [COLOR].
Helmet shell material: [CARBON/FIBERGLASS/POLYCARBONATE].
Helmet colorway and graphics: [EXACT DESCRIPTION].
Helmet position: [ON FUEL TANK / ON MIRROR / ON RIDER HEAD].
Lighting: [GOLDEN HOUR/OVERCAST/CONTROLLED GARAGE/DAYLIGHT].
Rider apparel: neutral motorcycle gear, no competing logos, if rider is used.
Show realistic scale, ergonomic placement, correct visor position,
natural reflections, and realistic contact with the motorcycle surface.

Do not change the helmet shape, colorway, graphics, vents, visor mechanism,
spoiler, or retention system. Do not create fictional brand marks.
```

### Negative prompt

```text
wrong motorcycle scale, helmet floating, rider with malformed anatomy,
extra logos, changed colorway, incorrect visor, distorted shell,
fictional accessories, unsafe riding posture, duplicate helmet,
watermark, text artifacts
```

---

# 3. Product and Image Data Architecture

## 3.1 Core Principles

Each image must be:

- Attached to one helmet.
- Assigned one shot type.
- Versioned.
- Audited.
- Traceable to a generation job or manual upload.
- Independently publishable or rejectable.
- Stored in multiple responsive sizes.
- Linked to its source and verification history.

The image must never exist only as an unstructured WordPress attachment.

---

## 3.2 Helmet Entity

Example relational structure:

```sql
helmets
---------
id
wordpress_post_id
sku
brand
model
model_year
shell_material
colorway
certifications_json
reference_assets_json
image_coverage_status
created_at
updated_at
```

Example JSON representation:

```json
{
  "id": "helmet_000184",
  "wordpress_post_id": 9382,
  "sku": "AGV-PISTA-GP-RR-CARBON-2025",
  "brand": "AGV",
  "model": "Pista GP RR",
  "model_year": 2025,
  "shell_material": "carbon",
  "colorway": "Carbon/Black",
  "certifications": ["ECE 22.06"],
  "image_coverage": {
    "required": 5,
    "approved": 4,
    "missing": ["interior_macro"],
    "status": "incomplete"
  }
}
```

---

## 3.3 Image Entity

Required image fields:

```json
{
  "id": "img_01J8KZ4M9J",
  "helmet_id": "helmet_000184",
  "wordpress_attachment_id": 124883,
  "shot_type": "front_hero",
  "source_type": "ai_generated",
  "url": "https://cdn.helmetsan.com/helmets/000184/front-hero-01.webp",
  "thumbnail_url": "https://cdn.helmetsan.com/helmets/000184/front-hero-01-480.webp",
  "alt_text": "AGV Pista GP RR carbon motorcycle helmet viewed from the front three-quarter angle",
  "caption": "Three-quarter front view of the AGV Pista GP RR carbon helmet.",
  "dimensions": {
    "original": {
      "width": 2048,
      "height": 2048
    },
    "hero": {
      "width": 1920,
      "height": 1920
    },
    "gallery": {
      "width": 1024,
      "height": 1024
    },
    "thumbnail": {
      "width": 480,
      "height": 480
    }
  },
  "format": "webp",
  "mime_type": "image/webp",
  "file_size_bytes": 284991,
  "phash": "a94c20ef18b7c991",
  "sha256": "hexadecimal-sha256",
  "is_primary": true,
  "sort_order": 1,
  "validation_status": "verified",
  "generated_at": "2025-03-08T12:11:54Z",
  "published_at": "2025-03-08T12:18:12Z"
}
```

---

## 3.4 Generation Metadata

```json
{
  "generation": {
    "provider": "nvidia_nim",
    "model": "black-forest-labs/flux.1-dev",
    "seed": 18377429,
    "prompt_version": "helmet-shot-v3",
    "prompt_hash": "sha256:...",
    "negative_prompt_hash": "sha256:...",
    "reference_asset_ids": [
      "ref_883",
      "ref_884",
      "ref_885"
    ],
    "parameters": {
      "width": 1536,
      "height": 1536,
      "steps": 28,
      "guidance_scale": 3.5
    }
  },
  "audit": {
    "kimi_model": "kimi-k3",
    "kimi_status": "pass",
    "kimi_confidence": 0.94,
    "llama_model": "llama-3.2-90b-vision",
    "llama_status": "pass",
    "llama_confidence": 0.91,
    "human_review_required": false
  }
}
```

---

## 3.5 Shot Types

Use stable machine-readable identifiers:

```text
front_hero
side_profile
rear_exhaust
interior_macro
cockpit_context
```

Do not use display labels as database identifiers.

---

## 3.6 Validation Statuses

```text
queued
generating
generated
processing
audit_pending
audit_passed
audit_failed
human_review
approved
published
rejected
archived
```

A recommended publication rule is:

```text
published = true only if:
- image processing completed
- all required dimensions exist
- pHash generated
- Kimi audit passed
- Llama audit passed or human override recorded
- no technical contradiction is unresolved
- license/source status is valid
```

---

# 4. WordPress Product-Page Integration

## 4.1 Product Page Presentation

The helmet product page should display:

### Hero area

- Primary hero image.
- Responsive `srcset`.
- WebP delivery.
- Lazy loading for non-primary images.
- Zoom/lightbox.
- Image caption.
- “AI generated” or “Manufacturer image” badge where appropriate.

### Thumbnail strip

- Five canonical shots in protocol order.
- Additional images after required shots.
- Missing shot indicator only in administrative contexts, not necessarily public-facing.

### Lightbox

- Full-resolution image.
- Shot title.
- Caption.
- Technical image description.
- Optional audit status for internal users.
- Keyboard and mobile support.

### Accessibility

Each image must have:

- Meaningful `alt_text`.
- Captions where useful.
- No alt text such as “image1.jpg”.
- No claims in alt text that are not visually supported.
- Automatic fallback for missing alt text, with editorial review queue.

---

## 4.2 WordPress Storage Strategy

Use a hybrid arrangement:

- WordPress attachment records for CMS compatibility.
- R2 objects for durable storage and CDN delivery.
- Attachment metadata pointing to R2 variants.
- WordPress image URLs rewritten through a Helmetsan CDN layer.

Example attachment metadata:

```php
[
    '_helmetsan_image_id'       => 'img_01J8KZ4M9J',
    '_helmetsan_helmet_id'      => 'helmet_000184',
    '_helmetsan_shot_type'      => 'front_hero',
    '_helmetsan_r2_key'         => 'helmets/000184/front-hero-01.webp',
    '_helmetsan_validation'     => 'verified',
    '_helmetsan_phash'          => 'a94c20ef18b7c991'
]
```

---

# 5. Dedicated Helmetsan Image Manager

## 5.1 WordPress Admin Location

Primary interface:

```text
/wp-admin/admin.php?page=helmetsan-images
```

Alternative implementation:

```text
Helmet Edit Screen
  └── Images tab
```

Both should use the same REST API and service layer.

---

## 5.2 Gallery Overview

The overview table should show:

| Field | Purpose |
|---|---|
| Helmet | Brand, model, SKU |
| Coverage | `5/5 shots complete` |
| Missing shots | Example: `Interior Macro` |
| Primary image | Thumbnail |
| Validation | Passed, failed, review |
| Source | AI, manufacturer, manual upload |
| Last generated | Timestamp |
| Queue status | Idle, queued, running, failed |
| Actions | Manage, generate, audit |

Coverage examples:

```text
5/5 Shots Complete
4/5 Shots Complete
Missing: Rear Exhaust
Audit Required
Generation Failed
```

---

## 5.3 Per-Helmet Image Manager

The per-helmet screen should provide five fixed shot cards.

Each card includes:

- Large preview.
- Shot name.
- Coverage status.
- Validation badge.
- Source badge.
- Generation model.
- Last generation date.
- Thumbnail dimensions.
- pHash.
- Audit summary.
- Action menu.

Actions:

- Generate.
- Regenerate.
- Generate alternate.
- Upload manual replacement.
- Replace current image.
- Set as primary hero.
- Drag-and-drop reorder.
- Edit caption.
- Edit alt text.
- View audit report.
- Compare against reference.
- Delete.
- Archive.
- Publish/unpublish.

---

## 5.4 Bulk Operations

Bulk actions:

- Generate all missing shots.
- Regenerate failed shots.
- Run visual audit on selected images.
- Reprocess all image sizes.
- Sync selected images to R2.
- Recalculate pHash.
- Export audit report.
- Approve selected images.
- Archive old variants.

The bulk generator must display a preflight estimate:

```text
Helmets selected: 500
Missing shots: 2,500
Preview generations: 0
Final generations: 2,500
Estimated GPU credits: ...
Estimated storage: ...
Estimated time: ...
```

---

## 5.5 Visual Quality Audit Badge

Possible statuses:

```text
Verified by Kimi-K3
Verified by Kimi-K3 + Llama Vision
Human Approved
Audit Required
Audit Failed
Technical Conflict
```

The badge should not simply say “AI approved.” It should identify:

- The model used.
- Audit timestamp.
- Confidence.
- Whether a human override exists.
- Whether the image remains suitable only for marketing use.

---

# 6. HelmetsanManager Mission Control

## 6.1 Fleet Image Studio

Mission Control should be the operational interface for large-scale generation.

Main sections:

1. **Catalog coverage dashboard**
2. **Generation queue**
3. **GPU/rate-limit dashboard**
4. **Failed jobs**
5. **Audit queue**
6. **R2 synchronization**
7. **Model configuration**
8. **Prompt-template management**
9. **Cost and usage reporting**
10. **Generation history**

---

## 6.2 Queue Monitor

Each job should show:

```text
Job ID
Helmet
Shot type
Priority
Model
Status
Attempts
Started
Duration
GPU provider
Validation state
Error message
Retry action
```

Statuses:

```text
pending
reserved
running
generated
processing
auditing
awaiting_review
completed
failed
cancelled
```

---

## 6.3 GPU Rate-Limit Management

Implement:

- Maximum concurrent jobs per provider.
- Per-model concurrency.
- Token/API budget limits.
- Retry with exponential backoff.
- Provider health checks.
- Circuit breaker when NIM fails repeatedly.
- Fallback to SD3.5.
- Priority queues for new products versus regeneration.
- Daily and monthly budget limits.

Example configuration:

```yaml
providers:
  nvidia_flux_dev:
    max_concurrency: 4
    requests_per_minute: 20
    timeout_seconds: 300
  nvidia_flux_schnell:
    max_concurrency: 8
    requests_per_minute: 40
  stable_diffusion_35:
    max_concurrency: 4
    requests_per_minute: 20
```

---

# 7. Asynchronous Generation Pipeline

## 7.1 Why PHP Must Not Perform Generation Directly

Generating five images for 500 helmets means 2,500 jobs. This must not execute inside:

- A normal WordPress request.
- An admin page load.
- A synchronous REST request.
- A PHP cron task with a long blocking loop.

The WordPress plugin should enqueue jobs and return immediately.

---

## 7.2 Recommended Pipeline

```text
Helmet record created or updated
        ↓
Image coverage evaluator
        ↓
Generation job created
        ↓
Queue broker
        ↓
Generation worker
        ↓
Raw image object stored
        ↓
Image normalization worker
        ↓
WebP/responsive derivative worker
        ↓
pHash/deduplication worker
        ↓
Kimi-K3 audit
        ↓
Llama Vision audit
        ↓
Human review if needed
        ↓
WordPress attachment creation/update
        ↓
R2 synchronization
        ↓
Catalog publication
```

---

## 7.3 Queue Technology

Preferred choices:

- Redis + worker service.
- RabbitMQ.
- AWS SQS-compatible queue.
- Cloudflare Queues if the surrounding architecture supports it.
- A dedicated Python worker service.

WordPress Action Scheduler may be used for small orchestration tasks, but it should not be the sole GPU job engine for a 2,500-image batch.

---

## 7.4 Job Payload

```json
{
  "job_id": "job_01J8M2X",
  "helmet_id": "helmet_000184",
  "shot_type": "rear_exhaust",
  "priority": 50,
  "model": "black-forest-labs/flux.1-dev",
  "reference_asset_ids": ["ref_883", "ref_884"],
  "prompt_template_version": "helmet-shot-v3",
  "attempt": 1,
  "requested_by": "admin_user_42",
  "idempotency_key": "helmet_000184:rear_exhaust:v3"
}
```

---

## 7.5 Idempotency

Every job must have an idempotency key.

If the same job is submitted again, the system should:

- Return the existing job if it is active.
- Avoid duplicate generation if an approved image already exists.
- Permit explicit regeneration by incrementing the generation version.

---

# 8. Image Processing, Compression, and Delivery

## 8.1 Source Format

Generated images may arrive as PNG, JPEG, or another provider-defined format.

Immediately store the original source as an immutable internal artifact:

```text
original/
  helmet-id/
    image-id/
      source.png
```

Do not overwrite the original when generating derivatives.

---

## 8.2 WebP Derivatives

Generate:

- `1920px` hero.
- `1024px` gallery.
- `480px` thumbnail.

Recommended WebP quality:

```text
Hero: 85
Gallery: 82–85
Thumbnail: 78–82
```

The requested default is **lossy WebP at quality 85**, with an expected size reduction of approximately 50–70%, depending on image content.

Do not guarantee exactly 70% reduction. Product images with reflections and fine carbon weave may compress differently.

Use:

- `libvips` or Sharp for production processing.
- Image orientation normalization.
- Embedded color-profile handling.
- Metadata stripping from public derivatives where appropriate.
- No destructive resizing of the original.

---

## 8.3 Responsive Delivery

Example HTML:

```html
<img
  src="https://cdn.helmetsan.com/helmets/000184/front-hero-1024.webp"
  srcset="
    https://cdn.helmetsan.com/helmets/000184/front-hero-480.webp 480w,
    https://cdn.helmetsan.com/helmets/000184/front-hero-1024.webp 1024w,
    https://cdn.helmetsan.com/helmets/000184/front-hero-1920.webp 1920w
  "
  sizes="(max-width: 768px) 92vw, 720px"
  alt="AGV Pista GP RR carbon motorcycle helmet viewed from the front three-quarter angle"
  loading="eager"
  fetchpriority="high"
/>
```

Only the primary hero should normally use `fetchpriority="high"`.

---

## 8.4 Storage Layout

```text
r2://helmetsan-media/
  helmets/
    {helmet_id}/
      {shot_type}/
        {image_id}/
          original/
            source.png
          1920.webp
          1024.webp
          480.webp
          manifest.json
```

The manifest should contain:

```json
{
  "image_id": "img_01J8KZ4M9J",
  "helmet_id": "helmet_000184",
  "shot_type": "front_hero",
  "sha256": "...",
  "phash": "...",
  "variants": {
    "1920": "front-hero-1920.webp",
    "1024": "front-hero-1024.webp",
    "480": "front-hero-480.webp"
  }
}
```

---

# 9. Deduplication and Integrity

## 9.1 Cryptographic Hash

Use SHA-256 to identify byte-for-byte duplicates.

```text
sha256(source_file)
```

This catches exact duplicates only.

---

## 9.2 Perceptual Hash

Use 64-bit pHash to identify visually similar images.

Store as:

```text
a94c20ef18b7c991
```

Compare using Hamming distance.

Suggested policy:

| Hamming distance | Interpretation |
|---:|---|
| 0–4 | Almost certainly duplicate |
| 5–10 | Very similar; review |
| 11+ | Likely distinct, but not guaranteed |

The threshold should be calibrated with real Helmet images.

The deduplication system must be shot-aware. A front hero and a side profile will naturally differ, but two front hero generations may be almost identical.

---

## 9.3 Duplicate Handling

When a duplicate is detected:

- Do not immediately delete the new image.
- Mark it `duplicate_candidate`.
- Link it to the existing image.
- Allow an administrator to retain the better version.
- Avoid publishing duplicate images into the public gallery.
- Preserve audit history.

---

# 10. REST API Design

## 10.1 Read Endpoints

```text
GET /wp-json/helmetsan/v1/helmets/{helmet_id}/images
GET /wp-json/helmetsan/v1/helmets/{helmet_id}/images/{image_id}
GET /wp-json/helmetsan/v1/helmets/{helmet_id}/coverage
GET /wp-json/helmetsan/v1/images/{image_id}/audit
GET /wp-json/helmetsan/v1/image-jobs/{job_id}
```

---

## 10.2 Mutation Endpoints

```text
POST   /wp-json/helmetsan/v1/helmets/{helmet_id}/images/generate
POST   /wp-json/helmetsan/v1/helmets/{helmet_id}/images/generate-missing
POST   /wp-json/helmetsan/v1/images/{image_id}/regenerate
POST   /wp-json/helmetsan/v1/images/{image_id}/upload-replacement
PATCH  /wp-json/helmetsan/v1/images/{image_id}
POST   /wp-json/helmetsan/v1/images/{image_id}/set-primary
POST   /wp-json/helmetsan/v1/images/{image_id}/audit
POST   /wp-json/helmetsan/v1/images/{image_id}/publish
POST   /wp-json/helmetsan/v1/images/{image_id}/archive
DELETE /wp-json/helmetsan/v1/images/{image_id}
```

---

## 10.3 Bulk Endpoints

```text
POST /wp-json/helmetsan/v1/bulk/generate-missing
POST /wp-json/helmetsan/v1/bulk/audit
POST /wp-json/helmetsan/v1/bulk/reprocess
POST /wp-json/helmetsan/v1/bulk/sync-r2
```

Example request:

```json
{
  "helmet_ids": [
    "helmet_000184",
    "helmet_000185"
  ],
  "shot_types": [
    "front_hero",
    "side_profile",
    "rear_exhaust",
    "interior_macro",
    "cockpit_context"
  ],
  "mode": "final",
  "priority": 40
}
```

---

# 11. Proposed Codebase Changes

## 11.1 WordPress Core Plugin

Path:

```text
HelmetsanWeb/helmetsan-core/
```

Suggested structure:

```text
helmetsan-core/
├── helmetsan-core.php
├── composer.json
├── src/
│   ├── Admin/
│   │   ├── ImageManagerPage.php
│   │   ├── HelmetImageTab.php
│   │   ├── ImageManagerAssets.php
│   │   └── BulkImageActions.php
│   ├── REST/
│   │   ├── ImageController.php
│   │   ├── GenerationJobController.php
│   │   ├── AuditController.php
│   │   └── CoverageController.php
│   ├── Domain/
│   │   ├── HelmetImage.php
│   │   ├── ImageShotType.php
│   │   ├── ImageValidationStatus.php
│   │   └── GenerationJob.php
│   ├── Services/
│   │   ├── HelmetImageService.php
│   │   ├── ImageCoverageService.php
│   │   ├── MediaAttachmentService.php
│   │   ├── ImageProcessingService.php
│   │   ├── PHashService.php
│   │   ├── R2StorageService.php
│   │   ├── PromptTemplateService.php
│   │   └── AuditService.php
│   ├── Queue/
│   │   ├── JobDispatcher.php
│   │   ├── ActionSchedulerAdapter.php
│   │   └── QueueStatusService.php
│   ├── Database/
│   │   ├── Schema.php
│   │   ├── Migrations.php
│   │   └── Repositories/
│   │       ├── HelmetImageRepository.php
│   │       ├── GenerationJobRepository.php
│   │       └── AuditRepository.php
│   ├── CLI/
│   │   └── ImageCommands.php
│   └── Security/
│       ├── CapabilityManager.php
│       ├── NonceValidator.php
│       └── UploadValidator.php
├── assets/
│   ├── js/
│   │   ├── image-manager.js
│   │   ├── gallery-editor.js
│   │   └── queue-monitor.js
│   └── css/
│       └── image-manager.css
├── templates/
│   ├── image-manager-page.php
│   ├── helmet-image-tab.php
│   └── audit-modal.php
└── migrations/
```

---

## 11.2 Database Tables

Recommended custom tables:

```text
wp_helmetsan_images
wp_helmetsan_image_variants
wp_helmetsan_generation_jobs
wp_helmetsan_image_audits
wp_helmetsan_image_references
wp_helmetsan_prompt_templates
```

Do not store all image-generation metadata as an unstructured post meta blob. Post meta may be used for compatibility, but operational data belongs in indexed custom tables.

---

## 11.3 Headless Generator

Required path:

```text
HelmetsanWeb/scripts/generate_helmet_gallery.py
```

Suggested structure:

```text
scripts/
├── generate_helmet_gallery.py
├── workers/
│   ├── generation_worker.py
│   ├── processing_worker.py
│   └── audit_worker.py
├── providers/
│   ├── nvidia_flux.py
│   ├── stable_diffusion.py
│   ├── kimi_k3.py
│   └── llama_vision.py
├── prompts/
│   ├── front_hero.py
│   ├── side_profile.py
│   ├── rear_exhaust.py
│   ├── interior_macro.py
│   └── cockpit_context.py
├── media/
│   ├── convert_webp.py
│   ├── resize.py
│   ├── phash.py
│   └── manifests.py
├── clients/
│   ├── helmetsan_api.py
│   └── r2_client.py
├── config/
│   └── settings.py
└── tests/
```

CLI examples:

```bash
python generate_helmet_gallery.py \
  --helmet-id helmet_000184 \
  --missing-only \
  --mode final

python generate_helmet_gallery.py \
  --helmet-query "status=incomplete" \
  --shot-type interior_macro \
  --enqueue-only

python generate_helmet_gallery.py \
  --job-id job_01J8M2X \
  --retry-failed
```

The script should normally enqueue work rather than perform a large synchronous batch.

---

## 11.4 HelmetsanManager UI

Suggested modules:

```text
HelmetsanManager/
├── src/
│   ├── pages/
│   │   ├── FleetImageStudio.tsx
│   │   ├── ImageQueue.tsx
│   │   ├── ImageAuditQueue.tsx
│   │   └── ImageModelSettings.tsx
│   ├── components/
│   │   ├── CoverageBadge.tsx
│   │   ├── ShotCard.tsx
│   │   ├── GenerationProgress.tsx
│   │   ├── AuditBadge.tsx
│   │   └── BulkGenerateDialog.tsx
│   ├── api/
│   │   └── imageApi.ts
│   └── stores/
│       └── imageStudioStore.ts
```

Mission Control should use polling or WebSockets/SSE for progress updates. It should not require full-page refreshes.

---

# 12. Security, Governance, and Commercial Controls

## 12.1 Permissions

Define capabilities:

```text
helmetsan_view_images
helmetsan_generate_images
helmetsan_approve_images
helmetsan_publish_images
helmetsan_delete_images
helmetsan_manage_models
helmetsan_manage_storage
```

Generation, approval, and deletion should not be available to every WordPress administrator.

---

## 12.2 Upload Security

Manual replacement uploads must validate:

- MIME type.
- File extension.
- File signature.
- Maximum file size.
- Image dimensions.
- Malware scan where available.
- EXIF handling.
- User permissions.
- Helmet association.

Never trust the browser-provided MIME type.

---

## 12.3 Model and Asset Licensing

Record:

- Generation provider.
- Model name and revision.
- Reference-image source.
- Manufacturer asset license.
- Human-upload source.
- Commercial-use restrictions.
- Operator who approved publication.

This is especially important when using manufacturer photographs as references or when images include brand logos and copyrighted graphics.

---

## 12.4 Editorial Safety

The system must block or flag:

- Fake certification marks.
- Incorrect safety claims.
- Incorrect retention-system depictions.
- Invented internal safety structures.
- Wrong product colorways.
- Wrong accessories.
- Misleading rider behavior.
- Images that imply a product includes equipment it does not include.

---

# 13. Verification and Quality-Control Logic

## 13.1 Automated Checks

Every generated image should pass:

1. File integrity check.
2. Minimum resolution check.
3. Aspect-ratio check.
4. pHash generation.
5. Duplicate check.
6. Helmet detection check.
7. Shot-type classification.
8. Brand/model visual comparison.
9. Technical feature validation.
10. OCR check where labels are visible.
11. Kimi-K3 audit.
12. Llama Vision audit.
13. Publication policy evaluation.

---

## 13.2 Suggested Quality Score

```text
overall_score =
  0.25 * product_identity_score
+ 0.20 * geometry_score
+ 0.15 * shot_compliance_score
+ 0.15 * material_rendering_score
+ 0.10 * composition_score
+ 0.10 * technical_detail_score
+ 0.05 * image_integrity_score
```

Minimum automatic publication threshold:

```text
overall_score >= 0.88
```

However, any critical defect should override the numeric score:

```text
critical defects:
- wrong retention system
- wrong helmet model
- fake certification mark
- distorted shell
- invented safety feature
- materially incorrect colorway
- severe visor misalignment
```

---

## 13.3 Human Review Conditions

Human approval is required when:

- Kimi and Llama disagree.
- Confidence is below threshold.
- Certification text is visible but uncertain.
- Product identity confidence is below threshold.
- A technical feature is ambiguous.
- A duplicate candidate is detected.
- The image contains a rider or motorcycle brand context.
- An image will be used in an advertising campaign or technical guide.

---

# 14. Implementation Phases

## Phase 1 — Discovery and Schema

Deliverables:

- Final helmet entity model.
- Five-shot taxonomy.
- Image-status state machine.
- Database migrations.
- Model/provider configuration.
- Reference-asset policy.
- WordPress capability matrix.

Acceptance criteria:

- A helmet can be represented with zero to five images.
- Missing-shot calculation works.
- Image records can store all required metadata.

---

## Phase 2 — WordPress Image Manager

Deliverables:

- Admin menu.
- Helmet image gallery.
- Five shot cards.
- Upload replacement.
- Set primary.
- Reorder.
- Delete/archive.
- Coverage badges.
- REST endpoints.

Acceptance criteria:

- An administrator can manage all images for one helmet.
- No image can be attached without a helmet and shot type.
- Primary image selection is deterministic.

---

## Phase 3 — Storage and Media Processing

Deliverables:

- R2 integration.
- WordPress attachment service.
- Original preservation.
- WebP conversion.
- Responsive derivative creation.
- Manifest generation.
- CDN URL support.

Acceptance criteria:

- One source image produces 1920, 1024, and 480 variants.
- WordPress and R2 remain synchronized.
- Failed synchronization is retryable.

---

## Phase 4 — Generation Workers

Deliverables:

- NVIDIA NIM FLUX client.
- FLUX Schnell preview mode.
- FLUX Dev final mode.
- SD3.5 fallback.
- Prompt templates.
- Reference-image support.
- Async queue.
- Retry and timeout handling.

Acceptance criteria:

- A single helmet can enqueue all missing shots.
- A batch of at least 100 helmets can be queued without blocking WordPress.
- Failed jobs can be retried independently.

---

## Phase 5 — Verification Loop

Deliverables:

- Kimi-K3 client.
- Llama Vision client.
- Structured audit schema.
- Defect taxonomy.
- Confidence thresholds.
- Human-review queue.
- Audit badges.

Acceptance criteria:

- Generated images cannot publish automatically unless they satisfy configured audit rules.
- Rejection reasons are visible to administrators.
- Audit records are retained permanently.

---

## Phase 6 — Mission Control Fleet Studio

Deliverables:

- Batch selector.
- Queue monitor.
- GPU controls.
- Progress dashboard.
- Error and retry console.
- Cost and usage reporting.
- Model fallback controls.

Acceptance criteria:

- Operators can generate missing shots for a catalog segment.
- Operators can pause, resume, cancel, and retry batches.
- Job status is visible without page reload.

---

## Phase 7 — Product-Page Integration

Deliverables:

- Hero carousel.
- Thumbnail strip.
- Lightbox.
- Responsive image delivery.
- Accessibility metadata.
- Public/internal source labels.

Acceptance criteria:

- Approved five-shot galleries display correctly on desktop and mobile.
- Product pages do not request unnecessary full-resolution images.
- Missing or unpublished assets never appear publicly.

---

## Phase 8 — Pilot and Rollout

### Pilot

Start with:

- 10 helmets.
- Multiple shell materials.
- Multiple colorways.
- At least one carbon model.
- At least one adventure helmet.
- At least one helmet with interior quick-release hardware.
- At least one helmet with a micrometric closure.
- At least one helmet with complex graphics.

### Pilot measurements

Track:

- First-pass generation rate.
- Audit rejection rate.
- Human correction rate.
- Average cost per approved five-shot set.
- Average generation time.
- Duplicate rate.
- Product-identity accuracy.
- Page-load performance.
- Storage consumption.

### Rollout stages

```text
10 helmets → 50 helmets → 250 helmets → full catalog
```

Do not begin with a full 500-helmet batch until the pilot establishes:

- Stable prompts.
- Acceptable identity fidelity.
- Correct model fallback behavior.
- Reliable audit results.
- Predictable image costs.
- Satisfactory WordPress performance.

---

# 15. Testing Strategy

## Unit Tests

- Shot-type validation.
- Coverage calculation.
- pHash generation.
- Duplicate thresholds.
- Status transitions.
- Prompt rendering.
- Alt-text generation.
- R2 key generation.
- Permission checks.

## Integration Tests

- WordPress REST API.
- Queue submission.
- NVIDIA NIM response handling.
- Kimi-K3 audit response parsing.
- Llama Vision response parsing.
- WebP processing.
- WordPress attachment creation.
- R2 synchronization.

## End-to-End Tests

1. Create helmet.
2. Add reference assets.
3. Generate five shots.
4. Process derivatives.
5. Run visual audits.
6. Reject one shot.
7. Regenerate only rejected shot.
8. Approve final image.
9. Set hero.
10. Display on product page.
11. Confirm mobile responsive delivery.

## Failure Tests

- Provider timeout.
- Invalid image response.
- Corrupt PNG.
- Duplicate generation.
- R2 unavailable.
- WordPress unavailable.
- Kimi unavailable.
- Llama unavailable.
- Partial batch failure.
- Worker crash.
- Duplicate webhook.
- Permission violation.

---

# 16. Recommended Final Operating Policy

For each helmet:

```text
Minimum required:
- 5 canonical shot records
- 5 approved images
- 3 responsive derivatives per image
- pHash and SHA-256
- source and model metadata
- Kimi-K3 audit
- Llama Vision audit or human override
- alt text and caption
- primary image designation
```

The public product page should show only:

```text
validation_status = approved OR published
```

The administrative interface should show everything, including:

- Failed generations.
- Rejected images.
- Audit warnings.
- Duplicate candidates.
- Model metadata.
- Reference assets.
- Human review history.

---

# Final Architecture Recommendation

The correct Helmetsan architecture is not simply “ask an AI to create five helmet images.” It is a **catalog image production and governance system**:

```text
Real product data and reference images
        ↓
Structured five-shot prompt system
        ↓
FLUX.1-schnell preview generation
        ↓
FLUX.1-dev final generation
        ↓
Stable Diffusion fallback if needed
        ↓
Image normalization and WebP derivatives
        ↓
pHash deduplication
        ↓
Kimi-K3 visual and technical audit
        ↓
Llama Vision secondary audit
        ↓
Human review for exceptions
        ↓
WordPress + R2 publication
        ↓
Helmet product-page gallery
```

Kimi-K3 should be the reasoning and inspection layer, not the image generator. FLUX.1-dev should be the primary final generator, with FLUX.1-schnell for previews and Stable Diffusion 3.5 Large as a fallback. The WordPress plugin should own product linkage and editorial management, while HelmetsanManager should own fleet-scale generation, queue operations, auditing, and GPU management.

This gives Helmetsan a scalable system capable of producing and maintaining five verified, context-rich images for every helmet without blocking WordPress, losing media lineage, or publishing visually attractive but technically incorrect product imagery.