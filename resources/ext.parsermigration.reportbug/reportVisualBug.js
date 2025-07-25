const overlayContainer = document.createElement( 'div' );
const ReportVisualBugDialog = require( './ReportVisualBug.vue' );
const Vue = require( 'vue' );

module.exports = {
	setup: function () {
		if ( !this.feedbackTitle ) {
			// Get our configuration settings and resolve the appropriate
			// URL to use for the given feedback title
			const config = mw.config.get( 'wgParserMigrationConfig' );

			this.isMobile = config.isMobile;

			this.foreignApi = config.feedbackApiUrl ?
				new mw.ForeignApi( config.feedbackApiUrl, { anonymous: true } ) : null;
			this.feedbackTitle = new mw.Title(
				( this.foreignApi || config.feedbackTitle ) ?
					config.feedbackTitle :
					mw.msg( 'parsermigration-reportbug-feedback-title' )
			);
			if ( this.foreignApi ) {
				this.prefix = config.iwp + ':';
				this.feedbackUrlPromise = this.foreignApi.get( {
					action: 'query',
					prop: 'info',
					inprop: 'url',
					formatversion: 2,
					titles: this.feedbackTitle.getPrefixedText()
				} ).then(
					( response ) => response.query.pages[ 0 ].canonicalurl
				);
			} else {
				this.prefix = '';
				this.feedbackUrlPromise = Promise.resolve(
					this.feedbackTitle.getUrl()
				);
			}
			this.messagePosterPromise = mw.user.getRights().then(
				( rights ) => {
					this.canUseTags = ( rights.includes( 'applychangetags' ) );
					return mw.messagePoster.factory.create(
						this.feedbackTitle, config.feedbackApiUrl
					);
				}
			);
		}
	},

	/**
	 * Opens the dialog and builds it if necessary.
	 */
	openDialog: function () {
		if ( !this.dialog ) {
			document.body.appendChild( overlayContainer );
			this.dialog = Vue.createMwApp( ReportVisualBugDialog, {
				// When the dialog emits the 'submit' event, invoke
				// onSubmit(subject, message)
				onSubmit: this.onSubmit.bind( this )
			} ).mount( overlayContainer );
		}
		if ( !this.feedbackTitle ) {
			this.setup();
		}
		this.dialog.feedbackTitle = this.feedbackTitle.getPrefixedText();
		this.dialog.isMobile = this.isMobile;
		// Set the link URL, which may be on a foreign wiki.
		this.feedbackUrlPromise.then( ( url ) => {
			this.dialog.feedbackUrl = url;
		} );

		this.dialog.start(
			mw.config.get( 'wgPageName' ).replace( /_/g, ' ' ),
			mw.config.get( 'wgRevisionId' )
		);
		// The dialog will invoke 'onSubmit' when the user has
		// written & submitted their bug report.
	},

	onSubmit: function ( pageName, revisionId, message ) {
		this.messagePosterPromise.then(
			( poster ) => this.postMessage( poster, pageName, revisionId, message ),
			() => {
				this.status = 'error4';
				mw.log.warn( 'Report visual bug failed because MessagePoster could not be fetched' );
				throw this.getErrorMessage();
			}
		).then(
			() => {
				/* success */
				this.dialog.reportSuccess();
			},
			( e ) => {
				/* failure */
				this.dialog.reportFailure( e.message );
			}
		);
	},

	// Attempt to post the given subject & message, throwing an exception
	// on failure.
	postMessage: function ( poster, pageName, revisionId, message ) {
		const thisTitle = new mw.Title( pageName );
		const thisTitleUrl = new URL(
			thisTitle.getUrl( {
				oldid: revisionId,
				useparsoid: 1
			} ),
			location.href
		);
		return poster.post(
			// This is a link-ified version of the subject line that
			// we displayed to the user; note that we're ignoring the
			// subject argument we were given.
			'[' + thisTitleUrl.href + ' ' + this.prefix + thisTitle.getPrefixedText() + ']',
			// This is the user's report message.  It is assumed to be
			// wikitext.
			message,
			this.canUseTags ? {
				tags: 'parsermigration-visual-bug'
			} : {}
		).then( () => {
			this.status = 'submitted';
		}, ( mainCode, secondaryCode, details ) => {
			if ( mainCode === 'api-fail' ) {
				if ( secondaryCode === 'http' ) {
					this.status = 'error3';
					// ajax request failed
					mw.log.warn( 'Report Visual Bug report failed with HTTP error: ' + details.textStatus );
				} else {
					this.status = 'error2';
					mw.log.warn( 'Report Visual Bug report failed with API error: ' + secondaryCode );
				}
				this.$statusFromApi = ( new mw.Api() ).getErrorMessage( details );
			} else {
				// Some other failure.
				this.status = 'error1';
			}
			throw this.getErrorMessage();
		} );
	},

	getErrorMessage: function () {
		if ( this.$statusFromApi ) {
			return new Error( this.$statusFromApi.text() );
		}
		// The following messages can be used here:
		// * parsermigration-feedback-error1
		// * parsermigration-feedback-error4
		return new Error( mw.msg( 'parsermigration-feedback-' + this.status ) );
	}

};
