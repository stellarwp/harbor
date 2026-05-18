import { getHarborDataValue, getLicenseKeyPlaceholder } from '@/lib/harbor-data';
import type { HarborData } from '@/types/harbor-data';

const FIXTURE: HarborData = {
    restUrl:          '/wp-json/liquidweb/harbor/v1/',
    nonce:            'nonce-value',
    pluginsUrl:       '/wp-admin/plugins.php?from=fixture',
    activationUrl:    'https://portal.example.com/activate',
    subscriptionsUrl: 'https://portal.example.com/subscriptions',
    domain:           'fixture.example.com',
    version:          '9.9.9',
    licenseKeyPrefix: 'FIXTURE-',
};

describe( 'getHarborDataValue', () => {
    afterEach( () => {
        delete window.harborData;
    } );

    it( 'returns the live value when window.harborData[key] is set', () => {
        window.harborData = FIXTURE;
        expect( getHarborDataValue( 'licenseKeyPrefix' ) ).toBe( 'FIXTURE-' );
        expect( getHarborDataValue( 'pluginsUrl' ) ).toBe( '/wp-admin/plugins.php?from=fixture' );
        expect( getHarborDataValue( 'restUrl' ) ).toBe( '/wp-json/liquidweb/harbor/v1/' );
    } );

    it( 'falls back to the built-in default when window.harborData is absent', () => {
        expect( getHarborDataValue( 'licenseKeyPrefix' ) ).toBe( 'LWSW-' );
        expect( getHarborDataValue( 'pluginsUrl' ) ).toBe( '/wp-admin/plugins.php' );
    } );

    it( 'returns null for keys with no default when the global is absent', () => {
        expect( getHarborDataValue( 'restUrl' ) ).toBeNull();
        expect( getHarborDataValue( 'domain' ) ).toBeNull();
        expect( getHarborDataValue( 'version' ) ).toBeNull();
    } );

    it( 'uses an explicit per-call fallback before the built-in default', () => {
        expect( getHarborDataValue( 'pluginsUrl', '/custom/path.php' ) ).toBe( '/custom/path.php' );
        expect( getHarborDataValue( 'licenseKeyPrefix', 'OTHER-' ) ).toBe( 'OTHER-' );
    } );

    it( 'still prefers the live value over a per-call fallback', () => {
        window.harborData = FIXTURE;
        expect( getHarborDataValue( 'pluginsUrl', '/custom/path.php' ) ).toBe( '/wp-admin/plugins.php?from=fixture' );
    } );

    it( 'does not throw when window itself has no harborData', () => {
        // jsdom always provides `window`, so we simulate the "absent" case by
        // deleting the property — same path as a fresh page load with no
        // localize_script call.
        delete window.harborData;
        expect( () => getHarborDataValue( 'restUrl' ) ).not.toThrow();
        expect( getHarborDataValue( 'restUrl' ) ).toBeNull();
    } );
} );

describe( 'getLicenseKeyPlaceholder', () => {
    afterEach( () => {
        delete window.harborData;
    } );

    it( 'returns the configured prefix followed by five XXXX groups', () => {
        window.harborData = FIXTURE;
        expect( getLicenseKeyPlaceholder() ).toBe( 'FIXTURE-XXXX-XXXX-XXXX-XXXX-XXXX' );
    } );

    it( 'falls back to the default LWSW- prefix when window.harborData is absent', () => {
        expect( getLicenseKeyPlaceholder() ).toBe( 'LWSW-XXXX-XXXX-XXXX-XXXX-XXXX' );
    } );
} );
