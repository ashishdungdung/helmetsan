# AI Module

The Helmetsan AI module provides a single, configurable layer for all AI-powered features. It is designed for **lowest cost first** (free/low-cost providers) with **premium options** and dedicated controls when needed.

## Phases

| Phase | Purpose | Status |
|-------|---------|--------|
| **1. SEO** | Meta descriptions (title, description, focus keyword) for helmets, brands, accessories | ✅ Live |
| **2. Fill data** | Context-aware filling of missing entity fields (shell material, brand story, category, etc.) | ✅ Live |
| **3. Integrity** | Data quality, accuracy, reliability checks | 🔜 Planned |
| **4. More tools** | Additional AI tools (TBD) | 🔜 Planned |

## Providers

### Free / low-cost (use these first)

- **Groq** – Fast inference, generous free tier (e.g. llama-3.1-8b-instant).
- **Google Gemini** – Free tier (e.g. gemini-1.5-flash).
- **Mistral AI** – Free tier available.
- **OpenRouter** – Single API for many models; free tiers per model.
- **Hugging Face** – Inference API with free tier.

### Premium (dedicated controls)

- **OpenAI (ChatGPT)** – e.g. gpt-4o-mini.
- **Perplexity** – e.g. sonar.

## Configuration

1. In WP Admin go to **Helmetsan → AI**.
2. Enable at least one **free** provider and set its **API key** and **model** (defaults are pre-filled).
3. Optionally enable **premium** providers and set default free/premium providers.
4. Enable **Phase 1 (SEO)** to use AI for `wp helmetsan seo seed --use-ai`.

API keys are stored in the `helmetsan_ai` option; they are not written to code or env by the UI. For CLI-only use you can still set `GROQ_API_KEY` / `GEMINI_API_KEY` (or constants in wp-config); the module uses plugin settings when available and falls back to env/constants.

## Usage

- **Phase 1 – SEO seed with AI:** `wp helmetsan seo seed --use-ai` (uses enabled providers, load-balanced).
- **Phase 2 – Fill missing fields:**  
  `wp helmetsan ai fill-missing [--post-type=helmet|brand|accessory|all] [--limit=N] [--offset=N] [--dry-run] [--fields=key1,key2] [--only-incomplete] [--verbose] [--strict]`  
  - Fills only **empty** meta; context-aware from existing data. Default limit 50 per type.  
  - **--dry-run** – Do not save; only report counts.  
  - **--fields** – Comma-separated meta keys to fill (e.g. `head_shape,technical_analysis`). If omitted, all fillable fields are used.  
  - **--only-incomplete** – Only process posts that have at least one empty fillable field (skips fully filled posts).  
  - **--verbose** – Log each filled field (post ID, meta key) and each failure (post ID, field, reason).  
  - **--strict** – On invalid or empty AI output, leave field empty and do not retry (saves API calls).  
  - **Constraints:** Per-field `max_length` truncation and `allowed_values` validation (e.g. `head_shape`: long-oval, intermediate-oval, round-oval). `brand_founded_year` must be 1900–current year.  
  - **Retry:** One retry on empty response; if the value fails validation (e.g. not in allowed list), one retry with feedback (“Your previous reply was invalid; you must reply with exactly one of: …”).  
  - **Cache:** Identical context (post type, field, existing data hash) is cached for 24 hours to avoid duplicate API calls on re-runs.

## Architecture

- **ProviderInterface** – Contract for any provider (Groq, Gemini, etc.).
- **ProviderRegistry** – Reads `helmetsan_ai` option, builds provider instances.
- **AiService** – High-level API: `generate()`, `generateSeoDescription()`, `generateFillField()`, `checkIntegrity()`.
- **ContextBuilder** – Builds prompts for SEO, fill-field, and integrity (context-aware from entity data).
- **Admin** – **Helmetsan → AI** page: API keys, enable/disable, model, free vs premium, phase toggles.

All AI features (current and future) go through this module so cost and provider choice stay in one place.
