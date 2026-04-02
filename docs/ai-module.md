# AI Module

The Helmetsan AI module provides a single, configurable layer for all AI-powered features. It is designed for **lowest cost first** (local/free providers) with **premium options** and dedicated controls when needed.

**Data flow:** AI runs on **WordPress** data. Ingestion reads JSON → WordPress; enrichment happens in WordPress; Export reads WordPress → JSON; Sync pushes JSON → GitHub. See [Data flow](data-flow.md).

## Phases

| Phase | Purpose | Status |
|-------|---------|--------|
| **1. SEO** | Meta descriptions (title, description, focus keyword) | ✅ Live |
| **2. Fill data** | Context-aware filling of missing entity fields | ✅ Live |
| **3. Integrity** | Data quality, accuracy, reliability checks | 🔜 Planned |
| **4. Cross-link** | Rule-based internal link suggestions | ✅ Live |

## Providers

### Local & Free (Recommended)

- **LM Studio** – Run models locally on your hardware (e.g. M4 Pro). Use for batch tasks to save all cloud tokens.
- **Google Gemini** – Generous free tier (e.g. gemini-1.5-flash).
- **Groq** – Fast inference, free tier for small models.

### Premium

- **OpenAI (ChatGPT)** – e.g. gpt-4o-mini.
- **Perplexity** – e.g. sonar (web-aware research).

---

## Local AI (LM Studio)

The plugin is optimized for **LM Studio** running on your local machine. This offloads the entire enrichment cost to your GPU.

### Setup
1. **LM Studio**: Load a model (e.g. Qwen 2.5 Coder 7B), enable "Local Server" on port `1234`.
2. **WP Admin**: Go to **Helmetsan → AI**, enable **LM Studio**.
3. **URL**: Set Base URL to `http://192.168.2.58:1234/v1` (or your local IP).

### Networking (Cloudflare Tunnel)
To reach your local LM Studio from a production server without port forwarding:
1. Run a `cloudflared` tunnel on your Mac targeting port `1234`.
2. Assign a subdomain (e.g. `ai.helmetsan.com`).
3. Set `define('HELMETSAN_LMSTUDIO_BASE_URL', 'https://ai.helmetsan.com/v1');` in `wp-config.php`.
4. See [LM Studio Tunnel Guide](walkthroughs/lm-studio-cloudflare-tunnel.md) for details.

---

## Usage (Enrichment)

### 1. Fill-missing (Blank fields only)
Fills **only** empty meta/taxonomies. Never overwrites existing data.

```bash
# All types, default limit 50
wp helmetsan ai fill-missing

# Helmets only, limit 200
wp helmetsan ai fill-missing --post-type=helmet --limit=200

# Specific fields only
wp helmetsan ai fill-missing --fields=use_case,helmet_family
```

### 2. SEO seed (Overwrites Yoast)
Updates Yoast titles, meta descriptions, and keywords. **Overwrites existing values.**

```bash
# With AI-generated meta descriptions
wp helmetsan seo seed --use-ai

# Preview without saving
wp helmetsan seo seed --dry-run
```

### 3. Cross-link
Overwrites `outgoing_internal_links_json` with suggested links based on taxonomies.

```bash
wp helmetsan ai cross-link --post-type=all
```

---

## Architecture

- **ProviderInterface**: Contract for any provider (LM Studio, Gemini, etc.).
- **ProviderRegistry**: Instantiates providers from plugin settings or constants.
- **AiService**: High-level API used by CLI and Admin.
- **FillMissingService**: Logic for determining what to fill and validating AI responses.
- **ContextBuilder**: Builds entity-specific prompts (HJC + RPHA 11 + ECE 22.06 → "Track focus").

All AI features go through this module so cost and provider choice stay in one place.
