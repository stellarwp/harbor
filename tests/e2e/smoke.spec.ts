import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Smoke', () => {
	test( 'renders the React app root', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=lw-software-manager' );

		// #lw-harbor-root is a zero-height mount point — the React tree
		// inside renders into an absolutely-positioned Shell — so assert
		// attachment, not visibility.
		await expect( page.locator( '#lw-harbor-root' ) ).toBeAttached();
		await expect( page.locator( '#lw-harbor-root' ) ).not.toBeEmpty();
	} );

	test( 'shows the Liquid Web Products menu when a premium plugin is registered', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect(
			page.locator( '#adminmenu' ).getByRole( 'link', { name: 'Liquid Web Products' } )
		).toBeVisible();
	} );

	test( 'hides the Liquid Web Products menu when no premium plugin reports itself', async ( { page, admin } ) => {
		// The fixture plugin's lw_harbor/premium_plugin_exists callback returns
		// false when this query param is present, mirroring a site with no
		// Harbor-aware premium plugin installed.
		await admin.visitAdminPage( 'index.php', 'lw_harbor_no_premium_exists=1' );

		await expect(
			page.locator( '#adminmenu' ).getByRole( 'link', { name: 'Liquid Web Products' } )
		).toHaveCount( 0 );
	} );
} );
