# Glossary

Definitions for the terms used throughout Harbor's documentation. Each entry explains what the term means, which subsystem owns it, and links to the doc that covers it in depth.

## Catalog

The complete, non-personalized definition of every Liquid Web product family: its tiers (ranked subscription levels) and its features (plugins and themes with minimum tier requirements). The catalog is provided by the Commerce Portal API and is the same for every site, regardless of what key the site has.

The catalog answers "what does this product offer?" It is the menu — it does not know what any given customer ordered. It has no knowledge of license keys, entitlements, or local activation state.

Harbor caches the catalog in a WordPress option (`lw_harbor_catalog_state`) via `Catalog_Repository`.

To see the catalog information on your site, run this command:

```
wp harbor catalog list
```

This will return the following:

| product_slug        | tiers | features |
|---------------------|-------|----------|
| the-events-calendar | 4     | 11       |
| kadence             | 4     | 29       |
| give                | 4     | 48       |
| learndash           | 3     | 26       |

To see the full catalog information on your site, run this command:

```
wp option get lw_harbor_catalog_state
```

## Entitlements

What a [unified license key](#unified-license-key) is allowed to use — the answer Licensing gives when a site asks "what does this key cover?" Licensing is the authority on entitlements; the [catalog](#catalog) only describes what products offer.

For each product on the key, entitlements include:

- whether the product is covered at all
- which tier the customer is on (e.g. `kadence-pro`, `give-basic`)
- [seat](#seats) limits (`site_limit` / `active_count`)
- which [capabilities](#feature-also-called-capability) (feature slugs) the key grants
- subscription status (`active`, `expired`, `suspended`, `cancelled`, etc.)

Feature resolution joins catalog structure with these entitlements to decide `is_available`. A missing entitlement for a product surfaces as the `no_entitlement` validation status.

See [Licensing](subsystems/licensing.md) and [Harbor: The Three Data Layers](harbor.md#the-three-data-layers).

Example:
To see in your site, run this command:

```
wp harbor license get
```

This will return the following:

| product_slug        | tier       | status | expires             | site_limit | active_count |
|---------------------|------------|--------|---------------------|------------|--------------|
| the-events-calendar | pro        | active | 2027-05-12 12:17:08 | 1          | 0            |
| kadence             | elite      | active | 2027-05-12 12:17:08 | 3          | 0            |
| kadence             | pro        | active | 2027-05-12 12:17:08 | 1          | 0            |
| give                | elite      | active | 2027-05-12 12:17:08 | 2          | 0            |
| give                | essentials | active | 2027-05-12 12:17:08 | 1          | 0            |
| the-events-calendar | elite      | active | 2027-05-12 12:17:08 | 1          | 0            |
| learndash           | elite      | active | 2027-05-12 12:17:08 | 1          | 0            |

## Feature (also called Capability)

A feature is an individual deliverable within a product family — an installable WordPress plugin, a WordPress theme, or an externally managed service. Examples: `kadence-blocks-pro` (Kadence Blocks Pro), `events-calendar-pro` (Events Calendar Pro), `service-central-pro` (Kadence Central Pro).

Crucially, **features are not a third data source**. A resolved feature is the computed join of catalog data (what exists, its metadata, its minimum tier) and licensing data (what this customer's key entitles them to), plus local state (whether it's enabled on this site).

The term **capability** refers to the same thing from the licensing side. The licensing response includes a `capabilities` array — the list of feature slugs the key actually grants. A feature is available to a customer if and only if its slug appears in this array. Capabilities are the authority on access; the catalog's tier structure exists for display and upsell, and overrides like promotional grants are expressed through capabilities alone.

Every feature has two independent states:

- **Available**: the feature's slug is in the license's `capabilities` array (or granted by a legacy license, or in the free tier when unlicensed).
- **Enabled**: the feature is actively turned on for this site (plugin activated, theme installed). A feature cannot be enabled without being available.

See [Features](subsystems/features.md).

To see the features in your site, run this command:

```
wp harbor feature list
```

This will return the following:

| slug                                 | name                             | type    | product | is_available | is_enabled |
|--------------------------------------|----------------------------------|---------|---------|--------------|------------|
| kadence-blocks                       | Kadence Blocks                   | plugin  | kadence | true         | true       |
| kadence                              | Kadence Theme                    | theme   | kadence | true         | true       |
| kadence-starter-templates            | Starter Templates                | plugin  | kadence | true         | false      |
| kadence-woocommerce-email-designer   | WooCommerce Email Designer       | plugin  | kadence | true         | false      |
| restrict-content                     | Kadence Memberships              | plugin  | kadence | true         | false      |
| solid-performance                    | Kadence Performance              | plugin  | kadence | true         | false      |
| better-wp-security                   | Solid Security                   | plugin  | kadence | true         | false      |
| ithemes-sync                         | Kadence Central                  | plugin  | kadence | true         | false      |
| wp-smtp                              | Kadence Mail                     | plugin  | kadence | true         | false      |
| kadence-blocks-pro                   | Kadence Blocks Pro               | plugin  | kadence | true         | true       |
| kadence-theme-pro                    | Theme Kit Pro                    | plugin  | kadence | true         | false      |
| kadence-creative-kit                 | Creative Kit                     | plugin  | kadence | true         | false      |
| kadence-custom-fonts                 | Kadence Custom Fonts             | plugin  | kadence | true         | false      |
| kadence-recaptcha                    | CAPTCHA                          | plugin  | kadence | true         | false      |
| kadence-reading-time                 | Kadence Reading Time             | plugin  | kadence | true         | false      |
| kadence-simple-share                 | Kadence Simple Share             | plugin  | kadence | true         | false      |
| kadence-galleries                    | Kadence Galleries                | plugin  | kadence | true         | false      |
| kadence-pattern-hub                  | Pattern Hub                      | plugin  | kadence | true         | false      |
| kadence-cloud-pages                  | Pattern Hub - Pages              | plugin  | kadence | true         | false      |
| kadence-pattern-hub-surecart-license | Pattern Hub - SureCart Licensing | plugin  | kadence | true         | false      |
| kadence-build-child-defaults         | Kadence Child Theme Builder      | plugin  | kadence | true         | false      |
| kadence-shop-kit                     | Shop Kit                         | plugin  | kadence | true         | false      |
| restrict-content-pro                 | Kadence Memberships Pro          | plugin  | kadence | true         | false      |
| ithemes-security-pro                 | Kadence Security Pro             | plugin  | kadence | true         | false      |
| solid-backups                        | Kadence Backups                  | plugin  | kadence | true         | false      |
| kadence-conversions                  | Kadence Conversions              | plugin  | kadence | true         | false      |
| kadence-insights                     | Kadence Insights (A/B Testing)   | plugin  | kadence | true         | false      |
| service-central-pro                  | Kadence Central Pro              | service | kadence | true         | true       |
| kadence-white-label                  | White Label                      | plugin  | kadence | true         | false      |

## Legacy License

A per-plugin license key from the old StellarWP Uplink system (v2/v3), predating the unified key. Each plugin managed its own key independently.

Harbor does not replace or validate legacy licenses — they continue through their existing per-resource path unchanged, and there is no automatic migration to the unified key. Harbor does three things with them:

1. **Discovers** them via the `lw-harbor/legacy_licenses` filter so the admin UI can display them and prompt users to migrate.
2. **Grants availability**: an active legacy license whose `slug` matches a catalog feature grants that feature availability even without a unified license (a fallback after the unified entitlement check).
3. **Authenticates downloads**: a matching active legacy key takes precedence over the unified key when building Herald download URLs for its specific slug.

Legacy entries only participate when their `key` is non-empty, `is_active` is `true`, and the reporting plugin has opted in with `use_for_updates = true`.

See [REST: Legacy Licenses](api/rest/legacy-licenses.md) and [Integration Guide](guides/integration.md).

## Unified License Key

The single `LWSW-`-prefixed license key shared by all Liquid Web products on a site. It replaces the old model where every plugin managed its own key. The key is the site's identity to the licensing system — presenting it to the Licensing API returns which products are entitled, what tier each is on, and which features (capabilities) are granted.

Rules of the model:

- **One key per site.** Stored in the `lw_harbor_unified_license_key` WordPress option (network-aware on multisite). All products share it; there is no multi-key support.
- **Two ways in**: embedded in a purchased product's `LWSW_KEY.php` license file, or typed into the admin UI by the user. A stored key always takes precedence over embedded keys.
- **Validated before storage**: `validate_and_store()` presents the key to the Licensing API first and only persists it if recognized.
- **No key means unlicensed**: the site makes no API calls and only free-tier features are available.

See [Unified License Key: System Design](architecture/unified-license-key-system-design.md) and [Licensing](subsystems/licensing.md).

## Portal

The **Commerce Portal** ([software.liquidweb.com](https://software.liquidweb.com/)) — the external Liquid Web service that serves the product catalog (see [Catalog](#catalog)) and provides the customer-facing management interface for a license.

In Harbor's architecture, the Portal plays two distinct roles:

1. **Data source**: the Commerce Portal API answers "what does each product offer?" — products, tiers, features, versions, changelogs. Not personalized; every site sees the same catalog.
2. **Administrative authority**: the only place a customer can perform privileged license operations — releasing seats, regenerating keys, managing subscriptions, upgrading tiers. Sites cannot do these things programmatically, and Portal never pushes state to sites — sites pull their own state from Licensing on a schedule.

The Portal is also the upsell target: when a site runs out of seats or a feature requires a higher tier, the UI links the customer to the Portal.

See [Portal](subsystems/portal.md) and [Unified License Key: System Design](architecture/unified-license-key-system-design.md#portal).

## Seats

The number of site activations a subscription allows. Each product entry in a licensing response carries a `site_limit` (maximum activations; `0` means unlimited) and an `active_count` (current activations).

- **Consumption**: a seat is consumed when a product is validated against a key on a domain for the first time. Re-validating an already-active product consumes nothing. Reading product state (`get_products()`) is always read-only.
- **Release**: seats can only be freed through the [Portal](#portal) by an authenticated customer — never programmatically from a site, to prevent abuse.
- **Exhaustion**: when all seats are consumed, validation returns `out_of_activations` and the product cannot activate. The customer must free an activation in the Portal or upgrade their plan. Seat counts sync to the site on the next periodic status check.

Seat management lives in the licensing layer; feature enable/disable never touches seats.

See [Licensing](subsystems/licensing.md) and [Unified License Key: System Design](architecture/unified-license-key-system-design.md).
