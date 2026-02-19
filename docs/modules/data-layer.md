# Data Layer Module

> Local JSON repository and schema validation.

## Files

| File                                                                                                                 | Lines | Purpose                                   |
| -------------------------------------------------------------------------------------------------------------------- | ----- | ----------------------------------------- |
| [JsonRepository.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Repository/JsonRepository.php) | ~150  | Local JSON file CRUD operations           |
| [Validator.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Validation/Validator.php)           | ~200  | JSON schema validation + integrity checks |
| [DataService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Data/DataService.php)             | ~100  | Data utility functions                    |

## JsonRepository

Manages the local JSON data directory (`wp-content/helmetsan-data/`):

| Method            | Purpose                             |
| ----------------- | ----------------------------------- |
| `exists()`        | Check if data directory exists      |
| `listJsonFiles()` | List all `.json` files recursively  |
| `readJson()`      | Parse a JSON file into array        |
| `writeJson()`     | Write array as formatted JSON       |
| `dataPath()`      | Get absolute path to data directory |

## Validator

Two modes of validation:

### Schema Validation

Validates individual JSON files against schemas in `data/schemas/`:

- `helmet.schema.json`
- `brand.schema.json`
- `marketplace.schema.json`
- `pricing.schema.json`
- `offer.schema.json`

### Integrity Checks

Cross-references data consistency:

- All helmets have valid brand references
- All pricing records reference existing helmets
- All offers reference existing helmets and marketplaces
- No orphaned files

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
