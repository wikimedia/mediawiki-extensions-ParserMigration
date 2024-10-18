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
		// Build the message content from scratch in order to match/use Codex
		// styles for a "notice" Message
		var $title = $( '<p>' ).append(
			$( '<strong>' ).html(
				mw.message( 'parsermigration-notice-title' ).parse()
			)
		);
		var $body = $( '<p>' ).html(
			mw.message( 'parsermigration-notice-body' ).parse()
		);
		var msgBody = document.createElement( 'div' );
		msgBody.appendChild( $title[ 0 ] );
		msgBody.appendChild( $body[ 0 ] );

		var $content = $( mw.util.messageBox( msgBody, 'notice', true ) );
		// mw.util.messageBox currently does not support close button so we have to append it.
		$content.append(
			$( '<span>' ).addClass( 'cdx-button__icon parsermigration-notice-icon-close' )
		);
		mw.notify(
			$content,
			{
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
