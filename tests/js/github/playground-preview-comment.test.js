const { buildBlueprint, buildComment } = require( '../../../.github/scripts/playground-preview-comment' );

const ZIP_URL = 'https://evnt.is/test-zip?file=harbor-playground-pr199-abc1234.zip';

/**
 * Decodes the blueprints out of the buttons in a comment body.
 */
function blueprintsIn( body ) {
    return [ ...body.matchAll( /blueprint-url=data:application\/json,([^"]+)"/g ) ].map(
        ( match ) => JSON.parse( decodeURIComponent( match[ 1 ] ) )
    );
}

describe( 'buildBlueprint', () => {
    it( 'installs Harbor Dev Tools from the zip on the pro fixture key', () => {
        const blueprint = buildBlueprint( { zipUrl: ZIP_URL, withGive: false } );

        expect( blueprint.landingPage ).toBe( '/wp-admin/options-general.php?page=lw-software-manager' );
        expect( blueprint.steps.map( ( step ) => step.step ) ).toEqual( [
            'defineWpConfigConsts',
            'login',
            'setSiteOptions',
            'installPlugin',
        ] );
        expect( blueprint.steps[ 2 ].options ).toEqual( { lw_harbor_dev_tools_fixture_key: 'lwsw-unified-pro-2026' } );
        expect( blueprint.steps[ 3 ].pluginData ).toEqual( { resource: 'url', url: ZIP_URL } );
        expect( blueprint.steps[ 3 ].options.targetFolderName ).toBe( 'harbor-dev-tools' );
    } );

    it( 'installs GiveWP after Harbor Dev Tools when asked', () => {
        const blueprint = buildBlueprint( { zipUrl: ZIP_URL, withGive: true } );

        expect( blueprint.steps ).toHaveLength( 5 );
        expect( blueprint.steps[ 4 ].pluginData ).toEqual( { resource: 'wordpress.org/plugins', slug: 'give' } );
    } );
} );

describe( 'buildComment', () => {
    const body = buildComment( { zipUrl: ZIP_URL, sha: 'abc1234def', builtAt: new Date( '2026-10-01T18:44:09Z' ) } );

    it( 'says what was built and when', () => {
        expect( body ).toContain( 'Built 2026-10-01 18:44 UTC from abc1234def' );
        expect( body ).toContain( `[download the zip](${ ZIP_URL })` );
    } );

    it( 'carries one button per blueprint, each decoding back to that blueprint', () => {
        expect( blueprintsIn( body ) ).toEqual( [
            buildBlueprint( { zipUrl: ZIP_URL, withGive: false } ),
            buildBlueprint( { zipUrl: ZIP_URL, withGive: true } ),
        ] );
    } );
} );
