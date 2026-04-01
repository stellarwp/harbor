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
        "purchase_url": "https://..."
      },
      {
        "slug": "kadence-pro",
        "name": "Pro",
        "rank": 2,
        "purchase_url": "https://..."
      }
    ],
    "features": [
      {
        "feature_slug": "kadence-blocks-pro",
        "type": "plugin",
        "minimum_tier": "kadence-basic",
        "plugin_file": "kadence-blocks-pro/kadence-blocks-pro.php",
        "is_dot_org": false,
        "download_url": "https://...",
        "version": "2.6.1",
        "released_at": "2026-01-15T00:00:00Z",
        "changelog": "<p>Bug fixes and improvements.</p>",
        "name": "Kadence Blocks Pro",
        "description": "Premium blocks for the WordPress editor.",
        "category": "design",
        "authors": ["Suspended Starter Fish"],
        "documentation_url": "https://..."
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
