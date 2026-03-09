HELMETSAN.COM AI GOVERNANCE DOCUMENT
====================================

THIS FILE IS THE PRIMARY AI GOVERNANCE DOCUMENT FOR ALL AGENTS WORKING IN THIS REPOSITORY.

Project Context
---------------

**Objective:** Helmetsan is a comprehensive directory for helmets, with structured data, ingestion pipelines, and Git-backed JSON catalogs.

**Core Components:**

- **Custom WordPress Plugin (`helmetsan-core`)**
  - AI integrations (SEO, fill-missing, enrichment)
  - Custom post types (`helmet`, `brand`, `accessory`, `motorcycle`, etc.)
  - Ingestion, validation, sync, CLI, and background schedulers
- **Custom WordPress Theme (`helmetsan-theme`)**
  - Frontend templates for helmets, brands, accessories, legal pages
  - Assets (CSS/JS), WooCommerce overrides, tracking hooks
- **Data & Sync Layer**
  - JSON catalogs under `data/` (helmets, brands, accessories, geo, pricing, offers, recommendations, comparisons, schemas)
  - Ingestion & validation services
  - GitHub sync (pull/push) and logs

Agent Runtime Context
---------------------

Before executing any task, agents must establish the following runtime context (server defaults):

- `PROJECT_ROOT  = /var/www/helmetsan.com/public/`
- `PLUGIN_ROOT   = /var/www/helmetsan.com/public/wp-content/plugins/helmetsan-core/`
- `THEME_ROOT    = /var/www/helmetsan.com/public/wp-content/themes/helmetsan-theme/`
- `DATA_ROOT     = wp-content/uploads/helmetsan-data/` (resolved via `Helmetsan\Core\Support\Config`)

**Rules:**

- Always confirm `PROJECT_ROOT`, `PLUGIN_ROOT`, or `THEME_ROOT` before file operations.
- All filesystem paths must be relative to the active root you are working under.
- Never assume directories exist — verify first.
- Maintain a list of recently accessed files to avoid repeated reads.
- Maintain a list of completed actions to avoid repeating operations.
- If a command fails, retry at most **3 times**.
- If repeated failures occur, stop and reassess instead of looping.
- Cache results of directory listings during the current task.
- Do not run identical commands consecutively.
- After modifying a file, verify the change succeeded before continuing.
- Prefer modifying existing modules over creating new ones.
- Avoid loading large files into context unless necessary.
- If the repository structure is unclear, list relevant directories before acting.
- When uncertain about paths or environment, prefer conservative assumptions and small diffs.
- Always prioritize minimal, safe changes over large refactors.
- Maintain session state for discovered files and directories.

Agent Task Execution Protocol (ATEP)
------------------------------------

All AI agents must follow this execution protocol when performing tasks.

### Step 1 — Understand the Task

- Parse the objective in your own words.
- Identify affected subsystems:
  - Theme (`helmetsan-theme`)
  - Plugin (`helmetsan-core`)
  - Data/ingestion (`data/`, `IngestionService`, CLI)
  - Git sync (`SyncService`, CLI `sync` commands)
  - AI modules (`includes/AI`, `AiAdmin`, CLI AI commands)
  - Infrastructure / deployment (`scripts/deploy.sh`, `deploy.sh`)
- Determine risk level:
  - **Low:** Localized PHP/JS/markup change, docs only.
  - **Medium:** Changes to ingestion, AI behavior, or CLI flags.
  - **High:** Deployment scripts, database structure, or sync behavior.

### Step 2 — Establish Environment Context

- Confirm appropriate root path (`PROJECT_ROOT`, `PLUGIN_ROOT`, `THEME_ROOT`, or local repo clone).
- Verify repository structure and locate relevant modules:
  - For AI work: `helmetsan-core/includes/AI`, `includes/Admin/AiAdmin.php`, `includes/CLI/Commands.php`.
  - For ingestion: `helmetsan-core/includes/Ingestion`, `includes/Seed`, `includes/Validation`, `data/`.
  - For sync: `helmetsan-core/includes/Sync`, `includes/Data/SyncManager.php`, `includes/GoLive/ChecklistService.php`.
  - For frontend: `helmetsan-theme/` templates and assets.

### Step 3 — Discover Existing Implementations

- Search the repository for similar code before adding new modules.
- Prefer extending existing services:
  - Ingestion behavior → `IngestionService`, CLI `ingest` commands.
  - AI enrichment → `FillMissingService`, `ContextBuilder`, `FillableFieldsConfig`.
  - SEO AI → `Seo/AiSeoDescriptionProvider.php`, AI providers.
  - Git sync → `SyncService`, `Data/SyncManager`, CLI `sync` commands.
- Avoid duplicate services that overlap existing responsibilities.

### Step 4 — Validate Paths & Dependencies

- Confirm all referenced files and directories exist.
- For plugin code, resolve paths via:
  - `plugin_dir_path(__FILE__)`
  - `Helmetsan\Core\Support\Config` helpers
- For theme code, resolve via:
  - `get_template_directory()`
  - `get_stylesheet_directory()`
- Check required dependencies:
  - PHP: rely on existing composer/autoload bootstrap (no new packages unless requested).
  - JS: respect existing build tooling (no new bundlers unless requested).

### Step 5 — Plan Minimal Change

- Identify the smallest code modification required to meet the goal.
- Keep changes scoped to a single service/template whenever possible.
- Avoid rewriting large sections of code.
- Keep AI changes local to AI services (e.g. `AiService`, `FillMissingService`) rather than scattering `wp_remote_post` calls across the codebase.

### Step 6 — Execute Changes

- Apply the change to the correct file(s).
- Maintain formatting, coding standards, and existing comments.
- Prefer using existing abstractions:
  - Use `Helmetsan\Core\Support\Config` for configuration.
  - Use existing logging (`Support/Logger`) for diagnostics.
  - Use existing CLI patterns in `includes/CLI/Commands.php` for new commands.

### Step 7 — Verify Results

- Confirm file modification succeeded (re-read or run lints/tests where available).
- Validate syntax and configuration:
  - PHP: ensure no syntax errors, respect WPCS where feasible.
  - JS: basic syntax sanity checks; avoid breaking the existing bundles.
  - YAML/JSON: validate new or updated config files.

### Step 8 — Prevent Duplicate Actions

- Maintain a simple mental or explicit registry of completed actions.
- Before repeating an operation (e.g. re-running a CLI command, re-applying a patch), check whether it has already been executed successfully in this session.

### Step 9 — Validate System Impact

- For ingestion/sync changes:
  - Consider impact on `wp_helmetsan_ingest_logs` and `wp_helmetsan_sync_logs`.
  - Prefer dry-runs (`--dry-run`, `--limit`) before full runs.
- For infrastructure-related scripts:
  - Validate configuration (e.g. `nginx -t`, `php-fpm -t`) before reload (only if explicitly asked to touch infra).
  - Do not restart services autonomously without user approval.

### Step 10 — Final Confirmation

- Ensure the change satisfies the task objective without obvious side effects.
- Summarize:
  - What changed
  - Where it changed
  - Any follow-up or manual steps the user must perform

Autonomous Change Guardrails (ACG)
----------------------------------

These guardrails limit autonomous changes to protect system stability, infrastructure, and production environments.

### Rule 1 — Minimal Change Principle

- Modify the smallest possible section of code required to complete a task.
- Prefer targeted patches over broad refactors.

### Rule 2 — Scope Containment

- Changes must remain within the subsystem related to the task:
  - AI behavior → `includes/AI`, `AiAdmin`, CLI AI commands.
  - Ingestion behavior → `includes/Ingestion`, CLI `ingest`, ingestion admin screens.
  - Sync behavior → `includes/Sync`, sync admin, CLI sync.
  - Frontend layout → `helmetsan-theme` templates and assets.
- Do **not** modify theme rendering logic when working on a background AI plugin task, and vice versa, unless explicitly requested.

### Rule 3 — File Change Limits

Unless explicitly instructed otherwise:

- Modify **≤ 3 files per task**.
- Modify **≤ 200 lines of code** total (approximate; aim to stay well under this by default).

### Rule 4 — Protected Files

Agents must request explicit confirmation from the user before modifying:

- `wp-config.php`
- `.env` or environment-variable files
- `docker/*`
- `scripts/deploy*` and top-level `deploy.sh`
- `package-lock.json`
- `composer.lock`
- systemd service files or server-level configs

### Rule 5 — Infrastructure Safety

Infrastructure changes must follow a validation workflow:

1. Backup or confirm rollback path.
2. Validate configuration (e.g. `nginx -t`, `php-fpm -t`) if touched.
3. Reload or restart services only with user approval.

### Rule 6 — Database Protection

- Do not run destructive database queries without approval.
- Forbidden without explicit confirmation:
  - `DROP TABLE`
  - `ALTER TABLE`
  - `DELETE FROM` without a `WHERE` clause.
- Prefer WP core functions (`wp_delete_post()`, `wp_update_post()`, `wp_insert_post()`) and existing Helmetsan services.

### Rule 7 — AI & API Safety (Strict Enforcement)

- **Zero Hardcoding**
  - Never hardcode AI API keys (OpenAI, Gemini, Anthropic, Groq, Mistral, etc.).
  - Always use environment or WordPress options:
    - `$_ENV`, `getenv()`, or
    - `get_option()` / `update_option()` (e.g. Helmetsan AI settings page).

- **Asynchronous Processing**
  - AI API calls that take longer than **2 seconds** MUST NOT block HTTP requests.
  - For long-running AI tasks (e.g. bulk fill-missing, SEO descriptions):
    - Use WP-Cron, Action Scheduler, or Helmetsan’s scheduler (`SchedulerService`) / CLI commands (`wp helmetsan ai ...`).
    - For admin-triggered runs, queue jobs rather than blocking page loads.

- **Rate Limit Handling**
  - All AI API calls must:
    - Handle `429 Too Many Requests` gracefully.
    - Implement exponential backoff (e.g. base delay, jitter, capped retries).
    - Respect provider-specific rate limits and per-provider configuration in `AiService`.

- **Cost Management**
  - Log token and usage metrics for AI calls where possible (via `Support/Logger` or dedicated logging).
  - Avoid unbounded loops or recursive AI calls.
  - Prefer batch operations with explicit limits (`--limit`, `--batch-size`) for CLI commands.

### Rule 8 — Dependency Guardrails

- Do not introduce new PHP/JS dependencies unless explicitly required by the task.
- Before using a library, confirm:
  - It already exists in the project, **or**
  - The user has requested its addition and understands implications.

### Rule 9 — Safe Failure Handling

- If an operation fails repeatedly:
  - Stop after **3 attempts**.
  - Reassess context and assumptions.
  - Surface a clear error message rather than retrying indefinitely.

AI Agent Optimization Guidelines
--------------------------------

To ensure high-throughput execution, minimize token costs, and maintain robust structural integrity across the Helmetsan project, adhere to the following strict guidelines.

### 1. Enforce Strict Directory Roles

**Plugin (`helmetsan-core/`):**

- `includes/Core` — Plugin bootstrap (`Plugin`, `DatabaseManager`).
- `includes/CPT` — Custom post types and meta registration.
- `includes/AI` — All third‑party LLM integrations, prompt/context builders, AI services, providers, and fill-missing logic.
- `includes/Ingestion` — Ingestion pipelines (`IngestionService`, ingestion logs), schema validation, and apply operations.
- `includes/Seed` — Seeders and data generation helpers.
- `includes/Sync` & `includes/Data` — GitHub and data sync, sync logs, and Git integration.
- `includes/Scheduler` — Background tasks, schedules, and workers.
- `includes/Seo` — SEO schema and AI SEO description generation.
- `includes/Analytics`, `includes/Revenue`, `includes/Marketplace`, `includes/WooBridge` — Tracking, offers, and commerce integrations.
- `includes/CLI/Commands.php` — All `wp helmetsan` commands (ingest, sync, AI, analytics, etc.).
- `includes/Support` — Shared config, logging, helpers.

**Theme (`helmetsan-theme/`):**

- `templates` / root PHP files — WordPress template hierarchy (single, archive, taxonomy, page).
- `template-parts` — Reusable layout fragments (`helmet` cards, mobile PDP, comparison bars).
- `assets` — Compiled CSS/JS and source files (respect build pipeline).
- `inc` — Theme hooks, enqueue scripts, WooCommerce integration.

**Data & Scripts:**

- `data/` — Source-of-truth JSON catalogs for entities (helmets, brands, accessories, etc.).
- `scripts/` — Deployment, seeding, enrichment, stats, and parallel/batch helpers.
- `tests/` — PHPUnit tests for AI, ingestion, and services.

When adding new behavior:

- Put AI logic in `includes/AI` (or extend `AiService`), not in controllers/templates.
- Put ingestion/validation changes in `includes/Ingestion` / `includes/Validation`, not scattered across CLI.
- Put sync behavior in `includes/Sync` / `includes/Data`.
- Keep templates “dumb”; call into plugin services for complex logic.

### 2. Optimize Token Usage & Context Strategy

- Use narrow, targeted searches:
  - Prefer file‑scoped grep/semantic search over repo‑wide when you know the subsystem.
- When reading files:
  - Prefer partial views (specific line ranges) instead of whole files when possible.
- Modularize tasks:
  - Break work into discrete, isolated steps (e.g. “update provider config” vs “rewrite AI module”).

### 3. Standardize Configuration

- Respect `.editorconfig` rules:
  - PHP: 4 spaces.
  - JS/TS/CSS: 2 spaces.
- Follow WordPress Coding Standards (WPCS) for all PHP files:
  - Meaningful escaping/sanitization.
  - Snake_case for function names where consistent with WP conventions.

### 4. WordPress Development Rules

- Prefer WordPress APIs:
  - `add_action()`, `add_filter()`, `wp_schedule_event()`
  - `WP_Query`, `wp_insert_post()`, `wp_update_post()`, `wp_remote_post()`, `wp_remote_get()`
- Sanitize input:
  - `sanitize_text_field()`, `sanitize_key()`, `absint()`, `sanitize_email()`, etc.
- Escape output:
  - `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
- Admin forms:
  - Always use `wp_nonce_field()` and `check_admin_referer()`.
- Never modify WordPress core (`wp-admin/`, `wp-includes/`).

### 5. Hybrid Architecture Categorization Pattern (Plugin)

All plugin features must strictly separate routing from background logic:

- **Services (`includes/*Service.php`)**
  - Background WordPress hooks, database operations, ingestion/sync, AI modules, schedulers.
  - No direct HTTP routing or REST responses.

- **API Controllers (`includes/API/*Controller.php`)**
  - REST endpoints via `register_rest_route`.
  - Payload validation and JSON responses only.
  - Delegate heavy work to services.

- **AI Modules (`includes/AI/*`)**
  - Third‑party LLM integrations (providers), prompt management, API wrappers, rate limiting, and context builders.
  - No UI or template rendering logic.

### 6. Explicit Constructor / Plugin Registration Pattern

- `Helmetsan\Core\Core\Plugin` is the single source of truth for service instantiation.
- New services / routes / AI modules must be registered explicitly:
  - Add to the plugin bootstrap (services array or registration calls).
  - Avoid “magic” auto‑loaders that hide where logic is wired.
- Prefer constructor injection or explicit setter injection over globals when adding dependencies.

### 7. Golden Rule

When uncertain:

- Modify the smallest possible code section.
- Verify filesystem and service state with safe read‑only operations.
- Avoid infrastructure changes unless specifically requested.
- Prefer documentation and logging improvements over speculative behavior changes.

Agent Capability Matrix (ACM)
----------------------------

This matrix defines which types of changes different agent roles are allowed to perform. In this repo, most automated agents should behave like a combination of **Builder**, **Refactor**, and **Reviewer** under the above guardrails.

| Role            | Code Changes | WP Theme/Plugin | AI Integration | Ingestion/Sync | Infra Changes | Dependency Changes |
|----------------|-------------|-----------------|----------------|----------------|---------------|--------------------|
| Builder Agent   | Yes         | Yes             | Yes            | Yes (guarded)  | No            | Limited            |
| Infra Agent     | No          | Limited (config)| No             | Limited        | Yes (with approval) | Limited       |
| Refactor Agent  | Yes         | Yes             | Yes            | Yes (guarded)  | No            | No                 |
| Reviewer Agent  | Read-only   | Read-only       | Read-only      | Read-only      | Read-only     | Read-only          |
| Docs Agent      | Docs only   | Docs only       | Docs only      | Docs only      | Docs only     | N/A                |

All roles must respect ATEP and ACG at all times.

