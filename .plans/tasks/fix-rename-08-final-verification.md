---
status: draft
---

# Stage 8: Final verification and build

## Problem

All the rename work is done across Stages 1-7, but we need to confirm that no references were missed, all verification tools pass, and the built frontend assets are committed. The `build/` and `build-dev/` directories contain compiled output that is tracked in git and needs to reflect the renamed CSS classes and JS identifiers.

## Proposed solution

Run every verification tool, do a comprehensive grep for any remaining "uplink" references, build the production frontend assets, and commit them.

### Comprehensive grep

```bash
grep -ri 'uplink' \
  --include='*.php' --include='*.ts' --include='*.tsx' --include='*.js' \
  --include='*.json' --include='*.css' --include='*.md' --include='*.yml' \
  --include='*.neon' --include='*.xml' \
  . | grep -v node_modules | grep -v vendor | grep -v '.git/' | grep -v '.plans/'
```

Expected: zero results. Acceptable exceptions: `bun.lock` (regenerate with `bun install`), and any method names that genuinely contain "uplink" as part of an external API we do not control.

### Full verification suite

```bash
composer dump-autoload
composer test:analysis
bun run typecheck
bun run build
```

All must pass.

### Build and commit assets

`bun run build` regenerates `build/` (production) and `build-dev/` (development). The diff will be large since all CSS class names and JS identifiers changed. Commit the built assets as a separate commit.

### Spelling check (optional)

`bun run check:spelling` may flag new words that need to be added to `.cspell.json` (e.g., `liquidweb`, `harbor`).

### Final review

```bash
git diff --stat main
git log --oneline main..HEAD
```

Verify the commit count and file counts look reasonable and no unexpected files changed.

## Testing

The full suite should pass:

```bash
composer dump-autoload
composer test:analysis
bun run typecheck
bun run build
```

If the project has integration tests that can run locally via slic:

```bash
slic run wpunit
```

These test the full bootstrap path (`Harbor::init()`, REST route registration, feature resolution, license management) and will catch any broken string references that static analysis misses.
