# Contributing to Harbor

## Quick start

**Day-to-day UI work** with [harbor-dev-tools](#local-development-recommended):

```bash
bun install
npm run build    # required before testing UI changes in WordPress
```

You do not need `composer install` in this repo for that workflow. See [Local development](#local-development-recommended).

**Automated tests** (E2E via wp-env, or PHP via slic):

E2E — needs Node, PHP dev deps, and compiled React assets:

```bash
bun install          # Playwright, wp-env, and test runners
composer install     # full dev deps — the E2E fixture plugin loads require-dev classes
npm run build        # skip only if build/ already exists and you did not change frontend code
```

Then see [docs/guides/testing.md](docs/guides/testing.md#e2e-tests-playwright) for `wp-env start`, Playwright browsers, and `bun run test:e2e`.

PHP via slic — `composer install` in this repo (or `slic composer install` inside slic). Bun is not required for PHP-only tests.

Run `npm run` or `composer` in this repo to list available scripts (`package.json` / `composer.json`).

---

## Prerequisites

| Tool                  | Minimum version | Notes                                                                                                                                                                                                                                                                                                          |
| --------------------- | --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Bun](https://bun.sh) | **1.3.x**       | Versions before 1.3.x may not install platform-specific native binary packages (e.g. `lightningcss-linux-x64-gnu`) correctly — `npm run build` will fail with a `Cannot find module` error. Confirmed working on 1.3.14. Run `bun --version` and update if needed: `curl -fsSL https://bun.sh/install \| bash` |
| Node.js               | 14+             | Managed by `nvm` or included with Bun. `wp-scripts` requires Node 14+ for optional chaining syntax                                                                                                                                                                                                             |
| Composer              | 2.x             | For PHP dependency management                                                                                                                                                                                                                                                                                  |
| PHP                   | 7.4+            | Required for Composer scripts and static analysis                                                                                                                                                                                                                                                              |

> **PHP install note:** For automated tests and static analysis, use `composer install` (full dev deps). The dev tree includes `lucatume/tdd-helpers`, which may cause install failures locally — PHP tests are typically run via [`slic`](https://github.com/stellarwp/slic), which runs `slic composer install` in its own environment. See [docs/guides/testing.md](docs/guides/testing.md).

---

## Local development (recommended)

For day-to-day Harbor UI and PHP work, use [harbor-dev-tools](https://github.com/stellarwp/harbor-dev-tools) instead of wiring a Composer path repository into a partner plugin. Clone this repo beside it at `wp-content/plugins/harbor`.

Setup, watch script, Branch Switcher, Postman fixtures, and QA zip workflow: [harbor-dev-tools README](https://github.com/stellarwp/harbor-dev-tools/blob/main/README.md).

Run `composer install` and `composer harbor:watch` in **harbor-dev-tools**, not in this repo. With watch running, PHP and compiled asset changes from this checkout are picked up automatically.

For JavaScript work in this repo:

```bash
npm run build    # or npm run start during active UI work
```

Fixture keys and welcome-screen behavior: [docs/guides/testing.md](docs/guides/testing.md#local-development-with-fixtures).

On Lando, run Composer from harbor-dev-tools with the `lando` prefix (see [harbor-dev-tools README](https://github.com/stellarwp/harbor-dev-tools/blob/main/README.md)). Keep this clone at `wp-content/plugins/harbor` inside the site root Lando mounts — harbor-dev-tools defaults to `../harbor`.

---

## Testing in a partner plugin (advanced)

When you need to validate inside a real partner plugin (GiveWP, Kadence, etc.) — for example Strauss text-domain behavior or partner-specific integration — use a Composer path repository.

See [docs/guides/partner-plugin-testing.md](docs/guides/partner-plugin-testing.md) for setup, iteration, Docker/Lando mounts, and cleanup.

---

## Running automated tests

PHP tests (Codeception via slic), E2E tests (Playwright via wp-env), and fixture-based local development are covered in [docs/guides/testing.md](docs/guides/testing.md).

---

## Documentation index

- [docs/harbor.md](docs/harbor.md) — architecture overview
- [docs/guides/integration.md](docs/guides/integration.md) — partner plugin integration
- [docs/guides/partner-plugin-testing.md](docs/guides/partner-plugin-testing.md) — test local Harbor changes in a partner plugin
- [docs/guides/testing.md](docs/guides/testing.md) — PHP, E2E, and fixtures
- [docs/subsystems/frontend.md](docs/subsystems/frontend.md) — React UI

---

## Release and PR expectations

Production asset bundles in `build/` are gitignored locally but committed by CI during release (`git add -f`). Do not commit local build output manually.

For version bumps and changelog prep, see `composer release:prep` in `composer.json`.
