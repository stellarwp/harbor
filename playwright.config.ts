import { defineConfig, devices } from '@playwright/test';
import { STORAGE_STATE_PATH } from './tests/e2e/global-setup';

// WP_ENV_PORT mirrors what wp-env uses (set via `WP_ENV_PORT=XXXX wp-env start`).
// WP_BASE_URL is the full override escape hatch.
const WP_ENV_PORT = process.env.WP_ENV_PORT ?? '8901';
const WP_BASE_URL = process.env.WP_BASE_URL ?? `http://localhost:${ WP_ENV_PORT }`;

export default defineConfig( {
	testDir: './tests/e2e',
	timeout: 60_000,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: process.env.CI ? 'github' : 'list',
	globalSetup: './tests/e2e/global-setup.ts',
	use: {
		baseURL: WP_BASE_URL,
		storageState: STORAGE_STATE_PATH,
		video: 'retain-on-failure',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'headed',
			use: { ...devices[ 'Desktop Chrome' ], headless: false, launchOptions: { slowMo: 800 } },
		},
	],
} );
