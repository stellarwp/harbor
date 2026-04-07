---
ticket: SCON-595
status: todo
url: https://stellarwp.atlassian.net/browse/SCON-595
---

# Service feature type in the frontend

## Problem

The frontend only recognizes `plugin` and `theme` as feature kinds. `FeatureType` is `'plugin' | 'theme'`, and the `Feature` discriminated union only includes `PluginFeature` and `ThemeFeature`.

The backend will start returning features with `type: 'service'`. Services represent features that are either present or not. They have no installable, activatable, or versioned state. There is nothing to install, toggle, or update.

If the frontend receives `type: 'service'` today, it falls outside the typed union. The rendering logic in FeatureRow has no defined behavior for it. StatusBadge would show "Activated"/"Deactivated" (wrong for a present-or-not concept), and the Switch would offer toggle actions that have no backend semantics.

## Proposed solution

**Extend the type system.** Add `'service'` to `FeatureType`. Define a `ServiceFeature` interface extending `BaseFeature` with `type: 'service'`. Add it to the `Feature` union. `ServiceFeature` should not include `plugin_file`, `plugin_slug`, or `authors` since services have no installable artifact. Use `never` for the optional `BaseFeature` fields (`version`, `installed_version`, `update_version`) that the backend will not send, so TypeScript catches accidental access after narrowing. `CatalogFeature.kind` also uses `FeatureType`, so it will accept `'service'` automatically once the type is expanded.

**Add a type guard.** Add `isServiceFeature` in `types/utils.ts` alongside the existing guards. Because `isInstallableFeature` explicitly checks for `'plugin' | 'theme'`, services are automatically excluded from install/toggle/update operations and the `isAnyInstallableBusy` concurrency gate. No changes needed there.

**Update FeatureRow rendering.** Service features should render only their name, description, any applicable LicenseBadge, and a green "Included" StatusBadge. They should not render VersionDisplay or the Switch. Add `'included'` as a new `FeatureStatus` in StatusBadge, rendered with the `success` variant. This fills the right side of the row so it is not just a bare title, and communicates "this is part of your plan" without implying any active/deactivated lifecycle.

**Guard hook behavior.** In `useFeatureRow`, return a static minimal state for services after all hooks execute (to respect Rules of Hooks) but before the derived-state computation. This prevents the `featureInstalled` bug (`undefined !== null` evaluates to `true`) and keeps no-op handlers on the return type for interface compatibility.
