#!/usr/bin/env bash
# Run this ON THE VPS to get a clean theme/plugin set: only GeneratePress + Helmetsan child theme,
# and only Helmetsan Core + WooCommerce + Yoast SEO. Removes all other themes and plugins first.
#
# Usage on server: bash vps-recover-themes-plugins.sh [WP_PATH]
# Default WP_PATH: /var/www/helmetsan.com/public
#
# Prerequisite: Deploy theme and plugin from local first (./scripts/deploy.sh) so
# wp-content/themes/helmetsan-theme and wp-content/plugins/helmetsan-core exist.
set -euo pipefail

WP_PATH="${1:-/var/www/helmetsan.com/public}"
if [ ! -d "$WP_PATH" ] || [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo "❌ Not a WordPress root: $WP_PATH"
    exit 1
fi

WP="wp --path=$WP_PATH"
[ "$(id -u)" = 0 ] && WP="$WP --allow-root"
KEEP_THEMES="generatepress helmetsan-theme"
KEEP_PLUGINS="helmetsan-core"
REQUIRED_PLUGINS="woocommerce wordpress-seo"

echo "Using WordPress path: $WP_PATH"
echo "Step 1: Themes — keep only GeneratePress + helmetsan-theme"

# Ensure GeneratePress exists (parent for child theme)
if ! $WP theme list --field=name 2>/dev/null | grep -q '^generatepress$'; then
    $WP theme install generatepress
fi
# Switch to GeneratePress so we can safely delete other themes
$WP theme activate generatepress

# Delete every theme that we are not keeping
for theme in $($WP theme list --field=name 2>/dev/null); do
    if echo "$KEEP_THEMES" | grep -qw "$theme"; then
        continue
    fi
    echo "  Removing theme: $theme"
    $WP theme delete "$theme" 2>/dev/null || true
done

# Activate Helmetsan child theme (must already be deployed)
if $WP theme list --field=name 2>/dev/null | grep -q '^helmetsan-theme$'; then
    $WP theme activate helmetsan-theme
    echo "  ✅ Active theme: helmetsan-theme"
else
    echo "  ⚠️  helmetsan-theme not found. Deploy from local first: ./scripts/deploy.sh"
fi

echo "Step 2: Plugins — keep only Helmetsan Core + WooCommerce + Yoast; remove the rest"

# Deactivate all so we can delete non-keep plugins
$WP plugin deactivate --all 2>/dev/null || true

# Delete every plugin that is not in KEEP_PLUGINS
for slug in $($WP plugin list --field=name 2>/dev/null); do
    if echo "$KEEP_PLUGINS" | grep -qw "$slug"; then
        continue
    fi
    echo "  Removing plugin: $slug"
    $WP plugin delete "$slug" 2>/dev/null || true
done

# Install required plugins if not present
for slug in $REQUIRED_PLUGINS; do
    if ! $WP plugin list --field=name 2>/dev/null | grep -q "^${slug}$"; then
        echo "  Installing plugin: $slug"
        $WP plugin install "$slug"
    fi
done

echo "Step 3: Configure theme and plugins together (activate all)"

$WP theme activate helmetsan-theme
$WP plugin activate helmetsan-core woocommerce wordpress-seo

echo "Done. Active theme: helmetsan-theme (GeneratePress parent). Active plugins: helmetsan-core, woocommerce, wordpress-seo."
echo "To add more plugins later: wp plugin install <slug> --activate --path=$WP_PATH"
