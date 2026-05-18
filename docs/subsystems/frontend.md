# Frontend

## Summary

The Harbor frontend is a React application rendered inside the WordPress admin. It provides the Software Manager page where users manage their unified license key and toggle features on and off. The app is built with TypeScript, Tailwind CSS, and `@wordpress/data` for state management.

PHP enqueues the bundle. React takes over a single mount point. All data flows through the `@wordpress/data` store, which talks to the [REST API](../api/rest/) endpoints served by the leader instance.

## Entry Point

`resources/js/index.tsx` is the webpack entry. It:

1. Registers the `@wordpress/data` store via `registerHarborStore()`.
2. Waits for `DOMContentLoaded`.
3. Calls `createRoot()` on `#lw-harbor-root`.

The mount point is rendered by `Feature_Manager_Page::render()` in PHP:

```html
<div class="wrap">
    <div id="lw-harbor-root" class="lw-harbor-ui"></div>
</div>
```

The `.lw-harbor-ui` class activates Tailwind CSS scoping (see [CSS Scoping](#css-scoping) below).

## Provider Nesting

`App.tsx` wraps the UI in five context providers and an error boundary:

```
ToastProvider
  ReloadBannerProvider
    FilterProvider
      ErrorModalProvider
        HarborDataProvider
          ErrorBoundary
            AppContent + Toaster
          ErrorModal          ← outside ErrorBoundary
```

The order matters:

- **ToastProvider** — toast notifications, consumed anywhere.
- **ReloadBannerProvider** — tracks whether enabled/disabled feature toggles require a page reload; powers `<ReloadBanner>` in the FilterBar row.
- **FilterProvider** — search query and product filter state for the feature list.
- **ErrorModalProvider** — collects `HarborError` instances; the `ErrorModal` renders them.
- **HarborDataProvider** — fires the four core resolvers (license, features, catalog, legacy licenses) and pushes resolver errors into the error modal. Exposes `isLoading` (all four resolvers settled) and `isLicenseLoading` (the license resolver alone). `AppContent` uses the latter to render the welcome screen as soon as we know there's no license, without waiting on catalog/features/legacy.
- **ErrorBoundary** — catches render crashes. `ErrorModal` sits outside it so a crash doesn't prevent the modal from opening.

## Routing

`AppContent` is the routing layer between the providers and the screens — pure routing, no inline visuals.

Three branches, evaluated in order:

1. A per-mount `hasEverHadLicenseRef` latch — once `hasLicense` has been `true` during this mount, render `AppShell` for the rest of the session.
2. Otherwise, if `isLicenseLoading` is true, render `AppLoader`.
3. Otherwise, render `WelcomeScreen`.

The latch mirrors the `hasEverResolvedRef` pattern in `harbor-data-context.tsx` and guards against license removal (via `LicensePanel`) or transient refresh failures bouncing the user back to the welcome screen.

`AppLoader` and `WelcomeScreen` only appear on a fresh page load with no key. Activation handoff is implicit: a successful `storeLicense` flips `hasLicense` to `true`, `AppContent` re-renders, the latch trips, and `AppShell` mounts. There is no explicit navigation call — the routing component reacts to store state.

## State Management

The store uses `@wordpress/data` with the Redux pattern, not Zustand.

### Store Registration

`resources/js/store/index.ts` calls `createReduxStore(STORE_NAME, config)` and exports a `registerHarborStore()` function. The store name constant is `'lw/harbor'`.

### State Shape

```typescript
interface State {
    features: {
        bySlug:      Record<string, Feature>;
        toggling:    Record<string, boolean>;
        updating:    Record<string, boolean>;
        errorBySlug: Record<string, HarborError>;
    };
    harborHosts: {
        basenames: string[];       // active Harbor-bundled plugin basenames
    };
    license: {
        license:      License;     // { key, products[], error }
        isStoring:    boolean;
        isDeleting:   boolean;
        isRefreshing: boolean;
        storeError:   HarborError | null;
        deleteError:  HarborError | null;
        refreshError: HarborError | null;
    };
    catalog: {
        byProductSlug: Record<string, ProductCatalog>;
    };
    legacyLicenses: {
        bySlug: Record<string, LegacyLicense>;
    };
}
```

### Resolvers

Resolvers fetch data from the REST API the first time a selector is called, then cache the result. Four core resolvers are fired by `HarborDataProvider`; a fifth ancillary resolver runs on demand.

| Resolver                  | Endpoint                                   | Populates                |
| ------------------------- | ------------------------------------------ | ------------------------ |
| `getFeatures`             | `GET /liquidweb/harbor/v1/features`        | `features.bySlug`        |
| `getLicenseKey`           | `GET /liquidweb/harbor/v1/license`         | `license.license`        |
| `getCatalog`              | `GET /liquidweb/harbor/v1/catalog`         | `catalog.byProductSlug`  |
| `getLegacyLicenses`       | `GET /liquidweb/harbor/v1/legacy-licenses` | `legacyLicenses.bySlug`  |
| `getHarborHostBasenames`  | `GET /liquidweb/harbor/v1/hosts`           | `harborHosts.basenames`  |

`getHarborHostBasenames` is consumed by `useFeatureRow` and is invalidated by `enableFeature` (activation may bootstrap a new Harbor host plugin). The other four are fired together by `HarborDataProvider`.

Derived selectors (e.g. `getFeature(slug)`, `getProductCatalog(slug)`) use `forwardResolver` / `forwardResolverWithoutArgs` to delegate to the parent resolver without re-fetching.

### Actions

Plain action creators (`receiveFeatures`, `receiveLicense`, `receiveCatalog`, `receiveLegacyLicenses`, `receiveHarborHosts`) populate the store from resolver responses.

Thunk action creators handle mutations:

| Action            | Endpoint                               | Effect                                    |
| ----------------- | -------------------------------------- | ----------------------------------------- |
| `enableFeature`   | `POST /features/{slug}/enable`         | Toggles a feature on                      |
| `disableFeature`  | `POST /features/{slug}/disable`        | Toggles a feature off                     |
| `updateFeature`   | `POST /features/{slug}/update`         | Updates to latest version                 |
| `storeLicense`    | `POST /license`                        | Activates a key, invalidates features     |
| `refreshLicense`  | `POST /license/refresh`                | Refreshes the license, invalidates features |
| `deleteLicense`   | `DELETE /license`                      | Removes the key, invalidates features     |
| `refreshCatalog`  | `POST /catalog/refresh`                | Re-fetches the catalog into the store     |

After `storeLicense`, `deleteLicense`, and `refreshLicense` succeed, the thunk calls `dispatch.invalidateResolution('getFeatures', [])` so the feature list refreshes with updated entitlements. `refreshLicense` additionally surfaces `result.error` from the response as a `HarborError`. After `enableFeature` succeeds, the thunk invalidates `getHarborHostBasenames` because activation may have bootstrapped a new Harbor host plugin. The `LicensePanel`'s Refresh button calls `refreshLicense` and `refreshCatalog` together via `Promise.all`.

### Selectors

Selectors are memoized with `createSelector` from `@wordpress/data`. Key selectors:

- **Features** — `getFeatures`, `getFeaturesByProduct`, `getFeature`, `isFeatureEnabled`, `isFeatureToggling`, `isFeatureUpdating`, `getFeatureError`, `getFeatureMismatchType`, `isAnyInstallableBusy`
- **Harbor hosts** — `getHarborHostBasenames`, `getEnabledHarborHostCount`
- **License** — `getLicenseKey`, `hasLicense`, `getLicenseProducts`, `getLicenseError`, `isLicenseStoring`, `isLicenseDeleting`, `isLicenseRefreshing`, `canModifyLicense`, `getStoreLicenseError`, `getDeleteLicenseError`, `getRefreshLicenseError`, `areAllProductsNotActivated`, `getUnactivatedLicenseProduct`
- **Catalog** — `getCatalog`, `getProductCatalog`, `getProductTiers`, `getCatalogTier`
- **Legacy** — `getLegacyLicenses`, `getLegacyLicenseBySlug`, `hasLegacyLicense`, `hasLegacyLicenses`, `hasUncoveredLegacyLicenses`, `getActiveLegacyLicense`, `getWithoutCancelledProducts`, `isProductUnifiedLicensed`, `isProductLicenseValid`, `hasActiveLegacyLicenseForProduct`

### useResolvableSelect

`useResolvableSelect` wraps `useSelect` to return resolution metadata alongside data. Instead of calling a selector and separately checking `hasFinishedResolution`, consumers get a single object:

```typescript
const { data, status, isResolving, hasResolved, error } = resolve(store).getFeatures();
```

`HarborDataProvider` uses this to fire all four core resolvers and derive `isLoading`, `isLicenseLoading`, and error states in one place.

`useResolvableSelectWithError` is a sibling hook that re-throws resolver errors during render so an `ErrorBoundary` can catch them. It's the general-purpose variant; `HarborDataProvider` uses the base hook because it pipes resolver errors to the error modal rather than an `ErrorBoundary`.

## Hooks

First-party hooks under `resources/js/hooks/` encapsulate non-trivial UI and store wiring:

- `useFeatureRow` — orchestrates `FeatureRow` behaviour (toggle state, store dispatch, reload-banner integration after successful enable/disable).
- `useFilteredFeatures` — returns the features for a given product filtered by `FilterContext` search.
- `useProductFeatureGroups` — partitions a product's features into available + tier-grouped locked sets and computes upgrade vs activation tier lists.
- `useWelcomeLicenseForm` — owns input state, derived `LWSW-` prefix validation, `storeLicense` dispatch, and the `pickWelcomeErrorMessage` helper for `WelcomeLicenseForm`. Mirrors the `useFeatureRow` pattern.
- `useResolvableSelect` / `useResolvableSelectWithError` — see the [useResolvableSelect](#useresolvableselect) section above.

## Component Hierarchy

Components follow an atomic design structure:

```
resources/js/components/
├── atoms/          — Leaf-level display: ErrorBoundary, ErrorItem, FeatureIcon,
│                     LicenseBadge, LicenseKeyInputSkeleton, NexcessLogo,
│                     ProductLogo, PurchaseLink, SectionHeader, StatusBadge,
│                     UpdateButton
├── molecules/      — Composed groups: FeatureRow, FilterBar, LegacyLicenseBanner,
│                     LicenseKeyInput, LicenseProductCard, NotActivatedBanner,
│                     ReloadBanner, TierGroup, UpsellCard, VersionDisplay,
│                     WelcomeLicenseForm, WelcomeNoticeBanner
├── organisms/      — Sections: AppLoader, ErrorModal, LicensePanel, LicenseSection,
│                     ProductSection, ProductSectionSkeleton, UpsellSection,
│                     WelcomeScreen
├── templates/      — Page layouts: AppShell (two-column with sidebar),
│                     Shell (header + main + aside slots),
│                     WelcomeShell (first-load layout for the welcome screen)
└── ui/             — Shadcn-based primitives: badge, button, card, dialog,
                      input, label, select, switch, toast, tooltip
```

The `ui/` directory contains Shadcn components adapted for the project. These are low-level building blocks used by the atomic layers above.

## Asset Pipeline

### Build Output

Webpack compiles `resources/js/index.tsx` into a single bundle. The output directory depends on the build mode:

| Mode        | Output directory | Source maps | Minified |
| ----------- | ---------------- | ----------- | -------- |
| Development | `build-dev/`     | Yes         | No       |
| Production  | `build/`         | No          | Yes      |

The build produces `index.js`, `index.css`, and `index.asset.php` (dependency manifest generated by `@wordpress/scripts`).

### PHP Asset Loading

`Feature_Manager_Page::enqueue_assets()` loads assets from `build-dev/` when `WP_DEBUG` is true, from `build/` otherwise. It:

1. Reads `index.asset.php` for the dependency list and content-hashed version.
2. Registers the JS handle `lw-harbor-ui` with `wp_register_script()`.
3. Injects the `harborData` global via `wp_localize_script()`.
4. Registers and enqueues the CSS.

Assets are only enqueued on the Software Manager admin page (hook suffix check in `maybe_enqueue_assets`).

### harborData Global

PHP injects a `window.harborData` object containing:

```typescript
interface HarborData {
    restUrl:          string; // rest_url('liquidweb/harbor/v1/')
    nonce:            string; // wp_create_nonce('wp_rest')
    pluginsUrl:       string; // admin_url('plugins.php')
    activationUrl:    string; // portal /subscriptions/ URL with referral + redirect params
    subscriptionsUrl: string; // portal /subscriptions/ base, no query params
    domain:           string; // site domain from Data::get_domain()
    version:          string; // Harbor::VERSION, rendered in AppShell footer
    licenseKeyPrefix: string; // mirrors PHP License_Key::PREFIX (default 'LWSW-')
}
```

Consumers read these fields through `resources/js/lib/harbor-data.ts`. `getHarborDataValue(key, fallback?)` is the typed accessor; it returns the live `window.harborData[key]`, then the per-call `fallback`, then a built-in default from the module's `DEFAULTS` map, then `null` (matching the codebase convention of `string | null` over `undefined`). Keys with a built-in default (`licenseKeyPrefix`, `pluginsUrl`) are safe to read even before the PHP `wp_localize_script` payload runs. `getLicenseKeyPlaceholder()` builds the canonical `LWSW-XXXX-XXXX-XXXX-XXXX-XXXX` placeholder from the configured prefix and is consumed by both `LicenseKeyInput` and `WelcomeLicenseForm`.

The `@wordpress/api-fetch` package handles nonce headers automatically via its built-in middleware, so `getHarborDataValue('nonce')` is available as a fallback. `getHarborDataValue('restUrl')` is available for constructing full URLs when needed.

`activationUrl` is the portal URL used to drive unactivated products through the activation flow (pre-built with `portal-referral`, `redirect_url`, and `domain` query params). `subscriptionsUrl` is the bare `/subscriptions/` base used as the starting point for URL-building helpers. `licenseKeyPrefix` is consumed by `useWelcomeLicenseForm` for client-side prefix validation and by `getLicenseKeyPlaceholder()` for input placeholders.

### Upgrade CTA Routing

When a user sees an upgrade button in a `TierGroup` — i.e. a tier ranked above their current one — the target URL depends on whether they already have a `licenseProduct` (activated or unactivated) for that product:

- **Has a `licenseProduct` for this product**: the button calls `buildUpgradeUrl(tier.upgrade_url, getHarborDataValue('domain'))`, which appends `domain` and `portal-referral=plugin` query params to the tier's catalog `upgrade_url`. The portal resolves the subscription from the authenticated session and drives the upgrade flow.
- **No `licenseProduct` for this product**: the button falls back to the catalog tier's `purchase_url` for a fresh purchase.

URL construction is centralized in `resources/js/lib/upgrade-url.ts` (`buildUpgradeUrl`). The decision between upgrade and `purchase_url` is made in `ProductSection`, keeping `TierGroup` a dumb presentational component that receives a resolved `buttonHref`.

### Webpack Aliases

The webpack config defines path aliases for clean imports:

| Alias          | Path                        |
| -------------- | --------------------------- |
| `@`            | `resources/js/`             |
| `@components`  | `resources/js/components/`  |
| `@lib`         | `resources/js/lib/`         |
| `@css`         | `resources/css/`            |
| `@img`         | `resources/img/`            |

## CSS Scoping

Tailwind v4 outputs all utilities inside `@layer`. Per the CSS cascade spec, any unlayered stylesheet (e.g. WordPress admin's `load-styles.php`) beats named layers regardless of specificity. Three mechanisms fix this:

1. **`important: true`** in `tailwind.config.js` adds `!important` to every utility, which inside a named layer beats normal unlayered declarations. A companion PostCSS plugin (`stripImportantFromCustomProps`) strips `!important` from CSS custom property declarations, since browsers treat those as invalid.

2. **Tailwind variable namespacing** — a PostCSS plugin (`renameTailwindVars`) rewrites Tailwind's internal `--tw-*` variables to `--lw-harbor-tw-*`. External plugins built with Tailwind v3 (e.g. LearnDash) declare `--tw-translate-x`, `--tw-shadow`, etc. on the universal selector without any CSS layer, which beats Harbor's layered utilities. Renaming Harbor's internal variables makes the namespace private and eliminates the collision.

3. **Selector scoping** — a PostCSS plugin (`scopeToHarborUI`) prefixes all generated selectors with `.lw-harbor-ui`, limiting Tailwind styles to the Harbor mount point. `:root` rules and `@keyframes` content are excluded from scoping.

The PostCSS pipeline runs in order: `@tailwindcss/postcss` → `stripImportantFromCustomProps` → `renameTailwindVars` → `scopeToHarborUI` → `autoprefixer`.

## Error Handling

### HarborError

All frontend errors are normalized into `HarborError`, a typed `Error` subclass that wraps `WP_Error` JSON responses from the REST API. It preserves the error code, status, data payload, and `additional_errors` chain from multi-code `WP_Error` responses.

Key static methods:

- `HarborError.wrap(error, code, message)` — async, handles `Response` objects from `apiFetch`.
- `HarborError.wrapSync(error, code, message)` — synchronous variant.
- `HarborError.from(error, code, message)` — async conversion without wrapping as cause.
- `HarborError.syncFrom(error, code, message)` — synchronous conversion.

Error codes are defined in `ErrorCode` enum (`resources/js/errors/error-code.ts`).

### Error Surfaces

- **ErrorModal** — resolver failures in `HarborDataProvider` are pushed to the `ErrorModalContext`. The modal renders outside the `ErrorBoundary` so it survives render crashes.
- **ErrorBoundary** — catches uncaught render errors in the component tree.
- **Per-feature errors** — toggle and update failures are stored in `features.errorBySlug` and surfaced inline on the affected feature row.
- **License errors** — `storeError`, `deleteError`, and `refreshError` in the license state slice, surfaced by the license panel.
- **Toasts** — `ToastProvider` manages transient notifications (auto-dismiss after 3.5 seconds).
- **ReloadBanner** — sticky banner in the FilterBar row that asks the user to reload after a successful feature toggle; state lives in `ReloadBannerContext` and is set by `useFeatureRow` on successful enable/disable.

## Product Registry

Product metadata (slug, display name, tagline) is defined in `resources/js/data/products.ts`. This is display-layer data only — tier definitions and feature lists come from the catalog and features REST endpoints.

```typescript
const PRODUCTS: Product[] = [
    { slug: 'give',                name: 'GiveWP',              tagline: 'Donation forms and fundraising for WordPress' },
    { slug: 'the-events-calendar', name: 'The Events Calendar', tagline: 'Powerful event management for WordPress' },
    { slug: 'learndash',           name: 'LearnDash',           tagline: 'World-class LMS for online courses' },
    { slug: 'kadence',             name: 'Kadence',             tagline: 'Page builder and theme toolkit for WordPress' },
];
```

The module also exports a `getProduct(slug)` helper that returns the matching entry or `undefined`.

`AppShell` iterates this list to render `ProductSection` components, filtering by the current product filter from `FilterContext`.
