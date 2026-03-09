#!/usr/bin/env bash
# Run ON THE VPS (as root or with sudo) to fix "Could not create directory" when installing
# themes or plugins from the WordPress admin. Sets ownership of wp-content to the web server user.
#
# Usage: sudo bash vps-fix-wp-content-permissions.sh [WP_PATH] [WEB_USER]
# Defaults: WP_PATH=/var/www/helmetsan.com/public  WEB_USER=auto-detect (www-data or nginx)
set -euo pipefail

WP_PATH="${1:-/var/www/helmetsan.com/public}"
WEB_USER="${2:-}"

if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo "❌ Not a WordPress root: $WP_PATH"
    exit 1
fi

# Auto-detect web user if not set (owner of php-fpm or nginx worker)
if [ -z "$WEB_USER" ]; then
    if id www-data &>/dev/null; then
        WEB_USER="www-data"
    elif id nginx &>/dev/null; then
        WEB_USER="nginx"
    else
        echo "❌ Could not detect web user. Run as: $0 $WP_PATH www-data"
        exit 1
    fi
    echo "Using web user: $WEB_USER"
fi

echo "Fixing ownership of $WP_PATH/wp-content for $WEB_USER..."
chown -R "$WEB_USER:$WEB_USER" "$WP_PATH/wp-content"
find "$WP_PATH/wp-content" -type d -exec chmod 755 {} \;
find "$WP_PATH/wp-content" -type f -exec chmod 644 {} \;
echo "✅ Done. Try installing GeneratePress again from Appearance → Add Themes, or run:"
echo "   wp theme install generatepress --path=$WP_PATH"
echo "   (as $WEB_USER or root if WP-CLI is available)"
