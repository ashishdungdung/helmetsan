# helmetsan-core: Plugin Architecture & Coding Standards

This rule applies specifically to the `helmetsan-core/` plugin subsystem.

## 1. Service Container & Dependency Injection
- All core services must be registered in and accessed via `includes/Core/Plugin.php`.
- Access the container via `helmetsan_core()`.
- Do not instantiate services as loose ad-hoc singletons.

## 2. Strict Typing & Quality Invariants
- Mandatory `declare(strict_types=1);` at the top of every PHP file.
- Use explicit return types and typed properties throughout.
- Follow WordPress Coding Standards (WPCS) with strict sanitization (`sanitize_text_field()`, `absint()`, `sanitize_key()`) on input and escaping (`esc_html()`, `esc_attr()`, `esc_url()`) on output.

## 3. Database & Query Safety
- **Parent Post Filter**: Any query retrieving helmet records must include `'post_parent' => 0` to prevent child color/graphic SKU variants from polluting results.
- **Zero Raw SQL**: Use WordPress query APIs (`WP_Query`, `$wpdb->prepare()`) to prevent SQL injection vulnerabilities.

## 4. Ingestion & Validation Pipelines
- Before updating catalog records in the database, always pass payloads through `Validator::validateSchema` and `Validator::validateLogic`.
- Staged corrections must be logged via `HealRepository` and applied through `HealService`.
