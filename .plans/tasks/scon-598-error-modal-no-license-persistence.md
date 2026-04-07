---
ticket: SCON-598
status: in-progress
url: https://stellarwp.atlassian.net/browse/SCON-598
---

# Fix ErrorModal firing on no-license load and persisting after feature actions

## Problem

Two related bugs in the Feature Manager admin screen:

1. **ErrorModal on initial load without a license key.** The page shows a `FeaturesFetchFailed` error in the ErrorModal even though the backend's `Resolve_Feature_Collection::__invoke()` already handles the no-license case gracefully (creates an empty `Product_Collection` and returns features successfully). The actual error source needs investigation. The most likely culprit is the catalog fetch failing at `Resolve_Feature_Collection:116-124`, which is license-independent and would cause the entire feature resolution to return a `WP_Error`.

2. **ErrorModal persists after feature actions.** After entering a license key and successfully loading features, every feature action (enable, disable, update) re-triggers the ErrorModal until a hard reload. This happens because `enableFeature`, `disableFeature`, and `updateFeature` in `store/actions.ts` do not invalidate the `getFeatures` resolver after completing. If the resolver was ever in an ERROR state, that stale state gets re-pushed to the ErrorModal by the `useEffect` in `HarborDataProvider` whenever the `result` object recomputes.

Free-tier and WordPress.org features should remain visible without a license key. The frontend resolver should not be gated on license presence.

## Proposed solution

### 1. Investigate and fix the initial-load error

Trace what the `/liquidweb/harbor/v1/features` endpoint actually returns when no license key is stored. The backend code at `Resolve_Feature_Collection::__invoke()` (lines 127-140) already creates an empty `Product_Collection` when `get_key()` returns null, so the resolution should succeed. If the error is coming from the catalog fetch failing (lines 116-124), that is the root cause and needs its own fix.

The investigation should determine whether the error is:
- A catalog fetch failure (fix the catalog client or its error handling)
- A different backend error not visible in static analysis (fix accordingly)
- A frontend-only issue with how the resolver interprets the response

### 2. Add resolver invalidation to feature toggle/update actions

`enableFeature`, `disableFeature`, and `updateFeature` in `store/actions.ts` should call `dispatch.invalidateResolution('getFeatures', [])` after a successful action. This matches the pattern already used by `storeLicense` (line 164), `refreshLicense` (line 205), and `deleteLicense` (line 274). The invalidation clears stale resolver error state and ensures the feature list reflects the current server state.

### 3. Remove license gating from ProductSection feature rendering

`ProductSection.tsx` gated the entire feature list behind `hasLicense`. When no license key was present, the component showed only "Add a license to unlock features" and hid all features. This prevented free-tier and WordPress.org features from rendering even though the backend marks them as available regardless of license state.

The `useProductFeatureGroups` hook already handles the no-license case correctly: when there is no license product, `rank` defaults to `-1`, so all catalog tiers become upgrade tiers. Free features land in `availableFeatures` (rendered as normal `FeatureRow` entries) and paid features land in `lockedByTier` (rendered inside `TierGroup` accordions). The only thing blocking this from working was the `hasLicense` condition in the template.

Remove the `hasLicense` gate and the "Add a license to unlock features" empty state so features always render. The unlicensed state is already communicated by the `LicenseBadge` in the product header.

### 4. Verify catalog and legacy license resolvers are license-independent

Confirm that `getCatalog` and `getLegacyLicenses` resolvers have no implicit dependency on a valid license key. Based on code review they appear independent, but this should be verified with a manual test (load the page with no license key and confirm both resolve successfully).

## Key files

- `resources/js/store/actions.ts` - Add invalidation to feature actions
- `resources/js/components/organisms/ProductSection.tsx` - Remove license gating from feature rendering
- `resources/js/store/resolvers.ts` - Resolvers (no change expected unless investigation reveals frontend issue)
- `resources/js/context/harbor-data-context.tsx` - Error detection and auto-clear logic
- `src/Harbor/Features/Resolve_Feature_Collection.php` - Backend feature resolution (lines 115-165)
- `src/Harbor/API/REST/V1/Feature_Controller.php` - REST endpoint for features (line 172)
- `src/Harbor/Features/Manager.php` - `get_all()` at line 585
- `src/Harbor/Features/Feature_Repository.php` - Caching layer, `get()` at line 57
