---
status: draft
---

# Stage 6: CI configuration

## Problem

The GitHub Actions workflow still references old names. The `slic use` command was updated to `license-manager` in Stage 1 but now needs to become `harbor`.

## Proposed solution

Update `.github/workflows/tests-php.yml`:

| Old | New |
|---|---|
| `StellarWP Uplink` (comment, line ~91) | `LiquidWeb Harbor` |
| `${SLIC_BIN} use license-manager` (line ~93) | `${SLIC_BIN} use harbor` |

Update `tests/wpunit.suite.dist.yml`:

| Old | New |
|---|---|
| `title: 'StellarWP Uplink Tests'` | `title: 'LiquidWeb Harbor Tests'` |

Check for any other workflow files under `.github/` or test config files with remaining Uplink or license-manager references.

## Testing

```bash
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/tests-php.yml')); print('YAML valid')"
grep -rn 'uplink\|license-manager' .github/ tests/*.yml tests/*.dist.yml
```

YAML parses. Grep returns zero results.
