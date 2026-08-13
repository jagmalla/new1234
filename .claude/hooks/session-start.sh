#!/bin/bash
# ---------------------------------------------------------------------------
# SessionStart hook — makes this repo testable the moment a session opens.
#
# This project has no composer/npm manifest: PHP 8.4, a PSR-4 autoloader and a
# webroot. What every session actually needs is a running site to test against,
# because the self-check harness (tests/lalkitab_process_check.php) fetches real
# pages over HTTP and asserts against the rendered HTML.
#
# Two things were rebuilt by hand at the start of every single session:
#   1. _devrouter.php — emulates .htaccess (clean paths -> ?r=) AND serves the
#      static files under public_html/. It is deliberately NEVER committed, so
#      it has to be regenerated each time. It is also in .gitignore now, so it
#      can no longer be committed by accident.
#   2. php -S on 127.0.0.1:8899 with APP_ENV=local (AdminGuard returns 403
#      "Staff authentication required" without it) and PHP_CLI_SERVER_WORKERS>1
#      (the self-check calls the site from inside itself, so a single worker
#      deadlocks).
#
# Runs in Claude Code on the web only; it never touches a local machine.
# Safe to run repeatedly: an already-running server is reused, not duplicated.
# ---------------------------------------------------------------------------
set -uo pipefail

# Local/desktop sessions: do nothing at all.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

ROOT="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
PORT=8899
BASE="http://127.0.0.1:${PORT}"
cd "$ROOT" || exit 0

# --- 1. Regenerate the dev router (never committed) -------------------------
cat > "$ROOT/_devrouter.php" << 'PHPROUTER'
<?php
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . '/public_html' . $path;
if ($path !== '/' && is_file($file)) {
    if (substr($file, -4) === '.php') { chdir(__DIR__ . '/public_html'); require $file; return true; }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ([
        'js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml', 'gz' => 'application/gzip', 'woff2' => 'font/woff2',
    ][$ext] ?? 'application/octet-stream'));
    readfile($file); return true;
}
$r = ltrim($path, '/');
if ($r !== '' && !isset($_GET['r'])) {
    $_GET['r'] = $r; $_REQUEST['r'] = $r;
    $q = $_SERVER['QUERY_STRING'] ?? '';
    $_SERVER['QUERY_STRING'] = 'r=' . rawurlencode($r) . ($q !== '' ? '&' . $q : '');
}
chdir(__DIR__ . '/public_html');
require __DIR__ . '/public_html/index.php';
return true;
PHPROUTER

# --- 2. Start the dev server (reuse one that is already up) -----------------
if curl -sf -o /dev/null --max-time 3 "${BASE}/calc?name=ping"; then
  echo "session-start: dev server already answering on ${BASE}"
else
  APP_ENV=local PHP_CLI_SERVER_WORKERS=8 \
    nohup php -S "127.0.0.1:${PORT}" _devrouter.php > /tmp/ab-devserver.log 2>&1 &
  for i in $(seq 1 25); do
    sleep 0.4
    curl -sf -o /dev/null --max-time 3 "${BASE}/calc?name=ping" && break
  done
  if curl -sf -o /dev/null --max-time 3 "${BASE}/calc?name=ping"; then
    echo "session-start: dev server started on ${BASE}"
  else
    echo "session-start: WARNING dev server did not answer; see /tmp/ab-devserver.log"
  fi
fi

# --- 3. Hand the session the settings the tools expect ----------------------
if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
  {
    # tests/lalkitab_process_check.php reads LK_BASE to know which site to check
    echo "export LK_BASE=${BASE}"
    # so browser scripts can `require('playwright')` from anywhere
    echo "export NODE_PATH=/opt/node22/lib/node_modules"
    echo "export PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers"
  } >> "$CLAUDE_ENV_FILE"
fi

exit 0
