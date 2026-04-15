#!/bin/bash
# Helmetsan Data Sync Script
# Usage: ./sync_data.sh
#
# This script rsyncs the local data/ directory to the server's
# actual WP data root (resolved dynamically via WP-CLI), and
# then triggers WP-CLI ingestion for all entity types.

# Resolve the repository root (parent of the scripts/ directory)
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
[ -f "$ROOT_DIR/scripts/config" ] || { echo "❌ scripts/config not found. Checked: $ROOT_DIR/scripts/config"; exit 1; }
. "$ROOT_DIR/scripts/config"
[ -n "${REMOTE_WP_PATH:-}" ] || { echo "❌ REMOTE_WP_PATH not set in scripts/config."; exit 1; }
REMOTE_BASE="$REMOTE_WP_PATH"

# Retrieve password from sftp.json using perl to handle regex parsing reliably
SSHPASS=$(perl -n -e '/"password":\s*"([^"]+)"/ && print $1' "$ROOT_DIR/.vscode/sftp.json")

if [ -z "$SSHPASS" ]; then
    echo "❌ Error: Could not extract password from $ROOT_DIR/.vscode/sftp.json."
    exit 1
fi

# ──────────────────────────────────────────────────
# 0.5 SAFETY CHECK: Git Status
# ──────────────────────────────────────────────────
echo "🔍 Checking Git status..."
git fetch origin main 2>/dev/null
BEHIND=$(git rev-list HEAD..origin/main --count)
if [ "$BEHIND" -gt 0 ]; then
    echo "⚠️  WARNING: Your local branch is behind GitHub by $BEHIND commits."
    echo "   The server may have AI heals that you don't have yet."
    echo "   Recommended: Run 'git pull --rebase' before pushing your data."
    echo ""
    read -p "   Do you want to continue anyway? (y/N) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "🚀 Starting Data Sync to $HOST ($USER)..."
export SSHPASS

# ──────────────────────────────────────────────────
# 0.6 LOCKING: Prevent AI daemon interference
# ──────────────────────────────────────────────────
SYNC_LOCK="/tmp/helmetsan_sync.lock"
echo "🔒 Creating sync lock on server..."
sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" "echo \$\$ > $SYNC_LOCK"

# ──────────────────────────────────────────────────
# 1. PRE-FLIGHT: Dynamically resolve the data root
# ──────────────────────────────────────────────────
echo "📂 Resolving remote data root..."
REMOTE_DATA_ROOT=$(sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" \
    "wp --path=$REMOTE_WP_PATH --allow-root eval 'echo (new \Helmetsan\Core\Support\Config())->dataRoot();' 2>/dev/null")

if [ -z "$REMOTE_DATA_ROOT" ]; then
    REMOTE_DATA_ROOT="$REMOTE_BASE/wp-content/uploads/helmetsan-data"
fi
sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" "mkdir -p '$REMOTE_DATA_ROOT'"

# ──────────────────────────────────────────────────
# 2. Sync Data Directory via rsync (SAFE MODE)
# ──────────────────────────────────────────────────
echo "📦 Syncing local data to server (Safe Mode: --update)..."
# -u (update) skips files that are newer on the receiver (server heals)
sshpass -e rsync -avziu \
    --exclude='.DS_Store' \
    -e "ssh -o StrictHostKeyChecking=no" \
    "$ROOT_DIR/data/" "$USER@$HOST:$REMOTE_DATA_ROOT/"

if [ $? -ne 0 ]; then 
    echo "❌ Rsync failed. Removing lock..."
    sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" "rm -f $SYNC_LOCK"
    exit 1; 
fi

# ──────────────────────────────────────────────────
# 2. Validate: Confirm file counts match
# ──────────────────────────────────────────────────
LOCAL_HELMETS=$(find "$ROOT_DIR/data/helmets" -name '*.json' 2>/dev/null | wc -l | tr -d ' ')
LOCAL_BRANDS=$(find "$ROOT_DIR/data/brands" -name '*.json' 2>/dev/null | wc -l | tr -d ' ')
LOCAL_ACCESSORIES=$(find "$ROOT_DIR/data/accessories" -name '*.json' 2>/dev/null | wc -l | tr -d ' ')

REMOTE_COUNTS=$(sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" \
    "echo \$(find '$REMOTE_DATA_ROOT/helmets' -name '*.json' 2>/dev/null | wc -l):\$(find '$REMOTE_DATA_ROOT/brands' -name '*.json' 2>/dev/null | wc -l):\$(find '$REMOTE_DATA_ROOT/accessories' -name '*.json' 2>/dev/null | wc -l)")

REMOTE_HELMETS=$(echo "$REMOTE_COUNTS" | cut -d: -f1 | tr -d ' ')
REMOTE_BRANDS=$(echo "$REMOTE_COUNTS" | cut -d: -f2 | tr -d ' ')
REMOTE_ACCESSORIES=$(echo "$REMOTE_COUNTS" | cut -d: -f3 | tr -d ' ')

echo "✅ File count validation:"
echo "   Helmets     — Local: $LOCAL_HELMETS  Remote: $REMOTE_HELMETS"
echo "   Brands      — Local: $LOCAL_BRANDS  Remote: $REMOTE_BRANDS"
echo "   Accessories — Local: $LOCAL_ACCESSORIES  Remote: $REMOTE_ACCESSORIES"

if [ "$LOCAL_HELMETS" != "$REMOTE_HELMETS" ] || [ "$LOCAL_BRANDS" != "$REMOTE_BRANDS" ] || [ "$LOCAL_ACCESSORIES" != "$REMOTE_ACCESSORIES" ]; then
    echo "⚠️  Warning: Local and remote file counts do not match! Check rsync output above."
fi

# ──────────────────────────────────────────────────
# 3. Trigger WP Data Ingestion on Server
# ──────────────────────────────────────────────────
echo "⚙️  Triggering WP-CLI ingestion on server..."
sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" << EOF
    set -e
    WP_PATH="$REMOTE_WP_PATH"
    echo "   -> Ingesting Brands..."
    wp --path="\$WP_PATH" --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("brands", 200);'
    
    echo "   -> Ingesting Helmets..."
    wp --path="\$WP_PATH" --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("helmets", 200);'
    
    echo "   -> Ingesting Accessories..."
    wp --path="\$WP_PATH" --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("accessories", 200);'
    
    echo "   -> Ingesting Motorcycles..."
    wp --path="\$WP_PATH" --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("motorcycles", 200);'
    
    echo "   -> Flushing Cache..."
    wp --path="\$WP_PATH" --allow-root cache flush
    
    echo "   -> Post-ingestion counts:"
    echo "      Helmets:     \$(wp --path="\$WP_PATH" --allow-root post list --post_type=helmet --post_status=publish --format=count)"
    echo "      Brands:      \$(wp --path="\$WP_PATH" --allow-root post list --post_type=brand --post_status=publish --format=count)"
    echo "      Accessories: \$(wp --path="\$WP_PATH" --allow-root post list --post_type=accessory --post_status=publish --format=count)"
EOF

if [ $? -eq 0 ]; then
    echo "✅ Data Sync and Server Ingestion Successful!"
    echo "🔓 Removing sync lock..."
    sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" "rm -f $SYNC_LOCK"
else
    echo "❌ Server ingestion failed. Lock remains for safety."
    exit 1
fi
