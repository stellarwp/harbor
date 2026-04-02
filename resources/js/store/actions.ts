/**
 * Action creators for the lw @wordpress/data store.
 *
 * @package LiquidWeb\Harbor
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { HarborError, ErrorCode } from '@/errors';
import type { Feature, LegacyLicense, License, ProductCatalog } from '@/types/api';
import type { Action, Thunk } from './types';

// ---------------------------------------------------------------------------
// Plain action creators (synchronous)
// ---------------------------------------------------------------------------

export const receiveFeatures = (features: Feature[]): Action => ({
	type: 'RECEIVE_FEATURES',
	features,
});

export const receiveLicense = (license: License): Action => ({
	type: 'RECEIVE_LICENSE',
	license,
});

export const receiveCatalog = (catalogs: ProductCatalog[]): Action => ({
	type: 'RECEIVE_CATALOG',
	catalogs,
});

export const receiveLegacyLicenses = (licenses: LegacyLicense[]): Action => ({
	type: 'RECEIVE_LEGACY_LICENSES',
	licenses,
});

// ---------------------------------------------------------------------------
// Thunk action creators (async)
// ---------------------------------------------------------------------------

/**
 * Enable a feature via the REST API.
 *
 * @param slug
 * @since 1.0.0
 */
export const enableFeature =
	(slug: string): Thunk<HarborError | null> =>
	async ({ dispatch }) => {
		dispatch({ type: 'TOGGLE_FEATURE_START', slug });
		try {
			const feature = await apiFetch<Feature>({
				path: `/liquidweb/harbor/v1/features/${slug}/enable`,
				method: 'POST',
			});
			dispatch({ type: 'TOGGLE_FEATURE_FINISHED', feature });
			return null;
		} catch (err) {
			const error = await HarborError.wrap(
				err,
				ErrorCode.FeatureEnableFailed,
				__(
					'Liquid Web Software Manager failed to enable your feature.',
					'%TEXTDOMAIN%'
				)
			);
			dispatch({ type: 'TOGGLE_FEATURE_FAILED', slug, error });
			return error;
		}
	};

/**
 * Disable a feature via the REST API.
 *
 * @param slug
 * @since 1.0.0
 */
export const disableFeature =
	(slug: string): Thunk<HarborError | null> =>
	async ({ dispatch }) => {
		dispatch({ type: 'TOGGLE_FEATURE_START', slug });
		try {
			const feature = await apiFetch<Feature>({
				path: `/liquidweb/harbor/v1/features/${slug}/disable`,
				method: 'POST',
			});
			dispatch({ type: 'TOGGLE_FEATURE_FINISHED', feature });
			return null;
		} catch (err) {
			const error = await HarborError.wrap(
				err,
				ErrorCode.FeatureDisableFailed,
				__(
					'Liquid Web Software Manager failed to disable your feature.',
					'%TEXTDOMAIN%'
				)
			);
			dispatch({ type: 'TOGGLE_FEATURE_FAILED', slug, error });
			return error;
		}
	};

/**
 * Update a feature via the REST API.
 *
 * @param slug
 * @since 1.0.0
 */
export const updateFeature =
	(slug: string): Thunk<HarborError | null> =>
	async ({ dispatch }) => {
		dispatch({ type: 'UPDATE_FEATURE_START', slug });
		try {
			const feature = await apiFetch<Feature>({
				path: `/liquidweb/harbor/v1/features/${slug}/update`,
				method: 'POST',
			});
			dispatch({ type: 'UPDATE_FEATURE_FINISHED', feature });
			return null;
		} catch (err) {
			const error = await HarborError.wrap(
				err,
				ErrorCode.FeatureUpdateFailed,
				__(
					'Liquid Web Software Manager failed to update your feature.',
					'%TEXTDOMAIN%'
				)
			);
			dispatch({ type: 'UPDATE_FEATURE_FAILED', slug, error });
			return error;
		}
	};

/**
 * Store a license key via the REST API, then invalidate the license
 * and features resolvers so the UI refreshes with the new entitlements.
 *
 * @param key
 * @since 1.0.0
 */
export const storeLicense =
	(key: string): Thunk<HarborError | null> =>
	async ({ dispatch, select }) => {
		if (!select.canModifyLicense()) {
			return new HarborError(
				ErrorCode.LicenseActionInProgress,
				__(
					'Liquid Web Software Manager failed to activate your license, another action is in progress.',
					'%TEXTDOMAIN%'
				)
			);
		}
		dispatch({ type: 'STORE_LICENSE_START' });
		try {
			const result = await apiFetch<License>({
				path: '/liquidweb/harbor/v1/license',
				method: 'POST',
				data: { key },
			});
			dispatch({
				type: 'STORE_LICENSE_FINISHED',
				license: result,
			});
			dispatch.invalidateResolution('getFeatures', []);
			return null;
		} catch (err) {
			const error = await HarborError.wrap(
				err,
				ErrorCode.LicenseStoreFailed,
				__(
					'Liquid Web Software Manager failed to activate your license.',
					'%TEXTDOMAIN%'
				)
			);
			dispatch({ type: 'STORE_LICENSE_FAILED', error });
			return error;
		}
	};

/**
 * Delete the stored license key via the REST API, then invalidate the
 * features resolver so the UI refreshes.
 *
 * @since 1.0.0
 */
export const deleteLicense =
	(): Thunk<HarborError | null> =>
	async ({ dispatch, select }) => {
		if (!select.canModifyLicense()) {
			return new HarborError(
				ErrorCode.LicenseActionInProgress,
				__(
					'Liquid Web Software Manager failed to delete your license, another action is in progress.',
					'%TEXTDOMAIN%'
				)
			);
		}
		dispatch({ type: 'DELETE_LICENSE_START' });
		try {
			await apiFetch<void>({
				path: '/liquidweb/harbor/v1/license',
				method: 'DELETE',
			});
			dispatch({ type: 'DELETE_LICENSE_FINISHED' });
			dispatch.invalidateResolution('getFeatures', []);
			return null;
		} catch (err) {
			const error = await HarborError.wrap(
				err,
				ErrorCode.LicenseDeleteFailed,
				__(
					'Liquid Web Software Manager failed to remove your license.',
					'%TEXTDOMAIN%'
				)
			);
			dispatch({ type: 'DELETE_LICENSE_FAILED', error });
			return error;
		}
	};
