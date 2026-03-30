# Conventions

## Naming

Harbor is a PHP library bundled by Liquid Web WordPress plugins. Each plugin ships its own vendor-prefixed copy via Strauss. Multiple copies coexist on a single WordPress site and negotiate leadership internally.

Because of this architecture, identifiers appear in several scopes with different collision risks. The conventions below ensure consistency and avoid conflicts.

### PHP Namespace

```php
namespace LiquidWeb\Harbor\Features;

use LiquidWeb\Harbor\Licensing\License_Manager;
```

### Packages

The Composer package is `stellarwp/harbor`. The GitHub repository lives under the `stellarwp` organization. These may migrate to `liquidweb` in the future depending on GitHub organization and Packagist policies that are still being resolved. The `stellarwp` scope is a publishing detail and does not affect the PHP namespace or any runtime identifiers.

The NPM package (`@stellarwp/harbor`) is private and not published to any registry. It exists only for local package management within this project.

### Source Layout

```
src/Harbor/           PHP source (PSR-4 root)
resources/js/         TypeScript/React frontend
resources/css/        CSS (Tailwind + PostCSS)
build/                Production assets (committed)
build-dev/            Development assets (committed)
```

### WordPress Hooks

Prefix: `lw-harbor/`

```php
apply_filters( 'lw-harbor/catalog/fetched', $catalog );
do_action( 'lw-harbor/licensing/key_stored', $key );
```

### Options, Transients, and User Meta

Prefix: `lw_harbor_`

```php
get_option( 'lw_harbor_unified_license_key' );
get_user_meta( $user_id, 'lw_harbor_dismissed_notices', true );
```

### Cache Keys

Prefix: `lw_harbor_`

```php
wp_cache_get( 'lw_harbor_domain', 'lw_harbor' );
```

### Container Keys

Prefix: `lw-harbor.`

```php
$container->get( 'lw-harbor.admin-views.path' );
```

### REST API

Namespace: `liquidweb/harbor/v1`

```
/wp-json/liquidweb/harbor/v1/features
/wp-json/liquidweb/harbor/v1/license
/wp-json/liquidweb/harbor/v1/catalog
/wp-json/liquidweb/harbor/v1/legacy-licenses
```

The `liquidweb/harbor/v1` namespace is shared across Liquid Web libraries. Harbor registers its routes there, and other libraries can add their own routes under the same namespace.

### Global PHP Functions

Prefix: `lw_harbor_` (public) and `_lw_harbor_` (internal)

```php
lw_harbor_is_feature_enabled( 'ai-content' );
lw_harbor_get_unified_license_key();
```

These are non-namespaced functions that survive Strauss prefixing. They use a global registry so the highest-version copy always handles the call. Each function has three references that must stay in sync: the `function_exists()` guard, the `function` definition, and the registry key.

### WP-CLI

Parent command: `wp harbor`

```
wp harbor feature list
wp harbor license validate
wp harbor catalog refresh
```

The `harbor` parent command is project-specific, following WP-CLI convention of namespacing under the project name.

### Cron Hooks

Prefix: `lw_harbor_`

```php
'lw_harbor_data_refresh'
```

### Error Codes (PHP)

Prefix: `lw-harbor-`

```php
'lw-harbor-feature-not-found'
'lw-harbor-invalid-key'
'lw-harbor-store-failed'
```

### CSS Scope and DOM

```html
<div id="lw-harbor-root" class="lw-harbor"></div>
```

The `.lw-harbor` class is the Tailwind CSS scope boundary. A PostCSS plugin (`scopeToLwHarbor`) prefixes all generated selectors with `.lw-harbor` to prevent style conflicts with the WordPress admin.

### Script Handles

Prefix: `lw-harbor-`

```php
wp_enqueue_script( 'lw-harbor-ui', ... );
wp_enqueue_style( 'lw-harbor-ui', ... );
wp_enqueue_script( 'lw-harbor-notice-dismiss', ... );
```

### JavaScript and TypeScript

<!-- markdownlint-disable MD060 -->

| Identifier         | Convention                              |
| ------------------ | --------------------------------------- |
| Error class        | `LiquidError` (file: `liquid-error.ts`) |
| Store name         | `'lw'`                                  |
| Store registration | `registerHarborStore`                   |
| Store import alias | `harborStore`                           |
| REST paths         | `'/liquidweb/harbor/v1/...'`                   |
| Docblock package   | `@package LiquidWeb\Harbor`             |

<!-- markdownlint-enable MD060 -->

### Error Codes (JS)

The `ErrorCode` enum values in TypeScript do not carry a prefix. They are scoped by the enum itself.

```typescript
ErrorCode.FeaturesFetchFailed; // 'features-fetch-failed'
ErrorCode.LicenseStoreFailed; // 'license-store-failed'
```

### Version Tags

The library version starts at `0.0.1`. All `@since` tags for new code use `@since 1.0.0`.

## Summary

<!-- markdownlint-disable MD060 -->

| Scope                   | Prefix             | Separator | Example                             |
| ----------------------- | ------------------ | --------- | ----------------------------------- |
| PHP namespace           | `LiquidWeb\Harbor` | `\`       | `LiquidWeb\Harbor\Features\Manager` |
| Hooks                   | `lw-harbor`        | `/`       | `lw-harbor/catalog/fetched`         |
| Options/meta/cache/cron | `lw_harbor`        | `_`       | `lw_harbor_unified_license_key`     |
| Container keys          | `lw-harbor`        | `.`       | `lw-harbor.admin-views.path`        |
| REST namespace          | `liquidweb`        | `/`       | `liquidweb/harbor/v1/features`             |
| Global functions        | `lw_harbor`        | `_`       | `lw_harbor_is_feature_enabled()`    |
| WP-CLI                  | `harbor`           | ` `       | `wp harbor feature list`            |
| Error codes (PHP)       | `lw-harbor`        | `-`       | `lw-harbor-feature-not-found`       |
| CSS/DOM                 | `lw-harbor`        | `-`       | `.lw-harbor`, `#lw-harbor-root`     |
| Script handles          | `lw-harbor`        | `-`       | `lw-harbor-ui`                      |

<!-- markdownlint-enable MD060 -->
