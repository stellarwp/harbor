#!/bin/bash
#
# Runs the Playwright E2E suite locally against a wp-env instance.
# Mirrors .github/workflows/tests-e2e.yml so local failures match CI.
#
# Steps: ensure composer + node deps installed, start wp-env if down,
# parse the wp-env URL, install playwright browsers if missing, run tests.
#
# Parameters
#   $@ - forwarded to `playwright test` (e.g. --headed, a specific spec, --grep)
#
# Examples
#   dev_scripts/run-e2e.sh
#   dev_scripts/run-e2e.sh tests/e2e/software-manager.spec.ts
#   dev_scripts/run-e2e.sh --project=headed
#
# Env overrides
#   WP_BASE_URL  Skip wp-env detection and target an explicit URL.
#   KEEP_WP_ENV  If set, do not stop wp-env after the run (default: leave it running).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# -----------------------------------------------------------------------------
# Composer deps (vendor/autoload.php is required by the harbor-fixture plugin;
# without it the fixture silently bails and the admin page returns 403).
# -----------------------------------------------------------------------------
if [ ! -f "vendor/autoload.php" ]; then
	echo "==> Installing composer dependencies"
	composer install --no-interaction --prefer-dist
fi

# -----------------------------------------------------------------------------
# Node deps
# -----------------------------------------------------------------------------
if [ ! -d "node_modules" ]; then
	echo "==> Installing JS dependencies"
	if command -v bun >/dev/null 2>&1; then
		bun install
	else
		npm install
	fi
fi

WP_ENV_BIN="node_modules/.bin/wp-env"
PLAYWRIGHT_BIN="node_modules/.bin/playwright"

if command -v bun >/dev/null 2>&1; then
	RUNNER="bun"
else
	RUNNER="npm"
fi

# -----------------------------------------------------------------------------
# wp-env: reuse if already up, otherwise start. Parse the URL the same way
# the GitHub Action does so we don't hardcode a port.
# -----------------------------------------------------------------------------
if [ -z "${WP_BASE_URL:-}" ]; then
	echo "==> Starting wp-env (reuses existing instance if running)"
	WP_ENV_OUTPUT="$("$WP_ENV_BIN" start 2>&1)"
	echo "$WP_ENV_OUTPUT"
	WP_BASE_URL="$(echo "$WP_ENV_OUTPUT" | grep -oE 'http://localhost:[0-9]+' | head -1)"
	WP_BASE_URL="${WP_BASE_URL:-http://localhost:8901}"
fi

export WP_BASE_URL
echo "==> WP_BASE_URL=$WP_BASE_URL"

# -----------------------------------------------------------------------------
# Playwright browser binary. `install chromium` is idempotent and finishes in
# under a second when already present, so just run it unconditionally.
# -----------------------------------------------------------------------------
"$PLAYWRIGHT_BIN" install chromium

# -----------------------------------------------------------------------------
# Clean stale artifacts so a previous run's storage state can't leak in.
# -----------------------------------------------------------------------------
rm -rf artifacts/ test-results/

# -----------------------------------------------------------------------------
# Run tests via the package.json script so CI and local invocations match.
# `bun run test:e2e -- <extra-args>` forwards args after the existing
# --project=chromium flag baked into the script.
# -----------------------------------------------------------------------------
"$RUNNER" run test:e2e -- "$@"
