# Adversarial Audit of the Luna Blueprint

## 1. Safety and zero-breakage verification

### Important limitation

The blueprint names candidate paths, but it does not prove that they are unused. Without the repository, deployment host, CI configuration, crontabs, systemd units, container manifests, and web-server configuration, no deletion or move can be certified safe.

Therefore, the correct disposition is **candidate for quarantine**, not “safe to delete.”

### Highest-risk operations

#### A. Deleting runtime state

These are especially risky:

```text
metal_bot.pid
metal_bot_staging.json
metal_bot_state.json
swarm_leases.db-shm
swarm_leases.db-wal
```

Although they look like generated state, they may contain:

- Queue ownership or lease information.
- Restart checkpoints.
- Idempotency state.
- Active process coordination.
- SQLite transactions not yet checkpointed into the main database.

The blueprint correctly warns about SQLite WAL files, but its proposed deletion command still creates operational risk unless the files are confirmed to belong only to a repository checkout.

Do not delete these from a live service directory. First determine:

```bash
lsof -- \
  HelmetsanWeb/scripts/metal_bot.pid \
  HelmetsanWeb/scripts/metal_bot_staging.json \
  HelmetsanWeb/scripts/metal_bot_state.json \
  HelmetsanWeb/scripts/swarm_leases.db-shm \
  HelmetsanWeb/scripts/swarm_leases.db-wal
```

Also check likely processes:

```bash
pgrep -af 'metal_bot|swarm|sqlite|python|node'
```

A PID file must not be trusted as proof that a process is stopped. Verify the process identity and its working directory.

#### B. Moving `HelmetsanWeb/scripts/`

Moving scripts can silently break:

- Absolute-path cron entries.
- systemd `ExecStart` and `WorkingDirectory`.
- Deployment scripts.
- Docker `COPY` or `ENTRYPOINT` paths.
- Nginx FastCGI or CGI wrappers.
- WP-CLI wrappers.
- External monitoring or backup jobs.
- Documentation containing copy-paste operational commands.

A move is not safe merely because `git grep` finds no reference. External automation is invisible to repository-local search.

#### C. Moving `HelmetsanWeb/scripts/archive/`

This is lower risk, but still requires checking:

```bash
git grep -nE 'scripts/archive|HelmetsanWeb/scripts/archive'
```

Also inspect all shell scripts and deployment manifests for path construction such as:

```bash
SCRIPT_DIR="$(dirname "$0")"
find "$SCRIPT_DIR/archive" ...
```

Relative references can be missed by simple filename searches.

#### D. Deleting `.playwright-mcp/`

This is probably generated output, but deletion can break:

- Local Playwright workflows that expect the directory.
- Regression evidence referenced by documentation.
- A test harness configured with a fixed output directory.
- Uncommitted investigation artifacts that have not yet been triaged.

Check:

```bash
git grep -nE '\.playwright-mcp|playwright-report|test-results'
find . -maxdepth 4 -type f \
  \( -name 'playwright.config.*' -o -name '*playwright*' \) -print
```

It is normally safe to remove generated captures from the repository, but it is not safe to assume that the entire directory is disposable without inspection.

#### E. Deleting `metal_bot.log`

A log is not normally an executable dependency, but it may be:

- A configured log path.
- Required by a health check.
- A startup diagnostic source.
- A symlink or bind-mounted path in a deployment checkout.

Check whether it is a symlink and whether a process has it open:

```bash
stat HelmetsanWeb/scripts/metal_bot.log
readlink -f HelmetsanWeb/scripts/metal_bot.log
lsof -- HelmetsanWeb/scripts/metal_bot.log
```

#### F. Deleting `HelmetsanMobile/assets/database/catalog.db`

This is not cleanup. It is a data-retention and application-behavior decision. It could be loaded by:

- Mobile application code.
- A build script.
- Tests.
- A migration tool.
- A packaging step.
- A runtime resource lookup.

Before moving or deleting it:

```bash
git grep -nE 'catalog\.db|assets/database|sqlite|SQLite'
find HelmetsanMobile -type f -print0 |
  xargs -0 grep -IlE 'catalog\.db|assets/database' 2>/dev/null || true
```

Then inspect mobile build manifests and resource-copy configuration. A Git LFS migration also changes checkout and CI behavior; it is not a neutral replacement.

#### G. Moving or deleting `backup/`

The directory may contain operationally referenced restore assets. Search both names and content:

```bash
git grep -nEi 'backup|restore|disaster.recovery|July 2026|sync_data|deploy'
find HelmetsanWeb/backup -type f -maxdepth 5 -print
```

Do not archive configuration containing secrets merely to preserve provenance. Secret-bearing material should be removed from active history where appropriate and retained only under an approved, access-controlled process.

### Required external verification

Run these on the deployment host, not only in the repository checkout:

```bash
crontab -l 2>/dev/null || true
sudo crontab -l 2>/dev/null || true

sudo grep -RInE \
  'aggregate_daily_clicks|affiliate_telemetry|multi_engine_ingest|deploy\.sh|sync_data\.sh|metal_bot|wp .*helmetsan' \
  /etc/cron.d /etc/cron.daily /etc/cron.hourly /etc/cron.weekly /etc/systemd \
  2>/dev/null || true

systemctl list-unit-files --type=service --type=timer
systemctl list-timers --all
```

Inspect relevant units individually:

```bash
systemctl cat SERVICE_NAME.service
systemctl cat TIMER_NAME.timer
```

Inspect deployment and container references:

```bash
git grep -nE \
  'deploy\.sh|sync_data\.sh|ExecStart|WorkingDirectory|ENTRYPOINT|CMD|COPY .*scripts|helmetsan-core|helmetsan-theme'

find . -maxdepth 5 -type f \
  \( -name 'Dockerfile*' -o -name 'compose*.yml' -o -name '*.service' \
     -o -name '*.timer' -o -name '*.conf' -o -name '*.ini' \) -print
```

For the web stack:

```bash
sudo nginx -T 2>/dev/null |
  grep -nE 'helmetsan|fastcgi|root|include|scripts' || true

php -m
```

Also inspect the actual deployed release directory. A repository checkout may not be the path used by Nginx, PHP-FPM, cron, or systemd.

### WordPress-specific zero-breakage checks

Before moving anything beneath `helmetsan-core` or `helmetsan-theme`, inventory indirect entry points:

```bash
git grep -nE \
  'add_action|add_filter|register_rest_route|wp_ajax_|wp_ajax_nopriv_|add_shortcode|wp_schedule|WP_CLI|register_post_type|register_taxonomy|wp_enqueue_script|wp_enqueue_style|get_template_part|locate_template|include|require'
```

Also inspect:

- `functions.php`.
- Plugin bootstrap files.
- Composer autoload configuration.
- Theme and plugin headers.
- REST and AJAX clients.
- WP-CLI registration.
- Cron hook names stored in the database.
- Template resolution and child-theme overrides.
- Build manifests and generated bundles.

A PHP file with no apparent direct caller may still be loaded by Composer, a WordPress convention, a hook registration file, or a deployment package.

### Additional blueprint defects

1. The supplied blueprint is truncated at “Safe candidates,” so its dead-code conclusions cannot be treated as complete.
2. `grep -R` can traverse generated files, symlinks, binary files, and secret-bearing content. It is not a complete dependency analysis.
3. `git grep` cannot detect references from external systems or values assembled dynamically.
4. `git clean` is intentionally absent, which is good; it must remain absent from an automated cleanup.
5. Adding broad ignores such as `*.log` can hide an already tracked file but does not remove it from Git history or prevent operational confusion.
6. Moving files in the same commit as functional refactoring makes rollback and causality harder. Separate moves from behavior changes.

---

# 2. Refined pruning checklist

The safest approach is:

1. Snapshot.
2. Record references.
3. Quarantine.
4. Review the diff.
5. Run tests.
6. Delete only after the quarantine has passed review.

The following sequence is deliberately conservative and operates only from the repository root.

## A. Establish a controlled worktree

```bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

test -d .git
git status --short
git switch -c chore/repository-cleansing
git tag -a "pre-cleanse-$(date +%Y%m%d-%H%M%S)" \
  -m "Pre-cleansing repository snapshot"

git bundle create "../$(basename "$ROOT")-pre-cleanse.bundle" --all
```

Do not continue automatically if the initial status contains unexpected modifications. Save the output for review.

## B. Inventory candidate files without deleting anything

```bash
find . -type f \
  \( -name '.DS_Store' -o -name 'Thumbs.db' \
     -o -name '*.swp' -o -name '*.swo' \
     -o -name '*.tmp' -o -name '*.temp' \
     -o -name '*.bak' -o -name '*.orig' \) \
  -not -path './.git/*' \
  -print | sort
```

Check whether candidates are tracked:

```bash
git ls-files -- \
  '*DS_Store' '*Thumbs.db' '*.swp' '*.swo' \
  '*.tmp' '*.temp' '*.bak' '*.orig' \
  '.playwright-mcp/*' \
  'HelmetsanWeb/scripts/metal_bot.log' \
  'HelmetsanWeb/scripts/metal_bot.pid' \
  'HelmetsanWeb/scripts/metal_bot_staging.json' \
  'HelmetsanWeb/scripts/metal_bot_state.json' \
  'HelmetsanWeb/scripts/swarm_leases.db-shm' \
  'HelmetsanWeb/scripts/swarm_leases.db-wal' \
  2>/dev/null || true
```

## C. Search references before removing named directories/files

```bash
git grep -nE \
  '\.playwright-mcp|playwright-report|test-results|metal_bot|swarm_leases|catalog\.db|HelmetsanWeb/scripts/archive|aggregate_daily_clicks|affiliate_telemetry|multi_engine_ingest|sync_data\.sh|deploy\.sh' \
  -- ':!*.log' ':!*.db-wal' ':!*.db-shm' || true
```

Search executable and configuration files more broadly:

```bash
grep -RInE \
  'metal_bot|swarm_leases|catalog\.db|HelmetsanWeb/scripts|aggregate_daily_clicks|affiliate_telemetry|multi_engine_ingest|sync_data\.sh|deploy\.sh' \
  . \
  --exclude-dir=.git \
  --exclude-dir=node_modules \
  --exclude-dir=vendor \
  --exclude='*.log' \
  --exclude='*.db-wal' \
  --exclude='*.db-shm' || true
```

External references must be checked separately with `crontab`, `systemctl`, deployment configuration, and the live host.

## D. Quarantine generated junk

Create a quarantine outside the repository:

```bash
QUARANTINE="../$(basename "$ROOT")-cleanup-quarantine-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$QUARANTINE"
```

Copy rather than immediately destroy untracked candidates:

```bash
for p in \
  .playwright-mcp \
  HelmetsanWeb/scratch \
  HelmetsanWeb/scripts/metal_bot.log \
  HelmetsanWeb/scripts/metal_bot.pid \
  HelmetsanWeb/scripts/metal_bot_staging.json \
  HelmetsanWeb/scripts/metal_bot_state.json \
  HelmetsanWeb/scripts/swarm_leases.db-shm \
  HelmetsanWeb/scripts/swarm_leases.db-wal
do
  if [ -e "$p" ] || [ -L "$p" ]; then
    mkdir -p "$QUARANTINE/$(dirname "$p")"
    cp -a -- "$p" "$QUARANTINE/$p"
  fi
done
```

If SQLite files are present, stop here until process and open-file checks have passed:

```bash
lsof -- \
  HelmetsanWeb/scripts/swarm_leases.db-shm \
  HelmetsanWeb/scripts/swarm_leases.db-wal \
  2>/dev/null || true
```

## E. Remove only confirmed junk

For tracked files, use `git rm`, not plain `rm`, so the change is explicit:

```bash
git rm -f --ignore-unmatch \
  ':(glob)**/.DS_Store' \
  ':(glob)**/Thumbs.db' \
  ':(glob)**/*.swp' \
  ':(glob)**/*.swo'
```

For untracked files, remove only the exact reviewed paths:

```bash
find . -type f \
  \( -name '.DS_Store' -o -name 'Thumbs.db' \
     -o -name '*.swp' -o -name '*.swo' \) \
  -not -path './.git/*' \
  -not -path "$QUARANTINE/*" \
  -delete
```

Do not use `git clean -fdx`.

For `.playwright-mcp` and `scratch`, delete only after the reference review:

```bash
if [ -d .playwright-mcp ]; then
  rm -rf -- .playwright-mcp
fi

if [ -d HelmetsanWeb/scratch ]; then
  rm -rf -- HelmetsanWeb/scratch
fi
```

For runtime files, only remove files from this checkout after confirming they are not live service paths:

```bash
rm -f -- \
  HelmetsanWeb/scripts/metal_bot.log \
  HelmetsanWeb/scripts/metal_bot.pid \
  HelmetsanWeb/scripts/metal_bot_staging.json \
  HelmetsanWeb/scripts/metal_bot_state.json \
  HelmetsanWeb/scripts/swarm_leases.db-shm \
  HelmetsanWeb/scripts/swarm_leases.db-wal
```

For tracked runtime files, use:

```bash
git rm -f --ignore-unmatch \
  HelmetsanWeb/scripts/metal_bot.log \
  HelmetsanWeb/scripts/metal_bot.pid \
  HelmetsanWeb/scripts/metal_bot_staging.json \
  HelmetsanWeb/scripts/metal_bot_state.json \
  HelmetsanWeb/scripts/swarm_leases.db-shm \
  HelmetsanWeb/scripts/swarm_leases.db-wal
```

Do not run both forms blindly; use the tracked/untracked result from the inventory.

## F. Add narrowly scoped ignore rules

```bash
cat >> .gitignore <<'EOF'

# Local browser/test output
.playwright-mcp/
playwright-report/
test-results/

# Runtime process artifacts
*.log
*.pid
*.db-shm
*.db-wal
*.sqlite-shm
*.sqlite-wal
EOF
```

Review whether `*.log` and `*.pid` are too broad for this repository. If schema fixtures or test databases exist, do not add a broad `*.sqlite*` rule.

## G. Validate before committing

```bash
git diff --check
git status --short
git diff --stat
git diff -- .gitignore
```

Run the project’s actual validation commands, for example:

```bash
npm test
npm run build
composer test
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

Use only commands that are defined by the repository. Then inspect:

```bash
git diff --name-status
git diff --summary
```

Commit cleanup separately from any script relocation or application-code change:

```bash
git add .gitignore
git add -u
git commit -m "chore: remove confirmed generated repository artifacts"
```

---

# 3. Documentation retention policy

## Canonical truth

Keep one authoritative version of each operational fact in `docs/` or the relevant project README.

Preserve as canonical:

- Current architecture and dependency boundaries.
- Current deployment and rollback procedure.
- Current backup and restore procedure.
- Current cron and systemd responsibilities.
- Current data-retention and migration policy.
- Current environment-variable contract, excluding secret values.
- Current WordPress hooks, routes, AJAX actions, cron hooks, and CLI commands.
- Current ownership and escalation information.
- Current supported scripts and their invocation examples.
- Security and incident-response procedures.
- Decisions that explain why a supported behavior or dependency exists.

Canonical documents should have:

- An owner.
- A last-reviewed date.
- A scope.
- A link to the implementation or deployment unit.
- No credentials or production secrets.

## Archive

Archive, outside active execution paths:

- Superseded deployment guides.
- Historical consultation scripts.
- Old architecture diagrams.
- Retired provider comparisons.
- Historical audit outputs.
- Prior migration plans.
- Old server configurations after sanitization.
- Replaced runbooks that may be needed for provenance or compliance.

Each archived item should include a small metadata file or README stating:

```text
Original path:
Archived date:
Source commit:
Status:
Reason for archival:
Replacement:
Operationally executable: no
```

## Delete

Delete rather than archive:

