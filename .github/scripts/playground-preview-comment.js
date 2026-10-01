// cspell:ignore adamziel noopener noreferrer
/**
 * Builds the pull request comment for the Playground Preview workflow.
 *
 * It touches neither GitHub nor the filesystem, so it can be unit tested and
 * reused by another repository's preview workflow.
 */

const PLAYGROUND_URL = 'https://playground.wordpress.net/?blueprint-url=data:application/json,';
const BUTTON_IMAGE   = 'https://raw.githubusercontent.com/adamziel/playground-preview/refs/heads/trunk/assets/playground-preview-button.svg';

/*
 * The Software Manager page lists the four product brands only. Harbor Dev
 * Tools' default fixture key describes a product named test-fixtures, which
 * that page does not show, so the site starts on a fixture that covers the
 * real brands.
 */
const FIXTURE_KEY = 'lwsw-unified-pro-2026';

/*
 * Free plugins on WordPress.org that bundle their own copy of Harbor. Each gets
 * a button that installs its latest release beside Harbor Dev Tools, to check a
 * change with two Harbor hosts on one site. LearnDash is not here because it
 * is premium and cannot be downloaded without a license.
 */
const HOSTS = [
    { name: 'GiveWP', slug: 'give' },
    { name: 'The Events Calendar', slug: 'the-events-calendar' },
    { name: 'Kadence Blocks', slug: 'kadence-blocks' },
];

/**
 * Builds the Playground blueprint for one preview button.
 *
 * @param {Object}      args
 * @param {string}      args.zipUrl Public URL of the Harbor Dev Tools zip built for the pull request.
 * @param {string|null} args.host   WordPress.org slug of a plugin to install as well, if any.
 *
 * @return {Object} The blueprint.
 */
function buildBlueprint( { zipUrl, host = null } ) {
    const steps = [
        /*
         * Debug output goes to the log instead of the screen: a released host
         * plugin can bundle an older Harbor, and Playground reports any notice
         * printed during its activation as unexpected output.
         */
        { step: 'defineWpConfigConsts', consts: { WP_DEBUG: true, WP_DEBUG_LOG: true, WP_DEBUG_DISPLAY: false } },
        { step: 'login', username: 'admin' },
        { step: 'setSiteOptions', options: { lw_harbor_dev_tools_fixture_key: FIXTURE_KEY } },
        {
            step: 'installPlugin',
            pluginData: { resource: 'url', url: zipUrl },
            options: { activate: true, targetFolderName: 'harbor-dev-tools' },
        },
    ];

    if ( host ) {
        steps.push( {
            step: 'installPlugin',
            pluginData: { resource: 'wordpress.org/plugins', slug: host },
            options: { activate: true },
        } );
    }

    return {
        landingPage: '/wp-admin/options-general.php?page=lw-software-manager',
        preferredVersions: { php: '8.3', wp: 'latest' },
        features: { networking: true },
        steps,
    };
}

/**
 * Builds the HTML for one preview button.
 *
 * @param {Object} blueprint The blueprint the button opens.
 * @param {string} label     Alternative text for the button image.
 *
 * @return {string} The button markup.
 */
function buildButton( blueprint, label ) {
    const href = PLAYGROUND_URL + encodeURIComponent( JSON.stringify( blueprint ) );

    return `<a href="${ href }" target="_blank" rel="noopener noreferrer"><img src="${ BUTTON_IMAGE }" alt="${ label }" width="220" height="57" /></a>`;
}

/**
 * Builds the comment body.
 *
 * @param {Object} args
 * @param {string} args.zipUrl  Public URL of the Harbor Dev Tools zip built for the pull request.
 * @param {string} args.sha     Commit the zip was built from.
 * @param {Date}   args.builtAt When the zip was built.
 *
 * @return {string} The comment body, as Markdown.
 */
function buildComment( { zipUrl, sha, builtAt } ) {
    const built = builtAt.toISOString().slice( 0, 16 ).replace( 'T', ' ' ) + ' UTC';
    const plain = buildButton( buildBlueprint( { zipUrl } ), 'Open WordPress Playground Preview' );
    const hosts = HOSTS.flatMap( ( { name, slug } ) => [
        `**With ${ name }**`,
        '',
        buildButton( buildBlueprint( { zipUrl, host: slug } ), `Open WordPress Playground Preview with ${ name }` ),
        '',
    ] );

    return [
        '### WordPress Playground Preview',
        '',
        `Try this pull request in a throwaway WordPress in your browser. Harbor Dev Tools runs this branch of Harbor as the leader, licensed with the ${ FIXTURE_KEY } fixture key. Free features can be enabled; premium ones cannot, because their downloads need a real key. Built ${ built } from ${ sha }, or [download the zip](${ zipUrl }) for a site of your own.`,
        '',
        '**Harbor Dev Tools only**',
        '',
        plain,
        '',
        'Each button below also installs the latest release of one plugin beside Harbor Dev Tools. That plugin bundles its own copy of Harbor, and Harbor Dev Tools stays the leader.',
        '',
        ...hosts,
    ].join( '\n' );
}

module.exports = { HOSTS, buildBlueprint, buildComment };
