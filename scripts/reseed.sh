#!/usr/bin/env bash
# scripts/reseed.sh — One-command seed → deploy → ingest pipeline
#
# Usage:
#   ./scripts/reseed.sh              # Full pipeline: generate → deploy → ingest
#   ./scripts/reseed.sh --dry-run    # Validate + deploy only, no ingestion writes
#   ./scripts/reseed.sh --validate   # Just validate the seed, no deploy
#   ./scripts/reseed.sh --skip-deploy # Generate + ingest without deploy

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SEED_SCRIPT="$PROJECT_DIR/create_helmets_seed.php"
SEED_OUTPUT="$PROJECT_DIR/helmetsan-core/seed-data/helmets_seed.json"
DEPLOY_SCRIPT="$SCRIPT_DIR/deploy.sh"
REMOTE_HOST="root@helmetsan.com"
REMOTE_WP_PATH="/var/www/helmetsan.com/public"

DRY_RUN=false
VALIDATE_ONLY=false
SKIP_DEPLOY=false

for arg in "$@"; do
    case $arg in
        --dry-run)    DRY_RUN=true ;;
        --validate)   VALIDATE_ONLY=true ;;
        --skip-deploy) SKIP_DEPLOY=true ;;
        --help|-h)
            echo "Usage: $0 [--dry-run] [--validate] [--skip-deploy]"
            echo ""
            echo "Options:"
            echo "  --dry-run       Generate + deploy, but validate-only on server (no DB writes)"
            echo "  --validate      Just validate the seed script locally and exit"
            echo "  --skip-deploy   Skip deploy, only ingest (for when seed is already on server)"
            exit 0
            ;;
    esac
done

echo "╔══════════════════════════════════════════╗"
echo "║       Helmetsan Reseed Pipeline          ║"
echo "╚══════════════════════════════════════════╝"
echo ""

# ── Step 1: Generate ────────────────────────────────────────────────────
echo "▶ Step 1: Generating seed data..."
php "$SEED_SCRIPT" --output="$SEED_OUTPUT" --stats 2>&1
echo "  ✓ Seed JSON written to: $SEED_OUTPUT"
echo ""

# ── Step 2: Validate ────────────────────────────────────────────────────
echo "▶ Step 2: Validating seed data..."
php "$SEED_SCRIPT" --validate 2>&1
echo "  ✓ Validation passed"
echo ""

if $VALIDATE_ONLY; then
    echo "✅ Validate-only mode — stopping here."
    exit 0
fi

# ── Step 3: Deploy ──────────────────────────────────────────────────────
if ! $SKIP_DEPLOY; then
    echo "▶ Step 3: Deploying to production..."
    if [ -x "$DEPLOY_SCRIPT" ]; then
        bash "$DEPLOY_SCRIPT"
        echo "  ✓ Deployment complete"
    else
        echo "  ⚠  Deploy script not found or not executable: $DEPLOY_SCRIPT"
        echo "     Skipping deploy — make sure files are on the server."
    fi
    echo ""
fi

# ── Step 4: Ingest ──────────────────────────────────────────────────────
echo "▶ Step 4: Running ingestion on server..."

INGEST_FLAGS=""
if $DRY_RUN; then
    INGEST_FLAGS="--dry-run"
    echo "  (dry-run mode — no database writes)"
fi

ssh -o StrictHostKeyChecking=no "$REMOTE_HOST" \
    "cd $REMOTE_WP_PATH && wp helmetsan ingest-seed $INGEST_FLAGS --allow-root 2>&1"

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║       ✅ Pipeline Complete!              ║"
echo "╚══════════════════════════════════════════╝"
