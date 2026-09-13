# Code Integrity & Architectural Guardrails

## 1. Minimal Targeted Patches
- Prefer targeted patches over broad rewrites: ≤3 files, ≤200 lines per task.
- Preserve existing comments and docstrings.
- Edit existing services in `helmetsan-core/includes/` instead of inventing redundant helper classes.

## 2. Protected Files (Strictly Read-Only)
- `wp-admin/`, `wp-includes/`, `.env`, `composer.lock`, and `package-lock.json` must never be edited or modified.

## 3. WordPress Standards & Strict Typing
- Mandatory `declare(strict_types=1);` in all PHP logic files.
- Always use WordPress APIs: `add_action()`, `add_filter()`, `WP_Query`, `wp_remote_post()`.
- Strict sanitization on all inputs: `sanitize_text_field()`, `absint()`, `sanitize_key()`.
- Strict escaping on all outputs: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
- Zero raw SQL queries without explicit justification. Use WordPress database methods with prepared statements: `$wpdb->prepare()`.

## 4. Query Safety: Parent Post Filtering
- Because Helmetsan stores color and graphic variants as child posts (`post_parent > 0`), any query fetching helmets for archives, homepage grids, featured carousels, or recommendation engines must include `'post_parent' => 0`.
- Failure to include `'post_parent' => 0` pollutes the UI with dozens of duplicate child SKUs.

## 5. Zero Secrets
- Never hardcode API keys, passwords, or tokens in source files.
- Always retrieve via `getenv()` or `get_option()`.
