# Skill: AI Enrichment & Intelligence

A specialized instruction set for the agent to manage Helmetsan's AI pipelines, focusing on **Semantic Integrity**, **Vision AI**, and **Local LLM Efficiency**.

## 1. Semantic Integrity (Data Truthfulness)

Maintain high data quality by cross-reference AI outputs with the project's internal validation rules.

- **Workflow**:
  1. `FillMissingService` generates a field (e.g., `head_shape`).
  2. Use `Validator::validateSchema` to check the data type and presence of required keys.
  3. Use `Validator::validateLogic` for range checks (e.g., `weight_g` should be 800-3000g).
- **Truthfulness Guardrails**:
  - **Certifications**: AI must never "invent" a certification. Only use those documented in the project's `certification` taxonomy.
  - **Correction Prompting**: If a validation error is encountered, retry the AI call with the error as feedback (e.g., *"Your previous response for 'weight_g' was 5000; the allowed range is 800-3000. Please fix."*).
- **Integrity Level**: Treat data as "suspect" until it passes `validateLogic`.

## 2. Vision AI (Asset Analysis)

Leverage the `ImageAnalysisService` to automate catalog media management.

- **Automated Scraping**: Use `wp helmetsan image-enrich` to fetch and analyze external URLs.
- **Is-Relevant Check**: AI must identify and set `is_relevant: false` for junk assets (size charts, promotional banners, random logos). If `is_relevant` is false, the asset should **not** be ingested into R2.
- **Model Detection**: AI should identify the specific model from the image context if it differs from the expected context.
- **Angle Tagging**: Properly identify photo types (e.g., `front-view`, `side-view`, `angled`, `interior`, `visor-close-up`, `lifestyle`).

## 3. Plugin-Based AI Mechanism

The project uses a structured `AiService` layer to manage all interactions.

- **Unified Interface**: Use `AiServiceInterface` for all AI-calling code.
- **Registry & Providers**: Config is managed by `ProviderRegistry`. Never hardcode API keys; they are pulled from the `helmetsan_ai` option.
- **Enrichment Logic**: `FillMissingService` handles the iteration loop and persistence into WordPress post meta.

## 4. Local AI Efficiency (LM Studio)

Ensure all batch tasks follow the "Local AI First" rule strictly to save all cloud tokens.

- **Local Endpoint**: `http://192.168.2.58:1234/v1`.
- **Pre-flight Check**: Always run `scripts/check-lm-studio.sh` before starting any large-scale `wp helmetsan ai fill-missing` or `seo seed` tasks.
- **Model Bias**: Prefer models like **Qwen 2.5 Coder** for structured extraction and **Llama 3** for descriptive SEO content.

## 5. Enrichment Loop

1. **Step 1: Check Health** (`check-lm-studio.sh`).
2. **Step 2: Dry Run** (`--dry-run --verbose`).
3. **Step 3: Enrich** (`--limit=50`).
4. **Step 4: Validate** (`wp helmetsan health`).
5. **Step 5: Export & Sync** (Export to JSON, Push to GitHub).

---

## Technical Context

- **Service Layer**: `Helmetsan\Core\AI\AiService`.
- **Provider Interface**: `Helmetsan\Core\AI\ProviderInterface`.
- **Validation Engine**: `Helmetsan\Core\Validation\Validator`.
