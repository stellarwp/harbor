# Feature Endpoints

All endpoints require the `manage_options` capability.

Features are the resolved join of [Catalog](../../subsystems/catalog.md) and [Licensing](../../subsystems/licensing.md) data. The response shape varies by feature type (`plugin`, `theme`, `flag`). See [Features: Resolved Feature Shape](../../subsystems/features.md#resolved-feature-shape) for the complete field reference.

## GET /liquidweb/harbor/v1/features

Lists all resolved features with optional filters.

### Parameters

| Parameter   | Type    | Required | Description                                      |
| ----------- | ------- | -------- | ------------------------------------------------ |
| `product`   | string  | no       | Filter by product slug                           |
| `tier`      | string  | no       | Filter by tier slug                              |
| `available` | boolean | no       | Filter by availability                           |
| `type`      | string  | no       | Filter by feature type (`plugin`/`theme`/`flag`) |

### Response (200)

```json
[
  {
    "slug": "kadence-blocks-pro",
    "name": "Kadence Blocks Pro",
    "description": "Premium blocks for the WordPress editor.",
    "product": "kadence",
    "tier": "kadence-basic",
    "type": "plugin",
    "is_available": true,
    "in_catalog_tier": true,
    "is_enabled": true,
    "documentation_url": "https://...",
    "plugin_file": "kadence-blocks-pro/kadence-blocks-pro.php",
    "released_at": "2026-01-15T00:00:00Z",
    "version": "2.6.1",
    "changelog": "<p>Bug fixes and improvements.</p>",
    "authors": ["Suspended Starter Fish"],
    "is_dot_org": false,
    "installed_version": "2.6.0",
    "update_version": "2.6.1"
  },
  {
    "slug": "kadence-ai",
    "name": "Kadence AI",
    "description": "AI-powered design assistant.",
    "product": "kadence",
    "tier": "kadence-pro",
    "type": "flag",
    "is_available": false,
    "in_catalog_tier": false,
    "is_enabled": false,
    "documentation_url": "https://..."
  }
]
```

## GET /liquidweb/harbor/v1/features/{slug}

Returns a single feature by slug.

### Response (200)

Returns the feature object (same shape as the list items above).

### Errors

| HTTP | Code                          | Meaning              |
| ---- | ----------------------------- | -------------------- |
| 404  | `lw-harbor-feature-not-found` | Feature slug unknown |

## POST /liquidweb/harbor/v1/features/{slug}/enable

Enables a feature. For plugins and themes, this installs (if needed) and activates. For flags, it toggles the capability on.

### Response (200)

Returns the updated feature object.

### Errors

| HTTP | Code                                       | Meaning                                             |
| ---- | ------------------------------------------ | --------------------------------------------------- |
| 400  | `lw-harbor-feature-type-mismatch`          | Feature type not supported by the resolved strategy |
| 403  | `lw-harbor-capability-revoked`             | Feature capability individually revoked on license  |
| 409  | `lw-harbor-install-locked`                 | Concurrent install already in progress              |
| 409  | `lw-harbor-plugin-ownership-mismatch`      | Installed plugin author doesn't match expected      |
| 409  | `lw-harbor-theme-ownership-mismatch`       | Installed theme author doesn't match expected       |
| 422  | `lw-harbor-requirements-not-met`           | PHP or WordPress version too low                    |
| 422  | `lw-harbor-install-failed`                 | Installation failed                                 |
| 422  | `lw-harbor-activation-fatal`               | Fatal PHP error during activation                   |
| 422  | `lw-harbor-activation-failed`              | Activation did not take effect                      |
| 422  | `lw-harbor-plugin-not-found-after-install` | Plugin file missing after install                   |
| 422  | `lw-harbor-theme-not-found-after-install`  | Theme missing after install                         |
| 422  | `lw-harbor-download-link-missing`          | No download URL from plugins_api/themes_api         |
| 422  | `lw-harbor-feature-enable-failed`          | Enable failed (strategy exception)                  |
| 422  | `lw-harbor-unknown-feature-type`           | No registered strategy for this feature type        |
| 502  | `lw-harbor-plugins-api-failed`             | WordPress plugins_api() returned an error           |
| 502  | `lw-harbor-themes-api-failed`              | WordPress themes_api() returned an error            |
| 502  | `lw-harbor-feature-request-failed`         | Upstream feature API error                          |

## POST /liquidweb/harbor/v1/features/{slug}/disable

Disables a feature. For plugins, deactivates the plugin. For themes, returns an error if the theme is active. For flags, toggles the capability off.

### Response (200)

Returns the updated feature object.

### Errors

| HTTP | Code                               | Meaning                             |
| ---- | ---------------------------------- | ----------------------------------- |
| 409  | `lw-harbor-theme-is-active`        | Cannot disable the active theme     |
| 409  | `lw-harbor-theme-delete-required`  | Theme must be deleted manually      |
| 409  | `lw-harbor-deactivation-failed`    | Deactivation did not take effect    |
| 422  | `lw-harbor-feature-disable-failed` | Disable failed (strategy exception) |

## POST /liquidweb/harbor/v1/features/{slug}/update

Triggers an update for an installable feature. Flag features do not support updates.

### Response (200)

Returns the updated feature object.

### Errors

| HTTP | Code                              | Meaning                              |
| ---- | --------------------------------- | ------------------------------------ |
| 422  | `lw-harbor-feature-not-active`    | Feature is not currently installed   |
| 422  | `lw-harbor-update-not-supported`  | Feature type doesn't support updates |
| 422  | `lw-harbor-no-update-available`   | No pending update for this feature   |
| 422  | `lw-harbor-update-failed`         | Update failed                        |
