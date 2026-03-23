---
status: draft
---

# Stage 7: Documentation

## Problem

All documentation still references old names: CLAUDE.md (the primary developer reference), README.md, the architecture docs in `docs/`, and the API Functions README. Anyone reading the docs after the rename will see stale namespace references, wrong function names, and broken cross-references.

## Proposed solution

Rename doc files, then bulk-update all documentation content to use the new names, namespaces, function names, and conventions.

### File renames

```bash
git mv docs/uplink-v3.md docs/harbor.md
git mv docs/uplink-v3-integration-guide.md docs/harbor-integration-guide.md
git mv docs/uplink-v3-fat-leader-thin-instance.md docs/harbor-fat-leader-thin-instance.md
```

### Content updates

**CLAUDE.md** is the most important file to get right. It needs updated directory paths (`src/Harbor/`), namespace references (`LiquidWeb\Harbor`), class names (`Harbor.php` not `Uplink.php`), function name references (`lw_harbor_*` not `stellarwp_uplink_*`), doc links, hook examples, and prose.

**README.md** needs the title, composer require command, code examples, and any identifier references updated.

**All docs/*.md files** need namespace references, hook names, error codes, function names, directory paths, cross-references to renamed doc files, and prose updated.

**`src/Harbor/API/Functions/README.md`** has function name examples that need updating.

**`docs/conventions.md`** was already written with the new names. No changes needed.

Bulk replace patterns across documentation:

| Old | New |
|---|---|
| `StellarWP\Uplink` | `LiquidWeb\Harbor` |
| `stellarwp/uplink/` | `lw-harbor/` |
| `stellarwp_uplink_*` | `lw_harbor_*` (for options, functions) |
| `stellarwp-uplink-*` | `lw-harbor-*` (for error codes) |
| `src/Uplink/` | `src/Harbor/` |
| `docs/uplink-v3` | `docs/harbor` (in cross-references) |
| Prose "Uplink" | "Harbor" |
| Prose "StellarWP" | "LiquidWeb" or "Liquid Web" as appropriate |

Do not touch `.plans/liquid-web-harbor-naming.md`. That document intentionally references old names as part of the rename mapping.

## Testing

```bash
grep -ri 'uplink' docs/ CLAUDE.md README.md src/Harbor/API/Functions/README.md | grep -v 'plans/'
```

Zero results.
