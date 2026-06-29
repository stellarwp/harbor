# Contributing to Harbor

## Quick start

**Day-to-day development** with [harbor-dev-tools](https://github.com/stellarwp/harbor-dev-tools) — PHP and UI. Install and activate **harbor-dev-tools** on your local WordPress site, then clone **this repository** as a sibling directory:

```
wp-content/plugins/
├── harbor-dev-tools/    ← active plugin (runs harbor:watch)
└── harbor/              ← this repo (your git checkout)
```

Setup, watch script, Branch Switcher, and fixtures: [harbor-dev-tools README](https://github.com/stellarwp/harbor-dev-tools/blob/main/README.md).

In **harbor-dev-tools** (once per session):

```bash
composer install          # first time only
composer harbor:watch     # leave running — syncs this repo into WordPress
```

Leave `harbor:watch` running. It picks up PHP changes in `src/` automatically and re-runs Strauss. You do not need `composer install` in this repo for that workflow.

**When you change frontend code** (`resources/`), also in **this repo**:

```bash
bun install               # first time only
npm run build             # first time, after pulling frontend changes, or one-off
npm run start             # leave running while editing — rebuilds on save
```

With `harbor:watch` and `npm run start` both running, each save recompiles assets here and the sync delivers the new `build/` / `build-dev/` output to WordPress. PHP-only work needs only `harbor:watch`.

Run `npm run` or `composer` in this repo to list available scripts (`package.json` / `composer.json`).

---

## Prerequisites

| Tool                  | Minimum version | Notes                                                                                                                                                                                                                                                                                                          |
| --------------------- | --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Bun](https://bun.sh) | **1.3.x**       | Versions before 1.3.x may not install platform-specific native binary packages (e.g. `lightningcss-linux-x64-gnu`) correctly — `npm run build` will fail with a `Cannot find module` error. Confirmed working on 1.3.14. Run `bun --version` and update if needed: `curl -fsSL https://bun.sh/install \| bash` |
| Node.js               | 14+             | Managed by `nvm` or included with Bun. `wp-scripts` requires Node 14+ for optional chaining syntax                                                                                                                                                                                                             |
| Composer              | 2.x             | For PHP dependency management                                                                                                                                                                                                                                                                                  |
| PHP                   | 7.4+            | Required for Composer scripts and static analysis                                                                                                                                                                                                                                                              |

---

## Documentation index

- [docs/harbor.md](docs/harbor.md) — architecture overview
- [docs/guides/integration.md](docs/guides/integration.md) — partner plugin integration
- [docs/guides/testing.md](docs/guides/testing.md) — PHP, E2E, and fixtures
- [docs/guides/partner-plugin-testing.md](docs/guides/partner-plugin-testing.md) — advanced: test local changes inside a real partner plugin (Composer path repo)
- [docs/subsystems/frontend.md](docs/subsystems/frontend.md) — React UI

---

## Release and PR expectations

Production asset bundles in `build/` are gitignored locally but committed by CI during release (`git add -f`). Do not commit local build output manually.

For version bumps and changelog prep, see `composer release:prep` in `composer.json`.
