<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\ChangeTags\Hook\ChangeTagsAllowedAddHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserOutputPostCacheTransformHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\Page\Hook\ArticleParserOptionsHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

class Hooks implements
	ArticleParserOptionsHook,
	GetPreferencesHook,
	ParserOutputPostCacheTransformHook,
	ResourceLoaderGetConfigVarsHook,
	SidebarBeforeOutputHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	ChangeTagsAllowedAddHook
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
		$user = null;
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

			if ( $user === null || $this->shouldShowReportVisualBug( $user ) ) {
				// Harmless to add this module if we don't know user.
				$parserOutput->addModules( [ 'ext.parsermigration.reportbug.init' ] );
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

		if ( $usingParsoid && $this->shouldShowReportVisualBug( $user ) ) {
			// Add "report visual bug" sidebar link
			$href = $this->getFeedbackTitleUrl();
			$sidebar[ 'TOOLBOX' ][ 'parsermigration-report-bug' ] = [
				'html' => Html::rawElement( 'a', [
					// This will be overridden in JavaScript, but is a useful
					// fallback for no-javascript browsers and unnamed users
					'href' => $href,
					'title' => $skin->msg(
						'parsermigration-report-bug-toolbox-title'
					)->text(),
				], $skin->msg(
						'parsermigration-report-bug-toolbox-label'
				)->parse() . '&nbsp;' . Html::rawElement( 'span', [
					'class' => "parsermigration-report-bug-icon",
				] ) ),
				'id' => 'parsermigration-report-bug',
				// These properties are for the mobile skins
				'text' => $skin->msg( 'parsermigration-report-bug-toolbox-label' ),
				'href' => $href,
				'icon' => 'feedback',
			];
		}
	}

	/**
	 * Adds extra variables to the global config
	 *
	 * @param array &$vars Global variables object
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		if ( $this->mainConfig->get( 'ParserMigrationEnableReportVisualBug' ) ) {
			$vars['wgParserMigrationConfig'] = [
				'onlyLoggedIn' => $this->mainConfig->get( 'ParserMigrationEnableReportVisualBugOnlyLoggedIn' ),
				'isMobile' => $this->oracle->showingMobileView(),
				'feedbackApiUrl' => $this->mainConfig->get( 'ParserMigrationFeedbackAPIURL' ),
				'feedbackTitle' => $this->mainConfig->get( 'ParserMigrationFeedbackTitle' ),
				'iwp' => WikiMap::getCurrentWikiId(),
			];
		}
	}

	/**
	 * A change tag for "report visual bug" reports.
	 * @inheritDoc
	 */
	public function onListDefinedTags( &$tags ) {
		$tags[] = 'parsermigration-visual-bug';
		return true;
	}

	/**
	 * All tags are still active.
	 * @inheritDoc
	 */
	public function onChangeTagsListActive( &$tags ) {
		return $this->onListDefinedTags( $tags );
	}

	/**
	 * MessagePoster acts on behalf of the user, so the
	 * user (any user) needs to be allowed to add this tag.
	 * @inheritDoc
	 */
	public function onChangeTagsAllowedAdd( &$allowedTags, $addTags, $user ) {
		return $this->onListDefinedTags( $allowedTags );
	}

	/**
	 * Suppress captcha when posting to the "Report Visual Bug" feedback
	 * page.
	 * @param string $action Action user is performing, one of sendmail,
	 *  createaccount, badlogin, edit, create, addurl.
	 * @param PageIdentity|null $page
	 * @param bool &$result
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onConfirmEditTriggersCaptcha(
		string $action,
		?PageIdentity $page,
		bool &$result
	) {
		// If we don't have a target page, bail.
		if ( $page === null ) {
			return true;
		}
		// We're only going to intervene if this is an edit or create
		// ('create' since this could be the first report posted)
		// 'addurl' because our subject lines link to the page which is
		// the subject of the report.
		if ( !( $action === 'edit' || $action === 'create' || $action === 'addurl' ) ) {
			return true;
		}
		$services = MediaWikiServices::getInstance();
		$mainConfig = $services->getMainConfig();
		// If the Report Visual Bug tool is not enabled, bail.
		if ( !$mainConfig->get( 'ParserMigrationEnableReportVisualBug' ) ) {
			return true;
		}
		$apiUrl = $mainConfig->get( 'ParserMigrationFeedbackAPIURL' );
		// If a foreign API URL is set, we can't do anything to help.
		if ( $apiUrl ) {
			return true;
		}
		$title = $mainConfig->get( 'ParserMigrationFeedbackTitle' ) ?:
			wfMessage( 'parsermigration-reportbug-feedback-title' )->plain();
		$titleValue = $services->getTitleParser()->parseTitle( $title );
		// If the page doesn't match our feedback page, do nothing.
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		if ( !$titleValue->isSameLinkAs( TitleValue::castPageToLinkTarget( $page ) ) ) {
			return true;
		}
		// Ok, this is our page!  Suppress the captcha.
		$result = false;
		return false;
	}

	private function getFeedbackTitleUrl(): string {
		$apiURL = $this->mainConfig->get( 'ParserMigrationFeedbackAPIURL' );
		$url = $this->mainConfig->get( 'ParserMigrationFeedbackTitleURL' );
		if ( $apiURL && $url ) {
			return $url;
		}
		$title = Title::newFromText(
			$this->mainConfig->get( 'ParserMigrationFeedbackTitle' ) ?:
			wfMessage( 'parsermigration-reportbug-feedback-title' )->plain()
		);
		return $title->getLinkURL();
	}

	protected function shouldShowReportVisualBug( User $user ): bool {
		if ( !$this->mainConfig->get( 'ParserMigrationEnableReportVisualBug' ) ) {
			return false;
		}
		if ( $user->isNamed() ) {
			return true;
		}
		return !$this->mainConfig->get( 'ParserMigrationEnableReportVisualBugOnlyLoggedIn' );
	}
}
