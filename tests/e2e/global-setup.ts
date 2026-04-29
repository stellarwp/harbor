import path from 'path';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

export const STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH ||
	path.join( process.cwd(), 'artifacts/storage-states/admin.json' );

const WP_ENV_PORT = process.env.WP_ENV_PORT ?? '8901';
const WP_BASE_URL = process.env.WP_BASE_URL ?? `http://localhost:${ WP_ENV_PORT }`;

async function globalSetup(): Promise<void> {
	const requestUtils = await RequestUtils.setup( {
		baseURL: WP_BASE_URL,
		storageStatePath: STORAGE_STATE_PATH,
	} );

	await requestUtils.setupRest();
}

export default globalSetup;
