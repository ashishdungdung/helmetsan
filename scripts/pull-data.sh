#!/bin/bash
# Sync data from Remote Server to Local Repo

HOST="helmetsan.com"
USER="root"
REMOTE_PATH="/var/www/helmetsan.com/public/wp-content/plugins/helmetsan-core/data"
LOCAL_PATH="./helmetsan-core/data"
PASSWORD="CQz7nF0HSd"

echo "🔄 Syncing Data from ${USER}@${HOST}..."

./scripts/deploy-rsync.expect "$PASSWORD" "${USER}@${HOST}:${REMOTE_PATH}/" "$LOCAL_PATH/"

if [ $? -eq 0 ]; then
    echo "✅ Data Sync Complete!"
else
    echo "❌ Data Sync Failed!"
    exit 1
fi
