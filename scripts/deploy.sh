#!/usr/bin/env bash
# Deploy theme and plugin to production via rsync.
# Usage: ./scripts/deploy.sh   (from repo root)
# - If SSH key auth works (ssh USER@HOST): uses rsync over SSH, no password needed.
# - Else set DEPLOY_PASSWORD and use deploy-rsync.expect / deploy-rsync-file.expect for password auth.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
if [ ! -f "$SCRIPT_DIR/config" ]; then
    echo "❌ scripts/config not found. Cannot get REMOTE_WP_PATH. Run from repo root."
    exit 1
fi
. "$SCRIPT_DIR/config"
[ -f "$SCRIPT_DIR/.env.deploy" ] && . "$SCRIPT_DIR/.env.deploy"
if [ -z "${REMOTE_WP_PATH:-}" ]; then
    echo "❌ REMOTE_WP_PATH not set in scripts/config."
    exit 1
fi

PASSWORD="${DEPLOY_PASSWORD:-}"
EXPECT_RSYNC="$SCRIPT_DIR/deploy-rsync.expect"
EXPECT_FILE="$SCRIPT_DIR/deploy-rsync-file.expect"
USE_EXPECT=false
if [ -n "$PASSWORD" ] && [ -x "$EXPECT_RSYNC" ]; then
    USE_EXPECT=true
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
if [ "$USE_EXPECT" = true ]; then
    echo "   (using expect + DEPLOY_PASSWORD)"
else
    echo "   (using SSH key)"
fi

# Deploy one component: rsync over SSH (key or expect+password)
deploy_component() {
    local src=$1
    local dest=$2
    local name=$3

    echo "[${name}] Syncing..."
    
    # We use SSH Multiplexing to collapse all parallel SSH connections into a single underlying TCP connection.
    # This prevents the server's fail2ban firewall from seeing multiple rapid parallel login attempts.
    local SSH_OPTS="ssh -o ControlMaster=auto -o ControlPath=/tmp/hs-deploy-ssh-%r@%h:%p -o ControlPersist=10m"

    if [ "$USE_EXPECT" = true ]; then
        if "$EXPECT_RSYNC" "$PASSWORD" "$src" "${USER}@${HOST}:${dest}"; then
            echo "[${name}] ✅ Done!"
        else
            echo "[${name}] ❌ Failed!"
            exit 1
        fi
    else
        # Sync folder as folder (no trailing slash on src) so we get themes/helmetsan-theme/ and plugins/helmetsan-core/
        if rsync -avz --delete -e "$SSH_OPTS" "$src" "${USER}@${HOST}:${dest}"; then
            echo "[${name}] ✅ Done!"
        else
            echo "[${name}] ❌ Failed!"
            exit 1
        fi
    fi
}

# Ensure master SSH connection is established first to prevent race conditions during parallel startup
if [ "$USE_EXPECT" = false ]; then
    echo "⚡ Bootstrapping shared SSH connection..."
    ssh -o ControlMaster=auto -o ControlPath=/tmp/hs-deploy-ssh-%r@%h:%p -o ControlPersist=10m -fN "${USER}@${HOST}" || true
fi

# Run in parallel
deploy_component "$THEME_SRC" "$THEME_DEST" "Theme" &
PID_THEME=$!

deploy_component "$PLUGIN_SRC" "$PLUGIN_DEST" "Plugin" &
PID_PLUGIN=$!

deploy_component "$SCRIPT_DIR" "${REMOTE_WP_PATH}/" "Scripts" &
PID_SCRIPTS=$!

deploy_component "$PROJECT_DIR/data" "${REMOTE_WP_PATH}/" "Data" &
PID_DATA=$!

# Wait for all processes
wait $PID_THEME
wait $PID_PLUGIN
wait $PID_SCRIPTS
wait $PID_DATA

# Deploy ads.txt to site root (required for AdSense; IAB ads.txt at domain root)
if [ -f "$PROJECT_DIR/ads.txt" ]; then
    echo "[Ads.txt] Copying to site root..."
    if [ "$USE_EXPECT" = true ] && [ -x "$EXPECT_FILE" ]; then
        if "$EXPECT_FILE" "$PASSWORD" "$PROJECT_DIR/ads.txt" "${USER}@${HOST}:${REMOTE_WP_PATH}/"; then
            echo "[Ads.txt] ✅ Done! (available at https://${HOST}/ads.txt)"
        else
            echo "[Ads.txt] ❌ Failed!"
            exit 1
        fi
    else
        if scp "$PROJECT_DIR/ads.txt" "${USER}@${HOST}:${REMOTE_WP_PATH}/"; then
            echo "[Ads.txt] ✅ Done! (available at https://${HOST}/ads.txt)"
        else
            echo "[Ads.txt] ❌ Failed!"
            exit 1
        fi
    fi
else
    echo "[Ads.txt] ⚠️  $PROJECT_DIR/ads.txt not found; skipping."
fi

echo "🎉 Deployment Complete!"
