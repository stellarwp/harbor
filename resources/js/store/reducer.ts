/**
 * Reducer for the lw @wordpress/data store.
 *
 * @package LiquidWeb\Harbor
 */
import { combineReducers } from '@wordpress/data';
import type { License } from '@/types/api';
import type {
	Action,
	CatalogState,
	FeaturesState,
	HarborHostsState,
	LegacyLicensesState,
	LicenseState,
} from './types';

function withoutCancelledProducts( license: License ): License {
	return {
		...license,
		products: license.products.filter( ( p ) => p.status !== 'cancelled' ),
	};
}

export const reducer = combineReducers({ features, harborHosts, license, catalog, legacyLicenses });

// ---------------------------------------------------------------------------
// Catalog
// ---------------------------------------------------------------------------

const CATALOG_DEFAULT: CatalogState = {
	byProductSlug: {},
};

function catalog(
	state: CatalogState = CATALOG_DEFAULT,
	action: Action
): CatalogState {
	switch (action.type) {
		case 'RECEIVE_CATALOG': {
			return {
				...state,
				byProductSlug: Object.fromEntries(
					action.catalogs.map((c) => [c.product_slug, c])
				),
			};
		}

		default:
			return state;
	}
}

// ---------------------------------------------------------------------------
// Legacy licenses
// ---------------------------------------------------------------------------

const LEGACY_LICENSES_DEFAULT: LegacyLicensesState = {
	bySlug: {},
};

function legacyLicenses(
	state: LegacyLicensesState = LEGACY_LICENSES_DEFAULT,
	action: Action
): LegacyLicensesState {
	switch (action.type) {
		case 'RECEIVE_LEGACY_LICENSES': {
			return {
				...state,
				bySlug: Object.fromEntries(
					action.licenses.map((l) => [l.slug, l])
				),
			};
		}

		default:
			return state;
	}
}

// ---------------------------------------------------------------------------
// Features
// ---------------------------------------------------------------------------

const FEATURES_DEFAULT: FeaturesState = {
	bySlug: {},
	toggling: {},
	updating: {},
	errorBySlug: {},
};

function features(
	state: FeaturesState = FEATURES_DEFAULT,
	action: Action
): FeaturesState {
	switch (action.type) {
		case 'RECEIVE_FEATURES': {
			return {
				...state,
				bySlug: Object.fromEntries( action.features.map( ( f ) => [ f.slug, f ] ) ),
			};
		}

		case 'TOGGLE_FEATURE_START': {
			const { [action.slug]: _, ...remainingErrors } = state.errorBySlug;
			return {
				...state,
				toggling: { ...state.toggling, [action.slug]: true },
				errorBySlug: remainingErrors,
			};
		}

		case 'TOGGLE_FEATURE_FINISHED': {
			const { slug } = action.feature;
			const { [slug]: _, ...remainingToggling } = state.toggling;
			return {
				...state,
				bySlug: { ...state.bySlug, [ slug ]: action.feature },
				toggling: remainingToggling,
			};
		}

		case 'TOGGLE_FEATURE_FAILED': {
			const { [action.slug]: _, ...remainingToggling } = state.toggling;
			return {
				...state,
				toggling: remainingToggling,
				errorBySlug: {
					...state.errorBySlug,
					[action.slug]: action.error,
				},
			};
		}

		case 'UPDATE_FEATURE_START': {
			const { [action.slug]: _, ...remainingErrors } = state.errorBySlug;
			return {
				...state,
				updating: { ...state.updating, [action.slug]: true },
				errorBySlug: remainingErrors,
			};
		}

		case 'UPDATE_FEATURE_FINISHED': {
			const { slug } = action.feature;
			const { [slug]: _, ...remainingUpdating } = state.updating;
			return {
				...state,
				bySlug: {
					...state.bySlug,
					[slug]: action.feature,
				},
				updating: remainingUpdating,
			};
		}

		case 'UPDATE_FEATURE_FAILED': {
			const { [action.slug]: _, ...remainingUpdating } = state.updating;
			return {
				...state,
				updating: remainingUpdating,
				errorBySlug: {
					...state.errorBySlug,
					[action.slug]: action.error,
				},
			};
		}

		default:
			return state;
	}
}

// ---------------------------------------------------------------------------
// Harbor hosts
// ---------------------------------------------------------------------------

const HARBOR_HOSTS_DEFAULT: HarborHostsState = {
	basenames: [],
};

function harborHosts(
	state: HarborHostsState = HARBOR_HOSTS_DEFAULT,
	action: Action
): HarborHostsState {
	switch ( action.type ) {
		case 'RECEIVE_HARBOR_HOSTS':
			return { ...state, basenames: action.basenames };
		default:
			return state;
	}
}

// ---------------------------------------------------------------------------
// License
// ---------------------------------------------------------------------------

const LICENSE_DEFAULT: LicenseState = {
	license:      { key: null, products: [], error: null },
	isStoring:    false,
	isDeleting:   false,
	isRefreshing: false,
	storeError:   null,
	deleteError:  null,
	refreshError: null,
};

function license(
	state: LicenseState = LICENSE_DEFAULT,
	action: Action
): LicenseState {
	switch (action.type) {
		case 'RECEIVE_LICENSE': {
			return {
				...state,
				license: withoutCancelledProducts( action.license ),
			};
		}

		case 'STORE_LICENSE_START': {
			return {
				...state,
				isStoring: true,
				storeError: null,
			};
		}

		case 'STORE_LICENSE_FINISHED': {
			return {
				...state,
				isStoring: false,
				license: withoutCancelledProducts( action.license ),
			};
		}

		case 'STORE_LICENSE_FAILED': {
			return {
				...state,
				isStoring: false,
				storeError: action.error,
			};
		}

		case 'DELETE_LICENSE_START': {
			return {
				...state,
				isDeleting: true,
				deleteError: null,
			};
		}

		case 'DELETE_LICENSE_FINISHED': {
			return {
				...state,
				isDeleting: false,
				license: { key: null, products: [], error: null },
			};
		}

		case 'DELETE_LICENSE_FAILED': {
			return {
				...state,
				isDeleting: false,
				deleteError: action.error,
			};
		}

		case 'REFRESH_LICENSE_START': {
			return {
				...state,
				isRefreshing: true,
				refreshError: null,
			};
		}

		case 'REFRESH_LICENSE_FINISHED': {
			return {
				...state,
				isRefreshing: false,
				license: withoutCancelledProducts( action.license ),
			};
		}

		case 'REFRESH_LICENSE_FAILED': {
			return {
				...state,
				isRefreshing: false,
				refreshError: action.error,
			};
		}

		default:
			return state;
	}
}
