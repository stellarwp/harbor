/**
 * Selectors for the lw @wordpress/data store.
 *
 * @package LiquidWeb\Harbor
 */
import { createSelector } from '@wordpress/data';
import type { State } from './types';
import type {
	CatalogTier,
	Feature,
	FeatureMismatchType,
	LegacyLicense,
	LicenseError,
	LicenseProduct,
	ProductCatalog,
} from '@/types/api';
import type HarborError from '@/errors/harbor-error';
import { getFeatureMismatch } from '@/lib/feature-utils';
import { isInstallableFeature } from '@/types/utils';

// ---------------------------------------------------------------------------
// Features
// ---------------------------------------------------------------------------

export const getFeatures = createSelector(
	(state: State): Feature[] => Object.values(state.features.bySlug),
	(state: State) => [state.features.bySlug]
);

export const getFeaturesByProduct = createSelector(
	(state: State, product: string): Feature[] =>
		Object.values(state.features.bySlug).filter((f) => f.product === product),
	(state: State, product: string) => [state.features.bySlug, product]
);

export const getFeature = (state: State, slug: string): Feature | null =>
	state.features.bySlug[slug] ?? null;

export const isFeatureEnabled = (state: State, slug: string): boolean =>
	state.features.bySlug[slug]?.is_enabled ?? false;

export const isFeatureToggling = (state: State, slug: string): boolean =>
	state.features.toggling[slug] ?? false;

export const getFeatureError = (
	state: State,
	slug: string
): HarborError | null => state.features.errorBySlug[slug] ?? null;

/**
 * Returns the capability mismatch type for a feature, or null if there is none.
 *
 * Wraps getFeatureMismatch() for consumers that only have access to the store.
 * Hooks that already hold a Feature object should call getFeatureMismatch() directly.
 */
export const getFeatureMismatchType = (
	state: State,
	slug:  string
): FeatureMismatchType => {
	const feature = state.features.bySlug[ slug ];
	if ( ! feature ) return null;
	return getFeatureMismatch( feature );
};

export const isFeatureUpdating = (state: State, slug: string): boolean =>
	state.features.updating[slug] ?? false;

/**
 * True when any feature is being toggled or updated.
 *
 * Both toggle and update operations trigger WordPress install/activate/deactivate
 * operations that should not run concurrently.
 *
 * Memoized via createSelector so the loops only re-run when
 * the relevant sub-trees actually change.
 */
export const isAnyInstallableBusy = createSelector(
	(state: State): boolean => {
		const { toggling, updating, bySlug } = state.features;
		const isInstallable = (slug: string): boolean => {
			const feature = bySlug[slug];
			return feature !== undefined && isInstallableFeature(feature);
		};
		return (
			Object.keys(toggling).some(isInstallable) ||
			Object.keys(updating).some(isInstallable)
		);
	},
	(state: State) => [
		state.features.toggling,
		state.features.updating,
		state.features.bySlug,
	]
);

// ---------------------------------------------------------------------------
// Legacy licenses
// ---------------------------------------------------------------------------

export const getLegacyLicenses = createSelector(
	(state: State): LegacyLicense[] => Object.values(state.legacyLicenses.bySlug),
	(state: State) => [state.legacyLicenses.bySlug]
);

export const getLegacyLicenseBySlug = (state: State, slug: string): LegacyLicense | null =>
	state.legacyLicenses.bySlug[slug] ?? null;

export const hasLegacyLicense = (state: State, slug: string): boolean =>
	slug in state.legacyLicenses.bySlug;

export const hasLegacyLicenses = (state: State): boolean =>
	Object.keys(state.legacyLicenses.bySlug).length > 0;

/**
 * Returns the legacy license for the given feature slug only if it is active,
 * or null if it does not exist or has expired.
 */
export const getActiveLegacyLicense = (state: State, slug: string): LegacyLicense | null => {
	const license = state.legacyLicenses.bySlug[ slug ] ?? null;
	return license !== null && license.is_active ? license : null;
};

/**
 * True when the unified license covers the given product slug.
 */
export const isProductUnifiedLicensed = (state: State, productSlug: string): boolean =>
	state.license.license.products.some( (p) => p.product_slug === productSlug );

/**
 * True when at least one feature belonging to the product has an active legacy license.
 */
export const hasActiveLegacyLicenseForProduct = createSelector(
	(state: State, productSlug: string): boolean =>
		Object.values( state.features.bySlug )
			.filter( (f) => f.product === productSlug )
			.some( (f) => state.legacyLicenses.bySlug[ f.slug ]?.is_active === true ),
	(state: State, productSlug: string) => [ state.features.bySlug, state.legacyLicenses.bySlug, productSlug ]
);

// ---------------------------------------------------------------------------
// Catalog
// ---------------------------------------------------------------------------

export const getCatalog = createSelector(
	(state: State): ProductCatalog[] =>
		Object.values(state.catalog.byProductSlug),
	(state: State) => [state.catalog.byProductSlug]
);

export const getProductCatalog = (
	state: State,
	slug: string
): ProductCatalog | null => state.catalog.byProductSlug[slug] ?? null;

export const getProductTiers = createSelector(
	(state: State, slug: string): CatalogTier[] =>
		state.catalog.byProductSlug[slug]?.tiers ?? [],
	(state: State, slug: string) => [state.catalog.byProductSlug, slug]
);

/**
 * Returns a single CatalogTier by product slug and tier slug, or null.
 *
 * Returns the full tier object so callers can read price, currency, etc.
 */
export const getCatalogTier = (
	state: State,
	productSlug: string,
	tierSlug: string
): CatalogTier | null =>
	state.catalog.byProductSlug[productSlug]?.tiers.find(
		(t) => t.tier_slug === tierSlug
	) ?? null;

// ---------------------------------------------------------------------------
// License
// ---------------------------------------------------------------------------

const UNACTIVATED_STATUSES = [ 'not_activated', 'activation_required' ] as const;

/**
 * True when a license is present and every product's validation_status indicates
 * it has not been activated on this domain (not_activated or activation_required).
 * Returns false when there are no products.
 */
export const areAllProductsNotActivated = ( state: State ): boolean => {
	const products = state.license.license.products;
	return (
		products.length > 0 &&
		products.every(
			( p ) => UNACTIVATED_STATUSES.includes( p.validation_status as typeof UNACTIVATED_STATUSES[number] )
		)
	);
};

/**
 * Returns the stored unified license key, or null. Triggers getLicenseKey resolver.
 * @param state
 */
export const getLicenseKey = (state: State): string | null =>
	state.license.license.key;

export const hasLicense = (state: State): boolean =>
	state.license.license.key !== null;

export const getLicenseProducts = (state: State): LicenseProduct[] =>
	state.license.license.products;

export const getLicenseError = (state: State): LicenseError | null =>
	state.license.license.error;

export const isLicenseStoring = (state: State): boolean =>
	state.license.isStoring;

export const isLicenseDeleting = (state: State): boolean =>
	state.license.isDeleting;

export const isLicenseRefreshing = (state: State): boolean =>
	state.license.isRefreshing;

export const canModifyLicense = (state: State): boolean =>
	!state.license.isStoring &&
	!state.license.isDeleting &&
	!state.license.isRefreshing;

export const getStoreLicenseError = (state: State): HarborError | null =>
	state.license.storeError;

export const getDeleteLicenseError = (state: State): HarborError | null =>
	state.license.deleteError;

export const getRefreshLicenseError = (state: State): HarborError | null =>
	state.license.refreshError;
