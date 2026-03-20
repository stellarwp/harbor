---
status: draft
---

# Remove V2 code from the codebase

## Problem

This repo was forked from Uplink V2 and still carries most of that codebase. The V3 subsystems (Catalog, Features, Licensing, REST API) are complete and self-contained, but large portions of V2 code remain and create confusion about what is active, add dead code to maintain, and obscure the V3 architecture.

The key indicator is `Uplink::init()`: everything inside the `is_enabled()` conditional block is V2 infrastructure (Storage provider, API/V3 provider, Notice provider, Admin provider, Auth provider). Everything outside it is V3 and should be kept.

## Proposed solution

Remove all V2 code that is not referenced by the V3 providers registered outside the `is_enabled()` block in `Uplink::init()`. The V3 providers are: `Legacy`, `Features`, `Http`, `Licensing`, `Catalog`, `API\REST\V1`, `API\Functions`, `CLI`, and `Cron`.

### Full directory deletions (no V3 dependencies)
- `src/Uplink/Pipeline/`
- `src/Uplink/Exceptions/`
- `src/Uplink/Messages/`
- `src/Uplink/Auth/`

### Partial directory deletions (mixed content, delete only V2 files)

`src/Uplink/Notice/` -- keep `Notice_Controller.php` and `Notice.php` (used by `Legacy\Provider`), delete `Notice_Handler.php` and `Provider.php` (registered only inside `is_enabled`)

`src/Uplink/Resources/` -- keep `Collection.php` and `Resource.php` if still referenced after Auth is removed; otherwise delete the whole directory. Delete `License.php`, `Plugin.php`, `Service.php`, and the `Filters/` subdirectory regardless.

`src/Uplink/API/` -- keep `API/V3/`, `API/REST/V1/`, `API/Functions/`. Delete `API/Client.php` and `API/Validation_Response.php` (V2 validation pipeline).

`src/Uplink/Admin/` -- keep `Feature_Manager_Page.php`, `Asset_Manager.php`, `Fields/`, and a gutted `Provider.php`. Delete `Notice.php`, `Plugins_Page.php`, `License_Field.php`, `Package_Handler.php`, `Update_Prevention.php`, `Field.php`, `Ajax.php`, `Group.php`.

### File deletions
- `src/Uplink/Register.php` (V2 plugin/service resource factory, no V3 equivalent needed)
- `src/Uplink/Functions/functions.php` (global helpers that wrap V2 Resources)

### Uplink.php changes
- Remove the `is_enabled()` block and its contents
- Remove `is_enabled()` and `is_disabled()` methods
- Gut `register_cross_instance_hooks()` -- keep the `_stellarwp_uplink_instance_registry()` call and `Version::register_debug_info()`, remove the three filters that delegate to Resources (`validate_license`, `set_license_key`, `delete_license_key`)
- Remove singleton bindings for deleted providers
- Remove the `functions.php` require

### Admin\Provider.php changes
Gut it down to registering only `Feature_Manager_Page` and `Asset_Manager`, and hooking only `admin_menu` (for `register_unified_feature_manager_page`) and `admin_enqueue_scripts` (for `register_assets`).

### Test cleanup
Delete all tests for removed classes. Update any tests for kept classes that previously set up V2 fixtures (Resources, Auth, etc.).
