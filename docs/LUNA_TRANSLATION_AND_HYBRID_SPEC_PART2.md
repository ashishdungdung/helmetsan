# PART 2: TRANSLATION STUDIO ARCHITECTURE  
## Continuation from Section 3.2: Timeout Strategy

### 3.3 Complete Timeout Matrix

All timeout values shall be configurable at runtime through Mission Control. Defaults below are optimized for a local Apple M4 Pro running LM Studio, a local Express control plane, and an SSH/WP bridge.

Timeouts must be enforced independently. A single global timeout is insufficient because connection establishment, first-byte latency, streaming inactivity, and total job duration represent different failure modes.

| Subsystem / Operation | Connect Timeout | First-Byte Timeout | Idle / Read Timeout | Total Timeout | Retry Policy | Failure Action |
|---|---:|---:|---:|---:|---|---|
| Mission Control HTTP API | 2 s | 3 s | 10 s | 30 s | 2 retries, exponential backoff | Return structured API error; preserve job state |
| Mission Control SSE/WebSocket stream | 3 s | 5 s | 45 s heartbeat | Session-bound | Reconnect with cursor | Resume from last event ID |
| LM Studio health check | 1 s | 2 s | 3 s | 5 s | 3 retries | Mark inference service degraded |
| LM Studio model load | 5 s | 10 s | 30 s | 180 s | 1 retry | Block inference queue; emit operator alert |
| LM Studio single translation request | 2 s | 15 s | 45 s | 180 s | 1 retry if no tokens emitted | Quarantine candidate |
| LM Studio streaming translation | 2 s | 20 s | 60 s between tokens/chunks | 300 s | 1 retry only before first token | Preserve partial output; quarantine |
| LM Studio batch request | 2 s | 20 s | 90 s | 600 s | No automatic full-batch retry | Split failed batch into individual jobs |
| SSH ControlMaster connection | 3 s | N/A | 10 s | 15 s | Reopen master once | Mark bridge unavailable |
| SSH command execution | 3 s | N/A | 30 s | 90 s | 1 retry for transport errors | Return bridge error |
| WordPress REST request | 3 s | 5 s | 30 s | 60 s | 2 retries for 408/429/5xx | Exponential backoff; preserve idempotency key |
| WordPress media upload | 5 s | 15 s | 60 s | 300 s | 1 retry | Quarantine media operation |
| WordPress batch sync | 5 s | 15 s | 120 s | 900 s | Resume from item cursor | Mark individual item failures |
| Batch file discovery | 2 s | 5 s | 15 s | 60 s | 1 retry | Reject unreadable input |
| Batch file parsing | N/A | N/A | 30 s per MB | 300 s per file | No blind retry | Move file to ingestion quarantine |
| CSV/JSONL record processing | N/A | N/A | 30 s per record | 600 s per file | Retry record once | Continue file; quarantine record |
| SQLite compilation | N/A | N/A | 60 s | 900 s | One restart after cleanup | Preserve previous catalog.db |
| Production sync verification | 5 s | 10 s | 60 s | 600 s | One retry | Do not promote new database |

#### Timeout Rules

1. **Connect timeout** covers DNS, socket creation, SSH negotiation, and HTTP connection establishment.
2. **First-byte timeout** begins after the request is transmitted.
3. **Idle timeout** is reset whenever a valid response chunk, token, heartbeat, or progress event is received.
4. **Total timeout** is an absolute upper bound and cannot be extended by continuous low-volume output.
5. A retry must use a new request ID but retain the same `job_id`, `record_id`, and idempotency key.
6. A timeout must never silently become a success.
7. Partial streamed output shall be stored separately from accepted output and must not be published automatically.
8. Operator overrides may increase timeouts but may not reduce safety limits below the minimum values defined by the platform.

#### Retry Classification

Retryable:

- Connection reset before response completion
- HTTP 408, 425, 429, 500, 502, 503, 504
- SSH channel reset
- LM Studio unavailable before generation begins
- Temporary file-lock contention

Non-retryable:

- Invalid model request
- Invalid JSON or malformed batch record
- Unsupported language pair
- Prompt/schema validation failure
- WordPress authentication failure
- Terminology or protected-token validation failure
- Repeated model output exceeding maximum token budget

---

## 3.4 Worker Pool Design

### 3.4.1 Hardware Target

Primary execution target:

- Apple M4 Pro
- 12 CPU cores
- 24 GB unified memory
- Metal GPU acceleration through LM Studio
- Local Express Mission Control service on port `3005`

The worker system shall assume that CPU and GPU share unified memory. Worker count must therefore be limited by memory pressure and model behavior, not CPU core count alone.

### 3.4.2 Execution Model

The Translation Studio shall use an asynchronous supervisor with separate worker classes.

#### A. I/O Workers

Used for:

- File discovery
- Batch parsing
- SQLite reads and writes
- SSH bridge calls
- WordPress REST calls
- Mission Control API requests
- Progress and telemetry publication

Implementation:

- Node.js async I/O
- `fetch`/Undici or equivalent pooled HTTP client
- No child process per request
- No synchronous filesystem calls on the request path

Recommended initial limits:

```text
I/O concurrency: 16
WordPress concurrency: 4
SSH command concurrency: 2
SQLite write concurrency: 1
```

#### B. Translation Workers

Used for:

- TM lookup
- Prompt construction
- LM Studio inference
- Output validation
- Candidate persistence

Translation workers shall be logical jobs managed by the supervisor. They shall not automatically map one-to-one to OS threads.

Recommended initial inference configuration:

```text
Default active inference workers: 1
Maximum inference workers: 2
CPU fallback workers: 2
Maximum queued translation candidates: 2,000
```

Two concurrent inference requests may be enabled only after observing:

- Stable GPU utilization
- No memory-pressure warnings
- No significant increase in first-token latency
- No model eviction or reload events
- No output corruption or request interleaving

On a 24 GB unified-memory system, one active model request is the safe default. Concurrency of two is an adaptive ceiling, not a permanent requirement.

### 3.4.3 Thread Versus Process Model

#### Threads / Async Tasks

Preferred for:

- Network operations
- File operations
- Queue management
- Telemetry
- Parsing small and medium records
- Translation orchestration

Advantages:

- Low memory overhead
- Fast queue handoff
- Shared in-memory TM
- Simple cancellation and timeout propagation

#### Worker Threads

Use for:

- Large JSON/CSV parsing
- Hashing and n-gram fingerprint calculation
- CPU-heavy normalization
- Large catalog transformations

Worker threads shall receive immutable job payloads or references to memory-mapped files where practical.

#### Child Processes

Use only for:

- Untrusted external binaries
- Long-running conversion utilities
- Database compilation isolation
- Recovery from a potentially wedged native dependency

Child processes shall not be spawned for each translation request or SSH command. This is specifically prohibited because process creation adds latency and creates avoidable timeout and orphan-process failure modes.

### 3.4.4 Adaptive Concurrency

The supervisor shall calculate the permitted worker count from:

```text
effective_workers =
  min(
    configured_max_workers,
    cpu_budget,
    memory_budget,
    gpu_budget,
    downstream_budget
  )
```

Suggested control signals:

- GPU utilization
- Unified-memory pressure
- LM Studio queue depth
- First-token latency
- Tokens per second
- Error rate
- SSH/WordPress response latency
- Event-loop lag
- Number of quarantined candidates

Suggested adjustment behavior:

| Condition | Action |
|---|---|
| Memory pressure warning | Reduce inference concurrency to 1 |
| Memory pressure critical | Pause new inference; drain active request |
| First-token latency > 2× baseline | Reduce concurrency by 1 |
| GPU utilization < 45% for 60 seconds | Consider increasing concurrency by 1 |
| Error rate > 5% over 20 jobs | Reduce concurrency and quarantine failures |
| Queue depth > high-water mark | Apply backpressure to ingestion |
| Queue depth < low-water mark | Permit ingestion to resume |
| Event-loop lag > 250 ms | Reduce parsing and telemetry activity |
| LM Studio unavailable | Stop translation workers; retain queued jobs |

Concurrency changes shall be gradual and rate-limited. The system shall not oscillate between one and two workers on every telemetry interval.

### 3.4.5 Backpressure

The system shall use bounded queues.

Recommended queue structure:

```text
ingestion_queue        max 500 records
normalization_queue    max 500 records
translation_queue      max 2,000 candidates
validation_queue       max 500 candidates
publish_queue          max 250 records
quarantine_queue       max 1,000 records
```

When a queue reaches its high-water mark:

1. Stop reading additional input records.
2. Stop accepting new low-priority batch work.
3. Continue processing existing jobs.
4. Publish a backpressure event to Mission Control.
5. Resume only after queue depth falls below the low-water mark.

No queue may grow without bound. Large input files must be processed as streams or bounded chunks rather than loaded entirely into memory.

### 3.4.6 Candidate Job Lifecycle

Each candidate shall move through explicit states:

```text
DISCOVERED
→ PARSED
→ NORMALIZED
→ TM_CHECKED
→ QUEUED
→ INFERENCING
→ VALIDATING
→ ACCEPTED
→ PUBLISHED
```

Alternative paths:

```text
TM_CHECKED → TM_HIT → ACCEPTED
INFERENCING → RETRY_PENDING
INFERENCING → QUARANTINED
VALIDATING → QUARANTINED
PUBLISHED → SYNC_FAILED
```

Each transition must be durable enough to support restart and resume. At minimum, store:

- `job_id`
- `record_id`
- `source_hash`
- `language_from`
- `language_to`
- `model_id`
- `prompt_version`
- `glossary_version`
- `status`
- `attempt_count`
- `created_at`
- `updated_at`
- `error_code`
- `error_message`
- `partial_output_ref`

---

## 3.5 In-Memory Translation Memory and Caching

### 3.5.1 Cache Objectives

The cache shall:

1. Avoid inference for exact deterministic matches.
2. Deduplicate repeated source content within and across batches.
3. Preserve terminology consistency.
4. Reduce LM Studio load.
5. Support rapid testbench responses.
6. Never return a result generated under an incompatible model, glossary, or prompt version.

### 3.5.2 Deterministic 0-Token Matching

A 0-token match means no model invocation occurs.

The cache key shall include:

```text
hash(
  normalization_version
  + source_language
  + target_language
  + normalized_source
  + glossary_version
  + terminology_profile
  + output_schema_version
)
```

Optional model inclusion:

- For canonical approved translations, `model_id` may be omitted.
- For model-specific candidates, `model_id` and `prompt_version` must be included.

Normalization shall be deterministic and versioned. It may include:

- Unicode normalization
- Whitespace collapse
- Line-ending normalization
- HTML entity normalization
- Case handling where configured
- Removal of non-semantic surrounding whitespace
- Preservation of numeric and technical tokens
- Preservation of unit formatting
- Preservation of punctuation that affects meaning

It must not normalize away:

- Part numbers
- Safety ratings
- Product model names
- Displacement values
- Torque or power values
- Certification codes
- Size designators
- SKU or ASIN identifiers

### 3.5.3 Cache Tiers

#### Tier 0: Request-Local Cache

- Lifetime: one job or batch
- Purpose: eliminate duplicate work within a single request
- Implementation: `Map`
- No persistence requirement

#### Tier 1: Process In-Memory TM

- LRU or segmented-LRU cache
- Recommended initial capacity: 50,000–100,000 entries
- Store compact metadata and translation text
- Evict by memory cost, not item count alone

#### Tier 2: Persistent TM

SQLite-backed or file-backed store containing:

- Source hash
- Normalized source
- Source language
- Target language
- Approved translation
- Candidate translation
- Glossary version
- Model and prompt version
- Validation status
- Usage count
- Last-used timestamp

#### Tier 3: Batch Deduplication Index

A temporary per-batch index used to identify repeated or near-repeated descriptions before inference.

### 3.5.4 N-Gram Deduplication

N-gram deduplication shall reduce repeated translation work without incorrectly treating unrelated technical descriptions as identical.

Recommended approach:

1. Normalize the source.
2. Protect technical tokens.
3. Tokenize into words, numbers, units, and punctuation.
4. Generate word 3-grams and 5-grams.
5. Hash each n-gram.
6. Calculate a weighted overlap score.
7. Reuse a translation only when threshold and compatibility rules pass.

Suggested thresholds:

```text
Exact normalized match: 100% reuse
High-confidence near duplicate: ≥ 0.92 similarity
Review candidate: 0.82–0.919 similarity
No automatic reuse: < 0.82 similarity
```

Near-duplicate reuse shall not occur automatically when differences include:

- Product size
- Model year
- Engine displacement
- Power or torque figures
- Certification rating
- Gender or fitment designation
- Motorcycle make, model, or year
- Country-specific legal or safety wording

A near-duplicate may reuse a translation template, but variable tokens must be substituted and revalidated.

---

## 3.6 Terminology Enforcement for Motorcycle Technical Specifications

The Translation Studio shall maintain a terminology profile for motorcycle equipment, accessories, and vehicle specifications.

### Protected Token Classes

- ASIN, SKU, GTIN, and internal product IDs
- Manufacturer and model names
- Engine displacement: `125 cc`, `1.0 L`
- Power and torque: `kW`, `hp`, `Nm`, `lb-ft`
- Dimensions and weights
- Helmet certifications: `ECE 22.06`, `DOT`, `SNELL`, `FIM`
- IP ratings: `IP67`
- Material designations: `TPU`, `EPS`, `ABS`, `PC`
- Standards and part numbers
- Size labels: `XS`, `S`, `M`, `L`, `XL`, `XXL`
- Motorcycle fitment values
- Year ranges and generation codes

### Enforcement Pipeline

1. Extract protected tokens from source.
2. Replace them with stable placeholders before inference.
3