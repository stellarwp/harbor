import {
	areAllProductsNotActivated,
	getLicenseProducts,
	isProductLicenseValid,
	isProductUnifiedLicensed,
} from '@/store/selectors';
import type { State } from '@/store/types';
import type { LicenseProduct } from '@/types/api';

function makeProduct( overrides: Partial< LicenseProduct > = {} ): LicenseProduct {
	return {
		product_slug:      'givewp',
		tier:              'plus',
		status:            'licensed',
		expires:           '2026-01-01',
		activations:       { site_limit: 1, active_count: 1, over_limit: false, domains: [] },
		capabilities:      [],
		validation_status: 'valid',
		is_valid:          true,
		activated_here:    true,
		...overrides,
	};
}

function makeState( products: LicenseProduct[] ): State {
	return {
		license: {
			license:      { key: 'LWSW-TEST', products, error: null },
			isStoring:    false,
			isDeleting:   false,
			isRefreshing: false,
			storeError:   null,
			deleteError:  null,
			refreshError: null,
		},
		features:       { bySlug: {}, toggling: {}, updating: {}, errorBySlug: {} },
		harborHosts:    { basenames: [] },
		catalog:        { byProductSlug: {} },
		legacyLicenses: { bySlug: {} },
	} as State;
}

// ---------------------------------------------------------------------------
// areAllProductsNotActivated
// ---------------------------------------------------------------------------

describe( 'areAllProductsNotActivated', () => {
	it( 'returns true when all non-cancelled products are unactivated', () => {
		const state = makeState( [
			makeProduct( { status: 'unactivated', validation_status: 'not_activated', is_valid: false, activated_here: false } ),
			makeProduct( { status: 'cancelled',   validation_status: 'cancelled' } ),
		] );

		expect( areAllProductsNotActivated( state ) ).toBe( true );
	} );

	it( 'returns false when the only products are cancelled', () => {
		const state = makeState( [
			makeProduct( { status: 'cancelled', validation_status: 'cancelled' } ),
		] );

		expect( areAllProductsNotActivated( state ) ).toBe( false );
	} );

	it( 'returns false when a non-cancelled product is active', () => {
		const state = makeState( [
			makeProduct( { status: 'unactivated', validation_status: 'not_activated', is_valid: false, activated_here: false } ),
			makeProduct( { product_slug: 'kadence', status: 'licensed', validation_status: 'valid' } ),
		] );

		expect( areAllProductsNotActivated( state ) ).toBe( false );
	} );

	it( 'returns false when there are no products', () => {
		expect( areAllProductsNotActivated( makeState( [] ) ) ).toBe( false );
	} );
} );

// ---------------------------------------------------------------------------
// getLicenseProducts
// ---------------------------------------------------------------------------

describe( 'getLicenseProducts', () => {
	it( 'excludes cancelled products', () => {
		const state = makeState( [
			makeProduct( { product_slug: 'givewp',  status: 'licensed' } ),
			makeProduct( { product_slug: 'kadence', status: 'cancelled' } ),
		] );

		const result = getLicenseProducts( state );

		expect( result ).toHaveLength( 1 );
		expect( result[ 0 ].product_slug ).toBe( 'givewp' );
	} );

	it( 'sorts activated-here products first', () => {
		const state = makeState( [
			makeProduct( { product_slug: 'kadence', activated_here: false } ),
			makeProduct( { product_slug: 'givewp',  activated_here: true } ),
		] );

		const result = getLicenseProducts( state );

		expect( result[ 0 ].product_slug ).toBe( 'givewp' );
		expect( result[ 1 ].product_slug ).toBe( 'kadence' );
	} );
} );

// ---------------------------------------------------------------------------
// isProductUnifiedLicensed
// ---------------------------------------------------------------------------

describe( 'isProductUnifiedLicensed', () => {
	it( 'returns false for a product that is only present as cancelled', () => {
		const state = makeState( [
			makeProduct( { product_slug: 'givewp', status: 'cancelled' } ),
		] );

		expect( isProductUnifiedLicensed( state, 'givewp' ) ).toBe( false );
	} );

	it( 'returns true for a non-cancelled product', () => {
		const state = makeState( [
			makeProduct( { product_slug: 'givewp', status: 'licensed' } ),
		] );

		expect( isProductUnifiedLicensed( state, 'givewp' ) ).toBe( true );
	} );
} );

// ---------------------------------------------------------------------------
// isProductLicenseValid
// ---------------------------------------------------------------------------

describe( 'isProductLicenseValid', () => {
	it( 'returns false for a cancelled product even when is_valid is true', () => {
		const state = makeState( [
			makeProduct( { product_slug: 'givewp', status: 'cancelled', is_valid: true } ),
		] );

		expect( isProductLicenseValid( state, 'givewp' ) ).toBe( false );
	} );

	it( 'returns true for a non-cancelled valid product', () => {
		const state = makeState( [
			makeProduct( { product_slug: 'givewp', status: 'licensed', is_valid: true } ),
		] );

		expect( isProductLicenseValid( state, 'givewp' ) ).toBe( true );
	} );
} );
