import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

const LICENSE_PATH = '/liquidweb/harbor/v1/license';

export const VALID_LICENSE_KEY = 'LWSW-UNIFIED-PRO-2026';

// How the stored key renders once locked: the prefix and final segment stay
// visible while the middle segments are masked with X's of matching length.
// Mirrors maskLicenseKey() in resources/js/lib/license-utils.ts.
export const MASKED_LICENSE_KEY = 'LWSW-XXXX-XXXX-XXXX-XXXX-DJJT';

export async function setLicense( requestUtils: RequestUtils, key: string ): Promise< void > {
	await requestUtils.rest( {
		path:   LICENSE_PATH,
		method: 'POST',
		data:   { key },
	} );
}

// Wipe both the stored license key and the cached products state. The
// products state carries the 60s validate_and_store throttle, so plain
// DELETE /license leaves a failed validation cached for the next 60s and
// poisons the next test. The fixture plugin exposes /lw-harbor-fixture/v1/reset
// which deletes both options.
export async function clearLicense( requestUtils: RequestUtils ): Promise< void > {
	const url      = requestUtils.storageState.rootURL + 'lw-harbor-fixture/v1/reset';
	const response = await requestUtils.request.fetch( url, {
		method:  'POST',
		headers: { 'X-WP-Nonce': requestUtils.storageState.nonce },
	} );

	if ( ! response.ok() ) {
		throw new Error( `clearLicense failed: ${ response.status() } ${ await response.text() }` );
	}
}
