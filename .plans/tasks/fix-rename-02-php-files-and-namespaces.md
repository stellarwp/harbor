---
status: draft
---

# Stage 2: Rename PHP files and namespaces

## Problem

After v2 dead code removal, the codebase still uses `StellarWP\Uplink` as its PHP namespace, `src/Uplink/` as its source directory, and `stellarwp/uplink` as its Composer package name. All of these need to become `LiquidWeb\Harbor` equivalents per the naming conventions in `docs/conventions.md`.

This stage is where the PSR-4 autoloading mapping changes, so it must be done atomically: the directory rename, the composer.json PSR-4 entries, and every namespace declaration and class reference must all change together. If any piece is out of sync, autoloading breaks.

## Proposed solution

Rename directories and files with `git mv`, update package configuration, then bulk-rename all PHP namespaces and class references. Update the phpstan config and baseline to match the new paths and class names.

### File and directory renames

Use `git mv` to preserve blame history.

```
git mv src/Uplink src/Harbor
git mv src/Harbor/Uplink.php src/Harbor/Harbor.php
git mv bin/stellar-uplink bin/stellar-harbor
git mv tests/_support/Helper/UplinkTestCase.php tests/_support/Helper/HarborTestCase.php
```

JS file renames (`uplink-error.ts`, `uplink-data.ts`) do NOT happen here. They happen in Stage 4 alongside JS content changes so TypeScript imports are never in a broken state.

### Package configuration (composer.json)

The package name changes from `stellarwp/uplink` to `stellarwp/harbor`.

All 5 PSR-4 entries update:

| Old | New |
|---|---|
| `"StellarWP\\Uplink\\": "src/Uplink/"` | `"LiquidWeb\\Harbor\\": "src/Harbor/"` |
| `"StellarWP\\Uplink\\Views\\": "src/views/"` | `"LiquidWeb\\Harbor\\Views\\": "src/views/"` |
| `"StellarWP\\Uplink\\Build_Dir\\": "build/"` | `"LiquidWeb\\Harbor\\Build_Dir\\": "build/"` |
| `"StellarWP\\Uplink\\Build_Dev_Dir\\": "build-dev/"` | `"LiquidWeb\\Harbor\\Build_Dev_Dir\\": "build-dev/"` |
| `"StellarWP\\Uplink\\Tests\\": [...]` (autoload-dev) | `"LiquidWeb\\Harbor\\Tests\\": [...]` |

The bin entry changes from `bin/stellar-uplink` to `bin/stellar-harbor`.

### Package configuration (package.json)

The package name changes from `@stellarwp/uplink` to `@stellarwp/harbor`.

The changelogger `linkTemplate` changes from `stellarwp/uplink` to `stellarwp/harbor`.

### PHP namespace bulk rename

Apply across all `.php` files in `src/`, `tests/`, and project root files (`bootstrap-plugin.php`, `index.php`). Order patterns from most specific to least specific to avoid partial matches.

**158 namespace declarations** (`namespace StellarWP\Uplink...`) across 91 source files and 67 test files.

**548 use statements** (`use StellarWP\Uplink\...`) across 140 files.

Patterns:

1. `StellarWP\Uplink\` to `LiquidWeb\Harbor\` (namespace declarations, use statements, inline class references)
2. `StellarWP\Uplink` to `LiquidWeb\Harbor` (end-of-line references without trailing backslash)

### Prose references

These are NOT caught by the namespace patterns above because they use a space instead of a backslash:

- `bootstrap-plugin.php`: plugin header contains `"StellarWP Uplink"`
- `tests/plugin.php`: plugin header contains `"StellarWP Uplink"`

Both become `"LiquidWeb Harbor"`.

### Bootstrap class rename

In `src/Harbor/Harbor.php` (after the git mv), `class Uplink` becomes `class Harbor`.

All references across the codebase update:

- `Uplink::init()` becomes `Harbor::init()` (in `bootstrap-plugin.php`, `tests/_support/Helper/HarborTestCase.php`)
- `Uplink::VERSION` becomes `Harbor::VERSION` (in `Utils/Version.php`, `API/Functions/Provider.php`, `Legacy/Notices/License_Notice_Handler.php`, and 2 test files)
- `_stellarwp_uplink_instance_registry( self::VERSION )` call stays as-is in this stage (the function name is a string identifier, changed in Stage 3)

### Test base class rename

`class UplinkTestCase` becomes `class HarborTestCase`. **64 test files** have `extends UplinkTestCase` that needs updating. The internal references in UplinkTestCase itself (`use StellarWP\Uplink\Config`, `use StellarWP\Uplink\Uplink`, and `Uplink::init()`) also update to use the new namespace and class name.

### @package docblock

1 occurrence in `src/Uplink/Admin/Feature_Manager_Page.php`: `@package StellarWP\Uplink` becomes `@package LiquidWeb\Harbor`.

### PHPStan configuration

**phpstan.neon.dist** has 4 path entries and 1 comment referencing `src/Uplink/`:

- `src/Uplink/global-functions.php` becomes `src/Harbor/global-functions.php`
- `src/Uplink/Uplink.php` becomes `src/Harbor/Harbor.php`
- `src/Uplink/Utils/Version.php` becomes `src/Harbor/Utils/Version.php`
- `src/Uplink/API/Functions/Global_Function_Registry.php` becomes `src/Harbor/API/Functions/Global_Function_Registry.php`
- Comment: "Strauss-prefixed copies of Uplink" becomes "Strauss-prefixed copies of Harbor"

**phpstan-baseline.neon** has two kinds of Uplink references:

1. **Path references** (`path: src/Uplink/...`): 21 entries become `src/Harbor/...`
2. **Class name references** in rawMessage strings: 3 entries reference `StellarWP\Uplink\` class names and become `LiquidWeb\Harbor\`

Importantly, rawMessage strings that contain string LITERALS (like `'stellarwp/uplink/'`) must NOT be updated here. Those strings have not changed in the source code yet (they change in Stage 3), so the baseline rawMessages still need to match the old strings.

### What NOT to change in this stage

These all look like Uplink references but belong to later stages:

- String identifiers (`'stellarwp/uplink/'` hooks, `'stellarwp_uplink_'` options, `'stellarwp-uplink-'` error codes) stay until Stage 3
- Global function names (`stellarwp_uplink_*`, `_stellarwp_uplink_*`) stay until Stage 3
- JS/TS content and file names stay until Stage 4
- `@since 3.0.0` version tags stay until Stage 5
- Documentation content stays until Stage 7

## Testing

After this stage completes:

```bash
composer dump-autoload
composer test:analysis
```

Both must pass. If `test:analysis` fails, generate a temp baseline to diff against:

```bash
phpstan analyse -c phpstan.neon.dist --memory-limit=-1 --generate-baseline=temp.neon
diff temp.neon phpstan-baseline.neon
rm temp.neon
```

Verification greps (should return zero results):

```bash
grep -rn 'namespace StellarWP\\Uplink' src/Harbor/ tests/
grep -rn 'use StellarWP\\Uplink\\' src/Harbor/ tests/
grep -n 'src/Uplink' phpstan.neon.dist phpstan-baseline.neon
```
