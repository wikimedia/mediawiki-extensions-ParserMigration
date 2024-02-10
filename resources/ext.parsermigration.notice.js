/*!
* Add a user notice the first time they use parsoid-generated HTML.
*/
( function () {
	// Bump this number any time you've made a significant change to the
	// notice and want to redisplay it to the user.
	var NOTICE_VERSION = mw.config.get( 'parsermigration-notice-version' );

	// Don't show this message more than once/week.
	var NOTICE_EXPIRY_SECONDS = mw.config.get( 'parsermigration-notice-days' ) *
		60 * 60 * 24;

	var STORAGE_KEY = 'mw-ext-parsermigration-notice-version';

	mw.hook( 'wikipage.content' ).add( function () {
		// Only display this notice if we're looking at parsoid content
		if ( !mw.config.get( 'parsermigration-parsoid' ) ) {
			return;
		}
		// Only display this notice once/week
		var seen = mw.storage.getObject( STORAGE_KEY );
		if ( seen === false ) {
			return; // storage is not available
		}
		if ( seen === NOTICE_VERSION ) {
			return; // Already seen this version
		}
		mw.notify(
			mw.message( 'parsermigration-notice-body' ),
			{
				title: mw.message( 'parsermigration-notice-title' ),
				type: 'info',
				autoHide: false,
				tag: 'parsermigration-notice'
			}
		).then( function ( notif ) {
			// hook the close method
			var oldClose = notif.close;
			notif.close = function () {
				// Once close, stay closed
				mw.storage.setObject(
					STORAGE_KEY, NOTICE_VERSION,
					NOTICE_EXPIRY_SECONDS || undefined
				);
				oldClose.apply( this, arguments );
			};
		} ).done();
	} );
}() );
