# Harbor Integration Guide

This document explains how to integrate a WordPress plugin with LiquidWeb Harbor for unified license management.

---

## Notes on examples

Since the recommendation is to use [Strauss](https://github.com/BrianHenryIE/strauss) to prefix this library's namespaces, all examples use the `Boomshakalaka` namespace prefix. Replace `Boomshakalaka` with your actual vendor prefix wherever it appears.

### Strauss must not prefix the global functions

Harbor's global functions (`src/Harbor/global-functions.php`) are deliberately non-namespaced. They are how the copies of Harbor on a site find each other and route every call to the highest-version copy. Recent Strauss versions prefix global function names as well as namespaces, which breaks that negotiation: each plugin ends up with its own privately-named copy of the helpers, and `function_exists()` guards never see one another.

Exclude the file in your `composer.json` Strauss config:

```json
"exclude_from_prefix": {
    "file_patterns": [
        "/harbor/src/Harbor/global-functions\\.php$"
    ]
}
```

---

## Before you build: the free-vs-premium boundary

Harbor is bundled in free WordPress.org plugins (Kadence Blocks, Give, TEC free) as well as paid ones, and it stays completely inert in the free ones by design. This is not a nicety. When Harbor first shipped inside The Events Calendar, the WordPress.org plugins team threatened to remove the plugin from the directory.

**A plugin distributed on WordPress.org must not:**

- present a license field that validates a key,
- call Harbor, the Commerce Portal, the Licensing API, or Herald at runtime,
- install or activate anything from an entered key.

**The most a free plugin may do** is show a static link pointing the user to the Portal, and optionally detect the `LWSW-` key *format* locally — a string check with no network call — to steer a user who pasted a unified key into a legacy field.

**All new licensing and activation surface lives in the premium plugin**, where the premium-plugin gate has opened and Harbor is active. Every license field, validation call, activation button, and install flow belongs there. (Pre-existing Uplink license fields in free plugins are grandfathered and are not what this rule is about; it governs new surface.)

**Onboarding for a free product is never gated behind a license.** A free plugin's onboarding must complete without a key. This has been attempted before and must not ship.

The three WordPress.org guideline points at stake, and what keeps Harbor on the right side of each:

| Risk                                | Guideline                                                        | Harbor guardrail                                                                                                                                                                                                          |
| ----------------------------------- | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Installing a paid add-on from a key | Item 8 — executable code served from outside the .org repo       | Harbor never auto-installs. Installs are user-initiated, `manage_options`-gated, tier-gated, and require a valid `LWSW-` key on a site where a premium plugin is installed.                                               |
| "Enter key to unlock"               | Items 5 & 6 — a free plugin gating its own features behind a key | Storing a key runs `validate_and_store()`, which verifies the key and fetches the catalog. It does not consume a seat, activate a product, or unlock anything, and the path only exists once a premium plugin is present. |
| Phoning home without consent        | Item 7 — outbound calls the user never opted into                | The entire networked subsystem sits behind the premium-plugin gate. A free-only site never contacts our servers.                                                                                                          |

Any ticket that moves one of these lines — validating in a free plugin, installing from a key, calling out without the gate — is out of bounds regardless of the UX benefit. Take it to the premium plugin instead.

### Consume the API; never hand-roll

Portal and activation URLs, script handles, and license state come from Harbor's `lw_harbor_*` global functions. Do not build a Portal query string, activation URL, or licensing request inside a plugin. Every hand-rolled copy is a copy of the Portal contract that silently drifts the moment the Portal changes a parameter, and it bypasses the version negotiation that makes the helpers resolve to the highest Harbor copy on the site.

If the helper you need does not exist yet, that is a Harbor ticket, not a reason to inline it. See [The Harbor release train](#the-harbor-release-train).

### Naming: the "Unified License Manager"

Harbor's in-plugin license management page is called the **Unified License Manager** in everything a user sees: UI copy, onboarding text, link labels, documentation. Use that name in every plugin so users meet one name everywhere.

The name is deliberately brand-neutral. The company name has changed several times (Liquid Web / Nexcess / StellarWP), and a brand-based label would need re-touching in every plugin on every rebrand.

Internally the page is `Admin\Feature_Manager_Page` and the docs refer to the Feature Manager when discussing the code. That is an implementation name — do not surface it to users.

### The Harbor release train

Harbor changes ship on Harbor's release train. A plugin ticket that needs new Harbor code is blocked until that code is reviewed, merged, tagged, and released:

```text
Harbor PR reviewed -> merged -> tagged/released
        -> composer update into each dependent plugin
        -> plugin PR QA'd -> plugin released
```

**Never release or QA a plugin whose `composer.json` pins a Harbor dev branch.** Pointing at `dev-main` or a feature branch while prototyping is fine, and it usefully makes the cross-repo dependency visible. Shipping in that state is not, because leadership is elected by version:

- A dev branch carries no bumped version. If another plugin on the site ships the same released version *without* your dev-only change and wins leadership, your feature silently does not run. Often it does not even error — depending on the code path, a missing method or class can also fatal.
- Code still in review can change before it merges, and the plugin that vendored it is now wrong.
- Pins rot. Kadence Shop Kit tracked a dev Uplink branch during Consolidation; the branch drifted so far from Uplink's latest that it could not be safely merged and complicated that project for a long time.

Planning consequence: any plugin ticket depending on new Harbor code inherits the Harbor release as a hard predecessor. Code review of the dependent plugin PR can run in parallel; QA cannot.

### Install the agent skill

Harbor ships an agent skill that states the rules above in the form an AI coding agent reads before it edits your plugin. Install it once, from the plugin root:

```bash
vendor/bin/harbor-install-skill
```

That writes `.claude/skills/harbor-integration/SKILL.md`, stamped with the Harbor version it came from. **Commit it** — it is the copy every developer and agent working in the repo will read, and it must be present without anyone running `composer install` first.

To keep it tracking the installed Harbor version, add it to your Composer scripts:

```json
"scripts": {
    "post-update-cmd": ["harbor-install-skill"]
}
```

**Ordering with Strauss.** The command reads from `vendor/stellarwp/harbor`. If your Strauss config sets `delete_vendor_packages` or `delete_vendor_files`, that directory is gone or gutted once Strauss has run, so the command must run *before* it:

```json
"scripts": {
    "post-update-cmd": ["harbor-install-skill", "@strauss"]
}
```

---

## 1. Initialization

Harbor must be initialized once per plugin, typically inside a service provider registered during the plugin bootstrap.

```php
use Boomshakalaka\LiquidWeb\Harbor\Config;
use Boomshakalaka\LiquidWeb\Harbor\Harbor;

// Announce this premium plugin to Harbor's bootstrap gate.
// This MUST run before Harbor::init(). See "The premium-plugin gate" below.
add_filter('lw_harbor/premium_plugin_exists', '__return_true');

class HarborServiceProvider
{
    public function register(): void
    {
        // Tell Harbor which plugin hosts this instance.
        // Use a plugin basename constant defined in your main plugin file,
        // e.g. define( 'MY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) )
        Config::set_plugin_basename(MY_PLUGIN_BASENAME);

        // Give Harbor access to your DI container
        Config::set_container($container);

        // Boot all Harbor subsystems (only if the premium-plugin gate passes)
        Harbor::init();
    }

    public function boot(): void
    {
        // Register filters here (see sections below)
    }
}
```

**Key points:**

- `Config::set_plugin_basename()` must receive the plugin basename (e.g. `myplugin/myplugin.php`). Define a constant like `MY_PLUGIN_BASENAME` in your main plugin file using `plugin_basename( __FILE__ )` and pass that — calling `plugin_basename( __FILE__ )` from inside a service class will resolve the wrong file
- `Config::set_container()` must be called before `Harbor::init()`
- `Harbor::init()` sets up all internal providers (storage, API, licensing, admin UI, etc.), but only when the premium-plugin gate passes (see next section)
- Register the Harbor service provider after all other providers so the container is fully configured

### The premium-plugin gate

`Harbor::init()` only registers providers, REST routes, the admin page, and the `lw_harbor/loaded` action when at least one callback on the `lw_harbor/premium_plugin_exists` filter returns `true`. This keeps Harbor dormant on sites that have only free entry plugins installed.

**The filter must be attached before `Harbor::init()` is called.** Anywhere earlier in the request works; the simplest pattern is to attach it on the line right above the `Harbor::init()` call (as shown in the example above). If your plugin attaches the filter from a service class that itself is loaded inside `Harbor::init()`, it is too late: the gate has already been evaluated.

```php
add_filter('lw_harbor/premium_plugin_exists', '__return_true');
```

Use a real condition (e.g. a license check) instead of `__return_true` if you want the gate to remain closed when the premium plugin is installed but not licensed.

Once the gate passes, Harbor fires the `lw_harbor/loaded` action. Hook anything that depends on Harbor being fully booted (admin notices, submenu registrations, REST consumers) on this action rather than on `plugins_loaded` directly.

```php
add_action('lw_harbor/loaded', function () {
    // Safe to call lw_harbor_register_submenu(), query the leader, etc.
});
```

---

## 2. Bundling a License Key

Harbor discovers your plugin's embedded key automatically by scanning active plugins for a file named `LWSW_KEY.php` in the plugin root. No filter registration is required.

Create `LWSW_KEY.php` in your plugin root and have it return your `LWSW-`-prefixed key:

```php
<?php return 'LWSW-xxxx-xxxx-xxxx-xxxx';
```

This file should be gitignored and injected at build or deploy time. Its presence signals to Harbor that your plugin belongs to the unified licensing system. Plugins managed by Uplink v2 do not ship this file.

When Harbor scans active plugins and finds this file, it reads the key and auto-stores it if no key is already present on the site. If a key is already stored, the stored key takes precedence.

---

## 3. Reporting Legacy Licenses

**Filter:** `lw-harbor/legacy_licenses`

If your plugin has a pre-existing license system (licenses stored in the database before Harbor), report those licenses to Harbor so they appear in the unified license UI.

```php
add_filter('lw-harbor/legacy_licenses', function (array $licenses): array {
    $storedLicenses = get_option('my_plugin_licenses', []);

    foreach ($storedLicenses as $license) {
        $licenses[] = [
            'key'             => $license['key'],         // The license key string
            'slug'            => $license['slug'],        // The product/add-on slug this key covers
            'name'            => $license['name'],        // Human-readable product name
            'product'         => 'your-product',          // Product brand slug
            'is_active'       => $license['is_active'],   // bool
            'use_for_updates' => true,                    // Opt-in: route updates and downloads via Herald. See below.
            'page_url'        => admin_url('...'),        // Where the user can manage this license
            'expires_at'      => $license['expires'],     // Optional: ISO date string e.g. "2026-01-01"
        ];
    }

    return $licenses;
});
```

**Legacy license array fields:**

| Field             | Required | Description                                                                                                         |
| ----------------- | -------- | ------------------------------------------------------------------------------------------------------------------- |
| `key`             | Yes      | The license key string.                                                                                             |
| `slug`            | Yes      | The product/add-on slug this key applies to.                                                                        |
| `name`            | Yes      | Human-readable product name.                                                                                        |
| `product`         | Yes      | Product brand slug (e.g. `givewp`, `kadence`).                                                                      |
| `is_active`       | Yes      | Whether the license is currently active (`bool`).                                                                   |
| `use_for_updates` | No       | Opt-in (`bool`, default `false`). Set to `true` only when the key is compatible with Stellar Licensing v3 / Herald. |
| `page_url`        | Yes      | Admin URL where the user can manage this license.                                                                   |
| `expires_at`      | No       | Expiry date string (e.g. `"2026-01-01"`).                                                                           |

> **Tip:** If a single license key covers multiple add-ons, emit one entry per add-on slug so each slug can display a legacy license badge in the Unified License Manager.

### How Harbor uses reported legacy keys

Reported entries always appear in the unified license UI and feed admin notices. Whether they also feed Harbor's update pipeline (feature availability and download URLs) depends on the `use_for_updates` opt-in:

1. **Opt-in entries (`use_for_updates = true`).** When such an entry is `is_active = true` with a non-empty `key`, it marks the catalog feature matching its `slug` as available and in-tier (even with no unified license installed, or when the installed unified tier does not include that feature). Update checks proceed for that slug, and the package URL routes through Herald's `/legacy/download` endpoint using the reported key.
2. **Opt-out or omitted (`use_for_updates = false`, the default).** The entry is informational only. It appears in the licensing UI and admin notices, but does not grant availability, does not show "update available" badges, and is never sent to Herald.
3. **Inactive entries (`is_active = false`).** Same informational-only treatment, plus the entry surfaces in admin notices urging the user to renew or reactivate.

**When to set `use_for_updates = true`.** Only when the legacy key is compatible with Stellar Licensing v3 (the system Herald authenticates against on `/legacy/download`). Plugins whose legacy keys are issued by a separate licensing backend (for example, SolidWP API keys, or some Give legacy keys) should leave it `false`: surfacing an "update available" badge for a key Herald cannot validate would lead to download failures during install. When in doubt, leave it off and the entry continues to display in the UI/notices without breaking anything.

**What `is_active` means.** Harbor takes this flag at face value from your plugin. It should reflect whatever your existing licensing system already considers a valid, in-good-standing license: for example, the result of a recent successful validation against your licensing server. Harbor does not (and cannot) independently verify the key; it trusts the reporting plugin to decide whether the customer is currently entitled to use the product. Regardless of the `is_active` value reported here, Herald validates the key server-side when serving the actual ZIP download, so a falsely-reported `is_active = true` cannot be used to obtain a package the customer is not entitled to.

**Malformed entries.** `key` and `slug` are both required (see the table above). Entries missing either field are not considered legacy licenses at all. They are dropped at repository intake and never appear in the UI, notices, availability checks, or download URLs. Only emit entries you have a real key for.

### Admin notices for inactive legacy licenses

Once you report licenses via this filter, Harbor automatically displays consolidated admin notices for any inactive licenses that are not already covered by a StellarWP v3 unified license. Notices are grouped by product, shown only to administrators, and are dismissible per user for 7 days.

Because Harbor handles this, you should remove or suppress any existing license-related admin notices in your own plugin to avoid showing duplicate warnings. The leader Harbor instance (the highest version on the site) is the one that renders the notices, so there is no risk of duplicates across plugins that all bundle Harbor.

### Notifying users on the legacy license page

If your plugin has its own license settings page, display a notice on that page to inform users that licensing has moved to Liquid Web's unified system:

```php
// With a product name (recommended)
lw_harbor_display_legacy_license_page_notice('GiveWP');

// Without a product name (generic fallback)
lw_harbor_display_legacy_license_page_notice();
```

This outputs a standard WordPress info notice:

> GiveWP iss now part of Liquid Web\'s software offerings. This page is still available for managing legacy licenses from your previous GiveWP account. If you purchased a new plan through Liquid Web, your products are managed through the Liquid Web Software Manager.

Call this function directly in the render callback for your legacy license page. Because it echoes immediately when called, no hook registration is needed — it renders wherever you place it.

---

## 4. Checking License Status

Use the global helper functions to check license state anywhere in your plugin. These functions always delegate to the highest-version Harbor instance present on the site, so they are safe to call even when multiple plugins bundle Harbor.

### Check if a product has an active license

```php
if (lw_harbor_is_product_license_active('your-plugin')) {
    // Plugin has an active unified license
}
```

This is the primary check for gating features or waiving platform fees.

### Check if a unified license key exists (local only, no remote call)

```php
if (lw_harbor_has_unified_license_key()) {
    // A unified key is stored locally
}
```

### Get the unified license key

```php
$key = lw_harbor_get_unified_license_key(); // string|null
```

### Get the licensed domain

```php
$domain = lw_harbor_get_licensed_domain(); // string
```

This returns the domain that Harbor uses for licensing on the current site (the host portion of the WordPress `siteurl`, lowercased). Useful when your plugin needs to display or transmit the licensed domain to an external service.

### Check feature availability

```php
// Feature is active locally on this site
if (lw_harbor_is_feature_enabled('feature-slug')) {
    // Feature is active
}

// Customer's license/tier includes this feature
if (lw_harbor_is_feature_available('feature-slug')) {
    // Feature is available under the current license
}
```

### Get the Unified License Manager admin URL

```php
$url = lw_harbor_get_license_page_url(); // string (empty string if Harbor is not active)
```

Label the link **Unified License Manager** in whatever UI you place it in. Never build this URL by hand.

### Force a catalog refresh

```php
$refreshed = lw_harbor_refresh_catalog(); // bool — true on success, false on failure
```

Bypasses Harbor's cached catalog and fetches a fresh copy from the Commerce Portal API. This is **synchronous** — the call blocks until the upstream responds — so reserve it for user-initiated actions (e.g. a "Refresh now" button) rather than passive page loads. Returns `false` when Harbor is not active or the upstream fetch fails; the previously cached catalog is preserved in that case so subsequent reads continue to work.

---

## 5. Registering a Submenu Link

If your plugin has its own top-level admin menu, call `lw_harbor_register_submenu()` to append a **Licensing** item that links directly to the Unified License Manager. This lets users reach the unified license UI without leaving your plugin's menu area.

```php
add_action('lw_harbor/loaded', function () {
    lw_harbor_register_submenu('my-plugin-menu-slug');
});
```

`lw_harbor_register_submenu()` is a no-op until the `lw_harbor/loaded` action has fired, so hook the call into that action (or any later hook). The item is always appended last in the submenu so it does not disrupt your plugin's own menu order.

The function always delegates to the highest-version Harbor instance on the site, so it is safe to call even when multiple plugins bundle Harbor.

### Hiding the Settings menu item

By default, Harbor registers a **Liquid Web Products** entry under the WordPress **Settings** menu. If your plugin surfaces the Unified License Manager through its own submenu link (above) and you do not want the standalone Settings entry, hook the `lw-harbor/hide_menu_item` filter:

```php
add_filter('lw-harbor/hide_menu_item', '__return_true');
```

The page itself remains registered, so direct URLs continue to work. The filter hides both the standalone **Settings → Liquid Web Products** entry and any submenu items added through `lw_harbor_register_submenu()`.

---

## 6. Embedded / Bundled License Keys

See [Section 2](#2-bundling-a-license-key). Bundling a key is done entirely through `LWSW_KEY.php` — no additional wiring is needed.

---

## 7. Quick Reference

### Never do this

| Don't                                                                                 | Do instead                                                               | Why                                                                                                        |
| ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------- |
| Build a Portal or activation query string in a plugin                                 | Call the `lw_harbor_*` helper                                            | One copy of the Portal contract per plugin; all drift the moment a parameter changes                       |
| Validate a key, or call Harbor / Portal / Licensing / Herald, from a free .org plugin | Static Portal link, or a local `LWSW-` format check with no network call | WordPress.org guideline items 5, 6, 7 — see [the boundary](#before-you-build-the-free-vs-premium-boundary) |
| Install or activate a plugin from an entered key                                      | User-initiated install inside the premium plugin                         | Guideline item 8 — executable code from outside the .org repo                                              |
| Gate free-plugin onboarding on a license                                              | Complete onboarding with no key                                          | Free onboarding must never require a key                                                                   |
| Release or QA a plugin pinned to a Harbor dev branch                                  | Wait for the tagged release, then `composer update`                      | Leader election is by version; a dev branch silently loses and the feature does not run                    |
| Let Strauss prefix `global-functions.php`                                             | Add the `exclude_from_prefix` file pattern                               | Prefixed helpers cannot find the other Harbor copies                                                       |
| Say "Feature Manager" in user-facing copy                                             | Say "Unified License Manager"                                            | One brand-neutral name across every plugin                                                                 |

### Filters

| Filter                            | Purpose                                                                                                                                                                                                         |
| --------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `lw_harbor/premium_plugin_exists` | Announce that a premium plugin is present so `Harbor::init()` registers its providers. Receives and returns `bool`. **Must be attached before `Harbor::init()` runs**; see [Initialization](#1-initialization). |
| `lw-harbor/legacy_licenses`       | Report pre-existing licenses to Harbor. Receives and returns `array $licenses`.                                                                                                                                 |
| `lw-harbor/hide_menu_item`        | Hide the **Liquid Web Products** Settings entry and any `lw_harbor_register_submenu()` items without unregistering the page itself.                                                                             |

### Actions

| Action             | Purpose                                                                                                                                                                                         |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `lw_harbor/loaded` | Fires once Harbor finishes registering providers. Only fires when the premium-plugin gate passes. Hook integrations that depend on Harbor being booted (submenu registrations, etc.) onto this. |

### Global Functions

| Function                                       | Signature                           | Purpose                                                                                                       |
| ---------------------------------------------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `lw_harbor_is_product_license_active`          | `(string $slug): bool`              | Check if a specific product slug has an active license.                                                       |
| `lw_harbor_has_unified_license_key`            | `(): bool`                          | Check if a unified key is stored locally (no remote call).                                                    |
| `lw_harbor_get_unified_license_key`            | `(): ?string`                       | Retrieve the stored unified license key.                                                                      |
| `lw_harbor_is_feature_enabled`                 | `(string $slug): bool`              | Check if a feature is currently active locally on this site.                                                  |
| `lw_harbor_is_feature_available`               | `(string $slug): bool`              | Check if the customer's license/tier includes this feature.                                                   |
| `lw_harbor_get_license_page_url`               | `(): string`                        | Get the admin URL for the Unified License Manager (empty string if inactive).                                 |
| `lw_harbor_get_licensed_domain`                | `(): string`                        | Get the domain Harbor uses for licensing on this site.                                                        |
| `lw_harbor_register_submenu`                   | `(string $parent_slug): void`       | Append a Licensing submenu item to a plugin's top-level admin menu. No-op until `lw_harbor/loaded` has fired. |
| `lw_harbor_display_legacy_license_page_notice` | `(string $product_name = ''): void` | Display a notice on a legacy license page pointing users to the unified system.                               |
| `lw_harbor_refresh_catalog`                    | `(): bool`                          | Force a synchronous re-fetch of the product catalog. Returns `true` on success, `false` on failure.           |

### Constants

Define these in `wp-config.php` (or another point that loads before Harbor) to override defaults.

| Constant                       | Type     | Purpose                                                                                                                                                                                                               |
| ------------------------------ | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `LW_HARBOR_DISABLE_DEBUG_LOG`  | `bool`   | When truthy, suppresses **all** of Harbor's debug logging even while `WP_DEBUG` and `WP_DEBUG_LOG` are enabled. Use this to silence Harbor's output without turning off WordPress debugging for the rest of the site. |
| `LW_HARBOR_LICENSING_BASE_URL` | `string` | Override the base URL for the licensing API. Intended for local development and testing.                                                                                                                              |
| `LW_HARBOR_PORTAL_BASE_URL`    | `string` | Override the base URL for the Commerce Portal catalog API. Intended for local development and testing.                                                                                                                |
| `LW_HARBOR_HERALD_BASE_URL`    | `string` | Override the base URL for the Herald API. Intended for local development and testing.                                                                                                                                 |

Harbor's debug logging is otherwise gated on both `WP_DEBUG` and `WP_DEBUG_LOG` being enabled, and all messages are prefixed with `Harbor:` for easy filtering in `debug.log`.
