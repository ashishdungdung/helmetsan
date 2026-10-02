# Helmetsan Token Discipline Audit

## 0. Executive finding

Helmetsan’s current shield is effective but not yet a complete token-governance system.

- Physical repository: **255,761,273 estimated tokens**
- Shielded: **254,901,225 tokens**
- Active/indexable: **860,047 tokens**
- Current shield ratio: **99.664%**
- Active context is still **~297× larger** than a disciplined 10K–25K-token task slice.
- A developer who loads the full active set can spend **34×–86× more input tokens** than necessary for a normal coding task.

The central distinction is:

> **Indexable is not the same as loaded, and ignored is not the same as inaccessible.**

`.geminiignore` prevents accidental indexing by one toolchain. It does not prevent:

- explicit file reads,
- shell commands that dump files,
- subagents inheriting oversized transcripts,
- IDE context injection,
- scripts that concatenate generated artifacts,
- another model integration bypassing `.geminiignore`.

The repository needs both **path-level exclusion** and **procedural enforcement**.

---

# 1. Content that should never enter ordinary LLM context

“Never” means **never during routine coding, debugging, review, or feature development unless the task explicitly concerns that artifact**.

## 1.1 Absolute exclusions

### A. Binary assets

Never load:

- `*.png`, `*.jpg`, `*.jpeg`, `*.gif`, `*.webp`
- fonts: `*.woff`, `*.woff2`, `*.ttf`
- videos, archives, compiled binaries
- SQLite databases as raw files
- PDFs unless the task specifically requires document extraction

The `helmet-default.png` estimate of **302,568 tokens** is not useful coding context. It is a binary-tokenization failure, not meaningful semantic context.

Use instead:

```bash
file path/to/helmet-default.png
identify path/to/helmet-default.png
```

or a bounded visual inspection tool when visual analysis is genuinely required.

### B. Generated catalogs and seed data

Never load raw:

- `HelmetsanWeb/data/**`
- `helmets_seed.json`
- generated JSON catalogs
- search indexes
- memory indexes
- export snapshots
- fixture dumps
- import payloads

The 4.82 MB `helmets_seed.json` alone represents roughly **1.33M tokens**. It should be queried or summarized, never pasted wholesale.

Correct alternatives:

```bash
jq 'length' helmets_seed.json
jq '.[0] | keys' helmets_seed.json
jq '[.[] | .category] | sort | group_by(.) | map({category: .[0], count: length})' helmets_seed.json
```

For a specific record:

```bash
jq '.[] | select(.id == "TARGET_ID")' helmets_seed.json
```

### C. Vendor dependencies

Never load entire dependency trees:

- `HelmetsanWeb/vendor/**`
- Composer-installed packages
- npm package directories
- generated autoload maps
- third-party source trees

Use:

```bash
composer show
composer why PACKAGE
rg -n "ClassName|functionName" vendor/package/path
```

Only the relevant declaration and call boundary should enter context.

### D. Minified and bundled assets

Never load:

- `*.min.js`
- `*.min.css`
- Webpack/Vite bundles
- compiled distribution assets
- source maps
- generated asset manifests

The `helmetsan-bundle.min.css` file is a classic stealth sink: **69,453 tokens** with very low diagnostic value.

Use the unminified source, component stylesheet, selector search, or a generated summary.

### E. Historical and operational data

Never load wholesale:

- audit logs
- request logs
- access logs
- debug logs
- deployment logs
- historical exports
- chat transcripts
- crash dumps
- profiling output
- database backups

Use bounded extraction:

```bash
rg -n "ERROR|Exception|request-id" app.log | tail -100
tail -200 app.log
awk 'NR >= 1200 && NR <= 1300' app.log
```

### F. Secrets and identity material

Never place in context unless the secret has already been revoked and the task is explicitly a secret-remediation investigation:

- `.env`
- API keys
- private keys
- certificates containing private material
- OAuth tokens
- database passwords
- production credentials
- customer PII
- session tokens

Recommended handling:

```bash
rg -n --hidden \
  --glob '!vendor/**' \
  --glob '!data/**' \
  '(API_KEY|SECRET|TOKEN|PASSWORD|PRIVATE_KEY)' .
```

Share variable names and redacted structure, never values.

---

## 1.2 Conditional exclusions

These may be loaded only in narrow slices.

| Content | Policy |
|---|---|
| `create_helmets_seed.php` | Never whole; inspect the specific generator function and call path |
| `Admin.php` | Never whole; inspect target class/method plus relevant hooks |
| `single-helmet.php` | Load only the affected template block |
| `archive-helmet.php` | Load only query, loop, or rendering region |
| translations | Load only the relevant locale/key range |
| docs | Load the specific document sections, not all 80+ files |
| lockfiles | Use dependency lookup; do not paste wholesale |
| migrations | Load only the migration relevant to the schema issue |
| tests | Load the failing test, fixture, and implementation boundary |
| theme templates | Load the named template and directly included partials only |

---

# 2. Token economics

## 2.1 Size ratios

Using the supplied estimates:

\[
\frac{255{,}761{,}273}{860{,}047} \approx 297.4
\]

The raw repository is approximately **297 times larger** than the active indexable context.

For focused interactions:

\[
\frac{860{,}047}{25{,}000} \approx 34.4
\]

\[
\frac{860{,}047}{10{,}000} \approx 86.0
\]

Thus, even the active context is between **34× and 86× larger** than the target working slice.

The stated shield efficiency is:

\[
1 - \frac{860{,}047}{255{,}761{,}273}
= 99.6637\%
\]

Rounded: **99.7%**, correct.

---

## 2.2 Input cost by model

Assuming the supplied prices are per million input tokens:

| Load | GPT-5.6-luna / Gemini Flash | GPT-5.6-sol | Claude 3.7 Sonnet |
|---:|---:|---:|---:|
| 255.761M raw | $25.58 | $511.52 | $767.28 |
| 860K active | $0.086 | $1.72 | $2.58 |
| 10K focused | $0.001 | $0.020 | $0.030 |
| 25K focused | $0.0025 | $0.050 | $0.075 |

These are **input-only** costs. Output charges are separate.

The raw-load scenario is also often operationally impossible because model context windows, latency limits, truncation, and retrieval budgets will intervene before the nominal invoice is reached. The cost table is therefore an economic lower-bound model, not a claim that 255M tokens can be sent in one request.

---

## 2.3 Standard, batch, and cached costs

Assumptions:

- Batch API: **50% of standard**
- Prompt caching: **25% of standard**, equivalent to 75% savings
- If both apply multiplicatively: **12.5% of standard**
- Pricing treatment differs by provider; verify actual billing semantics before budgeting.

### GPT-5.6-luna / Gemini Flash

| Load | Standard | Batch | Cached | Batch + cached |
|---:|---:|---:|---:|---:|
| 255.761M | $25.58 | $12.79 | $6.39 | $3.20 |
| 860K | $0.086 | $0.043 | $0.0215 | $0.0108 |
| 10K | $0.0010 | $0.0005 | $0.00025 | $0.000125 |
| 25K | $0.0025 | $0.00125 | $0.000625 | $0.000313 |

### GPT-5.6-sol

| Load | Standard | Batch | Cached | Batch + cached |
|---:|---:|---:|---:|---:|
| 255.761M | $511.52 | $255.76 | $127.88 | $63.94 |
| 860K | $1.72 | $0.86 | $0.43 | $0.215 |
| 10K | $0.020 | $0.010 | $0.005 | $0.0025 |
| 25K | $0.050 | $0.025 | $0.0125 | $0.00625 |

### Claude 3.7 Sonnet

| Load | Standard | Batch | Cached | Batch + cached |
|---:|---:|---:|---:|---:|
| 255.761M | $767.28 | $383.64 | $191.82 | $95.91 |
| 860K | $2.58 | $1.29 | $0.645 | $0.3225 |
| 10K | $0.030 | $0.015 | $0.0075 | $0.00375 |
| 25K | $0.075 | $0.0375 | $0.01875 | $0.009375 |

### Important caveat on caching

Caching does not make oversized context semantically free:

- Cached tokens still consume context-window capacity.
- Cached irrelevant content still distracts retrieval and reasoning.
- Cache invalidation can cause unexpected full-price reloads.
- Prompt caching should stabilize a small, reusable system/project prefix—not justify retaining a massive repository dump.

The correct optimization order is:

1. Exclude irrelevant content.
2. Retrieve a narrow slice.
3. Cache only stable, high-value context.
4. Use batch for asynchronous work.

---

# 3. Monolithic-file risk

The largest dangerous files are not necessarily the largest byte files. Their risk is:

\[
\text{Risk} \approx \text{token volume} \times \text{probability of accidental inclusion} \times \text{irrelevance}
\]

| File | Tokens | Policy |
|---|---:|---|
| `helmets_seed.json` | 1,329,756 | Hard exclude; query structurally |
| `helmet-default.png` | 302,568 | Hard exclude; inspect metadata or image selectively |
| `create_helmets_seed.php` | 131,413 | Split/refactor; never whole-file load |
| `Admin.php` | 91,065 | Split/refactor; method-level retrieval |
| `helmetsan-bundle.min.css` | 69,453 | Hard exclude; use source CSS |
| `single-helmet.php` | 27,440 | Extract partials and bounded slices |
| `archive-helmet.php` | 13,485 | Usually manageable only by targeted range |
| `archive-accessory.php` | 13,082 | Same |

`create_helmets_seed.php` and `Admin.php` are particularly problematic because they are executable source and therefore appear “relevant,” while their full contents are almost never needed.

A 131K-token PHP file is not a context artifact; it is a refactoring signal.

---

# 4. Zero-token-burn execution protocol

“Zero token burn” means **zero unnecessary model-visible tokens**, not zero CPU, disk, or developer effort.

## 4.1 Mandatory slice notation

Every file read supplied to an LLM must use an explicit line range.

Valid:

```text
path/to/file.php:120-180
```

```bash
nl -ba path/to/file.php | sed -n '120,180p'
```

```bash
awk 'NR >= 120 && NR <= 180' path/to/file.php
```

Invalid:

```bash
cat path/to/file.php
less path/to/file.php
sed -n '1,$p' path/to/file.php
```

unless the file has been mechanically proven to be below a strict size threshold.

Recommended default limits:

- Routine slice: **30–150 lines**
- Complex method: **up to 250 lines**
- Hard maximum without explicit approval: **500 lines**
- Generated/data output: **20 records**
- Logs: **100–200 matching lines**
- Search results: **20 matches per query**

Every escalation beyond these limits must state why the additional context is necessary.

## 4.2 Search before read

Use symbol and structural search first:

```bash
rg -n "function targetMethod|class TargetClass|hook_name" HelmetsanWeb
```

Then inspect only the relevant ranges.

Do not begin with:

```bash
find . -type f -exec cat {} \;
```

or broad recursive concatenation.

## 4.3 In-memory CLI transformations

The shell should perform reduction before the model sees output.

Good:

```bash
jq '.items[] | select(.status == "active") | {id, name, status}' data.json
```

```bash
sqlite3 HelmetsanMobile/catalog.db \
  "SELECT category, COUNT(*) FROM helmets GROUP BY category ORDER BY COUNT(*) DESC;"
```

```bash
git diff --stat
git diff --unified=20 -- path/to/file.php
```

Bad:

```bash
cat data.json
sqlite3 catalog.db '.dump'
git diff
```

Use CLI tools to emit:

- counts,
- schemas,
- selected fields,
- grouped summaries,
- top-N records,
- exact matching rows,
- bounded diffs.

## 4.4 Subagent sandboxing

Every subagent should receive:

1. A task statement.
2. A fixed file allowlist.
3. A token budget.
4. A line-range budget.
5. A no-transcript-inheritance rule unless explicitly required.
6. A requirement to return a compact result, not its working transcript.

Example:

```text
Allowed files:
- HelmetsanWeb/helmetsan-core/includes/HelmetRepository.php:80-180
- tests/HelmetRepositoryTest.php:20-120

Forbidden:
- data/**
- vendor/**
- *.min.css
- *.min.js
- logs/**
- .env*

Output:
- diagnosis <= 500 words
- patch or exact recommended changes
- no file contents reproduced unnecessarily
```

Subagents must not inherit:

- prior exploratory dumps,
- complete repository maps,
- unrelated agent transcripts,
- raw logs,
- generated catalogs.

A subagent that receives a polluted transcript cannot reliably “ignore” it; the tokens have already consumed context capacity and may influence reasoning.

---

# 5. Hardened `.geminiignore` recommendations

The supplied data indicates substantial shielding, but the exact current patterns were not provided. Therefore this is a hardened baseline, not a line-by-line review.

## 5.1 Recommended broad exclusions

```gitignore
# Secrets and local configuration
.env
.env.*
!.env.example
*.pem
*.key
*.p12
*.pfx
secrets/
credentials/

# Dependencies
vendor/
node_modules/
bower_components/
composer/cache/

# Generated data and catalogs
HelmetsanWeb/data/
**/seed-data/
**/fixtures/generated/
**/*seed*.json
**/*catalog*.json
**/*index*.json
**/*snapshot*.json
**/exports/
**/backups/

# Databases and binary stores
*.db
*.sqlite
*.sqlite3
*.mdb
*.dump

# Logs and operational output
*.log
logs/
log/
tmp/
cache/
coverage/
artifacts/
crash-dumps/

# Build output
dist/
build/
public/build/
assets/build/
*.min.js
*.min.css
*.map

# Binary media
*.png
*.jpg
*.jpeg
*.gif
*.webp
*.ico
*.svg
*.pdf
*.zip
*.tar
*.gz
*.7z
*.woff
*.woff2
*.ttf
*.eot

# IDE and OS noise
.git/
.github/
.idea/
.vscode/
.DS_Store
Thumbs.db
```

## 5.2 Helmetsan-specific exclusions

```gitignore
HelmetsanWeb/data/
HelmetsanWeb/vendor/
HelmetsanMobile/*.db
HelmetsanMobile/**/*.db

HelmetsanWeb/helmetsan-theme/**/*.min.css
HelmetsanWeb/helmetsan-theme/**/*.min.js
HelmetsanWeb/helmetsan-theme/**/*.map

HelmetsanManager/**/cache/
HelmetsanManager/**/logs/
HelmetsanManager/**/exports/

HelmetsanWeb/docs/archive/
HelmetsanWeb/docs/generated/
HelmetsanWeb/docs/history/
```

Be careful with broad patterns such as `**/*index*.json`: retain a named, human-maintained application index if it is genuinely needed. Generated indexes should be excluded by path or naming convention.

## 5.3 Blind spots to audit specifically

Check for:

- alternate database extensions,
- uppercase variants: `*.PNG`, `*.JSON`,
- files hidden under dot-directories,
- generated files with ordinary names,
- translation files containing enormous repeated catalogs,
- source maps,
- test snapshots,
- PHP serialized data,
- XML exports,
- NDJSON/JSONL streams,
- compressed archives,
- copied vendor trees under nonstandard directories,
- historical docs,
- CI artifacts,
- `.