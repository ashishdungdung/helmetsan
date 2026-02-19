#!/bin/bash
set -e

# Configuration from .vscode/sftp.json
HOST="helmetsan.com"
USER="root"
REMOTE_PATH="/var/www/helmetsan.com/public"
PORT="22"

# Paths to deploy
THEME_SRC="./helmetsan-theme"
THEME_DEST="${REMOTE_PATH}/wp-content/themes/" 
PLUGIN_SRC="./helmetsan-core"
PLUGIN_DEST="${REMOTE_PATH}/wp-content/plugins/"

echo "🚀 Starting Parallel Deployment to ${USER}@${HOST}..."

# Function to run rsync via expect
deploy_component() {
    local src=$1
    local dest=$2
    local name=$3
    local password="CQz7nF0HSd" # Should be env var in production

    echo "[${name}] Syncing..."
    ./scripts/deploy-rsync.expect "$password" "$src" "${USER}@${HOST}:${dest}"
    
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

echo "🎉 Deployment Complete!"
