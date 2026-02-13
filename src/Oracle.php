<?php

namespace MediaWiki\Extension\ParserMigration;

use ExtensionRegistry;
use MediaWiki\Config\Config;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MobileContext;

class Oracle {

	public const USERPREF_ALWAYS = 1;
	public const USERPREF_DEFAULT = 0;
	public const USERPREF_NEVER = 2;

	private Hook\HookRunner $hookRunner;

	public function __construct(
		private readonly Config $mainConfig,
		private readonly UserOptionsManager $userOptionsManager,
		private HookContainer $hookContainer,
		private readonly ?MobileContext $mobileContext,
	) {
		$this->hookRunner = new Hook\HookRunner( $hookContainer );
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
		// Check if the content model is allowed for parser migration
		if ( !$this->isContentModelAllowed( $title ) ) {
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

		// Allow a hook to opt-in a user (for example, as part of an
		// experimental intervention).
		$this->hookRunner->onShouldUseParsoid(
			$user,
			$request,
			$title,
			$userOptIn
		);

		// Allow disabling query string handling via config change to manage
		// parser cache usage.
		$queryStringEnabled = $this->mainConfig->get(
			'ParserMigrationEnableQueryString'
		);
		if ( !$queryStringEnabled ) {
			// Ignore query string and use Parsoid read views if and only
			// if the user has opted in / the hook indicates we should.
			return $userOptIn;
		}

		// Otherwise, use the user's opt-in status to set the default for
		// query string processing.
		// (Query string needs to be able to override $userOptIn in order to
		// allow the "switch to legacy"/"switch to Parsoid" options in the
		// sidebar to work.)
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

		// Incremental deploys (T391881)
		// (avoid md5 hash unless needed for incremental deploy)
		$percentage = $this->mainConfig->get( 'ParserMigrationEnableParsoidPercentage' );
		if ( $isEnabled && ( $percentage < 100 ) ) {
			$key = $title->getNamespace() . ':' . $title->getDBkey();
			$hash = hexdec( substr( md5( $key ), 0, 8 ) ) & 0x7fffffff;
			if ( ( $hash % 100 ) >= $percentage ) {
				$isEnabled = false;
			}
		}

		return $isEnabled;
	}

	/** Proxy MobileContext::shouldDisplayMobileView() */
	public function showingMobileView(): bool {
		return $this->mobileContext &&
			$this->mobileContext->shouldDisplayMobileView();
	}

	/**
	 * Check if a content model is allowed for parser migration.
	 * This includes wikitext and any content models registered by extensions
	 * via the AllowedContentModels attribute.
	 *
	 * @param Title $title
	 * @return bool
	 */
	private function isContentModelAllowed( Title $title ): bool {
		// Always allow wikitext
		if ( $title->hasContentModel( CONTENT_MODEL_WIKITEXT ) ) {
			return true;
		}

		// Get content models registered by extensions
		$extensionContentModels = ExtensionRegistry::getInstance()
			->getAttribute( 'ParserMigrationAllowedContentModels' );

		// Check if the title's content model is in the registered list
		if ( in_array( $title->getContentModel(), $extensionContentModels, true ) ) {
			return true;
		}

		return false;
	}
}
