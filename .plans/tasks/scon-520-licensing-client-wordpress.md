---
ticket: SCON-520
status: todo
url: https://stellarwp.atlassian.net/browse/SCON-520
---

# Adopt stellarwp/licensing-api-client-wordpress

## Problem

Harbor maintains its own `Licensing_Client` interface and `Http_Client` that manually build HTTP requests, parse responses, and handle errors against the v4 licensing API. The `stellarwp/licensing-api-client-wordpress` package already provides a tested client for the same API with a WordPress HTTP transport. Keeping the hand-rolled implementation means duplicating logic the package already owns and maintaining two separate PSR-18 stacks (Licensing and Catalog both pull in Symfony).

## Proposed solution

Wire up `stellarwp/licensing-api-client-wordpress` (already in `composer.json`) using the DI52 provider pattern from the package README. `Licensing\Provider` should register `WordPressHttpClient`, `Psr17Factory`, the PSR interface bindings, `Config`, `AuthState`, and `ApiVersion`, then bind `LicensingClientInterface` to `Api`. `License_Manager` should depend on `LicensingClientInterface` instead of the hand-rolled `Licensing_Client`. Remove `Licensing_Client` and its `Http_Client` once nothing depends on them.

Keep `Product_Entry` as Harbor's own DTO but update it to hydrate from the package's `CatalogEntry` type instead of from raw API response arrays.

Migrate the Catalog `Http_Client` from its PSR-18/Symfony stack to `WordPressHttpClient` as well. Once both subsystems are on the WordPress transport, drop `symfony/http-client` from `composer.json`.

Update `Fixture_Client` to implement `LicensingClientInterface` instead of the hand-rolled `Licensing_Client` so it can still be used as a test double for `License_Manager`. It should return the package's response types directly rather than hydrating `Product_Entry` objects internally.

Update tests to cover the new provider wiring and the updated `Product_Entry` hydration path. Remove or replace tests that were specific to the hand-rolled `Http_Client`. Update any docs that reference the old `Licensing_Client` contract or the Symfony transport.
