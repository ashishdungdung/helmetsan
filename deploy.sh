#!/usr/bin/env bash
# =============================================================================
# Helmetsan Unified Production Deployment Pipeline
# =============================================================================
# High-performance delta deployment via rsync with SSH multiplexing.
#
# Usage:
#   bash deploy.sh                → Deploy Theme, Plugin, and ads.txt
#   bash deploy.sh --theme-only   → Fast deploy of helmetsan-theme only
#   bash deploy.sh --plugin-only  → Fast deploy of helmetsan-core only
#   bash deploy.sh --with-data    → Deploy Theme, Plugin, and data/ JSONs
#   bash deploy.sh --data-only    → Deploy data/ catalog JSONs only
#   bash deploy.sh --dry-run      → Preview files without transferring
#   bash deploy.sh [password]     → Provide SSH password (or uses SSH key/agent)
#
# Automatically flushes Nginx microcache and WordPress object cache upon success.
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONFIG_FILE="$ROOT_DIR/scripts/config"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ Error: scripts/config not found at $CONFIG_FILE"
    exit 1
fi

# shellcheck source=/dev/null
. "$CONFIG_FILE"
[ -f "$ROOT_DIR/scripts/.env.deploy" ] && . "$ROOT_DIR/scripts/.env.deploy"

REMOTE_CONTENT="$REMOTE_WP_PATH/wp-content"
THEME_SRC="$ROOT_DIR/helmetsan-theme/"
PLUGIN_SRC="$ROOT_DIR/helmetsan-core/"
DATA_SRC="$ROOT_DIR/data/"

DEPLOY_THEME=true
DEPLOY_PLUGIN=true
DEPLOY_DATA=false
DRY_RUN=""
SSHPASS=""
PASSWORD="${DEPLOY_PASSWORD:-}"

for arg in "$@"; do
    case "$arg" in
        --theme-only)
            DEPLOY_THEME=true
            DEPLOY_PLUGIN=false
            DEPLOY_DATA=false
            ;;
        --plugin-only)
            DEPLOY_THEME=false
            DEPLOY_PLUGIN=true
            DEPLOY_DATA=false
            ;;
        --data-only)
            DEPLOY_THEME=false
            DEPLOY_PLUGIN=false
            DEPLOY_DATA=true
            ;;
        --with-data)
            DEPLOY_DATA=true
            ;;
        --dry-run)
            DRY_RUN="--dry-run"
            echo "🔍 DRY RUN MODE ACTIVATED (No remote files will be modified)"
            ;;
        --help|-h)
            echo "Usage: ./deploy.sh [--theme-only|--plugin-only|--data-only|--with-data|--dry-run] [password]"
            exit 0
            ;;
        --*)
            echo "⚠️  Unknown flag: $arg (ignored)"
            ;;
        *)
            PASSWORD="$arg"
            ;;
    esac
done

echo "🚀 Initiating Unified Deployment to ${USER}@${HOST}..."
echo "📂 Remote Path: $REMOTE_WP_PATH"
echo "   Component Plan: [Theme: $DEPLOY_THEME | Plugin: $DEPLOY_PLUGIN | Data: $DEPLOY_DATA]"
echo ""

# SSH Multiplexing socket setup
SOCKET_PATH="/tmp/hs-deploy-ssh-${USER}@${HOST}"
SSH_OPTS="-o ControlMaster=auto -o ControlPath=${SOCKET_PATH} -o ControlPersist=5m -o StrictHostKeyChecking=no"

if [ -n "$PASSWORD" ]; then
    SSHPASS="sshpass -p $PASSWORD"
fi

# Test/bootstrap connection
echo "⚡ Establishing secure SSH connection to ${HOST}..."
$SSHPASS ssh $SSH_OPTS -fN "${USER}@${HOST}" || true

RSYNC_EXCLUDES=(
    --exclude=".git*"
    --exclude=".DS_Store"
    --exclude="**/__pycache__"
    --exclude="node_modules"
    --exclude="vendor"
    --exclude="scratch"
    --exclude="*.log"
    --exclude=".phpunit.cache"
    --exclude="tests"
)

# ── 1. Deploy Theme ───────────────────────────────────────────────────────────
if [ "$DEPLOY_THEME" = true ]; then
    echo "⚡ [Theme] Compiling and minifying CSS production bundle..."
    python3 "$ROOT_DIR/scripts/bundle_and_minify_css.py"
    echo "📦 [Theme] Syncing helmetsan-theme via rsync..."
    $SSHPASS rsync -avz --delete $DRY_RUN "${RSYNC_EXCLUDES[@]}" \
        -e "ssh $SSH_OPTS" \
        "$THEME_SRC" "${USER}@${HOST}:${REMOTE_CONTENT}/themes/helmetsan-theme/"
    echo "   ✅ Theme synced."
fi

# ── 2. Deploy Plugin ──────────────────────────────────────────────────────────
if [ "$DEPLOY_PLUGIN" = true ]; then
    echo "📦 [Plugin] Syncing helmetsan-core via rsync..."
    $SSHPASS rsync -avz --delete $DRY_RUN "${RSYNC_EXCLUDES[@]}" \
        -e "ssh $SSH_OPTS" \
        "$PLUGIN_SRC" "${USER}@${HOST}:${REMOTE_CONTENT}/plugins/helmetsan-core/"
    echo "   ✅ Plugin synced."
fi

# ── 3. Deploy Data (if requested) ─────────────────────────────────────────────
if [ "$DEPLOY_DATA" = true ]; then
    echo "📦 [Data] Syncing data/ catalog JSONs..."
    $SSHPASS rsync -avz --delete $DRY_RUN "${RSYNC_EXCLUDES[@]}" \
        -e "ssh $SSH_OPTS" \
        "$DATA_SRC" "${USER}@${HOST}:${REMOTE_WP_PATH}/data/"
    echo "   ✅ Data synced."
fi

# ── 4. Deploy ads.txt & llms.txt ──────────────────────────────────────────────
if [ -f "$ROOT_DIR/ads.txt" ] && [ -z "$DRY_RUN" ]; then
    echo "📄 [Ads.txt] Updating root ads.txt..."
    $SSHPASS scp $SSH_OPTS "$ROOT_DIR/ads.txt" "${USER}@${HOST}:${REMOTE_WP_PATH}/ads.txt"
    echo "   ✅ ads.txt updated."
fi

if [ -f "$ROOT_DIR/llms.txt" ] && [ -z "$DRY_RUN" ]; then
    echo "🤖 [llms.txt] Updating root llms.txt AI manifest..."
    $SSHPASS scp $SSH_OPTS "$ROOT_DIR/llms.txt" "${USER}@${HOST}:${REMOTE_WP_PATH}/llms.txt"
    echo "   ✅ llms.txt updated."
fi

if [ -f "$ROOT_DIR/llms-full.txt" ] && [ -z "$DRY_RUN" ]; then
    echo "📚 [llms-full.txt] Updating root llms-full.txt deep manifest..."
    $SSHPASS scp $SSH_OPTS "$ROOT_DIR/llms-full.txt" "${USER}@${HOST}:${REMOTE_WP_PATH}/llms-full.txt"
    echo "   ✅ llms-full.txt updated."
fi

if [ -f "$ROOT_DIR/c9a72e8140db4e5fb3d6812975ef83a0.txt" ] && [ -z "$DRY_RUN" ]; then
    echo "⚡ [IndexNow] Updating IndexNow verification key..."
    $SSHPASS scp $SSH_OPTS "$ROOT_DIR/c9a72e8140db4e5fb3d6812975ef83a0.txt" "${USER}@${HOST}:${REMOTE_WP_PATH}/c9a72e8140db4e5fb3d6812975ef83a0.txt"
    echo "   ✅ IndexNow key updated."
fi

# If dry run, stop before cache invalidation and health checks
if [ -n "$DRY_RUN" ]; then
    echo ""
    echo "🎉 Dry run completed successfully! Remote server was not altered."
    exit 0
fi

# ── 5. Set Permissions & Clear All Caches ─────────────────────────────────────
echo ""
echo "🧹 Finalizing permissions and flushing server caches..."
$SSHPASS ssh $SSH_OPTS "${USER}@${HOST}" << 'REMOTE_COMMANDS'
    set -e
    REMOTE_WP_PATH="/var/www/helmetsan.com/public"
    REMOTE_CONTENT="$REMOTE_WP_PATH/wp-content"

    # Ensure correct web ownership
    chown -R www-data:www-data "$REMOTE_CONTENT/themes/helmetsan-theme" 2>/dev/null || true
    chown -R www-data:www-data "$REMOTE_CONTENT/plugins/helmetsan-core" 2>/dev/null || true

    # Nginx FastCGI microcache purge
    if [ -d "/var/cache/nginx/microcache" ]; then
        find /var/cache/nginx/microcache -type f -delete 2>/dev/null || true
    fi
    systemctl reload nginx 2>/dev/null || nginx -s reload 2>/dev/null && echo "   ✅ Nginx cache purged & service reloaded" || true

    # WordPress transients and object cache
    if command -v wp >/dev/null 2>&1; then
        wp --path="$REMOTE_WP_PATH" --allow-root cache flush 2>/dev/null | grep -v Deprecated || true
        wp --path="$REMOTE_WP_PATH" --allow-root transient delete --all 2>/dev/null | grep -E "Success|No transients" || true
        echo "   ✅ WordPress cache & transients flushed"
    fi
REMOTE_COMMANDS

# ── 6. Health Check ───────────────────────────────────────────────────────────
echo ""
echo "🔍 Running Post-Deployment Health Check..."
$SSHPASS ssh $SSH_OPTS "${USER}@${HOST}" << 'REMOTE_HEALTH'
    REMOTE_WP_PATH="/var/www/helmetsan.com/public"
    if command -v wp >/dev/null 2>&1; then
        PLUGIN_STATUS=$(wp --path="$REMOTE_WP_PATH" --allow-root plugin status helmetsan-core 2>/dev/null | grep -c "Status: Active" || true)
        if [ "$PLUGIN_STATUS" -ge 1 ]; then
            echo "   ✅ Plugin Helmetsan Core: ACTIVE"
        else
            echo "   ⚠️  Plugin Helmetsan Core status warning"
        fi
    fi

    CSS_FILE="$REMOTE_WP_PATH/wp-content/themes/helmetsan-theme/assets/css/helmetsan-bundle.min.css"
    if [ -f "$CSS_FILE" ]; then
        CSS_SIZE=$(wc -c < "$CSS_FILE")
        echo "   ✅ Production CSS Bundle: ${CSS_SIZE} bytes"
    fi

    # Verify origin HTML body delivery
    HTML_BYTES=$(curl -sk --resolve helmetsan.com:443:127.0.0.1 https://helmetsan.com/ | wc -c)
    if [ "$HTML_BYTES" -gt 1000 ]; then
        echo "   ✅ Live HTML Body Verification: ${HTML_BYTES} bytes served"
    else
        echo "   ❌ Warning: Live HTML body is unexpectedly small (${HTML_BYTES} bytes)"
    fi
REMOTE_HEALTH

echo ""
echo "🎉 Deployment Complete & Verified! Live site: https://helmetsan.com/"
