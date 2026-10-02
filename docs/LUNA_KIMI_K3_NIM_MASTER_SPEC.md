# Helmetsan AI Fabric  
## Refined Enterprise Integration Master Specification  
### GPT-5.6-Luna + Moonshot AI Kimi-K3 through NVIDIA NIM

> **Implementation note:** NVIDIA NIM deployments generally expose an OpenAI-compatible API, commonly under `/v1/chat/completions`, but exact model identifiers, supported parameters, multimodal behavior, structured-output guarantees, context limits, and reasoning telemetry must be verified against the specific NIM container or hosted endpoint. The architecture below treats those capabilities as discoverable rather than assumed.

---

# 0. Target Architecture

```text
                         ┌──────────────────────────┐
                         │ Helmetsan Manager / WP    │
                         │ Mission Control            │
                         └─────────────┬────────────┘
                                       │
                              AI Orchestration Layer
                                       │
                 ┌─────────────────────┼─────────────────────┐
                 │                     │                     │
          GPT-5.6-Luna          Dual Consensus         Local Silicon Grid
       Experiential Gateway      Cross-Audit Engine       Emergency Fallback
                 │                     │                     │
                 │              ┌──────┴──────┐              │
                 │              │             │              │
                 └──────────────┤  Kimi-K3    ├──────────────┘
                                │ NVIDIA NIM  │
                                └──────┬──────┘
                                       │
                        OpenAI-compatible NIM endpoint
                         integrate.api.nvidia.com/v1
```

## Core design principles

1. **Provider abstraction first**  
   WordPress, Mission Control, WP-CLI, workers, and batch processors must never call vendor APIs directly.

2. **Evidence before inference**  
   Product records, images, certifications, and compatibility claims are treated as evidence objects. Models produce assessments, not legally binding certifications.

3. **Kimi-K3 for context-heavy and multimodal workloads**  
   Use it for large catalog audits, long-document contradiction detection, and visual evidence inspection.

4. **Luna for editorial, commercial, and architecture workloads**  
   Use it for concise synthesis, customer-facing language, pricing narratives, product positioning, and final editorial normalization.

5. **Consensus is not “two models agree”**  
   Consensus requires:
   - independent assessments,
   - evidence references,
   - normalized claims,
   - confidence calibration,
   - contradiction analysis,
   - policy thresholds,
   - human escalation for regulated or ambiguous claims.

6. **No silent downgrade**  
   If Kimi-K3 does not support a requested capability, the provider returns a capability error. The orchestration layer decides whether to fall back.

---

# 1. Executive Architecture and Role Complementarity

## 1.1 Model role assignment

The supplied Helmetsan deployment profile describes Kimi-K3 as a large MoE model with approximately 2.8T total parameters, a 1M-token context window, 16K generation capacity, and native vision. These values must be confirmed against the actual NIM deployment.

| Capability | GPT-5.6-Luna | Kimi-K3 / NVIDIA NIM |
|---|---:|---:|
| Editorial nuance | Primary | Secondary |
| Commercial strategy | Primary | Supporting |
| Product page synthesis | Primary | Supporting |
| Concise code generation | Primary | Supporting |
| Whole-catalog analysis | Limited by cost/context | Primary |
| Multi-document contradiction extraction | Supporting | Primary |
| Long-horizon audit reasoning | Supporting | Primary |
| High-resolution visual inspection | Deployment-dependent | Primary if vision-enabled |
| Structured batch audit | Primary when bounded | Primary at catalog scale |
| Cross-language normalization | Primary | Strong secondary |
| Final customer-facing copy | Primary | Never publish directly without Luna/editorial pass |
| Compliance evidence extraction | Supporting | Primary extraction engine |
| Legal or regulatory determination | Neither independently | Neither independently |

## 1.2 Operational routing matrix

```text
Request
  │
  ├─ Customer-facing copy, SEO, merchandising, pricing narrative
  │       └── Luna
  │
  ├─ 500+ records, large compatibility graph, historical catalog audit
  │       └── Kimi-K3
  │
  ├─ Image-based strap, visor, shell, sticker inspection
  │       └── Kimi-K3 Vision
  │
  ├─ Compliance evidence extraction
  │       └── Kimi-K3 → Luna normalization → human review if required
  │
  ├─ High-risk factual product claim
  │       └── Dual Consensus
  │
  └─ Architecture, code, infrastructure, API design
          └── Luna primary, Kimi-K3 adversarial reviewer
```

## 1.3 Canonical assessment object

Every model result must be normalized into a common envelope.

```json
{
  "assessment_id": "asm_01J...",
  "provider": "nvidia_nim",
  "model": "moonshotai/kimi-k3",
  "request_id": "req_01J...",
  "task_type": "compliance_audit",
  "subject": {
    "type": "helmet_product",
    "id": "helmet_abc_123",
    "version": "catalog-v2025-03-08"
  },
  "claims": [
    {
      "claim_id": "claim_001",
      "field": "certifications.ece_22_06",
      "value": true,
      "normalized_value": true,
      "confidence": 0.97,
      "evidence_refs": [
        "doc:manufacturer_page#certifications",
        "img:front-sticker-01#ocr"
      ],
      "uncertainty": [],
      "severity": "high"
    }
  ],
  "contradictions": [],
  "missing_evidence": [],
  "policy_result": "pass",
  "model_confidence": 0.96,
  "effective_confidence": 0.94,
  "requires_human_review": false,
  "created_at": "2025-03-08T12:00:00Z"
}
```

## 1.4 Dual-Model Consensus Protocol

### Inputs

```json
{
  "subject": { "type": "helmet_product", "id": "h-123" },
  "evidence_bundle": {
    "structured_records": [],
    "documents": [],
    "images": []
  },
  "policy": {
    "minimum_promotion_confidence": 0.95,
    "require_independent_evidence": true,
    "require_human_for_regulatory_claims": true
  }
}
```

### Protocol

1. Freeze an immutable evidence snapshot.
2. Create a deterministic `evidence_hash`.
3. Send equivalent evidence to Luna and Kimi-K3.
4. Do not expose Model A's output to Model B during independent assessment.
5. Normalize both outputs into canonical claims.
6. Match claims by:
   - subject ID,
   - field path,
   - normalized value,
   - evidence references.
7. Calculate agreement and evidence quality.
8. Run an adversarial contradiction pass.
9. Apply promotion policy.
10. Persist all raw and normalized outputs.

### Effective confidence

A practical conservative formula:

```text
effective_confidence =
    min(
      luna_claim_confidence,
      kimi_claim_confidence,
      evidence_quality,
      agreement_score
    )
    × source_reliability
    × temporal_validity
```

Where:

```text
agreement_score =
  1.0       exact normalized agreement
  0.75      semantically equivalent agreement
  0.40      partial overlap
  0.00      contradiction
```

No claim should be promoted merely because both models return `0.99`.

### Promotion rules

```text
PROMOTE automatically only if:

  both assessments succeeded
  AND claims agree
  AND effective_confidence >= 0.95
  AND required evidence exists
  AND no high-severity contradiction exists
  AND the claim is not designated human-only
```

### Quarantine rules

A record enters quarantine if any of the following is true:

- effective confidence `< 0.95`;
- model disagreement on a regulated or safety-critical field;
- certification appears in metadata but not in evidence;
- image evidence is unreadable or ambiguous;
- source documents conflict by date or model variant;
- compatibility graph contains incompatible fitment claims;
- provider output fails schema validation;
- evidence hash differs between assessment stages.

### Quarantine resolution states

```text
OPEN
  → ADDITIONAL_EVIDENCE_REQUESTED
  → REASSESSMENT_PENDING
  → HUMAN_REVIEW
  → RESOLVED_PASS
  → RESOLVED_FAIL
  → PERMANENTLY_BLOCKED
```

---

# 2. Advanced Parameter Profiles and Dynamic Reasoning Budgeting

## 2.1 Reasoning calibration matrix

The exact availability of `reasoning_effort` must be checked against the deployed NIM model.

| Workflow | Effort | Max output | Temperature | Notes |
|---|---:|---:|---:|---|
| ECE 22.06 / FIM evidence audit | `max` | 12,288–16,384 | 0–0.1 | Evidence extraction, contradiction analysis |
| Multi-document compliance comparison | `max` | 16,384 | 0–0.1 | Require citations and uncertainty |
| High-resolution visual certification inspection | `high` or `max` | 8,192–12,288 | 0–0.1 | Do not infer unreadable text |
| Motorcycle posture/aerodynamics | `high` | 8,192 | 0.1–0.2 | Use measured assumptions |
| Product fitment reasoning | `high` | 8,192 | 0–0.1 | Validate model-year boundaries |
| Multilingual translation | `medium` | 4,096 | 0.1–0.2 | Preserve technical terminology |
| Metadata tagging | `low` | 1,024–2,048 | 0–0.1 | Prefer deterministic classification |
| SEO title/description | `low` or `medium` | 2,048–4,096 | 0.3–0.6 | Luna generally preferred |
| Code review | `high` | 8,192 | 0–0.1 | Require diff-specific findings |
| Final copy editing | `low` | 2,048 | 0.2–0.4 | No new unsupported facts |

## 2.2 Parameter policy

```json
{
  "model": "moonshotai/kimi-k3",
  "reasoning_effort": "high",
  "max_tokens": 8192,
  "temperature": 0.05,
  "top_p": 0.9,
  "seed": 482190,
  "response_format": {
    "type": "json_object"
  },
  "stream": true
}
```

### Important compatibility rule

Do not blindly send unsupported parameters. Maintain a provider capability manifest:

```json
{
  "moonshotai/kimi-k3": {
    "chat_completions": true,
    "vision": true,
    "json_object": true,
    "json_schema": false,
    "reasoning_effort": true,
    "seed": true,
    "stream": true,
    "max_context_tokens": 1000000,
    "max_output_tokens": 16384
  }
}
```

If `json_schema` is unsupported, use:

1. `response_format: { "type": "json_object" }`;
2. strict schema instructions;
3. server-side JSON Schema validation;
4. one bounded repair request;
5. quarantine on continued failure.

## 2.3 Seed management

Use seeds only for regression reproducibility, not as a guarantee of bitwise identity across infrastructure changes.

```text
seed = HMAC-SHA256(
  HELMETSAN_CI_SEED_SECRET,
  task_type | evidence_hash | prompt_version | model_id
)
```

Persist:

- model ID;
- NIM image/container digest;
- prompt version;
- seed;
- tokenizer/version if available;
- sampling parameters;
- evidence hash;
- response hash.

For production creative content, omit or randomize the seed. For CI and audit replay, use a stable seed.

## 2.4 Token headroom

Let:

```text
C = context window
I = input tokens
R = reserved reasoning/internal budget, if exposed
O = requested output tokens
S = safety margin
```

Require:

```text
I + R + O + S <= C
```

Suggested safety margins:

| Context utilization | Action |
|---:|---|
| `< 70%` | Normal |
| `70–85%` | Compress low-priority evidence |
| `85–92%` | Chunk and create intermediate summaries |
| `> 92%` | Reject direct call; use hierarchical reduction |

Do not assume `max_tokens=16384` means 16,384 visible output tokens if the provider internally consumes a reasoning budget.

## 2.5 TTFT and timeout policy

Deep reasoning may produce no visible token for 30–120 seconds.

Recommended transport settings:

```text
connect timeout:       10 seconds
TLS handshake timeout: 10 seconds
time-to-first-byte:    180 seconds
read idle timeout:      180 seconds
total request timeout:  900 seconds
stream heartbeat:      15 seconds
```

Use:

- HTTP/2 where available;
- streaming responses;
- reverse-proxy idle timeout ≥ 240 seconds;
- worker queues for long audits;
- `Idempotency-Key` for retries;
- cancellation propagation.

Do not interpret a long TTFT as failure unless the provider's documented timeout is exceeded.

---

# 3. One-Million-Token Context Engineering

## 3.1 Evidence manifest

Never concatenate arbitrary files. Build a manifest first.

```json
{
  "bundle_id": "bundle_01J...",
  "schema_version": "evidence-bundle.v3",
  "catalog_snapshot": "catalog-2025-03-08",
  "records": [
    {
      "record_id": "helmet_123",
      "record_type": "helmet",
      "priority": "high",
      "source_uri": "internal://catalog/helmet_123",
      "sha256": "..."
    }
  ],
  "documents": [],
  "images": [],
  "compatibility_matrix": {
    "rows": 3247,
    "sha256": "..."
  }
}
```

## 3.2 Structured encapsulation

Use explicit records rather than free-form concatenation.

```xml
<HELMETSAN_EVIDENCE_BUNDLE bundle_id="bundle_01J" schema="3">
  <INSTRUCTIONS>
    <RULE>Do not infer facts absent from evidence.</RULE>
    <RULE>Every claim requires one or more evidence_ref values.</RULE>
    <RULE>Unreadable image text must be reported as unreadable.</RULE>
  </INSTRUCTIONS>

  <HELMET_RECORD id="helmet_123" version="7" priority="high">
    <FIELD path="brand">Example</FIELD>
    <FIELD path="model">X-900</FIELD>
    <FIELD path="certifications.ece_22_06">unknown</FIELD>
    <SOURCE ref="doc_55" />
    <IMAGE ref="img_22" />
  </HELMET_RECORD>

  <COMPATIBILITY_MATRIX id="fitment_2025" rows="3247">
    ...
  </COMPATIBILITY_MATRIX>
</HELMETSAN_EVIDENCE_BUNDLE>
```

XML is useful for boundary marking, but all values should remain machine-generated and escaped. JSON-L or JSON Lines is preferable for large tabular data:

```json
{"type":"helmet","id":"helmet_123","field":"certifications.ece_22_06","value":true}
{"type":"fitment","make":"Honda","model":"CBR600RR","year_from":2013,"year_to":2016,"helmet_id":"helmet_123"}
```

## 3.3 Needle-in-a-haystack mitigation

For every critical fact, provide it in at least three representations:

1. original source location;
2. normalized record;
3. critical-facts index.

```json
{
  "critical_facts": [
    {
      "fact_id": "fact_0001",
      "subject_id": "helmet_123",
      "path": "certifications.ece_22_06",
      "value": true,
      "evidence_refs": ["doc_55:p14", "img_22:region_3"],
      "priority": 100
    }
  ]
}
```

At the beginning and end of the prompt, include an index containing only IDs, paths, and expected audit questions. Do not duplicate entire documents.

## 3.4 Hierarchical processing

For 500 helmets and 3,247 fitment rows:

```text
Stage 1: record-level normalization
  50–100 records per shard

Stage 2: shard-level contradiction extraction
  one result per shard

Stage 3: global cross-shard reconciliation
  summaries + critical evidence only

Stage 4: Luna editorial/commercial synthesis
  approved facts only
```

This is safer than placing all data into one call even when the context technically fits.

## 3.5 Context budget example

```text
System instructions                  3,000 tokens
Schema and policy                    4,000
Critical-facts index                 8,000
500 helmet records                 220,000
3,247 compatibility rows           240,000
Documents and extracted text       180,000
Image captions/OCR                  30,000
Reserved output                     12,000
Safety margin                       80,000
----------------------------------------------
Estimated total                    777,000
```

Keep actual context below the verified deployment limit. A nominal 1M window does not guarantee equal attention quality across 1M tokens.

---

# 4. Multimodal Vision Specification

## 4.1 Image classes

| Image type | Primary inspection |
|---|---|
| Shell front/rear/side | shell design, visible branding, damage |
| Certification sticker | ECE/DOT/FIM markings, text legibility |
| Chin strap close-up | D-rings, micrometric ratchet, buckle |
| Visor interior | Pinlock pins, insert compatibility |
| Product label | model, size, production date |
| Finish macro | gloss, matte, metallic, carbon weave |

## 4.2 Image preprocessing

Recommended pipeline:

```text
Original upload
  → malware scan
  → EXIF removal
  → orientation normalization
  → perceptual hash
  → resize preserving detail
  → optional crop variants
  → MIME validation
  → encrypted object storage
```

Preserve the original, but send an optimized derivative.

Recommended limits:

- JPEG or PNG;
- maximum decoded dimensions: 8,192 × 8,192 unless deployment supports more;
- long edge: 2,048–4,096 pixels for normal inspection;
- certification sticker crops: 1,500–3,000 pixels on the relevant edge;
- reject animated formats;
- reject images whose MIME type does not match magic bytes.

## 4.3 URL versus base64

### Prefer HTTPS URLs when:

- the object is in controlled object storage;
- URL expiration is short;
- the provider can access the URL;
- the image is not customer-private beyond the audit scope.

### Prefer base64 when:

- the image is private;
- provider egress cannot access internal storage;
- deterministic payload capture is required;
- the image is small enough to avoid request inflation.

Use signed URLs with:

```text
expiry: 5–15 minutes
single object scope
no directory traversal
content-disposition: inline
content-type: image/*
```

Never allow arbitrary user-supplied URLs to be passed to NIM.

## 4.4 Vision request shape

OpenAI-compatible multimodal payload:

```json
{
  "model": "moonshotai/kimi-k3",
