# Content Types Module

> WordPress Custom Post Types and their management services.

## Custom Post Types

| CPT             | Slug              | Service                 | Purpose                             |
| --------------- | ----------------- | ----------------------- | ----------------------------------- |
| Helmet          | `helmet`          | `HelmetService`         | Core product — motorcycle helmets   |
| Brand           | `brand`           | `BrandService`          | Manufacturer brands                 |
| Accessory       | `accessory`       | `AccessoryService`      | Visor, liner, and other accessories |
| Motorcycle      | `motorcycle`      | `MotorcycleService`     | Motorcycle compatibility data       |
| Safety Standard | `safety_standard` | `SafetyStandardService` | ECE, DOT, Snell certifications      |
| Dealer          | `dealer`          | `DealerService`         | Physical retailer directory         |
| Distributor     | `distributor`     | `DistributorService`    | Distribution network directory      |
| Comparison      | `comparison`      | `ComparisonService`     | Head-to-head helmet comparisons     |
| Recommendation  | `recommendation`  | `RecommendationService` | Curated helmet suggestions          |

## Registration

### Registrar (`CPT/Registrar.php`)

Registers all custom post types and taxonomies with WordPress:

- Post types with proper labels, supports, and rewrite rules
- Taxonomies: `brand`, `helmet_type`, `safety_standard`

### MetaRegistrar (`CPT/MetaRegistrar.php`)

Registers post meta fields with `register_post_meta()` for REST API exposure.

## Service Pattern

Each content type service follows a consistent pattern:

```php
final class BrandService {
    public function register(): void {
        // Register admin hooks, meta boxes, etc.
    }

    public function upsert(array $data): array {
        // Create or update from JSON data
    }

    public function findBySlug(string $slug): ?WP_Post {
        // Lookup by slug
    }
}
```

## Key Service Methods

### HelmetService

- Search and query helmets with filters
- Manage helmet variants (`variants_json`)
- Link helmets to brands, safety standards

### BrandService

- Manage brand profiles and metadata
- Brand → helmet relationship
- Logo association via MediaEngine

### ComparisonService

- Generate comparison matrices
- Auto-compare helmets by shared attributes

### RecommendationService

- Curated recommendation lists
- Contextual suggestions (riding style, budget, head shape)

## Taxonomies

| Taxonomy          | Post Type         | Description                         |
| ----------------- | ----------------- | ----------------------------------- |
| `brand`           | helmet, accessory | Manufacturer name                   |
| `helmet_type`     | helmet            | Full-face, modular, open-face, etc. |
| `safety_standard` | helmet            | ECE 22.06, DOT, Snell M2020, etc.   |
