---
status: draft
---

# Stage 5: Version numbers

## Problem

The codebase still uses version `3.0.0` throughout, inherited from the original Uplink v3 development. The fork needs its own version scheme starting at `0.0.1`, and all `@since 3.0.0` annotations need to reflect the new starting point of `1.0.0`.

## Proposed solution

Change the `VERSION` constant to `'0.0.1'` and bulk-replace all `@since 3.0.0` tags with `@since 1.0.0`. Only `3.0.0` gets changed. Older version tags (`@since 1.0.0`, `@since 1.3.0`, `@since 2.0.0`, etc.) are historical and stay as-is.

### VERSION constant

In `src/Harbor/Harbor.php`, `VERSION = '3.0.0'` becomes `VERSION = '0.0.1'`.

In `tests/plugin.php`, the plugin header `Version: 3.0.0` becomes `Version: 0.0.1` if present.

### @since tags

Spans PHP files in `src/` and `tests/`, plus TypeScript/JavaScript files in `resources/js/`. Use a literal `3.0.0` match, not a version regex, to avoid touching historical tags.

## Testing

After this stage completes:

```bash
composer test:analysis
```

Verification:

```bash
grep -r '@since 3.0.0' src/ resources/ tests/
grep -r "VERSION.*3.0.0" src/
```

Both greps return zero results.
