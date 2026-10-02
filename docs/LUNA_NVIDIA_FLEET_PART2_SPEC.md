# 3.4 Activity Chains & Long Timeout Windows

Long-running agent work must be modeled as a durable activity chain rather than as one HTTP request. A chain may contain research, planning, tool calls, image generation, WordPress mutations, validation, and human approval steps.

The orchestration layer must therefore support:

- resumable execution;
- idempotent tool calls;
- per-step retries;
- heartbeat and checkpoint telemetry;
- cancellation;
- operator inspection;
- timeout budgets that increase with task complexity;
- recovery after PHP-FPM, worker, network, or provider failure.

## 3.4.1 Long-Horizon Agent Run Model

A run consists of:

```text
activity_chain
 ├── activity_step: plan
 ├── activity_step: retrieve_catalog_data
 ├── activity_step: generate_concepts
 ├── activity_step: render_images
 ├── activity_step: upload_assets
 ├── activity_step: draft_wordpress_content
 ├── activity_step: validate
 └── activity_step: publish_or_request_approval
```

Each step must have:

- a deterministic `step_key`;
- an idempotency key;
- an input hash;
- a state transition record;
- a retry count;
- a timeout budget;
- a provider/model identifier;
- a checkpoint payload;
- a final result reference.

The HTTP request that creates or advances a chain must return quickly. Long work executes through a queue worker, CLI worker, Action Scheduler task, or external orchestration process.

Recommended lifecycle:

```text
queued
  → running
  → waiting_tool
  → waiting_approval
  → retrying
  → completed
```

Terminal states:

```text
failed
cancelled
expired
partially_completed
```

No worker should rely on PHP process memory as the source of truth.

## 3.4.2 SQLite Schema

SQLite is appropriate for a single-node or low-to-moderate-volume control plane. Production deployments with multiple independent workers may use PostgreSQL with the same logical schema.

```sql
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA busy_timeout = 10000;

CREATE TABLE IF NOT EXISTS agent_activity_chains (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    chain_uuid          TEXT NOT NULL UNIQUE,
    tenant_id           TEXT NOT NULL,
    parent_chain_uuid   TEXT,
    requested_by        TEXT NOT NULL,
    objective           TEXT NOT NULL,
    status              TEXT NOT NULL DEFAULT 'queued'
                        CHECK (status IN (
                            'queued',
                            'running',
                            'waiting_tool',
                            'waiting_approval',
                            'retrying',
                            'completed',
                            'failed',
                            'cancelled',
                            'expired',
                            'partially_completed'
                        )),
    priority            INTEGER NOT NULL DEFAULT 50,
    model_policy        TEXT NOT NULL,
    input_json          TEXT NOT NULL,
    output_json         TEXT,
    context_json        TEXT,
    idempotency_key     TEXT NOT NULL UNIQUE,
    timeout_budget_s    INTEGER NOT NULL DEFAULT 300,
    deadline_at         TEXT,
    started_at          TEXT,
    heartbeat_at        TEXT,
    completed_at        TEXT,
    cancelled_at        TEXT,
    last_error_code     TEXT,
    last_error_message  TEXT,
    created_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_chains_status_priority
    ON agent_activity_chains(status, priority, created_at);

CREATE INDEX IF NOT EXISTS idx_chains_heartbeat
    ON agent_activity_chains(status, heartbeat_at);

CREATE TABLE IF NOT EXISTS agent_activity_steps (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    chain_id            INTEGER NOT NULL,
    step_uuid            TEXT NOT NULL UNIQUE,
    step_key             TEXT NOT NULL,
    sequence_no         INTEGER NOT NULL,
    step_type            TEXT NOT NULL,
    status              TEXT NOT NULL DEFAULT 'queued'
                        CHECK (status IN (
                            'queued',
                            'running',
                            'waiting_tool',
                            'waiting_approval',
                            'retrying',
                            'completed',
                            'failed',
                            'cancelled',
                            'expired'
                        )),
    provider             TEXT,
    model               TEXT,
    request_hash        TEXT,
    input_json          TEXT,
    output_json         TEXT,
    checkpoint_json     TEXT,
    attempt_count       INTEGER NOT NULL DEFAULT 0,
    max_attempts        INTEGER NOT NULL DEFAULT 3,
    timeout_budget_s    INTEGER NOT NULL DEFAULT 300,
    started_at          TEXT,
    heartbeat_at        TEXT,
    completed_at        TEXT,
    next_retry_at       TEXT,
    error_code          TEXT,
    error_message       TEXT,
    created_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(chain_id, step_key),
    FOREIGN KEY(chain_id) REFERENCES agent_activity_chains(id)
        ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_steps_claimable
    ON agent_activity_steps(status, next_retry_at, created_at);

CREATE TABLE IF NOT EXISTS agent_activity_checkpoints (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    chain_id            INTEGER NOT NULL,
    step_id             INTEGER,
    checkpoint_uuid     TEXT NOT NULL UNIQUE,
    event_type          TEXT NOT NULL,
    sequence_no         INTEGER NOT NULL,
    progress_percent    REAL,
    phase               TEXT,
    message             TEXT,
    metrics_json        TEXT,
    state_json          TEXT,
    created_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(chain_id) REFERENCES agent_activity_chains(id)
        ON DELETE CASCADE,
    FOREIGN KEY(step_id) REFERENCES agent_activity_steps(id)
        ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_checkpoints_chain_sequence
    ON agent_activity_checkpoints(chain_id, sequence_no);

CREATE TABLE IF NOT EXISTS agent_activity_events (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    chain_id            INTEGER NOT NULL,
    step_id             INTEGER,
    event_type          TEXT NOT NULL,
    event_json          TEXT NOT NULL,
    trace_id            TEXT,
    created_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(chain_id) REFERENCES agent_activity_chains(id)
        ON DELETE CASCADE,
    FOREIGN KEY(step_id) REFERENCES agent_activity_steps(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS provider_usage_events (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    chain_id            INTEGER,
    step_id             INTEGER,
    provider             TEXT NOT NULL,
    model               TEXT NOT NULL,
    request_id           TEXT,
    input_tokens        INTEGER,
    output_tokens       INTEGER,
    latency_ms          INTEGER,
    http_status         INTEGER,
    estimated_cost      REAL,
    usage_json          TEXT,
    created_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

All JSON columns must be treated as untrusted input. Validate their structure before use and redact secrets, authorization headers, full prompts containing personal data, and provider credentials from telemetry.

## 3.4.3 Checkpoint Protocol

A worker must emit a checkpoint:

1. when a step starts;
2. after every external tool invocation;
3. before and after a retry;
4. when provider output is received;
5. when an artifact is persisted;
6. before waiting for approval;
7. before marking the step complete;
8. at least once per heartbeat interval during long generation.

Example checkpoint:

```json
{
  "event_type": "provider_completed",
  "phase": "image_generation",
  "progress_percent": 64,
  "message": "Primary helmet render received",
  "metrics": {
    "provider": "nvidia-nim",
    "model": "black-forest-labs/flux.1-dev",
    "latency_ms": 18420,
    "artifact_count": 1,
    "bytes_received": 2849012
  },
  "state": {
    "artifact_id": "asset_01J...",
    "seed": 218741,
    "next_step": "thumbnail_generation"
  }
}
```

Checkpoint writes must be transactional with state transitions where possible:

```sql
BEGIN IMMEDIATE;

UPDATE agent_activity_steps
SET status = 'completed',
    output_json = ?,
    completed_at = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
WHERE step_uuid = ?
  AND status IN ('running', 'retrying');

INSERT INTO agent_activity_checkpoints (
    chain_id,
    step_id,
    checkpoint_uuid,
    event_type,
    sequence_no,
    progress_percent,
    phase,
    message,
    metrics_json,
    state_json
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);

COMMIT;
```

## 3.4.4 Timeout Scaling

Timeouts apply at three layers:

```text
connect timeout < read timeout < activity deadline
```

Recommended defaults:

| Work class | Connect | Read | Activity budget |
|---|---:|---:|---:|
| Metadata/chat | 10 s | 300 s | 300 s |
| Reasoning model | 10 s | 600 s | 600 s |
| One image generation | 15 s | 900 s | 900 s |
| Image editing/in-context | 15 s | 1,200 s | 1,200 s |
| Multi-image campaign | 15 s | 1,800 s | 1,800 s |
| WordPress publication | 10 s | 300 s | 300 s |

Scaling policy:

```php
function activityTimeout(int $complexity, bool $hasImage, int $artifactCount): int
{
    if ($artifactCount > 4) {
        return 1800;
    }

    if ($hasImage) {
        return $complexity >= 7 ? 1200 : 900;
    }

    return $complexity >= 7 ? 600 : 300;
}
```

Timeouts must not be implemented as a single blocking request. The provider request may use a 300–1,800 second read timeout, but the activity chain remains recoverable through heartbeats.

A worker is considered stale when:

```text
now - heartbeat_at > max(120 seconds, timeout_budget_s / 3)
```

A stale worker is not immediately marked failed. The supervisor should:

1. verify whether the process still owns the lease;
2. inspect the provider request ID;
3. attempt provider-side cancellation if supported;
4. requeue only if the operation is known to be idempotent;
5. otherwise mark the step `expired` and require reconciliation.

---

# 4. PHOTOREALISTIC IMAGE GENERATION ON NVIDIA NIM

## 4.1 NVIDIA Hosted Endpoint Convention

NVIDIA-hosted GenAI model endpoints generally use:

```text
POST https://ai.api.nvidia.com/v1/genai/{publisher}/{model}
```

Examples:

```text
https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev
https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-schnell
https://ai.api.nvidia.com/v1/genai/stabilityai/stable-diffusion-3.5-large
https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-kontext-dev
```

The exact model slug and accepted fields are deployment-specific. Helmetsan must maintain an endpoint registry sourced from the current NVIDIA model card. Self-hosted NIM containers can expose a different internal URL, commonly:

```text
POST http://nim-image:8000/v1/infer
```

The adapter must not assume that the hosted endpoint and self-hosted endpoint have identical routes.

Common headers:

```http
Authorization: Bearer $NVIDIA_API_KEY
Accept: application/json
Content-Type: application/json
User-Agent: Helmetsan-NIM/1.0
```

## 4.2 FLUX.1-dev

Canonical hosted request:

```http
POST /v1/genai/black-forest-labs/flux.1-dev
```

```json
{
  "prompt": "Photorealistic premium motorcycle helmet, studio product photography, ...",
  "negative_prompt": "cartoon, illustration, low resolution, warped visor, duplicate straps",
  "width": 1024,
  "height": 1024,
  "steps": 28,
  "cfg_scale": 3.5,
  "seed": 218741
}
```

Implementation notes:

- `steps`: normally 20–50; use 28–40 for production renders.
- `cfg_scale`: FLUX models typically perform well at lower guidance than SD-family models.
- `seed`: use a positive integer for reproducibility; use `0` or omit it for provider-randomized generation, depending on the deployed schema.
- `aspect_ratio`: use only when the deployed model card explicitly supports it. Otherwise translate the requested aspect ratio to `width` and `height`.

## 4.3 FLUX.1-schnell

```http
POST /v1/genai/black-forest-labs/flux.1-schnell
```

```json
{
  "prompt": "Photorealistic matte carbon-fiber adventure helmet on a neutral studio turntable, front three-quarter view, realistic visor reflections",
  "width": 1024,
  "height": 1024,
  "steps": 4,
  "cfg_scale": 1.0,
  "seed": 218742
}
```

Use Schnell for:

- concept iteration;
- thumbnail candidates;
- prompt testing;
- rapid UI previews.

Use FLUX.1-dev for final catalog imagery when fidelity and material rendering are more important than latency.

## 4.4 SD 3.5 Large

```http
POST /v1/genai/stabilityai/stable-diffusion-3.5-large
```

```json
{
  "prompt": "Photorealistic close-up product photograph of a premium full-face motorcycle helmet, precise carbon-fiber weave, clean shell geometry, dual D-ring chin strap, controlled softbox lighting",
  "negative_prompt": "text, logo errors, extra straps, malformed visor, deformed shell, watermark, low detail",
  "width": 1024,
  "height": 1024,
  "steps": 35,
  "cfg_scale": 5.0,
  "seed": 218743
}
```

SD 3.5 Large is useful where prompt adherence, typography experiments, or SD-compatible workflows are preferred. The adapter must map the internal Helmetsan parameter name `guidance_scale` to `cfg_scale` where required.

## 4.5 Aspect-Ratio Mapping

The Mission Control API may accept:

```json
{
  "aspect_ratio": "4:5",
  "seed": 218741
}
```

The provider adapter converts this to dimensions:

```php
$aspectMap = [
    '1:1'  => [1024, 1024],
    '4:5'  => [1024, 1280],
    '3:4'  => [1024, 1365],
    '16:9' => [1344, 768],
    '9:16' => [768, 1344],
    '3:2'  => [1152, 768],
];
```

Dimensions must be validated against the deployed model’s maximum pixel count. If the endpoint explicitly supports `aspect_ratio`, retain that field and omit `width`/`height` when required by the model schema.

## 4.6 Response Normalization

NVIDIA image responses may return an artifact structure such as:

```json
{
  "artifacts": [
    {
      "base64": "iVBORw0KGgoAAAANSUhEUgAA...",
      "finishReason": "SUCCESS",
      "seed": 218741
    }
  ]
}
```

Some deployments may return a data URL, URL, or OpenAI-compatible structure:

```json
{
  "data": [
    {
      "b64_json": "iVBORw0KGgoAAAANSUhEUgAA..."
    }
  ]
}
```

The provider must normalize all supported forms to:

```php
[
    'mime_type' => 'image/png',
    'bytes'     => $binaryImage,
    'seed'      => 218741,
    'provider'  => 'nvidia-nim',
    'model'     => 'black-forest-labs/flux.1-dev',
    'request_id' => $requestId,
]
```

Unknown response formats must fail closed and be recorded for adapter maintenance.

---

## 4.7 Helmetsan Prompt Templates

### Helmet Shell Template

```text
Photorealistic premium {helmet_type} motorcycle helmet, {camera_view},
precisely engineered aerodynamic shell, clean continuous shell geometry,
accurate visor aperture