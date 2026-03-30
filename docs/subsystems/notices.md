# Notices

Harbor uses a lightweight notice system to display admin notices in WordPress. The system has two layers: a general-purpose `Notice` model and controller for rendering any admin notice, and a `License_Notice_Handler` that uses them to display consolidated warnings about inactive legacy licenses.

## The Notice model

A `Notice` is a simple DTO representing a single WordPress admin notice. It requires a type and a message; everything else is optional.

### Types

| Constant          | Value       | WordPress class    |
| ----------------- | ----------- | ------------------ |
| `Notice::INFO`    | `'info'`    | `notice-info`      |
| `Notice::SUCCESS` | `'success'` | `notice-success`   |
| `Notice::WARNING` | `'warning'` | `notice-warning`   |
| `Notice::ERROR`   | `'error'`   | `notice-error`     |

### Properties

| Property       | Type   | Default | Purpose                                                    |
| -------------- | ------ | ------- | ---------------------------------------------------------- |
| `$type`        | string | —       | One of the four type constants (required)                  |
| `$message`     | string | —       | Already-translated message to display (required, non-empty)|
| `$dismissible` | bool   | false   | Adds `is-dismissible` class and a dismiss button           |
| `$alt`         | bool   | false   | Adds `notice-alt` class for alternate styling              |
| `$large`       | bool   | false   | Adds `notice-large` class for larger text                  |
| `$id`          | string | `''`    | Unique ID for persistent dismissal tracking                |

The constructor validates that `$type` is one of the allowed types and that `$message` is non-empty, throwing `InvalidArgumentException` otherwise.

## Rendering

`Notice_Controller` renders a notice through the `admin/notice` view template. It builds CSS classes from the notice properties, sanitizes the ID and classes, and passes the message through `wp_kses()` with a restricted HTML allowlist.

### HTML allowlist

Messages can contain these elements and nothing else:

| Element    | Allowed attributes              |
| ---------- | ------------------------------- |
| `<a>`      | `href`, `title`, `target`, `rel`|
| `<br>`     | —                               |
| `<code>`   | —                               |
| `<em>`     | —                               |
| `<pre>`    | —                               |
| `<span>`   | —                               |
| `<strong>` | —                               |

Allowed URL protocols: `http`, `https`, `mailto`.

### View template output

The template (`src/views/admin/notice.php`) produces:

```html
<div class="notice notice-{type} [is-dismissible] [notice-alt] [notice-large]"
     [data-lw-harbor-notice-id="{id}"]>
    <p>{sanitized message}</p>
</div>
```

The `data-lw-harbor-notice-id` attribute is only present when `$id` is set. It is used by the dismiss JavaScript to identify which notice was closed.

## Creating and rendering a notice

```php
use LiquidWeb\Harbor\Notice\Notice;
use LiquidWeb\Harbor\Notice\Notice_Controller;

$notice = new Notice(
    Notice::WARNING,
    __( 'Your license expires in 3 days. <a href="https://example.com">Renew now</a>.', 'harbor' ),
    true  // dismissible
);

$controller->render( $notice->to_array() );
```

The controller is registered in the container and can be resolved from it. In practice, most notice rendering goes through the `License_Notice_Handler` described below.

## Legacy license notices

`License_Notice_Handler` displays consolidated admin notices when legacy (pre-unified) licenses are inactive. It is the primary consumer of the notice system.

### What it does

On every admin page load, the handler checks for inactive legacy licenses and displays one error notice per product. If a product has three inactive add-on licenses, the user sees a single notice for that product with the count, not three separate notices.

### Guards

The handler applies several checks before rendering anything:

1. **Permissions** — returns early if the current user cannot `manage_options`.
2. **Leadership** — returns early if `Version::should_handle('legacy_license_notices')` returns false. This ensures only the highest-version Harbor instance displays notices, preventing duplicates when multiple plugins bundle Harbor.
3. **No inactive licenses** — returns early if `License_Repository::all_inactive()` is empty.
4. **Already covered** — skips any license whose product is already covered by a StellarWP v3 unified feature (checked via `lw_harbor_is_feature_available()`).
5. **Already dismissed** — skips notices the user has dismissed within the last 7 days.
6. **Page suppression** — skips rendering if the user is already on the page the notice would link to (the Feature Manager page or the product's own license page).

### Notice format

Each notice is rendered as `Notice::ERROR` with `dismissible: true` and an ID of `legacy-{product}`. The message uses WordPress i18n pluralization:

> You have 3 inactive Kadence licenses. Please [activate them](https://example.com) to receive critical updates and new features.

### Persistent dismissal

When a user clicks the dismiss button, the notice stays hidden for 7 days (the `DISMISS_TTL` constant, `7 * DAY_IN_SECONDS`). Dismissal state is stored in user meta under the `lw_harbor_dismissed_notices` key as a map of notice ID to expiry timestamp.

The dismiss flow:

1. `License_Notice_Handler` enqueues `notice-dismiss.js` whenever it renders at least one notice.
2. The script listens for clicks on `.notice-dismiss` buttons.
3. It reads the `data-lw-harbor-notice-id` attribute from the parent notice element.
4. It fetches the current user's meta via the WordPress REST API (`/wp/v2/users/me`), updates the dismissed-notices map with a new expiry timestamp, and PATCHes the user record.
5. On the next page load, `is_dismissed()` checks whether the stored timestamp is still in the future.

The user meta field is registered with `show_in_rest: true` so the JavaScript can read and write it through the standard WordPress REST API. No custom REST endpoint is needed for dismissal.

### Version gating

`Version::should_handle('legacy_license_notices')` calls `Version::is_highest()` to check whether this Harbor instance has the highest version among all loaded instances, then fires a `do_action` hook to claim the responsibility exclusively. This prevents duplicate notices when a site runs multiple Liquid Web plugins.

## Key files

- `src/Harbor/Notice/Notice.php` — the notice DTO
- `src/Harbor/Notice/Notice_Controller.php` — renders notices through the view template
- `src/views/admin/notice.php` — the notice HTML template
- `src/Harbor/Legacy/Notices/License_Notice_Handler.php` — consolidated legacy license notices
- `src/Harbor/Legacy/Notices/assets/js/notice-dismiss.js` — persistent dismissal via REST
- `src/Harbor/Legacy/Provider.php` — registers the handler and hooks it to `admin_notices`
