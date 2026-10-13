#!/usr/bin/env bash
# Copies the PHP Supertext SDK (https://github.com/Supertext/Supertext-API-SDK-and-Guide, sdk/php)
# into lib/supertext-sdk/ and records the commit in lib/supertext-sdk/VERSION.
#
#   bin/update-supertext-sdk.sh [ref]       ref: tag, branch or commit (default: main)
#   bin/update-supertext-sdk.sh --check     CI: fails if lib/supertext-sdk differs from the recorded commit
#
# The SDK isn't on Packagist yet, so the bundle ships a copy (namespace Supertext\Sdk, unchanged).
# Never edit lib/supertext-sdk by hand: fix the SDK repository and run this script.
set -euo pipefail

REPO=https://github.com/Supertext/Supertext-API-SDK-and-Guide
ROOT=$(cd "$(dirname "$0")/.." && pwd)
DEST="$ROOT/lib/supertext-sdk"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

if [ "${1:-}" = "--check" ]; then
	ref=$(sed -n 's/^commit: //p' "$DEST/VERSION")
else
	ref=${1:-main}
fi

git -c advice.detachedHead=false clone -q --filter=blob:none --no-checkout "$REPO" "$TMP/sdk"
git -C "$TMP/sdk" checkout -q "$ref" -- sdk/php/src sdk/php/LICENSE sdk/php/autoload.php
commit=$(git -C "$TMP/sdk" rev-parse "$ref^{commit}")

mkdir -p "$TMP/out"
cp -R "$TMP/sdk/sdk/php/src" "$TMP/out/src"
cp "$TMP/sdk/sdk/php/LICENSE" "$TMP/sdk/sdk/php/autoload.php" "$TMP/out/"
printf 'Supertext SDK for PHP, copied from %s (sdk/php)\ncommit: %s\n' "$REPO" "$commit" > "$TMP/out/VERSION"

if [ "${1:-}" = "--check" ]; then
	if ! diff -r "$TMP/out" "$DEST"; then
		echo "lib/supertext-sdk differs from SDK commit $commit: run bin/update-supertext-sdk.sh $commit" >&2
		exit 1
	fi
	echo "lib/supertext-sdk matches SDK commit $commit"
	exit 0
fi

rm -rf "$DEST"
mkdir -p "$(dirname "$DEST")"
cp -R "$TMP/out" "$DEST"
echo "lib/supertext-sdk updated to SDK commit $commit"
