#!/bin/sh
# Rasterize the icon pack from the SVG sources in public/icons. Requires rsvg-convert (brew install librsvg).
set -eu
cd "$(dirname "$0")/../public/icons"
command -v rsvg-convert >/dev/null || { echo "rsvg-convert not found (brew install librsvg)" >&2; exit 1; }
rsvg-convert -w 16 -h 16 favicon.svg -o favicon-16x16.png
rsvg-convert -w 32 -h 32 favicon.svg -o favicon-32x32.png
rsvg-convert -w 180 -h 180 -b '#0a121c' icon.svg -o apple-touch-icon.png
rsvg-convert -w 192 -h 192 -b '#0a121c' icon.svg -o icon-192.png
rsvg-convert -w 512 -h 512 -b '#0a121c' icon.svg -o icon-512.png
rsvg-convert -w 512 -h 512 -b '#0a121c' icon-maskable.svg -o icon-maskable-512.png
echo "icons rebuilt"
