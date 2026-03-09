#!/usr/bin/env bash
# Run ON THE VPS to fix duplicate "Helmetsan Core" plugin entries and "Could not fully remove" state.
# Use when the Plugins page shows two Helmetsan Core entries stuck in "Deleting..." or failing to remove.
#
# Usage: bash vps-fix-duplicate-helmetsan-core.sh [WP_PATH]
# Default: /var/www/helmetsan.com/public
set -euo pipefail

WP_PATH="${1:-/var/www/helmetsan.com/public}"
PLUGINS_DIR="$WP_PATH/wp-content/plugins"

if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo "❌ Not a WordPress root: $WP_PATH"
    exit 1
fi

WP="wp --path=$WP_PATH"
[ "$(id -u)" = 0 ] && WP="$WP --allow-root"
echo "Using WordPress path: $WP_PATH"

# 1. Remove stray plugin file at plugins root (from bad deploy: contents were dumped into plugins/)
if [ -f "$PLUGINS_DIR/helmetsan-core.php" ]; then
    echo "Removing stray file $PLUGINS_DIR/helmetsan-core.php (causes duplicate plugin entry)"
    rm -f "$PLUGINS_DIR/helmetsan-core.php"
fi

# 2. Deactivate so WordPress stops referencing it for deletion
echo "Deactivating Helmetsan Core..."
$WP plugin deactivate helmetsan-core 2>/dev/null || true

# 3. Dedupe active_plugins in the database (WordPress can list the same plugin twice)
$WP eval '
    $active = get_option("active_plugins", []);
    if (!is_array($active)) $active = [];
    $deduped = array_unique($active);
    if (count($deduped) !== count($active)) {
        update_option("active_plugins", array_values($deduped));
        echo "Removed duplicate entries from active_plugins.\n";
    }
' 2>/dev/null || true

# 4. If the plugin folder exists, activate once
if [ -f "$PLUGINS_DIR/helmetsan-core/helmetsan-core.php" ]; then
    echo "Activating Helmetsan Core (single instance)..."
    $WP plugin activate helmetsan-core
    echo "✅ Done. Refresh the Plugins page; you should see one Helmetsan Core entry."
else
    echo "⚠️  Plugin file not found at $PLUGINS_DIR/helmetsan-core/helmetsan-core.php"
    echo "   Run deploy from local first: ./scripts/deploy.sh"
fi
