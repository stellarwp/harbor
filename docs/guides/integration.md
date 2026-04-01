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

## 5. Embedded / Bundled License Keys

See [Section 2](#2-bundling-a-license-key). Bundling a key is done entirely through `LWSW_KEY.php` — no additional wiring is needed.

---

## 6. Quick Reference

### Filters

| Filter                      | Purpose                                                                         |
| --------------------------- | ------------------------------------------------------------------------------- |
| `lw-harbor/legacy_licenses` | Report pre-existing licenses to Harbor. Receives and returns `array $licenses`. |

### Global Functions

| Function                              | Signature              | Purpose                                                                    |
| ------------------------------------- | ---------------------- | -------------------------------------------------------------------------- |
| `lw_harbor_is_product_license_active` | `(string $slug): bool` | Check if a specific product slug has an active license.                    |
| `lw_harbor_has_unified_license_key`   | `(): bool`             | Check if a unified key is stored locally (no remote call).                 |
| `lw_harbor_get_unified_license_key`   | `(): ?string`          | Retrieve the stored unified license key.                                   |
| `lw_harbor_is_feature_enabled`        | `(string $slug): bool` | Check if a feature is currently active locally on this site.               |
| `lw_harbor_is_feature_available`      | `(string $slug): bool` | Check if the customer's license/tier includes this feature.                |
| `lw_harbor_get_license_page_url`      | `(): string`           | Get the admin URL for the Feature Manager page (empty string if inactive). |
