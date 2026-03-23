---
status: draft
---

# Stage 4: Rename JS/TS and CSS

## Problem

The PHP side is fully renamed, but the JavaScript/TypeScript source and CSS still use the old names: `UplinkError` class, `UplinkData` interface, `stellarwp/uplink` store name, `uplinkData` window variable, `uplink-root` DOM id, `.uplink-ui` CSS scope class, and `@package StellarWP\Uplink` docblock tags. The frontend build will fail until these are updated because PHP now emits different DOM ids, CSS classes, and REST paths (changed in Stage 3).

## Proposed solution

Rename the two JS files that need it, then bulk-update all JS/TS content and CSS scope classes. After this stage, `bun run typecheck` and `bun run build` both pass.

### JS file renames

```bash
git mv resources/js/errors/uplink-error.ts resources/js/errors/liquid-error.ts
git mv resources/js/types/uplink-data.ts resources/js/types/liquid-data.ts
```

These renames happen here (not in Stage 2) so that import path updates and content changes can all land in the same commit. There is no intermediate state where TypeScript cannot resolve an import.

### Import paths

Every file that imports the renamed modules needs updating:

- `'./uplink-error'` becomes `'./liquid-error'`
- `'./uplink-data'` becomes `'./liquid-data'`
- `'@/errors/uplink-error'` becomes `'@/errors/liquid-error'`

Check barrel files (`errors/index.ts`) for re-exports.

### Class and type renames

`UplinkError` becomes `LiquidError` everywhere: the class declaration, `this.name = 'LiquidError'`, type annotations, static method return types, and all consuming files (store actions, resolvers, selectors, hooks, components).

`UplinkData` becomes `LiquidData` in the interface definition, `global.d.ts` Window type, and all consuming files. Note: this type and its `window.uplinkData` usage are dead code. Rename them for now but they can be removed separately.

### Store identifiers

| Old | New | File |
|---|---|---|
| `STORE_NAME = 'stellarwp/uplink'` | `STORE_NAME = 'lw'` | `store/constants.ts` |
| `registerUplinkStore` | `registerHarborStore` | `store/index.ts`, `index.tsx` |
| `uplinkStore` (import alias) | `harborStore` | hooks, components |

### Window and DOM references

| Old | New |
|---|---|
| `window.uplinkData` | `window.lwData` |
| `'uplink-root'` | `'lw-harbor-root'` |

These must match what the PHP side emits (changed in Stage 3).

### REST paths in JS

`'stellarwp/uplink/v1'` becomes `'liquidweb/v1'` in `store/actions.ts` (6 paths) and `store/resolvers.ts` (4 paths).

### CSS scope

The CSS scoping chain must stay in sync across 4 files:

| File | Old | New |
|---|---|---|
| `resources/css/globals.css` | `.uplink-ui` (~6 occurrences) | `.lw-harbor` |
| `postcss.config.js` | `scopeToUplinkUI`, `'postcss-scope-to-uplink-ui'`, `.uplink-ui` | `scopeToLwHarbor`, `'postcss-scope-to-lw-harbor'`, `.lw-harbor` |
| `tailwind.config.js` | `.uplink-ui` in comments | `.lw-harbor` |
| `Feature_Manager_Page.php` | already done in Stage 3 | |

### Docblocks and comments

All `@package StellarWP\Uplink` tags in JS/TS files become `@package LiquidWeb\Harbor`. Prose references like "the Uplink plugin" become "the Harbor library".

### What NOT to commit

Do not commit the `build/` and `build-dev/` output in this stage. Stage 8 handles the final asset build commit.

## Testing

After this stage completes:

```bash
bun run typecheck
bun run build
```

Both must pass. Verification:

```bash
grep -ri 'uplink' resources/js/ resources/css/ postcss.config.js tailwind.config.js
```

Should return zero results.
