#!/bin/bash
# scripts/production_ingest_final.sh
# Final Production Ingestion for 2,235 Enriched Helmets & Brand Profiles.

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
. "$ROOT_DIR/scripts/config"

REMOTE_DATA_ROOT="/var/www/helmetsan.com/public/wp-content/uploads/helmetsan-data"
WP_PATH="/var/www/helmetsan.com/public"

echo "🚀 Starting Production Ingestion for Helmetsan..."

# 1. Sync Data (JSON Files)
echo "📦 Syncing JSON data to server..."
rsync -avz --progress "$ROOT_DIR/data/" "$USER@$HOST:$REMOTE_DATA_ROOT/"
if [ $? -ne 0 ]; then echo "❌ Data sync failed"; exit 1; fi

# 1b. Sync Media (AI Hero Images)
echo "🖼  Syncing AI Hero Images to WordPress Uploads..."
ssh "$USER@$HOST" "mkdir -p /var/www/helmetsan.com/public/wp-content/uploads/helmets/"
rsync -avz --progress "$ROOT_DIR/data/media/helmets/" "$USER@$HOST:/var/www/helmetsan.com/public/wp-content/uploads/helmets/"
if [ $? -ne 0 ]; then echo "❌ Media sync failed"; exit 1; fi

# 2. Run WP-CLI Ingestion
echo "⚙️  Executing Remote Ingestion..."
ssh "$USER@$HOST" << EOF
    # Ingest Brands first (to ensure taxonomy terms exist)
    echo "   -> Ingesting Brands..."
    wp --path="$WP_PATH" --allow-root helmetsan ingest-brands
    
    # Ingest Helmets in high-concurrency mode
    echo "   -> Ingesting 2,235 Helmets (Concurrency 4)..."
    wp --path="$WP_PATH" --allow-root helmetsan ingest --data-path=helmets --concurrency=4 --batch-size=50 --force
    
    # Run Cross-Link Finalization (WP Side)
    echo "   -> Finalizing Cross-Links..."
    wp --path="$WP_PATH" --allow-root helmetsan ai cross-link --mode=verify
    
    echo "   -> Flushing Caches..."
    wp --path="$WP_PATH" --allow-root cache flush
EOF

echo "✅ Production Ingestion Complete!"
