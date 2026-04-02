# Skill: Autonomous Multi-AI Engine

Integrated instruction set for navigating the **IDE**, **Local**, and **Server** AI layers to build and maintain the Helmetsan project.

## 🧭 Routing Guide: What AI to Use

| Task Complexity          | Priority Layer         | Why?                                          |
|--------------------------|------------------------|-----------------------------------------------|
| New Feature Design       | **IDE AI** (Gemini)    | Deep context and architectural understanding. |
| Multi-file Refactors     | **IDE AI** (Gemini)    | High cognitive overhead.                      |
| **Bulk Spec Extraction** | **Local AI** (LM Studio) | Zero cost, perfect for 50+ items.             |
| **SEO Descriptions**     | **Local AI** (LM Studio) | Content heavy, low logic.                     |
| **Vision Analysis**      | **Local AI** (LM Studio) | High GPU-bound task.                          |
| Ingestion & Sync         | **Server AI** (Plugin) | Native access to WP database and meta.        |

## 🛡️ The "Semantic Guardrail" Workflow

Maintain **Semantic Integrity** across ALL layers by following these strict constraints:

### Layer-Agnostic Validation

- Use `scripts/id-ai-validate.php` to run any JSON payload (from any AI) through the project's `Validator.php`.
- **Pre-Seed Check**: Always validate before saving to `data/`.
- **Pre-Ingest Check**: Always validate before running `wp helmetsan ingest-seed`.

### The 24/7 "Healer" Loop

- The `scripts/continuous-sweep.php` script is the "Background Linter".
- If it finds a specs error (e.g., `weight_g: 5000`), it uses **Local AI** to attempt a fix based on the helmet title/brand.
- It stages corrections in `corrections/` for final approval.

## 🤖 AI "Handshake" Patterns

### Pattern A: "Orchestrated Extraction"

1. **IDE AI** creates the initial research prompt for a brand (e.g., "Shoei 2024 Lineup").
2. **Local AI** processes the bulk extraction and saves to raw JSON.
3. **IDE AI** reviews the JSON and uses the `Validator` to check for anomalies.
4. **Server AI** ingests the clean data.

### Pattern B: "Verification & Audit"

1. **Server AI** flags a helmet with missing images.
2. **Local AI** uses Vision tools to find and analyze high-res assets.
3. **IDE AI** (when active) audits the log and commits the new metadata to GitHub.

## 🚀 Autonomous Logic

- **Routing**: If the task involves bulk spec extraction (e.g., "Enrich 50 helmets"), the system defaults to **Local AI**.
- **Correction Loop**: If any AI layer encounters a validation error, it passes the error message to the **Local AI** for a "Correction Prompt" loop until `Validator::validateLogic` returns `ok`.
- **Hybrid Correction Policy (24/7 Sweep)**:
  - **Auto-Commit**: If the Local AI generates a fix that passes all validation rules (schema + logic) with **zero errors and zero warnings**, it will overwrite the source file directly.
  - **Staging**: If the fix still contains warnings (e.g., "Suspicious weight range") or minor logic anomalies, it is saved in `data/corrections/` for your final review.
- **Continuous Sweep**: A local cron/loop script (`continuous-sweep.php`) ensures the catalog is always high-quality, even when the user is not actively developing.

## 🛠️ Performance & Cost Rules

- **Rule 1**: Avoid using Gemini for tasks that involve more than 20 repetitive items. Use `scripts/check-lm-studio.sh`.
- **Rule 2**: If the Local AI is offline, **PAUSE** bulk tasks instead of falling back to Gemini (to save credits).
- **Rule 3**: Use **PHPStan** for all logic verification first; only use AI for "ambiguous" semantic checks.
