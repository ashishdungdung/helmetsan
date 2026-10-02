# Helmetsan Repository Cleansing and Restructuring Plan

This should be executed as a controlled repository migration, not as an ad hoc deletion pass. The primary risks are:

- Deleting an operational script still invoked by cron, systemd, CI, or deployment hooks.
- Removing WordPress hooks, AJAX handlers, REST routes, shortcodes, or template files that appear unused from static analysis.
- Committing production state, logs, SQLite journals, credentials, or generated artifacts.
- Archiving files without preserving provenance or documenting their replacement.

The recommended approach is:

1. Freeze and snapshot.
2. Inventory and classify.
3. Quarantine.
4. Validate production behavior.
5. Permanently delete.
6. Restructure in separate, reviewable commits.

---

# 1. Immediate Repository Safety Measures

Before deleting or moving anything:

```bash
git status --short
git branch --show-current
git tag --list | tail -20
git log --oneline -20
```

Create a preservation branch and a signed or immutable export:

```bash
git switch -c chore/repository-cleansing
git tag -a pre-cleanse-$(date +%Y%m%d) -m "Pre-cleansing repository snapshot"
git bundle create ../helmetsan-pre-cleanse.bundle --all
```

If the repository contains deployment-only files that are not committed, capture them separately:

```bash
tar \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='vendor' \
  -czf ../helmetsan-working-tree-pre-cleanse-$(date +%Y%m%d).tar.gz .
```

Do not permanently remove production-related content until the following are known:

- Cron jobs.
- systemd services and timers.
- Docker or container entrypoints.
- CI/CD workflows.
- Nginx and PHP-FPM deployment scripts.
- WordPress WP-CLI commands.
- External automation invoking scripts by absolute path.
- Database migration or ingestion schedules.
- Any scripts referenced by `deploy.sh`, `server.js`, plugin code, theme code, or environment configuration.

Recommended discovery commands:

```bash
grep -RInE \
  'scripts/|deploy\.sh|node .*\.mjs|python .*\.py|wp .*|curl .*wp-json|cron|systemd' \
  . \
  --exclude-dir=.git \
  --exclude-dir=node_modules \
  --exclude-dir=vendor
```

Also inspect:

```bash
find .github . -maxdepth 4 \
  \( -name '*.yml' -o -name '*.yaml' -o -name 'Dockerfile*' -o -name 'Makefile' \) \
  -print
```

---

# 2. Categorized Cleansing Plan

## 2.1 Delete after snapshot and validation

These are generated artifacts, not source code, and should not remain in the repository.

### Root-level generated artifacts

Delete:

- `.DS_Store`
- `Thumbs.db`
- editor swap files
- `*.tmp`
- `*.temp`
- `*.bak`
- `*.orig`
- `*.swp`
- generated screenshots not used as intentional documentation
- local IDE metadata
- untracked build output
- local environment files containing secrets

Example:

```bash
find . -type f \
  \( -name '.DS_Store' \
  -o -name 'Thumbs.db' \
  -o -name '*.swp' \
  -o -name '*.swo' \
  -o -name '*.tmp' \
  -o -name '*.temp' \
  -o -name '*.bak' \
  -o -name '*.orig' \) \
  -not -path './.git/*' \
  -print
```

Review once, then delete:

```bash
find . -type f \
  \( -name '.DS_Store' -o -name 'Thumbs.db' -o -name '*.swp' \) \
  -not -path './.git/*' \
  -delete
```

### `.playwright-mcp/`

The entire directory should be removed from the working repository if it contains only historical logs, screenshots, and DOM dumps.

```bash
rm -rf .playwright-mcp
```

If some screenshots are needed for a documented regression or release record, retain only selected images under a clearly named documentation location, such as:

```text
docs/assets/browser-regressions/
```

Do not preserve the entire 7.3 MB capture directory.

Add to `.gitignore`:

```gitignore
.playwright-mcp/
playwright-report/
test-results/
```

### `HelmetsanWeb/scratch/`

Delete temporary audit output unless it is an approved, reproducible artifact:

```bash
rm -rf HelmetsanWeb/scratch/
```

If `audit_results.json` represents an important audit finding, convert it into a human-readable report under:

```text
docs/audits/
```

Do not retain unstructured scratch JSON as authoritative project documentation.

### Runtime logs and process state

The following should not live in source control:

```text
HelmetsanWeb/scripts/metal_bot.log
HelmetsanWeb/scripts/metal_bot.pid
HelmetsanWeb/scripts/metal_bot_staging.json
HelmetsanWeb/scripts/metal_bot_state.json
HelmetsanWeb/scripts/swarm_leases.db-shm
HelmetsanWeb/scripts/swarm_leases.db-wal
```

Delete them from the repository after confirming the service does not require them to be present at startup:

```bash
rm -f \
  HelmetsanWeb/scripts/metal_bot.log \
  HelmetsanWeb/scripts/metal_bot.pid \
  HelmetsanWeb/scripts/metal_bot_staging.json \
  HelmetsanWeb/scripts/metal_bot_state.json \
  HelmetsanWeb/scripts/swarm_leases.db-shm \
  HelmetsanWeb/scripts/swarm_leases.db-wal
```

Important: `*.db-shm` and `*.db-wal` are SQLite journal artifacts. They must not be deleted from a live database directory while a process is using the database. Stop the relevant process first, or remove them only from the repository checkout.

Logs and state should be redirected to runtime-owned locations such as:

```text
/var/log/helmetsan/
/var/lib/helmetsan/
/run/helmetsan/
```

or an externally managed logging system.

Add patterns such as:

```gitignore
*.log
*.pid
*.db-shm
*.db-wal
*.sqlite-shm
*.sqlite-wal
runtime/
var/
```

Do not broadly ignore all SQLite databases because schema fixtures and migration test databases may be legitimate source-controlled assets.

---

## 2.2 Do not immediately delete

### `HelmetsanMobile/assets/database/catalog.db`

The 92 MB database is not automatically junk. It may contain:

- Product or catalog data.
- A migration source.
- A historical snapshot needed for reconciliation.
- Proprietary or sensitive data.
- A database with no reproducible source.

Perform a data-retention decision first:

```bash
file HelmetsanMobile/assets/database/catalog.db
sqlite3 HelmetsanMobile/assets/database/catalog.db '.tables'
sqlite3 HelmetsanMobile/assets/database/catalog.db 'PRAGMA integrity_check;'
sqlite3 HelmetsanMobile/assets/database/catalog.db 'PRAGMA user_version;'
```

Then classify it:

| Classification | Action |
|---|---|
| Active production input | Move to managed data storage; never keep in Git |
| Required migration source | Move to a controlled migration artifact store and document checksum |
| Historical but valuable | Archive outside the application repository |
| Reproducible from source data | Delete after verification |
| Unused and undocumented | Quarantine, then delete after the retention window |

Recommended repository state:

```text
HelmetsanMobile/
├── README.md
└── migrations/
    └── README.md
```

The database itself should generally not be committed. If it must be preserved, use artifact storage, object storage, or Git LFS with access controls—not ordinary Git history.

### `HelmetsanWeb/backup/`

Do not retain old server configuration in the production application tree.

Recommended procedure:

1. Inspect the restore guide and configuration.
2. Remove secrets and server-specific credentials.
3. Determine whether it is still an approved recovery procedure.
4. Convert useful instructions into versioned runbooks.
5. Move the sanitized historical material to an external archive or `archive/operations/`.

The canonical recovery instructions should be rewritten under:

```text
docs/operations/disaster-recovery.md
```

Old July 2026 configuration should not be treated as authoritative merely because it exists in the repository.

### `HelmetsanWeb/scripts/archive/`

Do not leave an archive nested under an operational script directory. It blurs the boundary between executable production tooling and historical material.

Move it to:

```text
archive/scripts/helmetsan-web/
```

Preserve:

- Original relative path.
- Original filename.
- Date or commit range.
- Reason for archival.
- Whether the script was ever production-authorized.
- Replacement script, if one exists.

---

# 3. Root `scripts/` Versus `HelmetsanWeb/scripts/`

The current distinction is ambiguous. It should be made explicit.

## Root-level scripts

Root-level scripts should contain repository-wide developer, CI, validation, and maintenance tooling only.

Recommended structure:

```text
scripts/
├── README.md
├── ci/
├── dev/
├── maintenance/
├── release/
└── security/
```

Examples:

```text
scripts/ci/lint.sh
scripts/ci/test-all.sh
scripts/dev/reset-local-environment.sh
scripts/maintenance/check-links.py
scripts/release/build-artifacts.sh
scripts/security/scan-secrets.sh
```

The 33 historical consultation scripts should not remain at the root. They should either be:

- Deleted if they are disposable and contain no reusable logic.
- Consolidated into a documented archive.
- Converted into a supported diagnostic tool if they still have a current use.

## `HelmetsanWeb/scripts/`

This directory currently mixes production operations, data processing, logs, abandoned consultation scripts, and archives. It should be split.

Preferred end state:

```text
ops/
├── README.md
├── production/
│   ├── deploy.sh
│   ├── health-check.sh
│   ├── backup.sh
│   └── rollback.sh
├── data/
│   ├── ingest/
│   │   ├── multi_engine_ingest_controller.py
│   │   └── ...
│   ├── reporting/
│   │   ├── aggregate_daily_clicks.py
│   │   └── affiliate_telemetry_report.py
│   └── migrations/
└── development/
```

A simpler alternative is:

```text
scripts/
├── production/
├── data/
├── maintenance/
└── local/
```

The key rule is that production code must not be mixed with historical or local-only scripts.

### Script classification rules

| Script type | Destination |
|---|---|
| Deployment, rollback, health checks | `ops/production/` |
| Scheduled ingestion and transformation | `ops/data/` or `scripts/data/` |
| Reporting and analytics generation | `ops/data/reporting/` |
| Local developer helper | `scripts/dev/` |
| One-time migration | `scripts/data/migrations/` with a README |
| Consultation or abandoned experiment | `archive/consultations/` or delete |
| Generated output | Never store beside source scripts |
| Runtime state | `/var/lib`, `/run`, or external service |

Every retained operational script should have:

- A clear executable name.
- A usage comment or adjacent README.
- Required environment variables documented.
- Exit-code behavior documented.
- Locking/idempotency behavior documented.
- Dry-run support where practical.
- A designated owner.
- A test or smoke-check procedure.

---

# 4. Proposed Standard Repository Structure

A clean target structure could be:

```text
.
├── .github/
│   ├── workflows/
│   └── dependabot.yml
├── archive/
│   ├── consultations/
│   │   ├── root/
│   │   └── helmetsan-web/
│   ├── scripts/
│   ├── docs/
│   └── operations/
├── docs/
│   ├── README.md
│   ├── architecture/
│   ├── development/
│   ├── operations/
│   ├── data/
│   ├── security/
│   ├── audits/
│   ├── decisions/
│   ├── releases/
│   └── assets/
├── HelmetsanManager/
│   ├── README.md
│   ├── src/
│   ├── test/
│   ├── package.json
│   └── server.js
├── HelmetsanMobile/
│   ├── README.md
│   └── migrations/
├── HelmetsanWeb/
│   ├── README.md
│   ├── helmetsan-core/
│   ├── helmetsan-theme/
│   └── public/
├── ops/
│   ├── README.md
│   ├── production/
│   ├── data/
│   ├── maintenance/
│   └── monitoring/
├── scripts/
│   ├── README.md
│   ├── ci/
│   ├── dev/
│   ├── maintenance/
│   ├── release/
│   └── security/
├── tests/
│   ├── integration/
│   ├── e2e/
│   └── fixtures/
├── .editorconfig
├── .gitattributes
├── .gitignore
├── CONTRIBUTING.md
├── LICENSE
├── Makefile
└── README.md
```

## Runtime production code

These directories should contain deployable application code only:

```text
HelmetsanWeb/helmetsan-core/
HelmetsanWeb/helmetsan-theme/
HelmetsanManager/
```

They should not contain:

- Logs.
- Backups.
- Temporary JSON.
- Shell sessions.
- Consultation scripts.
- Database journals.
- Historical specifications.
- Deployment secrets.

## Production operational tools

Use:

```text
ops/production/
```

These are supported operational tools, not application runtime code.

## Data pipelines

Use:

```text
ops/data/
```

or, if these are intended primarily for developer execution:

```text
scripts/data/
```

Choose one convention and apply it consistently. I recommend `ops/data/` for scheduled or production-authorized pipelines and `scripts/data/` for local/reproducible migration utilities.

## Historical archives

Use:

```text
archive/
```

An archive is not an active source directory. Nothing under it should be referenced by production code or CI.

Every archive directory should contain a README:

```text
archive/consultations/README.md
archive/scripts/README.md
archive/docs/README.md
```

---

# 5. Consultation Script Consolidation

The consultation scripts are a classic source of repository noise.

## Classification

For every `.mjs`, `.py`, `.sh`, or similar file, record:

- Path.
- File size.
- Last modification date.
- Git history.
- Imports and dependencies.
- External services contacted.
- Credentials or tokens referenced.
- Whether it produces a reproducible artifact.
- Whether another script supersedes it.
- Whether it is referenced anywhere.

Generate an inventory:

```bash
find scripts HelmetsanWeb/scripts -type f \
  \( -name '*.mjs' -o -name '*.js' -o -name '*.py' -o -name '*.sh' \) \
  -print0 | xargs -0 -n1 stat --format='%n|%s|%y'
```

Search references:

```bash
git grep -nE \
  'consult_luna|test_kimi|multi_engine_ingest|aggregate_daily_clicks|affiliate_telemetry'
```

## Recommended disposition

### Delete

Delete scripts that are:

- Empty or broken.
- Duplicates.
- One-off prompts with no reusable implementation.
- Dependent on expired endpoints.
- Containing secrets.
- Replaced by a documented workflow.
- Not referenced and not reproducible or valuable.

### Archive

Move historically meaningful scripts to:

```text
archive/consultations/
├── README.md
├── luna/
├── kimi/
├── phase3/
└── dated/
```

Do not preserve 33 files merely because they existed. Preserve only those that document an important decision, reproduce an historical result, or may be needed for legal or operational traceability.

### Consolidate

If several scripts perform the same operation, replace them with one parameterized tool:

```text
ops/consultations/consult.py
```

Example:

```bash
python ops/consultations/consult.py \
  --provider kimi \
  --phase phase3 \
  --input context.json
```

The consolidated tool should have:

- Argument validation.
- Structured logging.
- Explicit timeout behavior.
- No embedded credentials.
- A stable output format.
- A README and example invocation.

---

# 6. PHP and JavaScript Dead-Code Audit

Static analysis alone is insufficient for WordPress. WordPress code can be invoked indirectly through hooks, naming conventions, configuration, HTTP requests, shortcodes, metadata, or templates.

## 6.1 Build a production reference inventory

Inventory:

- PHP classes and methods.
- Global functions.
- Actions and filters.
- AJAX actions.
- REST routes.
- Shortcodes.
- WP-CLI commands.
- Cron hooks.
- Admin menu callbacks.
- Rewrite endpoints.
- Template files.
- JavaScript entrypoints.
- CSS handles.
- Translation domains.
- Database tables and options.
- Environment variables.
- External API endpoints.

Useful searches:

```bash
git grep -nE \
  'add_action|add_filter|register_rest_route|wp_ajax_|wp_ajax_nopriv_|add_shortcode|wp_schedule|WP_CLI|register_post_type|register_taxonomy'
```

```bash
git grep -nE \
  'wp_enqueue_script|wp_enqueue_style|get_template_part|locate_template|include|require|require_once'
```

```bash
git grep -nE \
  'fetch\(|axios|admin-ajax\.php|wp-json|ajaxurl|rest_url'
```

For JavaScript:

```bash
find HelmetsanWeb/helmetsan-theme HelmetsanWeb/helmetsan-core \
  -type f \( -name '*.js' -o -name '*.mjs' \)
```

For PHP static analysis, use:

- PHPStan at an appropriate WordPress baseline.
- PHP_CodeSniffer with WordPress coding standards.
- PHP-CS-Fixer only if formatting changes are controlled.
- Psalm if the project can support the annotation effort.
- Rector only in reviewable, narrowly scoped migrations.

For JavaScript, use:

- ESLint.
- TypeScript compiler if applicable.
- `knip` for unused JavaScript exports and files.
- `depcheck` with manual verification.
- Bundle analysis for production entrypoints.

## 6.2 Candidate categories

### Safe candidates

Usually safe to remove after confirming they are untracked or unused