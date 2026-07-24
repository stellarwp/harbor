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

Harbor appends `lw-harbor-activated=1` to whatever return URL you supply. That
tag lives inside `redirect_url`, not at the top level — see below.

## The return trip refreshes your data automatically

Licensing data is cached. Without a refresh, a user who has just activated in
the portal comes back to a screen that still believes they are unlicensed: the
Activate button is still there, the feature they paid for is still gated.

You do not have to handle this. `Activation_Url` tags every return URL it
builds, and `Activation_Return` watches for that tag on any admin screen. On the
way back it refreshes the license products and the catalog, strips the tag, and
redirects — all on `admin_init`, before your page renders. By the time your code
runs, `License_Repository` is current.

Consequences worth knowing:

- **Read licensing state at render time**, not from something cached earlier in
  the request. The refresh has already happened by then.
- **The URL the user lands on is not the one you supplied** — it briefly carries
  `lw-harbor-activated=1`, then redirects to your clean URL. Anything that
  fingerprints the query string should tolerate that.
- **Only one instance refreshes.** The handler is behind the same version
  leadership check as the rest of Harbor, so four active Liquid Web plugins make
  one API call between them, not four.
- **It requires `manage_options`.** The tag rides on a URL your plugin owns, so
  it can land on a screen with no capability check of its own.
- **Failures are logged, not surfaced.** If the refresh fails the user still
  reaches your page, with stale data. They have just come back from activating
  and are looking at your screen, not a licensing one, so an error notice there
  would be noise they cannot act on.

## From PHP

Call the global functions. Like the rest of Harbor's public API they resolve to
the highest-version Harbor copy on the site, so you always get the loaded
version's logic — do not build `Activation_Url` from your own bundled copy, which
may not be the one actually running.

```php
// Product-scoped, returning the user to your onboarding screen.
$href = lw_harbor_get_product_activation_url(
    'kadence',
    'pro',
    admin_url( 'admin.php?page=kadence-onboarding&step=2' )
);
```

| Function                                                                                    | Returns                                                                 |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `lw_harbor_get_activation_url( ?string $redirect_url )`                                      | The portal subscriptions URL with referral, redirect, and domain params |
| `lw_harbor_get_product_activation_url( string $slug, string $tier, ?string $redirect_url )` | The same, plus `sku={slug}:{tier}`                                       |

Both return an empty string when no Harbor instance is active — treat that as
"hide the button". Omit `$redirect_url` to fall back to Harbor's Software Manager
page. Pass your own whenever the user started somewhere else — otherwise they
will not come back to where they were.

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
use LiquidWeb\Harbor\Portal\Activation_Script;

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
        'activationBaseUrl' => lw_harbor_get_activation_url(
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
