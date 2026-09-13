# Token Discipline & Context Optimization Protocol

## 1. Zero Bulk File Dumps
- Never read raw catalog files in bulk. Do not run `list_dir` or `grep_search` across `data/helmets`, `data/motorcycles`, or `data/accessories`.
- Never inspect or dump master RAM index files (`data/helmets_unified_master_memory_index.json` [3.5MB] or `data/compatibility_matrix.json` [12.7MB]). Doing so triggers catastrophic token burn.
- To understand entity structures, inspect the compact schema: `data/schemas/helmet.schema.json` (~150 lines), `accessory.schema.json` (~50 lines), or `motorcycle.schema.json` (~23 lines).

## 2. In-Memory RAM Tools First
Always execute sub-millisecond RAM CLI tools instead of filesystem searches:
- **Fast Similarity & Alternatives**: `php scripts/in_memory_search_engine.php [id]` (<66ms).
- **RAM Catalog Audit & Stats**: `php scripts/in_memory_analytics_engine.php` (<300ms).
- **Recompile Master RAM Index**: `python3 scripts/build_in_memory_catalog_index.py` (<1s).
- **Data Validation Bridge**: `php scripts/id-ai-validate.php {file}`.

## 3. Mandatory Slice Notation for Large Templates
- Never read monolithic templates in full (e.g. `single-helmet.php` is 104KB, `archive-helmet.php` is 50KB).
- Always supply `StartLine` and `EndLine` to inspect targeted slices, or read dedicated components in `template-parts/helmet/` (e.g. `where-to-buy.php`, `quick-verdict.php`).
- Never read or grep `helmetsan-bundle.min.css` (264KB). Edit source CSS in `assets/css/` and recompile via `python3 scripts/bundle_and_minify_css.py`.

## 4. Subagent Sandboxing
- Offload deep exploratory searches, multi-file code comparisons, and broad audit tasks to subagents.
- Subagents execute in isolated context windows, returning a concise summary (10–20 lines) to the main thread, keeping the master transcript lean and fast.
