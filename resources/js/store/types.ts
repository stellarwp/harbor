/**
 * Shared types for the lw @wordpress/data store.
 *
 * @package LiquidWeb\Harbor
 */
import type { ReduxStoreConfig, StoreDescriptor } from '@wordpress/data';
import type { Thunk as BaseThunk } from '@/types/data';

import type LiquidError from '@/errors/liquid-error';
import type { Feature, LegacyLicense, License, ProductCatalog } from '@/types/api';

import type * as actions from './actions';
import type * as selectors from './selectors';

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

export interface CatalogState {
	/**
	 * Product catalogs keyed by product slug, populated by the getCatalog resolver.
	 */
	byProductSlug: Record<string, ProductCatalog>;
}

export interface FeaturesState {
	/**
	 * Feature objects keyed by slug, populated by the getFeatures resolver.
	 */
	bySlug: Record<string, Feature>;
	/**
	 * Feature slugs currently being toggled.
	 */
	toggling: Record<string, boolean>;
	/**
	 * Feature slugs currently being updated.
	 */
	updating: Record<string, boolean>;
	/**
	 * Per-feature errors from toggle or update failures, keyed by slug.
	 */
	errorBySlug: Record<string, LiquidError>;
}

export interface LicenseState {
	/**
	 * The license data from the API.
	 */
	license: License;
	/**
	 * Whether a license store (activation) is in progress.
	 */
	isStoring: boolean;
	/**
	 * Whether a license deletion is in progress.
	 */
	isDeleting: boolean;
	/**
	 * Whether a per-product validation is in progress.
	 */
	isValidating: boolean;
	/**
	 * The error from the last failed license store.
	 * Cleared when a new store starts.
	 */
	storeError: LiquidError | null;
	/**
	 * The error from the last failed license deletion.
	 * Cleared when a new deletion starts.
	 */
	deleteError: LiquidError | null;
	/**
	 * The error from the last failed product validation.
	 * Cleared when a new validation starts.
	 */
	validateError: LiquidError | null;
}

export interface LegacyLicensesState {
	bySlug: Record<string, LegacyLicense>;
}

export interface State {
	features: FeaturesState;
	license: LicenseState;
	catalog: CatalogState;
	legacyLicenses: LegacyLicensesState;
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

export type Action =
	| { type: 'RECEIVE_CATALOG'; catalogs: ProductCatalog[] }
	| { type: 'RECEIVE_FEATURES'; features: Feature[] }
	| { type: 'RECEIVE_LEGACY_LICENSES'; licenses: LegacyLicense[] }
	| { type: 'TOGGLE_FEATURE_START'; slug: string }
	| { type: 'TOGGLE_FEATURE_FINISHED'; feature: Feature }
	| { type: 'TOGGLE_FEATURE_FAILED'; slug: string; error: LiquidError }
	| { type: 'UPDATE_FEATURE_START'; slug: string }
	| { type: 'UPDATE_FEATURE_FINISHED'; feature: Feature }
	| { type: 'UPDATE_FEATURE_FAILED'; slug: string; error: LiquidError }
	| { type: 'RECEIVE_LICENSE'; license: License }
	| { type: 'STORE_LICENSE_START' }
	| { type: 'STORE_LICENSE_FINISHED'; license: License }
	| { type: 'STORE_LICENSE_FAILED'; error: LiquidError }
	| { type: 'DELETE_LICENSE_START' }
	| { type: 'DELETE_LICENSE_FINISHED' }
	| { type: 'DELETE_LICENSE_FAILED'; error: LiquidError }
	| { type: 'VALIDATE_PRODUCT_START' }
	| { type: 'VALIDATE_PRODUCT_FINISHED'; license: License }
	| { type: 'VALIDATE_PRODUCT_FAILED'; error: LiquidError };

// ---------------------------------------------------------------------------
// Thunk
// ---------------------------------------------------------------------------

type Store = StoreDescriptor<
	ReduxStoreConfig<State, typeof actions, typeof selectors>
>;

export type Thunk<T = void> = BaseThunk<Action, Store, T>;
