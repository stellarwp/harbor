---
status: draft
---

# Stage 3: Rename PHP string identifiers

## Problem

After Stage 2, PHP autoloading and class references all work under `LiquidWeb\Harbor`, but string-based identifiers throughout the code still say "uplink": WordPress hook names, option keys, container keys, REST namespace, error codes, global function names, CSS/DOM identifiers emitted by PHP, and the cron hook. These are all PHP strings so the autoloader does not care, but they need to match the naming conventions in `docs/conventions.md`.

## Proposed solution

Bulk-replace all string-based identifiers in PHP files, then update the phpstan baseline rawMessages for any affected entries. This is the most pattern-heavy stage because there are several different naming conventions at play.

### Hook and filter prefix

Every `apply_filters()`, `add_filter()`, `do_action()`, and `add_action()` call that uses a `'stellarwp/uplink/'` prefix changes to `'lw-harbor/'`.

### REST namespace

The four REST controllers in `API/REST/V1/` all define `$namespace = 'stellarwp/uplink/v1'` which becomes `'liquidweb/v1'`.

`Feature_Manager_Page.php` has `rest_url('uplink/v1/')` which was a pre-existing bug (missing the `stellarwp/` prefix). This gets fixed to `rest_url('liquidweb/v1/')`.

### Option, transient, and cache prefixes

Everything with `stellarwp_uplink_` becomes `lw_harbor_`. This hits constants and strings in `License_Repository`, `Catalog_Repository`, `Site/Data`, `Legacy/Notices/License_Notice_Handler`, and the `CronHook` value object.

Key option names:

| Old | New |
|---|---|
| `stellarwp_uplink_unified_license_key` | `lw_harbor_unified_license_key` |
| `stellarwp_uplink_licensing_products_state` | `lw_harbor_licensing_products_state` |
| `stellarwp_uplink_licensing_products_last_active_dates` | `lw_harbor_licensing_products_last_active_dates` |
| `stellarwp_uplink_catalog_state` | `lw_harbor_catalog_state` |
| `stellarwp_uplink_dismissed_notices` (user meta) | `lw_harbor_dismissed_notices` |
| `stellarwp_uplink_domain` (cache key) | `lw_harbor_domain` |
| `stellarwp_uplink_multisite_active_sites` (cache key) | `lw_harbor_multisite_active_sites` |
| `stellarwp_uplink_timezone` (cache key) | `lw_harbor_timezone` |
| `stellarwp_uplink_totals` (cache key) | `lw_harbor_totals` |
| `stellarwp_uplink_is_public` (cache key) | `lw_harbor_is_public` |

### Container key values

Verify these still exist after v2 code removal. If they survived:

| Old | New |
|---|---|
| `'uplink.admin-views.path'` | `'lw-harbor.admin-views.path'` |
| `'uplink.assets.uri'` | `'lw-harbor.assets.uri'` |
| `'uplink.token_prefix'` | `'lw-harbor.token_prefix'` |

### Error codes

42 constants across three files. The prefix `'stellarwp-uplink-'` becomes `'lw-harbor-'`.

- `Licensing/Error_Code.php` (14 constants)
- `Features/Error_Code.php` (28 constants)
- `Catalog/Error_Code.php` (2 constants)

### Global functions (triple-rename)

This is the trickiest part. Each function in `global-functions.php` has three references that must all change:

1. The `function_exists('...')` guard string
2. The `function ...(` definition
3. The registry key string passed to `_lw_harbor_global_function_registry()`

Missing any one breaks cross-instance negotiation between Strauss-prefixed copies.

| Old | New |
|---|---|
| `_stellarwp_uplink_instance_registry` | `_lw_harbor_instance_registry` |
| `_stellarwp_uplink_global_function_registry` | `_lw_harbor_global_function_registry` |
| `stellarwp_uplink_has_unified_license_key` | `lw_harbor_has_unified_license_key` |
| `stellarwp_uplink_get_unified_license_key` | `lw_harbor_get_unified_license_key` |
| `stellarwp_uplink_is_product_license_active` | `lw_harbor_is_product_license_active` |
| `stellarwp_uplink_is_feature_enabled` | `lw_harbor_is_feature_enabled` |
| `stellarwp_uplink_is_feature_available` | `lw_harbor_is_feature_available` |
| `stellarwp_uplink_get_license_page_url` | `lw_harbor_get_license_page_url` |

All callers outside `global-functions.php` need updating too: `Harbor.php`, `Utils/Version.php`, `API/Functions/Global_Function_Registry.php`, `Legacy/Notices/License_Notice_Handler.php`, and test files.

### CSS/DOM identifiers emitted by PHP

`Feature_Manager_Page.php`:

| Old | New |
|---|---|
| `'stellarwp-uplink-ui'` (script handle) | `'lw-harbor-ui'` |
| `'uplink-root'` (DOM id) | `'lw-harbor-root'` |
| `'uplink-ui'` (CSS class) | `'lw-harbor'` |
| `'uplinkData'` (localized var) | `'lwData'` (dead code, but rename for now) |

`License_Notice_Handler.php`:

| Old | New |
|---|---|
| `'stellarwp-uplink-notice-dismiss'` (script handle) | `'lw-harbor-notice-dismiss'` |
| `'uplinkNoticeDismiss'` (localized var) | `'lwNoticeDismiss'` |

### Cron hook

`CronHook.php` defines `stellarwp_uplink_data_refresh` which becomes `lw_harbor_data_refresh`.

### WP-CLI command name

`CLI/Provider.php` registers the parent command as `'uplink'` which becomes `'lw'`.

### PHPStan baseline update

After all the string changes, some baseline rawMessages will be stale. The `'stellarwp/uplink/'` string in concatenation expressions may get truncated by PHPStan with a unicode ellipsis. The baseline must use the exact truncated form.

Process: run `composer test:analysis`, note failures, generate a temp baseline to see exact rawMessage format, update the real baseline to match, delete the temp file, re-run.

### sed ordering

Always go from most specific to least specific: `stellarwp/uplink/v1` before `stellarwp/uplink/`, `stellarwp_uplink_unified_license_key` before `stellarwp_uplink_`, etc. Getting this wrong causes double-replacement artifacts.

## Testing

After this stage completes:

```bash
composer test:analysis
```

Verification greps (should return zero functional matches):

```bash
grep -rn "stellarwp/uplink" src/Harbor/ --include='*.php'
grep -rn "stellarwp_uplink" src/Harbor/ --include='*.php'
grep -rn "'uplink" src/Harbor/ --include='*.php' | grep -v '//'
```
