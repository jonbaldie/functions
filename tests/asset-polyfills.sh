#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/assets"
cp "$root/webpack.config.js" "$root/package.json" "$tmp/"
cp "$root/assets/app.js" "$tmp/assets/app.js"
printf '\ntypeof axios;\n' >> "$tmp/assets/app.js"
ln -s "$root/node_modules" "$tmp/node_modules"
cd "$tmp"
./node_modules/.bin/encore dev
./node_modules/.bin/encore production
