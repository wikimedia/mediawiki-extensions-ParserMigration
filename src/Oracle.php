<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\Config\Config;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MobileContext;

class Oracle {

	public const USERPREF_ALWAYS = 1;
	public const USERPREF_DEFAULT = 0;
	public const USERPREF_NEVER = 2;

	public function __construct(
		private readonly Config $mainConfig,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly ?MobileContext $mobileContext,
	) {
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
	public function shouldUseParsoid( User $user, WebRequest $request, Title $title ): bool {
		// Use Parsoid only for wikitext pages
		if ( !$title->hasContentModel( CONTENT_MODEL_WIKITEXT ) ) {
			return false;
		}

		// Find out if the user has opted in to Parsoid Read Views by default
		$userPref = intval( $this->userOptionsManager->getOption(
			$user,
			'parsermigration-parsoid-readviews'
		) );
		$userOptIn = $this->isParsoidDefaultFor( $title );

		// Override the default if a preference is set
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

	/**
	 * Determine whether Parsoid should be used by default on this page,
	 * based on per-wiki configuration.  User preferences and query
	 * string parameters are not consulted.
	 * @param Title $title
	 * @return bool
	 */
	public function isParsoidDefaultFor( Title $title ): bool {
		$articlePagesEnabled = $this->mainConfig->get(
			'ParserMigrationEnableParsoidArticlePages'
		);
		// This enables Parsoid on all talk pages, which isn't *exactly*
		// the same as "the set of pages where DiscussionTools is enabled",
		// but it will do for now.
		$talkPagesEnabled = $this->mainConfig->get(
			'ParserMigrationEnableParsoidDiscussionTools'
		);

		$isEnabled = $title->isTalkPage() ? $talkPagesEnabled : $articlePagesEnabled;

		// Exclude mobile domains by default, regardless of the namespace settings
		// above, if the config isn't on
		$disableOnMobile =
			!$this->mainConfig->get( 'ParserMigrationEnableParsoidMobileFrontend' );
		if (
			$title->isTalkPage() &&
			!$this->mainConfig->get( 'ParserMigrationEnableParsoidMobileFrontendTalkPages' )
		) {
			$disableOnMobile = true;
		}
		if (
			$disableOnMobile && $this->showingMobileView()
		) {
			$isEnabled = false;
		}

		return $isEnabled;
	}

	/** Proxy MobileContext::shouldDisplayMobileView() */
	public function showingMobileView(): bool {
		return $this->mobileContext &&
			$this->mobileContext->shouldDisplayMobileView();
	}
}
