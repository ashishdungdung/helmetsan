#!/bin/bash
# Helmetsan Deployment Script
# Usage: ./deploy.sh [password]

# Configuration from .vscode/sftp.json
USER="root"
HOST="helmetsan.com"
REMOTE_BASE="/var/www/helmetsan.com/public"
REMOTE_CONTENT="$REMOTE_BASE/wp-content"

# Optional: Password support via sshpass if installed, otherwise relies on SSH keys or interactive prompt
SSHPASS=""
if [ ! -z "$1" ]; then
    SSHPASS="sshpass -p $1"
fi

echo "🚀 Starting Deployment to $HOST ($USER)..."
echo "📂 Remote Path: $REMOTE_CONTENT"

# 1. Check Artifacts
if [ ! -f "dist/helmetsan-core.zip" ] || [ ! -f "dist/helmetsan-theme.zip" ]; then
    echo "❌ Error: Deployment artifacts not found in dist/. Please check previous steps."
    exit 1
fi

# 2. Upload Files
echo "📦 Uploading artifacts..."
$SSHPASS scp dist/helmetsan-core.zip "$USER@$HOST:$REMOTE_CONTENT/plugins/"
if [ $? -ne 0 ]; then echo "❌ Plugin upload failed"; exit 1; fi

$SSHPASS scp dist/helmetsan-theme.zip "$USER@$HOST:$REMOTE_CONTENT/themes/"
if [ $? -ne 0 ]; then echo "❌ Theme upload failed"; exit 1; fi

# 3. Remote Extraction
echo "📂 Extracting on server..."
$SSHPASS ssh "$USER@$HOST" << EOF
    # Stop on error
    set -e
    
    # 3a. Plugin
    echo "   -> Extracting Plugin..."
    cd "$REMOTE_CONTENT/plugins/"
    unzip -q -o helmetsan-core.zip
    rm helmetsan-core.zip
    
    # 3b. Theme
    echo "   -> Extracting Theme..."
    cd "$REMOTE_CONTENT/themes/"
    unzip -q -o helmetsan-theme.zip
    rm helmetsan-theme.zip
    
    # 3c. Permissions (Ensure ww-data owns files)
    echo "   -> Setting permissions..."
    chown -R www-data:www-data "$REMOTE_CONTENT/plugins/helmetsan-core"
    chown -R www-data:www-data "$REMOTE_CONTENT/themes/helmetsan-theme"
    
EOF

if [ $? -eq 0 ]; then
    echo "✅ Deployment Successful!"
else
    echo "❌ Remote extraction failed."
    exit 1
fi
