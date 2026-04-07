---
ticket: SCON-599
status: todo
url: https://stellarwp.atlassian.net/browse/SCON-599
---

# Route action errors through ErrorModal instead of toasts

## Problem

API operation failures in the React frontend are surfaced with `addToast(..., 'error')`. Toasts auto-dismiss after 3.5 seconds, so errors disappear before the user can read them or act on them.

Toasts are the right pattern for confirmations and soft warnings. They are the wrong pattern for unexpected failures that need user attention.

The ErrorModal already handles this correctly for resolver failures. It deduplicates by error code, supports nested cause chains, and stays on screen until the user dismisses it. But action-level errors bypass it entirely.

Affected call sites (all 6):

- `useFeatureRow.ts` lines 93, 101, 114 (feature enable/disable/update)
- `LicenseKeyInput.tsx` line 68 (license activation)
- `LicensePanel.tsx` lines 67, 80 (license deletion and refresh)

## Proposed solution

Replace `addToast(result.message, 'error')` with `addError(result)` from `useErrorModal()` at all 6 call sites. The action creators already return `HarborError` instances with structured codes and cause chains, so no wrapping is needed.

License activation errors in `LicenseKeyInput.tsx` are not purely validation failures. The `storeLicense` action can fail for reasons beyond bad input, such as expired licenses, domain limits, or server errors. These need persistent display with the cause chain and support links the ErrorModal provides. Client-side validation (empty input) remains handled by inline `localError` state. The existing TODO comment requesting this change is removed.

Additionally, fix `LicensePanel.handleRefresh` to surface both errors when license refresh and catalog refresh both fail. The current implementation uses `licenseResult ?? catalogResult` which silently drops the second failure. Check each result independently and only show the success toast when neither failed.

After the change, `addToast` should only be used for non-error communication: success confirmations, neutral state changes, and soft warnings that don't require action.
