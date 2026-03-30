# Legacy License Endpoints

All endpoints require the `manage_options` capability.

Legacy licenses are per-plugin license keys from the old StellarWP Uplink system. Harbor surfaces them so the admin UI can prompt users to migrate to the unified `LWSW-` key.

## GET /liquidweb/harbor/v1/legacy-licenses

Returns all legacy per-plugin licenses discovered on the site. Plugins contribute their legacy license data via the `lw-harbor/legacy_licenses` filter (see [Integration Guide](../../guides/integration.md)).

### Response (200)

```json
[
  {
    "key": "abc123-def456-...",
    "slug": "give",
    "name": "GiveWP",
    "product": "givewp",
    "is_active": true,
    "page_url": "https://example.com/wp-admin/edit.php?post_type=give_forms&page=give-settings&tab=licenses",
    "expires_at": "2026-12-31"
  }
]
```

When no legacy licenses exist, returns an empty array `[]`.

| Field        | Type    | Description                                                |
| ------------ | ------- | ---------------------------------------------------------- |
| `key`        | string  | The legacy license key                                     |
| `slug`       | string  | Plugin slug                                                |
| `name`       | string  | Plugin display name                                        |
| `product`    | string  | Product family name                                        |
| `is_active`  | boolean | Whether the legacy license is currently active             |
| `page_url`   | string  | URL to the plugin's license management page, can be empty  |
| `expires_at` | string  | License expiration date                                    |
