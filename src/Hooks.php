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

		// T335157: Enable Parsoid Read Views for articles as an experimental
		// feature; this is primarily used for internal testing at this time.
		$queryStringPresent = $article->getContext()->getRequest()->getRawVal( 'useparsoid' );

		// Allow disabling via config change to manage parser cache usage
		$queryStringEnabled = \RequestContext::getMain()->getConfig()->get( 'ParserMigrationEnableQueryString' );

		if ( $userOptIn || ( $queryStringPresent && $queryStringEnabled ) ) {
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
		if ( $out->isArticleRelated() && $userOptionsManager->getOption( $user, 'parsermigration' ) ) {
			$sidebar['TOOLBOX']['parsermigration'] = [
				'href' => $title->getLocalURL( [ 'action' => 'parsermigration-edit' ] ),
				'text' => $skin->msg( 'parsermigration-toolbox-label' )->text(),
			];
		}
	}
}
