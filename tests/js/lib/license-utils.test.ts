// cspell:ignore EFGH IJKL MNOP DJJT -- illustrative masking example fragments
import { maskLicenseKey } from '@/lib/license-utils';

describe( 'maskLicenseKey', () => {
    it( 'keeps the prefix and final segment visible while masking the middle', () => {
        expect( maskLicenseKey( 'LWSW-ABCD-EFGH-IJKL-MNOP-DJJT' ) ).toBe(
            'LWSW-XXXX-XXXX-XXXX-XXXX-DJJT'
        );
    } );

    it( 'masks middle segments using X\'s of matching length', () => {
        expect( maskLicenseKey( 'LWSW-UNIFIED-PRO-2026' ) ).toBe( 'LWSW-XXXXXXX-XXX-2026' );
    } );

    it( 'returns the key unchanged when there is no middle to mask', () => {
        expect( maskLicenseKey( 'LWSW-ABCD' ) ).toBe( 'LWSW-ABCD' );
        expect( maskLicenseKey( 'LWSW' ) ).toBe( 'LWSW' );
        expect( maskLicenseKey( '' ) ).toBe( '' );
    } );
} );
