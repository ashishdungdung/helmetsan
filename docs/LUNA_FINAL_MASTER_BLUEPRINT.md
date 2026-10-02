# Helmetsan Production Blueprint

## 0. Executive Architecture

Helmetsan should be implemented as a **provider-neutral orchestration platform** with three independently deployable surfaces:

1. **WordPress Plugin**
   - Customer-facing CMS integration.
   - wp-admin configuration.
   - REST endpoints for missions, providers, media, activity chains, and health.
   - WordPress-native storage and Media Library integration.

2. **HelmetsanManager Mission Control**
   - Operator dashboard.
   - Fleet configuration, mission submission, live activity streaming, consensus inspection, provider health, usage, and media review.
   - Must communicate through APIs only; it must not directly manipulate WordPress internals or provider state.

3. **Headless Agent Runtime**
   - CLI and CI-compatible orchestration engine.
   - DAG scheduler.
   - Bounded parallelism.
   - Context assembly.
   - Consensus.
   - Durable SQLite activity chains.
   - Provider adapters.
   - Media generation and validation.

The system must work identically from:

- Antigravity IDE
- Cursor
- Zed
- Claude Code
- VS Code
- Shell
- GitHub Actions
- GitLab CI
- Docker
- Direct HTTP clients

The IDE is an interface, not a runtime dependency.

---

# 1. Core Design Principles

## 1.1 IDE Independence

All durable behavior must live in source-controlled files and APIs:

```text
helmetsan/
├── contracts/
├── config/
├── packages/
├── HelmetsanWeb/
├── HelmetsanManager/
├── scripts/
├── tests/
├── migrations/
└── docker/
```

No essential task may depend on:

- IDE-specific prompts
- IDE-specific memory
- IDE-specific agent state
- hidden editor extensions
- local GUI state
- undocumented model context

Every operation must be reproducible using:

```bash
npm run mission -- --file missions/example.yaml
python HelmetsanWeb/scripts/agent_activity_chains.py resume --chain-id ...
curl -X POST https://example.com/wp-json/helmetsan/v1/missions
```

The canonical runtime contract should be HTTP and CLI based.

---

## 1.2 Control Plane and Data Plane Separation

### Control Plane

Responsible for:

- Provider definitions
- Model routing rules
- concurrency limits
- cost limits
- consensus policies
- feature flags
- operator permissions
- mission templates

### Data Plane

Responsible for:

- prompts
- context payloads
- model calls
- generated images
- normalized claims
- activity steps
- provider usage
- artifacts

The WordPress plugin and HelmetsanManager may both expose control-plane settings, but they must not share mutable state directly.

---

## 1.3 Two Independent Configuration Authorities

### WordPress Configuration

Stored in WordPress options:

```text
helmetsan_settings
helmetsan_provider_profiles
helmetsan_routing_profiles
helmetsan_media_settings
helmetsan_security_settings
```

### HelmetsanManager Configuration

Stored in:

```text
config/manager.yaml
.env
SQLite control database
```

Each environment has a unique source identifier:

```text
source_id = wordpress:site-uuid
source_id = manager:environment-name
```

No configuration object should be silently synchronized in both directions.

Synchronization, if enabled, must be explicit and versioned:

```text
POST /api/config/export
POST /api/config/import
```

Every imported configuration must include:

- source
- version
- timestamp
- operator
- checksum
- conflict strategy

Supported conflict strategies:

```text
reject
replace
merge-explicit
dry-run
```

---

# 2. Multi-Model Fleet

## 2.1 Provider Adapter Interface

Every model provider implements the same interface:

```typescript
interface ModelProvider {
  id: string;
  capabilities(): ProviderCapabilities;
  healthCheck(): Promise<HealthStatus>;
  estimate(request: ModelRequest): CostEstimate;
  execute(request: ModelRequest): Promise<ModelResponse>;
  cancel(requestId: string): Promise<void>;
}
```

```typescript
interface ModelRequest {
  requestId: string;
  missionId: string;
  taskId: string;
  model: string;
  messages: Message[];
  inputArtifacts?: ArtifactRef[];
  responseSchema?: JSONSchema;
  temperature?: number;
  maxTokens?: number;
  timeoutMs: number;
  metadata: Record<string, string>;
}
```

```typescript
interface ModelResponse {
  provider: string;
  model: string;
  output: string;
  structuredOutput?: unknown;
  usage: {
    inputTokens?: number;
    outputTokens?: number;
    cachedTokens?: number;
    estimatedCostUsd?: number;
  };
  finishReason: string;
  latencyMs: number;
  rawMetadata?: Record<string, unknown>;
}
```

The adapter layer must hide provider-specific details such as:

- OpenAI-compatible endpoints
- NIM endpoint formats
- LM Studio routes
- authentication headers
- streaming formats
- model-specific parameters
- retry behavior

---

## 2.2 Initial Fleet

The first fleet should be represented as configurable profiles rather than hard-coded assumptions.

### Local Silicon Grid

| Profile | Intended Usage |
|---|---|
| `local-gemma-12b` | classification, extraction, summarization, low-cost drafting |
| `local-qwen-2.5` | coding, structured transformation, local reasoning |

Endpoint example:

```text
http://127.0.0.1:1234/v1
```

The endpoint must be configurable because LM Studio ports and model names may differ.

### NVIDIA NIM

| Profile | Intended Usage |
|---|---|
| `nim-kimi-k3` | large-context synthesis and document analysis |
| `nim-deepseek-r1` | deep reasoning and verification |
| `nim-llama-3.3-70b` | high-quality general generation |
| `nim-llama-3.2-vision` | image understanding and visual QA |
| `nim-flux-dev` | photorealistic image generation |
| `nim-sd35` | alternate image generation and variation |

NIM model IDs, routes, and quotas must be configuration-driven. Do not assume that a vendor model name is identical to the deployment name.

### Experiential Labs

| Profile | Intended Usage |
|---|---|
| `experiential-gpt-5.6-luna` | premium reasoning, synthesis, escalation, final review |

The platform must treat GPT-5.6-Luna as another provider adapter, not as a privileged hard-coded dependency.

---

## 2.3 Routing Policy

Routing should use a policy engine with the following inputs:

- task class
- required capabilities
- context size
- latency budget
- quality tier
- cost budget
- current provider health
- remaining quota
- operator override
- data sensitivity
- consensus requirement

Example:

```yaml
routing:
  default:
    provider_order:
      - local-gemma-12b
      - nim-llama-3.3-70b
      - experiential-gpt-5.6-luna

  coding:
    provider_order:
      - local-qwen-2.5
      - nim-deepseek-r1
      - experiential-gpt-5.6-luna

  long_context:
    provider_order:
      - nim-kimi-k3
      - experiential-gpt-5.6-luna

  image_generation:
    provider_order:
      - nim-flux-dev
      - nim-sd35

  visual_validation:
    provider_order:
      - nim-llama-3.2-vision
```

Operator overrides must be accepted through:

- wp-admin settings
- HelmetsanManager UI
- CLI flags
- mission YAML
- REST request payload

Precedence:

```text
per-task override
→ mission override
→ operator routing profile
→ task-class profile
→ global default
```

---

## 2.4 Cost Policy

Use a cost-aware routing score:

```text
score =
  quality_weight * quality
+ availability_weight * availability
+ latency_weight * latency_score
- cost_weight * estimated_cost
```

Local models should be preferred when:

- data is non-sensitive
- task complexity is low or moderate
- context fits available local context
- quality threshold is satisfied

Premium providers should be reserved for:

- escalations
- difficult reasoning
- final consensus arbitration
- high-value customer-facing content
- failure recovery

Every request must create a `provider_usage_events` record, including zero-cost local calls.

---

# 3. DAG Task Runner and Parallel Swarm

## 3.1 Mission Model

A mission consists of:

```text
Mission
 ├── Task A
 ├── Task B
 ├── Task C
 └── Finalizer
```

Each task declares:

```yaml
id: extract_product_facts
class: extraction
depends_on: []
provider_policy: local-first
consensus: single
timeout_seconds: 300
input_artifacts:
  - source_manifest
output_schema: schemas/product-facts.json
```

Tasks may only depend on completed upstream tasks. Cycles must be rejected before execution.

---

## 3.2 DAG States

Mission states:

```text
created
validated
queued
running
paused
waiting_retry
partially_completed
completed
failed
cancelled
timed_out
```

Task states:

```text
pending
ready
running
blocked
retrying
succeeded
failed
skipped
cancelled
```

State transitions must be validated centrally.

---

## 3.3 Bounded Concurrency Pools

Implement separate semaphores:

```text
global_pool
provider_pool[provider_id]
task_class_pool[task_class]
mission_pool[mission_id]
```

Example:

```yaml
concurrency:
  global: 12
  providers:
    local-gemma-12b: 2
    local-qwen-2.5: 2
    nim-kimi-k3: 4
    nim-deepseek-r1: 3
    experiential-gpt-5.6-luna: 2
  task_classes:
    extraction: 6
    coding: 4
    reasoning: 3
    image_generation: 2
    validation: 4
```

A task must acquire all required permits before execution.

This prevents:

- local GPU starvation
- provider quota exhaustion
- image generation flooding
- one task class monopolizing the fleet

---

## 3.4 Token Bucket Rate Limiting

Each provider and model receives a token bucket:

```text
capacity
refill_rate
current_tokens
last_refill_at
```

Requests consume estimated input plus output tokens. If token capacity is insufficient:

- queue the task
- emit a backpressure event
- do not busy-loop
- wake on refill timer

Rate limits should exist at:

- provider level
- model level
- API key level
- mission level
- tenant/site level

---

## 3.5 Retry and Circuit Breakers

Retry only transient failures:

- HTTP 408
- HTTP 429
- HTTP 500
- HTTP 502
- HTTP 503
- network timeout
- temporary provider unavailability

Do not retry:

- invalid credentials
- malformed request
- schema validation failure
- policy rejection
- deterministic prompt error

Use exponential backoff with jitter:

```text
delay = min(max_delay, base_delay * 2^attempt) + random_jitter
```

Circuit breaker states:

```text
closed
open
half_open
```

Suggested defaults:

```yaml
circuit_breaker:
  failure_threshold: 5
  rolling_window_seconds: 120
  open_seconds: 60
  half_open_probe_count: 2
```

When open, the router immediately selects a fallback provider.

---

## 3.6 Consensus Modes

### Single

One provider call.

Use for:

- low-risk extraction
- formatting
- drafts
- inexpensive transformations

### Dual

Two independent calls, followed by comparison.

Use for:

- product claims
- technical specifications
- customer-facing content
- code changes

### Triple

Three calls, followed by deterministic adjudication or a fourth arbitration call.

Use for:

- safety claims
- regulatory wording
- irreversible migrations
- production deployment plans
- high-value visual validation

Consensus must compare structured outputs, not only prose.

```text
normalize(output)
→ canonicalize fields
→ compare claims
→ detect conflicts
→ calculate agreement
→ adjudicate unresolved conflicts
```

Never declare consensus merely because all responses are non-empty.

---

# 4. One-Million-Token Context Engineering

## 4.1 Triple Representation

Every large source must be represented three ways.

### A. Raw Source Manifest

Preserves original material:

```json
{
  "document_id": "doc-001",
  "source_type": "xml",
  "source_uri": "...",
  "sha256": "...",
  "mime_type": "application/xml",
  "byte_length": 12899322,
  "sections": [],
  "raw_artifact_uri": "..."
}
```

Supported raw forms:

- XML
- JSON-L
- Markdown
- HTML
- PDF extraction
- database export
- image metadata
- source code

### B. Normalized Claims

Convert source content into atomic, attributable claims:

```json
{
  "claim_id": "claim-0001",
  "subject": "Helmet X",
  "predicate": "shell_material",
  "object": "3K carbon fiber",
  "source_location": "catalog.xml#/products/12/material",
  "confidence": 0.98,
  "status": "verified"
}
```

Each claim must carry:

- source reference
- extraction method
- confidence
- timestamp
- provenance
- contradiction status

### C. Critical Facts Header/Footer

Generate a compact high-priority summary that is injected both at the start and end of prompts.

Header:

```text
CRITICAL FACTS — READ BEFORE PROCESSING
- Product: ...
- Approved material claims: ...
- Forbidden claims: ...
- Required output schema: ...
- Known conflicts: ...
- Safety constraints: ...
```

Footer:

```text
FINAL VALIDATION CHECK
- Do not contradict critical facts.
- Do not invent specifications.
- Preserve units and model numbers.
- Report unresolved conflicts explicitly.
```

Duplicating critical facts at both ends reduces lost-in-the-middle failures.

---

## 4.2 Context Assembly Layers

The context builder should assemble:

```text
1. System policy
2. Task contract
3. Critical Facts Header
4. Required schema
5. Relevant normalized claims
6. Source excerpts
7. Prior activity checkpoints
8. Agent outputs
9. Critical Facts Footer
```

Do not blindly concatenate all available content.

Use relevance filters:

- task-specific claim predicates
- dependency outputs
- semantic similarity
- exact entity match
- source authority
- contradiction priority
- recency
- operator-pinned facts

---

## 4.3 Context Budgeting

The context engine must calculate:

```text
estimated_input_tokens
reserved_output_tokens
safety_margin
provider_max_context
```

Safe input budget:

```text
provider_max_context
- reserved_output_tokens
- safety_margin
```

If the source exceeds budget:

1. preserve critical facts
2. preserve schema and task instructions
3. preserve conflicting claims
4. preserve operator-pinned excerpts
5. summarize low-priority material
6. retain source references
7. record what was compressed

Every compression step creates an artifact:

```text
context_compaction_v3.json
```

---

# 5. Durable Activity Chains

## 5.1 SQLite Schema

### `agent_activity_chains`

```sql
CREATE TABLE agent_activity_chains (
  chain_id TEXT PRIMARY KEY,
  mission_id TEXT NOT NULL,
  status TEXT NOT NULL,
  current_step_id TEXT,
  config_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  resumed_at TEXT,
  completed_at TEXT,
  error_code TEXT,
  error_message TEXT
);
```

### `agent_activity_steps`

```sql
CREATE TABLE agent_activity_steps (
  step_id TEXT PRIMARY KEY,
  chain_id TEXT NOT NULL,
  task_id TEXT NOT NULL,
  attempt INTEGER NOT NULL DEFAULT 1,
  status TEXT NOT NULL,
  provider_id TEXT,
  model_id TEXT,
  input_artifact_uri TEXT,
  output_artifact_uri TEXT,
  started_at TEXT,
  completed_at TEXT,
  timeout_seconds INTEGER NOT NULL,
  error_code TEXT,
  error_message TEXT,
  FOREIGN KEY(chain_id) REFERENCES agent_activity_chains(chain_id)
);
```

### `agent_activity_checkpoints`

```sql
CREATE TABLE agent_activity_checkpoints (
  checkpoint_id TEXT PRIMARY KEY,
  chain_id TEXT NOT NULL,
  step_id TEXT,
  checkpoint_type TEXT NOT NULL,
  state_json TEXT NOT NULL,
  artifact_uri TEXT,
  created_at TEXT NOT NULL,
  checksum TEXT NOT NULL
);
```

### `provider_usage_events`

```sql
CREATE TABLE provider_usage_events (
  event_id TEXT PRIMARY KEY,
  chain_id TEXT,
  step_id TEXT,
  provider_id TEXT NOT NULL,
  model_id TEXT NOT NULL,
  request_id TEXT NOT NULL,
  input_tokens INTEGER,
  output_tokens INTEGER,
  cached_tokens INTEGER,
  estimated_cost_usd REAL,
  latency_ms INTEGER,
  http_status INTEGER,
  success INTEGER NOT NULL,
  created_at TEXT NOT NULL
);
```

Add indexes for:

```sql
chain_id
mission_id
status
provider_id
created_at
request_id
```

---

## 5.2 Checkpoint Policy

Create checkpoints:

- before provider call
- after provider call
- after parsing
- after schema validation
- after consensus
- after artifact write
- after every retry
- before mission finalization

On restart:

1. load chain
2. identify last durable checkpoint
3. verify artifact checksums
4. recover completed tasks
5. requeue incomplete tasks
6. avoid duplicate side effects using idempotency keys

---

## 5.3 Timeouts and Heartbeats

Default timeout:

```text
300 seconds
```

Maximum supported timeout:

```text
1800 seconds
```

Timeouts should be task-specific:

```yaml
timeouts:
  extraction: 300
  reasoning: 900
  long_context: 1800
  image_generation: 900
  visual_validation: 600
```

For long-running tasks:

- emit SSE heartbeat every 10–20 seconds
- update `last_heartbeat_at`
- report queue, provider, elapsed time, and phase
- distinguish active execution from stalled execution

Heartbeat event:

```json
{
  "event": "heartbeat",
  "chain_id": "...",
  "step_id": "...",
  "phase": "provider_call",
  "elapsed_seconds": 412,
  "timestamp": "..."
}
```

---

# 6. Photorealistic NVIDIA NIM Media Pipeline

## 6.1 Prompt Architecture

Prompts must be structured, versioned, and reusable.

### Helmet Prompt Template

```text
Photorealistic studio product photograph of a premium motorcycle helmet,
three-quarter front-left view, complete helmet visible, accurate aerodynamic
shell geometry, realistic helmet proportions, high-resolution 3K carbon-fiber
weave visible across the shell, clean clear visor with realistic optical
reflections, correctly positioned Pinlock mounting pins, functional visor
mechanism, accurately rendered D-rings and chin strap hardware, precise seams,
premium matte/gloss finish as specified, neutral studio background, softbox
lighting, controlled highlights, physically accurate shadows, commercial
catalog photography, sharp focus, no text, no logos unless explicitly
provided, no extra vents, no distorted hardware, no duplicated straps,
no malformed visor, no floating parts.
```

### Motorcycle Prompt Template

```text
Photorealistic premium motorcycle product image, accurate wheelbase and
mechanical proportions, correctly modeled frame, forks, brakes, chain or
belt drive, engine casing, controls, tires, lighting, and exhaust routing,
studio lighting, realistic metallic and painted surfaces, natural tire
contact with ground, no warped geometry, no duplicate wheels, no extra
controls, no impossible mechanical connections, no text or watermark.
```

### Accessories Prompt Template

```text
Photorealistic commercial product photograph of [accessory], accurate
dimensions and material behavior, correct stitching, fasteners, buckles,
mounting points, textures, and surface finish, clean studio lighting,
neutral background, realistic shadows, no invented logos, no deformation,
no duplicate components, no watermark, no text unless specified.
```

Negative prompts should be model-specific and stored separately.

---

## 6.2 Image Generation Request

The NIM adapter must support:

```json
{
  "model": "configured-nim-image-model",
  "prompt": "...",
  "negative_prompt": "...",
  "width": 1536,
  "height": 1536,
  "steps": 30,
  "guidance_scale": 5.5,
  "seed": 123456,
  "num_images": 1,
  "response_format": "base64"
}
```

All generation parameters must be persisted in the artifact manifest.

---

## 6.3 Media Lifecycle

```text
prompt creation
→ request validation
→ NIM generation
→ binary decode
→ MIME validation
→ malware scan
→ dimensions check
→ perceptual hash
→ duplicate check
→ lossless master storage
→ derivative generation
→ WordPress upload
→ R2 upload
→ metadata persistence
→ visual QA
→ publish or quarantine
```

---

## 6.4 Storage Policy

### Master

- PNG or lossless WebP
- preserved for editing and audit
- stored in object storage
- immutable content hash

### Delivery Versions

- WebP quality 82–90
- AVIF where supported
- JPEG fallback only when required

### Derivatives

```text
original
2048px
1200px
768px
480px
320px thumbnail
square crop
social crop
```

Store:

```json
{
  "asset_id": "...",
  "sha256": "...",
  "phash": "...",
  "width": 1536,
  "height": 1536,
  "mime": "image/webp",
  "byte_size": 481992,
  "source_prompt_hash": "...",
  "model": "...",
  "seed": 123456,
  "created_at": "..."
}
```

---

## 6.5 pHash Deduplication

Use pHash or equivalent perceptual hashing:

- exact duplicate: Hamming distance `0`
- likely duplicate: configurable threshold, initially `<= 6`
- visually similar candidate: `<= 12`
- unrelated: above configured threshold

Do not automatically delete near-duplicates. Mark them:

```text
exact_duplicate
probable_duplicate
variant
unique
needs_review
```

---

## 6.6 Validation

Automated image validation should check:

- file integrity
- MIME type
- dimensions
- alpha-channel policy
- corrupted image detection
- pHash
- OCR for unwanted text
- logo/watermark detection
- visual geometry using vision model
- required component presence
- prohibited artifact presence

For helmets specifically, inspect:

- shell symmetry
- visor placement
- Pinlock pins
- chin strap
- D-rings
- shell weave
- vents
- shell-to-liner relationship
- absence of impossible hardware

Failed images enter quarantine, not the public Media Library.

---

# 7. Concrete Code Architecture

## 7.1 WordPress Plugin

Root:

```text
HelmetsanWeb/helmetsan-core/
```

### `NvidiaNimProvider.php`

Responsibilities:

- NIM authentication
- text and image request handling
- endpoint selection
- timeout enforcement
- retry classification
- response normalization
- usage extraction
- error mapping
- request correlation IDs

Methods:

```php
healthCheck(): ProviderHealth
complete(ModelRequest $request): ModelResponse
generateImage(ImageRequest $request): ImageResponse
estimate(ModelRequest $request): CostEstimate
```

NIM URL, model ID, API key, and timeout must come from provider profiles.

### `ProviderRegistry.php`

Responsibilities:

- register providers
- resolve provider by ID
- expose capabilities
- validate configuration
- health-check providers
- prevent duplicate provider IDs

Adapters:

```text
LocalLmStudioProvider
NvidiaNimProvider
ExperientialLabsProvider
```

### `Config.php`

Responsibilities:

- typed settings access
- defaults
- schema validation
- encryption/decryption for secrets
- environment overrides
- configuration versioning
- migration support

Never expose secrets through REST responses or HTML.

### REST Controllers

Create:

```text
Rest/MissionsController.php
Rest/ProvidersController.php
Rest/ActivityChainsController.php
Rest/MediaController.php
Rest/HealthController.php
Rest/ConfigController.php
```

Routes:

```text
POST   /helmetsan/v1/missions
GET    /helmetsan/v1/missions/{id}
POST   /helmetsan/v1/missions/{id}/cancel
GET    /helmetsan/v1/activity-chains/{id}
GET    /helmetsan/v1/activity-chains/{id}/events
GET    /helmetsan/v1/providers
POST   /helmetsan/v1/providers/{id}/health
POST   /helmetsan/v1/media/generate
POST   /helmetsan/v1/media/{id}/approve
POST   /helmetsan/v1/media/{id}/reject
GET    /helmetsan/v1/usage
```

Security requirements:

- WordPress capability checks
- nonce validation for wp-admin
- application passwords or signed tokens for external systems
- per-route schemas
- request size limits
- audit logging
- rate limits
- no arbitrary provider URL access

---

## 7.2 Headless Runtime

### `scripts/nvidia_nim_fleet.mjs`

Responsibilities:

- provider fleet CLI
- health checks
- model listing where supported
- text generation
- image generation
- failover probing
- usage reporting
- smoke tests

Commands:

```bash
node scripts/nvidia_nim_fleet.mjs health
node scripts/nvidia_nim_fleet.mjs text --provider nim-llama-3.3-70b
node scripts/nvidia_nim_fleet.mjs image --profile helmet-hero
node scripts/nvidia_nim_fleet.mjs failover-probe
```

### `HelmetsanWeb/scripts/agent_activity_chains.py`

Responsibilities:

- SQLite migrations
- chain creation
- DAG execution
- checkpointing
- resume
- retry
- cancellation
- SSE event emission
- usage persistence

Commands:

```bash
python .../agent_activity_chains.py run mission.yaml
python .../agent_activity_chains.py resume --chain-id CHAIN
python .../agent_activity_chains.py status --chain-id CHAIN
python .../agent_activity_chains.py cancel --chain-id CHAIN
python .../agent_activity_chains.py migrate
```

### `HelmetsanWeb/scripts/generate_nim_media.py`

Responsibilities:

- prompt profile selection
- NIM image generation
- image validation
- pHash
- derivatives
- WordPress upload
- R2 upload
- manifest creation
- quarantine handling

Commands:

```bash
python .../generate_nim_media.py generate --profile helmet-hero.yaml
python .../generate_nim_media.py audit --asset-dir ...
python .../generate_nim_media.py dedupe --asset-dir ...
python .../generate_nim_media.py publish --asset-id ...
```

---

## 7.3 HelmetsanManager

### `server.js`

Responsibilities:

- serve dashboard
- authenticate operators
- proxy or call canonical APIs
- SSE connection management
- configuration validation
- audit logging
- CSRF protection
- rate limiting
- mission lifecycle endpoints

Do not duplicate orchestration logic in `server.js`. It should call the shared runtime or API.

### `index.html`

Include:

- mission dashboard
- provider fleet panel
- model override controls
- DAG visualization
- activity chain timeline
- consensus comparison
- usage/cost panel
- media review queue
- health and circuit-breaker indicators

### `app.js`

Responsibilities:

- API client
- SSE subscription
- state rendering
- optimistic UI only for non-authoritative actions
- conflict/error display
- operator override submission
- no durable state beyond local UI preferences

The backend remains authoritative.

---

# 8. API and Event Contracts

Every request should include:

```text
X-Request-ID
X-Mission-ID
X-Idempotency-Key
```

Every event should include:

```json
{
  "event_id": "...",
  "event_type": "task.completed",
  "mission_id": "...",
  "chain_id": "...",
  "task_id": "...",
  "sequence": 42,
  "timestamp": "...",
  "payload": {}
}
```

Event sequence numbers must be monotonic per chain.

SSE reconnect behavior:

```text
GET /events?chain_id=...&last_event_id=42
```

The server must replay missed events from durable storage.

---

# 9. Verification and Validation Plan

## 9.1 Unit Tests

### Provider Layer

- request serialization
- response normalization
- timeout behavior
- error classification
- retry classification
- usage parsing
- endpoint configuration
- secret redaction

### Scheduler

- DAG validation
- cycle detection
- dependency ordering
- bounded concurrency
- token bucket behavior
- backpressure
- cancellation
- retry and resume
- circuit breaker transitions

### Context Engine

- claim normalization
- provenance preservation
- critical-facts placement
- token budgeting
- compaction
- conflict detection
- schema injection

### Media

- MIME validation
- image decoding
- derivative generation
- pHash computation
- duplicate classification
- metadata persistence
- quarantine flow

### WordPress

- capability checks
- REST schema validation
- nonce handling
- option migrations
- Media Library attachment creation
- R2 failure recovery

---

## 9.2 Integration Tests

Run against mocked provider servers:

- local LM Studio-compatible endpoint
- NIM-compatible endpoint
- Experiential Labs-compatible endpoint

Test:

1. successful single-provider mission
2. provider timeout
3. provider returns HTTP 429
4. circuit breaker opens
5. fallback provider succeeds
6. dual consensus agreement
7. triple consensus disagreement
8. process termination and resume
9. duplicate idempotency key
10. SSE reconnect and replay
11. image generation and upload
12. WordPress upload succeeds but R2 fails
13. R2 succeeds but WordPress fails
14. media quarantine
15. configuration conflict detection

---

## 9.3 Failover Probes

Scheduled probes should test:

- provider reachability
- authentication
- model availability
- latency
- minimal generation
- structured output compliance
- image generation availability
- quota/rate-limit headers

Probe results must be stored and visualized. A provider should not be considered healthy solely because its HTTP endpoint responds.

---

## 9.4 Consensus Verification

Create golden fixtures containing:

- matching answers
- numeric disagreement
- unit disagreement
- missing field
- contradictory safety claim
- hallucinated specification
- malformed schema response

The adjudicator must:

- identify conflicts
- preserve provenance
- refuse unsupported claims
- report unresolved disagreement
- never silently choose a majority answer when source authority is unequal

---

## 9.5 Media Integrity Audits

For every asset:

```text
sha256 verified
mime verified
dimensions verified
pHash recorded
master exists
derivatives exist
WordPress attachment valid
R2 object valid
metadata consistent
prompt manifest present
```

Run nightly and before production publication.

---

# 10. Security and Governance

Implement:

- encrypted provider secrets
- least-privilege WordPress capabilities
- no secrets in logs
- signed manager-to-WordPress requests
- TLS-only production communication
- allowlisted provider hosts
- prompt and artifact size limits
- malware scanning for uploaded media
- audit logs for model and configuration changes
- operator identity on every override
- data-retention policy
- deletion workflow for source artifacts and generated media

Sensitive data policy should be explicit:

```yaml
data_policy:
  allow_local: true
  allow_nim: true
  allow_premium_provider: false
  redact_before_external_provider: true
```

---

# 11. Deployment Strategy

## Phase 1: Contract Foundation

- define JSON Schemas
- define provider interface
- create configuration model
- create SQLite migrations
- implement request IDs and idempotency
- establish CI

## Phase 2: Single Provider Runtime

- implement local LM Studio adapter
- implement basic DAG runner
- implement checkpoints
- implement CLI execution
- add unit tests

## Phase 3: NIM Integration

- implement `NvidiaNimProvider.php`
- implement Node and Python NIM clients
- add health probes
- add rate limits
- add fallback routing
- validate configured model IDs and endpoints

## Phase 4: Consensus and Context

- implement triple representation
- add context budgeting
- add dual/triple consensus
- add conflict reports
- add long-running SSE activity

## Phase 5: Media Pipeline

- implement Flux/SD adapters
- implement validation
- implement pHash
- implement derivatives
- implement WordPress and R2 delivery
- add quarantine review UI

## Phase 6: Mission Control

- implement provider dashboard
- implement DAG view
- implement activity timeline
- implement consensus comparison
- implement media review

## Phase 7: Staging

- deploy with staging credentials
- run synthetic missions
- run failover probes
- test restart recovery
- test quota exhaustion
- verify logs and metrics
- perform media audit

## Phase 8: Production

Production release gates:

- all migrations tested
- all secrets provisioned
- backup and restore verified
- health probes passing
- rollback artifact available
- database schema version recorded
- staging smoke tests green
- operator runbook approved

---

# 12. Production SLOs

Suggested initial targets:

```text
mission state durability: 99.99%
activity event loss: zero tolerated
provider request correlation: 100%
resume after process restart: 100%
successful transient failover: >= 95%
SSE heartbeat interval: <= 20 seconds
critical media integrity: 100%
unapproved media publication: zero
secret exposure in logs: zero
```

Track:

- mission success rate
- task retry rate
- provider latency
- provider error rate
- circuit-breaker openings
- token consumption
- estimated cost
- consensus disagreement rate
- context compaction rate
- duplicate media rate
- WordPress/R2 synchronization failures

---

# 13. Required Operating Rule

The most important implementation rule is:

> WordPress, HelmetsanManager, and the CLI must all invoke the same provider-neutral contracts and durable runtime behavior. None of them may become the hidden owner of mission state.

The final architecture should therefore be:

```text
Antigravity / Cursor / Zed / Claude Code / VS Code / CI
                         │
                         ▼
              CLI and HTTP Contracts
                         │
                         ▼
              Shared Orchestration Runtime
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
     WordPress      Manager UI      Headless CLI
          │              │              │
          └──────────────┼──────────────┘
                         ▼
          Provider Registry and Router
                         │
       ┌─────────────────┼─────────────────┐
       ▼                 ▼                 ▼
 Local Silicon       NVIDIA NIM       Experiential Labs
```

This structure provides:

- IDE independence
- operator-controlled model selection
- cost-aware routing
- parallel agent execution
- durable restart-safe workflows
- million-token context discipline
- production-grade image generation
- WordPress and R2 delivery
- verifiable consensus
- auditable provider usage
- safe failover and deployment rollback