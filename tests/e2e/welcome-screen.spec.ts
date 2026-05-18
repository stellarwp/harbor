import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { clearLicense, VALID_LICENSE_KEY } from './_helpers/license';

const PAGE = { admin: 'options-general.php', query: 'page=lw-software-manager' };

test.describe( 'Welcome screen', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await clearLicense( requestUtils );
	} );

	test( 'renders the welcome screen when no license is stored', async ( { page, admin } ) => {
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		await expect( page.getByRole( 'heading', { name: 'Software License Manager' } ) ).toBeVisible();
		await expect( page.locator( '#welcome-license-key-input' ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Activate' } ) ).toBeVisible();
	} );

	test( 'shows the format hint and keeps Activate disabled for non-LWSW input', async ( { page, admin } ) => {
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		const input  = page.locator( '#welcome-license-key-input' );
		const submit = page.getByRole( 'button', { name: 'Activate' } );

		await input.fill( 'NOT-A-UNIFIED-KEY' );

		await expect( page.locator( '#welcome-license-hint' ) ).toContainText(
			"This doesn't look like a unified license key."
		);
		await expect( submit ).toBeDisabled();
	} );

	test( 'enables Activate once the input matches the LWSW- prefix', async ( { page, admin } ) => {
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		const input  = page.locator( '#welcome-license-key-input' );
		const submit = page.getByRole( 'button', { name: 'Activate' } );

		await input.fill( 'LWSW-' );

		await expect( page.locator( '#welcome-license-hint' ) ).toHaveCount( 0 );
		await expect( submit ).toBeEnabled();
	} );

	test( 'shows a server error when activating an unrecognized LWSW- key', async ( { page, admin } ) => {
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		await page.locator( '#welcome-license-key-input' ).fill( 'LWSW-FAKE-INVALID-KEY' );
		await page.getByRole( 'button', { name: 'Activate' } ).click();

		await expect( page.locator( '#welcome-license-error' ) ).toBeVisible( { timeout: 15_000 } );
		await expect( page.locator( '#welcome-license-error' ) ).toContainText(
			"We couldn't verify this key."
		);
		// Welcome screen stays mounted — no transition to the products page.
		await expect( page.getByText( 'Your Features' ) ).toHaveCount( 0 );
	} );

	test( 'transitions to the products page on successful activation', async ( { page, admin } ) => {
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		await page.locator( '#welcome-license-key-input' ).fill( VALID_LICENSE_KEY );
		await page.getByRole( 'button', { name: 'Activate' } ).click();

		await expect( page.getByText( 'Your Features' ) ).toBeVisible( { timeout: 15_000 } );
		await expect( page.locator( `input[value="${ VALID_LICENSE_KEY }"]` ) ).toBeVisible();
	} );
} );
