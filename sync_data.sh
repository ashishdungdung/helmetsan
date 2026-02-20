#!/bin/bash
# Helmetsan Data Sync Script
# Usage: ./sync_data.sh
#
# This script rsyncs the local data/ directory to the server's
# actual WP data root (resolved dynamically via WP-CLI), and
# then triggers WP-CLI ingestion for all entity types.

USER="root"
HOST="helmetsan.com"
REMOTE_BASE="/var/www/helmetsan.com/public"

# Retrieve password from sftp.json using perl to handle regex parsing reliably
SSHPASS=$(perl -n -e '/"password":\s*"([^"]+)"/ && print $1' .vscode/sftp.json)

if [ -z "$SSHPASS" ]; then
    echo "❌ Error: Could not extract password from .vscode/sftp.json."
    exit 1
fi

echo "🚀 Starting Data Sync to $HOST ($USER)..."
export SSHPASS

# ──────────────────────────────────────────────────
# 0. PRE-FLIGHT: Dynamically resolve the data root
# ──────────────────────────────────────────────────
echo "🔍 Pre-flight: Resolving remote data root via WP-CLI..."
REMOTE_DATA_ROOT=$(sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" \
    "cd $REMOTE_BASE && wp --allow-root eval 'echo (new \Helmetsan\Core\Support\Config())->dataRoot();' 2>/dev/null")

if [ -z "$REMOTE_DATA_ROOT" ]; then
    echo "⚠️  Warning: Could not resolve data root from WP-CLI. Falling back to default."
    REMOTE_DATA_ROOT="$REMOTE_BASE/wp-content/uploads/helmetsan-data"
fi

echo "📂 Resolved data root: $REMOTE_DATA_ROOT"

# Ensure the remote data root directory exists
sshpass -e ssh -o StrictHostKeyChecking=no "$USER@$HOST" "mkdir -p '$REMOTE_DATA_ROOT'"

# ──────────────────────────────────────────────────
# 1. Sync Data Directory via rsync
# ──────────────────────────────────────────────────
echo "📦 Rsyncing local data directory to server..."
sshpass -e rsync -avzi \
    --exclude='.DS_Store' \
    -e "ssh -o StrictHostKeyChecking=no" \
    data/ "$USER@$HOST:$REMOTE_DATA_ROOT/"

if [ $? -ne 0 ]; then 
    echo "❌ Rsync failed"; 
    exit 1; 
fi

# ──────────────────────────────────────────────────
# 2. Validate: Confirm file counts match
# ──────────────────────────────────────────────────
LOCAL_HELMETS=$(find data/helmets -name '*.json' 2>/dev/null | wc -l | tr -d ' ')
LOCAL_BRANDS=$(find data/brands -name '*.json' 2>/dev/null | wc -l | tr -d ' ')
LOCAL_ACCESSORIES=$(find data/accessories -name '*.json' 2>/dev/null | wc -l | tr -d ' ')

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
    
    cd "$REMOTE_BASE"
    echo "   -> Ingesting Brands..."
    wp --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("brands", 200);'
    
    echo "   -> Ingesting Helmets..."
    wp --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("helmets", 200);'
    
    echo "   -> Ingesting Accessories..."
    wp --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("accessories", 200);'
    
    echo "   -> Ingesting Motorcycles..."
    wp --allow-root eval 'helmetsan_core()->ingestion()->ingestPath("motorcycles", 200);'
    
    echo "   -> Flushing Cache..."
    wp --allow-root cache flush
    
    echo "   -> Post-ingestion counts:"
    echo "      Helmets:     \$(wp --allow-root post list --post_type=helmet --post_status=publish --format=count)"
    echo "      Brands:      \$(wp --allow-root post list --post_type=brand --post_status=publish --format=count)"
    echo "      Accessories: \$(wp --allow-root post list --post_type=accessory --post_status=publish --format=count)"
EOF

if [ $? -eq 0 ]; then
    echo "✅ Data Sync and Server Ingestion Successful!"
else
    echo "❌ Server ingestion failed."
    exit 1
fi
