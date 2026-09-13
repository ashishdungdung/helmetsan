# Local AI Protocol & Unavailability Strategy

## 1. Local AI Node Architecture
- **Primary Inference Endpoint**: Local LM Studio on `http://127.0.0.1:1234/v1` (port 1234).
- **Primary Reasoning Model (Node A)**: `google/gemma-4-12b-qat`.
  - **Reasoning Token Overhead**: Gemma-4 natively generates internal thinking tokens (~280 tokens).
  - **Budget Requirement**: Always set `max_tokens >= 500` for Gemma-4 completions. Low token limits (e.g. 100) will exhaust during reasoning and truncate the output payload.
  - **Concurrency**: Throttle to 1x–2x parallel requests to maintain GPU thermal stability on the M4 Pro.

## 2. Unavailability & Offline Policy (Zero Cloud Token Spills)
Before running batch enrichment or extraction, verify health: `bash scripts/check-lm-studio.sh`.
- **Bulk Operations (50+ items)**:
  - **Strict Cloud Quarantine**: NEVER fall back to cloud Gemini for bulk catalog tasks.
  - **Queue & Pause**: If LM Studio is offline or unreachable, serialize remaining IDs into `data/task_queue.json` and exit gracefully. Resume when the local server is running.
- **Single-Item Tactical Fixes (1 item)**:
  - If a user explicitly requests a quick single-helmet fix in chat while LM Studio is offline, IDE AI handles that single file directly (~500 tokens).

## 3. Data Quality & Self-Correction Feedback Loop
Every AI output must be validated against `Validator.php` before writing:
1. **Schema Check**: `Validator::validateSchema` checks required keys and types.
2. **Logic Check**: `Validator::validateLogic`:
   - ECE 22.06 non-carbon full-face weight check (must be >= 1250g).
   - Weight range bounds: 800g–3000g.
   - Price must be non-negative.
   - Anti-slop check: `isFallbackDescription` flags generic marketing fluff.
3. **1-Shot Correction Loop**:
   - If validation returns an error, pass the exact error back to the model as feedback for 1 retry.
   - Clean passes (0 errors, 0 warnings) auto-commit.
   - Ambiguous passes are staged in `data/corrections/{entity}_{id}.json`.
