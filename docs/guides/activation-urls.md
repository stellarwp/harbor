# Activation URLs

An activation URL sends the user to the Liquid Web portal with enough context to
activate a product against the current site. Harbor builds these URLs so host
plugins do not each reimplement the portal's query string.

Use this when you need an "Activate" button outside Harbor's own Software
Manager page — for example on a plugin's onboarding screen.

## What the URL contains

```text
{portal_base_url}/subscriptions/
    ?portal-referral=plugin
    &redirect_url={where the portal returns the user}
    &domain={this site's domain}
    &sku={product_slug}:{tier}      # only on product-scoped URLs
```

`redirect_url` is percent-encoded, so its own query string does not leak into
the portal URL as separate params. `sku` is what lets the portal pre-select a
product and tier instead of dropping the user on an unfiltered list.

## From PHP

Resolve `Activation_Url` from the container.

```php
use LiquidWeb\Harbor\Portal\Activation_Url;

$activation_url = Config::get_container()->get( Activation_Url::class );

// Product-scoped, returning the user to your onboarding screen.
$href = $activation_url->for_product(
    'kadence',
    'pro',
    admin_url( 'admin.php?page=kadence-onboarding&step=2' )
);
```

| Method                                                             | Returns                                                                 |
| ------------------------------------------------------------------ | ----------------------------------------------------------------------- |
| `get_base( ?string $redirect_url )`                                | The portal subscriptions URL with referral, redirect, and domain params |
| `for_product( string $slug, string $tier, ?string $redirect_url )` | The same, plus `sku={slug}:{tier}`                                      |

Omit `$redirect_url` to fall back to Harbor's Software Manager page. Pass your
own whenever the user started somewhere else — otherwise they will not come
back to where they were.

### Getting the return URL right

Prefer your page's canonical address — the parent it is actually registered
under:

| How your page is registered                    | Canonical return URL              |
| ---------------------------------------------- | --------------------------------- |
| `add_menu_page()` (top level)                  | `admin.php?page={slug}`           |
| `add_submenu_page( 'options-general.php', … )` | `options-general.php?page={slug}` |
| `add_submenu_page( 'tools.php', … )`           | `tools.php?page={slug}`           |

WordPress will resolve an `admin.php?page={slug}` URL to a submenu page anyway,
so this is a consistency preference rather than a correctness requirement.
`menu_page_url( 'your-slug', false )` returns the canonical form without
hardcoding the parent.

The examples below assume a top-level menu.

## From JavaScript

Use this when the product or tier is chosen in the browser. If it is fixed at
render time, build the URL in PHP instead and skip the script entirely.

Harbor registers a dependency-free script exposing `window.lwHarbor`. Declare it
as a dependency:

```php
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Portal\Activation_Script;
use LiquidWeb\Harbor\Portal\Activation_Url;

$activation_url = Config::get_container()->get( Activation_Url::class );

wp_enqueue_script(
    'kadence-onboarding',
    $url . 'build/onboarding.js',
    [ Activation_Script::HANDLE ],
    $version,
    true
);

// The helper only appends sku, so pass it a base URL built in PHP.
wp_localize_script(
    'kadence-onboarding',
    'kadenceOnboarding',
    [
        'activationBaseUrl' => $activation_url->get_base(
            admin_url( 'admin.php?page=kadence-onboarding&step=2' )
        ),
    ]
);
```

Then in the browser:

```js
const href = window.lwHarbor.buildActivationUrl(
    kadenceOnboarding.activationBaseUrl,
    'kadence',
    selectedTier
);
```

This works from a bundled module or an inline `<script>` — no build step
required on the consuming side.

### Always feature-detect

Every active Harbor copy runs the registration code, but only the highest
version claims it. The API available at runtime is therefore the leader's, which
may be older than the copy your plugin ships.

```js
if ( window.lwHarbor?.buildActivationUrl ) {
    // safe to use
}
```

`window.lwHarbor.version` reports the version that actually registered the
script.

### Enqueue timing

You do not need to worry about hook priority. WordPress resolves script
dependencies when scripts are **printed**, not when they are enqueued, and
`admin_enqueue_scripts` always runs before `admin_print_scripts`. Harbor
registers during `admin_enqueue_scripts` (at priority `0`, defensively), so any
consumer enqueuing on that hook is in time regardless of its own priority.

Declaring the dependency before Harbor has registered it is therefore fine —
`wp_enqueue_script()` does not validate dependencies at call time.

### If your script does not load

WordPress silently refuses to print a script whose dependency is still
unregistered at print time — no error, no console warning. In practice that
means Harbor is not present on the request at all rather than a timing problem.
Check the handle exists:

```php
wp_script_is( Activation_Script::HANDLE, 'registered' );
```

The script is admin-only. It is not registered on the front end, so a dependency
declared on a front-end script will never resolve.

## Why the handle is not vendor-prefixed

`lw-harbor-activation` and `lwHarbor` are plain strings. Strauss rewrites class
names, not strings, so every Harbor copy on the site agrees on them — which is
what allows a single registration to serve every plugin. This is deliberate; do
not prefix them.
