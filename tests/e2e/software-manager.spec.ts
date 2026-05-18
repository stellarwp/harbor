import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { setLicense, clearLicense, VALID_LICENSE_KEY } from './_helpers/license';

const PRODUCT_NAMES = [ 'GiveWP', 'The Events Calendar', 'LearnDash', 'Kadence' ];

test.describe( 'Software Manager page', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setLicense( requestUtils, VALID_LICENSE_KEY );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await clearLicense( requestUtils );
	} );

	test( 'displays the "Your Features" heading after data loads', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=lw-software-manager' );

		await expect( page.getByText( 'Your Features' ) ).toBeVisible( {
			timeout: 15_000,
		} );
	} );

	test( 'renders a section for each product', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=lw-software-manager' );

		for ( const name of PRODUCT_NAMES ) {
			await expect( page.getByText( name ).first() ).toBeVisible( {
				timeout: 15_000,
			} );
		}
	} );

	test( 'renders the filter bar with product selector', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=lw-software-manager' );

		// The FilterBar renders a product filter combobox once the app loads
		await expect( page.getByRole( 'combobox' ).first() ).toBeVisible( {
			timeout: 15_000,
		} );
	} );

	test( 'shows the license key in the sidebar', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=lw-software-manager' );

		// The sidebar LicenseKeyInput renders the stored key in a read-only input once loaded
		await expect(
			page.locator( `input[value="${ VALID_LICENSE_KEY }"]` )
		).toBeVisible( { timeout: 15_000 } );
	} );
} );
