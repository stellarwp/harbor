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
                                    # the :{tier} half is omitted when unknown
```

`redirect_url` is percent-encoded, so its own query string does not leak into
the portal URL as separate params. `sku` is what lets the portal pre-select a
product and tier instead of dropping the user on an unfiltered list. Without a
tier the portal offers a picker limited to the activating domain, so a partial
`sku` narrows the choice rather than failing.

Harbor appends `lw-harbor-activated=1` to whatever return URL you supply. That
tag lives inside `redirect_url`, not at the top level — see below.

## The return trip refreshes your data automatically

Licensing data is cached. Without a refresh, a user who has just activated in
the portal comes back to a screen that still believes they are unlicensed: the
Activate button is still there, the feature they paid for is still gated.

You do not have to handle this. Harbor tags every return URL it builds and
watches for that tag on any admin screen. On the way back it refreshes the
license products and the catalog, strips the tag, and redirects — all on
`admin_init`, before your page renders. By the time your code runs,
`License_Repository` is current.

Consequences worth knowing:

- **Read licensing state at render time**, not from something cached earlier in
  the request. The refresh has already happened by then.
- **The URL the user lands on is not the one you supplied** — it briefly carries
  `lw-harbor-activated=1`, then redirects to your clean URL. Anything that
  fingerprints the query string should tolerate that.
- **Only one instance refreshes.** The handler is behind the same version
  leadership check as the rest of Harbor, so four active plugins using Harbor make
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
version's logic — do not build the URL from a Harbor class in your own bundled
copy, which may not be the one actually running.

```php
// Product-scoped, returning the user to your onboarding screen.
$href = lw_harbor_get_product_activation_url(
    'kadence',
    lw_harbor_get_product_tier( 'kadence' ),
    add_query_arg(
        [
            'page' => 'kadence-onboarding',
            'step' => 2,
        ],
        admin_url( 'admin.php' )
    )
);
```

| Function                                                                                     | Returns                                                                 |
| -------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `lw_harbor_get_product_activation_base_url( ?string $redirect_url )`                         | The portal subscriptions URL with referral, redirect, and domain params |
| `lw_harbor_get_product_activation_url( string $slug, ?string $tier, ?string $redirect_url )` | The same, plus `sku={slug}` and `:{tier}` when a tier is given          |
| `lw_harbor_is_product_licensed( string $slug )`                                              | Whether the stored license covers the product at all, activated or not  |
| `lw_harbor_get_product_tier( string $slug )`                                                 | The licensed tier, or `null` when absent or licensed at several         |

The URL builders return `null` when no Harbor instance is active, or when the URL
could not be built — treat that as "hide the button". Omit `$redirect_url` to fall
back to Harbor's Software Manager page. Pass your own whenever the user started
somewhere else — otherwise they will not come back to where they were.

```php
$href = lw_harbor_get_product_activation_base_url( $return_url );

if ( null === $href ) {
    return; // Nothing to offer.
}
```

### Do not look the tier up yourself

`$tier` is optional, and `lw_harbor_get_product_tier()` is the supported way to
find one. Pass its result straight through, including when it is `null`: an
unscoped `sku` sends the user to the portal's product and tier picker, still
scoped to the activating domain, which is the right screen when the license
covers the product at more than one tier.

Reaching into `License_Repository` or `Product_Entry` from your own bundled copy
to read a tier is the thing this API exists to replace. Those classes are
Strauss-prefixed per plugin, and only the highest-version copy refreshes the
catalog — so you would be reading the leader's data with your own, possibly
older, code.

```php
// Licensed but not yet activated here: the state worth prompting on.
if (
    lw_harbor_is_product_licensed( 'kadence' )
    && ! lw_harbor_is_product_license_active( 'kadence' )
) {
    $href = lw_harbor_get_product_activation_url(
        'kadence',
        lw_harbor_get_product_tier( 'kadence' ),
        $return_url
    );
}
```

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

Harbor does not ship a browser API for this. Build the URL in PHP and hand it to
your script, so the `sku` contract lives in exactly one place.

```php
wp_localize_script(
    'kadence-onboarding',
    'kadenceOnboarding',
    [
        'activationUrl' => lw_harbor_get_product_activation_url(
            'kadence',
            lw_harbor_get_product_tier( 'kadence' ),
            menu_page_url( 'kadence-onboarding', false )
        ),
    ]
);
```

```js
if ( kadenceOnboarding.activationUrl ) {
    // safe to link to
}
```

The function returns `null` when no Harbor instance is active, so a falsy value
is your signal to hide the control rather than render a dead link.

When the tier is chosen in the browser, localize one URL per tier and pick
between them client-side:

```php
$tiers = [];

foreach ( [ 'plus', 'pro' ] as $tier ) {
    $tiers[ $tier ] = lw_harbor_get_product_activation_url( 'kadence', $tier, $return_url );
}
```
