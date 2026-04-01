# LiquidWeb Harbor

A PHP library bundled by Liquid Web WordPress plugins to handle licensing, updates, and feature management. Each Liquid Web plugin ships its own vendor-prefixed copy via Strauss. Multiple copies coexist on a single WordPress site and negotiate leadership internally.

We are developing version 1. It is not released. Do not worry about backward compatibility or breaking changes. When something needs to change, refactor to fit. Do not add shims, aliases, or deprecation layers.

See `docs/harbor.md` for the architecture overview. Subsystem docs live in `docs/subsystems/`, architecture docs in `docs/architecture/`, API references in `docs/api/`, and guides in `docs/guides/`.

## Active code

The subsystems live in these directories. This is where active development happens:

- `src/Harbor/Catalog/` - Product catalog from the Commerce Portal (products, tiers, features)
- `src/Harbor/Features/` - Feature resolution (joins catalog + licensing), strategies, Manager
- `src/Harbor/Licensing/` - Unified license key management, validation, product registry
- `src/Harbor/API/REST/V1/` - WordPress REST endpoints for the above
- `src/Harbor/Legacy/` - Adapter for reading old per-plugin license data
- `src/Harbor/Admin/Feature_Manager_Page.php` - Admin page that hosts the React app
- `src/Harbor/Utils/` - Shared utilities (Collection, Version, Cast, License_Key)
- `resources/js/` - React frontend (TypeScript, Tailwind, Zustand)

## Shared infrastructure

- `src/Harbor/Config.php` - Container setup, hook prefix, storage driver config
- `src/Harbor/Contracts/` - Abstract_Provider base class
- `src/Harbor/Site/` - Domain and environment data
- `src/Harbor/View/` - Template rendering
- `src/Harbor/Harbor.php` - Bootstrap and provider registration
- `src/Harbor/Register.php` - Plugin/service registration entry point
- `src/Harbor/Auth/` - Token management for OAuth (we actually need to answer this question)

## Testing

Tests use Codeception with `slic` for containerized WordPress test execution. See `docs/guides/testing.md`.

Fixture data lives in `tests/_data/`. The catalog and licensing fixture files are working prototypes, not finalized API contracts.

## PHP version

The minimum PHP version is 7.4 (see `composer.json`). Do not use language features from PHP 8.0+ (named arguments, union types, match expressions, constructor promotion, etc.).

## Debugging

All debug logging goes through the `With_Debugging` trait (`src/Harbor/Traits/With_Debugging.php`). Never call `error_log()` directly — use `debug_log()`, `debug_log_throwable()`, or `debug_log_wp_error()` instead. Since it's a trait, standalone global functions that aren't inside a class can't use it — route those through a class that uses the trait (see `Global_Function_Registry` for the pattern).

## DI container registration

**Prefer auto-wiring.** When all constructor parameters are type-hinted with classes already registered in the container, pass the class string as both arguments and let DI52 resolve dependencies via reflection:

```php
$this->container->singleton( Some_Service::class );
```

**Use a closure only when necessary** — e.g. post-construction setup is required, a non-container scalar must be passed, or a factory/interface binding is needed. When a closure is required, it must close over `$this->container` rather than accepting a typed `ContainerInterface $c` parameter. DI52's `ClosureBuilder::build()` passes the raw `lucatume\DI52\Container` directly to closures — it does not resolve the parameter from the container. That inner class only implements PSR-11's `Psr\Container\ContainerInterface`, not `StellarWP\ContainerContract\ContainerInterface`, so a typed parameter causes a `TypeError` when the plugin uses a wrapper-pattern container.

```php
// Correct — close over $this->container
$this->container->singleton(
    Some_Service::class,
    function () {
        $service = new Some_Service( $this->container->get( Dep::class ) );
        $service->setup();
        return $service;
    }
);

// Wrong — do not accept ContainerInterface as a parameter
$this->container->singleton(
    Some_Service::class,
    static function ( ContainerInterface $c ) {
        return new Some_Service( $c->get( Dep::class ) );
    }
);
```

`$this->container` is always set to `Config::get_container()` in `Abstract_Provider::__construct()`, so it is always the correct Harbor container.

## Key principles

- One unified `LWSW-` license key per site, shared by all products
- A product is a brand family (Kadence, GiveWP, etc.), not a plugin
- Features are the resolved join of catalog + licensing data, not a third data source
- The `Licensing_Client` and `Catalog_Client` contracts exist so the backend can be swapped without affecting the rest of the system
