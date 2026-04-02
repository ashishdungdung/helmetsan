# Autonomous Multi-AI Engine Specification

The **Autonomous Multi-AI Engine** is a triple-layered intelligence model designed to maximize cost-efficiency and data integrity. It distributes tasks between the IDE (Gemini), Local Hardware (LM Studio), and the Server (Helmetsan Plugin) based on complexity and cost.


## 🏗️ Architecture: The Three Layers

### 1. IDE AI (Antigravity / Gemini)

- **Role**: Strategic Orchestrator & Bulk Architect.
- **Complexity**: High (Multi-file refactors, complex brand storytelling, architectural design).
- **Cost**: Premium (Conserved for high-value tasks).
- **Workflow**: Orchestrates the local and server AIs, writes core logic, and performs initial seed generation.

### 2. Local AI (LM Studio on Mac M4 Pro)

- **Role**: Background Workhorse & Data Sanitizer.
- **Complexity**: Medium (Spec extraction, schema validation, SEO summary generation, Vision analysis).
- **Cost**: **FREE** (persistent 24/7 background loop).
- **Workflow**: Continuously scans the `data/` directory and WP Database meta to "heal" semantic errors and fill missing values.

### 3. Server AI (Helmetsan Core Plugin)

- **Role**: Real-time Engine & Batch Processor.
- **Complexity**: Operations (Ingestion, live site SEO updates, affiliate inventory enrichment).
- **Cost**: Low/Configurable (Managed via the plugin’s Provider Registry).
- **Workflow**: Executes scheduled WP-Cron tasks and handles live ingestion of GitHub data.

---

## 🛡️ Semantic Integrity: The AI Handshake

Semantic Integrity is maintained by ensuring ALL layers use the same validation logic (`Validator.php`).

### Cross-Validation Chain

1. **IDE AI** generates a draft JSON seed.
2. **IDE AI** calls `id-ai-validate.php`.
3. **Local AI** runs in the background to "audit" the seed and fix minor typos/weights.
4. **Server AI** ingests the validated seed into the Production database.
5. **Local AI** periodically "sweeps" the database to check for drift or missing fields.

---


## 🚀 Autonomous Logic

- **Routing**: If the task involves bulk spec extraction (e.g., "Enrich 50 helmets"), the system defaults to **Local AI**.
- **Correction Loop**: If any AI layer encounters a validation error, it passes the error message to the **Local AI** for a "Correction Prompt" loop until `Validator::validateLogic` returns `ok`.
- **Hybrid Correction Policy (24/7 Sweep)**:
  - **Auto-Commit**: If the Local AI generates a fix that passes all validation rules (schema + logic) with **zero errors and zero warnings**, it will overwrite the source file directly.
  - **Staging**: If the fix still contains warnings (e.g., "Suspicious weight range") or minor logic anomalies, it is saved in `data/corrections/` for your final review.
- **Continuous Sweep**: A local cron/loop script (`continuous-sweep.php`) ensures the catalog is always high-quality, even when the user is not actively developing.
