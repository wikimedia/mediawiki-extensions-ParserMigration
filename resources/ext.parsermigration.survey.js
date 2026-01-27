const eligibleForSurvey = mw.user.isNamed() &&
	mw.config.get( 'wgNamespaceNumber' ) === 0 &&
	mw.user.options.get( 'parsermigration-parsoid-readviews' ) === '2';
const viewingLegacy = document.querySelectorAll( '.mw-parser-output[data-mw-parsoid-version]' ).length === 0;
const bodyContent = document.getElementById( 'bodyContent' );
if ( eligibleForSurvey && viewingLegacy && bodyContent ) {
	const surveyPlaceholder = document.createElement( 'div' );
	surveyPlaceholder.id = 'parsermigration-survey-placeholder';
	bodyContent.appendChild( surveyPlaceholder );
	mw.requestIdleCallback( () => {
		mw.loader.using( 'ext.quicksurveys.lib' ).then( ( require ) => {
			// Will show survey if the user is in sample in the element with ID 'foo'
			require( 'ext.quicksurveys.lib' ).showSurvey(
				'parsoid-migration-survey-2026',
				'parsermigration-survey-placeholder'
			);
		} );
	} );
}
