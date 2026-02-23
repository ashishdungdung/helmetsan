#!/bin/bash
# Sync data from Remote Server to Local Repo

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
[ -f "$SCRIPT_DIR/config" ] || { echo "❌ scripts/config not found. Run from repo root."; exit 1; }
. "$SCRIPT_DIR/config"
[ -n "${REMOTE_PLUGIN_DATA_PATH:-}" ] || { echo "❌ REMOTE_PLUGIN_DATA_PATH not set in scripts/config."; exit 1; }
REMOTE_PATH="$REMOTE_PLUGIN_DATA_PATH"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
LOCAL_PATH="$ROOT_DIR/helmetsan-core/data"
PASSWORD="CQz7nF0HSd"

echo "🔄 Syncing Data from ${USER}@${HOST}..."

./scripts/deploy-rsync.expect "$PASSWORD" "${USER}@${HOST}:${REMOTE_PATH}/" "$LOCAL_PATH/"

if [ $? -eq 0 ]; then
    echo "✅ Data Sync Complete!"
else
    echo "❌ Data Sync Failed!"
    exit 1
fi
