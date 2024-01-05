<?php

namespace MediaWiki\Extension\ParserMigration;

use Article;
use MediaWiki\Config\Config;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Page\Hook\ArticleParserOptionsHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use ParserOptions;
use Skin;

class Hooks implements
	GetPreferencesHook,
	SidebarBeforeOutputHook,
	ArticleParserOptionsHook
{

	private Config $mainConfig;
	private UserOptionsManager $userOptionsManager;

	/**
	 * @param Config $mainConfig
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		Config $mainConfig,
		UserOptionsManager $userOptionsManager
	) {
		$this->mainConfig = $mainConfig;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @param User $user
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
		// T348257: Allow individual user to opt in to Parsoid read views as a
		// use option in the ParserMigration section.
		$context = $article->getContext();
		if ( $this->shouldUseParsoid( $context->getUser(), $context->getRequest() ) ) {
			$popts->setUseParsoid();
		}
		return true;
	}

	/**
	 * @param Skin $skin
	 * @param array &$sidebar Sidebar content
	 * @return void
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$out = $skin->getOutput();
		if ( !$out->isArticleRelated() ) {
			// Only add sidebar links before article-related pages
			return;
		}

		$user = $skin->getUser();
		$title = $skin->getTitle();

		$editToolPref = $this->userOptionsManager->getOption(
			$user, 'parsermigration'
		);
		$parsoidDefaultPref = $this->userOptionsManager->getOption(
			$user, 'parsermigration-parsoid-readviews'
		);
		$queryStringEnabled = $this->mainConfig->get(
			'ParserMigrationEnableQueryString'
		);

		if ( $editToolPref ) {
			$sidebar['TOOLBOX']['parsermigration'] = [
				'href' => $title->getLocalURL( [
					'action' => 'parsermigration-edit',
				] ),
				'text' => $skin->msg( 'parsermigration-toolbox-label' )->text(),
			];
		}

		if ( $queryStringEnabled && ( $editToolPref || $parsoidDefaultPref ) ) {
			$usingParsoid = $this->shouldUseParsoid( $user, $skin->getRequest() );
			$sidebar[ 'TOOLBOX' ][ 'parsermigration' ] = [
				'href' => $title->getLocalURL( [
					// Allow toggling 'useParsoid' from the current state
					'useparsoid' => $usingParsoid ? '0' : '1',
				] ),
				'text' => $skin->msg(
					$usingParsoid ?
					'parsermigration-use-legacy-parser-toolbox-label' :
					'parsermigration-use-parsoid-toolbox-label'
				)->text(),
			];
		}
	}

	/**
	 * Determine whether to use Parsoid for read views on this request,
	 * request, based on the user's preferences and the URL query string.
	 *
	 * @param User $user
	 * @param WebRequest $request
	 * @return bool True if Parsoid should be used for this request
	 */
	private function shouldUseParsoid( User $user, WebRequest $request ): bool {
		// Find out if the user has opted in to Parsoid Read Views by default
		$userOptIn = $this->userOptionsManager->getOption(
			$user,
			'parsermigration-parsoid-readviews'
		);

		// Allow disabling query string handling via config change to manage
		// parser cache usage.
		$queryStringEnabled = $this->mainConfig->get(
			'ParserMigrationEnableQueryString'
		);
		if ( !$queryStringEnabled ) {
			// Ignore query string and use Parsoid read views if and only
			// if the user has opted in.
			return $userOptIn;
		}

		// Otherwise, use the user's opt-in status to set the default for
		// query string processing.
		return $request->getFuzzyBool( 'useparsoid', $userOptIn );
	}
}
