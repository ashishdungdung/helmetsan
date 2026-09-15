# Helmetsan Editorial Intelligence Rebuild  
## Master Editorial Strategy, Continuous Agent Architecture, and Execution Specification

## 0. Executive Position

Helmetsan should not treat `editorial_overview` as a descriptive text field. It should become a **grounded editorial intelligence layer**: a versioned, auditable, continuously maintainable interpretation of structured catalog data.

The rebuild must address five classes of failure:

| Failure | Corrective principle |
|---|---|
| Grammar defects such as “is delivers” | Generate from normalized facts, then run deterministic linguistic linting before persistence |
| Tautologies | Separate product identity, rider use case, and evaluative conclusion |
| Contradictory ergonomics | Classify product intent and usage envelope before prose generation |
| Missing accessory content | Use category-specific schemas and generation paths, not motorcycle/helmet templates |
| Synthetic weight defaults | Prohibit inferred weights unless explicitly labeled as estimates and separately sourced |

The system should produce:

1. A polished editorial overview.
2. Structured product intelligence.
3. Evidence and provenance.
4. Confidence and coverage scores.
5. Linter results.
6. A revision history.
7. A machine-readable review queue for uncertain or conflicting records.

No item should be silently “completed” merely because a model produced plausible prose.

---

# 1. Master Editorial Philosophy

## 1.1 Editorial mission

Helmetsan writing should answer five questions for a serious rider:

1. **What is this product?**
2. **What does it do well?**
3. **Where does it compromise?**
4. **Who is it realistically suited to?**
5. **Which claims are verified, reported, inferred, or unknown?**

The editorial voice should be:

- technically literate without being needlessly academic;
- vivid but not promotional;
- specific rather than generic;
- candid about trade-offs;
- proportionate to the available evidence;
- useful across commuting, touring, sport, adventure, and utility contexts.

The system must never confuse a marketing claim with a measured fact.

---

## 1.2 World-class moto-journalism rubric

Every overview should score against the following dimensions:

| Dimension | Standard |
|---|---|
| Accuracy | Every factual claim can be traced to a normalized field or approved source |
| Specificity | The copy names meaningful components, behaviors, dimensions, or use cases |
| Context | Specifications are translated into rider consequences |
| Balance | Strengths and limitations are both represented where evidence permits |
| Realism | Claims reflect the actual category and usage envelope |
| Clarity | Sentences are direct, grammatical, and easy to scan |
| Distinctiveness | The prose reflects the item rather than a reusable generic template |
| Restraint | Unknown values are not converted into confident-sounding claims |
| Utility | The reader can make a purchase, fit, or usage decision |
| Traceability | Editorial assertions carry evidence, confidence, and source metadata |

### Required prose pattern

A strong overview generally follows this logic:

1. **Identity:** what the product is and its market position.
2. **Technical character:** the design or engineering decisions that define it.
3. **Rider consequence:** what those decisions mean in actual use.
4. **Best-fit context:** where the product is strongest.
5. **Trade-off:** a limitation, caveat, or condition of use.
6. **Evidence boundary:** only when important information is unavailable or disputed.

This is a reasoning pattern, not a fixed sentence template.

---

# 2. Category-Specific Editorial Rubrics

## 2.1 Helmets

### Required intelligence domains

#### A. Protection and certification

Capture:

- shell construction;
- shell material or composite type;
- certification standard and region;
- rotational-impact system, if documented;
- EPS liner architecture;
- emergency-release cheek pads;
- retention system;
- visor-locking mechanism;
- certification date or revision where relevant.

Do not write “highly protective” as an unsupported conclusion. Instead write what is known:

> “The helmet uses a multi-density EPS liner and carries ECE 22.06 certification, giving it a documented modern-impact compliance baseline.”

If a feature is not documented, omit it or state that it is not specified.

#### B. Aerodynamics

Evaluate:

- shell shape;
- spoiler or aero appendages;
- stability at speed;
- crosswind sensitivity;
- neck-load implications;
- whether the shape appears sport-focused, touring-focused, or neutral.

Avoid universal statements such as “stable at all speeds.” Use conditional language where appropriate:

> “Its rear aero profile is intended to reduce lift in a tucked riding position; upright riders may experience a different balance of wind load.”

#### C. Acoustics

Acoustic claims must distinguish:

1. independently measured dB;
2. manufacturer claim;
3. editorial impression;
4. unknown.

Required metadata:

```json
{
  "value_db": 96.4,
  "measurement_context": {
    "speed_kph": 100,
    "motorcycle": "unknown",
    "ear_position": "unknown",
    "rider_configuration": "unknown"
  },
  "source_type": "independent_test",
  "confidence": "medium"
}
```

Never write “quiet” solely because a product page uses that word. If no measurement exists:

> “No standardized independent noise figure is available; perceived noise will depend heavily on the motorcycle, screen, fit, and rider posture.”

#### D. Optical performance

Assess:

- visor class;
- optical clarity;
- distortion;
- field of view;
- visor thickness;
- Pinlock or anti-fog provision;
- sun visor;
- tear-off compatibility;
- opening mechanism;
- detents and lock quality, if reviewed.

#### E. Ventilation

Assess:

- intake location;
- exhaust paths;
- chin, brow, crown, and rear extraction;
- glove usability;
- whether the system is independently measured or only described;
- likely behavior in hot, wet, and cold conditions.

Do not invent airflow throughput in cubic meters per hour unless a validated test exists.

#### F. Fit and retention

Cover:

- head-shape tendency only where documented or consistently reported;
- size range;
- cheek-pad configuration;
- emergency cheek-pad removal;
- retention system;
- eyewear compatibility;
- liner adjustability;
- fit caveat.

“Fits everyone” is prohibited.

### Helmet editorial output should answer

- Is it sport, touring, urban, adventure, modular, off-road, or multi-purpose?
- What protection facts are verified?
- How does it manage noise and air?
- Is the visor system a strength or compromise?
- What rider and motorcycle environments suit it?
- What remains unknown?

---

## 2.2 Motorcycles

### Required intelligence domains

#### A. Powertrain

Capture:

- engine architecture;
- displacement;
- claimed or measured peak power;
- peak torque;
- torque delivery character;
- transmission;
- final drive;
- fuel delivery;
- operating character;
- electric motor/battery details where applicable.

A peak number is insufficient. Editorially translate the curve:

- strong low-rpm pull;
- midrange-focused;
- top-end biased;
- linear;
- abrupt;
- tractable;
- heat-sensitive;
- highly rev-dependent.

If only peak figures exist, do not fabricate torque-curve behavior. Phrase it as a bounded interpretation:

> “The available specifications emphasize a high peak-output figure; detailed torque-curve data is not published, so low-rpm flexibility should not be assumed.”

#### B. Power-to-weight

Never use synthetic weight values such as 108 or 110 kg.

Store:

```json
{
  "power_to_weight": {
    "value_kw_per_kg": null,
    "status": "not_computable",
    "reason": "verified_running_weight_missing"
  }
}
```

Only calculate power-to-weight when:

- power unit is normalized;
- weight basis is explicit;
- the weight is verified;
- the calculation basis is recorded.

Distinguish:

- dry weight;
- wet/curb weight;
- ready-to-ride weight;
- battery weight;
- optional equipment.

#### C. Ergonomics and inseam confidence

Use explicit classification:

- low-seat confidence;
- neutral confidence;
- tall-bike confidence;
- compact sport posture;
- relaxed upright;
- forward-set;
- rear-set;
- standing-oriented.

Do not infer inseam suitability from seat height alone. Include:

- seat height;
- seat width if available;
- suspension sag implications;
- rider triangle;
- tank shape;
- footpeg position;
- handlebar reach;
- loaded touring effects.

Preferred phrasing:

> “The seat height is moderate on paper, but the broad front section may make shorter inseams feel less confident at a stop.”

Avoid:

> “It offers fatigue-free ergonomics for every rider.”

#### D. Chassis and suspension dynamics

Assess:

- frame architecture;
- wheelbase;
- steering geometry;
- suspension adjustability;
- damping character;
- wheel size;
- tire format;
- braking hardware;
- chassis feedback;
- high-speed stability;
- low-speed maneuverability;
- loaded behavior.

Claims must distinguish specification from test observation.

#### E. Realistic usage envelope

Every motorcycle receives one or more use classifications:

```json
[
  "urban",
  "commuting",
  "touring",
  "sport_touring",
  "track",
  "adventure",
  "off_road",
  "utility",
  "beginner_friendly",
  "specialist"
]
```

The agent must not use a generic “open highway and city traffic” claim for every model.

Examples:

- A Panigale V4 R: track-focused, road-capable, specialist, high-performance.
- A Yamaha R1: supersport, track-oriented, demanding road ergonomics.
- A large adventure motorcycle: touring, adventure, mixed-surface, luggage-capable.
- A small commuter: urban, utility, low-speed maneuverability.

The usage envelope must constrain the language.

#### F. Rider aids

Capture:

- ABS;
- cornering ABS;
- traction control;
- ride modes;
- wheelie control;
- launch control;
- engine-brake management;
- quickshifter;
- cruise control;
- semi-active suspension;
- IMU;
- radar;
- hill-hold;
- reverse assist.

Do not equate “many electronics” with “easy to ride.” Explain the rider consequence.

### Motorcycle editorial output should answer

- What is the motorcycle’s actual performance character?
- Where in the rev range does it work best?
- How demanding is it physically and technically?
- What is its realistic road, track, touring, or off-road role?
- How does its chassis respond to speed, load, and surface?
- Which aids materially change the experience?
- What is unknown because the data is incomplete?

---

## 2.3 Accessories

Accessories must receive their own editorial logic. They should never inherit helmet or motorcycle prose patterns.

### Required intelligence domains

#### A. Installation and docking

Assess:

- mounting hardware;
- permanent versus removable installation;
- required tools;
- drilling or wiring;
- motorcycle-specific brackets;
- tank, rack, bar, seat, or helmet mounting;
- lockability;
- installation time if known;
- effect on service access.

#### B. Capacity and aerodynamics

For luggage and storage products:

- nominal volume;
- usable volume;
- expansion system;
- internal organization;
- load limit;
- waterproof rating;
- rack compatibility;
- symmetry;
- effect on handling;
- effect on aerodynamic drag;
- passenger-space implications.

Avoid generic phrases such as “ideal for long trips” unless capacity and mounting support that conclusion.

#### C. Intercom and connected technology

Capture:

- Bluetooth version;
- mesh protocol;
- rider count;
- pairing mode;
- range;
- simultaneous audio behavior;
- phone/GPS integration;
- firmware update path;
- voice-control support;
- microphone and speaker compatibility.

Distinguish claimed range from real-world tested range.

#### D. Weatherproofing and durability

Assess:

- IP rating;
- rain cover;
- sealed connectors;
- material;
- abrasion resistance;
- UV exposure;
- temperature limits;
- warranty;
- serviceable components.

No IP rating means no “fully waterproof” claim.

#### E. Cross-entity compatibility

Accessories should connect to a compatibility graph:

```text
Accessory → Helmet models
Accessory → Motorcycle models
Accessory → Rack systems
Accessory → Phone platforms
Accessory → Intercom ecosystems
Accessory → Mounting standards
```

Compatibility claims must have a source and date. “Fits most motorcycles” is prohibited unless a defined compatibility standard supports it.

### Accessory editorial output should answer

- What problem does it solve?
- What must be installed or purchased separately?
- What does it fit?
- How does it behave in weather and motion?
- What are its meaningful limitations?
- Will it alter motorcycle handling, visibility, safety, or serviceability?

---

# 3. Zero-Hallucination Editorial Policy

## 3.1 Claim-status taxonomy

Every factual assertion should be classified as one of:

```text
verified_fact
manufacturer_claim
independent_measurement
editorial_observation
derived_calculation
bounded_inference
unknown
conflict
```

Only the first six may normally appear in public prose. `bounded_inference` must be phrased cautiously. `unknown` and `conflict` should trigger review or transparent caveat language.

## 3.2 Prohibited behavior

The agent must not:

- invent weight, inseam, decibel, airflow, range, or capacity data;
- convert missing values into defaults;
- present marketing adjectives as measurements;
- infer a safety rating from brand reputation;
- infer comfort from seat height alone;
- infer weatherproofing from material appearance;
- infer compatibility from visual similarity;
- claim a product is “best,” “perfect,” “effortless,” or “fatigue-free” without defined evidence;
- claim universal suitability;
- merge contradictory source values without recording the conflict.

## 3.3 Missing-data language

Use concise, useful caveats:

- “The manufacturer does not publish a verified running weight.”
- “Independent noise testing was not located.”
- “Compatibility is listed for selected rack systems; universal fit should not be assumed.”
- “The published seat height does not fully capture reach to the ground because seat width is not specified.”

Do not turn every missing field into a distracting disclaimer. The system should prioritize material unknowns.

---

# 4. Anti-Formulaic Editorial System

## 4.1 Separate semantic planning from prose generation

The agent should first create an **editorial plan**, then write prose. The plan includes:

- product identity;
- category;
- dominant technical story;
- rider consequences;
- usage envelope;
- differentiators;
- caveats;
- evidence gaps;
- prohibited claims.

This prevents the language model from filling an empty narrative with generic claims.

## 4.2 Controlled variation

Variation should occur through:

- opening angle;
- sentence rhythm;
- order of technical domains;
- level of detail;
- category-specific vocabulary;
- contrast structure;
- conditional framing;
- evidence-aware conclusions.

Variation must not mean random verbosity or unsupported creativity.

## 4.3 Repetition controls

Store semantic fingerprints for all overviews:

```json
{
  "opening_pattern": "identity_then_use_case",
  "dominant_claims": [
    "track_focus",
    "high_rpm_power",
    "limited_road_comfort"
  ],
  "phrase_fingerprints": [
    "built for riders seeking",
    "whether carving traffic or cruising"
  ]
}
```

Reject or revise copy when:

- the same 5–8 word phrase appears across too many items;
- two consecutive sentences begin with the same structure;
- identical adjective sequences appear across a category;
- generic use cases conflict with the product classification.

---

# 5. Continuous Editorial Agent Architecture

## 5.1 High-level components

```text
Mission Control UI
        │
REST API + WebSocket Telemetry
        │
ContinuousEditorialAgent
        │
Job Planner ──> Durable Queue ──> Worker Pool
        │                             │
State Store                       Luna Synthesis
        │                             │
Checkpoint Store               Grounding + Rules
                                      │
                             Quality Sentinel/Linter
                                      │
                           Review Queue / Database
                                      │
                              Published Editorial
```

Recommended implementation:

- Node.js or TypeScript;
- PostgreSQL for durable state;
- Redis or a queue service for work dispatch;
- object storage for prompt/output artifacts;
- WebSocket or Server-Sent Events for telemetry;
- structured logs;
- OpenTelemetry traces;
- database transactions for editorial writes.

## 5.2 Stateful agent

The agent must be resumable, idempotent, and safe to stop.

### `state.json`

```json
{
  "run_id": "editorial-2025-03-08T14:30:00Z",
  "catalog_snapshot_id": "catalog-2025-03-08",
  "agent_version": "cea-1.0.0",
  "prompt_version": "editorial-v4.2",
  "status": "running",
  "started_at": "2025-03-08T14:30:00Z",
  "updated_at": "2025-03-08T14:35:18Z",
  "totals": {
    "discovered": 5493,
    "queued": 5493,
    "processing": 12,
    "generated": 238,
    "passed": 211,
    "needs_revision": 19,
    "needs_human_review": 8,
    "failed": 0,
    "published": 0
  },
  "partitions": {
    "helmets": {
      "total": 2219,
      "completed": 80,
      "cursor": "helmet:80"
    },
    "motorcycles": {
      "total": 3247,
      "completed": 152,
      "cursor": "motorcycle:152"
    },
    "accessories": {
      "total": 27,
      "completed": 6,
      "cursor": "accessory:6"
    }
  },
  "active_batches": [
    {
      "batch_id": "batch-00018",
      "category": "motorcycle",
      "item_ids": ["m-1001", "m-1002"],
      "attempt": 1,
      "lease_expires_at": "2025-03-08T14:40:00Z"
    }
  ],
  "last_checkpoint": "2025-03-08T14:35:00Z",
  "shutdown_requested": false
}
```

The database, not a local file, should be the authoritative state store in production. `state.json` may be a human-readable checkpoint export.

## 5.3 Editorial item lifecycle

```text
discovered
→ normalized
→ grounded
→ planned
→ generated
→ linted
→ revised
→ approved
→ published
```

Failure states:

```text
blocked_missing_data
conflict_review
generation_failed
lint_failed
human_review
```

Every transition requires:

- timestamp;
- agent version;
- prompt version;
- reason;
- input snapshot;
- output hash.

## 5.4 Checkpointing

Checkpoint after:

- each item transaction;
- each batch;
- every 30–60 seconds;
- graceful shutdown;
- worker failure;
- rate-limit event.

An item should only be marked complete after:

1. generated JSON is schema-valid;
2. evidence references resolve;
3. linter passes;
4. editorial record is transactionally stored;
5. telemetry event is emitted.

---

# 6. Batching, Rate Limiting, and Recovery

## 6.1 Batch strategy

Use category-specific batches:

- Helmets: 20–40 items per planning batch.
- Motorcycles: 15–30 items per planning batch.
- Accessories: 5–10 items per planning batch.

Do not send thousands of products in one prompt. Large batches reduce grounding precision and make error isolation difficult.

A practical pipeline:

```text
Batch extraction → normalization → individual editorial generation
→ deterministic lint → optional revision pass → persistence
```

Generation should normally be one item per model call or a small homogeneous group. Batching is for queue and infrastructure efficiency, not for combining unrelated products into one narrative prompt.

## 6.2 Rate limiting

Use a token bucket per model and provider:

```ts
type RateLimitConfig = {
  requestsPerMinute: number;
  tokensPerMinute: number;
  maxConcurrency: number;
  retryAfterRespect: boolean;
};
```

Use:

- exponential backoff;
- jitter;
- provider `Retry-After`;
- maximum retry count;
- dead-letter queue;
- circuit breaker after repeated failures.

## 6.3 Recovery rules

| Error | Action |
|---|---|
| JSON parse failure | Retry with repair prompt once |
| Schema failure | Send targeted correction prompt |
| Linter failure | Send failure report and regenerate |
| Missing source field | Mark unknown; do not invent |
| Provider timeout | Retry with backoff |
| Rate limit | Pause queue partition |
| Contradictory data | Route to review |
| Database failure | Retry transaction; retain output artifact |
| Agent stop request | Finish current item, checkpoint, release leases |

---

# 7. Hybrid Generation Strategy

## 7.1 Luna High-Speed Synthesis

Luna should perform:

- editorial prioritization;
- technical interpretation;
- category-aware prose;
- concise trade-off analysis;
- explanation of rider consequences;
- controlled stylistic variation.

## 7.2 Heuristic grounding layer

Deterministic code should perform:

- unit normalization;
- category classification;
- usage-envelope assignment;
- weight anomaly detection;
- duplicate field detection;
- power-to-weight calculations;
- compatibility joins;
- phrase repetition detection;
- contradiction detection;
- grammar linting;
- source and confidence attachment.

The language model should not be responsible for arithmetic or policy enforcement.

## 7.3 Data anomaly rules

### Weight artifact detector

```ts
function detectSyntheticWeight(value: number | null): string[] {
  if (value == null) return [];
  const issues: string[] = [];

  if ([108, 110].includes(value)) {
    issues.push("suspicious_default_weight");
  }

  if (value < 25 || value > 500) {
    issues.push("implausible_motorcycle_weight");
  }

  return issues;
}
```

A value of 108 or 110 is not automatically deleted if independently verified. It is flagged because it matches the known synthetic artifact pattern.

### Motorcycle usage classifier

```ts
function classifyUsage(m: Motorcycle): UsageProfile {
  const tags = new Set<string>();

  if (m.category?.match(/supersport|superbike|race/i)) tags.add("track");
  if (m.seatHeightMm > 850 || m.category?.match(/adventure|enduro/i)) {
    tags.add("adventure");
  }
  if (m.category?.match(/touring|cruiser/i)) tags.add("touring");
  if (m.engineCc && m.engineCc < 500) tags.add("urban");

  return {
    tags: [...tags],
    posture: inferPosture(m),
    fatigueFreeClaimsAllowed: false
  };
}
```

“Fatigue-free” should generally be prohibited as an absolute regardless of category.

---

# 8. Exact Editorial JSON Schema

A recommended canonical record:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://helmetsan.com/schemas/editorial-record.json",
  "type": "object",
  "additionalProperties": false,
  "required": [
    "item_id",
    "category",
    "schema_version",
    "editorial_overview",
    "editorial_plan",
    "claims",
    "quality",
    "provenance"
  ],
  "properties": {
    "item_id": {
      "type": "string",
      "minLength": 1
    },
    "category": {
      "enum": ["helmet", "motorcycle", "accessory"]
    },
    "schema_version": {
      "type": "string"
    },
    "editorial_overview": {
      "type": "string",
      "minLength": 80,
      "maxLength": 1800
    },
    "editorial_plan": {
      "type": "object",
      "additionalProperties": false,
      "required": [
        "identity",
        "dominant_story",
        "best_fit",
        "tradeoffs",
        "unknowns",
        "prohibited_claims"
      ],
      "properties": {
        "identity": { "type": "string" },
        "dominant_story": { "type": "string" },
        "best_fit": {
          "type": "array",
          "items": { "type": "string" }
        },
        "tradeoffs": {
          "type": "array",
          "items": { "type": "string" }
        },
        "unknowns": {
          "type": "array",
          "items": { "type": "string" }
        },
        "prohibited_claims": {
          "type": "array",
          "items": { "type": "string" }
        }
      }
    },
    "claims": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": [
          "claim",
          "claim_type",
          "confidence",
          "evidence_ids"
        ],
        "properties": {
          "claim": { "type": "string" },
          "claim_type": {
            "enum": [
              "verified_fact",
              "manufacturer_claim",
              "independent_measurement",
              "editorial_observation",
              "derived_calculation",
              "bounded_inference",
              "unknown",
              "conflict"
            ]
          },
          "confidence": {
            "enum": ["high", "medium", "low"]
          },
          "evidence_ids": {
            "type": "array",
            "items": { "type": "string" }
          }
        }
      }
    },
    "category_intelligence": {
      "type": "object"
    },
    "quality": {
      "type": "object",
      "additionalProperties": false,
      "required": [
        "schema_valid",
        "linter_passed",
        "grounding_score",
        "distinctiveness_score",
        "contradiction_count",
        "review_required"
      ],
      "properties": {
        "schema_valid": { "type": "boolean" },
        "linter_passed": { "type": "boolean" },
        "grounding_score": {
          "type": "number",
          "minimum": 0,
          "maximum": 1
        },
        "distinctiveness_score": {
          "type": "number",
          "minimum": 0,
          "maximum": 1
        },
        "contradiction_count": {
          "type": "integer",
          "minimum": 0
        },
        "review_required": { "type": "boolean" }
      }
    },
    "provenance": {
      "type": "object",
      "additionalProperties": false,
      "required": [
        "catalog_snapshot_id",
        "agent_version",
        "prompt_version",
        "generated_at"
      ],
      "properties": {
        "catalog_snapshot_id": { "type": "string" },
        "agent_version": { "type": "string" },
        "prompt_version": { "type": "string" },
        "generated_at": {
          "type": "string",
          "format": "date-time"
        }
      }
    }
  }
}
```

### Category intelligence extensions

Use one of these typed structures:

```json
{
  "category_intelligence": {
    "type": "helmet",
    "shell": {
      "material": null,
      "status": "unknown"
    },
    "acoustics": {
      "value_db": null,
      "status": "not_measured"
    },
    "ventilation": {
      "intakes": ["chin", "brow"],
      "exhausts": ["rear"],
      "throughput": null
    },
    "retention": "double_d",
    "emergency_cheek_pads": true
  }
}
```

```json
{
  "category_intelligence": {
    "type": "motorcycle",
    "powertrain": {
      "engine_type": "V4",
      "displacement_cc": 1103,
      "peak_power_kw": 160,
      "peak_torque_nm": 121,
      "curve_character": "top_end_biased",
      "curve_source": "bounded_inference"
    },
    "weight": {
      "value_kg": null,
      "basis": "unknown",
      "status": "missing"
    },
    "usage_envelope": [
      "track",
      "specialist",
      "road_capable"
    ],
    "ergonomics": {
      "posture": "forward_sport",
      "inseam_confidence": "not_determinable"
    }
  }
}
```

```json
{
  "category_intelligence": {
    "type": "accessory",
    "installation": {
      "mounting_method": "rack_specific",
      "tools_required": "unknown",
      "permanent_modification": "unknown"
    },
    "capacity": {
      "liters": null,
      "status": "not_specified"
    },
    "weatherproofing": {
      "ip_rating": null,
      "rain_cover": true,
      "status": "partially_documented"
    },
    "compatibility": [
      {
        "entity_type": "motorcycle",
        "entity_ids": ["m-123", "m-456"],
        "source": "manufacturer_fitment_list"
      }
    ]
  }
}
```

---

# 9. Quality Sentinel and Linter

## 9.1 Deterministic linguistic rules

Reject and revise if the overview contains:

```regex
/\bis\s+(delivers|combines|offers|features|provides|uses)\b/gi
```

Also detect:

```regex
/\b(is|are|was|were)\s+\1\b/gi
```

Additional checks:

- duplicate consecutive verbs;
- sentence fragments;
- repeated opening clauses;
- excessive passive voice;
- unsupported superlatives;
- “perfect for everyone” language;
- “fatigue-free”;
- “best-in-class” without evidence;
- “all-day comfort” without comfort evidence;
- “whether carving traffic or cruising the open highway” on track/supersport models;
- “built for riders seeking” repeated above threshold;
- use of “lightweight” without a verified comparison basis.

## 9.2 Semantic contradiction rules

Example:

```ts
function detectContradictions(item: Item, text: string): Issue[] {
  const issues: Issue[] = [];
  const superbike = item.tags.includes("supersport") ||
                    item.tags.includes("superbike");

  if (
    superbike &&
    /fatigue[- ]free|relaxed ergonomics|ideal for long highway cruising/i.test(text)
  ) {
    issues.push({
      code: "SUPERBIKE_ERGONOMIC_OVERCLAIM",
      severity: "high"
    });
  }

  if (
    item.weight?.status === "missing" &&
    /\b\d{2,3}\s?kg\b/i.test(text)
  ) {
    issues.push({
      code: "UNSUPPORTED_WEIGHT_ASSERTION",
      severity: "critical"
    });
  }

  return issues;
}
```

## 9.3 Grounding validator

Every numeric claim in prose should map to an evidence-backed field.

```ts
function validateNumericClaims(text: string, facts: FactSet): Issue[] {
  const numbers = extractNumbers(text);
  return numbers
    .filter(n => !facts.containsEquivalent(n))
    .map(n => ({
      code: "UNSUPPORTED_NUMERIC_CLAIM",
      value: n,
      severity: "critical"
    }));
}
```

## 9.4 Readability and style thresholds

Recommended defaults:

- overview length: 90–220 words;
- average sentence length: 12–28 words;
- no more than two semicolon-heavy sentences;
- no more than one marketing adjective before a noun;
- no paragraph of more than five sentences;
- no repeated phrase used in more than 3 percent of a category.

## 9.5 Revision loop

```ts
for (let attempt = 1; attempt <= 2; attempt++) {
  const draft = await generateEditorial(input);
  const report = await sentinel.inspect(draft, input);

  if (report.passed) {
    await persistApproved(draft, report);
    return;
  }

  if (report.criticalIssues.length) {
    input = addRevisionConstraints(input, report);
    continue;
  }

  await persistForHumanReview(draft, report);
  return;
}
```

Never allow unlimited automatic rewriting. Repeated retries can produce drift and conceal source-data problems.

---

# 10. Prompt Templates

## 10.1 System prompt

```text
You are Helmetsan's senior motorcycle editor and technical fact interpreter.

Your task is to produce grounded editorial intelligence for one catalog item.

Rules:
1. Use only the supplied facts and evidence.
2. Never invent missing weight, inseam, dB, airflow, range, capacity, compatibility, or performance data.
3. Distinguish verified facts, manufacturer claims, measurements, observations, calculations, and bounded inferences.
4. Do not use generic claims such as “fatigue-free,” “perfect for every rider,” or “best-in-class.”
5. Match the usage envelope and category of the item.
6. A superbike must not be described using generic commuter or relaxed-touring language.
7. Write active, precise, varied sentences.
8. Do not use “is delivers,” “is combines,” duplicate verbs, or formulaic openings.
9. If evidence is insufficient, represent the uncertainty explicitly.
10. Return only valid JSON matching the supplied schema.
```

## 10.2 Item prompt

```text
CATEGORY:
{{category}}

NORMALIZED ITEM:
{{normalized_item_json}}

EVIDENCE:
{{