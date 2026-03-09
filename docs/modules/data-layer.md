# Data Layer Module

> Local JSON repository and PHP-based validation at ingest.

## Files

| File                                                                                                                 | Lines | Purpose                                   |
| -------------------------------------------------------------------------------------------------------------------- | ----- | ----------------------------------------- |
| [JsonRepository.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Repository/JsonRepository.php) | ~150  | Local JSON file CRUD operations           |
| [Validator.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Validation/Validator.php)           | ~200  | PHP-only validation + integrity checks    |
| [DataService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Data/DataService.php)             | ~100  | Data utility functions                    |

## JsonRepository

Manages the local JSON data directory (`wp-content/uploads/helmetsan-data/` by default):

| Method            | Purpose                             |
| ----------------- | ----------------------------------- |
| `exists()`        | Check if data directory exists      |
| `listJsonFiles()` | List all `.json` files recursively  |
| `readJson()`      | Parse a JSON file into array        |
| `writeJson()`     | Write array as formatted JSON       |
| `dataPath()`      | Get absolute path to data directory |

## Validator (plugin ingestion)

**Validation at ingest is PHP-only.** The plugin does not load JSON Schema files from `data/schemas/`. Validator provides:

- **validateSchema()**: Required `id`, optional `specs.weight_g` (integer).
- **validateLogic()**: Weight range warnings, `legal_status` structure.
- **validateIntegrity()**: Placeholder for cross-reference checks.

Schemas in **`data/schemas/`** are the authoritative reference for **CI validation and AI agents**; they are not used by the plugin during ingestion. See **`docs/schemas-and-validation.md`** for how schemas and plugin validation relate.

## JSON Data Structure

```
data/
├── schemas/          # JSON Schema definitions
├── helmets/          # Helmet data files
├── brands/           # Brand data files
├── accessories/      # Accessory data files
├── motorcycles/      # Motorcycle compatibility
├── safety-standards/ # ECE, DOT, Snell definitions
├── dealers/          # Dealer directory
├── distributors/     # Distributor directory
├── countries/        # Country definitions
├── currencies/       # Currency definitions
├── marketplaces/     # Marketplace definitions
├── pricing/          # Price records
├── offers/           # Offer records
├── comparisons/      # Comparison data
└── recommendations/  # Personalized recommendations
```
