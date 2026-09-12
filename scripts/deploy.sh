#!/usr/bin/env bash
# =============================================================================
# Helmetsan Deployment Forwarder
# =============================================================================
# Forwards execution to the unified root deploy.sh pipeline.
#
# Usage:
#   ./scripts/deploy.sh [options]
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DEPLOY="$(dirname "$SCRIPT_DIR")/deploy.sh"

if [ ! -f "$ROOT_DEPLOY" ]; then
    echo "❌ Root deployment script not found at $ROOT_DEPLOY"
    exit 1
fi

exec bash "$ROOT_DEPLOY" "$@"
