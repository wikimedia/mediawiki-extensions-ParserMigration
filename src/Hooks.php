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
		$request = $article->getContext()->getRequest();
		$queryEnable = $request->getRawVal( 'useparsoid' );
		if ( $queryEnable ) {
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
