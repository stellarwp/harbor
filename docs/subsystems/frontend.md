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

`App.tsx` wraps the UI in four context providers and an error boundary:

```
ToastProvider
  FilterProvider
    ErrorModalProvider
      HarborDataProvider
        ErrorBoundary
          AppShell + Toaster
        ErrorModal          ← outside ErrorBoundary
```

The order matters:

- **ToastProvider** — toast notifications, consumed anywhere.
- **FilterProvider** — search query and product filter state for the feature list.
- **ErrorModalProvider** — collects `HarborError` instances; the `ErrorModal` renders them.
- **HarborDataProvider** — fires the four core resolvers (license, features, catalog, legacy licenses) and pushes resolver errors into the error modal. Exposes `isLoading` to gate the UI.
- **ErrorBoundary** — catches render crashes. `ErrorModal` sits outside it so a crash doesn't prevent the modal from opening.

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
    license: {
        license:     License;      // { key, products[] }
        isStoring:   boolean;
        isDeleting:  boolean;
        storeError:  HarborError | null;
        deleteError: HarborError | null;
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

Resolvers fetch data from the REST API the first time a selector is called, then cache the result. The four primary resolvers:

| Resolver            | Endpoint                                   | Populates               |
| ------------------- | ------------------------------------------ | ----------------------- |
| `getFeatures`       | `GET /liquidweb/harbor/v1/features`        | `features.bySlug`       |
| `getLicenseKey`     | `GET /liquidweb/harbor/v1/license`         | `license.license`       |
| `getCatalog`        | `GET /liquidweb/harbor/v1/catalog`         | `catalog.byProductSlug` |
| `getLegacyLicenses` | `GET /liquidweb/harbor/v1/legacy-licenses` | `legacyLicenses.bySlug` |

Derived selectors (e.g. `getFeature(slug)`, `getProductCatalog(slug)`) use `forwardResolver` / `forwardResolverWithoutArgs` to delegate to the parent resolver without re-fetching.

### Actions

Plain action creators (`receiveFeatures`, `receiveLicense`, `receiveCatalog`, `receiveLegacyLicenses`) populate the store from resolver responses.

Thunk action creators handle mutations:

| Action            | Endpoint                               | Effect                                    |
| ----------------- | -------------------------------------- | ----------------------------------------- |
| `enableFeature`   | `POST /features/{slug}/enable`         | Toggles a feature on                      |
| `disableFeature`  | `POST /features/{slug}/disable`        | Toggles a feature off                     |
| `updateFeature`   | `POST /features/{slug}/update`         | Updates to latest version                 |
| `storeLicense`    | `POST /license`                        | Activates a key, invalidates features     |
| `deleteLicense`   | `DELETE /license`                      | Removes the key, invalidates features     |

After `storeLicense` and `deleteLicense` succeed, the thunk calls `dispatch.invalidateResolution('getFeatures', [])` so the feature list refreshes with updated entitlements.

### Selectors

Selectors are memoized with `createSelector` from `@wordpress/data`. Key selectors:

- **Features** — `getFeatures`, `getFeaturesByProduct`, `getFeature`, `isFeatureEnabled`, `isFeatureToggling`, `isFeatureUpdating`, `getFeatureError`, `getFeatureMismatchType`, `isAnyInstallableBusy`
- **License** — `getLicenseKey`, `hasLicense`, `getLicenseProducts`, `isLicenseStoring`, `isLicenseDeleting`, `canModifyLicense`, `getStoreLicenseError`, `getDeleteLicenseError`
- **Catalog** — `getCatalog`, `getProductCatalog`, `getProductTiers`, `getCatalogTier`
- **Legacy** — `getLegacyLicenses`, `getLegacyLicenseBySlug`, `hasLegacyLicense`, `hasLegacyLicenses`, `getActiveLegacyLicense`, `isProductUnifiedLicensed`, `hasActiveLegacyLicenseForProduct`

### useResolvableSelect

`useResolvableSelect` wraps `useSelect` to return resolution metadata alongside data. Instead of calling a selector and separately checking `hasFinishedResolution`, consumers get a single object:

```typescript
const { data, status, isResolving, hasResolved, error } = resolve(store).getFeatures();
```

`HarborDataProvider` uses this to fire all four resolvers and derive `isLoading` and error states in one place.

## Component Hierarchy

Components follow an atomic design structure:

```
resources/js/components/
├── atoms/          — Leaf-level display: ErrorBoundary, ErrorItem, FeatureIcon,
│                     LicenseBadge, ProductLogo, PurchaseLink, SectionHeader,
│                     StatusBadge, UpdateButton
├── molecules/      — Composed groups: FeatureRow, FilterBar, LegacyLicenseBanner,
│                     LicenseKeyInput, LicenseProductCard, TierGroup, UpsellCard,
│                     VersionDisplay
├── organisms/      — Sections: ErrorModal, LicensePanel, LicenseSection,
│                     ProductSection, UpsellSection
├── templates/      — Page layouts: AppShell (two-column with sidebar),
│                     Shell (header + main + aside slots)
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
}
```

The `@wordpress/api-fetch` package handles nonce headers automatically via its built-in middleware, so `harborData.nonce` is available as a fallback. `harborData.restUrl` is available for constructing full URLs when needed.

`activationUrl` is the portal URL used to drive unactivated products through the activation flow (pre-built with `portal-referral`, `redirect_url`, and `domain` query params). `subscriptionsUrl` is the bare `/subscriptions/` base used as the starting point for URL-building helpers like `buildChangePlanUrl`.

### Upgrade CTA Routing

When a user sees an upgrade button in a `TierGroup` — i.e. a tier ranked above their current one — the target URL depends on whether they already have a subscription for that product:

- **Has a `licenseProduct` for this product** (valid, expired, or otherwise invalid): the button links to `<subscriptionsUrl>/<product-slug>/<tier-slug>/change-plan/`. The portal resolves the subscription from the authenticated session and drives the plan-change flow, so an upgrade modifies the existing subscription instead of adding a new plan to the basket.
- **No `licenseProduct` for this product**: the button falls back to the catalog tier's `purchase_url` for a fresh purchase.

URL construction is centralized in `resources/js/lib/change-plan-url.ts` (`buildChangePlanUrl`). The decision between change-plan and `purchase_url` is made in `ProductSection`, keeping `TierGroup` a dumb presentational component that receives a resolved `buttonHref`.

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

Tailwind v4 outputs all utilities inside `@layer`. Per the CSS cascade spec, any unlayered stylesheet (e.g. WordPress admin's `load-styles.php`) beats named layers regardless of specificity. Two mechanisms fix this:

1. **`important: true`** in `tailwind.config.js` adds `!important` to every utility, which inside a named layer beats normal unlayered declarations. A companion PostCSS plugin (`stripImportantFromCustomProps`) strips `!important` from CSS custom property declarations, since browsers treat those as invalid.

2. **Selector scoping** — a PostCSS plugin (`scopeToHarborUI`) prefixes all generated selectors with `.lw-harbor-ui`, limiting Tailwind styles to the Harbor mount point. `:root` rules and `@keyframes` content are excluded from scoping.

The PostCSS pipeline runs in order: `@tailwindcss/postcss` → `stripImportantFromCustomProps` → `scopeToHarborUI` → `autoprefixer`.

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
- **License errors** — `storeError` and `deleteError` in the license state slice, surfaced by the license panel.
- **Toasts** — `ToastProvider` manages transient notifications (auto-dismiss after 3.5 seconds).

## Product Registry

Product metadata (slug, display name, tagline) is defined in `resources/js/data/products.ts`. This is display-layer data only — tier definitions and feature lists come from the catalog and features REST endpoints.

```typescript
const PRODUCTS: Product[] = [
    { slug: 'give',                name: 'GiveWP',              tagline: '...' },
    { slug: 'the-events-calendar', name: 'The Events Calendar', tagline: '...' },
    { slug: 'learndash',           name: 'LearnDash',           tagline: '...' },
    { slug: 'kadence',             name: 'Kadence',             tagline: '...' },
];
```

`AppShell` iterates this list to render `ProductSection` components, filtering by the current product filter from `FilterContext`.
