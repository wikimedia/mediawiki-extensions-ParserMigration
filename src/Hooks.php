<?php

namespace MediaWiki\Extension\ParserMigration;

use Article;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleParserOptionsHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use ParserOptions;

class Hooks implements
	GetPreferencesHook,
	SidebarBeforeOutputHook,
	ArticleParserOptionsHook
{
	/**
	 * @param \User $user
	 * @param array &$defaultPreferences
	 * @return bool
	 */
	public function onGetPreferences( $user, &$defaultPreferences ) {
		$defaultPreferences['parsermigration'] = [
			'type' => 'toggle',
			'label-message' => 'parsermigration-pref-label',
			'help-message' => 'parsermigration-pref-help',
			'section' => 'editing/developertools'
			];

		$defaultPreferences['parsermigration-parsoid-readviews'] = [
			'type' => 'toggle',
			'label-message' => 'parsermigration-parsoid-readviews-pref-label',
			'help-message' => 'parsermigration-parsoid-readviews-pref-help',
			'section' => 'editing/developertools'
		];
		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleParserOptions
	 * @param Article $article
	 * @param ParserOptions $popts
	 * @return bool|void
	 */
	public function onArticleParserOptions(
		Article $article, ParserOptions $popts
	) {
		// T348257: Allow individual user to opt in to Parsoid read views as a ParserMigration option
		$user = $article->getContext()->getUser();
		$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$userOptIn = $userOptionsManager->getOption( $user, 'parsermigration-parsoid-readviews' );

		// Allow disabling via config change to manage parser cache usage
		$queryStringEnabled = \RequestContext::getMain()->getConfig()->get( 'ParserMigrationEnableQueryString' );

		// If user preference opts in to default Parsoid parses, and no url useparsoid parameter is defined
		// or if parser migration is enabled, and useparsoid parameter is defined as true, use Parsoid
		$request = $article->getContext()->getRequest();
		if ( ( $userOptIn && $request->getFuzzyBool( 'useparsoid', true ) ) ||
			( $queryStringEnabled && $request->getFuzzyBool( 'useparsoid', false ) ) ) {
			$popts->setUseParsoid();
		}
		return true;
	}

	/**
	 * @param \Skin $skin
	 * @param array &$sidebar Sidebar content
	 * @return void
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$out = $skin->getOutput();
		$title = $skin->getTitle();
		$user = $skin->getUser();
		$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$userOptIn = $userOptionsManager->getOption( $user, 'parsermigration-parsoid-readviews' );

		if ( $out->isArticleRelated() &&
			( $userOptIn || $userOptionsManager->getOption( $user, 'parsermigration' ) ) ) {
			$sidebar['TOOLBOX']['parsermigration'] = [
				'href' => $title->getLocalURL( [ 'action' => 'parsermigration-edit' ] ),
				'text' => $skin->msg( 'parsermigration-toolbox-label' )->text(),
			];

			$useParsoid = $skin->getRequest()->getVal( 'useparsoid' );
			// if the user preference is set to opt in to default parsoid parses, and the current request url
			// has not set the useparsoid parameter,
			// or the current request url has set the useparsoid parameter to true,
			// set the toolbox link to legacy parser rendering, otherwise set the link to Parsoid rendering
			if ( ( $userOptIn && $useParsoid === null ) || $useParsoid === '1' ) {
				$sidebar[ 'TOOLBOX' ][ 'parser' ] = [
					'href' => $title->getLocalURL( [ 'useparsoid' => '0' ] ),
					'text' => $skin->msg( 'parsermigration-use-legacy-parser-toolbox-label' )->text(),
				];
			} else {
				$sidebar[ 'TOOLBOX' ][ 'parser' ] = [
					'href' => $title->getLocalURL( [ 'useparsoid' => '1' ] ),
					'text' => $skin->msg( 'parsermigration-use-parsoid-toolbox-label' )->text(),
				];
			}
		}
	}
}
