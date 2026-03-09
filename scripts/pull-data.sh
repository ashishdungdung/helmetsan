#!/usr/bin/env bash
# Sync data from remote server to local repo (rsync pull).
# Usage: ./scripts/pull-data.sh   (from repo root; requires DEPLOY_PASSWORD and deploy-rsync.expect)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
[ -f "$SCRIPT_DIR/config" ] || { echo "❌ scripts/config not found. Run from repo root."; exit 1; }
. "$SCRIPT_DIR/config"
[ -n "${REMOTE_PLUGIN_DATA_PATH:-}" ] || { echo "❌ REMOTE_PLUGIN_DATA_PATH not set in scripts/config."; exit 1; }

PASSWORD="${DEPLOY_PASSWORD:-}"
if [ -z "$PASSWORD" ]; then
    echo "❌ DEPLOY_PASSWORD is not set. Set it in the environment (e.g. export DEPLOY_PASSWORD=...)."
    exit 1
fi

EXPECT_RSYNC="$SCRIPT_DIR/deploy-rsync.expect"
if [ ! -x "$EXPECT_RSYNC" ]; then
    echo "❌ Expect script not found or not executable: $EXPECT_RSYNC"
    echo "   (Expect scripts are gitignored; create them for rsync-over-SSH.)"
    exit 1
fi

REMOTE_PATH="$REMOTE_PLUGIN_DATA_PATH"
LOCAL_PATH="$ROOT_DIR/helmetsan-core/data"

echo "🔄 Syncing Data from ${USER}@${HOST}..."

if "$EXPECT_RSYNC" "$PASSWORD" "${USER}@${HOST}:${REMOTE_PATH}/" "$LOCAL_PATH/"; then
    echo "✅ Data Sync Complete!"
else
    echo "❌ Data Sync Failed!"
    exit 1
fi
