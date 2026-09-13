# Helmetsan — System Anchor & Subsystem Router

## 1. Subsystem Fast Routing
Before performing exploratory searches, navigate directly to the designated subsystem:

| Subsystem | Primary Code Path | Context & Contracts | Scoped Rules |
| :--- | :--- | :--- | :--- |
| **Core Services (47 areas)** | `helmetsan-core/` | `docs/architecture-map.md` | `helmetsan-core/AGENTS.md` |
| **Theme & UI Templates** | `helmetsan-theme/` | `docs/theme-governance.md` | `helmetsan-theme/AGENTS.md` |
| **Catalog Schemas** | `data/schemas/` | `data/schemas/helmet.schema.json` | `.agents/rules/01-token-discipline.md` |
| **In-Memory RAM Analytics**| `scripts/` | `docs/in-memory-data-engine.md` | `scripts/in_memory_*.php` |
| **Monetization & Geo Router**| `helmetsan-core/includes/Revenue/`| `docs/AMAZON_ASSOCIATES_PROGRAM_MATRIX_AND_ONBOARDING.md` | — |
| **Operations & Deployment** | `scripts/` & `deploy.sh` | `docs/OPS_MANUAL.md` | — |
| **Autonomous Multi-AI** | Local LM Studio `:1234` | `.agents/workflows/ai-optimizations.md` | `.agents/rules/02-local-ai-protocol.md` |

## 2. Inviolable Guardrails
1. **Zero Bulk Reading**: Never read raw catalog files in bulk. Use schemas (`data/schemas/*.json`) or in-memory CLI tools.
2. **Local AI Protocol**: Bulk tasks use local LM Studio (`google/gemma-4-12b-qat`, `max_tokens >= 500`). If offline, queue to `data/task_queue.json` and pause; NEVER burn cloud tokens on bulk.
3. **Protected Paths**: `wp-admin/`, `wp-includes/`, `.env`, `composer.lock` are strictly read-only.
4. **Targeted Patches**: ≤3 files, ≤200 lines per patch. Slicing notation (`StartLine`/`EndLine`) mandatory on large templates.
5. **Parent Post Safety**: All helmet queries must use `'post_parent' => 0` to exclude child SKU variants.
6. **Zero Prefix Invalidation**: Never edit this file to record bug logs or sprint items (preserves Gemini KV prompt cache).
