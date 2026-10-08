#!/usr/bin/env bash
# Regenerates the images in art/ from a live Halyard admin panel. Run it after
# changing the plugin's pages or the demo content, then commit art/.
#
# Filament pages are Livewire components, so this serves the demo app with
# `php -S` (see demo/app.php) instead of rendering static files.
#
# Needs PHP (with the dev dependencies installed) and a Chrome or Chromium
# binary: set CHROME=/path/to/chrome if it isn't on the PATH.
set -euo pipefail
cd "$(dirname "$0")/.."

CHROME=${CHROME:-$(command -v chromium || command -v chromium-browser || command -v google-chrome || command -v chrome-headless-shell || true)}
[ -x "$CHROME" ] || { echo "Chrome not found; set CHROME=/path/to/chrome" >&2; exit 1; }

rm -rf build/demo
php demo/app.php seed
revision=$(cat build/demo/deploy-revision)

port=8940
php -S "127.0.0.1:$port" -t build/demo/public demo/server.php >/dev/null 2>&1 &
server=$!
trap 'kill $server' EXIT
sleep 1

mkdir -p art
shot() { # name, path, width,height, [extra chrome flags]
    "$CHROME" --headless --no-sandbox --hide-scrollbars --force-device-scale-factor=2 \
        --virtual-time-budget=8000 ${4:-} --window-size="$3" \
        --screenshot="art/$1.png" "http://127.0.0.1:$port$2" 2>/dev/null
    echo "art/$1.png"
}

shot screenshot-pages     /admin/wiki                         1440,900
shot screenshot-reader    /admin/wiki/deploy-guide            1440,900
shot screenshot-editor    /admin/wiki/deploy-guide/edit       1440,900
shot screenshot-diff      "/admin/wiki/deploy-guide/history?tableAction=changes&tableActionRecord=$revision" 1440,900
shot screenshot-dashboard /admin                              1440,900
shot screenshot-dark      /admin/wiki/deploy-guide            1440,900 --blink-settings=preferredColorScheme=0
shot screenshot-mobile    /admin/wiki/deploy-guide            390,844

# The banner frames the reader screenshot, so it goes last.
mkdir -p build/demo/public/banner
cp demo/banner.html demo/ruvelo-mark.svg art/screenshot-reader.png build/demo/public/banner/
shot banner /banner/banner.html 1280,640
