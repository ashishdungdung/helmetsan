# Helmetsan Enterprise AI Platform  
## Master Architectural Specification & Implementation Blueprint

**Document status:** Baseline architecture  
**Primary goals:** IDE independence, provider independence, cost control, durable execution, deep WordPress integration, media integrity, operational resilience, and verifiable outputs.

---

# 0. Executive Architecture

Helmetsan should be implemented as a **provider-neutral control plane** with multiple interchangeable execution surfaces:

```text
Antigravity / Cursor / Zed / Claude Code / VS Code / CI / Cron
                              │
                              ▼
                    Headless CLI + REST API
                              │
                              ▼
                    Helmetsan Control Plane
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
 Model Fleet Router     Durable DAG Runner     Media Pipeline
        │                     │                     │
        ▼                     ▼                     ▼
 Providers / Adapters   SQLite/Postgres      WordPress / R2
```

The core principle is:

> No IDE, WordPress plugin, model vendor, or UI owns the orchestration protocol.

The authoritative implementation should be the **headless Helmetsan Core runtime**. WordPress, HelmetsanManager, and IDE integrations are clients or controlled adapters.

## 0.1 Ownership boundaries

| Capability | Authoritative owner |
|---|---|
| Model registry | Helmetsan Core |
| Routing and policy | Helmetsan Core |
| Task graph execution | Helmetsan Core |
| Durable activity chains | Helmetsan Core |
| Provider credentials | Secret manager / environment |
| WordPress post/media mutation | WordPress adapter |
| Operator configuration UI | WordPress and HelmetsanManager clients |
| Image storage | Object storage, preferably R2 |
| UI dashboard | HelmetsanManager |
| IDE integration | Thin client adapters |

## 0.2 Non-negotiable design rules

1. Every provider is accessed through an adapter interface.
2. Every task is represented as a durable activity chain.
3. Every external operation is idempotent.
4. Every provider call has a correlation ID and usage event.
5. No WordPress request may block indefinitely on a long-running model job.
6. No model output is trusted without schema validation and policy validation.
7. No generated asset is published without checksum, integrity, and provenance records.
8. Operator overrides must be explicit, scoped, auditable, and reversible.
9. The same job must be runnable by CLI, REST, cron, or a UI.
10. The platform must degrade gracefully from frontier models to local models.

---

# SECTION 1 — IDE-INDEPENDENT, COST-OPTIMIZED MULTI-MODEL FLEET FABRIC

## 1.1 Canonical runtime components

```text
helmetsan
├── CLI client
├── API server
├── Worker
├── Scheduler
├── Provider adapters
├── Activity-chain engine
├── Context engine
├── Media engine
└── Policy engine
```

The CLI and HTTP API must call the same application services.

```text
CLI ─────────────┐
REST API ────────┼──> Application Services ──> Domain / Infrastructure
WordPress ───────┤
HelmetsanManager ┘
```

No UI should reimplement routing logic.

## 1.2 Provider abstraction

### TypeScript contract

```ts
export type ProviderTier = 0 | 1 | 2 | 3;

export interface ModelDescriptor {
  id: string;
  provider: string;
  tier: ProviderTier;
  capabilities: ModelCapability[];
  contextWindow: number;
  modalities: ("text" | "vision" | "image-generation")[];
  pricing: {
    inputPerMillion?: number;
    outputPerMillion?: number;
    fixedPerRequest?: number;
    isFreeQuota?: boolean;
  };
  availability: "enabled" | "disabled" | "degraded";
  metadata: Record<string, unknown>;
}

export type ModelCapability =
  | "classification"
  | "extraction"
  | "summarization"
  | "coding"
  | "architecture"
  | "reasoning"
  | "vision"
  | "image-generation"
  | "policy-arbitration"
  | "long-context";

export interface ProviderAdapter {
  readonly providerId: string;

  listModels(): Promise<ModelDescriptor[]>;

  chat(request: ChatRequest, signal?: AbortSignal): Promise<ChatResponse>;

  streamChat(
    request: ChatRequest,
    onEvent: (event: StreamEvent) => void,
    signal?: AbortSignal
  ): Promise<ChatResponse>;

  generateImage(
    request: ImageGenerationRequest,
    signal?: AbortSignal
  ): Promise<ImageGenerationResponse>;

  healthCheck(): Promise<ProviderHealth>;

  estimate(request: CostEstimationRequest): Promise<CostEstimate>;
}
```

### Canonical chat request

```ts
export interface ChatRequest {
  model: string;
  messages: Message[];
  responseFormat?: JsonSchemaResponseFormat;
  temperature?: number;
  topP?: number;
  maxOutputTokens?: number;
  timeoutMs: number;
  metadata: {
    tenantId: string;
    chainId: string;
    stepId: string;
    taskType: string;
    idempotencyKey: string;
  };
}
```

All adapters normalize vendor-specific APIs into this contract.

## 1.3 Fleet tiers

The fleet registry should support the following logical tiers.

### Tier 0 — Local/free execution

Examples:

- Apple Silicon grid
- M4 Pro / M3 Pro machines
- LM Studio
- Gemma 4 12B
- Qwen 2.5
- Other locally hosted compatible models
- Local Silicon Grid workers

Use for:

- classification
- metadata extraction
- deterministic transformations
- draft generation
- local code assistance
- privacy-sensitive preprocessing
- low-risk bulk operations
- retries when cloud providers are unavailable

“$0 cost” means no marginal API charge; power, hardware, maintenance, and capacity remain operational costs.

### Tier 1 — Free or quota-based cloud execution

Examples:

- NVIDIA NIM Developer API
- Kimi-K3 with long context
- DeepSeek-R1
- Llama 3.3 70B
- Llama 3.2 Vision

Use for:

- structured extraction
- parallel analysis
- candidate generation
- long-document processing
- vision analysis
- low-cost consensus participants

Quota and rate limits must be treated as variable, not guaranteed.

### Tier 2 — Frontier and policy arbitration

Example:

- GPT-5.6-Luna through the Experiential AI gateway

Use for:

- system architecture
- high-value editorial work
- complex reasoning
- final synthesis
- conflict resolution
- policy arbitration
- release-critical decisions

Luna should not be hard-coded as the only premium provider. It is the default Tier 2 policy arbiter, but the policy interface permits future replacements.

### Tier 3 — Specialized media generation

Examples:

- NVIDIA NIM FLUX.1-dev
- NVIDIA NIM FLUX.1-schnell
- SD 3.5 Large

Use for:

- photorealistic product images
- lifestyle imagery
- technical variants
- controlled image generation
- image regeneration and enhancement

## 1.4 Routing model

Routing is based on:

```text
effective_score =
  capability_fit
+ schema_fit
+ context_fit
+ health_score
+ latency_score
+ cost_score
+ operator_preference
- risk_penalty
- recent_failure_penalty
```

Example normalized scoring:

```text
S(model, task) =
  0.30C
+ 0.20Q
+ 0.15X
+ 0.10H
+ 0.10L
+ 0.10O
+ 0.05R
- P
```

Where:

- `C` = capability fit
- `Q` = output quality history
- `X` = context compatibility
- `H` = provider health
- `L` = latency suitability
- `O` = operator preference
- `R` = reliability
- `P` = penalties

The weights must be configurable by task class.

### Routing policy signature

```ts
export interface RoutingPolicy {
  name: string;
  taskTypes: string[];
  allowedModels?: string[];
  deniedModels?: string[];
  preferredTier?: ProviderTier;
  maximumEstimatedCost?: number;
  maximumLatencyMs?: number;
  consensusMode: "single" | "dual" | "triple";
  fallbackPolicy: FallbackPolicy;
  requireHumanApproval?: boolean;
}
```

## 1.5 Operator override and governance

There must be one logical configuration model with two interfaces:

- WordPress `wp-admin`
- HelmetsanManager

Neither interface writes vendor-specific configuration directly. Both call the same control-plane API.

### Configuration precedence

```text
Emergency runtime override
  > Job-level override
  > Project/workspace override
  > User preference
  > Task-class policy
  > Global default
  > Built-in safe default
```

Every override receives:

- actor ID
- timestamp
- scope
- expiration
- reason
- previous value
- new value
- audit event ID

### Example

```json
{
  "scope": "project",
  "scope_id": "helmetsan-catalog",
  "task_type": "product_description",
  "default_model": "gpt-5.6-luna",
  "fallback_models": [
    "nvidia:kimi-k3",
    "local:qwen2.5"
  ],
  "consensus_mode": "dual",
  "max_cost_usd": 0.08,
  "expires_at": null
}
```

### Conflict prevention

1. Both clients use optimistic locking.
2. Every configuration document has a `version`.
3. Writes require `If-Match` or equivalent version matching.
4. Changes are stored as immutable audit events.
5. The effective configuration is computed by the core service.
6. UI caches are never authoritative.
7. A job receives a frozen routing snapshot at submission time.
8. Later settings changes do not mutate an already-running job.

### API endpoints

```text
GET    /v1/config/effective
GET    /v1/config/overrides
PUT    /v1/config/overrides/{id}
DELETE /v1/config/overrides/{id}
POST   /v1/config/validate
GET    /v1/models
GET    /v1/providers/health
```

## 1.6 CLI contract

```bash
helmetsan task submit \
  --task-type product-copy \
  --input ./product.json \
  --policy dual-consensus \
  --model auto \
  --wait

helmetsan chain inspect <chain-id>
helmetsan chain resume <chain-id>
helmetsan chain cancel <chain-id>
helmetsan model health
helmetsan config effective --scope project:helmetsan-catalog
helmetsan media generate --spec ./image-job.json
```

Output formats:

```bash
--output table
--output json
--output ndjson
--output quiet
```

This enables use from any IDE terminal, CI runner, shell script, or cron.

---

# SECTION 2 — MULTI-PARALLEL SWARM AND CONCURRENT AGENT PIPELINE

## 2.1 DAG model

A task is a directed acyclic graph:

```text
ingest
  ├── normalize
  ├── extract claims
  ├── retrieve source facts
  └── inspect media
          │
          ▼
  parallel candidate generation
    ├── Kimi-K3
    ├── DeepSeek-R1
    ├── Llama 3.3
    └── local Qwen
          │
          ▼
    Luna policy arbitration
          │
          ▼
    schema validation
          │
          ▼
    human approval or publish
```

### DAG node

```ts
export interface DagNode {
  id: string;
  taskType: string;
  dependencies: string[];
  inputRefs: string[];
  outputSchema?: JsonSchema;
  execution: {
    routingPolicy: string;
    timeoutMs: number;
    retryPolicy: RetryPolicy;
    concurrencyGroup?: string;
  };
  idempotencyKey: string;
}
```

The scheduler must reject cycles before execution.

## 2.2 State machine

```text
PENDING
  ↓
READY
  ↓
RUNNING
  ├── SUCCEEDED
  ├── RETRY_WAIT
  ├── PAUSED
  ├── FAILED
  └── CANCELLED
```

A node may only be marked `SUCCEEDED` after:

1. provider response received,
2. output persisted,
3. schema validated,
4. usage event persisted,
5. checkpoint committed.

## 2.3 Bounded concurrency

Pools should exist at several levels:

```text
global pool
  ├── provider pool
  │     ├── model pool
  │     └── endpoint pool
  ├── tenant pool
  ├── task-class pool
  └── media GPU pool
```

Example configuration:

```json
{
  "global": { "max_in_flight": 64 },
  "providers": {
    "local-lmstudio": { "max_in_flight": 4 },
    "nvidia-nim": { "max_in_flight": 12 },
    "experiential-labs": { "max_in_flight": 6 }
  },
  "task_classes": {
    "image_generation": { "max_in_flight": 2 },
    "catalog_extraction": { "max_in_flight": 20 }
  }
}
```

## 2.4 Backpressure

Backpressure occurs when:

- queue depth exceeds threshold,
- provider concurrency is exhausted,
- rate-limit tokens are depleted,
- database write latency exceeds threshold,
- object storage upload queue is saturated.

The scheduler responds by:

1. stopping admission to the affected pool,
2. leaving tasks durable in `READY`,
3. increasing retry delay,
4. routing eligible work to alternate providers,
5. emitting an operational event.

No work is discarded because of a temporary capacity condition.

## 2.5 Rate-limit handling

Every provider has a token bucket:

```text
tokens(t + Δ) = min(capacity, tokens(t) + refill_rate × Δ)
```

A request consumes tokens based on estimated request weight.

On HTTP `429`:

- parse `Retry-After` where available,
- record provider rate-limit event,
- suspend the model or endpoint temporarily,
- move task to `RETRY_WAIT`,
- apply full-jitter exponential backoff.

```text
delay = min(max_delay, base × 2^attempt) × random(0.5, 1.5)
```

Retries must preserve the same idempotency key.

## 2.6 Circuit breaker

Each provider/model endpoint has an independent circuit.

```text
CLOSED ──failure threshold──> OPEN
OPEN ──cooldown elapsed──────> HALF_OPEN
HALF_OPEN ──success──────────> CLOSED
HALF_OPEN ──failure──────────> OPEN
```

Suggested defaults:

```json
{
  "failure_window": 60,
  "failure_threshold": 5,
  "open_seconds": 60,
  "half_open_probe_count": 2,
  "slow_call_threshold_ms": 45000,
  "slow_call_rate_threshold": 0.5
}
```

Failures include:

- connection timeout
- repeated 5xx
- malformed provider response
- authentication failure
- schema failure above threshold
- excessive latency

A single user-level validation error should not open a provider circuit.

## 2.7 Consensus policies

### Single-model

Lowest cost and latency.

```text
answer = model(input)
```

Use for routine, low-risk work.

### Dual consensus

Default:

```text
candidate_A = Luna(input)
candidate_B = Kimi(input)
final = adjudicator(Luna, Kimi, evidence)
```

If Luna is unavailable, a configured alternate arbiter may be used, or the result may be marked `NEEDS_REVIEW`.

### Triple consensus

```text
A = Kimi-K3(input)
B = DeepSeek-R1(input)
C = Llama-3.3(input)

final = Luna_arbitrate(A, B, C, source_evidence)
```

Luna must not blindly majority-vote. It should evaluate correctness against source evidence.

### Mathematical consensus formula

For claim `c`, model `i` returns:

- truth probability `p_i(c)`
- confidence `q_i(c)`
- evidence support `e_i(c)`
- model reliability `r_i`

Weighted support:

```text
W(c) = Σ_i [w_i × p_i(c) × q_i(c) × e_i(c) × r_i]
```

Normalized confidence:

```text
C(c) = W(c) / Σ_i [w_i × q_i(c) × e_i(c) × r_i]
```

A claim is accepted only if:

```text
C(c) ≥ τ_accept
AND contradiction_score(c) ≤ τ_contradiction
AND schema_valid = true
AND provenance_present = true
```

Recommended initial thresholds:

```text
τ_accept = 0.82
τ_contradiction = 0.20
```

For safety-critical or legal claims:

```text
τ_accept = 0.95
```

A model can be outvoted but not overruled when it identifies a hard source contradiction. Contradictions escalate to arbitration or human review.

## 2.8 Swarm observability

Each node emits:

- queued timestamp
- start timestamp
- provider request timestamp
- first-byte timestamp
- completion timestamp
- token counts
- estimated cost
- retry number
- circuit state
- output validation result
- parent/child node IDs

Metrics:

```text
helmetsan_dag_queue_depth
helmetsan_node_duration_seconds
helmetsan_provider_error_total
helmetsan_provider_429_total
helmetsan_consensus_disagreement_total
helmetsan_checkpoint_write_seconds
```

---

# SECTION 3 — ONE-MILLION-TOKEN CONTEXT ENGINEERING AND DURABLE ACTIVITY CHAINS

## 3.1 Context architecture

A 1-million-token context must not be treated as one undifferentiated prompt. It should be represented in three layers.

### Representation A — Raw Source Manifest

An immutable inventory of original materials:

```json
{
  "document_id": "catalog-2026-001",
  "sources": [
    {
      "source_id": "manufacturer-pdf",
      "uri": "object://source/catalog.pdf",
      "sha256": "…",
      "mime": "application/pdf",
      "byte_length": 1839201,
      "page_count": 48
    }
  ]
}
```

### Representation B — Normalized Claims

Every extracted fact becomes a claim:

```json
{
  "claim_id": "claim-0192",
  "subject": "Helmet X",
  "predicate": "shell_material",
  "object": "carbon fiber",
  "value": "carbon fiber",
  "source_refs": ["manufacturer-pdf:p12"],
  "confidence": 0.97,
  "status": "verified"
}
```

### Representation C — Critical Facts Header/Footer

The prompt begins and ends with high-priority facts:

```text
CRITICAL FACTS:
- Product: Helmet X
- Verified shell material: carbon fiber
- Unverified claims must not be presented as facts
- Source priority: manufacturer > distributor > editorial inference
- Output must use the provided JSON schema
```

The same compact facts block is repeated at the end to reduce attention decay.

## 3.2 Context packing algorithm

1. Parse source artifacts.
2. Deduplicate identical content by SHA-256.
3. Normalize encoding.
4. Segment into semantic chunks.
5. Extract claims.
6. Build source-reference graph.
7. Score chunks by task relevance.
8. Reserve output and system-token budget.
9. Place critical facts at beginning and end.
10