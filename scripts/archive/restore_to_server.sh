#!/usr/bin/env bash
# Helmetsan 1-Click Automated Server Restoration Script
# Usage: ./scripts/restore_to_server.sh <target_server_ip>

set -e

SERVER_IP="${1:-66.179.243.155}"
REMOTE_PATH="/var/www/helmetsan.com/public"

echo "🚀 Starting Full Site & Server Restoration to ${SERVER_IP}..."

# 1. Check local backup artifacts
if [ ! -f "backup/helmetsan_db_backup.sql.gz" ]; then
    echo "❌ Error: Database backup backup/helmetsan_db_backup.sql.gz not found!"
    exit 1
fi

# 2. Upload and restore MySQL database
echo "🗄️ Uploading and importing production database..."
scp backup/helmetsan_db_backup.sql.gz root@${SERVER_IP}:/tmp/
ssh root@${SERVER_IP} "cd ${REMOTE_PATH} && zcat /tmp/helmetsan_db_backup.sql.gz | wp db import - --allow-root && rm /tmp/helmetsan_db_backup.sql.gz"
echo "   ✅ Database restored successfully."

# 3. Sync media uploads and runtime datasets
echo "🖼️ Syncing WordPress uploads and media assets (184MB)..."
rsync -avz backup/uploads/ root@${SERVER_IP}:${REMOTE_PATH}/wp-content/uploads/
echo "   ✅ Uploads synced successfully."

# 4. Deploy codebase (theme + plugin from source)
echo "📦 Building and deploying plugin and theme codebase..."
bash deploy.sh

# 5. Flush Caches & Verify Diagnostics
echo "🧹 Flushing all caches and running health check..."
ssh root@${SERVER_IP} "cd ${REMOTE_PATH} && wp plugin activate woocommerce polylang wordpress-seo redis-cache helmetsan-core --allow-root && wp theme activate helmetsan-theme --allow-root && wp cache flush --allow-root && wp transient delete --all --allow-root && nginx -s reload && wp helmetsan health --allow-root"

echo "🎉 Restoration to ${SERVER_IP} complete! Visit https://helmetsan.com/"
