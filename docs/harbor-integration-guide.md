# Harbor Integration Guide

This document explains how to integrate a WordPress plugin with LiquidWeb Harbor v3 for unified license management.

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

## 2. Registering Your Product

**Filter:** `lw-harbor/product_registry`

Add your plugin to the Harbor product registry so it participates in unified licensing.

```php
add_filter('lw-harbor/product_registry', function (array $products): array {
    $products[] = [
        'product'      => 'your-product',          // Product (brand) slug — all plugins in the same product share a unified license
        'slug'         => 'your-plugin',         // Unique slug for this specific plugin
        'name'         => 'Your Plugin',         // Human-readable product name
        'version'      => YOUR_PLUGIN_VERSION,   // Current plugin version
        'embedded_key' => getBundledLicenseKey(), // Optional: pre-embedded license key
    ];

    return $products;
});
```

**Product array fields:**

| Field          | Required | Description                                                                                             |
| -------------- | -------- | ------------------------------------------------------------------------------------------------------- |
| `product`      | Yes      | Product (brand) slug. All plugins in the same product share a unified license.                          |
| `slug`         | Yes      | Unique identifier for this plugin. Used in `lw_harbor_is_product_license_active()`.                     |
| `name`         | Yes      | Human-readable name shown in the license UI.                                                            |
| `version`      | Yes      | Current plugin version.                                                                                 |
| `embedded_key` | No       | A license key bundled with the plugin (see [Embedded License Keys](#5-embedded--bundled-license-keys)). |

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
            'product'    => 'your-product',          // Must match the value used in product_registry
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
| `product`    | Yes      | Must match the value used in `product_registry`.  |
| `is_active`  | Yes      | Whether the license is currently active (`bool`). |
| `page_url`   | Yes      | Admin URL where the user can manage this license. |
| `expires_at` | No       | Expiry date string (e.g. `"2026-01-01"`).         |

> **Tip:** If a single license key covers multiple add-ons, emit one entry per add-on slug so each slug can be checked independently via `lw_harbor_is_product_license_active()`.

### Admin notices for inactive legacy licenses

Once you report licenses via this filter, Harbor automatically displays consolidated admin notices for any inactive licenses that are not already covered by a v3 unified license. Notices are grouped by product, shown only to administrators, and are dismissible per user for 7 days.

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

### Check feature flags

```php
// Feature must be in the catalog AND enabled
if (lw_harbor_is_feature_enabled('feature-slug')) {
    // Feature is available and active
}

// Feature exists in the catalog regardless of enabled state
if (lw_harbor_is_feature_available('feature-slug')) {
    // Feature exists in catalog
}
```

---

## 5. Embedded / Bundled License Keys

If your plugin ships with a pre-embedded license key (e.g. for white-labeling or bundled distribution), provide it via the `embedded_key` field in `product_registry`.

The recommended pattern is to store the key in a dedicated PHP file excluded from version control:

```php
// PLUGIN_LICENSE.php (gitignored, injected at build/deploy time)
<?php return 'your-embedded-license-key-here';
```

Load it at runtime:

```php
function getBundledLicenseKey(): ?string
{
    $filePath = PLUGIN_DIR . 'PLUGIN_LICENSE.php';

    if (!is_readable($filePath)) {
        return null;
    }

    return include $filePath;
}
```

Pass the return value as `embedded_key` when registering your product (see [Section 2](#2-registering-your-product)).

---

## 6. Quick Reference

### Filters

| Filter                       | Purpose                                                                         |
| ---------------------------- | ------------------------------------------------------------------------------- |
| `lw-harbor/product_registry` | Register your product with Harbor. Receives and returns `array $products`.      |
| `lw-harbor/legacy_licenses`  | Report pre-existing licenses to Harbor. Receives and returns `array $licenses`. |

### Global Functions

| Function                              | Signature              | Purpose                                                       |
| ------------------------------------- | ---------------------- | ------------------------------------------------------------- |
| `lw_harbor_is_product_license_active` | `(string $slug): bool` | Check if a specific product slug has an active license.       |
| `lw_harbor_has_unified_license_key`   | `(): bool`             | Check if a unified key is stored locally (no remote call).    |
| `lw_harbor_get_unified_license_key`   | `(): ?string`          | Retrieve the stored unified license key.                      |
| `lw_harbor_is_feature_enabled`        | `(string $slug): bool` | Check if a feature is in the catalog and enabled.             |
| `lw_harbor_is_feature_available`      | `(string $slug): bool` | Check if a feature exists in the catalog regardless of state. |
