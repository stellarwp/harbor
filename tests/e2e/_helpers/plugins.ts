import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

// Core plugin files (top_dir/main_file) from the catalog fixture. Creating a
// stub at one of these paths makes its product report as installed.
export const GIVE_PLUGIN_FILE = 'give/give.php';

async function fixturePost(
	requestUtils: RequestUtils,
	route:        string,
	pluginFile:   string
): Promise< void > {
	const url      = requestUtils.storageState.rootURL + `lw-harbor-fixture/v1/${ route }`;
	const response = await requestUtils.request.fetch( url, {
		method:  'POST',
		headers: { 'X-WP-Nonce': requestUtils.storageState.nonce },
		data:    { plugin_file: pluginFile },
	} );

	if ( ! response.ok() ) {
		throw new Error( `${ route } failed: ${ response.status() } ${ await response.text() }` );
	}
}

// Drop a stub plugin file so the matching product reports as installed.
export function installPlugin( requestUtils: RequestUtils, pluginFile: string ): Promise< void > {
	return fixturePost( requestUtils, 'install-plugin', pluginFile );
}

// Remove a stub plugin file so the matching product returns to Available.
export function uninstallPlugin( requestUtils: RequestUtils, pluginFile: string ): Promise< void > {
	return fixturePost( requestUtils, 'uninstall-plugin', pluginFile );
}
