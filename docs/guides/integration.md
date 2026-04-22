# Harbor Integration Guide

This document explains how to integrate a WordPress plugin with LiquidWeb Harbor for unified license management.

---

## Notes on examples

Since the recommendation is to use [Strauss](https://github.com/BrianHenryIE/strauss) to prefix this library's namespaces, all examples use the `Boomshakalaka` namespace prefix. Replace `Boomshakalaka` with your actual vendor prefix wherever it appears.

---

## 1. Initialization

Harbor must be initialized once per plugin, typically inside a service provider registered during the plugin bootstrap.

```php
use Boomshakalaka\LiquidWeb\Harbor\Config;
use Boomshakalaka\LiquidWeb\Harbor\Harbor;

class HarborServiceProvider
{
    public function register(): void
    {
        // Tell Harbor which plugin file hosts this instance.
        // Use a plugin basename constant defined in your main plugin file,
        // e.g. define( 'MY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) )
        Config::set_plugin_file(MY_PLUGIN_BASENAME);

        // Give Harbor access to your DI container
        Config::set_container($container);

        // Boot all Harbor subsystems
        Harbor::init();
    }

    public function boot(): void
    {
        // Register filters here (see sections below)
    }
}
```

**Key points:**

- `Config::set_plugin_file()` must receive the plugin basename (e.g. `myplugin/myplugin.php`). Define a constant like `MY_PLUGIN_BASENAME` in your main plugin file using `plugin_basename( __FILE__ )` and pass that — calling `plugin_basename( __FILE__ )` from inside a service class will resolve the wrong file
- `Config::set_container()` must be called before `Harbor::init()`
- `Harbor::init()` sets up all internal providers (storage, API, licensing, admin UI, etc.)
- Register the Harbor service provider after all other providers so the container is fully configured

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
            'key'        => $license['key'],         // The license key string
            'slug'       => $license['slug'],        // The product/add-on slug this key covers
            'name'       => $license['name'],        // Human-readable product name
            'product'    => 'your-product',          // Product brand slug
            'is_active'  => $license['is_active'],   // bool
            'page_url'   => admin_url('...'),        // Where the user can manage this license
            'expires_at' => $license['expires'],     // Optional: ISO date string e.g. "2026-01-01"
        ];
    }

    return $licenses;
});
```

**Legacy license array fields:**

| Field        | Required | Description                                       |
| ------------ | -------- | ------------------------------------------------- |
| `key`        | Yes      | The license key string.                           |
| `slug`       | Yes      | The product/add-on slug this key applies to.      |
| `name`       | Yes      | Human-readable product name.                      |
| `product`    | Yes      | Product brand slug (e.g. `givewp`, `kadence`).    |
| `is_active`  | Yes      | Whether the license is currently active (`bool`). |
| `page_url`   | Yes      | Admin URL where the user can manage this license. |
| `expires_at` | No       | Expiry date string (e.g. `"2026-01-01"`).         |

> **Tip:** If a single license key covers multiple add-ons, emit one entry per add-on slug so each slug can display a legacy license badge on the Feature Manager page.

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

### Get the Feature Manager admin URL

```php
$url = lw_harbor_get_license_page_url(); // string (empty string if Harbor is not active)
```

---

## 5. Registering a Submenu Link

If your plugin has its own top-level admin menu, call `lw_harbor_register_submenu()` to append a **Licensing** item that links directly to the Harbor Feature Manager page. This lets users reach the unified license UI without leaving your plugin's menu area.

```php
lw_harbor_register_submenu('my-plugin-menu-slug');
```

Call this during or after `plugins_loaded`, before the `admin_menu` hook fires. The item is always appended last in the submenu so it does not disrupt your plugin's own menu order.

The function always delegates to the highest-version Harbor instance on the site, so it is safe to call even when multiple plugins bundle Harbor.

---

## 6. Embedded / Bundled License Keys

See [Section 2](#2-bundling-a-license-key). Bundling a key is done entirely through `LWSW_KEY.php` — no additional wiring is needed.

---

## 7. Quick Reference

### Filters

| Filter                      | Purpose                                                                         |
| --------------------------- | ------------------------------------------------------------------------------- |
| `lw-harbor/legacy_licenses` | Report pre-existing licenses to Harbor. Receives and returns `array $licenses`. |

### Global Functions

| Function                                       | Signature                           | Purpose                                                                         |
| ---------------------------------------------- | ----------------------------------- | ------------------------------------------------------------------------------- |
| `lw_harbor_is_product_license_active`          | `(string $slug): bool`              | Check if a specific product slug has an active license.                         |
| `lw_harbor_has_unified_license_key`            | `(): bool`                          | Check if a unified key is stored locally (no remote call).                      |
| `lw_harbor_get_unified_license_key`            | `(): ?string`                       | Retrieve the stored unified license key.                                        |
| `lw_harbor_is_feature_enabled`                 | `(string $slug): bool`              | Check if a feature is currently active locally on this site.                    |
| `lw_harbor_is_feature_available`               | `(string $slug): bool`              | Check if the customer's license/tier includes this feature.                     |
| `lw_harbor_get_license_page_url`               | `(): string`                        | Get the admin URL for the Feature Manager page (empty string if inactive).      |
| `lw_harbor_get_licensed_domain`                | `(): string`                        | Get the domain Harbor uses for licensing on this site.                          |
| `lw_harbor_register_submenu`                   | `(string $parent_slug): void`       | Append a Licensing submenu item to a plugin's top-level admin menu.             |
| `lw_harbor_display_legacy_license_page_notice` | `(string $product_name = ''): void` | Display a notice on a legacy license page pointing users to the unified system. |
