<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserOutputPostCacheTransformHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Html\Html;
use MediaWiki\Page\Article;
use MediaWiki\Page\Hook\ArticleParserOptionsHook;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Skin\Skin;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;

class Hooks implements
	GetPreferencesHook,
	SidebarBeforeOutputHook,
	ArticleParserOptionsHook,
	ParserOutputPostCacheTransformHook
{

	private Config $mainConfig;
	private UserOptionsManager $userOptionsManager;
	private Oracle $oracle;

	/**
	 * @param Config $mainConfig
	 * @param UserOptionsManager $userOptionsManager
	 * @param Oracle $oracle
	 */
	public function __construct(
		Config $mainConfig,
		UserOptionsManager $userOptionsManager,
		Oracle $oracle
	) {
		$this->mainConfig = $mainConfig;
		$this->userOptionsManager = $userOptionsManager;
		$this->oracle = $oracle;
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
				'parsermigration-parsoid-readviews-always' => Oracle::USERPREF_ALWAYS,
				'parsermigration-parsoid-readviews-default' => Oracle::USERPREF_DEFAULT,
				'parsermigration-parsoid-readviews-never' => Oracle::USERPREF_NEVER,
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
		// user option in the ParserMigration section.
		$context = $article->getContext();
		if ( $this->oracle->shouldUseParsoid( $context->getUser(), $context->getRequest(), $article->getTitle() ) ) {
			$popts->setUseParsoid();
		}
		return true;
	}

	/**
	 * This hook is called from ParserOutput::runOutputPipeline() to do
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
			// Add a user notice for named users
			$named = false;
			$userPref = Oracle::USERPREF_DEFAULT;
			if ( $options['skin'] ?? null ) {
				$user = $options['skin']->getUser();
				$named = $user->isNamed();
				$userPref = intval( $this->userOptionsManager->getOption(
					$user, 'parsermigration-parsoid-readviews'
				) );
			}
			if (
				$this->mainConfig->get( 'ParserMigrationEnableUserNotice' ) &&
				// Only display user notice for logged in ("named") users
				$named
			) {
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
			if (
				$this->mainConfig->get( 'ParserMigrationEnableIndicator' ) &&
				// Only display indicator for "opt in always" users.
				$userPref === Oracle::USERPREF_ALWAYS
			) {
				// Add an indicator using an ad-hoc Codex InfoChip
				// Replace when T357324 blesses a CSS-only InfoChip
				$parserOutput->addModuleStyles( [ 'ext.parsermigration.indicator' ] );
				$parserOutput->setIndicator(
					'parsoid',
					$this->mainConfig->get( 'ParserMigrationCompactIndicator' ) ?
					Html::rawElement(
						'div',
						[
							'class' => 'mw-parsoid-icon notheme mw-no-invert',
							'title' => wfMessage( 'parsermigration-parsoid-chip-label' )->text(),
						]
					) :
					Html::rawElement(
						'div',
						[ 'class' => 'cdx-info-chip' ],
						Html::element(
							'span',
							[ 'class' => 'cdx-info-chip--text' ],
							wfMessage( 'parsermigration-parsoid-chip-label' )->text()
						)
					)
				);
			}
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

		$user = $skin->getUser();
		$title = $skin->getTitle();
		$usingParsoid = $this->oracle->shouldUseParsoid( $user, $skin->getRequest(), $title );

		$queryStringEnabled = $this->mainConfig->get(
			'ParserMigrationEnableQueryString'
		);
		$reportVisualBugEnabled = $this->mainConfig->get(
			'ParserMigrationEnableReportVisualBug'
		);

		$editToolPref = $this->userOptionsManager->getOption(
			$user, 'parsermigration'
		);
		$userPref = intval( $this->userOptionsManager->getOption(
			$user, 'parsermigration-parsoid-readviews'
		) );

		$shouldShowToggle = false;
		if ( $editToolPref && $queryStringEnabled ) {
			$sidebar['TOOLBOX']['parsermigration-edit-tool'] = [
				'href' => $title->getLocalURL( [
					'action' => 'parsermigration-edit',
				] ),
				'text' => $skin->msg( 'parsermigration-toolbox-label' )->text(),
			];
			$shouldShowToggle = true;
		}
		if ( $this->oracle->isParsoidDefaultFor( $title ) ) {
			$shouldShowToggle = true;
		}
		if ( $userPref === Oracle::USERPREF_ALWAYS ) {
			$shouldShowToggle = true;
		}
		if ( !$queryStringEnabled ) {
			// On some wikis we don't want the user to be able to put
			// Parsoid pages into the parser cache.
			$shouldShowToggle = false;
		}

		if ( $shouldShowToggle ) {
			$queryParams = $out->getRequest()->getQueryValues();
			// Allow toggling 'useParsoid' from the current state
			$queryParams[ 'useparsoid' ] = $usingParsoid ? '0' : '1';
			// title is handled by getLocalURL, no need to pass it twice from a index.php?title= url
			unset( $queryParams[ 'title' ] );
			$sidebar[ 'TOOLBOX' ][ 'parsermigration-switch' ] = [
				'href' => $title->getLocalURL( $queryParams ),
				'text' => $skin->msg(
					$usingParsoid ?
					'parsermigration-use-legacy-parser-toolbox-label' :
					'parsermigration-use-parsoid-toolbox-label'
				)->text(),
			];
		}

		if ( $usingParsoid && $reportVisualBugEnabled ) {
			// Add "report visual bug" sidebar link
			$sidebar[ 'TOOLBOX' ][ 'parsermigration-report-bug' ] = [
				// This will be overridden in JavaScript
				'href' => 'https://www.mediawiki.org/wiki/Special:MyLanguage/Parsoid/Parser_Unification/Known_Issues',
				'text' => $skin->msg(
					'parsermigration-report-bug-toolbox-label'
				)->text(),
				'title' => $skin->msg(
					'parsermigration-report-bug-toolbox-title'
				)->text(),
				'id' => 'parsermigration-report-bug',
				'icon' => 'help',
			];
		}
	}
}
