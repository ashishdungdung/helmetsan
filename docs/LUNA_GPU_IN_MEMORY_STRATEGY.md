## Replanned architecture

Use a **single orchestrator** with three execution planes:

1. **CPU orchestration and validation**
   - Node.js 20+
   - `worker_threads` for JSON parsing, linting, normalization, and scoring
   - Full catalog loaded once into memory
2. **GPU/accelerated semantic service**
   - One persistent local embedding/NLI service
   - Metal/Core ML/MLX-backed where supported
   - Batched inference; workers never independently load the model
3. **Commit/compile pipeline**
   - Atomic JSON publication
   - `compile_sqlite.py` executed only after 100% verification
   - Atomic database replacement
   - Catalog reload through Mission Control
   - Automated Git commit and tag

The process should run from a dedicated branch or worktree:

```bash
git switch -c catalog/rewrite-$(date +%Y%m%d-%H%M%S)
```

---

# 1. In-memory processing

## Memory model

Load all files from:

```text
HelmetsanWeb/data/motorcycles/
HelmetsanWeb/data/helmets/
HelmetsanWeb/data/accessories/
```

into a single catalog object:

```ts
type CatalogItem = {
  category: "motorcycles" | "helmets" | "accessories";
  filePath: string;
  id: string;
  original: object;
  candidate?: object;
  editorialOverview?: string;
  sourceHash: string;
  status: "pending" | "accepted" | "rejected" | "error";
  diagnostics: Diagnostic[];
};
```

The catalog size is small enough for full memory residency. Keep:

- Parsed source objects
- Candidate rewritten objects
- Source and candidate hashes
- Validation diagnostics
- Embeddings
- Per-item telemetry

Do not perform per-item read/write cycles during synthesis or linting.

## Recommended process layout

```text
orchestrator
├── catalog loader
├── rewrite scheduler
├── CPU worker pool: 6–8 workers
├── semantic service: 1 GPU/model process
├── Quality Sentinel
├── atomic publisher
├── SQLite compiler
└── telemetry client → localhost:3005
```

Use 6–8 CPU workers rather than 12. Reserve cores for:

- macOS
- Node orchestration
- GPU driver/runtime
- SQLite compilation
- telemetry and API networking

Use bounded queues:

```text
rewrite queue:       32–64 items
embedding queue:     128–256 texts
verification queue:  128 items
remote API limit:    8–16 concurrent requests
```

Do not create one worker per catalog item.

## Atomic staging

Maintain two in-memory maps:

```ts
const sourceCatalog = new Map<string, CatalogItem>();
const stagingCatalog = structuredClone(sourceCatalog);
```

Each accepted item is written only to `stagingCatalog`. The source catalog remains immutable for comparison and rollback.

Each candidate must pass:

1. JSON schema validation
2. Required-field validation
3. Editorial style validation
4. Length and character checks
5. Duplicate/cliché detection
6. Semantic similarity/distinctiveness checks
7. Contradiction checks against structured facts
8. Quality Sentinel approval

Only then:

```ts
item.status = "accepted";
item.candidate = verifiedCandidate;
```

Rejected items remain unchanged and are included in the final verification report.

## Batch publication

After all components reach 100% verification:

1. Serialize changed JSON files from memory.
2. Write each to a temporary file in the same directory.
3. `fsync` the temporary file.
4. Rename temporary file over the original.
5. Write a manifest containing hashes and verification results.

Example:

```text
HelmetsanWeb/.catalog-build/
├── verification-report.json
├── source-manifest.json
├── output-manifest.json
└── telemetry-summary.json
```

Use atomic rename, not direct writes:

```js
await writeFile(tempPath, content, "utf8");
await fsync(tempPath);
await rename(tempPath, finalPath);
```

If any final gate fails, publish nothing.

---

# 2. GPU and Apple Silicon utilization

## Important constraint

The remote GPT-5.6-Luna gateway will not automatically use the Mac GPU. GPU acceleration applies only to locally executed workloads such as:

- Embeddings
- NLI/contradiction models
- Tokenization and batch preprocessing
- Similarity computation

Use the remote API for editorial generation and high-level review, but use a local accelerated semantic service for high-volume verification.

## Recommended semantic stack

Preferred order on Apple Silicon:

### Option A: MLX / MLX-LM

Best fit for Apple Silicon if a compatible embedding or NLI model is available.

```text
Node orchestrator
    ↓ HTTP/Unix socket
Python MLX semantic service
    ↓ Metal/MPS
Embedding/NLI model
```

### Option B: ONNX Runtime

Use:

- Core ML execution provider where model operators are supported
- CPU fallback for unsupported operators
- Metal/MPS-compatible runtime where available

Validate actual provider use at startup. Do not assume that installing ONNX Runtime means GPU execution.

### Option C: llama.cpp / GGUF

Useful for local compact embedding or classification models with Metal enabled. Suitable when the selected model is available in GGUF format.

### Option D: WebGPU

Usable from Node, but not the first choice for this workload. Native MLX, Core ML, or Metal-backed runtimes are generally simpler and more predictable on macOS.

## Embedding strategy

Do not compare every item against every other item using a full dense matrix. At 5,493 items this is feasible, but unnecessary.

Recommended flow:

1. Generate one embedding per overview.
2. Normalize embeddings.
3. Build an approximate nearest-neighbor index.
4. Compare each item only against:
   - Same category
   - Same brand/model family
   - Top 20–50 nearest semantic neighbors
   - Previously accepted items with similar templates

Use cosine similarity:

```text
cosine(a,b) = dot(a,b) / (||a|| × ||b||)
```

Suggested thresholds require calibration, but an initial policy could be:

```text
similarity >= 0.92  → likely duplicate; reject or rewrite
0.85–0.92           → review for repetitive phrasing
< 0.85              → generally acceptable
```

Thresholds must be calibrated against a manually reviewed sample.

## Cliché and phrase repetition detection

Embeddings alone are insufficient for detecting repeated 5–8 word clichés.

Use a hybrid detector:

```text
normalized text
├── word n-grams, length 5–8
├── punctuation-insensitive phrase hashes
├── category-level frequency counts
├── corpus-wide frequency counts
└── semantic similarity
```

Policy example:

- Any exact 5–8 word phrase occurring in more than 1% of a category: warning
- Any phrase occurring in more than 2% of the full catalog: reject or require rewrite
- Repeated sentence openings: warning
- High semantic similarity plus phrase overlap: reject

This part is CPU-cheap and should run before GPU inference.

## Semantic contradiction filtering

Use a two-stage verifier.

### Stage 1: deterministic fact checks

Extract structured claims from the item:

```json
{
  "engine_size": "998 cc",
  "power": "152 hp",
  "weight": "196 kg",
  "helmet_type": "modular",
  "certification": "ECE 22.06"
}
```

Check the rewritten overview against the source record and known facts. Reject:

- Changed numbers
- Unsupported certifications
- Wrong product category
- Incorrect engine or shell claims
- New safety claims not present in source data
- Contradictory fitment or compatibility claims

### Stage 2: local NLI or entailment model

Run claims against:

```text
premise: source facts and approved metadata
hypothesis: candidate overview claims
```

Classify:

```text
entailment
neutral
contradiction
```

Reject contradictions. Route ambiguous neutral results to the Quality Sentinel or remote review.

Use batch sizes of 32–128, depending on model memory. Keep the model resident in one process.

## GPU service contract

Example API:

```http
POST /embed
{
  "texts": ["overview 1", "overview 2"],
  "model": "catalog-embedding-v1"
}
```

```http
POST /nli
{
  "pairs": [
    {
      "premise": "...",
      "hypothesis": "..."
    }
  ]
}
```

Return:

```json
{
  "vectors": [[0.01, -0.04]],
  "device": "metal",
  "model": "catalog-embedding-v1",
  "batchSize": 64,
  "latencyMs": 42
}
```

At startup, verify:

```text
device != CPU
provider == Metal/CoreML/MLX
batch inference succeeds
```

If acceleration is unavailable, continue with CPU fallback and emit a telemetry warning rather than silently claiming GPU use.

---

# 3. Continuous execution and telemetry

## Telemetry events

Send structured events to HelmetsanManager on port 3005:

```http
POST http://127.0.0.1:3005/api/catalog/events
```

If the exact endpoint differs, make it configurable:

```bash
HELMETSAN_MANAGER_URL=http://127.0.0.1:3005
```

Event format:

```json
{
  "runId": "catalog-2025-...",
  "phase": "quality-sentinel",
  "category": "helmets",
  "itemId": "shoei-x15",
  "completed": 1820,
  "total": 5493,
  "status": "accepted",
  "latencyMs": 812,
  "gpu": {
    "enabled": true,
    "device": "Metal",
    "batchSize": 64
  },
  "errors": 0,
  "timestamp": "2025-..."
}
```

Emit:

- Run started
- Phase started/completed
- Item accepted/rejected
- Batch completed
- Remote API retry/rate limit
- GPU fallback
- Sentinel failure
- Publication started/completed
- SQLite compile started/completed
- Index reload requested/completed
- Git commit/tag completed

Use a local in-memory event buffer and flush telemetry in batches so observability does not become a bottleneck.

## Failure handling

Every item should have:

```json
{
  "attempts": 2,
  "lastError": null,
  "sourceHash": "...",
  "candidateHash": "...",
  "verificationHash": "...",
  "status": "accepted"
}
```

Retry only transient failures:

- Network timeout
- Gateway 429
- Temporary service errors

Do not retry deterministic validation failures indefinitely.

Persist only coarse checkpoints or manifests during processing. The actual catalog edits remain in memory until publication.

---

# 4. SQLite compilation and index reload

After all JSON files are published and hashes are verified:

```bash
/Library/Developer/CommandLineTools/Library/Frameworks/Python3.framework/Versions/3.9/bin/python3 \
  compile_sqlite.py
```

Prefer compiling to a temporary database:

```text
catalog.db.build
```

Then validate:

```bash
sqlite3 catalog.db.build "PRAGMA integrity_check;"
sqlite3 catalog.db.build "SELECT COUNT(*) FROM catalog;"
```

Expected count:

```text
5493
```

Also verify:

- All category counts
- No duplicate IDs
- No missing overviews
- Search/index tables populated
- Foreign keys valid
- Expected schema version

Replace atomically:

```bash
mv catalog.db catalog.db.previous
mv catalog.db.build catalog.db
```

If Mission Control uses a long-lived database connection, request a reload rather than assuming it notices the file replacement:

```http
POST http://127.0.0.1:3005/api/catalog/reload
{
  "database": "catalog.db",
  "manifestHash": "...",
  "runId": "..."
}
```

Then poll until the new manifest or database hash is reported active.

---

# 5. Final verification gate

Do not commit until all conditions are true:

```text
source files loaded:              5,493
rewritten/verified:               5,493
sentinel failures:                0
schema failures:                  0
contradictions:                   0
unresolved duplicate warnings:    0
SQLite integrity_check:           passed
SQLite row count:                 5,493
Mission Control reload:           confirmed
working tree:                     expected changes only
```

Generate:

```text
verification-report.json
output-manifest.json
catalog.db
catalog.db.sha256
```

Use SHA-256 manifests to prove that the committed JSON and compiled database correspond to the same run.

---

# 6. Automated Git checkpoint

Yes—create the requested checkpoint automatically after successful completion.

Recommended sequence:

```bash
git add HelmetsanWeb/data \
        catalog.db \
        .catalog-build/verification-report.json \
        .catalog-build/output-manifest.json

git diff --cached --check

git commit -m "catalog: rewrite and verify all editorial overviews"

git tag -a "catalog-verified-$(date +%Y%m%d-%H%M%S)" \
  -m "Verified catalog rewrite, SQLite rebuild, and index reload"
```

Before committing, enforce:

```bash
git diff --cached --name-only
git status --short
```

Abort if unexpected files are staged.

The commit metadata should include:

```text
Run ID
Source manifest hash
Output manifest hash
SQLite SHA-256
Verified item count
Quality Sentinel version
Embedding/NLI model versions
GPU provider used
Mission Control reload status
```

Example commit trailer:

```text
Catalog-Run: catalog-2025-...
Catalog-Items: 5493
Catalog-Verified: 5493
SQLite-SHA256: ...
Semantic-Device: Metal
Index-Reload: confirmed
```

## Execution order

```text
1. Create branch/worktree
2. Load all JSON into memory
3. Validate source catalog
4. Generate/rewrite candidates through GPT-5.6-Luna
5. Run deterministic linting
6. Run local GPU-assisted embeddings/NLI
7. Run Quality Sentinel
8. Repeat failed items only
9. Require 100% verification
10. Atomically publish JSON
11. Run compile_sqlite.py
12. Integrity-check catalog.db
13. Atomically replace database
14. Trigger and confirm Mission Control reload
15. Create Git commit
16. Create annotated verification tag
17. Publish final telemetry and report
```

This design carries forward the proposed changes across motorcycles, helmets, and accessories, minimizes disk I/O, uses unified memory safely, uses the GPU where Apple’s local runtimes actually support it, and creates the requested auditable Git checkpoint only after the complete catalog and index have been verified.