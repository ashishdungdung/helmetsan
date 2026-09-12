# Language Selector Architecture, Cloudflare Integration & Validation Guide

This document captures the architecture, bug fixes, Cloudflare edge caching compatibility, and validation methodologies implemented for the Helmetsan **Language Selector** component.

---

## 1. Overview & Architecture

### A. Routing & Locale Structure
Helmetsan supports three primary languages via Polylang:
* **English (`en`)**: Master / default locale hosted at root (`https://helmetsan.com/...`).
* **German (`de`)**: Localized directory prefix (`https://helmetsan.com/de/...`).
* **Simplified Chinese (`zh`)**: Localized directory prefix (`https://helmetsan.com/zh/...`).

### B. Currency & Geolocation Decoupling
* The **Language Selector** (`.hs-language-select`) and **Country/Currency Selector** (`.hs-currency-select`) are deliberately decoupled.
* Changing the language updates textual content, localized terms, and Polylang post queries.
* It does **not** override the user's localized pricing currency (which is handled independently via GeoIP and client-side conversion).

---

## 2. Identified Bugs & Implemented Solutions

### Bug 1: The "Homepage Bounce" on Untranslated Catalog Items
* **Root Cause:** In `helmetsan-theme/header.php`, when viewing an untranslated helmet model or term (where `$lang['no_translation'] === true`), Polylang's default fallback URL was the language homepage (`/de/` or `/zh/`). Selecting that language kicked users out of their viewing flow back to the home page without explanation.
* **Fix:** `header.php` now detects `$lang['no_translation'] && !$lang['current_lang']` and renders the option as `disabled` with an informative label (`Deutsch (Unavailable)`).
* **Impact:** Prevents user disorientation and preserves context.

### Bug 2: Inline `onchange` and Content Security Policy (CSP)
* **Root Cause:** The select element relied on `onchange="window.location.href = this.value;"`. This violated strict CSP directives prohibiting inline scripts and caused premature triggers when users browsed options via keyboard arrow keys.
* **Fix:** The inline attribute was removed. A dedicated listener was attached in `helmetsan-theme/assets/js/navigation.js` that checks for valid, non-disabled destinations and prevents duplicate navigation to the currently active page.

### Bug 3: AJAX Catalog Filter Language Leak
* **Root Cause:** When filtering helmets via AJAX, requests went to `/wp-admin/admin-ajax.php?action=helmetsan_filter`. WordPress admin-ajax executes in admin mode where `pll_current_language()` defaults to `'en'`. Because the frontend request didn't send a language parameter, the filter returned English product cards and stored them in `hs_filter_en_...` transients, poisoning German/Chinese catalog views with English titles and URLs.
* **Fix:**
  1. `enqueue.php` passes `'lang' => pll_current_language()` to `helmetsan_ajax`.
  2. `filters.js` appends `&lang=` to the query parameters.
  3. `ajax-filter.php` reads `$_GET['lang']` and sets Polylang's `$GLOBALS['polylang']->curlang` to ensure queries, translated terms, and transients are properly scoped.

### Bug 4: Cloudflare 30-URL Batching Limit & Multilingual Purge
* **Root Cause:** Cloudflare's Purge by URL API (`POST /zones/:zone_id/purge_cache`) has a strict ceiling of **30 URLs per API call**. When posts have translations, JSON endpoints, and taxonomy variations, purging exceeded 30 items, causing Cloudflare to reject the entire batch.
* **Fix:**
  1. Implemented `array_chunk($urls, 30)` in `CloudflareCacheService::purgeUrls`.
  2. Added `purgeMultilingualPaths(array $relativePaths)` to automatically expand and purge master and all localized URL variations (`/`, `/de/`, `/zh/`).
  3. Added an early return if `$urls` is empty to prevent unnecessary credential checks or empty API calls.

---

## 3. Validation Methodology & Automated Tests

### A. Continuous Integration / PHPUnit Suite
Permanent unit test: `tests/Unit/Cloudflare/CloudflareCacheServiceTest.php`.
* **Execution:**
  ```bash
  vendor/bin/phpunit
  ```
* **Coverage:**
  * Validates `purgeUrls([])` returns `true` immediately without network overhead.
  * Validates `purgeMultilingualPaths` correctly resolves master and localized language paths across active locales.

### B. Static Analysis (PHPStan)
* **Execution:**
  ```bash
  vendor/bin/phpstan analyse --memory-limit=1G helmetsan-theme/inc/ajax-filter.php helmetsan-core/includes/Cloudflare/CloudflareCacheService.php
  ```
* **Status:** Passed with `[OK] No errors`.

### C. Behavioral Validation Suite
A dedicated verification script checks 15 critical points:
* Verifies zero inline `onchange` attributes in `header.php`.
* Verifies `navigation.js` disabled-option guards.
* Verifies `enqueue.php` and `filters.js` parameter forwarding.
* Verifies Polylang `$GLOBALS['polylang']->curlang` context switching in `ajax-filter.php`.
* Verifies Cloudflare 30-item chunking and path expansion.
