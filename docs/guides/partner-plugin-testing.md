# Testing local Harbor changes in a partner plugin

Harbor is never run in isolation — it is always vendored inside a partner plugin (GiveWP,
Kadence, LearnDash, etc.) via [Strauss](https://github.com/BrianHenryIE/strauss). For
day-to-day Harbor development, prefer [harbor-dev-tools](https://github.com/stellarwp/harbor-dev-tools)
(see [CONTRIBUTING.md](../../CONTRIBUTING.md) or the [harbor-dev-tools README](https://github.com/stellarwp/harbor-dev-tools/blob/main/README.md)). Use the path repository workflow below only
when you must test **local changes** inside a real partner plugin.

The full test pipeline:

```
Harbor source → composer update → Strauss prefix → WordPress admin page
```

To test local Harbor changes without publishing a new Composer release, use a
[Composer path repository](https://getcomposer.org/doc/05-repositories.md#path). This
points the partner plugin's Composer at your local Harbor checkout instead of Packagist.

## Setup (one-time per partner plugin; revert when done)

### 1. Relax the Harbor constraint in the partner plugin's `composer.json`

Harbor's `composer.json` has no `version` field. Without one, Composer resolves the
path repo as `dev-main` (or `dev-{branch}`), which does not satisfy a `^1.x` constraint.
Change only the partner plugin's requirement — no Harbor file changes needed:

```json
"stellarwp/harbor": "*@dev"
```

`*@dev` accepts any version including dev stability, regardless of the branch name.

### 2. Add a `path` repository to the partner plugin's `composer.json`

Add at the **beginning** of the `repositories` array so it takes priority over Packagist:

```json
{
    "type": "path",
    "url": "/absolute/path/composer/sees",
    "options": { "symlink": false }
},
```

The URL is the path Composer can reach from where it runs. See
[Regular setup](#regular-setup) for the common case (host PHP or Harbor inside the site
root). See [Docker / Lando (Harbor outside the site root)](#docker--lando-harbor-outside-the-site-root)
when the checkout lives outside the mounted site directory (path repo URL is typically
`/harbor` after a bind-mount).

> **Why `symlink: false`?** The Strauss pipeline runs `stellar-harbor`, a script that
> replaces `%TEXTDOMAIN%` in-place on the files at `vendor/stellarwp/harbor/`. With
> `symlink: true`, that write goes through the symlink back to your Harbor source,
> corrupting it. With `symlink: false`, Composer copies the files first, so only the
> copy is modified.

## Iteration cycle

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

## Environment-specific setup

> **Applies to all environments:** the constraint change from step 1
> (`"stellarwp/harbor": "*@dev"`) is always required regardless of environment.
> What varies is the path repository URL and whether you need an extra bind-mount.

### Regular setup

Use this when Composer can already reach the Harbor checkout — no extra bind-mount or
container mapping. That includes native PHP on the host (Herd, Valet, MAMP, WAMP) and
Lando/Docker when Harbor lives **inside** the mounted site root (e.g.
`wp-content/plugins/harbor`).

Point the path repository at the **absolute** path Composer sees from where you run it.

**Native PHP** (host filesystem — Herd, Valet, MAMP, WAMP):

```json
{
    "type": "path",
    "url": "/path/to/wp-content/plugins/harbor",
    "options": { "symlink": false }
}
```

**Lando / Docker** (Harbor under the mounted site root — container path):

```json
{
    "type": "path",
    "url": "/app/wp-content/plugins/harbor",
    "options": { "symlink": false }
}
```

**wp-env** (Harbor under the project tree — container path):

```json
{
    "type": "path",
    "url": "/var/www/html/wp-content/plugins/harbor",
    "options": { "symlink": false }
}
```

`/app` is the usual Lando WordPress docroot; confirm with `lando ssh -c pwd` or
`lando ssh -c "ls wp-content/plugins/harbor"` if your recipe differs. wp-env uses
`/var/www/html` instead. If Harbor is already cloned at `wp-content/plugins/harbor` in
the project, wp-env needs no extra mapping — only the path repo URL above.

```bash
# Native PHP — from the partner plugin directory
composer update stellarwp/harbor && composer install

# Lando / Docker — Harbor inside the mounted site root (from the site root)
lando composer --working-dir=wp-content/plugins/give update stellarwp/harbor
lando composer --working-dir=wp-content/plugins/give install

# wp-env — same layout (from the project root that contains .wp-env.json)
npx wp-env run cli --env-cwd=wp-content/plugins/give composer update stellarwp/harbor
npx wp-env run cli --env-cwd=wp-content/plugins/give composer install
```

### Docker / Lando (Harbor outside the site root)

> **Harbor outside the site root.** This section is for when your checkout lives **outside**
> the site directory (e.g. `~/Dev/libs/harbor` on the host) and you run Composer inside a
> container (Lando, Docker Compose, or wp-env). It does not apply to the
> [harbor-dev-tools](https://github.com/stellarwp/harbor-dev-tools) workflow (see
> [CONTRIBUTING.md](../../CONTRIBUTING.md)).
>
> **Use [Regular setup](#regular-setup) instead** when Harbor is already inside the mounted
> site root (e.g. `wp-content/plugins/harbor`).
>
> When Harbor is external, expose it to the container (below). Composer resolves path repos
> from the container filesystem — without a mount or mapping, `composer update stellarwp/harbor`
> cannot see the checkout.

**Step 1 — expose Harbor to the container.**

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

With [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/),
add a mapping in `.wp-env.json`:

```json
{
  "mappings": {
    "/harbor": "/path/to/harbor"
  }
}
```

Then `npx wp-env start` (or restart if already running).

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

# wp-env
npx wp-env run cli git config --global --add safe.directory /harbor
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

# wp-env (from the project root that contains .wp-env.json)
npx wp-env run cli --env-cwd=wp-content/plugins/give composer update stellarwp/harbor
npx wp-env run cli --env-cwd=wp-content/plugins/give composer install
```

## Cleanup

```bash
# 1. Revert the partner plugin's composer.json:
#    - Restore "stellarwp/harbor": "^1.x"
#    - Remove the path repository entry

# 2. Restore vendor/ — re-resolves Harbor from Packagist and re-runs Strauss
composer update stellarwp/harbor
composer install

# 3. Remove the /harbor volume from .lando.yml or docker-compose.yml, or the `/harbor`
#    mapping from .wp-env.json, then rebuild or restart the environment
```
