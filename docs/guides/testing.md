# Automated tests

This repository has two test suites:

- **PHP tests** — Codeception unit/integration tests run via [`slic`](https://github.com/stellarwp/slic)
- **E2E tests** — Playwright browser tests run against a Docker WordPress environment via [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)

---

## E2E tests (Playwright)

E2E tests live in `tests/e2e/` and exercise the Software Manager admin page end-to-end through a real browser against a real WordPress installation.

### How it works

A fixture WordPress plugin (`tests/_data/plugins/harbor-fixture/`) boots Harbor with local JSON fixture data instead of live API calls. Playwright logs in once via `global-setup.ts`, saves the session to `artifacts/storage-states/admin.json`, and reuses it across tests.

### Prerequisites

- Docker (for wp-env)
- [Bun](https://bun.sh)
- Composer

### Running locally

```bash
# Install dependencies (first time only)
bun install
composer install

# Start the WordPress environment (port 8901 by default)
bunx wp-env start

# Install Playwright browsers (first time only)
bunx playwright install chromium --with-deps

# Run all E2E tests (headless)
bun run test:e2e

# Stop the environment when done
bunx wp-env stop
```

### Watching tests run in a browser

```bash
# Opens a live Chrome window; each action is slowed to 800 ms
bun run test:e2e:headed
```

### Interactive UI mode (time-travel viewer)

```bash
bun run test:e2e:ui
```

UI mode lets you step through each action and view a screenshot at that point in time. The browser preview is blank between runs because `@wordpress/e2e-test-utils-playwright` closes the page after each test — click an action step in the timeline to see its screenshot.

### Port configuration

wp-env starts on port **8901** by default (configured in `.wp-env.json`). If that port is taken, `autoPort: true` picks the next available one. Override both wp-env and Playwright together with a single env var:

```bash
WP_ENV_PORT=9000 bunx wp-env start
WP_ENV_PORT=9000 bun run test:e2e
```

### CI

The GitHub Actions workflow (`.github/workflows/tests-e2e.yml`) captures the URL that wp-env prints on startup and passes it as `WP_BASE_URL` to the Playwright run, so the dynamic port is handled automatically.

---

## PHP tests (Codeception + slic)

## Pre-requisites

- Docker
- A system-level PHP installation with MySQL libraries
- [`slic`](https://github.com/stellarwp/slic) set up and usable on your system (follow setup instructions in that repo)

## Running tests

### First time run

To run tests for the first time, there are a couple of things you need to do:

1. Run `slic here` in the parent directory from where this library is cloned. (e.g. If you ran `git clone` in your `wp-content/plugins` directory, run `slic here` from `wp-content/plugins`)
2. Run `slic use harbor` to tell `slic` to point to the Harbor library.
3. Run `slic composer install` to bring in all the dependencies.

### Running the tests

You can simply run `slic run` or `slic run SUITE_YOU_WANT_TO_RUN` to quickly run automated tests for this library. If you want to use xdebug with your tests, you'll need to open a `slic ssh` session and turn xdebugging on (there's help text to show you how).

The suites are `wpunit` (single-site) and `muwpunit` (multisite). Run a single test class with `--filter`, e.g. `slic run wpunit --filter DataTest`.

### Dependencies and PHP version — always resolve through slic

`composer.lock` is **gitignored** on purpose. CI (`.github/workflows/tests-php.yml`) tests a matrix of PHP versions (7.4 → 8.3) by setting the container PHP and *then* resolving dependencies inside that container:

```yaml
- ${SLIC_BIN} php-version set ${{ matrix.php }}
- ${SLIC_BIN} composer install
```

Each leg gets a dependency set valid for its PHP (for example, Codeception v4-era on 7.4, v5 on 8.x). Because of this, **do not commit `composer.lock` and do not add a `config.platform.php` pin** — either one forces a single resolution target and breaks the per-version matrix.

The practical rule for local development: **resolve dependencies through `slic`, never with host `composer`.** `slic composer install` resolves inside the container at its configured PHP version, matching what the tests actually run on. If you run host `composer install`/`update` on a newer PHP than the slic container (e.g. host PHP 8.4 vs container 8.2), Composer can pull packages that require the newer PHP (this has happened with `symfony/console`), and the suite then fails to even parse inside the container.

```bash
# Check / set the container PHP version (defaults to 8.2)
slic php-version
slic php-version set 8.2

# Resolve dependencies inside the container, then run
slic composer install
slic run
```

If you hit a parse error or a "lock file does not contain a compatible set of packages" message, it almost always means `vendor/` was resolved against a different PHP than the container. Fix it with `slic composer update` (re-resolves for the container's PHP), not host `composer`.

## Debug logging

Harbor uses the `With_Debugging` trait (`src/Harbor/Traits/With_Debugging.php`) for all debug output. When `WP_DEBUG` is enabled, log messages are written via `error_log()` with an `Harbor:` prefix so they're easy to filter.

The trait provides three methods:

| Method                  | Use for                                      |
| ----------------------- | -------------------------------------------- |
| `debug_log()`           | Plain string messages                        |
| `debug_log_throwable()` | Exceptions — logs message, file, line, trace |
| `debug_log_wp_error()`  | `WP_Error` objects — logs code and message   |

To see Harbor debug output during test runs, make sure `WP_DEBUG` is `true` in the test environment (slic sets this by default). Grep for `Harbor:` in the PHP error log to isolate Harbor messages from other output.

## Local development with fixtures

For day-to-day Harbor development, use [harbor-dev-tools](https://github.com/stellarwp/harbor-dev-tools) with a clone of this repo. See [CONTRIBUTING.md](../../CONTRIBUTING.md) for setup.

**Layout:**

```
wp-content/plugins/
├── harbor-dev-tools/    ← active plugin (fixture mode, harbor:watch)
└── harbor/              ← this repository
```

Harbor Dev Tools replaces the real API clients with fixture clients that read local JSON files. Open **Harbor Dev Tools** in wp-admin to configure:

- **Fixture Mode** — toggle between fixture files and the real API.
- **Fixture Key** — select which fixture set to use.
- **API Base URL** — override the real API endpoint (ignored when fixture mode is on).

Use fixture key **`lwsw-unified-basic-2026`** when exercising the Software Manager UI (welcome gate, product list, license sidebar). Keys like **`lwsw-unified-test-fixtures`** are aimed at API/Postman scenarios with a curated catalog subset — see the [harbor-dev-tools README](https://github.com/stellarwp/harbor-dev-tools/blob/main/README.md).

Admin URLs while developing:

| Check                        | URL                                                                                 |
| ---------------------------- | ----------------------------------------------------------------------------------- |
| Software Manager (canonical) | `?page=nexcess-software-manager`                                                    |
| Legacy slug (bookmarks)      | `?page=lw-software-manager`                                                         |
| Welcome gate (no license)    | Clear the unified license option; disable fixture auto-key or turn off fixture mode |

For how catalog and licensing data join to produce features, see [Data Sources in features.md](../subsystems/features.md#data-sources).

### What happens when you switch the fixture key

Each fixture key maps to JSON files in two directories — one for catalog, one for licensing:

1. The **licensing** client loads `licensing/{key}.json`. Each file represents a different license scenario (basic tier, pro tier, expired, etc.).
2. The **catalog** client looks for `catalog/{key}.json`. If no key-specific file exists, it falls back to `catalog/default.json` — the full product catalog.

Most fixture keys only have a licensing file. They share the same `default.json` catalog because the catalog doesn't change per customer — only licensing does. This means you'll see features from **all** products in the output. The `is_available` column shows which ones the fixture key actually entitles.

For example, with the full `default.json` catalog:

- **`lwsw-unified-give-basic-2026`** — licensing says "GiveWP at basic tier." Features from all products appear, but only basic-tier GiveWP features have `is_available: true`. Kadence features have `is_available: false` (no license entry).
- **`lwsw-unified-pro-2026`** — licensing says "pro tier across multiple products." More features become available.

Some fixture keys (like `lwsw-unified-test-fixtures`) ship a dedicated catalog file with a curated subset of products. When that file exists, it replaces the full catalog entirely — so fewer features appear in the output.

### File resolution order

harbor-dev-tools resolves fixture files from:

1. `fixtures/` inside harbor-dev-tools — custom or one-off files
2. `vendor-prefixed/stellarwp/harbor/tests/_data/` — shared Harbor test fixtures synced from this repo

The first match wins. For the catalog, if no key-specific file is found in either location, it falls back to `default.json` in the same order.

### Adding custom fixtures

Drop JSON files into harbor-dev-tools `fixtures/catalog/` and `fixtures/licensing/`. The filename (without `.json`) becomes a selectable key in the plugin settings. See the existing fixture files for the expected format.
