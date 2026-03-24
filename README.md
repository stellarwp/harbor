# LiquidWeb Harbor

[![PHP Compatibility](https://github.com/stellarwp/harbor/actions/workflows/compatibility.yml/badge.svg)](https://github.com/stellarwp/harbor/actions/workflows/compatibility.yml)
[![PHP Tests](https://github.com/stellarwp/harbor/actions/workflows/tests-php.yml/badge.svg)](https://github.com/stellarwp/harbor/actions/workflows/tests-php.yml)
[![PHPStan](https://github.com/stellarwp/harbor/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/stellarwp/harbor/actions/workflows/static-analysis.yml)

## Installation

It's recommended that you install Harbor as a project dependency via [Composer](https://getcomposer.org/):

```bash
composer require liquidweb/harbor
```

> We _actually_ recommend that this library gets included in your project using [Strauss](https://github.com/BrianHenryIE/strauss).
>
> Luckily, adding Strauss to your `composer.json` is only slightly more complicated than adding a typical dependency, so checkout our [strauss docs](https://github.com/stellarwp/global-docs/blob/main/docs/strauss-setup.md).

## Initialize the library

Initializing the Harbor library should be done within the `plugins_loaded` action, preferably at priority `0`.

```php
use LiquidWeb\Harbor\Harbor;

add_action( 'plugins_loaded', function() {
 /**
  * Configure the container.
  *
  * The container must be compatible with stellarwp/container-contract.
  * See here: https://github.com/stellarwp/container-contract#usage.
  *
  * If you do not have a container, we recommend https://github.com/lucatume/di52
  * and the corresponding wrapper:
  * https://github.com/stellarwp/container-contract/blob/main/examples/di52/Container.php
  */
 $container = new Container();
 Config::set_container( $container );
 Harbor::init();
}, 0 );
```

## Translation

Package is using `__( 'Invalid request: nonce field is expired. Please try again.', '%TEXTDOMAIN%' )` function for translation. In order to change domain placeholder `'%TEXTDOMAIN%'` to your plugin translation domain run

```bash
./bin/stellar-harbor domain=<your-plugin-domain>
```

or

```bash
./bin/stellar-harbor
```

and prompt the plugin domain
You can also add lines below to your composer file in order to run command automatically

```json
"scripts": {
 "stellar-harbor": [
   "vendor/bin/stellar-harbor domain=<your-plugin-domain>"
 ],
 "post-install-cmd": [
   "@stellar-harbor"
 ],
 "post-update-cmd": [
   "@stellar-harbor"
 ]
  }
```

## Registering a plugin

To register your plugin, you need to filter the `lw-harbor/product_registry` hook. See the [Harbor Integration Guide](/docs/harbor-integration-guide.md) for more details.

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

| Field          | Required | Description                                                                         |
| -------------- | -------- | ----------------------------------------------------------------------------------- |
| `product`      | Yes      | Product (brand) slug. All plugins in the same product share a unified license.      |
| `slug`         | Yes      | Unique identifier for this plugin. Used in `lw_harbor_is_product_license_active()`. |
| `name`         | Yes      | Human-readable name shown in the license UI.                                        |
| `version`      | Yes      | Current plugin version.                                                             |
| `embedded_key` | No       | A license key bundled with the plugin.                                              |

## Changelog

This project uses [@stellarwp/changelogger](https://github.com/stellarwp/changelogger) to manage its changelog. All notable changes are tracked via changelog entry files in the `changelog/` directory.

To add a new changelog entry:

```bash
bunx @stellarwp/changelogger add
```

To compile changelog entries into `changelog.txt`:

```bash
bunx @stellarwp/changelogger write --overwrite-version <version>
```

## Additional documentation

### Harbor

- [Harbor](/docs/harbor.md) — Primary architecture document for v3 unified licensing.
- [Licensing](/docs/licensing.md) — Key discovery, API responses, validation workflows, caching.
- [Catalog](/docs/catalog.md) — Product families, tiers, features, the Commerce Portal API.
- [Features](/docs/features.md) — Feature types, resolution, strategies, Manager API, REST endpoints.
- [Unified License Key](/docs/unified-license-key-system-design.md) — Key model, seat mechanics, system boundaries.
- [Multi-Instance Architecture](/docs/harbor-fat-leader-thin-instance.md) — Leader election, cross-instance hooks, thin instances.
- [Harbor Integration Guide](/docs/harbor-integration-guide.md) — How to integrate your plugin with Harbor.

### General

- [CLI Commands](/docs/cli.md) — WP-CLI commands for feature management.
- [Testing](/docs/testing.md) — How to set up and run automated tests with Codeception and `slic`.
