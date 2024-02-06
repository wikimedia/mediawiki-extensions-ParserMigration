<?php

namespace MediaWiki\Extension\ParserMigration;

use Article;
use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserOutputPostCacheTransformHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Page\Hook\ArticleParserOptionsHook;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use ParserOptions;
use Skin;

class Hooks implements
	GetPreferencesHook,
	SidebarBeforeOutputHook,
	ArticleParserOptionsHook,
	ParserOutputPostCacheTransformHook
{

	private Config $mainConfig;
	private UserOptionsManager $userOptionsManager;

	private const USERPREF_ALWAYS = 1;
	private const USERPREF_DEFAULT = 0;
	private const USERPREF_NEVER = 2;

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
		$defaultPreferences['parsermigration-parsoid-readviews'] = [
			'type' => 'select',
			'label-message' => 'parsermigration-parsoid-readviews-selector-label',
			'help-message' => 'parsermigration-parsoid-readviews-selector-help',
			'section' => 'editing/developertools',
			'options-messages' => [
				'parsermigration-parsoid-readviews-always' => self::USERPREF_ALWAYS,
				'parsermigration-parsoid-readviews-default' => self::USERPREF_DEFAULT,
				'parsermigration-parsoid-readviews-never' => self::USERPREF_NEVER,
			],
		];

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
		// T348257: Allow individual user to opt in to Parsoid read views as a
		// use option in the ParserMigration section.
		$context = $article->getContext();
		if ( $this->shouldUseParsoid( $context->getUser(), $context->getRequest(), $article->getTitle() ) ) {
			$popts->setUseParsoid();
		}
		return true;
	}

	/**
	 * This hook is called from ParserOutput::getText() to do
	 * post-cache transforms.
	 *
	 * @since 1.35
	 *
	 * @param ParserOutput $parserOutput
	 * @param string &$text Text being transformed, before core transformations are done
	 * @param array &$options Options array being used for the transformation
	 * @return void This hook must not abort, it must return no value
	 */
	public function onParserOutputPostCacheTransform( $parserOutput, &$text,
		&$options
	): void {
		// Make "whether Parsoid was used" visible to client-side JS
		if ( $options['isParsoidContent'] ?? false ) {
			$parserOutput->setJsConfigVar( 'parsermigration-parsoid', true );
			// Add a user notice
			$parserOutput->setJsConfigVar(
				'parsermigration-notice-version',
				$this->mainConfig->get(
					'ParserMigrationUserNoticeVersion'
				)
			);
			$parserOutput->setJsConfigVar(
				'parsermigration-notice-days',
				$this->mainConfig->get(
					'ParserMigrationUserNoticeDays'
				)
			);
			$parserOutput->addModules( [ 'ext.parsermigration.notice' ] );
		}
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

		$queryStringEnabled = $this->mainConfig->get(
			'ParserMigrationEnableQueryString'
		);
		if ( !$queryStringEnabled ) {
			// Early exit from those wikis where we don't want the
			// user to be able to put Parsoid pages into the parser cache.
			return;
		}

		$user = $skin->getUser();
		$title = $skin->getTitle();

		$editToolPref = $this->userOptionsManager->getOption(
			$user, 'parsermigration'
		);
		$userPref = intval( $this->userOptionsManager->getOption(
			$user, 'parsermigration-parsoid-readviews'
		) );

		$shouldShowToggle = false;
		if ( $editToolPref ) {
			$sidebar['TOOLBOX']['parsermigration'] = [
				'href' => $title->getLocalURL( [
					'action' => 'parsermigration-edit',
				] ),
				'text' => $skin->msg( 'parsermigration-toolbox-label' )->text(),
			];
			$shouldShowToggle = true;
		}
		if ( $this->isParsoidDefaultFor( $title ) ) {
			$shouldShowToggle = true;
		}
		if ( $userPref === self::USERPREF_ALWAYS ) {
			$shouldShowToggle = true;
		}

		if ( $shouldShowToggle ) {
			$usingParsoid = $this->shouldUseParsoid( $user, $skin->getRequest(), $title );
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
	 * Determine whether Parsoid should be used by default on this page,
	 * based on per-wiki configuration.  User preferences and query
	 * string parameters are not consulted.
	 * @param Title $title
	 * @return bool
	 */
	private function isParsoidDefaultFor( Title $title ): bool {
		$articlePagesEnabled = $this->mainConfig->get(
			'ParserMigrationEnableParsoidArticlePages'
		);
		// This enables Parsoid on all talk pages, which isn't *exactly*
		// the same as "the set of pages where DiscussionTools is enabled",
		// but it will do for now.
		$talkPagesEnabled = $this->mainConfig->get(
			'ParserMigrationEnableParsoidDiscussionTools'
		);
		if ( $title->isTalkPage() ? $talkPagesEnabled : $articlePagesEnabled ) {
			return true;
		}
		return false;
	}

	/**
	 * Determine whether to use Parsoid for read views on this request,
	 * request, based on the user's preferences and the URL query string.
	 *
	 * @param User $user
	 * @param WebRequest $request
	 * @param Title $title
	 * @return bool True if Parsoid should be used for this request
	 */
	private function shouldUseParsoid( User $user, WebRequest $request, Title $title ): bool {
		// Find out if the user has opted in to Parsoid Read Views by default
		$userPref = intval( $this->userOptionsManager->getOption(
			$user,
			'parsermigration-parsoid-readviews'
		) );
		$userOptIn = $this->isParsoidDefaultFor( $title );
		if ( $userPref === self::USERPREF_ALWAYS ) {
			$userOptIn = true;
		}
		if ( $userPref === self::USERPREF_NEVER ) {
			$userOptIn = false;
		}

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
