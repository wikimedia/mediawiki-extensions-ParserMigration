const moduleName = 'ext.parsermigration.reportbug.dialog';
const debugging = false;

function ensureLoaded() {
	return mw.loader
		.using( moduleName )
		.then( ( require ) => {
			if ( !$.reportVisualBug ) {
				$.reportVisualBug = require( moduleName );
			}
		} );
}

function init() {
	const config = mw.config.get( 'wgParserMigrationConfig' );
	// Unnamed users get pointed to the feedback page but w/o launching the
	// 'report visual bug' tool.
	if ( config.onlyLoggedIn && !mw.user.isNamed() ) {
		return;
	}

	const $reportVisualBugLink = $(
		// Most skins
		'#parsermigration-report-bug a, ' +
		// Mobile (MinervaNeue)
		'a.menu__item--page-actions-overflow-parsermigration-report-bug'
	);

	$reportVisualBugLink.off( 'click' );
	$reportVisualBugLink.on( 'click', ( e ) => {
		e.preventDefault();
		ensureLoaded().then( () => $.reportVisualBug.openDialog() );
	} );

	if ( debugging ) {
		setTimeout( () => {
			ensureLoaded().then( () => $.reportVisualBug.openDialog() );
		}, 200 );
	}
}

// Link this up once the DOM is loaded.
$( init );
