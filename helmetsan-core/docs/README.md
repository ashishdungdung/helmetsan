# Helmetsan Core Docs

This folder is rendered in the in-plugin Documentation tab.

## Available now
- Plugin bootstrap
- CPT and taxonomy registration
- Admin information architecture
- WP-CLI command surface
- Validation, health, ingestion, sync, analytics smoke-test scaffolds

## Next implementation
1. GitHub API client + PR workflow.
2. Full JSON Schema validator integration.
3. Transactional import/write model.
4. Analytics script injection with compatibility gates.
5. GeneratePress child theme integration blocks.

## Smart Build Policy
- Batch-first processing for ingestion/sync jobs.
- Single-pass validation + write pipeline.
- Skip unchanged entities by hash/checkpointing.
- Dry-run mandatory for new data sources before write.
- Keep AI generation opt-in to avoid credit waste.
