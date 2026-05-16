#!/usr/bin/env bash
set -euo pipefail

export DISPLAY="${DISPLAY:-:99}"
display_number="${DISPLAY#:}"
novnc_web_dir=/tmp/novnc-web
vnc_resolution="${VNC_RESOLUTION:-1440x900x24}"
window_size="${vnc_resolution%x*}"
window_size="${window_size/x/,}"

rm -f "/tmp/.X${display_number}-lock"
rm -rf "$novnc_web_dir"
cp -a /usr/share/novnc "$novnc_web_dir"
cat > "$novnc_web_dir/index.html" <<'HTML'
<!doctype html>
<meta http-equiv="refresh" content="0; url=/vnc.html">
HTML

Xvfb "$DISPLAY" -screen 0 "$vnc_resolution" &
for _ in {1..50}; do
    if [ -S "/tmp/.X11-unix/X${display_number}" ]; then
        break
    fi

    sleep 0.1
done

fluxbox >/tmp/fluxbox.log 2>&1 &
x11vnc -display "$DISPLAY" -forever -shared -nopw -listen 0.0.0.0 -rfbport 5900 >/tmp/x11vnc.log 2>&1 &
for _ in {1..50}; do
    if timeout 1 bash -c '</dev/tcp/127.0.0.1/5900' 2>/dev/null; then
        break
    fi

    sleep 0.1
done

websockify --web="$novnc_web_dir" 0.0.0.0:6080 localhost:5900 >/tmp/novnc.log 2>&1 &

profile_path="${OZON_PROFILE_PATH:-/var/www/ozon-prices/storage/app/ozon-browser-profile}"
chrome_bin="${CHROME_PATH:-/usr/bin/chromium}"
target_url="${OZON_LOGIN_URL:-https://www.ozon.ru/my/favorites}"

mkdir -p "$profile_path"
rm -f "$profile_path/SingletonLock" "$profile_path/SingletonSocket" "$profile_path/SingletonCookie"

chrome_args=(
    --no-sandbox
    --disable-dev-shm-usage
    --user-data-dir="$profile_path"
    --lang=ru-RU
    --window-size="$window_size"
)

if [ -n "${OZON_PROXY_SERVER:-}" ]; then
    chrome_args+=(--proxy-server="$OZON_PROXY_SERVER")
fi

"$chrome_bin" "${chrome_args[@]}" "$target_url" >/tmp/chromium.log 2>&1 &
wait "$!"
