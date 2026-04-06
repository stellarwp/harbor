/**
 * Machine-readable error codes for HarborError instances.
 *
 * @package LiquidWeb\Harbor
 */
export enum ErrorCode {
	FeaturesFetchFailed = 'features-fetch-failed',
	FeatureEnableFailed = 'feature-enable-failed',
	FeatureDisableFailed = 'feature-disable-failed',
	FeatureUpdateFailed = 'feature-update-failed',
	LicenseFetchFailed = 'license-fetch-failed',
	LicenseActionInProgress = 'license-action-in-progress',
	LicenseStoreFailed = 'license-store-failed',
	LicenseDeleteFailed = 'license-delete-failed',
	LicenseRefreshFailed = 'license-refresh-failed',
	LicenseValidateFailed = 'license-validate-failed',
	CatalogFetchFailed = 'catalog-fetch-failed',
	CatalogRefreshFailed = 'catalog-refresh-failed',
	LegacyLicensesFetchFailed = 'legacy-licenses-fetch-failed',
	ResolutionFailed = 'resolution-failed',
}
