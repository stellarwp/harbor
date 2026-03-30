# License Endpoints

All endpoints require the `manage_options` capability.

See [Licensing](../../subsystems/licensing.md) for the product entry data model, validation statuses, and key discovery workflows.

## GET /liquidweb/harbor/v1/license

Returns the stored unified license key and its associated products. Products come from the cached `Product_Collection` (fetched from the Licensing API). When no key is stored, `products` is an empty array.

### Response (200)

```json
{
  "key": "LWSW-...",
  "products": [
    {
      "product_slug": "give",
      "tier": "give-pro",
      "status": "active",
      "expires": "2026-12-31 00:00:00",
      "activations": {
        "site_limit": 0,
        "active_count": 1,
        "over_limit": false,
        "domains": ["example.com"]
      },
      "capabilities": ["give-recurring", "give-fee-recovery"],
      "activated_here": true,
      "validation_status": "valid",
      "is_valid": true
    }
  ]
}
```

The `activated_here`, `validation_status`, and `is_valid` fields are only present when the product has been validated on this domain. The `capabilities` array contains the feature slugs granted by this entitlement.

When no key exists, returns `{ "key": null, "products": [] }`.

## GET /liquidweb/harbor/v1/license/{key}

Looks up the products for a license key without storing it. Useful for previewing what a key covers before committing.

### Response (200)

Same shape as `GET /license` above, using the provided key instead of the stored one.

### Errors

| HTTP | Code                          | Meaning                            |
| ---- | ----------------------------- | ---------------------------------- |
| 400  | (validation)                  | Key format invalid (no `LWSW-`)    |
| 400  | `lw-harbor-invalid-key`       | Key not recognized by API          |
| 502  | `lw-harbor-invalid-response`  | Upstream API returned bad response |

## POST /liquidweb/harbor/v1/license

Validates a license key against the Licensing API and stores it. Verifies the key is recognized (has products) and caches the product list, but does not activate any product or consume a seat.

### Parameters

| Parameter | Type    | Required | Description                             |
| --------- | ------- | -------- | --------------------------------------- |
| `key`     | string  | yes      | License key (must have `LWSW-` prefix)  |
| `network` | boolean | no       | Store at network level (multisite only) |

### Response (200)

Returns the same `{ key, products }` shape as `GET /license`.

### Errors

| HTTP | Code                          | Meaning                            |
| ---- | ----------------------------- | ---------------------------------- |
| 400  | (validation)                  | Missing key or invalid format      |
| 400  | `lw-harbor-invalid-key`       | Key not recognized by API          |
| 500  | `lw-harbor-store-failed`      | Key could not be persisted         |
| 502  | `lw-harbor-invalid-response`  | Upstream API returned bad response |

## DELETE /liquidweb/harbor/v1/license

Removes the locally stored license key. Does not free any activation seats on the licensing service.

### Parameters

| Parameter | Type    | Required | Description                                |
| --------- | ------- | -------- | ------------------------------------------ |
| `network` | boolean | no       | Delete from network level (multisite only) |

### Response

Returns `204 No Content` with no body.
