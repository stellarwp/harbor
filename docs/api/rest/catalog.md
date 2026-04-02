# Catalog Endpoints

All endpoints require the `manage_options` capability.

See [Catalog](../../subsystems/catalog.md) for the product, tier, and feature data models and field definitions.

## GET /liquidweb/harbor/v1/catalog

Returns the full product catalog. Each entry represents a product family with its tiers and features. The catalog is not license-specific — it describes everything available regardless of entitlements.

### Response (200)

```json
[
  {
    "product_slug": "kadence",
    "tiers": [
      {
        "slug": "kadence-basic",
        "name": "Basic",
        "rank": 1,
        "price": 14900,
        "currency": "USD",
        "features": ["Access to pro blocks", "Premium support"],
        "herald_slugs": ["kadence-basic-herald"]
      },
      {
        "slug": "kadence-pro",
        "name": "Pro",
        "rank": 2,
        "price": 19900,
        "currency": "USD",
        "features": ["All Basic features", "Shop Kit", "Priority support"],
        "herald_slugs": ["kadence-pro-herald"]
      }
    ],
    "features": [
      {
        "slug": "kadence-blocks-pro",
        "kind": "plugin",
        "minimum_tier": "kadence-basic",
        "plugin_file": "kadence-blocks-pro/kadence-blocks-pro.php",
        "wporg_slug": null,
        "download_url": "https://...",
        "version": "2.6.1",
        "release_date": "2026-01-15T00:00:00Z",
        "changelog": "<p>Bug fixes and improvements.</p>",
        "name": "Kadence Blocks Pro",
        "description": "Premium blocks for the WordPress editor.",
        "category": "design",
        "authors": ["Suspended Starter Fish"],
        "documentation_url": "https://...",
        "homepage": null
      }
    ]
  }
]
```

When the catalog has not been fetched yet, returns an empty array `[]`.

### Errors

| HTTP | Code                                  | Meaning                          |
| ---- | ------------------------------------- | -------------------------------- |
| 502  | `lw-harbor-catalog-invalid-response`  | Catalog API response was invalid |
