import * as activationEntry from '@/activation-entry';
import { buildActivationUrl } from '@/lib/activation-url';

// This entry is a public surface: it compiles to window.lwHarbor and is
// consumed by host plugins' onboarding screens, which cannot be updated in
// lockstep with Harbor. Renaming or dropping an export here silently breaks
// them, so the shape is asserted rather than assumed.
describe( 'activation entry', () => {
    it( 'exports buildActivationUrl', () => {
        expect( activationEntry.buildActivationUrl ).toBe( buildActivationUrl );
    } );

    it( 'exports nothing else — the global surface stays minimal', () => {
        expect( Object.keys( activationEntry ) ).toEqual( [ 'buildActivationUrl' ] );
    } );

    it( 'pulls in no dependencies that would need enqueuing alongside it', () => {
        // The entry is registered with an empty deps array, so anything it
        // imports must be inlined rather than expected as a WP script handle.
        const source = require( 'fs' ).readFileSync(
            require( 'path' ).resolve( __dirname, '../../resources/js/activation-entry.ts' ),
            'utf8'
        );

        expect( source ).not.toMatch( /from\s+'@wordpress\// );
        expect( source ).not.toMatch( /from\s+'react/ );
    } );
} );
