#!/bin/sh
# Copies the PHP Supertext Plugin Health (version reports to the Supertext Commando Center, the API
# key check result and the ping) from the Commando Center's GitHub repository into
# lib/supertext-plugin-health, next to the SDK's copy. It isn't on Packagist, so the bundle ships
# this copy (namespace Supertext\PluginHealth, unchanged like the SDK's; mapped in composer.json).
# autoload.php is a Composer-free autoloader written by this script (tests, PHPStan).
#
#   scripts/vendor-plugin-health.sh [commit-or-branch]   (default: main)
#   PLUGIN_HEALTH_DIR=/path/to/clone scripts/vendor-plugin-health.sh   (use a local clone as it is)
set -eu
REF="${1:-main}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [ -n "${PLUGIN_HEALTH_DIR:-}" ]; then
    SRC="$PLUGIN_HEALTH_DIR"
else
    git clone --quiet https://github.com/Supertext/Commando-Center-Supertext-Translation "$TMP/center"
    git -C "$TMP/center" -c advice.detachedHead=false checkout --quiet "$REF"
    SRC="$TMP/center"
fi
COMMIT="$(git -C "$SRC" rev-parse HEAD)"
VERSION="$(sed -n "s/.*PLUGIN_HEALTH_VERSION = '\\([^']*\\)'.*/\\1/p" "$SRC/plugin-health/php/src/PluginHealth.php")"

DEST="$ROOT/lib/supertext-plugin-health"
rm -rf "$DEST"
mkdir -p "$DEST"
cp -R "$SRC/plugin-health/php/src" "$DEST/src"
cp "$SRC/LICENSE" "$DEST/LICENSE"
cat > "$DEST/autoload.php" <<'EOT'
<?php

declare(strict_types=1);

/*
 * Autoloader for using Supertext Plugin Health without Composer (tests, PHPStan). Written by
 * scripts/vendor-plugin-health.sh. With Composer, the bundle's composer.json maps the namespace.
 */
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Supertext\\PluginHealth\\')) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, \strlen('Supertext\\PluginHealth\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
EOT
printf '%s\n' "$COMMIT" > "$DEST/COMMIT"
printf '%s\n' "$VERSION" > "$DEST/VERSION"
cat > "$DEST/README.md" <<EOT
# Supertext Plugin Health (bundled copy)

PHP part of [Supertext Plugin Health](https://github.com/Supertext/Commando-Center-Supertext-Translation/tree/main/plugin-health),
version $VERSION, commit \`$COMMIT\`, namespace \`Supertext\\PluginHealth\` (unchanged).

Don't edit these files: fix it in Commando-Center-Supertext-Translation/plugin-health, then run
\`scripts/vendor-plugin-health.sh\`.
EOT
# The namespace stays as it is (like the SDK's): every file must declare it.
OUTSIDE="$(grep -rL '^namespace Supertext\\PluginHealth' "$DEST/src" --include='*.php' || true)"
if [ -n "$OUTSIDE" ]; then
    echo "vendor-plugin-health: files outside the Supertext\\PluginHealth namespace in $DEST/src: $OUTSIDE" >&2
    exit 1
fi
echo "lib/supertext-plugin-health: Supertext Plugin Health (PHP) $VERSION from commit $COMMIT"
