#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
if [ ! -f "$SCRIPT_DIR/config" ]; then
    echo "❌ scripts/config not found. Cannot get REMOTE_WP_PATH. Run from repo root."
    exit 1
fi
. "$SCRIPT_DIR/config"
if [ -z "${REMOTE_WP_PATH:-}" ]; then
    echo "❌ REMOTE_WP_PATH not set in scripts/config."
    exit 1
fi

# Paths to deploy (relative to repo root; run from repo root)
THEME_SRC="$PROJECT_DIR/helmetsan-theme"
PLUGIN_SRC="$PROJECT_DIR/helmetsan-core"
THEME_DEST="${REMOTE_WP_PATH}/wp-content/themes/" 
PLUGIN_DEST="${REMOTE_WP_PATH}/wp-content/plugins/"

if [ ! -d "$THEME_SRC" ] || [ ! -d "$PLUGIN_SRC" ]; then
    echo "❌ Theme or plugin dir missing. Run from repo root (expected: $THEME_SRC, $PLUGIN_SRC)."
    exit 1
fi

echo "🚀 Starting Parallel Deployment to ${USER}@${HOST}..."

# Function to run rsync via expect
deploy_component() {
    local src=$1
    local dest=$2
    local name=$3
    local password="CQz7nF0HSd" # Should be env var in production

    echo "[${name}] Syncing..."
    "$SCRIPT_DIR/deploy-rsync.expect" "$password" "$src" "${USER}@${HOST}:${dest}"
    
    if [ $? -eq 0 ]; then
        echo "[${name}] ✅ Done!"
    else
        echo "[${name}] ❌ Failed!"
        exit 1
    fi
}

# Run in parallel
deploy_component "$THEME_SRC" "$THEME_DEST" "Theme" &
PID_THEME=$!

deploy_component "$PLUGIN_SRC" "$PLUGIN_DEST" "Plugin" &
PID_PLUGIN=$!

# Wait for both processes
wait $PID_THEME
wait $PID_PLUGIN

# Deploy ads.txt to site root (required for AdSense; IAB ads.txt at domain root)
if [ -f "$PROJECT_DIR/ads.txt" ]; then
    echo "[Ads.txt] Copying to site root..."
    password="${DEPLOY_PASSWORD:-CQz7nF0HSd}"
    if "$SCRIPT_DIR/deploy-rsync-file.expect" "$password" "$PROJECT_DIR/ads.txt" "${USER}@${HOST}:${REMOTE_WP_PATH}/"; then
        echo "[Ads.txt] ✅ Done! (available at https://${HOST}/ads.txt)"
    else
        echo "[Ads.txt] ❌ Failed!"
        exit 1
    fi
else
    echo "[Ads.txt] ⚠️  $PROJECT_DIR/ads.txt not found; skipping."
fi

echo "🎉 Deployment Complete!"
