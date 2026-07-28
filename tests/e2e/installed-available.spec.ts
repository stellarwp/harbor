import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { Page, Locator } from '@playwright/test';
import { setLicense, clearLicense, VALID_LICENSE_KEY } from './_helpers/license';
import { installPlugin, GIVE_PLUGIN_FILE } from './_helpers/plugins';

const PAGE = { admin: 'options-general.php', query: 'page=lw-software-manager' };

// A unified key that grants only Kadence, so the other three products are
// unowned. Maps to tests/_data/licensing/lwsw-unified-kad-pro-2026.json.
const KADENCE_ONLY_LICENSE_KEY = 'LWSW-UNIFIED-KAD-PRO-2026';

const PRODUCT_SLUGS = [ 'give', 'the-events-calendar', 'learndash', 'kadence' ];

// The product section ids in the order they render in the DOM.
async function productOrder( page: Page ): Promise< string[] > {
	return page
		.locator( 'section[id]' )
		.evaluateAll(
			( nodes, slugs ) =>
				nodes.map( ( n ) => n.id ).filter( ( id ) => slugs.includes( id ) ),
			PRODUCT_SLUGS
		);
}

// Vertical position of an element, used to assert which section a product
// renders under (Installed above the Available heading, Available below it).
async function topY( locator: Locator ): Promise< number > {
	const box = await locator.boundingBox();
	if ( ! box ) {
		throw new Error( 'Element has no bounding box' );
	}
	return box.y;
}

test.describe( 'Installed vs Available Features', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await clearLicense( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		// clearLicense hits /reset, which also removes any stub plugins created
		// during the test.
		await clearLicense( requestUtils );
	} );

	test( 'renders every not-installed product under Available Features', async ( { page, admin, requestUtils } ) => {
		await setLicense( requestUtils, VALID_LICENSE_KEY );
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		await expect( page.getByText( 'Available Features' ) ).toBeVisible( { timeout: 15_000 } );
		// Nothing is installed, so there is no Installed Features section.
		await expect( page.getByText( 'Installed Features' ) ).toHaveCount( 0 );

		// The unified pro key owns all four, so they keep their catalog order.
		expect( await productOrder( page ) ).toEqual( PRODUCT_SLUGS );
	} );

	test( 'moves an installed product into Installed Features', async ( { page, admin, requestUtils } ) => {
		await setLicense( requestUtils, VALID_LICENSE_KEY );
		await installPlugin( requestUtils, GIVE_PLUGIN_FILE );

		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		const installedHeading = page.getByText( 'Installed Features' );
		const availableHeading = page.getByText( 'Available Features' );
		await expect( installedHeading ).toBeVisible( { timeout: 15_000 } );
		await expect( availableHeading ).toBeVisible();

		// GiveWP is installed, so it renders above the Available heading; Kadence
		// is not installed, so it renders below it.
		expect( await topY( page.locator( 'section#give' ) ) ).toBeLessThan( await topY( availableHeading ) );
		expect( await topY( page.locator( 'section#kadence' ) ) ).toBeGreaterThan( await topY( availableHeading ) );
	} );

	test( 'sorts owned-but-not-installed products above unowned ones in Available', async ( { page, admin, requestUtils } ) => {
		await setLicense( requestUtils, KADENCE_ONLY_LICENSE_KEY );
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		await expect( page.getByText( 'Available Features' ) ).toBeVisible( { timeout: 15_000 } );
		await expect( page.getByText( 'Installed Features' ) ).toHaveCount( 0 );

		// Kadence is owned but not installed, so it jumps to the top of Available,
		// above the three unowned products (which keep their catalog order).
		expect( await productOrder( page ) ).toEqual( [
			'kadence',
			'give',
			'the-events-calendar',
			'learndash',
		] );
	} );

	test( 'hides the license badge on Available cards but shows it once installed', async ( { page, admin, requestUtils } ) => {
		// Kadence-only key leaves GiveWP unowned, so its header would carry an
		// "Unlicensed" badge if it were not suppressed in the Available section.
		await setLicense( requestUtils, KADENCE_ONLY_LICENSE_KEY );
		await admin.visitAdminPage( PAGE.admin, PAGE.query );

		const giveBadge = page.locator( 'section#give' ).getByText( 'Unlicensed' );

		await expect( page.getByText( 'Available Features' ) ).toBeVisible( { timeout: 15_000 } );
		await expect( giveBadge ).toHaveCount( 0 );

		// Installing GiveWP moves it to Installed Features, where the badge shows.
		await installPlugin( requestUtils, GIVE_PLUGIN_FILE );
		await page.reload();

		await expect( page.getByText( 'Installed Features' ) ).toBeVisible( { timeout: 15_000 } );
		await expect( giveBadge ).toBeVisible();
	} );
} );
