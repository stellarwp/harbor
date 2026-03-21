# WP-CLI Commands

Harbor registers WP-CLI commands automatically when WP-CLI is present. No additional setup is needed.

## Command Reference

### `wp lw license`

Manage the unified license key.

| Command    | Usage                           | Description                                               |
| ---------- | ------------------------------- | --------------------------------------------------------- |
| `get`      | `wp lw license get`             | Show the current license key and associated products      |
| `set`      | `wp lw license set <key>`       | Validate and store a license key                          |
| `lookup`   | `wp lw license lookup <key>`    | Look up products for a key without storing it             |
| `validate` | `wp lw license validate <slug>` | Validate a product on this domain (may consume a seat)    |
| `delete`   | `wp lw license delete`          | Delete the stored unified license key                     |
| `legacy`   | `wp lw license legacy`          | List legacy per-plugin licenses from all Harbor instances |

### `wp lw catalog`

Manage the product catalog.

| Command    | Usage                           | Description                                       |
| ---------- | ------------------------------- | ------------------------------------------------- |
| `list`     | `wp lw catalog list`            | List all products in the catalog                  |
| `tiers`    | `wp lw catalog tiers <slug>`    | Show tiers for a specific product                 |
| `features` | `wp lw catalog features <slug>` | Show features for a specific product              |
| `refresh`  | `wp lw catalog refresh`         | Force refresh the catalog from the API            |
| `status`   | `wp lw catalog status`          | Show when the catalog was last fetched and errors |
| `delete`   | `wp lw catalog delete`          | Delete the cached catalog                         |

### `wp lw feature`

Manage Harbor features.

| Command      | Usage                             | Description                                       |
| ------------ | --------------------------------- | ------------------------------------------------- |
| `list`       | `wp lw feature list`              | List features with optional filters               |
| `get`        | `wp lw feature get <slug>`        | Show detailed information for a single feature    |
| `is-enabled` | `wp lw feature is-enabled <slug>` | Check if a feature is enabled (exit code 0 = yes) |
| `enable`     | `wp lw feature enable <slug>`     | Enable a feature                                  |
| `disable`    | `wp lw feature disable <slug>`    | Disable a feature                                 |
| `update`     | `wp lw feature update <slug>`     | Update a feature to the latest version            |

## License Commands

### get

Shows the current license key and associated products.

```bash
wp lw license get [--fields=<fields>] [--format=<format>]
```

**Default fields:** `product_slug, tier, status, expires, site_limit, active_count`

**Available fields:** `product_slug`, `tier`, `pending_tier`, `status`, `expires`, `site_limit`, `active_count`, `over_limit`, `installed_here`, `validation_status`, `is_valid`

**Examples:**

```bash
wp lw license get
wp lw license get --format=json
```

### set

Validates and stores a license key. Does not activate any product or consume a seat.

```bash
wp lw license set <key> [--network] [--fields=<fields>] [--format=<format>]
```

| Option      | Description                               |
| ----------- | ----------------------------------------- |
| `<key>`     | The license key (must start with `LWSW-`) |
| `--network` | Store at the network level (multisite)    |

**Examples:**

```bash
wp lw license set LWSW-abcdef-123456
wp lw license set LWSW-abcdef-123456 --network
```

### lookup

Looks up products for a key without storing it.

```bash
wp lw license lookup <key> [--fields=<fields>] [--format=<format>]
```

**Examples:**

```bash
wp lw license lookup LWSW-abcdef-123456
```

### validate

Validates a product on this domain using the stored license key. This may consume an activation seat.

```bash
wp lw license validate <product_slug>
```

**Examples:**

```bash
wp lw license validate kadence
```

### delete

Deletes the stored unified license key. Does not free any activation seats on the licensing service.

```bash
wp lw license delete [--network]
```

| Option      | Description                               |
| ----------- | ----------------------------------------- |
| `--network` | Delete from the network level (multisite) |

**Examples:**

```bash
wp lw license delete
wp lw license delete --network
```

### legacy

Lists legacy per-plugin licenses discovered across all Harbor instances. Read-only view of old-style keys stored individually by each plugin before unified licensing.

```bash
wp lw license legacy [--fields=<fields>] [--format=<format>]
```

**Default fields:** `slug, name, product, key, status, expires_at`

**Available fields:** `slug`, `name`, `product`, `key`, `status`, `page_url`, `expires_at`

**Examples:**

```bash
wp lw license legacy
wp lw license legacy --format=json
```

## Catalog Commands

### list

Lists all products in the catalog.

```bash
wp lw catalog list [--format=<format>]
```

**Default fields:** `product_slug, tiers, features`

**Examples:**

```bash
wp lw catalog list
wp lw catalog list --format=json
```

### tiers

Shows tiers for a specific product.

```bash
wp lw catalog tiers <product_slug> [--fields=<fields>] [--format=<format>]
```

**Default fields:** `slug, name, rank, purchase_url`

**Examples:**

```bash
wp lw catalog tiers kadence
wp lw catalog tiers kadence --format=json
```

### features

Shows features for a specific product.

```bash
wp lw catalog features <product_slug> [--fields=<fields>] [--format=<format>]
```

**Default fields:** `feature_slug, type, minimum_tier, name, category`

**Available fields:** `feature_slug`, `type`, `minimum_tier`, `name`, `description`, `category`, `plugin_file`, `is_dot_org`, `download_url`, `version`, `authors`, `documentation_url`

**Examples:**

```bash
wp lw catalog features kadence
wp lw catalog features kadence --format=json
```

### refresh

Force refreshes the catalog from the API, then displays the resulting product list.

```bash
wp lw catalog refresh [--format=<format>]
```

**Examples:**

```bash
wp lw catalog refresh
```

### status

Shows when the catalog was last fetched and any errors.

```bash
wp lw catalog status
```

**Examples:**

```bash
wp lw catalog status
```

### delete

Deletes the cached catalog. The next request for the catalog will fetch fresh data from the API.

```bash
wp lw catalog delete
```

**Examples:**

```bash
wp lw catalog delete
```

## Feature Commands

### list

Lists features with optional filters.

```bash
wp lw feature list [--product=<product>] [--tier=<tier>] [--available=<bool>] [--type=<type>] [--fields=<fields>] [--format=<format>]
```

**Options:**

| Option                | Description                                                      |
| --------------------- | ---------------------------------------------------------------- |
| `--product=<product>` | Filter by product (e.g. `kadence`)                               |
| `--tier=<tier>`       | Filter by tier (e.g. `Tier 1`)                                   |
| `--available=<bool>`  | Filter by availability (`true` or `false`)                       |
| `--type=<type>`       | Filter by type (`flag`, `plugin`, `theme`)                       |
| `--fields=<fields>`   | Comma-separated field list                                       |
| `--format=<format>`   | Output format: `table` (default), `json`, `csv`, `yaml`, `count` |

**Default fields:** `slug, name, type, product, is_available, is_enabled`

**Available fields:**

- All types: `slug`, `name`, `description`, `type`, `product`, `tier`, `is_available`, `is_enabled`, `documentation_url`
- Plugin and Theme: `installed_version`, `released_at`, `authors`, `is_dot_org`
- Plugin only: `plugin_file`

**Examples:**

```bash
# Table output (default)
wp lw feature list

# JSON for scripting
wp lw feature list --format=json

# Available flag features only
wp lw feature list --type=flag --available=true

# Count features in a product
wp lw feature list --product=kadence --format=count

# Show plugin-specific fields
wp lw feature list --type=plugin --fields=slug,plugin_file,authors,is_dot_org
```

### get

Shows detailed information for a single feature.

```bash
wp lw feature get <slug> [--fields=<fields>] [--format=<format>]
```

**Examples:**

```bash
wp lw feature get my-feature
wp lw feature get my-feature --format=json
```

### is-enabled

Checks whether a feature is currently enabled. Exits with code 0 if enabled, 1 if not.

```bash
wp lw feature is-enabled <slug>
```

**Examples:**

```bash
# Check in a script
if wp lw feature is-enabled my-feature; then
  echo "Feature is enabled"
fi
```

### enable

Enables a feature.

```bash
wp lw feature enable <slug>
```

**Examples:**

```bash
wp lw feature enable my-feature
```

### disable

Disables a feature.

```bash
wp lw feature disable <slug>
```

**Examples:**

```bash
wp lw feature disable my-feature
```

### update

Updates a feature to the latest available version. Only applies to plugin and theme features — flag features do not support updates.

```bash
wp lw feature update <slug>
```

**Examples:**

```bash
wp lw feature update my-feature
```

## Scripting Patterns

### JSON piping

```bash
# Get all feature slugs
wp lw feature list --format=json | jq -r '.[].slug'

# Get enabled features
wp lw feature list --format=json | jq '[.[] | select(.is_enabled == "true")]'

# Get legacy license keys
wp lw license legacy --format=json | jq -r '.[].key'
```

### Conditional logic

```bash
if wp lw feature is-enabled my-feature; then
  echo "my-feature is enabled"
else
  wp lw feature enable my-feature
fi
```

### Batch operations

```bash
# Enable all available flag features
for slug in $(wp lw feature list --type=flag --available=true --format=json | jq -r '.[].slug'); do
  wp lw feature enable "$slug"
done
```

## Cross-Instance Safety

When multiple vendor-prefixed copies of Harbor are active, only the highest version registers CLI commands. This uses the same `Version::should_handle()` mechanism as the REST API routes.
