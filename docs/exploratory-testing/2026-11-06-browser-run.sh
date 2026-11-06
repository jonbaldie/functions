#!/usr/bin/env bash
set -eu
cd "$(dirname "$0")/.."
export HOME="$PWD/.scratch/home" TMPDIR="$PWD/.scratch/tmp" XDG_CACHE_HOME="$PWD/.scratch/cache/xdg" XDG_CONFIG_HOME="$PWD/.scratch/home/.config"
export PLAYWRIGHT_BROWSERS_PATH="$PWD/.scratch/browsers" PLAYWRIGHT_HOST_PLATFORM_OVERRIDE=ubuntu24.04-x64
export LD_LIBRARY_PATH="$PWD/.scratch/libs/usr/lib/x86_64-linux-gnu"
pid=''
cleanup() {
  if [ -n "$pid" ]; then
    kill -TERM -- "-$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
  fi
}
trap cleanup EXIT INT TERM
if ss -ltn 'sport = :39874' | grep -q LISTEN; then echo 'port 39874 already occupied'; exit 1; fi
setsid node .scratch/browser-journey.js "$1" >".scratch/browser-$2.log" 2>&1 &
pid=$!
echo "registered process group=$pid"
status=0
wait "$pid" || status=$?
kill -TERM -- "-$pid" 2>/dev/null || true
pid=''
cat .scratch/browser-"$2".log
if ss -ltn 'sport = :39874' | grep -q LISTEN; then echo 'port 39874 still listening'; exit 1; fi
echo 'port 39874 clear'
exit "$status"
