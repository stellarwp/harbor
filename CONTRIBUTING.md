# Contributing to Harbor

## Quick Start

```bash
# JavaScript dependencies
bun install

# PHP dependencies — --no-dev is sufficient for building assets and partner plugin testing.
# For running PHP tests or static analysis, see the PHP section below.
composer install --no-dev

# Build React assets (required before testing in a partner plugin)
npm run build
```

All available scripts are listed in the [Scripts reference](#scripts-reference) below.

---

## Prerequisites

| Tool | Minimum version | Notes |
|---|---|---|
| [Bun](https://bun.sh) | **1.3.x** | Versions before 1.3.x may not install platform-specific native binary packages (e.g. `lightningcss-linux-x64-gnu`) correctly — `npm run build` will fail with a `Cannot find module` error. Confirmed working on 1.3.14. Run `bun --version` and update if needed: `curl -fsSL https://bun.sh/install \| bash` |
| Node.js | 14+ | Managed by `nvm` or included with Bun. `wp-scripts` requires Node 14+ for optional chaining syntax |
| Composer | 2.x | For PHP dependency management |
| PHP | 7.4+ | Required for Composer scripts and static analysis |

---

## Installing Dependencies

### JavaScript

```bash
bun install
```

> **Bun version requirement:** Some Bun versions before 1.3.x silently skip optional packages
> that declare `os`/`cpu`/`libc` fields (e.g. `lightningcss-linux-x64-gnu`). The build will
> then fail when `postcss-loader` tries to load `lightningcss`. Confirmed working on 1.3.14.
> Update Bun before running `bun install`: `curl -fsSL https://bun.sh/install | bash`.

### PHP

Use `--no-dev` when your goal is **building assets or testing Harbor changes in a partner
plugin**. The dev dependency tree includes tools only needed for running the PHP test suite
itself (Codeception, PHPStan, phpcs) — none of which are required for the local partner
plugin workflow.

```bash
# Building assets or testing via a partner plugin — use this
composer install --no-dev
```

Use the full install when you need to **run PHP tests or static analysis** locally:

```bash
# PHP tests (Codeception/slic) or static analysis — use this
composer install
```

> **Known issue with full `composer install`:** The `lucatume/tdd-helpers` repository (a
> transitive dev dependency of `lucatume/wp-browser`) no longer exists on GitHub, causing
> the install to fail. PHP tests are typically run via [`slic`](https://github.com/stellarwp/slic),
> which manages its own container environment and is not affected by this issue. See
> [docs/guides/testing.md](docs/guides/testing.md) for the recommended test workflow.

---

## Scripts Reference

### JavaScript (`npm run <script>` or `bun run <script>`)

> Both runners are equivalent here — `npm run build` calls `bun` internally.

| Script | What it does |
|---|---|
| `build` | Builds both dev and prod asset bundles (runs `build:dev` then `build:prod`) |
| `build:dev` | Webpack build with source maps, not minified |
| `build:prod` | Webpack build, minified, production-ready |
| `start` | Webpack watch mode with source maps (for active frontend development) |
| `typecheck` | TypeScript type-check without emitting files |
| `lint:js` | ESLint via `wp-scripts` |
| `lint:css` | Stylelint via `wp-scripts` |
| `format` | Auto-format JS/TS/CSS via Prettier |
| `check:spelling` | cspell spell-check across the whole project |
| `lint:md` | Markdownlint check |
| `fix:md` | Prettier + markdownlint auto-fix on all Markdown files |
| `test:js` | Jest unit tests (via `wp-scripts test-unit-js`) |
| `test:shell` | Bats shell script tests |
| `test:e2e` | Playwright E2E tests (headless Chromium) |
| `test:e2e:ui` | Playwright interactive UI mode (time-travel viewer) |
| `test:e2e:headed` | Playwright with a visible browser window |

> The `build/` and `build-dev/` directories are in `.gitignore` but are committed by CI via
> `git add -f` as part of the release process. Do not commit local build output manually.

### PHP (`composer <script>`)

| Script | What it does |
|---|---|
| `phpcs` | PHP CodeSniffer check |
| `phpcs:fix` | PHP Code Beautifier auto-fix |
| `phpcs:ci` | Partial phpcs check on changed lines only (used in CI) |
| `test:analysis` | PHPStan static analysis |
| `test:analysis:baseline` | Regenerate PHPStan baseline |
| `compatibility` | PHPCompatibilityWP check for all supported PHP versions (7.4–8.4) |
| `compatibility:php-X.X` | PHPCompatibilityWP check for a specific PHP version |
| `release:prep` | Bump version, replace TBD tags, compile changelog. Usage: `composer release:prep -- <version> [date]` |
| `release:set-version` | Replace TBD tags and update `Harbor::VERSION`. Usage: `composer release:set-version -- <version>` |

---

## Local Testing with a Partner Plugin

Harbor is never run in isolation — it is always vendored inside a partner plugin (GiveWP,
Kadence, LearnDash, etc.) via [Strauss](https://github.com/BrianHenryIE/strauss). The
full test pipeline is:

```
Harbor source → composer update → Strauss prefix → WordPress admin page
```

To test local Harbor changes without publishing a new Composer release, use a
[Composer path repository](https://getcomposer.org/doc/05-repositories.md#path). This
points the partner plugin's Composer at your local Harbor checkout instead of Packagist.

---

### Setup (one-time per partner plugin; revert when done)

#### 1. Relax the Harbor constraint in the partner plugin's `composer.json`

Harbor's `composer.json` has no `version` field. Without one, Composer resolves the
path repo as `dev-main` (or `dev-{branch}`), which does not satisfy a `^1.x` constraint.
Change only the partner plugin's requirement — no Harbor file changes needed:

```json
"stellarwp/harbor": "*@dev"
```

`*@dev` accepts any version including dev stability, regardless of the branch name.

#### 2. Add a `path` repository to the partner plugin's `composer.json`

Add at the **beginning** of the `repositories` array so it takes priority over Packagist:

```json
{
    "type": "path",
    "url": "/harbor",
    "options": { "symlink": false }
},
```

The URL is the path Composer can reach from where it runs. `/harbor` is the path used
in the Docker/Lando examples below (a bind-mount inside the container). For a native
PHP environment running on the host, use the local filesystem path instead
(e.g. `/home/username/Dev/libs/harbor`). See the environment-specific notes below.

> **Why `symlink: false`?** The Strauss pipeline runs `stellar-harbor`, a script that
> replaces `%TEXTDOMAIN%` in-place on the files at `vendor/stellarwp/harbor/`. With
> `symlink: true`, that write goes through the symlink back to your Harbor source,
> corrupting it. With `symlink: false`, Composer copies the files first, so only the
> copy is modified.

---

### Iteration cycle

```bash
# First time only — resolves the path repo and writes the lock file entry.
composer update stellarwp/harbor

# Every iteration — reinstalls all vendor packages and re-runs Strauss via post-install-cmd,
# regenerating the Strauss-prefixed vendor/vendor-prefixed/stellarwp/harbor/ directory.
composer install
```

> **Why `composer install` and not `composer strauss` directly?** The Strauss script in
> most partner plugins calls scripts from other vendored packages (e.g.
> `vendor/stellarwp/validation/bin/set-domain`) before running Strauss itself. Strauss's
> own `delete_vendor_packages: true` option removes those packages from `vendor/` after
> each run, so a bare `composer strauss` always fails on the second call. `composer install`
> restores all deleted packages first, then runs Strauss automatically via `post-install-cmd`.

Reload the page in your browser — no server restart needed after a PHP-only change.

**For JS changes**, build Harbor first, then re-run `composer install` from the partner
plugin's site:

```bash
# From the Harbor directory
npm run build

# From the partner plugin's site (where you edited composer.json)
composer install  # re-runs Strauss, picks up the new build/ directory
```

---

### Environment-specific setup

> **Applies to all environments:** the constraint change from step 1
> (`"stellarwp/harbor": "*@dev"`) is always required regardless of environment.
> What varies between environments is only the path repository URL and how `composer` is invoked.

#### Native PHP (Herd, Valet, MAMP, WAMP)

Composer runs directly on your host. Use the local filesystem path to Harbor as the
repository URL:

```json
{
    "type": "path",
    "url": "/path/to/harbor",
    "options": { "symlink": false }
}
```

Then run `composer update stellarwp/harbor && composer install` from the partner plugin
directory. No extra steps needed.

#### Docker / Lando

The Composer command runs inside a container that only mounts the partner plugin's site
directory. The Harbor checkout is not visible inside the container by default.

**Step 1 — bind-mount Harbor into the container.**

With Lando, add a volume to `.lando.yml`:

```yaml
services:
  appserver:
    overrides:
      volumes:
        - /path/to/harbor:/harbor
```

Then `lando rebuild -y`.

With plain Docker Compose, add to the `volumes` section of the appserver service:

```yaml
volumes:
  - /path/to/harbor:/harbor
```

Then `docker compose up -d --force-recreate`.

**Step 2 — use `/harbor` as the repository URL** (the container-side mount path):

```json
{
    "type": "path",
    "url": "/harbor",
    "options": { "symlink": false }
}
```

**Step 3 — fix git ownership inside the container** (Composer reads git metadata from
path repos; containers often run as root while the mounted volume has a different owner):

```bash
# Lando
lando ssh -c "git config --global --add safe.directory /harbor"

# Plain Docker
docker exec <container> git config --global --add safe.directory /harbor
```

> This setting is lost when the container is recreated. Re-run it after each rebuild.

**Step 4 — run the iteration cycle inside the container:**

```bash
# Lando (from the site root)
lando composer --working-dir=wp-content/plugins/give update stellarwp/harbor
lando composer --working-dir=wp-content/plugins/give install

# Plain Docker
docker exec <container> composer --working-dir=/app/wp-content/plugins/give update stellarwp/harbor
docker exec <container> composer --working-dir=/app/wp-content/plugins/give install
```

#### wp-env

Add Harbor as a custom mapping in `.wp-env.json`:

```json
{
  "mappings": {
    "/harbor": "/path/to/harbor"
  }
}
```

Then `npx wp-env start`. Use `/harbor` as the repository URL and run Composer inside
the container the same way as the plain Docker approach above.

---

### Cleanup

```bash
# 1. Revert the partner plugin's composer.json:
#    - Restore "stellarwp/harbor": "^1.x"
#    - Remove the path repository entry

# 2. Restore vendor/ — re-resolves Harbor from Packagist and re-runs Strauss
composer update stellarwp/harbor
composer install

# 3. Remove the /harbor volume from .lando.yml or docker-compose.yml, then rebuild
```

---

## Automated Tests

See [docs/guides/testing.md](docs/guides/testing.md) for PHP (Codeception/slic) and
E2E (Playwright/wp-env) test instructions.
